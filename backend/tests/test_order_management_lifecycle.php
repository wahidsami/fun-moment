<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\User;
use App\Order;
use App\StaticOption;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

function createTestOrder(array $overrides = []) {
    return Order::create(array_merge([
        'service_id' => 1,
        'name' => 'Test User',
        'email' => 'test@funmoments.test',
        'phone' => '0512345678',
        'post_code' => '12211',
        'address' => 'King Fahd Road',
        'city' => 2,
        'area' => 2,
        'country' => 2,
        'schedule' => '10:00',
        'package_fee' => '0',
        'extra_service' => '0',
        'sub_total' => '100',
        'tax' => '0',
        'total' => '100',
        'payment_status' => 'pending',
        'status' => 0,
        'date' => '2026-10-15',
        'cancel_order_money_return' => 0,
    ], $overrides));
}

echo "==========================================================" . PHP_EOL;
echo "RUNNING ORDER MANAGEMENT LIFECYCLE & WALLET REFUND TESTS" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

// Setup test users: provider and buyer
$provider = User::where('user_type', 0)->first();
if (!$provider) {
    $provider = User::create([
        'name' => 'Test Provider',
        'email' => 'test_provider_' . time() . '@test.com',
        'password' => bcrypt('12345678'),
        'user_type' => 0,
    ]);
}

$otherProvider = User::where('user_type', 0)->where('id', '!=', $provider->id)->first();
if (!$otherProvider) {
    $otherProvider = User::create([
        'name' => 'Other Provider',
        'email' => 'other_provider_' . time() . '@test.com',
        'password' => bcrypt('12345678'),
        'user_type' => 0,
    ]);
}

$buyer = User::where('user_type', 1)->first();
if (!$buyer) {
    $buyer = User::create([
        'name' => 'Test Buyer',
        'email' => 'test_buyer_' . time() . '@test.com',
        'password' => bcrypt('12345678'),
        'user_type' => 1,
    ]);
}

$sellerController = new SellerController();
$userController = new UserController();

// Test 1: Provider accepts own pending order (0 -> 1)
echo PHP_EOL . "--- GROUP 1: PROVIDER ACCEPTANCE ---" . PHP_EOL;
$orderAccept = createTestOrder([
    'seller_id' => $provider->id,
    'buyer_id' => $buyer->id,
    'name' => $buyer->name,
    'email' => $buyer->email,
    'payment_status' => 'complete',
    'payment_gateway' => 'paytabs',
    'total' => 150.00,
    'sub_total' => 150.00,
    'status' => 0,
    'order_note' => 'UAT_LIFECYCLE_TEST_ACCEPT',
]);

Auth::guard('sanctum')->setUser($provider);
$reqAccept = Request::create('/api/v1/seller/my-orders/order/change-status', 'POST', [
    'id' => $orderAccept->id,
    'status' => 1,
]);
$resAccept = $sellerController->OrderStatusChange($reqAccept);
$orderAccept->refresh();

assertTest($resAccept->getStatusCode() === 200, "Provider accept returns HTTP 200");
assertTest((int) $orderAccept->status === 1, "Order status transitions from Pending (0) to Active (1)");

// Test 2: Unauthorized provider cannot accept another provider's order
Auth::guard('sanctum')->setUser($otherProvider);
$reqUnauthAccept = Request::create('/api/v1/seller/my-orders/order/change-status', 'POST', [
    'id' => $orderAccept->id,
    'status' => 1,
]);
$resUnauthAccept = $sellerController->OrderStatusChange($reqUnauthAccept);
assertTest($resUnauthAccept->getStatusCode() === 422, "Unauthorized provider cannot accept another provider's order (HTTP 422)");

// Test 3: Provider declines unpaid pending order (0 -> 4, NO refund)
echo PHP_EOL . "--- GROUP 2: UNPAID PROVIDER DECLINE ---" . PHP_EOL;
$buyerWallet = Wallet::getOrCreateForUser($buyer->id);
$initialBuyerBalance = (float) $buyerWallet->balance;
$initialHistoryCount = WalletHistory::where('user_id', $buyer->id)->count();

$orderUnpaid = createTestOrder([
    'seller_id' => $provider->id,
    'buyer_id' => $buyer->id,
    'name' => $buyer->name,
    'email' => $buyer->email,
    'total' => 200.00,
    'sub_total' => 200.00,
    'status' => 0,
    'payment_status' => 'pending',
    'order_note' => 'UAT_LIFECYCLE_TEST_UNPAID_DECLINE',
]);

Auth::guard('sanctum')->setUser($provider);
$reqDeclineUnpaid = Request::create('/api/v1/seller/my-orders/order/change-status', 'POST', [
    'id' => $orderUnpaid->id,
    'status' => 4,
]);
$resDeclineUnpaid = $sellerController->OrderStatusChange($reqDeclineUnpaid);
$orderUnpaid->refresh();
$buyerWallet->refresh();

assertTest($resDeclineUnpaid->getStatusCode() === 200, "Provider decline unpaid returns HTTP 200");
assertTest((int) $orderUnpaid->status === 4, "Order status transitions to Cancelled (4)");
assertTest((int) $orderUnpaid->cancel_order_money_return === 0, "No money return marked for unpaid order");
assertTest((float) $buyerWallet->balance === $initialBuyerBalance, "Customer wallet balance unchanged on unpaid decline");
assertTest(WalletHistory::where('user_id', $buyer->id)->count() === $initialHistoryCount, "No wallet transaction created for unpaid decline");

// Test 4: Provider declines paid pending order (0 -> 4, WALLET REFUND)
echo PHP_EOL . "--- GROUP 3: PAID PROVIDER DECLINE & WALLET REIMBURSEMENT ---" . PHP_EOL;
$paidTotal = 275.50;
$orderPaid = createTestOrder([
    'seller_id' => $provider->id,
    'buyer_id' => $buyer->id,
    'name' => $buyer->name,
    'email' => $buyer->email,
    'total' => $paidTotal,
    'sub_total' => 275.50,
    'payment_gateway' => 'paytabs',
    'payment_status' => 'complete',
    'transaction_id' => 'TXN-UAT-TEST-001',
    'status' => 0,
    'order_note' => 'UAT_LIFECYCLE_TEST_PAID_DECLINE',
]);

$preRefundBalance = (float) $buyerWallet->balance;

Auth::guard('sanctum')->setUser($provider);
$reqDeclinePaid = Request::create('/api/v1/seller/my-orders/order/change-status', 'POST', [
    'id' => $orderPaid->id,
    'status' => 4,
]);
$resDeclinePaid = $sellerController->OrderStatusChange($reqDeclinePaid);
$orderPaid->refresh();
$buyerWallet->refresh();

$expectedBalance = round($preRefundBalance + $paidTotal, 2);
assertTest($resDeclinePaid->getStatusCode() === 200, "Provider decline paid order returns HTTP 200");
assertTest((int) $orderPaid->status === 4, "Paid order status transitions to Cancelled (4)");
assertTest((int) $orderPaid->cancel_order_money_return === 1, "Order marked as financially refunded (cancel_order_money_return = 1)");
assertTest(abs((float) $buyerWallet->balance - $expectedBalance) < 0.001, "Customer wallet balance credited with exact paid amount ({$paidTotal} SAR)");

$refundHistory = WalletHistory::where('reference_type', 'order_refund')
    ->where('reference_id', (string) $orderPaid->id)
    ->first();
assertTest(!is_null($refundHistory), "WalletHistory entry created for refund");
assertTest($refundHistory && $refundHistory->entry_type === 'credit', "WalletHistory entry is credit");
assertTest($refundHistory && abs((float) $refundHistory->amount - $paidTotal) < 0.001, "WalletHistory amount matches exact paid total");
assertTest($refundHistory && (int) $refundHistory->user_id === (int) $buyer->id, "WalletHistory correctly targets buyer ID");

// Test 5: Idempotency - Repeated decline must NOT double-credit wallet
echo PHP_EOL . "--- GROUP 4: IDEMPOTENCY & DOUBLE-REFUND PREVENTION ---" . PHP_EOL;
$balanceBeforeRepeat = (float) $buyerWallet->balance;
$resRepeat = $sellerController->OrderStatusChange($reqDeclinePaid);
$buyerWallet->refresh();

assertTest($resRepeat->getStatusCode() === 422, "Repeated decline returns HTTP 422 (Already cancelled)");
assertTest((float) $buyerWallet->balance === $balanceBeforeRepeat, "Wallet balance NOT increased on repeated decline");
$refundHistoryCount = WalletHistory::where('reference_type', 'order_refund')
    ->where('reference_id', (string) $orderPaid->id)
    ->count();
assertTest($refundHistoryCount === 1, "Exactly one refund transaction exists for order (no duplicate)");

// Test 6: Unauthorized provider cannot trigger refund on another provider's paid order
echo PHP_EOL . "--- GROUP 5: AUTHORIZATION SECURITY ---" . PHP_EOL;
$orderPaid2 = createTestOrder([
    'seller_id' => $provider->id,
    'buyer_id' => $buyer->id,
    'name' => $buyer->name,
    'email' => $buyer->email,
    'total' => 100.00,
    'sub_total' => 100.00,
    'payment_gateway' => 'paytabs',
    'payment_status' => 'complete',
    'status' => 0,
    'order_note' => 'UAT_LIFECYCLE_TEST_UNAUTH_REFUND',
]);

Auth::guard('sanctum')->setUser($otherProvider);
$reqUnauthDecline = Request::create('/api/v1/seller/my-orders/order/change-status', 'POST', [
    'id' => $orderPaid2->id,
    'status' => 4,
]);
$resUnauthDecline = $sellerController->OrderStatusChange($reqUnauthDecline);
$orderPaid2->refresh();

assertTest($resUnauthDecline->getStatusCode() === 422, "Unauthorized provider cannot decline or refund (HTTP 422)");
assertTest((int) $orderPaid2->status === 0, "Order status remains pending (0)");
assertTest((int) $orderPaid2->cancel_order_money_return === 0, "cancel_order_money_return remains 0");

// Test 7: Wallet Payment Reversal (when original gateway was 'wallet')
echo PHP_EOL . "--- GROUP 6: WALLET PAYMENT REVERSAL ---" . PHP_EOL;
$walletPaidTotal = 88.00;
$orderWalletPaid = createTestOrder([
    'seller_id' => $provider->id,
    'buyer_id' => $buyer->id,
    'name' => $buyer->name,
    'email' => $buyer->email,
    'total' => $walletPaidTotal,
    'sub_total' => 88.00,
    'payment_gateway' => 'wallet',
    'payment_status' => 'complete',
    'status' => 0,
    'order_note' => 'UAT_LIFECYCLE_TEST_WALLET_PAY_REVERSAL',
]);

$preReversalBalance = (float) $buyerWallet->balance;
Auth::guard('sanctum')->setUser($provider);
$reqDeclineWallet = Request::create('/api/v1/seller/my-orders/order/change-status', 'POST', [
    'id' => $orderWalletPaid->id,
    'status' => 4,
]);
$resDeclineWallet = $sellerController->OrderStatusChange($reqDeclineWallet);
$orderWalletPaid->refresh();
$buyerWallet->refresh();

assertTest($resDeclineWallet->getStatusCode() === 200, "Provider decline wallet-paid order returns HTTP 200");
assertTest((int) $orderWalletPaid->cancel_order_money_return === 1, "Wallet-paid order marked refunded");
assertTest(abs((float) $buyerWallet->balance - round($preReversalBalance + $walletPaidTotal, 2)) < 0.001, "Customer wallet reimbursed exactly once for wallet-paid order");

// Test 8: Order Details Backend Date Preservation & StaticOption Null Safety
echo PHP_EOL . "--- GROUP 7: ORDER DETAILS BACKEND FIXES ---" . PHP_EOL;
$testBookingDate = '2026-11-20';
$orderWithDate = createTestOrder([
    'seller_id' => $provider->id,
    'buyer_id' => $buyer->id,
    'name' => $buyer->name,
    'email' => $buyer->email,
    'total' => 300.00,
    'sub_total' => 300.00,
    'status' => 0,
    'date' => $testBookingDate,
    'order_note' => 'UAT_LIFECYCLE_TEST_DATE_PRESERVE',
]);

Auth::guard('sanctum')->setUser($provider);
$reqDetails = Request::create("/api/v1/seller/my-orders/{$orderWithDate->id}", 'POST', ['id' => $orderWithDate->id]);
$resDetails = $sellerController->singleOrder($reqDetails, $orderWithDate->id);

assertTest($resDetails->getStatusCode() === 201, "Provider order details returns HTTP 201");
$detailsJson = json_decode($resDetails->getContent(), true);
assertTest(isset($detailsJson['orderInfo']), "Response has orderInfo payload");
assertTest(!empty($detailsJson['orderInfo']['date']), "Order date is NOT null (preserved)");
assertTest(str_contains($detailsJson['orderInfo']['date'], '2026-11-20'), "Order date contains real booking date {$testBookingDate}");

// Test 8b: Route param $id without $request->id
$reqDetailsRouteOnly = Request::create("/api/v1/seller/my-orders/{$orderWithDate->id}", 'POST');
$resDetailsRouteOnly = $sellerController->singleOrder($reqDetailsRouteOnly, $orderWithDate->id);
assertTest($resDetailsRouteOnly->getStatusCode() === 201, "Provider order details works with route parameter \$id alone");

// Test 8c: Customer singleOrder date preservation
Auth::guard('sanctum')->setUser($buyer);
$reqCustDetails = Request::create("/api/v1/user/my-orders/{$orderWithDate->id}", 'POST', ['id' => $orderWithDate->id]);
$resCustDetails = $userController->singleOrder($reqCustDetails, $orderWithDate->id);
assertTest($resCustDetails->getStatusCode() === 201, "Customer order details returns HTTP 201");
$custDetailsJson = json_decode($resCustDetails->getContent(), true);
assertTest(!empty($custDetailsJson['orderInfo']['date']), "Customer order date is NOT null (preserved)");
assertTest(str_contains($custDetailsJson['orderInfo']['date'], '2026-11-20'), "Customer order date contains real booking date {$testBookingDate}");

// Test 8d: Unauthorized provider cannot view order details
Auth::guard('sanctum')->setUser($otherProvider);
$resOtherDetails = $sellerController->singleOrder($reqDetails, $orderWithDate->id);
$otherJson = json_decode($resOtherDetails->getContent(), true);
assertTest(!isset($otherJson['orderInfo']), "Wrong provider cannot access order details");

// Test 8e: Nonexistent order is safely handled
$reqNonexistent = Request::create("/api/v1/seller/my-orders/9999999", 'POST', ['id' => 9999999]);
$resNonexistent = $sellerController->singleOrder($reqNonexistent, 9999999);
assertTest($resNonexistent->getStatusCode() === 201 || $resNonexistent->getStatusCode() === 404, "Nonexistent order handled safely");

// Clean up test orders created for this test run
Order::where('order_note', 'LIKE', 'UAT_LIFECYCLE_TEST%')->delete();

echo PHP_EOL . "==========================================================" . PHP_EOL;
echo "TEST RESULTS: {$passed} Passed, {$failed} Failed" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
exit(0);
