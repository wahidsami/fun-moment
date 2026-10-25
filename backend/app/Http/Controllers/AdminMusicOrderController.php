<?php

namespace App\Http\Controllers;

use App\AdminAuditLog;
use App\MusicEntitlement;
use App\MusicOrder;
use App\MusicSellerLedgerEntry;
use App\Services\Music\MusicFinancialCalculationService;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Music\MusicRefundService;
use Modules\Wallet\Services\WalletService;

class AdminMusicOrderController extends Controller
{
    protected MusicFinancialCalculationService $calcService;
    protected WalletService $walletService;
    protected MusicRefundService $refundService;

    public function __construct(
        MusicFinancialCalculationService $calcService,
        WalletService $walletService,
        MusicRefundService $refundService
    ) {
        $this->middleware('auth:admin');
        $this->calcService = $calcService;
        $this->walletService = $walletService;
        $this->refundService = $refundService;
    }

    /**
     * Check whether authenticated admin has management privileges.
     */
    protected function canManage(Request $request): bool
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return false;
        }

        if (method_exists($admin, 'hasRole')) {
            if ($admin->hasRole('Super Admin') || $admin->hasRole('super_admin')) {
                return true;
            }
        }

        $roleStr = strtolower(trim((string) ($admin->role ?? '')));
        if (in_array($roleStr, ['super admin', 'super_admin', 'admin'], true)) {
            return true;
        }

        if (method_exists($admin, 'can') && $admin->can('manage_settings')) {
            return true;
        }

        return false;
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'FORBIDDEN',
            'message_en' => 'Unauthorized. Administrative privileges required to manage orders.',
            'message_ar' => 'غير مصرح لك. يلزم صلاحيات المشرف لإدارة الطلبات.',
        ], 403);
    }

    /**
     * GET /admin-home/music-orders-json
     * List music orders with search, filters, and metrics.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->canManage($request)) {
            return $this->forbidden();
        }

        $query = MusicOrder::with(['buyer', 'seller', 'track', 'entitlement']);

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('invoice', 'LIKE', "%{$search}%")
                  ->orWhere('buyer_name', 'LIKE', "%{$search}%")
                  ->orWhere('buyer_email', 'LIKE', "%{$search}%")
                  ->orWhere('seller_name', 'LIKE', "%{$search}%")
                  ->orWhere('transaction_id', 'LIKE', "%{$search}%")
                  ->orWhereHas('track', function ($tq) use ($search) {
                      $tq->where('title_en', 'LIKE', "%{$search}%")
                         ->orWhere('title_ar', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($request->has('payment_status') && !empty($request->input('payment_status'))) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->has('payment_gateway') && !empty($request->input('payment_gateway'))) {
            $query->where('payment_gateway', $request->input('payment_gateway'));
        }

        if ($request->has('offer_type') && !empty($request->input('offer_type'))) {
            $query->where('offer_type', $request->input('offer_type'));
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        // Calculate summary counters
        $totalOrders = MusicOrder::count();
        $completedOrders = MusicOrder::where('payment_status', MusicOrder::STATUS_COMPLETED)->count();
        $refundedOrders = MusicOrder::where('payment_status', MusicOrder::STATUS_REFUNDED)->count();
        $totalVolume = (float) MusicOrder::where('payment_status', MusicOrder::STATUS_COMPLETED)->sum('amount');
        $totalPlatformCommission = (float) MusicOrder::where('payment_status', MusicOrder::STATUS_COMPLETED)->sum('commission_amount');

        return response()->json([
            'success' => true,
            'data' => $orders->getCollection()->map(fn($o) => [
                'id' => $o->id,
                'invoice' => $o->invoice,
                'buyer_name' => $o->buyer_name,
                'buyer_email' => $o->buyer_email,
                'seller_name' => $o->seller_name,
                'track_title' => optional($o->track)->title_en,
                'track_title_ar' => optional($o->track)->title_ar,
                'offer_type' => $o->offer_type,
                'subtotal' => (float) $o->subtotal,
                'vat_amount' => (float) $o->vat_amount,
                'amount' => (float) $o->amount,
                'commission_amount' => (float) $o->commission_amount,
                'seller_earning' => (float) $o->seller_earning,
                'payment_gateway' => $o->payment_gateway,
                'payment_status' => $o->payment_status,
                'transaction_id' => $o->transaction_id,
                'refund_amount' => (float) ($o->refund_amount ?? 0),
                'paid_at' => optional($o->paid_at)->toIso8601String(),
                'refunded_at' => optional($o->refunded_at)->toIso8601String(),
                'created_at' => optional($o->created_at)->toIso8601String(),
            ]),
            'metrics' => [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'refunded_orders' => $refundedOrders,
                'total_volume_sar' => round($totalVolume, 2),
                'total_commission_sar' => round($totalPlatformCommission, 2),
            ],
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin-home/music-orders-json/{id}
     * Inspect single order with full audit breakdown and ledger history.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (!$this->canManage($request)) {
            return $this->forbidden();
        }

        $order = MusicOrder::with(['buyer', 'seller', 'track', 'entitlement', 'sellerLedgerEntries'])->find($id);
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'invoice' => $order->invoice,
                'buyer' => [
                    'id' => $order->buyer_id,
                    'name' => $order->buyer_name,
                    'email' => $order->buyer_email,
                ],
                'seller' => [
                    'id' => $order->seller_id,
                    'name' => $order->seller_name,
                    'email' => $order->seller_email,
                    'vat_registered' => $order->seller ? $order->seller->isVatRegistered() : false,
                    'vat_number' => $order->seller ? $order->seller->vat_registration_number : null,
                ],
                'track' => [
                    'id' => $order->track_id,
                    'title_en' => optional($order->track)->title_en,
                    'title_ar' => optional($order->track)->title_ar,
                    'artist_name' => optional($order->track)->artist_name,
                ],
                'financials' => [
                    'offer_type' => $order->offer_type,
                    'subtotal' => (float) $order->subtotal,
                    'vat_rate' => (float) $order->vat_rate,
                    'vat_amount' => (float) $order->vat_amount,
                    'total_amount' => (float) $order->amount,
                    'currency' => $order->currency ?: 'SAR',
                    'commission_rate' => (float) $order->commission_rate,
                    'commission_amount' => (float) $order->commission_amount,
                    'commission_vat_amount' => (float) $order->commission_vat_amount,
                    'seller_earning' => (float) $order->seller_earning,
                    'tax_treatment' => $order->tax_treatment,
                    'price_display_mode' => $order->price_display_mode,
                    'snapshot' => $order->financial_snapshot,
                ],
                'payment' => [
                    'gateway' => $order->payment_gateway,
                    'status' => $order->payment_status,
                    'transaction_id' => $order->transaction_id,
                    'paid_at' => optional($order->paid_at)->toIso8601String(),
                    'payment_details' => $order->payment_details,
                ],
                'refund' => [
                    'is_refunded' => $order->isRefunded(),
                    'refund_amount' => (float) ($order->refund_amount ?? 0),
                    'refund_amount_wallet' => (float) ($order->refund_amount_wallet ?? 0),
                    'refund_amount_gateway' => (float) ($order->refund_amount_gateway ?? 0),
                    'refund_destination' => $order->refund_destination,
                    'remaining_refundable' => $order->getRemainingRefundableAmount(),
                    'effective_remaining_refundable' => $order->getEffectiveRemainingRefundableAmount(),
                    'pending_reservation' => $order->getPendingRefundReservationAmount(),
                    'has_unresolved_attempt' => $order->hasUnresolvedRefundAttempt(),
                    'refunded_at' => optional($order->refunded_at)->toIso8601String(),
                    'refund_reason' => $order->refund_reason,
                    'refund_history' => $order->refund_metadata,
                    'attempts' => $order->refundAttempts->map(fn($a) => [
                        'id' => $a->id,
                        'attempt_uuid' => $a->attempt_uuid,
                        'destination' => $a->destination,
                        'amount' => (float) $a->amount,
                        'currency' => $a->currency,
                        'status' => $a->status,
                        'provider_refund_ref' => $a->provider_refund_ref,
                        'error_code' => $a->error_code,
                        'error_message' => $a->error_message,
                        'created_at' => optional($a->created_at)->toIso8601String(),
                        'last_reconciled_at' => optional($a->last_reconciled_at)->toIso8601String(),
                        'completed_at' => optional($a->completed_at)->toIso8601String(),
                    ]),
                ],
                'entitlement' => $order->entitlement ? [
                    'id' => $order->entitlement->id,
                    'is_active' => $order->entitlement->is_active,
                    'is_valid' => $order->entitlement->isValid(),
                    'is_perpetual' => $order->entitlement->isPerpetual(),
                    'starts_at' => optional($order->entitlement->starts_at)->toIso8601String(),
                    'expires_at' => optional($order->entitlement->expires_at)->toIso8601String(),
                    'revoked_at' => optional($order->entitlement->revoked_at)->toIso8601String(),
                    'revocation_reason' => $order->entitlement->revocation_reason,
                ] : null,
                'ledger_entries' => $order->sellerLedgerEntries->map(fn($e) => [
                    'id' => $e->id,
                    'entry_type' => $e->entry_type,
                    'gross_amount' => (float) $e->gross_amount,
                    'commission_deducted' => (float) $e->commission_deducted,
                    'vat_deducted' => (float) $e->vat_deducted,
                    'net_earning' => (float) $e->net_earning,
                    'status' => $e->status,
                    'created_at' => optional($e->created_at)->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * POST /admin-home/music-orders-json/{id}/refund
     * Execute an auditable refund, reverse seller earnings, and manage entitlement lifecycle.
     */
    public function refund(Request $request, int $id): JsonResponse
    {
        if (!$this->canManage($request)) {
            return $this->forbidden();
        }

        $request->validate([
            'reason' => 'required|string|min:3|max:500',
            'refund_amount' => 'nullable|numeric|min:0.01',
            'destination' => 'nullable|string|in:customer_wallet,original_payment_method',
            'buyer_agreed_wallet_credit' => 'nullable|boolean',
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        $reason = trim($request->input('reason'));
        $requestedAmount = $request->has('refund_amount') ? (float) $request->input('refund_amount') : null;
        $destination = $request->input('destination');
        $options = [
            'buyer_agreed_wallet_credit' => $request->boolean('buyer_agreed_wallet_credit'),
            'idempotency_key' => $request->input('idempotency_key') ?: $request->header('X-Idempotency-Key'),
        ];

        try {
            $result = $this->refundService->processRefund(
                $id,
                $requestedAmount,
                $destination,
                $reason,
                auth('admin')->user(),
                $options
            );

            return response()->json($result, 200);
        } catch (\App\Services\Music\MusicRefundException $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() <= 599) ? $e->getCode() : 422;
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message_en' => $e->getMessage(),
                'message_ar' => $this->translateRefundError($e->getErrorCode()),
            ], $statusCode);
        }
    }

    /**
     * POST /admin-home/music-orders-json/refund-attempts/{attemptId}/reconcile
     * Reconcile an ambiguous refund attempt against PayTabs status.
     */
    public function reconcileAttempt(Request $request, int $attemptId): JsonResponse
    {
        if (!$this->canManage($request)) {
            return $this->forbidden();
        }

        $request->validate([
            'provider_ref' => 'nullable|string|max:100',
            'manual_status' => 'nullable|string|in:completed,failed',
            'notes' => 'nullable|string|max:500',
        ]);

        $options = [
            'provider_ref' => $request->input('provider_ref'),
            'manual_status' => $request->input('manual_status'),
            'notes' => $request->input('notes'),
            'admin' => auth('admin')->user(),
        ];

        try {
            $result = $this->refundService->reconcileRefundAttempt($attemptId, $options);
            return response()->json($result, 200);
        } catch (\App\Services\Music\MusicRefundException $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() <= 599) ? $e->getCode() : 422;
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message_en' => $e->getMessage(),
                'message_ar' => $this->translateRefundError($e->getErrorCode()),
            ], $statusCode);
        }
    }

    protected function translateRefundError(string $code): string
    {
        $map = [
            'ORDER_NOT_FOUND' => 'الطلب غير موجود.',
            'ORDER_NOT_REFUNDABLE' => 'يمكن فقط استرداد الطلبات المكتملة والمدفوعة.',
            'ALREADY_FULLY_REFUNDED' => 'تم استرداد هذا الطلب بالكامل مسبقاً.',
            'INVALID_REFUND_AMOUNT' => 'مبلغ الاسترداد يجب أن يكون أكبر من الصفر.',
            'REFUND_AMOUNT_EXCEEDED' => 'المبلغ المطلوب استرداده يتجاوز الرصيد القابل للاسترداد.',
            'INVALID_REFUND_DESTINATION' => 'الطلبات المدفوعة بالمحفظة يجب استردادها إلى المحفظة.',
            'WALLET_CREDIT_CONSENT_REQUIRED' => 'استرداد مبالغ بطاقات الدفع إلى المحفظة يتطلب موافقة العميل.',
            'PAYMENT_REFERENCE_MISSING' => 'الرقم المرجعي للدفع مفقود.',
            'GATEWAY_REFUND_DECLINED' => 'تم رفض عملية الاسترداد من قبل بوابة الدفع.',
            'GATEWAY_REFUND_AMBIGUOUS' => 'نتيجة عملية الاسترداد غير مؤكدة من مزود الدفع.',
            'REFUND_PENDING_RECONCILIATION' => 'هناك محاولة استرداد سابقة قيد التحقق. يرجى مطابقتها قبل تقديم طلب جديد.',
            'ATTEMPT_NOT_FOUND' => 'سجل محاولة الاسترداد غير موجود.',
            'INVALID_ATTEMPT_STATUS' => 'حالة محاولة الاسترداد لا تسمح بإجراء المطابقة.',
        ];
        return $map[$code] ?? 'حدث خطأ أثناء معالجة الاسترداد.';
    }
}
