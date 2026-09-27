<?php

declare(strict_types=1);

/**
 * PHASE 3D — Automated Security & Payment Verification Test Suite
 *
 * Runs comprehensive automated verification for:
 * 1. Authentication barriers (Sanctum guards on initiate/verify)
 * 2. Order ownership authorization (cross-user access blocked)
 * 3. Legacy endpoint hardening (paymentStatusUpdate rejects PayTabs)
 * 4. Backward safety for COD & manual payments
 * 5. Webhook HMAC-SHA256 signature enforcement
 * 6. PayTabs payment verification logic (amount, currency, status)
 * 7. State mutation idempotency
 * 8. Sanitized payment audit details (no cardholder secrets stored)
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Order;
use App\User;
use App\Services\Payment\PayTabsPaymentService;
use App\Http\Controllers\Api\PayTabsApiController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PayTabsSecurityTestSuite
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function run(): void
    {
        echo "====================================================================\n";
        echo "   PHASE 3D: AUTOMATED PAYTABS PAYMENT SECURITY TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->testUnauthenticatedAccessBlocked();
        $this->testCrossUserAuthorizationBlocked();
        $this->testLegacyEndpointRejectsPayTabs();
        $this->testLegacyEndpointPreservesCodBackwardCompatibility();
        $this->testIpnWebhookSignatureVerification();
        $this->testVerificationRejectsMismatchedAmountOrCurrency();
        $this->testVerificationRejectsDeclinedTransaction();
        $this->testPaymentApplicationIdempotency();
        $this->testPaymentDetailsAreSanitizedWithoutSecrets();

        echo "\n--------------------------------------------------------------------\n";
        echo sprintf("RESULTS: %d PASSED, %d FAILED\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "\nFAILURES DETECTED:\n";
            foreach ($this->errors as $err) {
                echo " [X] {$err}\n";
            }
            exit(1);
        } else {
            echo "\n>>> ALL AUTOMATED SECURITY TESTS PASSED SUCCESSFULLY! <<<\n\n";
            exit(0);
        }
    }

    private function assert(bool $condition, string $testName, string $failureDetails = ''): void
    {
        if ($condition) {
            echo " [PASS] {$testName}\n";
            $this->passed++;
        } else {
            echo " [FAIL] {$testName} - {$failureDetails}\n";
            $this->failed++;
            $this->errors[] = "{$testName}: {$failureDetails}";
        }
    }

    private function testUnauthenticatedAccessBlocked(): void
    {
        Auth::forgetGuards();

        // Test initiate without auth
        $controller = new PayTabsApiController();
        $service = app(PayTabsPaymentService::class);

        // Create temporary test order
        $order = Order::first();
        if (!$order) {
            $this->assert(false, "Unauthenticated access blocked", "No order found in test DB");
            return;
        }

        $request = Request::create('/api/v1/user/paytabs/initiate', 'POST', [
            'order_id' => $order->id,
        ]);

        $response = $controller->initiate($request, $service);
        $status = $response->getStatusCode();
        $data = json_decode($response->getContent(), true);

        // Sanctum guard is null, and order->buyer_id != null, so should return 403 or 401
        $blocked = ($status === 403 || $status === 401 || (isset($data['success']) && $data['success'] === false));
        $this->assert($blocked, "1. Unauthenticated initiate request is rejected");

        // Test verify without auth
        $verifyRequest = Request::create('/api/v1/user/paytabs/verify-transaction', 'POST', [
            'order_id' => $order->id,
            'tran_ref' => 'TST_REF_12345',
        ]);

        $verifyResponse = $controller->verify($verifyRequest, $service);
        $verifyData = json_decode($verifyResponse->getContent(), true);
        $this->assert(
            $verifyResponse->getStatusCode() === 403 || ($verifyData['success'] ?? false) === false,
            "2. Unauthenticated verify request is rejected"
        );
    }

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

    private function testCrossUserAuthorizationBlocked(): void
    {
        $userA = User::first();
        $userB = User::skip(1)->first();

        $userAId = $userA ? $userA->id : 99881;
        $userBId = $userB ? $userB->id : 99882;

        $order = $this->createTestOrder([
            'buyer_id' => $userAId,
            'total' => 150.00,
            'payment_gateway' => 'paytabs',
            'payment_status' => 'pending',
            'status' => 0,
        ]);

        // Simulate User B logged into Sanctum
        $userBModel = new User();
        $userBModel->id = $userBId;
        Auth::guard('sanctum')->setUser($userBModel);

        $controller = new PayTabsApiController();
        $service = app(PayTabsPaymentService::class);

        $req = Request::create('/api/v1/user/paytabs/initiate', 'POST', [
            'order_id' => $order->id,
        ]);
        $res = $controller->initiate($req, $service);

        $this->assert(
            $res->getStatusCode() === 403,
            "3. User B cannot initiate checkout for User A's order (HTTP 403)"
        );

        $verifyReq = Request::create('/api/v1/user/paytabs/verify-transaction', 'POST', [
            'order_id' => $order->id,
            'tran_ref' => 'TST_ATTACK_999',
        ]);
        $verifyRes = $controller->verify($verifyReq, $service);

        $this->assert(
            $verifyRes->getStatusCode() === 403,
            "4. User B cannot verify transaction for User A's order (HTTP 403)"
        );

        // Cleanup
        $order->delete();
    }

    private function testLegacyEndpointRejectsPayTabs(): void
    {
        $order = $this->createTestOrder([
            'buyer_id' => 99881,
            'total' => 200.00,
            'payment_gateway' => 'paytabs',
            'payment_status' => 'pending',
            'status' => 0,
        ]);

        $authUser = new User();
        $authUser->id = 99881;
        Auth::guard('sanctum')->setUser($authUser);

        $serviceController = new ServiceController();
        $req = Request::create('/api/v1/user/payment-status-update', 'POST', [
            'order_id' => $order->id,
        ]);

        $res = $serviceController->paymentStatusUpdate($req);
        $order->refresh();

        $this->assert(
            $res->getStatusCode() === 422 && $order->payment_status === 'pending',
            "5. Legacy paymentStatusUpdate strictly blocks PayTabs orders without server verification (HTTP 422, order unpaid)",
            "Response status: " . $res->getStatusCode() . ", Order payment_status: " . $order->payment_status
        );

        $order->delete();
    }

    private function testLegacyEndpointPreservesCodBackwardCompatibility(): void
    {
        $order = $this->createTestOrder([
            'buyer_id' => 99881,
            'total' => 250.00,
            'payment_gateway' => 'cash_on_delivery',
            'payment_status' => 'pending',
            'status' => 0,
        ]);

        $authUser = new User();
        $authUser->id = 99881;
        Auth::guard('sanctum')->setUser($authUser);

        $serviceController = new ServiceController();
        $req = Request::create('/api/v1/user/payment-status-update', 'POST', [
            'order_id' => $order->id,
        ]);

        $res = $serviceController->paymentStatusUpdate($req);
        $order->refresh();

        $this->assert(
            in_array($res->getStatusCode(), [200, 201]) && $order->payment_status === 'complete',
            "6. Legacy paymentStatusUpdate preserves backward compatibility for COD/Cash orders",
            "Status: " . $res->getStatusCode() . ", payment_status: " . $order->payment_status
        );

        $order->delete();
    }

    private function testIpnWebhookSignatureVerification(): void
    {
        $service = app(PayTabsPaymentService::class);
        $controller = new PayTabsApiController();

        // 1. Missing signature
        $req1 = Request::create('/api/v1/paytabs/ipn', 'POST', [], [], [], [], json_encode([
            'tran_ref' => 'TST_IPN_001',
            'cart_id' => '888804',
        ]));
        $res1 = $controller->ipn($req1, $service);
        $this->assert(
            $res1->getStatusCode() === 400,
            "7. IPN Webhook rejects missing HMAC signature (HTTP 400)"
        );

        // 2. Invalid forged signature
        $req2 = Request::create('/api/v1/paytabs/ipn', 'POST', [], [], [], [
            'HTTP_SIGNATURE' => 'invalid_sha256_forged_signature_xyz',
        ], json_encode([
            'tran_ref' => 'TST_IPN_001',
            'cart_id' => '888804',
        ]));
        $res2 = $controller->ipn($req2, $service);
        $this->assert(
            $res2->getStatusCode() === 400,
            "8. IPN Webhook rejects invalid/tampered HMAC signature (HTTP 400)"
        );
    }

    private function testVerificationRejectsMismatchedAmountOrCurrency(): void
    {
        $service = new PayTabsPaymentService();

        $order = new Order();
        $order->id = 888805;
        $order->total = 100.00;
        $order->payment_gateway = 'paytabs';
        $order->payment_status = 'pending';

        // Tampered amount (10.00 instead of 100.00)
        $tamperedAmountPayload = [
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

        // We test the validation engine directly
        $method = new \ReflectionMethod(PayTabsPaymentService::class, 'validateTransactionData');
        $method->setAccessible(true);

        $result1 = $method->invoke($service, $order, $tamperedAmountPayload['tran_ref'], $tamperedAmountPayload);
        $this->assert(
            $result1['verified'] === false && $result1['reason'] === 'AMOUNT_MISMATCH',
            "9. Verification strictly rejects amount mismatch (Paid 10.00 vs Order 100.00)"
        );

        // Wrong currency (USD instead of SAR)
        $tamperedCurrencyPayload = $tamperedAmountPayload;
        $tamperedCurrencyPayload['cart_amount'] = '100.00';
        $tamperedCurrencyPayload['cart_currency'] = 'USD';

        $result2 = $method->invoke($service, $order, $tamperedCurrencyPayload['tran_ref'], $tamperedCurrencyPayload);
        $this->assert(
            $result2['verified'] === false && $result2['reason'] === 'CURRENCY_MISMATCH',
            "10. Verification strictly rejects currency mismatch (USD vs SAR)"
        );
    }

    private function testVerificationRejectsDeclinedTransaction(): void
    {
        $service = new PayTabsPaymentService();

        $order = new Order();
        $order->id = 888806;
        $order->total = 100.00;
        $order->payment_gateway = 'paytabs';

        $declinedPayload = [
            'tran_ref' => 'TST_DECLINED_01',
            'cart_id' => '888806',
            'cart_amount' => '100.00',
            'cart_currency' => 'SAR',
            'payment_result' => [
                'response_status' => 'D',
                'response_code' => 'D99999',
                'response_message' => 'Insufficient funds'
            ]
        ];

        $method = new \ReflectionMethod(PayTabsPaymentService::class, 'validateTransactionData');
        $method->setAccessible(true);

        $result = $method->invoke($service, $order, $declinedPayload['tran_ref'], $declinedPayload);
        $this->assert(
            $result['verified'] === false && $result['reason'] === 'NOT_AUTHORIZED',
            "11. Verification strictly rejects declined/non-authorized status ('D')",
            $result['message'] ?? ''
        );
    }

    private function testPaymentApplicationIdempotency(): void
    {
        $service = app(PayTabsPaymentService::class);

        $order = $this->createTestOrder([
            'buyer_id' => 99881,
            'total' => 300.00,
            'payment_gateway' => 'paytabs',
            'payment_status' => 'pending',
            'status' => 0,
        ]);

        $sanitizedDetails = [
            'tran_ref' => 'TST_IDEMPOTENT_777',
            'payment_method' => 'CreditCard',
            'card_scheme' => 'Visa',
            'card_type' => 'Credit',
            'verified_at' => now()->toIso8601String(),
            'cart_amount' => 300.00,
            'cart_currency' => 'SAR',
        ];

        // 1st Application
        $res1 = $service->applySuccessfulPayment($order, 'TST_IDEMPOTENT_777', $sanitizedDetails);
        $order->refresh();

        $firstPassed = (
            $res1['already_completed'] === false &&
            $order->payment_status === 'complete' &&
            $order->transaction_id === 'TST_IDEMPOTENT_777' &&
            $order->payment_verified_at !== null
        );

        $this->assert(
            $firstPassed,
            "12. First payment application marks order complete, sets payment_verified_at and transaction_id"
        );

        // 2nd Application with identical tran_ref
        $res2 = $service->applySuccessfulPayment($order, 'TST_IDEMPOTENT_777', $sanitizedDetails);
        $this->assert(
            $res2['already_completed'] === true,
            "13. Second payment application is idempotent (already_completed = true, zero side-effects)"
        );

        $order->delete();
    }

    private function testPaymentDetailsAreSanitizedWithoutSecrets(): void
    {
        $service = new PayTabsPaymentService();

        $rawPayTabsPayload = [
            'tran_ref' => 'TST_AUDIT_888',
            'cart_id' => '888808',
            'cart_amount' => '150.00',
            'cart_currency' => 'SAR',
            'payment_result' => [
                'response_status' => 'A',
                'response_code' => 'G00001',
                'response_message' => 'Authorised',
                'acquirer_message' => 'Approved',
                'acquirer_rrn' => '123456789012'
            ],
            'payment_info' => [
                'payment_method' => 'CreditCard',
                'card_type' => 'Credit',
                'card_scheme' => 'Mastercard',
                'payment_description' => '4111 11## #### 1111',
                'expiryMonth' => 12,
                'expiryYear' => 2028,
                'cvv' => '999', // Simulate raw sensitive data from gateway
                'card_number' => '4111111111111111', // Simulate sensitive pan
            ]
        ];

        $method = new \ReflectionMethod(PayTabsPaymentService::class, 'extractSanitizedAuditDetails');
        $method->setAccessible(true);

        $sanitized = $method->invoke($service, $rawPayTabsPayload);

        $jsonStr = json_encode($sanitized);

        $noCvv = !str_contains($jsonStr, '999') && !isset($sanitized['cvv']);
        $noRawCard = !str_contains($jsonStr, '4111111111111111') && !isset($sanitized['card_number']);
        $hasAuditFields = (
            isset($sanitized['tran_ref']) &&
            isset($sanitized['card_scheme']) &&
            isset($sanitized['verified_at'])
        );

        $this->assert(
            $noCvv && $noRawCard && $hasAuditFields,
            "14. payment_details contains only sanitized audit metadata (NO cardholder secrets / CVV)"
        );
    }
}

// Execute test suite
(new PayTabsSecurityTestSuite())->run();
