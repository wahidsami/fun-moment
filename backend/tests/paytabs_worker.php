<?php

// paytabs_worker.php - Worker script for PayTabs cancellation & verification concurrency tests
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\PayTabsApiController;
use App\Order;
use App\Services\Payment\PayTabsPaymentService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$action = $argv[1] ?? '';
$orderId = isset($argv[2]) ? (int) $argv[2] : 0;
$userId = isset($argv[3]) ? (int) $argv[3] : 0;
$tranRef = $argv[4] ?? ('TST_TRAN_' . time());

if ($action === 'cancel') {
    $user = User::find($userId);
    if ($user) {
        Auth::guard('sanctum')->setUser($user);
    }

    $request = new Request(['order_id' => $orderId]);
    $controller = app(PayTabsApiController::class);
    $response = $controller->cancelPending($request);

    echo json_encode([
        'action' => 'cancel',
        'status_code' => $response->getStatusCode(),
        'data' => json_decode($response->getContent(), true)
    ]);
    exit(0);
}

if ($action === 'apply') {
    $order = Order::find($orderId);
    if (!$order) {
        echo json_encode(['error' => 'Order not found']);
        exit(1);
    }

    $service = app(PayTabsPaymentService::class);
    $sanitizedDetails = [
        'gateway' => 'paytabs',
        'tran_ref' => $tranRef,
        'tran_type' => 'sale',
        'cart_id' => (string) $order->id,
        'cart_amount' => (float) $order->total,
        'cart_currency' => 'SAR',
        'response_status' => 'A',
        'response_code' => '100',
        'response_message' => 'Authorised',
        'payment_method' => 'card',
        'transaction_time' => now()->toIso8601String(),
        'verified_at' => now()->toIso8601String(),
    ];

    $result = $service->applySuccessfulPayment($order, $tranRef, $sanitizedDetails);

    echo json_encode([
        'action' => 'apply',
        'status_code' => 200,
        'already_completed' => $result['already_completed'],
        'order_status' => $result['order']->status,
        'payment_status' => $result['order']->payment_status,
    ]);
    exit(0);
}

echo json_encode(['error' => 'Invalid action']);
exit(1);
