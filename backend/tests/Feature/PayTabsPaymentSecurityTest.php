<?php

namespace Tests\Feature;

use App\Order;
use App\User;
use App\Services\Payment\PayTabsPaymentService;
use App\Http\Controllers\Api\PayTabsApiController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PayTabsPaymentSecurityTest extends TestCase
{
    private function createTestOrder(array $attributes = []): Order
    {
        $base = Order::first();
        $order = $base ? $base->replicate() : new Order();
        foreach ($attributes as $key => $val) {
            $order->{$key} = $val;
        }
        $order->save();
        return $order;
    }

    public function test_unauthenticated_initiate_request_is_rejected()
    {
        Auth::forgetGuards();
        $order = Order::first();
        if (!$order) {
            $this->markTestSkipped('No base order available.');
        }

        $controller = new PayTabsApiController();
        $service = app(PayTabsPaymentService::class);

        $request = Request::create('/api/v1/user/paytabs/initiate', 'POST', [
            'order_id' => $order->id,
        ]);

        $response = $controller->initiate($request, $service);
        $this->assertTrue(in_array($response->getStatusCode(), [401, 403]));
    }

    public function test_unauthenticated_verify_request_is_rejected()
    {
        Auth::forgetGuards();
        $order = Order::first();
        if (!$order) {
            $this->markTestSkipped('No base order available.');
        }

        $controller = new PayTabsApiController();
        $service = app(PayTabsPaymentService::class);

        $request = Request::create('/api/v1/user/paytabs/verify-transaction', 'POST', [
            'order_id' => $order->id,
            'tran_ref' => 'TST_FAKE_REF',
        ]);

        $response = $controller->verify($request, $service);
        $this->assertTrue(in_array($response->getStatusCode(), [401, 403]));
    }

    public function test_cross_user_order_hijacking_is_blocked()
    {
        $order = $this->createTestOrder([
            'buyer_id' => 99881,
            'total' => 150.00,
            'payment_gateway' => 'paytabs',
            'payment_status' => 'pending',
            'status' => 0,
        ]);

        $attacker = new User();
        $attacker->id = 99882;
        Auth::guard('sanctum')->setUser($attacker);

        $controller = new PayTabsApiController();
        $service = app(PayTabsPaymentService::class);

        $req = Request::create('/api/v1/user/paytabs/initiate', 'POST', ['order_id' => $order->id]);
        $res = $controller->initiate($req, $service);
        $this->assertEquals(403, $res->getStatusCode());

        $verifyReq = Request::create('/api/v1/user/paytabs/verify-transaction', 'POST', [
            'order_id' => $order->id,
            'tran_ref' => 'TST_ATTACK_01',
        ]);
        $verifyRes = $controller->verify($verifyReq, $service);
        $this->assertEquals(403, $verifyRes->getStatusCode());

        $order->delete();
    }

    public function test_legacy_endpoint_rejects_paytabs_bypass()
    {
        $order = $this->createTestOrder([
            'buyer_id' => 99881,
            'total' => 200.00,
            'payment_gateway' => 'paytabs',
            'payment_status' => 'pending',
            'status' => 0,
        ]);

        $buyer = new User();
        $buyer->id = 99881;
        Auth::guard('sanctum')->setUser($buyer);

        $serviceController = new ServiceController();
        $req = Request::create('/api/v1/user/payment-status-update', 'POST', ['order_id' => $order->id]);
        $res = $serviceController->paymentStatusUpdate($req);
        $order->refresh();

        $this->assertEquals(422, $res->getStatusCode());
        $this->assertEquals('pending', $order->payment_status);

        $order->delete();
    }

    public function test_legacy_endpoint_preserves_cod_backward_compatibility()
    {
        $order = $this->createTestOrder([
            'buyer_id' => 99881,
            'total' => 250.00,
            'payment_gateway' => 'cash_on_delivery',
            'payment_status' => 'pending',
            'status' => 0,
        ]);

        $buyer = new User();
        $buyer->id = 99881;
        Auth::guard('sanctum')->setUser($buyer);

        $serviceController = new ServiceController();
        $req = Request::create('/api/v1/user/payment-status-update', 'POST', ['order_id' => $order->id]);
        $res = $serviceController->paymentStatusUpdate($req);
        $order->refresh();

        $this->assertTrue(in_array($res->getStatusCode(), [200, 201]));
        $this->assertEquals('complete', $order->payment_status);

        $order->delete();
    }

    public function test_ipn_webhook_enforces_hmac_signature()
    {
        $service = app(PayTabsPaymentService::class);
        $controller = new PayTabsApiController();

        $req = Request::create('/api/v1/paytabs/ipn', 'POST', [], [], [], [
            'HTTP_SIGNATURE' => 'bad_signature',
        ], json_encode(['tran_ref' => 'TST_01', 'cart_id' => '101']));

        $res = $controller->ipn($req, $service);
        $this->assertEquals(400, $res->getStatusCode());
    }

    public function test_verification_rejects_tampered_amount()
    {
        $service = new PayTabsPaymentService();
        $order = new Order();
        $order->id = 888805;
        $order->total = 100.00;
        $order->payment_gateway = 'paytabs';

        $tampered = [
            'tran_ref' => 'TST_TAMPER_01',
            'cart_id' => '888805',
            'cart_amount' => '10.00',
            'cart_currency' => 'SAR',
            'payment_result' => [
                'response_status' => 'A',
                'response_code' => 'G12345',
                'response_message' => 'Authorised'
            ]
        ];

        $res = $service->validateTransactionData($order, $tampered['tran_ref'], $tampered);
        $this->assertFalse($res['verified']);
        $this->assertEquals('AMOUNT_MISMATCH', $res['reason']);
    }

    public function test_idempotent_payment_application()
    {
        $service = app(PayTabsPaymentService::class);
        $order = $this->createTestOrder([
            'buyer_id' => 99881,
            'total' => 300.00,
            'payment_gateway' => 'paytabs',
            'payment_status' => 'pending',
            'status' => 0,
        ]);

        $details = [
            'tran_ref' => 'TST_IDEMPOTENT_888',
            'payment_method' => 'CreditCard',
            'verified_at' => now()->toIso8601String(),
        ];

        $res1 = $service->applySuccessfulPayment($order, 'TST_IDEMPOTENT_888', $details);
        $order->refresh();

        $this->assertFalse($res1['already_completed']);
        $this->assertEquals('complete', $order->payment_status);
        $this->assertEquals('TST_IDEMPOTENT_888', $order->transaction_id);
        $this->assertNotNull($order->payment_verified_at);

        $res2 = $service->applySuccessfulPayment($order, 'TST_IDEMPOTENT_888', $details);
        $this->assertTrue($res2['already_completed']);

        $order->delete();
    }
}
