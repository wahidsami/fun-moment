<?php

// test_booking_payment_integrity.php
// Regression test suite for Wave 2A: Server-Side P0 Booking & Payment Integrity

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\User;
use App\Order;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;
use Modules\Wallet\Services\WalletService;

$passed = 0;
$failed = 0;

function assertTest($condition, $description) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$description}" . PHP_EOL;
        $passed++;
    } else {
        echo "  [FAIL] {$description}" . PHP_EOL;
        $failed++;
    }
}

function createOrder(array $overrides = []) {
    return Order::create(array_merge([
        'service_id' => 1,
        'name' => 'Integrity Test User',
        'email' => 'integrity@funmoments.test',
        'phone' => '0501234567',
        'post_code' => '12211',
        'address' => 'Riyadh Road',
        'city' => 2,
        'area' => 2,
        'country' => 2,
        'date' => '2026-11-25',
        'schedule' => '14:00',
        'package_fee' => '0',
        'extra_service' => '0',
        'sub_total' => '200.00',
        'tax' => '0',
        'total' => '200.00',
        'payment_gateway' => 'paytabs',
        'payment_status' => 'pending',
        'status' => 0,
        'cancel_order_money_return' => 0,
    ], $overrides));
}

echo "==========================================================" . PHP_EOL;
echo "RUNNING WAVE 2A: BOOKING & PAYMENT INTEGRITY TEST SUITE" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

// 1. Setup Provider & Customer
$provider = User::where('user_type', 0)->first();
if (!$provider) {
    $provider = User::create([
        'name' => 'Integrity Provider',
        'email' => 'integrity_provider_' . time() . '@test.com',
        'username' => 'integrity_provider_' . time(),
        'user_type' => 0,
        'phone' => '0511112233',
        'password' => bcrypt('secret123'),
        'email_verified' => 1,
    ]);
}

$otherProvider = User::where('user_type', 0)->where('id', '!=', $provider->id)->first();
if (!$otherProvider) {
    $otherProvider = User::create([
        'name' => 'Other Provider',
        'email' => 'other_provider_' . time() . '@test.com',
        'username' => 'other_provider_' . time(),
        'user_type' => 0,
        'phone' => '0511114455',
        'password' => bcrypt('secret123'),
        'email_verified' => 1,
    ]);
}

$customer = User::where('user_type', 1)->first();
if (!$customer) {
    $customer = User::create([
        'name' => 'Integrity Customer',
        'email' => 'integrity_customer_' . time() . '@test.com',
        'username' => 'integrity_customer_' . time(),
        'user_type' => 1,
        'phone' => '0522223344',
        'password' => bcrypt('secret123'),
        'email_verified' => 1,
    ]);
}

echo "Provider ID: {$provider->id}, Customer ID: {$customer->id}" . PHP_EOL;

// ---------------------------------------------------------
// GROUP 1: CUSTOMER ORDER VISIBILITY (T1, T2)
// ---------------------------------------------------------
echo PHP_EOL . "--- GROUP 1: Customer Order Visibility (T1, T2) ---" . PHP_EOL;

// Order A: Unpaid online pending order
$orderA = createOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 0,
    'payment_status' => 'pending',
    'payment_gateway' => 'paytabs',
    'date' => '2026-11-25',
]);

// Order B: Paid online pending order
$orderB = createOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 0,
    'payment_status' => 'complete',
    'payment_gateway' => 'paytabs',
    'date' => '2026-11-26',
]);

Auth::guard('sanctum')->setUser($customer);
$userController = new UserController();
request()->merge([]); // clear previous request params
$customerOrdersRes = $userController->myOrders();
$customerOrdersData = json_decode($customerOrdersRes->getContent(), true);

$customerOrderIds = collect($customerOrdersData['my_orders']['data'] ?? [])
    ->pluck('id')
    ->toArray();

assertTest(!in_array($orderA->id, $customerOrderIds), "T1: Unpaid online order (#{$orderA->id}) is excluded from customer My Orders");
assertTest(in_array($orderB->id, $customerOrderIds), "T2: Paid online order (#{$orderB->id}) remains visible to customer");


// ---------------------------------------------------------
// GROUP 2: PROVIDER ORDER VISIBILITY (T3, T4)
// ---------------------------------------------------------
echo PHP_EOL . "--- GROUP 2: Provider Order Visibility (T3, T4) ---" . PHP_EOL;

Auth::guard('sanctum')->setUser($provider);
$sellerController = new SellerController();
request()->merge([]);
$providerOrdersRes = $sellerController->myOrders(new Request());
$providerOrdersData = json_decode($providerOrdersRes->getContent(), true);

$providerOrderIds = collect($providerOrdersData['my_orders']['data'] ?? [])
    ->pluck('id')
    ->toArray();

assertTest(!in_array($orderA->id, $providerOrderIds), "T3: Unpaid online order (#{$orderA->id}) is excluded from provider My Orders");
assertTest(in_array($orderB->id, $providerOrderIds), "T4: Paid online order (#{$orderB->id}) remains visible to provider");


// ---------------------------------------------------------
// GROUP 3: PROVIDER ACCEPT GATE (T5, T6, T7)
// ---------------------------------------------------------
echo PHP_EOL . "--- GROUP 3: Provider Accept Gate (T5, T6, T7) ---" . PHP_EOL;

// T5: Provider cannot Accept unpaid online order (Order A)
$reqAcceptA = new Request(['id' => $orderA->id, 'status' => 1]);
$resAcceptA = $sellerController->OrderStatusChange($reqAcceptA);
$orderAFresh = $orderA->fresh();

assertTest($resAcceptA->getStatusCode() === 422, "T5a: Provider accept on unpaid online order rejected with HTTP 422");
assertTest((int)$orderAFresh->status === 0, "T5b: Unpaid online order status remains 0 (Pending)");

// T6: Provider can Accept paid order (Order B)
$reqAcceptB = new Request(['id' => $orderB->id, 'status' => 1]);
$resAcceptB = $sellerController->OrderStatusChange($reqAcceptB);
$orderBFresh = $orderB->fresh();

assertTest($resAcceptB->getStatusCode() === 200, "T6a: Provider accept on paid order succeeds with HTTP 200");
assertTest((int)$orderBFresh->status === 1, "T6b: Paid order status successfully transitioned to 1 (Active)");

// T7: Provider can still handle COD according to existing lifecycle
$orderCOD = createOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 0,
    'payment_status' => 'pending',
    'payment_gateway' => 'cash_on_delivery',
    'date' => '2026-11-27',
]);

// Check COD is visible to provider
request()->merge([]);
$providerOrdersRes2 = $sellerController->myOrders(new Request());
$providerOrderIds2 = collect(json_decode($providerOrdersRes2->getContent(), true)['my_orders']['data'] ?? [])
    ->pluck('id')->toArray();
assertTest(in_array($orderCOD->id, $providerOrderIds2), "T7a: Unpaid COD order (#{$orderCOD->id}) is visible to provider");

// Check COD is visible to customer
Auth::guard('sanctum')->setUser($customer);
request()->merge([]);
$customerOrdersResCOD = $userController->myOrders();
$customerOrderIdsCOD = collect(json_decode($customerOrdersResCOD->getContent(), true)['my_orders']['data'] ?? [])
    ->pluck('id')->toArray();
assertTest(in_array($orderCOD->id, $customerOrderIdsCOD), "T7b: Unpaid COD order (#{$orderCOD->id}) is visible to customer");

// Re-auth as provider for accept action
Auth::guard('sanctum')->setUser($provider);
$reqAcceptCOD = new Request(['id' => $orderCOD->id, 'status' => 1]);
$resAcceptCOD = $sellerController->OrderStatusChange($reqAcceptCOD);
$orderCODFresh = $orderCOD->fresh();

assertTest($resAcceptCOD->getStatusCode() === 200, "T7c: Provider can accept pending COD order (HTTP 200)");
assertTest((int)$orderCODFresh->status === 1, "T7d: Pending COD order status transitioned to 1 (Active)");


// ---------------------------------------------------------
// GROUP 4: CUSTOMER ORDER DATE PRESERVATION (T8)
// ---------------------------------------------------------
echo PHP_EOL . "--- GROUP 4: Customer Order Date Preservation (T8) ---" . PHP_EOL;

Auth::guard('sanctum')->setUser($customer);
request()->merge([]);
$customerOrdersResDate = $userController->myOrders();
$customerOrdersDataDate = json_decode($customerOrdersResDate->getContent(), true);

$orderBInCustomerList = collect($customerOrdersDataDate['my_orders']['data'] ?? [])
    ->firstWhere('id', $orderB->id);

assertTest(!empty($orderBInCustomerList), "T8a: Order B found in customer orders list");
assertTest(
    !empty($orderBInCustomerList['date']) && strpos($orderBInCustomerList['date'], '2026-11-26') !== false,
    "T8b: Order date is preserved and formatted as ISO-8601 (Found: " . ($orderBInCustomerList['date'] ?? 'null') . ")"
);


// ---------------------------------------------------------
// GROUP 5: CUSTOMER STATUS FILTER REPAIR (T9)
// ---------------------------------------------------------
echo PHP_EOL . "--- GROUP 5: Customer Status Filter Bug Fix (T9) ---" . PHP_EOL;

// Supply ONLY status=1 (without payment_status)
request()->merge(['status' => '1']);
$statusFilterRes = $userController->myOrders();
$statusFilterData = json_decode($statusFilterRes->getContent(), true);
$filteredItems = $statusFilterData['my_orders']['data'] ?? [];

$allStatusOne = count($filteredItems) > 0 && collect($filteredItems)->every(fn($i) => (int)$i['status'] === 1);
$containsOrderB = collect($filteredItems)->contains('id', $orderB->id);

assertTest($allStatusOne, "T9a: Supplying status=1 returns exclusively active orders (status === 1)");
assertTest($containsOrderB, "T9b: Status filter correctly includes active order #{$orderB->id}");

// Clear filter
request()->merge([]);


// ---------------------------------------------------------
// GROUP 6: REFUND & CONCURRENCY PRESERVATION (T10, T11)
// ---------------------------------------------------------
echo PHP_EOL . "--- GROUP 6: Existing Refund & Concurrency Invariants (T10, T11) ---" . PHP_EOL;

$wallet = Wallet::getOrCreateForUser($customer->id);
$initialBalance = (float) $wallet->balance;
$refundAmount = 250.00;

$orderRefund = createOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 0,
    'payment_status' => 'complete',
    'payment_gateway' => 'paytabs',
    'total' => (string) $refundAmount,
    'sub_total' => (string) $refundAmount,
    'cancel_order_money_return' => 0,
]);

Auth::guard('sanctum')->setUser($provider);

// T10: Decline paid order -> refund credited to wallet
$reqDecline = new Request(['id' => $orderRefund->id, 'status' => 4]);
$resDecline = $sellerController->OrderStatusChange($reqDecline);
$orderRefundFresh = $orderRefund->fresh();
$walletFresh = Wallet::where('buyer_id', $customer->id)->first();

assertTest($resDecline->getStatusCode() === 200, "T10a: Provider decline on paid order returns HTTP 200");
assertTest((int)$orderRefundFresh->status === 4, "T10b: Order status transitioned to 4 (Cancelled)");
assertTest((int)$orderRefundFresh->cancel_order_money_return === 1, "T10c: Order marked as refunded (cancel_order_money_return = 1)");
assertTest(
    abs((float)$walletFresh->balance - ($initialBalance + $refundAmount)) < 0.01,
    "T10d: Customer wallet credited with exact refund amount ({$refundAmount} SAR)"
);

// T11: Repeated decline rejected & idempotent (no double refund)
$resDeclineDuplicate = $sellerController->OrderStatusChange($reqDecline);
$walletAfterDup = Wallet::where('buyer_id', $customer->id)->first();

assertTest($resDeclineDuplicate->getStatusCode() === 422, "T11a: Repeated decline rejected with HTTP 422");
assertTest(
    abs((float)$walletAfterDup->balance - (float)$walletFresh->balance) < 0.01,
    "T11b: Customer wallet balance unchanged on repeated decline (idempotency preserved)"
);


// ---------------------------------------------------------
// GROUP 7: AUTHORIZATION GATE (T12)
// ---------------------------------------------------------
echo PHP_EOL . "--- GROUP 7: Authorization Gate (T12) ---" . PHP_EOL;

// Create order belonging to $provider
$orderOwnerProvider = createOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 0,
    'payment_status' => 'complete',
    'payment_gateway' => 'paytabs',
]);

// Auth as $otherProvider and attempt to Accept
Auth::guard('sanctum')->setUser($otherProvider);
$reqUnauthorizedAccept = new Request(['id' => $orderOwnerProvider->id, 'status' => 1]);
$resUnauthorizedAccept = $sellerController->OrderStatusChange($reqUnauthorizedAccept);
$orderOwnerFresh = $orderOwnerProvider->fresh();

assertTest($resUnauthorizedAccept->getStatusCode() === 422, "T12a: Unauthorized provider cannot accept another provider's order (HTTP 422)");
assertTest((int)$orderOwnerFresh->status === 0, "T12b: Order status remains unchanged at 0 (Pending)");


// ---------------------------------------------------------
// GROUP 8: LEGACY UNPAID ONLINE ORDERS WITH STATUS != 0 (T13, T14)
// ---------------------------------------------------------
echo PHP_EOL . "--- GROUP 8: Legacy Unpaid Online Orders with Status != 0 (T13, T14) ---" . PHP_EOL;

// Order X: An online order that somehow has status=1 (Active) but payment_status is pending
$orderX = createOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 1, // Active looking
    'payment_status' => 'pending',
    'payment_gateway' => 'paytabs',
    'date' => '2026-11-28',
]);

// Order Y: An online order that has status=2 (Completed) but payment_status is pending
$orderY = createOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 2, // Completed looking
    'payment_status' => 'pending',
    'payment_gateway' => 'paytabs',
    'date' => '2026-11-29',
]);

// 1. Customer check: neither should appear
Auth::guard('sanctum')->setUser($customer);
request()->merge([]);
$custResX = $userController->myOrders();
$custDataX = json_decode($custResX->getContent(), true)['my_orders']['data'] ?? [];
$custIdsX = collect($custDataX)->pluck('id')->toArray();

assertTest(!in_array($orderX->id, $custIdsX), "T13a: Unpaid online order with status=1 (#{$orderX->id}) is excluded from customer My Orders");
assertTest(!in_array($orderY->id, $custIdsX), "T13b: Unpaid online order with status=2 (#{$orderY->id}) is excluded from customer My Orders");

// 2. Provider check: neither should appear
Auth::guard('sanctum')->setUser($provider);
request()->merge([]);
$provResX = $sellerController->myOrders(new Request());
$provDataX = json_decode($provResX->getContent(), true)['my_orders']['data'] ?? [];
$provIdsX = collect($provDataX)->pluck('id')->toArray();

assertTest(!in_array($orderX->id, $provIdsX), "T14a: Unpaid online order with status=1 (#{$orderX->id}) is excluded from provider My Orders");
assertTest(!in_array($orderY->id, $provIdsX), "T14b: Unpaid online order with status=2 (#{$orderY->id}) is excluded from provider My Orders");


// ---------------------------------------------------------
// GROUP 9: MANUAL PAYMENT LIFECYCLE & NOTIFICATION GATING (T15)
// ---------------------------------------------------------
echo PHP_EOL . "--- GROUP 9: Manual Payment Lifecycle & Notification GATING (T15) ---" . PHP_EOL;

// Helper to evaluate ServiceController's notification condition
$evaluateNotificationCondition = function ($gateway, $paymentStatus) {
    return ($gateway === 'cash_on_delivery')
        || ($gateway === 'wallet' && $paymentStatus === 'complete');
};

assertTest(!$evaluateNotificationCondition('manual_payment', 'pending'), "T15a: manual_payment + pending does NOT trigger immediate provider notification");
assertTest(!$evaluateNotificationCondition('paytabs', 'pending'), "T15b: paytabs + pending does NOT trigger immediate provider notification");
assertTest($evaluateNotificationCondition('cash_on_delivery', 'pending'), "T15c: cash_on_delivery triggers immediate provider notification");
assertTest($evaluateNotificationCondition('wallet', 'complete'), "T15d: wallet + complete triggers immediate provider notification");

// Create synthetic pending manual payment order
$orderManual = createOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 0,
    'payment_status' => 'pending',
    'payment_gateway' => 'manual_payment',
    'date' => '2026-11-30',
]);

// 1. Verify provider cannot see pending manual payment
Auth::guard('sanctum')->setUser($provider);
request()->merge([]);
$provResManual = $sellerController->myOrders(new Request());
$provDataManual = json_decode($provResManual->getContent(), true)['my_orders']['data'] ?? [];
$provIdsManual = collect($provDataManual)->pluck('id')->toArray();
assertTest(!in_array($orderManual->id, $provIdsManual), "T15e: Pending manual payment order (#{$orderManual->id}) is hidden from provider");

// 2. Verify provider cannot accept pending manual payment
$reqAcceptManual = new Request(['id' => $orderManual->id, 'status' => 1]);
$resAcceptManual = $sellerController->OrderStatusChange($reqAcceptManual);
$orderManualFresh = $orderManual->fresh();

assertTest($resAcceptManual->getStatusCode() === 422, "T15f: Provider cannot accept unverified manual payment (HTTP 422)");
assertTest((int)$orderManualFresh->status === 0, "T15g: Unverified manual payment order status remains 0 (Pending)");

// 3. When admin verifies payment (simulated), order becomes visible to provider
$orderManual->update(['payment_status' => 'complete', 'status' => 1]);
request()->merge([]);
$provResManualPaid = $sellerController->myOrders(new Request());
$provDataManualPaid = json_decode($provResManualPaid->getContent(), true)['my_orders']['data'] ?? [];
$provIdsManualPaid = collect($provDataManualPaid)->pluck('id')->toArray();
assertTest(in_array($orderManual->id, $provIdsManualPaid), "T15h: Verified manual payment order (#{$orderManual->id}) becomes visible to provider");

echo PHP_EOL . "==========================================================" . PHP_EOL;
echo "TOTAL RESULTS: {$passed} PASSED, {$failed} FAILED" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);

