<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\User;
use App\Order;
use App\Http\Controllers\Api\SellerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$order = Order::where('seller_id', 5)->first();
$sellerId = $order ? $order->seller_id : 5;
$orderId = $order ? $order->id : 14;

echo "--- TEST 1: Provider {$sellerId} (owner of Order {$orderId}) ---" . PHP_EOL;
$userSeller = User::find($sellerId);
Auth::guard('sanctum')->setUser($userSeller);

$req = Request::create("/api/v1/seller/my-orders/{$orderId}", 'POST', ['id' => $orderId]);
$controller = new SellerController();
$res = $controller->singleOrder($req);
echo "Status: " . $res->getStatusCode() . PHP_EOL;
$content = json_decode($res->getContent(), true);
echo "Has orderInfo: " . (isset($content['orderInfo']) ? 'YES' : 'NO') . PHP_EOL;
if (isset($content['orderInfo'])) {
    echo "Order ID: " . $content['orderInfo']['id'] . PHP_EOL;
    echo "Seller ID: " . $content['orderInfo']['seller_id'] . PHP_EOL;
    echo "Buyer ID: " . $content['orderInfo']['buyer_id'] . PHP_EOL;
    echo "Total: " . $content['orderInfo']['total'] . PHP_EOL;
    echo "Payment Status: " . $content['orderInfo']['payment_status'] . PHP_EOL;
}

echo PHP_EOL . "--- TEST 2: Unauthorized User (User 1 is not owner of Order 6) ---" . PHP_EOL;
$user1 = User::find(1);
Auth::guard('sanctum')->setUser($user1);
$resUnauthorized = $controller->singleOrder($req);
echo "Status: " . $resUnauthorized->getStatusCode() . PHP_EOL;
echo "Response: " . $resUnauthorized->getContent() . PHP_EOL;

echo PHP_EOL . "--- TEST 3: Nonexistent Order (Order 999999) ---" . PHP_EOL;
Auth::guard('sanctum')->setUser($userSeller);
$reqNone = Request::create('/api/v1/seller/my-orders/999999', 'POST', ['id' => 999999]);
$resNone = $controller->singleOrder($reqNone);
echo "Status: " . $resNone->getStatusCode() . PHP_EOL;
echo "Response: " . $resNone->getContent() . PHP_EOL;
