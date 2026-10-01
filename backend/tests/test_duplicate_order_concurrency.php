<?php

/**
 * WAVE 2C FINAL: TRUE IDEMPOTENCY KEY + CONCURRENCY HARDENING TEST SUITE
 * 
 * Tests:
 * T1  — Two concurrent COD requests with SAME idempotency key (one order only).
 * T2  — Two concurrent PayTabs order requests with SAME key (one order only).
 * T3  — Two concurrent Wallet requests with SAME key (one order + one wallet deduction).
 * T4  — Retry after simulated network timeout using SAME key (existing order replayed).
 * T5  — Two requests with DIFFERENT idempotency keys but otherwise identical payload (slot conflict handles offline booking).
 * T6  — Two online purchases with DIFFERENT idempotency keys and identical service/buyer/total (MUST NOT be deduplicated).
 * T7  — Cancelled PayTabs order followed by NEW booking with NEW key (new booking succeeds).
 * T8  — Insufficient wallet with valid key (HTTP 422 and zero paid/complete order).
 * T9  — Same idempotency key retried after first successful wallet order (zero additional wallet deduction).
 * T10 — Same idempotency key concurrent duplicate must produce exactly one provider notification.
 * T11 — Different slots still execute concurrently without broad serialization.
 * T12 — Malformed/oversized idempotency key is rejected safely (HTTP 422).
 */

// Worker mode handling for genuine multi-process OS execution
if (isset($argv[1]) && $argv[1] === '--worker') {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    $rawArg = $argv[2] ?? '';
    $payload = json_decode(base64_decode($rawArg), true) ?: json_decode($rawArg, true);
    $buyerId = isset($payload['buyer_id']) ? (int) $payload['buyer_id'] : null;

    if ($buyerId) {
        $buyer = App\User::find($buyerId);
        if ($buyer) {
            Illuminate\Support\Facades\Auth::guard('sanctum')->setUser($buyer);
        }
    }

    $headers = [];
    if (!empty($payload['idempotency_key'])) {
        $headers['HTTP_X_IDEMPOTENCY_KEY'] = $payload['idempotency_key'];
    }

    $request = Illuminate\Http\Request::create('/api/v1/service/order', 'POST', $payload, [], [], $headers);
    $controller = app(App\Http\Controllers\Api\ServiceController::class);

    try {
        $response = $controller->order($request);
        echo json_encode([
            'status_code' => $response->getStatusCode(),
            'data' => json_decode($response->getContent(), true),
        ]);
    } catch (\Throwable $e) {
        echo json_encode([
            'status_code' => 500,
            'error' => $e->getMessage(),
        ]);
    }
    exit(0);
}

// Main Test Runner
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Order;
use App\Service;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Wallet\Entities\Wallet;

echo "==========================================================" . PHP_EOL;
echo "RUNNING WAVE 2C FINAL: IDEMPOTENCY KEY CONCURRENCY SUITE" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

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

function runParallelWorkers(array $payloadA, array $payloadB) {
    $script = __FILE__;
    $cmdA = 'php ' . escapeshellarg($script) . ' --worker ' . base64_encode(json_encode($payloadA));
    $cmdB = 'php ' . escapeshellarg($script) . ' --worker ' . base64_encode(json_encode($payloadB));

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $procA = proc_open($cmdA, $descriptors, $pipesA);
    $procB = proc_open($cmdB, $descriptors, $pipesB);

    $outA = stream_get_contents($pipesA[1]);
    $errA = stream_get_contents($pipesA[2]);
    fclose($pipesA[0]);
    fclose($pipesA[1]);
    fclose($pipesA[2]);
    proc_close($procA);

    $outB = stream_get_contents($pipesB[1]);
    $errB = stream_get_contents($pipesB[2]);
    fclose($pipesB[0]);
    fclose($pipesB[1]);
    fclose($pipesB[2]);
    proc_close($procB);

    return [
        'resA' => json_decode($outA, true),
        'resB' => json_decode($outB, true),
        'rawA' => $outA,
        'rawB' => $outB,
        'errA' => $errA,
        'errB' => $errB,
    ];
}

// Test Fixtures
$provider = User::find(1);
$customer = User::find(4);
$otherCustomer = User::find(6) ?: User::where('id', '!=', 4)->where('id', '!=', 1)->first();
$service = Service::where('seller_id', $provider->id)->first() ?: Service::first();

echo "Provider ID: {$provider->id}, Customer ID: {$customer->id}, Service ID: {$service->id}" . PHP_EOL . PHP_EOL;

// Ensure customer wallet exists and has sufficient test balance
$wallet = Wallet::where('buyer_id', $customer->id)->first();
if (!$wallet) {
    $wallet = Wallet::create([
        'user_id' => $customer->id,
        'buyer_id' => $customer->id,
        'balance' => 2000.0,
        'status' => 1,
    ]);
}
if ((float) $wallet->balance < 1000.0) {
    $wallet->update(['balance' => 2000.0]);
}

$basePayload = [
    'service_id' => $service->id,
    'seller_id' => $provider->id,
    'buyer_id' => $customer->id,
    'name' => $customer->name,
    'email' => $customer->email,
    'phone' => $customer->phone ?? '0501234567',
    'post_code' => '12211',
    'address' => 'Riyadh Road',
    'choose_service_city' => 1,
    'choose_service_area' => 1,
    'choose_service_country' => 1,
    'is_service_online' => 0,
    'include_services' => json_encode(['include_services' => [
        ['title' => 'Standard', 'price' => 100, 'quantity' => 1]
    ]]),
];

// Clean up any test orders for our test dates
$testDates = ['2026-12-25', '2026-12-26', '2026-12-27', '2026-12-28', '2026-12-29', '2026-12-30'];
Order::whereIn('date', $testDates)->forceDelete();

// -------------------------------------------------------------
// T1 & T10 — TWO CONCURRENT COD REQUESTS WITH SAME IDEMPOTENCY KEY
// -------------------------------------------------------------
echo "--- T1 & T10: Concurrent COD Requests with SAME Idempotency Key ---" . PHP_EOL;

$keyCOD = (string) Str::uuid();
$codSlot = '10:00 - 11:00';
$codDate = '2026-12-25';
$payloadCOD = array_merge($basePayload, [
    'date' => $codDate,
    'schedule' => $codSlot,
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => $keyCOD,
]);

$initialNotifs = DB::table('notifications')->where('notifiable_id', $provider->id)->count();

$codWorkers = runParallelWorkers($payloadCOD, $payloadCOD);
$resA = $codWorkers['resA'];
$resB = $codWorkers['resB'];

assertTest($resA['status_code'] === 201, "T1a: Worker A COD submission returned HTTP 201");
assertTest($resB['status_code'] === 201, "T1b: Worker B COD submission returned HTTP 201 (replayed)");

$codOrders = Order::where('idempotency_key', $keyCOD)->get();
assertTest($codOrders->count() === 1, "T1c: Exactly ONE order created in database with key {$keyCOD} (Count: {$codOrders->count()})");

$finalNotifs = DB::table('notifications')->where('notifiable_id', $provider->id)->count();
$notifDelta = $finalNotifs - $initialNotifs;
assertTest($notifDelta === 1, "T10: Exactly ONE provider notification dispatched for concurrent duplicate submissions (Delta: {$notifDelta})");

// -------------------------------------------------------------
// T2 — TWO CONCURRENT PAYTABS ORDER REQUESTS WITH SAME KEY
// -------------------------------------------------------------
echo PHP_EOL . "--- T2: Concurrent PayTabs Requests with SAME Idempotency Key ---" . PHP_EOL;

$keyPayTabs = (string) Str::uuid();
$onlineSlot = '11:00 - 12:00';
$onlineDate = '2026-12-25';
$payloadPayTabs = array_merge($basePayload, [
    'date' => $onlineDate,
    'schedule' => $onlineSlot,
    'selected_payment_gateway' => 'paytabs',
    'idempotency_key' => $keyPayTabs,
]);

$paytabsWorkers = runParallelWorkers($payloadPayTabs, $payloadPayTabs);
$ptResA = $paytabsWorkers['resA'];
$ptResB = $paytabsWorkers['resB'];

assertTest($ptResA['status_code'] === 201, "T2a: Worker A PayTabs submission returned HTTP 201");
assertTest($ptResB['status_code'] === 201, "T2b: Worker B PayTabs submission returned HTTP 201 (replayed)");

$ptOrders = Order::where('idempotency_key', $keyPayTabs)->get();
assertTest($ptOrders->count() === 1, "T2c: Exactly ONE PayTabs order exists for key {$keyPayTabs} (Count: {$ptOrders->count()})");
assertTest((int) $ptOrders->first()->status === 0, "T2d: Order status is 0 (Pending PayTabs)");

// -------------------------------------------------------------
// T3 & T9 — TWO CONCURRENT WALLET REQUESTS WITH SAME KEY + RETRY
// -------------------------------------------------------------
echo PHP_EOL . "--- T3 & T9: Concurrent Wallet Requests with SAME Key & Retry ---" . PHP_EOL;

$wallet->refresh();
$preWalletBalance = (float) $wallet->balance;

$keyWallet = (string) Str::uuid();
$walletSlot = '12:00 - 13:00';
$walletDate = '2026-12-25';
$payloadWallet = array_merge($basePayload, [
    'date' => $walletDate,
    'schedule' => $walletSlot,
    'selected_payment_gateway' => 'wallet',
    'idempotency_key' => $keyWallet,
]);

$walletWorkers = runParallelWorkers($payloadWallet, $payloadWallet);
$wResA = $walletWorkers['resA'];
$wResB = $walletWorkers['resB'];

assertTest($wResA['status_code'] === 201, "T3a: Worker A Wallet submission returned HTTP 201");
assertTest($wResB['status_code'] === 201, "T3b: Worker B Wallet submission returned HTTP 201 (replayed)");

$walletOrders = Order::where('idempotency_key', $keyWallet)->get();
assertTest($walletOrders->count() === 1, "T3c: Exactly ONE order created for wallet key {$keyWallet}");

$createdWalletOrder = $walletOrders->first();
$orderTotal = (float) $createdWalletOrder->total;

$wallet->refresh();
$postWalletBalance = (float) $wallet->balance;
$walletDelta = round($preWalletBalance - $postWalletBalance, 2);

assertTest($walletDelta === round($orderTotal, 2), "T3d: Wallet balance deducted exactly ONCE on concurrent submit (Expected: {$orderTotal}, Actual delta: {$walletDelta})");

// T9: Repeated retry of the SAME wallet idempotency key
Auth::guard('sanctum')->setUser($customer);
$reqWalletRetry = Request::create('/api/v1/service/order', 'POST', $payloadWallet, [], [], ['HTTP_X_IDEMPOTENCY_KEY' => $keyWallet]);
$resWalletRetry = app(App\Http\Controllers\Api\ServiceController::class)->order($reqWalletRetry);

$wallet->refresh();
$postRetryBalance = (float) $wallet->balance;
$retryDelta = round($postWalletBalance - $postRetryBalance, 2);

assertTest($resWalletRetry->getStatusCode() === 201, "T9a: Retrying wallet request with same key returns HTTP 201");
assertTest($retryDelta === 0.0, "T9b: Zero additional wallet deduction on subsequent retry (Delta: {$retryDelta} SAR)");

// -------------------------------------------------------------
// T4 — RETRY AFTER SIMULATED NETWORK TIMEOUT USING SAME KEY
// -------------------------------------------------------------
echo PHP_EOL . "--- T4: Retry After Simulated Network Timeout Using SAME Key ---" . PHP_EOL;

$keyTimeout = (string) Str::uuid();
$timeoutSlot = '14:00 - 15:00';
$timeoutDate = '2026-12-25';
$payloadTimeout = array_merge($basePayload, [
    'date' => $timeoutDate,
    'schedule' => $timeoutSlot,
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => $keyTimeout,
]);

// Initial request succeeds on server, but client drops connection
Auth::guard('sanctum')->setUser($customer);
$reqInitial = Request::create('/api/v1/service/order', 'POST', $payloadTimeout);
$resInitial = app(App\Http\Controllers\Api\ServiceController::class)->order($reqInitial);
$dataInitial = json_decode($resInitial->getContent(), true);

// Client retries with the exact same idempotency_key
$reqTimeoutRetry = Request::create('/api/v1/service/order', 'POST', $payloadTimeout);
$resTimeoutRetry = app(App\Http\Controllers\Api\ServiceController::class)->order($reqTimeoutRetry);
$dataTimeoutRetry = json_decode($resTimeoutRetry->getContent(), true);

assertTest($resTimeoutRetry->getStatusCode() === 201, "T4a: Network timeout retry returns HTTP 201");
assertTest((int) $dataTimeoutRetry['order_id'] === (int) $dataInitial['order_id'], "T4b: Replayed response returns exact original order #{$dataInitial['order_id']}");
$timeoutOrders = Order::where('idempotency_key', $keyTimeout)->get();
assertTest($timeoutOrders->count() === 1, "T4c: Exactly ONE order exists in database after timeout retry");

// -------------------------------------------------------------
// T5 — TWO REQUESTS WITH DIFFERENT KEYS (SLOT CONFLICT HANDLES)
// -------------------------------------------------------------
echo PHP_EOL . "--- T5: Two Requests with DIFFERENT Keys for Same Slot ---" . PHP_EOL;

$keyDiff1 = (string) Str::uuid();
$keyDiff2 = (string) Str::uuid();
$sameSlot = '15:00 - 16:00';
$sameDate = '2026-12-25';

$payloadDiff1 = array_merge($basePayload, [
    'date' => $sameDate,
    'schedule' => $sameSlot,
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => $keyDiff1,
]);
$payloadDiff2 = array_merge($basePayload, [
    'date' => $sameDate,
    'schedule' => $sameSlot,
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => $keyDiff2,
]);

$workersDiff = runParallelWorkers($payloadDiff1, $payloadDiff2);
$dResA = $workersDiff['resA'];
$dResB = $workersDiff['resB'];

// Because keys are different, they are NOT collapsed by idempotency; normal slot conflict rejects the second!
$hasConflict422 = ($dResA['status_code'] === 422 || $dResB['status_code'] === 422);
$hasSuccess201 = ($dResA['status_code'] === 201 || $dResB['status_code'] === 201);
assertTest($hasSuccess201 && $hasConflict422, "T5a: Different keys treated as separate booking attempts; exactly one succeeds and second receives 422 slot conflict");
$slotConflictOrders = Order::where('date', $sameDate)->where('schedule', $sameSlot)->get();
assertTest($slotConflictOrders->count() === 1, "T5b: Exactly one order created for physical slot");

// -------------------------------------------------------------
// T6 — TWO ONLINE PURCHASES WITH DIFFERENT KEYS NOT DEDUPLICATED
// -------------------------------------------------------------
echo PHP_EOL . "--- T6: Two Online Purchases with DIFFERENT Keys (Never Deduplicated) ---" . PHP_EOL;

$keyOnline1 = (string) Str::uuid();
$keyOnline2 = (string) Str::uuid();

$payloadOnlineService1 = [
    'service_id' => $service->id,
    'seller_id' => $provider->id,
    'buyer_id' => $customer->id,
    'name' => $customer->name,
    'email' => $customer->email,
    'phone' => $customer->phone ?? '0501234567',
    'is_service_online' => '1',
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => $keyOnline1,
];
$payloadOnlineService2 = [
    'service_id' => $service->id,
    'seller_id' => $provider->id,
    'buyer_id' => $customer->id,
    'name' => $customer->name,
    'email' => $customer->email,
    'phone' => $customer->phone ?? '0501234567',
    'is_service_online' => '1',
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => $keyOnline2,
];

Auth::guard('sanctum')->setUser($customer);
$resOnline1 = app(App\Http\Controllers\Api\ServiceController::class)->order(Request::create('/api/v1/service/order', 'POST', $payloadOnlineService1));
$resOnline2 = app(App\Http\Controllers\Api\ServiceController::class)->order(Request::create('/api/v1/service/order', 'POST', $payloadOnlineService2));

$dataOnline1 = json_decode($resOnline1->getContent(), true);
$dataOnline2 = json_decode($resOnline2->getContent(), true);

assertTest($resOnline1->getStatusCode() === 201 && $resOnline2->getStatusCode() === 201, "T6a: Both online bookings return HTTP 201");
assertTest((int) $dataOnline1['order_id'] !== (int) $dataOnline2['order_id'], "T6b: Two separate orders created (#{$dataOnline1['order_id']} != #{$dataOnline2['order_id']}) — NOT deduplicated!");

// -------------------------------------------------------------
// T7 — CANCELLED PAYTABS ORDER FOLLOWED BY NEW BOOKING WITH NEW KEY
// -------------------------------------------------------------
echo PHP_EOL . "--- T7: Cancelled PayTabs Order Followed by NEW Booking with NEW Key ---" . PHP_EOL;

$cancelSlot = '16:00 - 17:00';
$cancelDate = '2026-12-26';
$keyOldCancelled = (string) Str::uuid();

// 1. Create PayTabs order and cancel it
$cancelledOrder = Order::create([
    'service_id' => $service->id,
    'seller_id' => $provider->id,
    'buyer_id' => $customer->id,
    'name' => $customer->name,
    'email' => $customer->email,
    'phone' => '0501234567',
    'post_code' => '12211',
    'address' => 'Riyadh Road',
    'city' => 1,
    'area' => 1,
    'country' => 1,
    'date' => $cancelDate,
    'schedule' => $cancelSlot,
    'package_fee' => 100,
    'is_order_online' => '0',
    'extra_service' => 0,
    'sub_total' => 100,
    'tax' => 0,
    'total' => 100,
    'status' => 4, // CANCELLED
    'payment_gateway' => 'paytabs',
    'payment_status' => 'canceled',
    'idempotency_key' => $keyOldCancelled,
]);

// 2. Client initiates a NEW booking attempt with a NEW idempotency key
$keyNewBooking = (string) Str::uuid();
$payloadNew = array_merge($basePayload, [
    'date' => $cancelDate,
    'schedule' => $cancelSlot,
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => $keyNewBooking,
]);

Auth::guard('sanctum')->setUser($customer);
$resNew = app(App\Http\Controllers\Api\ServiceController::class)->order(Request::create('/api/v1/service/order', 'POST', $payloadNew));
$dataNew = json_decode($resNew->getContent(), true);

assertTest($resNew->getStatusCode() === 201, "T7a: New booking on released slot returns HTTP 201");
assertTest((int) $dataNew['order_id'] !== $cancelledOrder->id, "T7b: New booking created as separate active order #{$dataNew['order_id']}");

// -------------------------------------------------------------
// T8 — INSUFFICIENT WALLET BALANCE WITH VALID KEY PRODUCES 422
// -------------------------------------------------------------
echo PHP_EOL . "--- T8: Insufficient Wallet Balance Protection ---" . PHP_EOL;

$brokeUsername = 'broke_' . uniqid();
$brokeUser = User::create([
    'username' => $brokeUsername,
    'name' => 'Broke Customer',
    'email' => $brokeUsername . '@funmoments.test',
    'password' => bcrypt('secret123'),
    'phone' => '0509998877',
    'user_type' => 1,
]);
Wallet::create([
    'user_id' => $brokeUser->id,
    'buyer_id' => $brokeUser->id,
    'balance' => 0.0,
    'status' => 1,
]);

$keyBroke = (string) Str::uuid();
$payloadInsufficient = array_merge($basePayload, [
    'buyer_id' => $brokeUser->id,
    'email' => $brokeUser->email,
    'phone' => $brokeUser->phone,
    'date' => '2026-12-27',
    'schedule' => '10:00 - 11:00',
    'selected_payment_gateway' => 'wallet',
    'idempotency_key' => $keyBroke,
]);

Auth::guard('sanctum')->setUser($brokeUser);
$resInsufficient = app(App\Http\Controllers\Api\ServiceController::class)->order(Request::create('/api/v1/service/order', 'POST', $payloadInsufficient));

assertTest($resInsufficient->getStatusCode() === 422, "T8a: Insufficient wallet balance returns HTTP 422");
$insufficientOrders = Order::where('idempotency_key', $keyBroke)->get();
assertTest($insufficientOrders->count() === 0, "T8b: Zero orders created for insufficient wallet attempt");

Wallet::where('user_id', $brokeUser->id)->delete();
$brokeUser->forceDelete();

// -------------------------------------------------------------
// T11 — CONCURRENT REQUESTS FOR DIFFERENT SLOTS RUN FREELY
// -------------------------------------------------------------
echo PHP_EOL . "--- T11: Concurrency for Different Slots Runs Freely ---" . PHP_EOL;

$slotX = '10:00 - 11:00';
$slotY = '11:00 - 12:00';
$dateDiff = '2026-12-28';

$payloadX = array_merge($basePayload, [
    'buyer_id' => $customer->id,
    'date' => $dateDiff,
    'schedule' => $slotX,
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => (string) Str::uuid(),
]);

$payloadY = array_merge($basePayload, [
    'buyer_id' => $otherCustomer->id,
    'name' => $otherCustomer->name,
    'email' => $otherCustomer->email,
    'date' => $dateDiff,
    'schedule' => $slotY,
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => (string) Str::uuid(),
]);

$workersDiff = runParallelWorkers($payloadX, $payloadY);

assertTest($workersDiff['resA']['status_code'] === 201, "T11a: Concurrent Slot X submission succeeded (HTTP 201)");
assertTest($workersDiff['resB']['status_code'] === 201, "T11b: Concurrent Slot Y submission succeeded (HTTP 201)");

$orderX = Order::where('date', $dateDiff)->where('schedule', $slotX)->first();
$orderY = Order::where('date', $dateDiff)->where('schedule', $slotY)->first();
assertTest(!empty($orderX) && !empty($orderY), "T11c: Both different slots successfully created orders in parallel");

// -------------------------------------------------------------
// T12 — MALFORMED / OVERSIZED IDEMPOTENCY KEY REJECTED
// -------------------------------------------------------------
echo PHP_EOL . "--- T12: Malformed / Oversized Idempotency Key Validation ---" . PHP_EOL;

$payloadOversized = array_merge($basePayload, [
    'date' => '2026-12-29',
    'schedule' => '10:00 - 11:00',
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => str_repeat('a', 65), // 65 chars > max 64
]);

Auth::guard('sanctum')->setUser($customer);
$resOversized = app(App\Http\Controllers\Api\ServiceController::class)->order(Request::create('/api/v1/service/order', 'POST', $payloadOversized));
assertTest($resOversized->getStatusCode() === 422, "T12a: Oversized idempotency key (> 64 chars) returns HTTP 422");

$payloadSpecialChars = array_merge($basePayload, [
    'date' => '2026-12-29',
    'schedule' => '10:00 - 11:00',
    'selected_payment_gateway' => 'cash_on_delivery',
    'idempotency_key' => 'invalid key with spaces & symbols!@#$',
]);
$resSpecialChars = app(App\Http\Controllers\Api\ServiceController::class)->order(Request::create('/api/v1/service/order', 'POST', $payloadSpecialChars));
assertTest($resSpecialChars->getStatusCode() === 422, "T12b: Malformed idempotency key with illegal characters returns HTTP 422");

echo PHP_EOL . "==========================================================" . PHP_EOL;
echo "RESULTS: {$passed} Passed, {$failed} Failed" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
exit(0);
