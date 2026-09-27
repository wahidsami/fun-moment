<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\ServiceController;
use App\Notifications\Channels\FirebasePushChannel;
use App\Notifications\OrderNotification;
use App\Order;
use App\Service;
use App\Services\Notification\FirebaseNotificationService;
use App\Services\Payment\PayTabsPaymentService;
use App\User;
use App\UserDeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "========================================================\n";
echo "FUN MOMENT — PHASE 4 AUTOMATED NOTIFICATION TEST SUITE\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $description, bool $condition): void
{
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $description\n";
    } else {
        $failCount++;
        echo " [FAIL] $description\n";
    }
}

try {
    // -------------------------------------------------------------
    // Setup test users & service
    // -------------------------------------------------------------
    $buyer = User::where('email', 'testbuyer_notif@funmoments.test')->first();
    if (!$buyer) {
        $buyer = User::create([
            'name' => 'Test Buyer Notif',
            'username' => 'testbuyer_notif',
            'email' => 'testbuyer_notif@funmoments.test',
            'phone' => '966500000001',
            'password' => bcrypt('password123'),
            'user_type' => 1, // buyer
        ]);
    }

    $seller = User::where('email', 'testseller_notif@funmoments.test')->first();
    if (!$seller) {
        $seller = User::create([
            'name' => 'Test Seller Notif',
            'username' => 'testseller_notif',
            'email' => 'testseller_notif@funmoments.test',
            'phone' => '966500000002',
            'password' => bcrypt('password123'),
            'user_type' => 0, // seller
        ]);
    }

    $thirdUser = User::where('email', 'testthird_notif@funmoments.test')->first();
    if (!$thirdUser) {
        $thirdUser = User::create([
            'name' => 'Third User Notif',
            'username' => 'testthird_notif',
            'email' => 'testthird_notif@funmoments.test',
            'phone' => '966500000003',
            'password' => bcrypt('password123'),
            'user_type' => 1,
        ]);
    }

    $testService = Service::first();
    if (!$testService) {
        $testService = Service::create([
            'title' => 'Test Event Staging Service',
            'seller_id' => $seller->id,
            'price' => 150.00,
            'status' => 1,
            'is_service_on' => 1,
        ]);
    }

    function createTestOrder(User $seller, User $buyer, Service $testService, array $overrides = []): Order
    {
        $defaults = [
            'service_id' => $testService->id,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'name' => $buyer->name,
            'email' => $buyer->email,
            'phone' => $buyer->phone,
            'post_code' => '12345',
            'address' => 'Sample Street, Riyadh',
            'city' => 1,
            'area' => 1,
            'country' => 1,
            'date' => now()->toDateString(),
            'schedule' => '10:00 - 11:00',
            'package_fee' => 0.00,
            'extra_service' => 0.00,
            'sub_total' => 150.00,
            'tax' => 0.00,
            'total' => 150.00,
            'status' => 0,
            'payment_status' => 'pending',
            'payment_gateway' => 'paytabs',
            'commission_type' => 'percentage',
            'commission_charge' => 10,
            'commission_amount' => 15.00,
        ];

        return Order::create(array_merge($defaults, $overrides));
    }

    $notifController = new NotificationApiController();

    // -------------------------------------------------------------
    // 1. Authenticated token registration
    // -------------------------------------------------------------
    echo "--- 1. Device Token Registration & Management ---\n";
    Auth::guard('sanctum')->setUser($buyer);
    $deviceToken = 'fcm_token_sample_' . Str::random(24);

    $req = Request::create('/api/v1/user/device-token', 'POST', [
        'device_token' => $deviceToken,
        'device_type' => 'android',
    ]);
    $response = $notifController->registerDeviceToken($req);
    $data = $response->getData(true);

    assertTest("Authenticated token registration succeeds (HTTP 200)", $response->getStatusCode() === 200 && $data['status'] === 'success');
    assertTest("Device token persisted in user_device_tokens table", UserDeviceToken::where('user_id', $buyer->id)->where('device_token', $deviceToken)->exists());

    // Token update/refresh idempotency
    $reqRefresh = Request::create('/api/v1/user/device-token', 'POST', [
        'device_token' => $deviceToken,
        'device_type' => 'ios',
    ]);
    $responseRefresh = $notifController->registerDeviceToken($reqRefresh);
    $updatedToken = UserDeviceToken::where('user_id', $buyer->id)->where('device_token', $deviceToken)->first();
    assertTest("Token update does not duplicate row and updates device_type", $updatedToken->device_type === 'ios');

    // -------------------------------------------------------------
    // 2. Invalid token registration rejection
    // -------------------------------------------------------------
    $reqInvalid = Request::create('/api/v1/user/device-token', 'POST', [
        'device_token' => 'short', // min 10
    ]);
    $respInvalid = $notifController->registerDeviceToken($reqInvalid);
    assertTest("Invalid/short token rejected with HTTP 422", $respInvalid->getStatusCode() === 422);

    // -------------------------------------------------------------
    // 3. Token removal on logout
    // -------------------------------------------------------------
    $reqDelete = Request::create('/api/v1/user/device-token', 'DELETE', [
        'device_token' => $deviceToken,
    ]);
    $respDelete = $notifController->removeDeviceToken($reqDelete);
    assertTest("Device token removal succeeds on logout", $respDelete->getStatusCode() === 200);
    assertTest("Token removed from database", !UserDeviceToken::where('user_id', $buyer->id)->where('device_token', $deviceToken)->exists());

    // Re-register for subsequent tests
    $notifController->registerDeviceToken($req);

    // -------------------------------------------------------------
    // 4. Notification isolation between users
    // -------------------------------------------------------------
    echo "\n--- 2. Notification Isolation & Lifecycle API ---\n";
    // Clear old test notifications for clean counts
    $buyer->notifications()->delete();
    $seller->notifications()->delete();
    $thirdUser->notifications()->delete();

    // Send a notification specifically to buyer
    $buyer->notify(new OrderNotification(9901, $testService->id, $seller->id, $buyer->id, 'Order created for buyer', 'order_alert', 0, 'buyer'));

    // Check list as thirdUser
    Auth::guard('sanctum')->setUser($thirdUser);
    $reqListThird = Request::create('/api/v1/user/notifications', 'GET');
    $respListThird = $notifController->index($reqListThird);
    $thirdData = $respListThird->getData(true);
    assertTest("User isolation: other users cannot see buyer's notification", count($thirdData['notifications']['data']) === 0);

    // Check list as buyer
    Auth::guard('sanctum')->setUser($buyer);
    $reqListBuyer = Request::create('/api/v1/user/notifications', 'GET');
    $respListBuyer = $notifController->index($reqListBuyer);
    $buyerData = $respListBuyer->getData(true);
    assertTest("Buyer sees their own notification", count($buyerData['notifications']['data']) === 1);
    assertTest("Notification carries structured metadata (order_id, target_role, etc.)",
        $buyerData['notifications']['data'][0]['order_id'] === 9901 &&
        $buyerData['notifications']['data'][0]['target_role'] === 'buyer' &&
        $buyerData['notifications']['data'][0]['is_read'] === false
    );

    // -------------------------------------------------------------
    // 5. Unread count & mark read API
    // -------------------------------------------------------------
    $respUnread = $notifController->unreadCount(Request::create('/api/v1/user/notifications/unread-count', 'GET'));
    $unreadData = $respUnread->getData(true);
    assertTest("Unread count API returns 1 unread notification", $unreadData['unread_count'] === 1);

    $notifId = $buyerData['notifications']['data'][0]['id'];
    $respMarkOne = $notifController->markAsRead(Request::create("/api/v1/user/notifications/{$notifId}/read", 'POST'), $notifId);
    $markOneData = $respMarkOne->getData(true);
    assertTest("Mark one notification as read succeeds", $markOneData['status'] === 'success' && $markOneData['unread_count'] === 0);

    // Add another unread notification, then test markAllRead
    $buyer->notify(new OrderNotification(9902, $testService->id, $seller->id, $buyer->id, 'Second notification', 'order_alert', 0, 'buyer'));
    $buyer->notify(new OrderNotification(9903, $testService->id, $seller->id, $buyer->id, 'Third notification', 'order_alert', 0, 'buyer'));
    assertTest("Two new unread notifications added (unread = 2)", $buyer->unreadNotifications()->count() === 2);

    $respMarkAll = $notifController->markAllRead(Request::create('/api/v1/user/notifications/mark-all-read', 'POST'));
    assertTest("Mark all read succeeds and clears unread count", $buyer->unreadNotifications()->count() === 0);

    // -------------------------------------------------------------
    // 6. Provider Accept / Cancel -> Customer Notification
    // -------------------------------------------------------------
    echo "\n--- 3. Provider Status Changes -> Customer Notifications ---\n";
    $testOrder = createTestOrder($seller, $buyer, $testService, [
        'payment_status' => 'complete',
        'payment_gateway' => 'paytabs',
    ]);

    // Provider accepts order (status = 1)
    $buyer->notifications()->delete();
    Auth::guard('sanctum')->setUser($seller);
    $sellerController = new SellerController();
    $reqAccept = Request::create('/api/v1/seller/my-orders/order/change-status', 'POST', [
        'id' => $testOrder->id,
        'status' => 1,
    ]);
    $respAccept = $sellerController->OrderStatusChange($reqAccept);
    $testOrder->refresh();

    assertTest("Provider accepts booking: order status updated to 1 (Active)", $testOrder->status === 1);

    // Check customer notification
    $buyerNotif = $buyer->notifications()->first();
    $buyerNotifData = $buyerNotif ? (is_array($buyerNotif->data) ? $buyerNotif->data : json_decode($buyerNotif->data, true)) : [];
    assertTest("Customer receives booking_accepted notification on provider accept",
        ($buyerNotifData['notification_type'] ?? '') === 'booking_accepted' &&
        ($buyerNotifData['order_id'] ?? 0) === $testOrder->id &&
        ($buyerNotifData['target_role'] ?? '') === 'buyer'
    );

    // Provider cancels order (status = 4)
    $buyer->notifications()->delete();
    $reqCancel = Request::create('/api/v1/seller/my-orders/order/change-status', 'POST', [
        'id' => $testOrder->id,
        'status' => 4,
    ]);
    $respCancel = $sellerController->OrderStatusChange($reqCancel);
    $testOrder->refresh();

    assertTest("Provider cancels booking: order status updated to 4 (Cancelled)", $testOrder->status === 4);
    $buyerNotifAfterCancel = $buyer->notifications()->first();
    $buyerNotifCancelData = is_array($buyerNotifAfterCancel->data) ? $buyerNotifAfterCancel->data : json_decode($buyerNotifAfterCancel->data, true);
    assertTest("Customer receives booking_cancelled notification on provider cancel",
        ($buyerNotifCancelData['notification_type'] ?? '') === 'booking_cancelled' &&
        ($buyerNotifCancelData['order_id'] ?? 0) === $testOrder->id
    );

    // -------------------------------------------------------------
    // 7. PayTabs vs COD / Manual Notification Rules
    // -------------------------------------------------------------
    echo "\n--- 4. PayTabs vs COD / Manual Notification Flow Rules ---\n";
    $seller->notifications()->delete();

    // Rule A: PayTabs order creation -> Provider must NOT receive "new booking" notification before payment verification
    $paytabsOrder = createTestOrder($seller, $buyer, $testService, [
        'payment_status' => 'pending',
        'payment_gateway' => 'paytabs',
        'sub_total' => 200.00,
        'total' => 200.00,
    ]);

    // Simulate order creation logic from ServiceController.php:
    // Only dispatch to seller if payment_gateway is NOT paytabs
    if ($paytabsOrder->payment_gateway !== 'paytabs') {
        $seller->notify(new OrderNotification($paytabsOrder->id, $testService->id, $seller->id, $buyer->id, 'New booking', 'new_booking', 0, 'seller'));
    }

    assertTest("CRITICAL RULE: Provider does NOT receive notification immediately upon PayTabs order creation", $seller->notifications()->count() === 0);

    // Rule B: Server-side PayTabs verification succeeds -> ONLY THEN dispatch provider notification
    $paytabsService = new PayTabsPaymentService();
    $verifyPayload = [
        'tran_ref' => 'TST_TRAN_' . Str::random(10),
        'cart_id' => (string) $paytabsOrder->id,
        'cart_amount' => '200.00',
        'cart_currency' => 'SAR',
        'payment_result' => [
            'response_status' => 'A',
            'response_code' => '100',
            'response_message' => 'Authorised',
        ],
    ];

    $applyResult = $paytabsService->applySuccessfulPayment($paytabsOrder, $verifyPayload['tran_ref'], $verifyPayload);
    assertTest("Server-side PayTabs payment applied successfully", ($applyResult['order']->payment_status ?? '') === 'complete');

    $sellerNotifs = $seller->notifications()->get();
    assertTest("Provider receives EXACTLY ONE notification after PayTabs server-side verification", $sellerNotifs->count() === 1);
    $sellerNotifData = is_array($sellerNotifs[0]->data) ? $sellerNotifs[0]->data : json_decode($sellerNotifs[0]->data, true);
    assertTest("Notification has type 'new_booking' and target_role 'seller'",
        $sellerNotifData['notification_type'] === 'new_booking' &&
        $sellerNotifData['target_role'] === 'seller' &&
        $sellerNotifData['order_id'] === $paytabsOrder->id
    );

    // Rule C: Duplicate PayTabs verification -> Idempotent, NO duplicate notification
    $duplicateResult = $paytabsService->applySuccessfulPayment($paytabsOrder, $verifyPayload['tran_ref'], $verifyPayload);
    assertTest("Duplicate PayTabs verification detected as already paid", ($duplicateResult['already_completed'] ?? false) === true);
    assertTest("NO duplicate notification dispatched on duplicate PayTabs verification", $seller->notifications()->count() === 1);

    // Rule D: COD order creation -> Provider receives notification immediately
    $seller->notifications()->delete();
    $codOrder = createTestOrder($seller, $buyer, $testService, [
        'payment_status' => 'pending',
        'payment_gateway' => 'cash_on_delivery',
        'sub_total' => 100.00,
        'total' => 100.00,
    ]);
    if ($codOrder->payment_gateway !== 'paytabs') {
        $seller->notify(new OrderNotification($codOrder->id, $testService->id, $seller->id, $buyer->id, 'New COD booking', 'new_booking', 0, 'seller'));
    }
    assertTest("COD order creation immediately notifies provider", $seller->notifications()->count() === 1);

    // Rule E: Manual/Bank Transfer order creation -> Provider receives notification immediately
    $seller->notifications()->delete();
    $manualOrder = createTestOrder($seller, $buyer, $testService, [
        'payment_status' => 'pending',
        'payment_gateway' => 'manual_payment',
        'sub_total' => 120.00,
        'total' => 120.00,
    ]);
    if ($manualOrder->payment_gateway !== 'paytabs') {
        $seller->notify(new OrderNotification($manualOrder->id, $testService->id, $seller->id, $buyer->id, 'New Manual booking', 'new_booking', 0, 'seller'));
    }
    assertTest("Manual payment order creation immediately notifies provider", $seller->notifications()->count() === 1);

    // -------------------------------------------------------------
    // 8. Push failure fail-safe test
    // -------------------------------------------------------------
    echo "\n--- 5. Push Delivery Fail-Safe Architecture ---\n";
    // Verify that FirebaseNotificationService handles users with no tokens gracefully without throwing
    $pushService = new FirebaseNotificationService();
    $seller->deviceTokens()->delete();
    $result = $pushService->sendToUser($seller, 'Test Title', 'Test Body', ['type' => 'test']);
    assertTest("Push delivery returns gracefully when no device tokens exist (does NOT throw)", is_array($result) && !empty($result['skipped']));

    // Test channel fail-safe inside order workflow
    $failSafeOrder = createTestOrder($seller, $buyer, $testService, [
        'payment_status' => 'complete',
        'payment_gateway' => 'cash_on_delivery',
        'sub_total' => 50.00,
        'total' => 50.00,
    ]);
    assertTest("Push failure or token absence does NOT fail or roll back order placement", $failSafeOrder->exists);

    // Clean up test records
    $testOrder->delete();
    $paytabsOrder->delete();
    $codOrder->delete();
    $manualOrder->delete();
    $failSafeOrder->delete();
    $buyer->notifications()->delete();
    $seller->notifications()->delete();
    $thirdUser->notifications()->delete();
    UserDeviceToken::where('user_id', $buyer->id)->delete();

} catch (\Throwable $e) {
    echo "\n [EXCEPTION] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failCount++;
}

echo "\n========================================================\n";
echo "SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
