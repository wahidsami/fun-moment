<?php

namespace App\Services\Payment;

use App\Mail\OrderMail;
use App\Notifications\OrderNotification;
use App\Order;
use App\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PayTabsPaymentService
{
    protected string $profileId;
    protected string $serverKey;
    protected string $region;
    protected bool $testMode;
    protected string $baseUrl;

    public function __construct()
    {
        $this->profileId = (string) (config('paytabs.profile_id') ?: get_static_option('paytabs_profile_id') ?: '');
        $this->serverKey = (string) (config('paytabs.server_key') ?: get_static_option('paytabs_server_key') ?: '');
        $this->region = strtoupper((string) (config('paytabs.region') ?: get_static_option('paytabs_region') ?: 'SAU'));
        $this->testMode = config('paytabs.test_mode') !== null
            ? (bool) config('paytabs.test_mode')
            : !empty(get_static_option('paytabs_test_mode'));

        $this->baseUrl = match ($this->region) {
            'SAU' => 'https://secure.paytabs.sa',
            'EGY' => 'https://secure-egypt.paytabs.com',
            'OMN' => 'https://secure-oman.paytabs.com',
            'JOR' => 'https://secure-jordan.paytabs.com',
            default => 'https://secure.paytabs.com',
        };
    }

    public function isConfigured(): bool
    {
        return !empty($this->profileId) && !empty($this->serverKey);
    }

    public function getProfileId(): string
    {
        return $this->profileId;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * Initiate a payment session on PayTabs servers and return the redirect URL.
     */
    public function initiatePayment(Order $order, string $returnUrl, ?string $callbackUrl = null): array
    {
        if (!$this->isConfigured()) {
            Log::error('[PayTabs] Cannot initiate payment: PayTabs credentials not configured in system.');
            return [
                'success' => false,
                'message' => __('PayTabs payment gateway is not properly configured.')
            ];
        }

        $currency = get_static_option('site_global_currency') ?: 'SAR';
        $cartId = (string) $order->id;
        $amount = (float) number_format((float) $order->total, 2, '.', '');
        $customerName = trim($order->name) ?: 'Customer';
        $customerEmail = trim($order->email) ?: 'customer@funmoment.sa';
        $customerPhone = trim($order->phone) ?: '0500000000';

        $payload = [
            'profile_id' => (int) $this->profileId,
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $cartId,
            'cart_description' => 'FUN MOMENT Order #' . $order->id,
            'cart_currency' => $currency,
            'cart_amount' => $amount,
            'customer_details' => [
                'name' => $customerName,
                'email' => $customerEmail,
                'phone' => $customerPhone,
                'street1' => $order->address ?: 'N/A',
                'city' => (string) ($order->city ?: 'Riyadh'),
                'country' => 'SA',
            ],
            'return' => $returnUrl,
            'callback' => $callbackUrl ?: route('api.paytabs.ipn'),
            'hide_shipping' => true,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/payment/request', $payload);

            if ($response->successful()) {
                $resData = $response->json();
                if (!empty($resData['redirect_url'])) {
                    return [
                        'success' => true,
                        'redirect_url' => $resData['redirect_url'],
                        'tran_ref' => $resData['tran_ref'] ?? null,
                        'order_id' => $order->id,
                    ];
                }
                Log::warning('[PayTabs] Payment initiate missing redirect_url', ['response' => $resData]);
                return [
                    'success' => false,
                    'message' => $resData['message'] ?? __('Failed to initialize payment page.')
                ];
            }

            Log::error('[PayTabs] Payment initiate HTTP error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [
                'success' => false,
                'message' => __('Payment service error. Please try again.')
            ];
        } catch (\Exception $e) {
            Log::error('[PayTabs] Exception during initiatePayment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Network error contacting payment service.')
            ];
        }
    }

    /**
     * Query PayTabs directly by transaction reference to independently verify status.
     */
    public function queryTransaction(string $tranRef): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'PayTabs credentials missing'
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/payment/query', [
                'profile_id' => (int) $this->profileId,
                'tran_ref' => trim($tranRef),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            Log::warning('[PayTabs] Query transaction unsuccessful', [
                'tran_ref' => $tranRef,
                'status' => $response->status()
            ]);
            return [
                'success' => false,
                'message' => 'PayTabs query failed'
            ];
        } catch (\Exception $e) {
            Log::error('[PayTabs] Exception querying transaction ' . $tranRef . ': ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Authoritative server-side verification of a PayTabs transaction against an Order.
     */
    public function verifyOrderPayment(Order $order, string $tranRef): array
    {
        $queryResult = $this->queryTransaction($tranRef);
        if (!$queryResult['success'] || empty($queryResult['data'])) {
            return [
                'verified' => false,
                'reason' => 'TRANSACTION_NOT_FOUND',
                'message' => __('Transaction could not be verified with PayTabs.')
            ];
        }

        return $this->validateTransactionData($order, $tranRef, $queryResult['data']);
    }

    /**
     * Validate transaction data against order specifications.
     */
    public function validateTransactionData(Order $order, string $tranRef, array $pt): array
    {
        $respStatus = $pt['payment_result']['response_status'] ?? null;
        $isAuthorised = ($respStatus === 'A');

        if (!$isAuthorised) {
            $respMessage = $pt['payment_result']['response_message'] ?? 'Payment was not approved';
            Log::info('[PayTabs] Transaction not authorized', [
                'order_id' => $order->id,
                'tran_ref' => $tranRef,
                'response_status' => $respStatus,
                'response_message' => $respMessage
            ]);
            return [
                'verified' => false,
                'reason' => 'NOT_AUTHORIZED',
                'response_status' => $respStatus,
                'message' => __('Payment was not authorized by the bank: ') . $respMessage
            ];
        }

        // Cart ID matching: handles raw order_id "104" or legacy padded "1234510412345"
        $rawCartId = (string) ($pt['cart_id'] ?? '');
        $extractedId = $this->extractOrderIdFromCartId($rawCartId);

        if ((string) $order->id !== $extractedId && (string) $order->id !== $rawCartId) {
            Log::error('[PayTabs] Security Alert: Order ID mismatch', [
                'expected_order_id' => $order->id,
                'paytabs_cart_id' => $rawCartId,
                'extracted_id' => $extractedId,
                'tran_ref' => $tranRef
            ]);
            return [
                'verified' => false,
                'reason' => 'ORDER_ID_MISMATCH',
                'message' => __('Payment transaction does not match this order.')
            ];
        }

        // Amount verification
        $cartAmount = (float) ($pt['cart_amount'] ?? 0);
        $orderTotal = (float) $order->total;
        if (abs($cartAmount - $orderTotal) > 0.05) {
            Log::error('[PayTabs] Security Alert: Amount mismatch', [
                'expected' => $orderTotal,
                'paid' => $cartAmount,
                'tran_ref' => $tranRef
            ]);
            return [
                'verified' => false,
                'reason' => 'AMOUNT_MISMATCH',
                'message' => __('Transaction amount does not match order amount.')
            ];
        }

        // Currency verification
        $paidCurrency = strtoupper((string) ($pt['cart_currency'] ?? ''));
        $expectedCurrency = strtoupper(get_static_option('site_global_currency') ?: 'SAR');
        if (!empty($paidCurrency) && !empty($expectedCurrency) && $paidCurrency !== $expectedCurrency) {
            Log::error('[PayTabs] Security Alert: Currency mismatch', [
                'expected' => $expectedCurrency,
                'paid' => $paidCurrency,
                'tran_ref' => $tranRef
            ]);
            return [
                'verified' => false,
                'reason' => 'CURRENCY_MISMATCH',
                'message' => __('Transaction currency does not match platform currency.')
            ];
        }

        $sanitizedDetails = $this->extractSanitizedAuditDetails($pt, $tranRef);

        return [
            'verified' => true,
            'tran_ref' => $pt['tran_ref'] ?? $tranRef,
            'sanitized_details' => $sanitizedDetails,
        ];
    }

    /**
     * Extract sanitized audit metadata (strictly no cardholder secrets / CVVs).
     */
    public function extractSanitizedAuditDetails(array $pt, string $tranRef = ''): array
    {
        $rawCartId = (string) ($pt['cart_id'] ?? '');
        $cartAmount = (float) ($pt['cart_amount'] ?? 0);
        $paidCurrency = strtoupper((string) ($pt['cart_currency'] ?? ''));
        $respStatus = $pt['payment_result']['response_status'] ?? null;

        return [
            'gateway' => 'paytabs',
            'tran_ref' => $pt['tran_ref'] ?? $tranRef,
            'tran_type' => $pt['tran_type'] ?? 'sale',
            'cart_id' => $rawCartId,
            'cart_amount' => $cartAmount,
            'cart_currency' => $paidCurrency,
            'response_status' => $respStatus,
            'response_code' => $pt['payment_result']['response_code'] ?? null,
            'response_message' => $pt['payment_result']['response_message'] ?? null,
            'payment_method' => $pt['payment_info']['payment_method'] ?? 'card',
            'card_type' => $pt['payment_info']['card_type'] ?? null,
            'card_scheme' => $pt['payment_info']['card_scheme'] ?? null,
            'transaction_time' => $pt['payment_result']['transaction_time'] ?? now()->toIso8601String(),
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Idempotently transition order to complete/paid upon verified transaction.
     */
    public function applySuccessfulPayment(Order $order, string $tranRef, array $sanitizedDetails): array
    {
        // 1. Idempotency Check: if already completed with this tranRef or verified
        if ($order->payment_status === 'complete') {
            Log::info("[PayTabs] Order #{$order->id} payment already completed. Skipping side effects.");
            return [
                'already_completed' => true,
                'order' => $order
            ];
        }

        // 2. Authoritative Database State Mutation
        $order->payment_status = 'complete';
        $order->payment_gateway = 'paytabs';
        $order->transaction_id = $tranRef;
        $order->payment_verified_at = now();
        $order->payment_details = json_encode($sanitizedDetails);
        if ($order->status === 0) {
            $order->status = 1; // 1 = Active / In Progress
        }
        $order->save();

        Log::info("[PayTabs] Order #{$order->id} successfully verified and marked as complete with tran_ref: {$tranRef}");

        // 3. Dispatch Notifications & Emails Safely
        try {
            $seller = User::find($order->seller_id);
            if ($seller) {
                $seller->notify(new OrderNotification(
                    $order->id,
                    $order->service_id,
                    $order->seller_id,
                    $order->buyer_id,
                    __('New verified booking received for Order #') . $order->id,
                    'new_booking',
                    $order->status,
                    'seller'
                ));
            }
        } catch (\Exception $e) {
            Log::error('[PayTabs] Failed dispatching OrderNotification: ' . $e->getMessage());
        }

        try {
            $mailSubject = get_static_option('new_order_email_subject') ?? __('Order Payment Confirmed #');
            $buyerMsg = __('Your payment was successful for order #') . $order->id;
            $sellerMsg = __('Payment confirmed for new order #') . $order->id;

            if (!empty($order->email)) {
                Mail::to($order->email)->send(new OrderMail($mailSubject, $order, $buyerMsg));
            }
            if ($seller && !empty($seller->email)) {
                Mail::to($seller->email)->send(new OrderMail($mailSubject, $order, $sellerMsg));
            }
        } catch (\Exception $e) {
            Log::error('[PayTabs] Failed sending order confirmation mail: ' . $e->getMessage());
        }

        return [
            'already_completed' => false,
            'order' => $order
        ];
    }

    /**
     * Validate PayTabs IPN / Webhook Signature.
     */
    public function isValidIpnSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        if (empty($signatureHeader) || empty($this->serverKey)) {
            return false;
        }

        $computedSignature = hash_hmac('sha256', $rawPayload, $this->serverKey);
        return hash_equals($computedSignature, $signatureHeader);
    }

    /**
     * Helper to extract order_id whether padded (12345{id}67890) or plain ({id}).
     */
    protected function extractOrderIdFromCartId(string $cartId): string
    {
        if (strlen($cartId) > 10 && ctype_digit($cartId)) {
            $unpadded = substr($cartId, 5, -5);
            if (!empty($unpadded) && ctype_digit($unpadded)) {
                return $unpadded;
            }
        }
        return $cartId;
    }
}
