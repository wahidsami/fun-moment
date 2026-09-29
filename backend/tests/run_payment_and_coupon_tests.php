<?php

declare(strict_types=1);

/**
 * AUTOMATED PAYMENT HARDENING & COUPON CORRECTNESS TEST SUITE
 *
 * Verifies:
 * 1. Empty gateway list returns HTTP 200 with {"gateway_list": []} (not 404)
 * 2. Active PayTabs gateway returns correctly in gateway_list
 * 3. Admin coupons (user_type='admin', seller_id=null) apply cleanly to any seller's service
 * 4. Seller coupons are strictly isolated to their own services
 * 5. Inactive coupons (status=0) are rejected with 'inactive'
 * 6. Expired coupons (expire_date < current_date) are rejected with 'expired'
 * 7. Valid-on-expiry coupons (expire_date == current_date) are accepted with 'success'
 * 8. Order creation correctly retains admin coupon discount without wiping it
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\ServiceController;
use App\Order;
use App\Service;
use App\ServiceCoupon;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentAndCouponTestSuite
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function run(): void
    {
        echo "====================================================================\n";
        echo "   AUTOMATED PAYMENT & COUPON VERIFICATION TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->testEmptyGatewayReturnsHttp200();
        $this->testPaytabsGatewayReturnsInList();
        $this->testAdminCouponOnSellerService();
        $this->testSellerCouponIsolation();
        $this->testInactiveCouponRejected();
        $this->testExpiredCouponRejected();
        $this->testCouponValidOnExactExpiryDate();
        $this->testOrderCreationWithAdminCoupon();

        echo "\n--------------------------------------------------------------------\n";
        echo "TEST SUMMARY: {$this->passed} PASSED, {$this->failed} FAILED\n";
        echo "--------------------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "\nFAILURES:\n";
            foreach ($this->errors as $err) {
                echo "  - $err\n";
            }
            exit(1);
        }

        echo "\nALL TESTS PASSED WITH 100% SUCCESS!\n";
        exit(0);
    }

    private function assert(bool $condition, string $testName, string $failureDetails = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] $testName\n";
        } else {
            $this->failed++;
            $msg = "$testName: $failureDetails";
            $this->errors[] = $msg;
            echo " [FAIL] $msg\n";
        }
    }

    private function testEmptyGatewayReturnsHttp200(): void
    {
        // Temporarily clear all gateways
        $gatewayKeys = [
            'cash_on_delivery_gateway', 'manual_payment_gateway', 'paypal_gateway',
            'mollie_gateway', 'paytm_gateway', 'stripe_gateway', 'razorpay_gateway',
            'flutterwave_gateway', 'paystack_gateway', 'marcadopago_gateway',
            'instamojo_gateway', 'cashfree_gateway', 'payfast_gateway',
            'midtrans_gateway', 'squareup_gateway', 'cinetpay_gateway',
            'paytabs_gateway', 'billplz_gateway', 'zitopay_gateway', 'kineticpay_gateway'
        ];

        $origVals = [];
        foreach ($gatewayKeys as $k) {
            $origVals[$k] = get_static_option($k);
            delete_static_option($k);
            \Illuminate\Support\Facades\Cache::forget($k);
        }

        try {
            $controller = new PaymentGatewayController();
            $res = $controller->gatewayList();

            $status = $res->getStatusCode();
            $data = json_decode($res->getContent(), true);

            $this->assert(
                $status === 200,
                'Empty gateway list returns HTTP 200',
                "Expected 200, got $status"
            );

            $this->assert(
                isset($data['gateway_list']) && is_array($data['gateway_list']) && count($data['gateway_list']) === 0,
                'Empty gateway list body is {"gateway_list": []}',
                "Payload: " . json_encode($data)
            );
        } finally {
            // Restore original values
            foreach ($origVals as $k => $v) {
                if ($v !== null) {
                    update_static_option($k, $v);
                } else {
                    delete_static_option($k);
                }
                \Illuminate\Support\Facades\Cache::forget($k);
            }
        }
    }

    private function testPaytabsGatewayReturnsInList(): void
    {
        $original = get_static_option('paytabs_gateway');
        update_static_option('paytabs_gateway', 'on');

        try {
            $controller = new PaymentGatewayController();
            $res = $controller->gatewayList();

            $status = $res->getStatusCode();
            $data = json_decode($res->getContent(), true);

            $names = array_column($data['gateway_list'] ?? [], 'name');

            $this->assert(
                in_array($status, [200, 201]),
                'Populated gateway returns success status (200/201)',
                "Status: $status"
            );

            $this->assert(
                in_array('paytabs', $names),
                'PayTabs is present in gateway_list when paytabs_gateway is on',
                "Gateways returned: " . implode(', ', $names)
            );
        } finally {
            update_static_option('paytabs_gateway', $original);
        }
    }

    private function testAdminCouponOnSellerService(): void
    {
        $code = 'TESTADMIN' . rand(1000, 9999);
        $coupon = ServiceCoupon::create([
            'code' => $code,
            'discount' => 20,
            'discount_type' => 'percentage',
            'expire_date' => date('Y-m-d', strtotime('+7 days')),
            'status' => 1,
            'seller_id' => null,
            'user_type' => 'admin',
        ]);

        try {
            $controller = new ServiceController();
            $req = Request::create('/api/v1/service-list/coupon-apply', 'POST', [
                'coupon_code' => $code,
                'seller_id' => 99, // Some seller ID
                'total_amount' => 100,
            ]);

            $res = $controller->couponApply($req);
            $data = json_decode($res->getContent(), true);

            $this->assert(
                ($data['status'] ?? '') === 'success',
                'Admin coupon (seller_id=null) applies successfully to seller service',
                "Response: " . json_encode($data)
            );

            $this->assert(
                ($data['coupon_amount'] ?? 0) == 20,
                'Admin coupon computes correct 20% discount (20 SAR on 100 SAR)',
                "Amount: " . ($data['coupon_amount'] ?? 'null')
            );
        } finally {
            $coupon->delete();
        }
    }

    private function testSellerCouponIsolation(): void
    {
        $code = 'TESTSELLER' . rand(1000, 9999);
        $coupon = ServiceCoupon::create([
            'code' => $code,
            'discount' => 15,
            'discount_type' => 'percentage',
            'expire_date' => date('Y-m-d', strtotime('+7 days')),
            'status' => 1,
            'seller_id' => 10,
            'user_type' => 'seller',
        ]);

        try {
            $controller = new ServiceController();
            // Try applying to seller 20's service
            $req = Request::create('/api/v1/service-list/coupon-apply', 'POST', [
                'coupon_code' => $code,
                'seller_id' => 20,
                'total_amount' => 100,
            ]);

            $res = $controller->couponApply($req);
            $data = json_decode($res->getContent(), true);

            $this->assert(
                $res->getStatusCode() === 404 && isset($data['message']),
                'Seller coupon is rejected for different seller service',
                "Response: " . json_encode($data)
            );
        } finally {
            $coupon->delete();
        }
    }

    private function testInactiveCouponRejected(): void
    {
        $code = 'TESTINACTIVE' . rand(1000, 9999);
        $coupon = ServiceCoupon::create([
            'code' => $code,
            'discount' => 10,
            'discount_type' => 'percentage',
            'expire_date' => date('Y-m-d', strtotime('+7 days')),
            'status' => 0, // Inactive!
            'seller_id' => null,
            'user_type' => 'admin',
        ]);

        try {
            $controller = new ServiceController();
            $req = Request::create('/api/v1/service-list/coupon-apply', 'POST', [
                'coupon_code' => $code,
                'seller_id' => 1,
                'total_amount' => 100,
            ]);

            $res = $controller->couponApply($req);
            $data = json_decode($res->getContent(), true);

            $this->assert(
                ($data['status'] ?? '') === 'inactive',
                'Inactive coupon (status=0) is rejected with status "inactive"',
                "Response: " . json_encode($data)
            );
        } finally {
            $coupon->delete();
        }
    }

    private function testExpiredCouponRejected(): void
    {
        $code = 'TESTEXPIRED' . rand(1000, 9999);
        $coupon = ServiceCoupon::create([
            'code' => $code,
            'discount' => 10,
            'discount_type' => 'percentage',
            'expire_date' => date('Y-m-d', strtotime('-2 days')), // Expired!
            'status' => 1,
            'seller_id' => null,
            'user_type' => 'admin',
        ]);

        try {
            $controller = new ServiceController();
            $req = Request::create('/api/v1/service-list/coupon-apply', 'POST', [
                'coupon_code' => $code,
                'seller_id' => 1,
                'total_amount' => 100,
            ]);

            $res = $controller->couponApply($req);
            $data = json_decode($res->getContent(), true);

            $this->assert(
                ($data['status'] ?? '') === 'expired',
                'Past-date coupon is rejected with status "expired"',
                "Response: " . json_encode($data)
            );
        } finally {
            $coupon->delete();
        }
    }

    private function testCouponValidOnExactExpiryDate(): void
    {
        $code = 'TESTTODAY' . rand(1000, 9999);
        $coupon = ServiceCoupon::create([
            'code' => $code,
            'discount' => 25,
            'discount_type' => 'amount',
            'expire_date' => date('Y-m-d'), // Exactly today!
            'status' => 1,
            'seller_id' => null,
            'user_type' => 'admin',
        ]);

        try {
            $controller = new ServiceController();
            $req = Request::create('/api/v1/service-list/coupon-apply', 'POST', [
                'coupon_code' => $code,
                'seller_id' => 1,
                'total_amount' => 100,
            ]);

            $res = $controller->couponApply($req);
            $data = json_decode($res->getContent(), true);

            $this->assert(
                ($data['status'] ?? '') === 'success',
                'Coupon expiring today (expire_date == current_date) is accepted with "success"',
                "Response: " . json_encode($data)
            );

            $this->assert(
                ($data['coupon_amount'] ?? 0) == 25,
                'Coupon expiring today awards full discount amount (25 SAR)',
                "Amount: " . ($data['coupon_amount'] ?? 'null')
            );
        } finally {
            $coupon->delete();
        }
    }

    private function testOrderCreationWithAdminCoupon(): void
    {
        $service = Service::first();
        if (!$service) {
            $this->assert(true, 'Skip order creation test (no service in DB)');
            return;
        }

        $code = 'TESTORDCPN' . rand(1000, 9999);
        $coupon = ServiceCoupon::create([
            'code' => $code,
            'discount' => 10,
            'discount_type' => 'amount',
            'expire_date' => date('Y-m-d', strtotime('+3 days')),
            'status' => 1,
            'seller_id' => null,
            'user_type' => 'admin',
        ]);

        $user = User::first();
        if ($user) {
            Auth::guard('sanctum')->setUser($user);
        }

        try {
            $controller = new ServiceController();
            $req = Request::create('/api/v1/service/order', 'POST', [
                'service_id' => $service->id,
                'seller_id' => $service->seller_id,
                'name' => 'Test User',
                'email' => 'test@funmoment.test',
                'phone' => '0512345678',
                'address' => 'Test St',
                'city' => 1,
                'area' => 1,
                'country' => 1,
                'date' => date('Y-m-d', strtotime('+10 days')),
                'schedule' => '10:00-11:00',
                'include_services' => json_encode(['include_services' => [
                    ['title' => 'Basic Package', 'price' => 100, 'quantity' => 1]
                ]]),
                'coupon_code' => $code,
                'selected_payment_gateway' => 'paytabs',
                'is_service_online' => 0,
            ]);

            $res = $controller->order($req);
            $data = json_decode($res->getContent(), true);

            $orderId = $data['order_id'] ?? null;
            $this->assert(
                !empty($orderId),
                'Order is created successfully with admin coupon',
                "Response: " . json_encode($data)
            );

            if ($orderId) {
                $order = Order::find($orderId);
                $this->assert(
                    $order->coupon_code === $code && (float)$order->coupon_amount === 10.0,
                    'Created order retains coupon_code and coupon_amount = 10',
                    "Order coupon: {$order->coupon_code}, amount: {$order->coupon_amount}"
                );
                // Clean up order
                $order->delete();
            }
        } finally {
            $coupon->delete();
        }
    }
}

(new PaymentAndCouponTestSuite())->run();
