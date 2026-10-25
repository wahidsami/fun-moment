<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MusicEntitlement;
use App\MusicOffer;
use App\MusicOrder;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\MusicFinancialCalculationService;
use App\Services\Music\MusicFulfillmentService;
use App\Services\Payment\PayTabsPaymentService;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Services\WalletService;

class MusicOrderController extends Controller
{
    protected MusicFinancialCalculationService $calcService;
    protected MusicFulfillmentService $fulfillmentService;
    protected PayTabsPaymentService $payTabsService;
    protected WalletService $walletService;

    public function __construct(
        MusicFinancialCalculationService $calcService,
        MusicFulfillmentService $fulfillmentService,
        PayTabsPaymentService $payTabsService,
        WalletService $walletService
    ) {
        $this->calcService = $calcService;
        $this->fulfillmentService = $fulfillmentService;
        $this->payTabsService = $payTabsService;
        $this->walletService = $walletService;
    }

    /**
     * Resolve the authenticated user from Sanctum, web guard, or request token.
     */
    protected function resolveBuyer(Request $request): ?User
    {
        $user = auth('sanctum')->user() ?: auth('web')->user() ?: $request->user();
        if ($user instanceof User) {
            return $user;
        }
        return null;
    }

    /**
     * GET /api/v1/music/orders/quote
     * Authoritative pricing preview before checkout.
     */
    public function quote(Request $request): JsonResponse
    {
        $request->validate([
            'offer_id' => 'required|integer',
        ]);

        $offer = MusicOffer::with('track')->find($request->input('offer_id'));
        if (!$offer || !$offer->is_active) {
            return response()->json([
                'success' => false,
                'error_code' => 'OFFER_NOT_FOUND',
                'message_en' => 'Music offer not found or is no longer active.',
                'message_ar' => 'عرض الموسيقى غير موجود أو لم يعد نشطاً.',
            ], 404);
        }

        $track = $offer->track;
        if (!$track || $track->status !== MusicTrack::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'error_code' => 'TRACK_NOT_AVAILABLE',
                'message_en' => 'This track is currently not available for purchase.',
                'message_ar' => 'هذا المقطع غير متاح للشراء حالياً.',
            ], 422);
        }

        $seller = $track->seller_id ? User::find($track->seller_id) : null;
        $breakdown = $this->calcService->calculateOfferBreakdown($offer, $seller);

        return response()->json([
            'success' => true,
            'checkout_enabled' => MusicSetting::isCheckoutEnabled(),
            'vat_enabled' => MusicSetting::isVatEnabled(),
            'track' => [
                'id' => $track->id,
                'title_en' => $track->title_en,
                'title_ar' => $track->title_ar,
                'artist_name' => $track->artist_name,
                'cover_image' => $track->cover_image,
            ],
            'offer' => [
                'id' => $offer->id,
                'offer_type' => $offer->offer_type,
                'rental_duration_days' => $offer->rental_duration_days,
            ],
            'quote' => $breakdown,
        ]);
    }

    /**
     * POST /api/v1/music/orders
     * Create an order with authoritative calculation and payment processing.
     */
    public function store(Request $request): JsonResponse
    {
        $buyer = $this->resolveBuyer($request);
        if (!$buyer) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message_en' => 'Authentication required to checkout.',
                'message_ar' => 'يجب تسجيل الدخول لإتمام عملية الشراء.',
            ], 401);
        }

        // 1. Critical Commercial Gate: Is checkout enabled?
        if (!MusicSetting::isCheckoutEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'CHECKOUT_DISABLED',
                'message_en' => 'Music commercial checkout is currently paused pending platform tax review.',
                'message_ar' => 'شراء الموسيقى معطل حالياً بانتظار مراجعة السياسات الضريبية.',
            ], 403);
        }

        $request->validate([
            'offer_id' => 'required|integer',
            'payment_gateway' => 'required|string|in:wallet,paytabs',
            'idempotency_key' => 'nullable|string|max:64',
            'return_url' => 'nullable|string|url',
        ]);

        $idempotencyKey = trim((string) $request->input('idempotency_key'));
        if (!empty($idempotencyKey)) {
            $existingOrder = MusicOrder::where('buyer_id', $buyer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingOrder) {
                return response()->json([
                    'success' => true,
                    'already_exists' => true,
                    'message_en' => 'Existing order retrieved by idempotency key.',
                    'message_ar' => 'تم استرجاع الطلب السابق بنجاح.',
                    'order' => $this->formatOrderResponse($existingOrder),
                ]);
            }
        }

        // 2. Validate Track & Offer
        $offer = MusicOffer::with('track')->find($request->input('offer_id'));
        if (!$offer || !$offer->is_active) {
            return response()->json([
                'success' => false,
                'error_code' => 'OFFER_NOT_FOUND',
                'message_en' => 'The selected offer is not active.',
                'message_ar' => 'العرض المحدد غير نشط.',
            ], 404);
        }

        if ($offer->isFree()) {
            return response()->json([
                'success' => false,
                'error_code' => 'FREE_OFFER_NO_CHECKOUT',
                'message_en' => 'Free offers do not require commercial payment checkout.',
                'message_ar' => 'المقاطع المجانية لا تتطلب دفعاً مالياً.',
            ], 422);
        }

        $track = $offer->track;
        if (!$track || $track->status !== MusicTrack::STATUS_APPROVED || $track->media_status !== MusicTrack::MEDIA_STATUS_READY) {
            return response()->json([
                'success' => false,
                'error_code' => 'TRACK_NOT_AVAILABLE',
                'message_en' => 'This track is not approved or ready for commercial streaming.',
                'message_ar' => 'هذا المقطع غير معتمد أو غير جاهز للبث التجاري.',
            ], 422);
        }

        // 3. Prevent duplicate active perpetual purchase
        if ($offer->isSale()) {
            $existingEntitlement = MusicEntitlement::where('user_id', $buyer->id)
                ->where('track_id', $track->id)
                ->whereNull('expires_at')
                ->where('is_active', true)
                ->first();

            if ($existingEntitlement) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ALREADY_PURCHASED',
                    'message_en' => 'You already own permanent in-app streaming access to this track.',
                    'message_ar' => 'أنت تمتلك بالفعل حق الاستماع الدائم لهذا المقطع.',
                ], 422);
            }
        }

        $seller = $track->seller_id ? User::find($track->seller_id) : null;
        $calc = $this->calcService->calculateOfferBreakdown($offer, $seller);

        $gateway = $request->input('payment_gateway');

        // 4. Create Order inside DB transaction
        $order = DB::transaction(function () use ($buyer, $track, $offer, $seller, $calc, $gateway, $idempotencyKey) {
            return MusicOrder::create([
                'buyer_id' => $buyer->id,
                'buyer_name' => $buyer->name,
                'buyer_email' => $buyer->email,
                'seller_id' => $track->seller_id,
                'seller_name' => $seller ? $seller->name : null,
                'seller_email' => $seller ? $seller->email : null,
                'admin_id' => $track->admin_id,
                'track_id' => $track->id,
                'offer_id' => $offer->id,
                'offer_type' => $offer->offer_type,
                'subtotal' => $calc['subtotal'],
                'vat_rate' => $calc['vat_rate'],
                'vat_amount' => $calc['vat_amount'],
                'amount' => $calc['total_amount'],
                'currency' => $calc['currency'],
                'commission_rate' => $calc['commission_rate'],
                'commission_amount' => $calc['commission_amount'],
                'commission_vat_amount' => $calc['commission_vat_amount'],
                'seller_earning' => $calc['seller_earning'],
                'tax_treatment' => $calc['tax_treatment'],
                'price_display_mode' => $calc['price_display_mode'],
                'payment_gateway' => $gateway,
                'payment_status' => MusicOrder::STATUS_PENDING,
                'idempotency_key' => $idempotencyKey ?: null,
                'financial_snapshot' => $calc,
            ]);
        });

        // 5. Payment Processing
        if ($gateway === MusicOrder::GATEWAY_WALLET) {
            return $this->processWalletPayment($order, $buyer, $calc);
        }

        if ($gateway === MusicOrder::GATEWAY_PAYTABS) {
            return $this->processPayTabsPayment($order, $buyer, $request->input('return_url'));
        }

        return response()->json([
            'success' => false,
            'error_code' => 'UNSUPPORTED_GATEWAY',
            'message_en' => 'Unsupported payment method.',
            'message_ar' => 'طريقة الدفع غير مدعومة.',
        ], 422);
    }

    /**
     * Process direct wallet debit and instant entitlement fulfillment.
     */
    protected function processWalletPayment(MusicOrder $order, User $buyer, array $calc): JsonResponse
    {
        $totalRequired = (float) $calc['total_amount'];

        try {
            // Verify spendable balance on the server
            $wallet = Wallet::where('user_id', $buyer->id)->first();
            $availableBalance = $wallet ? (float) $wallet->balance : 0.00;

            if ($availableBalance < $totalRequired) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INSUFFICIENT_WALLET_BALANCE',
                    'available_balance' => $availableBalance,
                    'required_amount' => $totalRequired,
                    'message_en' => sprintf(
                        'Insufficient wallet balance (Available: %s SAR, Required: %s SAR).',
                        number_format($availableBalance, 2),
                        number_format($totalRequired, 2)
                    ),
                    'message_ar' => sprintf(
                        'رصيد المحفظة غير كافٍ (المتاح: %s ريال، المطلوب: %s ريال).',
                        number_format($availableBalance, 2),
                        number_format($totalRequired, 2)
                    ),
                ], 422);
            }

            // Atomic debit with transaction
            $walletHistory = $this->walletService->debit(
                $buyer,
                $totalRequired,
                'music_order',
                (string) $order->id,
                "Purchase of music track #{$order->track_id} ({$order->offer_type})",
                "شراء مقطع موسيقي #{$order->track_id} ({$order->offer_type})"
            );

            // Authoritative Fulfillment
            $fulfillment = $this->fulfillmentService->fulfillOrder(
                $order,
                MusicOrder::GATEWAY_WALLET,
                $walletHistory->transaction_id,
                [
                    'wallet_history_id' => $walletHistory->id,
                    'debited_amount' => $totalRequired,
                    'remaining_balance' => $walletHistory->balance_after,
                ]
            );

            return response()->json([
                'success' => true,
                'payment_status' => MusicOrder::STATUS_COMPLETED,
                'message_en' => 'Payment successful and music access unlocked.',
                'message_ar' => 'تم الدفع بنجاح وإتاحة الاستماع للمقطع.',
                'order' => $this->formatOrderResponse($fulfillment['order']),
                'entitlement' => [
                    'id' => $fulfillment['entitlement']->id,
                    'track_id' => $fulfillment['entitlement']->track_id,
                    'access_type' => $fulfillment['entitlement']->access_type,
                    'expires_at' => optional($fulfillment['entitlement']->expires_at)->toIso8601String(),
                    'is_perpetual' => $fulfillment['entitlement']->isPerpetual(),
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error("[MusicOrderController] Wallet debit exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'WALLET_PAYMENT_FAILED',
                'message_en' => 'Failed to process wallet payment: ' . $e->getMessage(),
                'message_ar' => 'تعذر إتمام الدفع بواسطة المحفظة.',
            ], 500);
        }
    }

    /**
     * Process PayTabs payment initiation.
     */
    protected function processPayTabsPayment(MusicOrder $order, User $buyer, ?string $returnUrl): JsonResponse
    {
        $defaultReturn = url('/api/v1/music/payments/paytabs/return?order_id=' . $order->id);
        $resolvedReturnUrl = !empty($returnUrl) ? $returnUrl : $defaultReturn;
        $callbackUrl = url('/api/v1/music/payments/paytabs/callback');

        if (!$this->payTabsService->isConfigured()) {
            return response()->json([
                'success' => false,
                'error_code' => 'PAYTABS_NOT_CONFIGURED',
                'message_en' => 'PayTabs gateway credentials are not configured.',
                'message_ar' => 'بوابة الدفع باي تابس غير مهيأة.',
            ], 503);
        }

        // Create compatible mock Order adapter or use PayTabs HTTP directly for MusicOrder
        $cartId = (string) $order->id;
        $amount = (float) number_format((float) $order->amount, 2, '.', '');
        $currency = $order->currency ?: 'SAR';

        $payload = [
            'profile_id' => (int) $this->payTabsService->getProfileId(),
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $cartId,
            'cart_description' => 'FUN MOMENT Music Order #' . $order->invoice,
            'cart_currency' => $currency,
            'cart_amount' => $amount,
            'customer_details' => [
                'name' => trim($buyer->name) ?: 'Music Buyer',
                'email' => trim($buyer->email) ?: 'buyer@funmoment.sa',
                'phone' => trim($buyer->phone) ?: '0500000000',
                'street1' => 'N/A',
                'city' => 'Riyadh',
                'country' => 'SA',
            ],
            'return' => $resolvedReturnUrl,
            'callback' => $callbackUrl,
            'hide_shipping' => true,
        ];

        try {
            $region = $this->payTabsService->getRegion();
            $baseUrl = match ($region) {
                'SAU' => 'https://secure.paytabs.sa',
                'EGY' => 'https://secure-egypt.paytabs.com',
                default => 'https://secure.paytabs.com',
            };

            $serverKey = config('paytabs.server_key') ?: get_static_option('paytabs_server_key') ?: '';

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($baseUrl . '/payment/request', $payload);

            if ($response->successful()) {
                $resData = $response->json();
                if (!empty($resData['redirect_url'])) {
                    $order->update([
                        'payment_details' => array_merge($order->payment_details ?: [], [
                            'tran_ref' => $resData['tran_ref'] ?? null,
                            'initiated_at' => now()->toIso8601String(),
                        ]),
                    ]);

                    return response()->json([
                        'success' => true,
                        'payment_status' => MusicOrder::STATUS_PENDING,
                        'redirect_url' => $resData['redirect_url'],
                        'tran_ref' => $resData['tran_ref'] ?? null,
                        'order' => $this->formatOrderResponse($order),
                    ], 201);
                }

                return response()->json([
                    'success' => false,
                    'error_code' => 'PAYTABS_INIT_FAILED',
                    'message_en' => $resData['message'] ?? 'Failed to initialize PayTabs session.',
                    'message_ar' => 'فشل في تهيئة جلسة الدفع.',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'error_code' => 'PAYTABS_HTTP_ERROR',
                'message_en' => 'Payment gateway communication error.',
                'message_ar' => 'خطأ في الاتصال ببوابة الدفع.',
            ], 502);
        } catch (\Throwable $e) {
            Log::error("[MusicOrderController] PayTabs exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error_code' => 'PAYTABS_EXCEPTION',
                'message_en' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/music/payments/paytabs/callback
     * Authoritative server-to-server webhook (IPN) from PayTabs.
     */
    public function handlePayTabsCallback(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('signature');

        // Signature Verification
        if (!$this->payTabsService->isValidIpnSignature($rawPayload, $signature)) {
            Log::warning('[MusicPayTabsCallback] Invalid IPN signature rejected.');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $data = $request->all();
        $cartId = (string) ($data['cart_id'] ?? '');
        $tranRef = (string) ($data['tran_ref'] ?? '');
        $respStatus = $data['payment_result']['response_status'] ?? null;

        $order = MusicOrder::find($cartId);
        if (!$order) {
            Log::error("[MusicPayTabsCallback] Order not found for cart_id: {$cartId}");
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->isCompleted()) {
            return response()->json(['success' => true, 'message' => 'Order already completed']);
        }

        if ($respStatus !== 'A') {
            Log::info("[MusicPayTabsCallback] Payment not authorized for order #{$order->id}: status {$respStatus}");
            $order->update([
                'payment_status' => MusicOrder::STATUS_FAILED,
                'payment_details' => array_merge($order->payment_details ?: [], [
                    'callback_response' => $data['payment_result'] ?? [],
                    'failed_at' => now()->toIso8601String(),
                ]),
            ]);
            return response()->json(['success' => false, 'message' => 'Payment not authorized']);
        }

        // Amount & Currency Verification
        $cartAmount = (float) ($data['cart_amount'] ?? 0);
        $orderTotal = (float) $order->amount;
        if (abs($cartAmount - $orderTotal) > 0.05) {
            Log::error("[MusicPayTabsCallback] Amount mismatch for order #{$order->id}. Expected: {$orderTotal}, Got: {$cartAmount}");
            return response()->json(['success' => false, 'message' => 'Amount mismatch'], 422);
        }

        $paidCurrency = strtoupper((string) ($data['cart_currency'] ?? ''));
        $expectedCurrency = strtoupper($order->currency ?: 'SAR');
        if (!empty($paidCurrency) && $paidCurrency !== $expectedCurrency) {
            Log::error("[MusicPayTabsCallback] Currency mismatch for order #{$order->id}.");
            return response()->json(['success' => false, 'message' => 'Currency mismatch'], 422);
        }

        // Fulfill Order
        $sanitizedDetails = [
            'gateway' => 'paytabs',
            'tran_ref' => $tranRef,
            'response_status' => $respStatus,
            'response_code' => $data['payment_result']['response_code'] ?? null,
            'response_message' => $data['payment_result']['response_message'] ?? null,
            'payment_method' => $data['payment_info']['payment_method'] ?? 'card',
            'card_scheme' => $data['payment_info']['card_scheme'] ?? null,
            'card_type' => $data['payment_info']['card_type'] ?? null,
            'verified_at' => now()->toIso8601String(),
        ];

        $fulfillment = $this->fulfillmentService->fulfillOrder($order, MusicOrder::GATEWAY_PAYTABS, $tranRef, $sanitizedDetails);

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'entitlement_id' => $fulfillment['entitlement']->id,
        ]);
    }

    /**
     * GET /api/v1/music/orders
     * Authenticated buyer order history.
     */
    public function index(Request $request): JsonResponse
    {
        $buyer = $this->resolveBuyer($request);
        if (!$buyer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $orders = MusicOrder::with(['track', 'offer', 'entitlement'])
            ->where('buyer_id', $buyer->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders->getCollection()->map(fn($o) => $this->formatOrderResponse($o)),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/music/orders/{id}
     * Authenticated buyer order detail.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $buyer = $this->resolveBuyer($request);
        if (!$buyer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $order = MusicOrder::with(['track', 'offer', 'entitlement'])
            ->where('buyer_id', $buyer->id)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => $this->formatOrderResponse($order),
        ]);
    }

    /**
     * Format clean order DTO.
     */
    protected function formatOrderResponse(MusicOrder $order): array
    {
        return [
            'id' => $order->id,
            'invoice' => $order->invoice,
            'track_id' => $order->track_id,
            'track_title' => optional($order->track)->title_en,
            'track_title_ar' => optional($order->track)->title_ar,
            'artist_name' => optional($order->track)->artist_name,
            'offer_type' => $order->offer_type,
            'subtotal' => (float) $order->subtotal,
            'vat_rate' => (float) $order->vat_rate,
            'vat_amount' => (float) $order->vat_amount,
            'amount' => (float) $order->amount,
            'currency' => $order->currency ?: 'SAR',
            'payment_gateway' => $order->payment_gateway,
            'payment_status' => $order->payment_status,
            'paid_at' => optional($order->paid_at)->toIso8601String(),
            'refunded_at' => optional($order->refunded_at)->toIso8601String(),
            'refund_amount' => (float) ($order->refund_amount ?? 0),
            'has_active_entitlement' => $order->entitlement ? $order->entitlement->isValid() : false,
            'entitlement_expires_at' => optional(optional($order->entitlement)->expires_at)->toIso8601String(),
            'is_perpetual' => $order->entitlement ? $order->entitlement->isPerpetual() : false,
            'created_at' => optional($order->created_at)->toIso8601String(),
        ];
    }
}
