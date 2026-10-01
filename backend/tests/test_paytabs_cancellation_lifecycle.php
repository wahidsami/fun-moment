<?php

// test_paytabs_cancellation_lifecycle.php
// Regression test suite for Wave 2B: PayTabs Pending Order Abandonment, Failure & Concurrency Cleanup

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Day;
use App\Http\Controllers\Api\PayTabsApiController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\ServiceController;
use App\Order;
use App\Schedule;
use App\Services\Payment\PayTabsPaymentService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Wallet\Entities\Wallet;

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

function createPayTabsOrder(array $overrides = []) {
    return Order::create(array_merge([
        'service_id' => 1,
        'name' => 'PayTabs Test Buyer',
        'email' => 'paytabs_buyer@funmoments.test',
        'phone' => '0501112233',
        'post_code' => '12211',
        'address' => 'Olaya St, Riyadh',
        'city' => 2,
        'area' => 2,
        'country' => 2,
        'date' => '2026-11-25',
        'schedule' => '14:00 - 15:00',
        'package_fee' => '0',
        'extra_service' => '0',
        'sub_total' => '250.00',
        'tax' => '0',
        'total' => '250.00',
        'payment_gateway' => 'paytabs',
        'payment_status' => 'pending',
        'status' => 0,
        'cancel_order_money_return' => 0,
    ], $overrides));
}

echo "==========================================================" . PHP_EOL;
echo "RUNNING WAVE 2B: PAYTABS ABANDONMENT & CLEANUP TEST SUITE" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

// Setup Users
$provider = User::where('user_type', 0)->first();
if (!$provider) {
    $provider = User::create([
        'name' => 'Wave2B Provider',
        'email' => 'w2b_provider_' . time() . '@test.com',
        'username' => 'w2b_provider_' . time(),
        'user_type' => 0,
        'phone' => '0511112233',
        'password' => bcrypt('secret123'),
        'email_verified' => 1,
    ]);
}

$customer = User::where('user_type', 1)->first();
if (!$customer) {
    $customer = User::create([
        'name' => 'Wave2B Customer',
        'email' => 'w2b_customer_' . time() . '@test.com',
        'username' => 'w2b_customer_' . time(),
        'user_type' => 1,
        'phone' => '0522223344',
        'password' => bcrypt('secret123'),
        'email_verified' => 1,
    ]);
}

$otherCustomer = User::where('user_type', 1)->where('id', '!=', $customer->id)->first();
if (!$otherCustomer) {
    $otherCustomer = User::create([
        'name' => 'Other Customer',
        'email' => 'other_customer_' . time() . '@test.com',
        'username' => 'other_customer_' . time(),
        'user_type' => 1,
        'phone' => '0533334455',
        'password' => bcrypt('secret123'),
        'email_verified' => 1,
    ]);
}

echo "Provider ID: {$provider->id}, Customer ID: {$customer->id}, Other Customer ID: {$otherCustomer->id}" . PHP_EOL;

// ---------------------------------------------------------
// T1 & T2: Buyer cancels pending PayTabs order
// ---------------------------------------------------------
echo PHP_EOL . "--- T1 & T2: Customer Cancels Pending PayTabs Order ---" . PHP_EOL;

$wallet = Wallet::getOrCreateForUser($customer->id);
$initialWalletBalance = (float) $wallet->balance;

$order1 = createPayTabsOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'total' => '250.00',
]);

Auth::guard('sanctum')->setUser($customer);
$controller = app(PayTabsApiController::class);
$request = new Request(['order_id' => $order1->id]);
$response = $controller->cancelPending($request);
$data = json_decode($response->getContent(), true);

assertTest($response->getStatusCode() === 200, "T1a: cancelPending endpoint returns HTTP 200");
assertTest(($data['success'] ?? false) === true, "T1b: cancelPending response indicates success");
assertTest(($data['status'] ?? null) === 4, "T1c: Response contains status = 4");
assertTest(($data['payment_status'] ?? null) === 'canceled', "T1d: Response contains payment_status = 'canceled'");

$reloadedOrder1 = Order::find($order1->id);
assertTest((int) $reloadedOrder1->status === 4, "T2a: Order status in database is 4 (Cancelled)");
assertTest($reloadedOrder1->payment_status === 'canceled', "T2b: Order payment_status in database is 'canceled'");
assertTest((int) $reloadedOrder1->cancel_order_money_return === 0, "T2c: cancel_order_money_return is 0 (No false refund)");

$wallet->refresh();
assertTest((float) $wallet->balance === $initialWalletBalance, "T2d: Customer wallet balance is completely unchanged ({$wallet->balance} SAR)");


// ---------------------------------------------------------
// T3: Customer cannot cancel another buyer's order
// ---------------------------------------------------------
echo PHP_EOL . "--- T3: Cross-Buyer Authorization Protection ---" . PHP_EOL;

$order2 = createPayTabsOrder([
    'buyer_id' => $otherCustomer->id,
    'seller_id' => $provider->id,
]);

Auth::guard('sanctum')->setUser($customer); // Authenticated as customer, order belongs to otherCustomer
$request3 = new Request(['order_id' => $order2->id]);
$response3 = $controller->cancelPending($request3);

assertTest($response3->getStatusCode() === 403, "T3a: Cancelling another buyer's order is rejected with HTTP 403");

$reloadedOrder2 = Order::find($order2->id);
assertTest((int) $reloadedOrder2->status === 0, "T3b: Other buyer's order status remains 0 (Pending)");
assertTest($reloadedOrder2->payment_status === 'pending', "T3c: Other buyer's payment_status remains 'pending'");


// ---------------------------------------------------------
// T4: Customer cannot cancel already-paid order
// ---------------------------------------------------------
echo PHP_EOL . "--- T4: Cannot Cancel Already Paid Order ---" . PHP_EOL;

$orderPaid = createPayTabsOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 0,
    'payment_status' => 'complete',
]);

Auth::guard('sanctum')->setUser($customer);
$request4 = new Request(['order_id' => $orderPaid->id]);
$response4 = $controller->cancelPending($request4);
$data4 = json_decode($response4->getContent(), true);

assertTest($response4->getStatusCode() === 422, "T4a: Cancelling already-paid order is rejected with HTTP 422");
assertTest(($data4['already_paid'] ?? false) === true, "T4b: Response contains already_paid = true flag");

$reloadedPaid = Order::find($orderPaid->id);
assertTest($reloadedPaid->payment_status === 'complete', "T4c: Paid order payment_status remains 'complete'");


// ---------------------------------------------------------
// T5 & T6: Gateway Guard (Cannot cancel COD or Wallet via this endpoint)
// ---------------------------------------------------------
echo PHP_EOL . "--- T5 & T6: Gateway Eligibility Guard ---" . PHP_EOL;

$orderCod = createPayTabsOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'payment_gateway' => 'cash_on_delivery',
    'payment_status' => 'pending',
    'status' => 0,
]);

$response5 = $controller->cancelPending(new Request(['order_id' => $orderCod->id]));
assertTest($response5->getStatusCode() === 422, "T5: Cancelling COD order via PayTabs cleanup is rejected (HTTP 422)");

$orderWallet = createPayTabsOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'payment_gateway' => 'wallet',
    'payment_status' => 'pending',
    'status' => 0,
]);

$response6 = $controller->cancelPending(new Request(['order_id' => $orderWallet->id]));
assertTest($response6->getStatusCode() === 422, "T6: Cancelling Wallet order via PayTabs cleanup is rejected (HTTP 422)");


// ---------------------------------------------------------
// T7: Idempotency on Repeated Cancellation
// ---------------------------------------------------------
echo PHP_EOL . "--- T7: Idempotent Double-Cancellation ---" . PHP_EOL;

$response7 = $controller->cancelPending(new Request(['order_id' => $order1->id]));
$data7 = json_decode($response7->getContent(), true);

assertTest($response7->getStatusCode() === 200, "T7a: Second cancellation on order #{$order1->id} returns HTTP 200");
assertTest(($data7['already_cancelled'] ?? false) === true, "T7b: Response contains already_cancelled = true");
assertTest(($data7['status'] ?? null) === 4, "T7c: Order status remains 4");
assertTest(($data7['payment_status'] ?? null) === 'canceled', "T7d: Payment status remains 'canceled'");


// ---------------------------------------------------------
// T8: Slot Release Immediately on Cancellation
// ---------------------------------------------------------
echo PHP_EOL . "--- T8: Slot Release Verification ---" . PHP_EOL;

// Find or create day & schedule for provider
$targetDayName = 'Wednesday';
$targetDate = '2026-11-25'; // Wednesday
$targetSlot = '15:00 - 16:00';

$day = Day::firstOrCreate(
    ['seller_id' => $provider->id, 'day' => $targetDayName],
    ['status' => 1, 'total_day' => 1]
);
$day->status = 1;
$day->save();

$schedule = Schedule::firstOrCreate(
    ['seller_id' => $provider->id, 'day_id' => $day->id, 'schedule' => $targetSlot],
    ['status' => 1]
);
$schedule->status = 1;
$schedule->save();

// Step 1: Create a pending PayTabs order blocking this slot
$orderSlot = createPayTabsOrder([
    'seller_id' => $provider->id,
    'buyer_id' => $customer->id,
    'date' => $targetDate,
    'schedule' => $targetSlot,
    'status' => 0,
    'payment_status' => 'pending',
    'created_at' => now(),
]);

$serviceController = app(ServiceController::class);
request()->merge(['date' => $targetDate]);
$scheduleResBefore = $serviceController->scheduleByDay($targetDayName, $provider->id);
$schedulesBefore = json_decode($scheduleResBefore->getContent(), true)['schedules'] ?? [];
$slotStringsBefore = array_column($schedulesBefore, 'schedule');

assertTest(!in_array($targetSlot, $slotStringsBefore), "T8a: Pending PayTabs order BLOCKS the slot '{$targetSlot}'");

// Step 2: Customer cancels the pending PayTabs order
$cancelSlotRes = $controller->cancelPending(new Request(['order_id' => $orderSlot->id]));
assertTest($cancelSlotRes->getStatusCode() === 200, "T8b: Pending slot order cancelled successfully");

// Step 3: Check availability again for the exact same date and slot
request()->merge(['date' => $targetDate]);
$scheduleResAfter = $serviceController->scheduleByDay($targetDayName, $provider->id);
$schedulesAfter = json_decode($scheduleResAfter->getContent(), true)['schedules'] ?? [];
$slotStringsAfter = array_column($schedulesAfter, 'schedule');

assertTest(in_array($targetSlot, $slotStringsAfter), "T8c: Cancelled order immediately FREES the slot '{$targetSlot}'");


// ---------------------------------------------------------
// T9: Cancelled PayTabs order is NOT actionable by provider
// ---------------------------------------------------------
echo PHP_EOL . "--- T9: Provider View & Action Isolation ---" . PHP_EOL;

Auth::guard('sanctum')->setUser($provider);
$sellerController = app(SellerController::class);
request()->merge([]);
$providerOrdersRes = $sellerController->myOrders(new Request());
$providerOrdersData = json_decode($providerOrdersRes->getContent(), true);
$providerOrderIds = collect($providerOrdersData['my_orders']['data'] ?? [])->pluck('id')->toArray();

assertTest(!in_array($order1->id, $providerOrderIds), "T9a: Cancelled PayTabs order (#{$order1->id}) is NOT visible in provider My Orders");

// Attempt to accept cancelled order
$acceptReq = new Request(['id' => $order1->id, 'status' => 1]);
$acceptRes = $sellerController->OrderStatusChange($acceptReq);
assertTest($acceptRes->getStatusCode() === 422, "T9b: Provider cannot accept cancelled order (HTTP 422)");


// ---------------------------------------------------------
// T10 & T11: Existing Paid PayTabs Verification & Idempotency
// ---------------------------------------------------------
echo PHP_EOL . "--- T10 & T11: Gateway Verification & Idempotency ---" . PHP_EOL;

$orderVerify = createPayTabsOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 0,
    'payment_status' => 'pending',
]);

$payTabsService = app(PayTabsPaymentService::class);
$applyResult1 = $payTabsService->applySuccessfulPayment($orderVerify, 'TRAN_TEST_CONFIRMED', [
    'gateway' => 'paytabs',
    'response_status' => 'A',
]);

assertTest($applyResult1['already_completed'] === false, "T10a: First verification completes order");
$reloadedVerify = Order::find($orderVerify->id);
assertTest($reloadedVerify->payment_status === 'complete', "T10b: Payment status transitioned to 'complete'");
assertTest((int) $reloadedVerify->status === 1, "T10c: Order status transitioned to 1 (Active)");

// Idempotent repeat
$applyResult2 = $payTabsService->applySuccessfulPayment($reloadedVerify, 'TRAN_TEST_CONFIRMED', [
    'gateway' => 'paytabs',
    'response_status' => 'A',
]);
assertTest($applyResult2['already_completed'] === true, "T11: Repeated verification is safely idempotent");


// ---------------------------------------------------------
// T12 & T13: Provider Notification Safety
// ---------------------------------------------------------
echo PHP_EOL . "--- T12 & T13: Notification Invariants ---" . PHP_EOL;

$notifCountBefore = $provider->notifications()->count();

$orderNotifTest = createPayTabsOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
]);

Auth::guard('sanctum')->setUser($customer);
$controller->cancelPending(new Request(['order_id' => $orderNotifTest->id]));

$notifCountAfterCancel = $provider->notifications()->count();
assertTest($notifCountAfterCancel === $notifCountBefore, "T12: Failure/cancellation does NOT dispatch provider notification (Delta: 0)");

// Successful verification dispatches exactly ONE notification
$orderSuccessNotif = createPayTabsOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
]);

$payTabsService->applySuccessfulPayment($orderSuccessNotif, 'TRAN_NOTIF_SUCCESS', [
    'gateway' => 'paytabs',
    'response_status' => 'A',
]);

$notifCountAfterSuccess = $provider->notifications()->count();
assertTest($notifCountAfterSuccess === $notifCountBefore + 1, "T13a: Successful payment dispatches exactly ONE provider notification");

// Repeating verification does not send duplicate notification
$payTabsService->applySuccessfulPayment($orderSuccessNotif, 'TRAN_NOTIF_SUCCESS', [
    'gateway' => 'paytabs',
    'response_status' => 'A',
]);
$notifCountAfterDuplicate = $provider->notifications()->count();
assertTest($notifCountAfterDuplicate === $notifCountAfterSuccess, "T13b: Duplicate verification does NOT dispatch second notification");


// ---------------------------------------------------------
// T14: Concurrency Race: Cancellation vs Payment Verification
// ---------------------------------------------------------
echo PHP_EOL . "--- T14: Concurrency Race Between Cancellation & Verification ---" . PHP_EOL;

// SUBTEST 14A: Genuine payment capture wins against cancellation race via 2 parallel processes
$orderRace = createPayTabsOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 0,
    'payment_status' => 'pending',
]);

$cmdApply = 'php ' . escapeshellarg(__DIR__ . '/paytabs_worker.php') . ' apply ' . $orderRace->id . ' ' . $customer->id . ' TRAN_PARALLEL_WIN';
$cmdCancel = 'php ' . escapeshellarg(__DIR__ . '/paytabs_worker.php') . ' cancel ' . $orderRace->id . ' ' . $customer->id;

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

// Launch Worker 1 (Verification/Capture) and Worker 2 (Cancellation) simultaneously
$proc1 = proc_open($cmdApply, $descriptors, $pipes1);
$proc2 = proc_open($cmdCancel, $descriptors, $pipes2);

$out1 = stream_get_contents($pipes1[1]);
fclose($pipes1[0]); fclose($pipes1[1]); fclose($pipes1[2]);
proc_close($proc1);

$out2 = stream_get_contents($pipes2[1]);
fclose($pipes2[0]); fclose($pipes2[1]); fclose($pipes2[2]);
proc_close($proc2);

echo "  Worker 1 (Apply) output: {$out1}" . PHP_EOL;
echo "  Worker 2 (Cancel) output: {$out2}" . PHP_EOL;

$reloadedRace = Order::find($orderRace->id);
assertTest($reloadedRace->payment_status === 'complete', "T14a: Authoritative payment is NOT lost during concurrency race (payment_status = complete)");
assertTest((int) $reloadedRace->status === 1, "T14b: Paid order transitioned to active booking (status = 1)");

// SUBTEST 14B: Cancelled order when gateway payment fails remains cancelled
$orderFailRace = createPayTabsOrder([
    'buyer_id' => $customer->id,
    'seller_id' => $provider->id,
    'status' => 0,
    'payment_status' => 'pending',
]);

// Customer cancels
$controller->cancelPending(new Request(['order_id' => $orderFailRace->id]));

// PayTabs verification returns unverified/declined (respStatus != 'A')
// Therefore applySuccessfulPayment is never invoked
$reloadedFailRace = Order::find($orderFailRace->id);
assertTest((int) $reloadedFailRace->status === 4, "T14c: Failed/Cancelled transaction remains status = 4");
assertTest($reloadedFailRace->payment_status === 'canceled', "T14d: Failed/Cancelled transaction remains payment_status = 'canceled'");


echo PHP_EOL . "==========================================================" . PHP_EOL;
echo "WAVE 2B TEST RESULTS: {$passed} PASSED, {$failed} FAILED" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
exit(0);
