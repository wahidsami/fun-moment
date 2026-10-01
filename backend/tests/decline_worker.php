<?php

// decline_worker.php - Worker script for concurrency test
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\User;
use App\Http\Controllers\Api\SellerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$orderId = isset($argv[1]) ? (int) $argv[1] : 0;
$sellerId = isset($argv[2]) ? (int) $argv[2] : 0;

$seller = User::find($sellerId);
if (!$seller) {
    echo json_encode(['error' => 'Seller not found']);
    exit(1);
}

Auth::guard('sanctum')->setUser($seller);

$request = new Request(['id' => $orderId, 'status' => 4]);
$controller = new SellerController();

$response = $controller->OrderStatusChange($request);
echo json_encode([
    'status_code' => $response->getStatusCode(),
    'data' => json_decode($response->getContent(), true)
]);
