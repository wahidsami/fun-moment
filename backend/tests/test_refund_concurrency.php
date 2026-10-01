<?php

// test_refund_concurrency.php
// Real concurrent multi-process regression test for OrderStatusChange refund concurrency

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\User;
use App\Order;
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

echo "==========================================================" . PHP_EOL;
echo "RUNNING ORDER STATUS CHANGE CONCURRENCY REFUND TEST" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

// 1. Setup Provider & Customer
$provider = User::where('user_type', 0)->first();
if (!$provider) {
    $provider = User::create([
        'name' => 'Concurrency Provider',
        'email' => 'concurrency_provider_' . time() . '@test.com',
        'username' => 'concurrency_provider_' . time(),
        'user_type' => 0,
        'phone' => '0501112233',
        'password' => bcrypt('secret123'),
        'email_verified' => 1,
    ]);
}

$customer = User::where('user_type', 1)->first();
if (!$customer) {
    $customer = User::create([
        'name' => 'Concurrency Customer',
        'email' => 'concurrency_customer_' . time() . '@test.com',
        'username' => 'concurrency_customer_' . time(),
        'user_type' => 1,
        'phone' => '0504445566',
        'password' => bcrypt('secret123'),
        'email_verified' => 1,
    ]);
}

$walletService = app(WalletService::class);
$wallet = Wallet::getOrCreateForUser($customer->id);
$initialBalance = (float) $wallet->balance;
$refundAmount = 350.00;

echo "--- STEP 1: INITIAL STATE ---" . PHP_EOL;
echo "  Provider ID: {$provider->id}" . PHP_EOL;
echo "  Customer ID: {$customer->id}" . PHP_EOL;
echo "  Customer Initial Wallet Balance: {$initialBalance} SAR" . PHP_EOL;

// 2. Create Paid Pending Order
$order = Order::create([
    'service_id' => 1,
    'seller_id' => $provider->id,
    'buyer_id' => $customer->id,
    'name' => $customer->name,
    'email' => $customer->email,
    'phone' => $customer->phone ?? '0501234567',
    'post_code' => '12211',
    'address' => 'Riyadh Road',
    'city' => 2,
    'area' => 2,
    'country' => 2,
    'date' => '2026-12-01',
    'schedule' => '12:00',
    'package_fee' => '0',
    'extra_service' => '0',
    'sub_total' => (string) $refundAmount,
    'tax' => '0',
    'total' => (string) $refundAmount,
    'payment_gateway' => 'paytabs',
    'payment_status' => 'complete',
    'status' => 0,
    'cancel_order_money_return' => 0,
]);

assertTest((int) $order->status === 0, "Initial order status is 0 (Pending)");
assertTest($order->payment_status === 'complete', "Initial order payment_status is 'complete'");
assertTest((int) $order->cancel_order_money_return === 0, "Initial cancel_order_money_return is 0 (unrefunded)");

echo PHP_EOL . "--- STEP 2: CONCURRENT DECLINE EXECUTION VIA 2 PARALLEL PROCESSES ---" . PHP_EOL;

$cmd1 = 'php ' . escapeshellarg(__DIR__ . '/decline_worker.php') . ' ' . $order->id . ' ' . $provider->id;
$cmd2 = 'php ' . escapeshellarg(__DIR__ . '/decline_worker.php') . ' ' . $order->id . ' ' . $provider->id;

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

// Launch Process A
$procA = proc_open($cmd1, $descriptors, $pipesA);
// Launch Process B immediately
$procB = proc_open($cmd2, $descriptors, $pipesB);

$outA = stream_get_contents($pipesA[1]);
$errA = stream_get_contents($pipesA[2]);
fclose($pipesA[0]);
fclose($pipesA[1]);
fclose($pipesA[2]);
$statusA = proc_close($procA);

$outB = stream_get_contents($pipesB[1]);
$errB = stream_get_contents($pipesB[2]);
fclose($pipesB[0]);
fclose($pipesB[1]);
fclose($pipesB[2]);
$statusB = proc_close($procB);

echo "  Process A output: {$outA}" . PHP_EOL;
echo "  Process B output: {$outB}" . PHP_EOL;

$respA = json_decode($outA, true);
$respB = json_decode($outB, true);

echo PHP_EOL . "--- STEP 3: FINANCIAL & LIFECYCLE INVARIANTS VERIFICATION ---" . PHP_EOL;

// Reload order and wallet from database
$reloadedOrder = Order::find($order->id);
$reloadedWallet = Wallet::find($wallet->id);
$finalBalance = (float) $reloadedWallet->balance;
$expectedBalance = round($initialBalance + $refundAmount, 2);

// 1. Order Status must be exactly 4 (Cancelled)
assertTest((int) $reloadedOrder->status === 4, "Final order status is 4 (Cancelled)");

// 2. cancel_order_money_return must be exactly 1
assertTest((int) $reloadedOrder->cancel_order_money_return === 1, "Final cancel_order_money_return is 1 (Refunded)");

// 3. Exactly one refund transaction in WalletHistory
$refundHistories = WalletHistory::where('reference_type', 'order_refund')
    ->where('reference_id', (string) $order->id)
    ->get();

assertTest($refundHistories->count() === 1, "Exactly ONE WalletHistory row exists for order #{$order->id} (Found: {$refundHistories->count()})");

// 4. Refund amount in history matches order total exactly
if ($refundHistories->count() >= 1) {
    $firstHistory = $refundHistories->first();
    assertTest((float) $firstHistory->amount === (float) $refundAmount, "WalletHistory amount matches exact order total ({$refundAmount} SAR)");
    assertTest($firstHistory->entry_type === 'credit', "WalletHistory entry_type is 'credit'");
    assertTest((int) $firstHistory->user_id === (int) $customer->id, "WalletHistory correctly credited to Customer ID #{$customer->id}");
}

// 5. Customer wallet credited exactly once (no double-credit)
assertTest($finalBalance === $expectedBalance, "Customer wallet credited exactly once: expected {$expectedBalance} SAR, actual {$finalBalance} SAR");

// 6. Process responses: exactly one 200 success, other is rejected with 422
$statusCodes = [$respA['status_code'] ?? 0, $respB['status_code'] ?? 0];
$successCount = count(array_filter($statusCodes, fn($c) => $c === 200));
$rejectedCount = count(array_filter($statusCodes, fn($c) => $c === 422));

assertTest($successCount === 1, "Exactly ONE concurrent request was granted refund & status transition (HTTP 200 count: {$successCount})");
assertTest($rejectedCount === 1, "Concurrent duplicate request was safely rejected (HTTP 422 count: {$rejectedCount})");

echo PHP_EOL . "==========================================================" . PHP_EOL;
echo "CONCURRENCY TEST RESULTS: {$passed} Passed, {$failed} Failed" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
