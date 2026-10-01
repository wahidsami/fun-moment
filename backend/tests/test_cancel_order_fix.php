<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\User;
use App\Order;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "--- TEST 1: Provider calling Customer Cancel Endpoint (Must be rejected) ---" . PHP_EOL;
$provider = User::where('user_type', 0)->first();
Auth::guard('sanctum')->setUser($provider);

$controller = new UserController();
$req = Request::create('/api/v1/user/my-orders/order/cancel', 'POST', ['id' => 1]);
$res1 = $controller->cancelOrder($req);
echo "Status: " . $res1->getStatusCode() . PHP_EOL;
echo "Response: " . $res1->getContent() . PHP_EOL;

echo PHP_EOL . "--- TEST 2: Customer cancelling an order they do not own (Must be rejected) ---" . PHP_EOL;
$customer = User::where('user_type', 1)->first();
Auth::guard('sanctum')->setUser($customer);
// Find an order that does NOT belong to this customer
$otherOrder = Order::where('buyer_id', '!=', $customer->id)->first();
if ($otherOrder) {
    $reqOther = Request::create('/api/v1/user/my-orders/order/cancel', 'POST', ['id' => $otherOrder->id]);
    $res2 = $controller->cancelOrder($reqOther);
    echo "Status: " . $res2->getStatusCode() . PHP_EOL;
    echo "Response: " . $res2->getContent() . PHP_EOL;
}

echo PHP_EOL . "--- TEST 3: Customer cancelling a completed order (Must be rejected) ---" . PHP_EOL;
$completedOrder = Order::where('buyer_id', $customer->id)->where('status', 2)->first();
if ($completedOrder) {
    $reqComp = Request::create('/api/v1/user/my-orders/order/cancel', 'POST', ['id' => $completedOrder->id]);
    $res3 = $controller->cancelOrder($reqComp);
    echo "Status: " . $res3->getStatusCode() . PHP_EOL;
    echo "Response: " . $res3->getContent() . PHP_EOL;
} else {
    echo "No completed order for customer {$customer->id} found." . PHP_EOL;
}

echo PHP_EOL . "--- TEST 4: Customer cancelling a paid order (Must be rejected for financial safety) ---" . PHP_EOL;
$paidOrder = Order::where('buyer_id', $customer->id)->where('payment_status', 'complete')->where('status', 0)->first();
if ($paidOrder) {
    $reqPaid = Request::create('/api/v1/user/my-orders/order/cancel', 'POST', ['id' => $paidOrder->id]);
    $res4 = $controller->cancelOrder($reqPaid);
    echo "Status: " . $res4->getStatusCode() . PHP_EOL;
    echo "Response: " . $res4->getContent() . PHP_EOL;
} else {
    echo "No pending paid order found for customer {$customer->id}." . PHP_EOL;
}
