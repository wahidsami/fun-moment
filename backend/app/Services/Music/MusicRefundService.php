<?php

namespace App\Services\Music;

use App\Admin;
use App\AdminAuditLog;
use App\MusicEntitlement;
use App\MusicOrder;
use App\MusicRefundAttempt;
use App\MusicSellerLedgerEntry;
use App\Services\Payment\PayTabsPaymentService;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Wallet\Services\WalletService;
use RuntimeException;

class MusicRefundException extends RuntimeException
{
    protected string $errorCode;

    public function __construct(string $errorCode, string $message, int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}

class MusicRefundService
{
    protected MusicFinancialCalculationService $calcService;
    protected WalletService $walletService;
    protected PayTabsPaymentService $payTabsService;

    public function __construct(
        MusicFinancialCalculationService $calcService,
        WalletService $walletService,
        PayTabsPaymentService $payTabsService
    ) {
        $this->calcService = $calcService;
        $this->walletService = $walletService;
        $this->payTabsService = $payTabsService;
    }

    /**
     * Process an authoritative refund for a completed music order.
     * Enforces two-phase saga durability, reservation checks, and destination routing.
     *
     * @param MusicOrder|int $orderOrId Order instance or primary key
     * @param float|null $requestedAmount Refund amount (null for full remaining)
     * @param string|null $destination 'customer_wallet' or 'original_payment_method'
     * @param string $reason Audit reason
     * @param Admin|null $authorizingAdmin Admin executing the action
     * @param array $options ['buyer_agreed_wallet_credit' => bool, 'idempotency_key' => string]
     * @return array Complete refund execution report
     * @throws MusicRefundException
     */
    public function processRefund(
        $orderOrId,
        ?float $requestedAmount,
        ?string $destination,
        string $reason,
        ?Admin $authorizingAdmin = null,
        array $options = []
    ): array {
        $orderId = ($orderOrId instanceof MusicOrder) ? $orderOrId->id : (int) $orderOrId;
        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            throw new MusicRefundException('INVALID_REASON', 'A valid audit reason of at least 3 characters is required.');
        }

        $idempotencyKey = $options['idempotency_key'] ?? null;

        // --- 1. Lock Order & Check Unresolved Attempts and Reservations ---
        /** @var MusicOrder|null $order */
        $order = MusicOrder::where('id', $orderId)->lockForUpdate()->first();
        if (!$order) {
            throw new MusicRefundException('ORDER_NOT_FOUND', 'Order not found.', 404);
        }

        // Verify eligibility
        if (!$order->isCompleted() && !$order->isRefunded()) {
            throw new MusicRefundException('ORDER_NOT_REFUNDABLE', 'Only completed paid orders can be refunded.');
        }

        // Check for existing idempotency replay
        if (!empty($idempotencyKey)) {
            $existingAttempt = MusicRefundAttempt::where('order_id', $order->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingAttempt) {
                if ($existingAttempt->isCompleted()) {
                    return [
                        'success' => true,
                        'idempotent_replay' => true,
                        'status' => 'completed',
                        'message_en' => 'Duplicate refund request detected. Existing refund is already completed.',
                        'message_ar' => 'تم استلام طلب مكرر. تم إكمال الاسترداد مسبقاً.',
                        'attempt' => $existingAttempt,
                        'order' => [
                            'id' => $order->id,
                            'invoice' => $order->invoice,
                            'payment_status' => $order->payment_status,
                            'refund_amount' => (float) $order->refund_amount,
                            'remaining_refundable' => $order->getRemainingRefundableAmount(),
                            'is_fully_refunded' => $order->isFullyRefunded(),
                        ],
                    ];
                }

                if ($existingAttempt->isPendingVerification()) {
                    throw new MusicRefundException(
                        'GATEWAY_REFUND_AMBIGUOUS',
                        "A previous refund attempt (#{$existingAttempt->attempt_uuid}) with this idempotency key is currently pending verification.",
                        502
                    );
                }
            }
        }

        // Unresolved Attempt Guard: block new requests while an attempt is pending verification
        if ($order->hasUnresolvedRefundAttempt()) {
            $pendingReservation = $order->getPendingRefundReservationAmount();
            throw new MusicRefundException(
                'REFUND_PENDING_RECONCILIATION',
                sprintf(
                    "An unresolved refund attempt of %s SAR is currently pending provider verification. Reconcile this attempt before submitting new refunds.",
                    number_format($pendingReservation, 2)
                )
            );
        }

        // Effective Remaining Refundable Calculation (taking active reservations into account)
        $effectiveRemaining = $order->getEffectiveRemainingRefundableAmount();
        if ($effectiveRemaining <= 0.00) {
            throw new MusicRefundException('ALREADY_FULLY_REFUNDED', 'This order has already been fully refunded.');
        }

        // Validate requested amount
        if ($requestedAmount !== null) {
            $reqHalalas = MusicFinancialCalculationService::toHalalas($requestedAmount);
            $remHalalas = MusicFinancialCalculationService::toHalalas($effectiveRemaining);

            if ($reqHalalas <= 0) {
                throw new MusicRefundException('INVALID_REFUND_AMOUNT', 'Refund amount must be greater than zero.');
            }

            if ($reqHalalas > $remHalalas) {
                throw new MusicRefundException(
                    'REFUND_AMOUNT_EXCEEDED',
                    sprintf(
                        "Requested refund amount (%s SAR) exceeds remaining refundable balance (%s SAR).",
                        number_format(MusicFinancialCalculationService::fromHalalas($reqHalalas), 2),
                        number_format(MusicFinancialCalculationService::fromHalalas($remHalalas), 2)
                    )
                );
            }
        }

        // Determine and validate authorized refund destination
        if ($order->payment_gateway === MusicOrder::GATEWAY_WALLET) {
            if ($destination !== null && $destination !== MusicOrder::REFUND_DESTINATION_WALLET) {
                throw new MusicRefundException(
                    'INVALID_REFUND_DESTINATION',
                    'Orders funded via customer wallet must be refunded to customer_wallet.'
                );
            }
            $targetDestination = MusicOrder::REFUND_DESTINATION_WALLET;
        } elseif ($order->payment_gateway === MusicOrder::GATEWAY_PAYTABS) {
            if ($destination === MusicOrder::REFUND_DESTINATION_WALLET) {
                $buyerAgreed = !empty($options['buyer_agreed_wallet_credit']);
                if (!$buyerAgreed) {
                    throw new MusicRefundException(
                        'WALLET_CREDIT_CONSENT_REQUIRED',
                        'Crediting a PayTabs card-funded order to customer wallet requires verified customer consent under platform terms.'
                    );
                }
                $targetDestination = MusicOrder::REFUND_DESTINATION_WALLET;
            } else {
                $targetDestination = MusicOrder::REFUND_DESTINATION_PAYMENT_METHOD;
            }
        } else {
            $targetDestination = $destination ?: MusicOrder::REFUND_DESTINATION_WALLET;
        }

        // Authoritative Integer Halala Calculation
        $refundCalc = $this->calcService->calculateRefund($order, $requestedAmount, $reason);
        $actualRefundAmount = $refundCalc['refund_amount'];

        $attemptUuid = 'MRA-' . date('Ymd') . '-' . strtoupper(Str::random(8));
        $cartId = 'REF-' . $order->invoice . '-' . substr($attemptUuid, -6);

        // --- 2. WALLET DESTINATION REFUND (ATOMIC, IN-HOUSE) ---
        if ($targetDestination === MusicOrder::REFUND_DESTINATION_WALLET) {
            return DB::transaction(function () use (
                $order,
                $actualRefundAmount,
                $targetDestination,
                $refundCalc,
                $reason,
                $authorizingAdmin,
                $options,
                $idempotencyKey,
                $attemptUuid,
                $cartId
            ) {
                $buyer = User::find($order->buyer_id);
                if (!$buyer) {
                    throw new MusicRefundException('BUYER_NOT_FOUND', 'Buyer account not found for wallet refund.');
                }

                $attempt = MusicRefundAttempt::create([
                    'attempt_uuid' => $attemptUuid,
                    'order_id' => $order->id,
                    'buyer_id' => $order->buyer_id,
                    'admin_id' => optional($authorizingAdmin)->id,
                    'destination' => MusicOrder::REFUND_DESTINATION_WALLET,
                    'payment_gateway' => $order->payment_gateway,
                    'amount' => $actualRefundAmount,
                    'currency' => $order->currency ?: 'SAR',
                    'subtotal_amount' => $refundCalc['refund_subtotal'],
                    'vat_amount' => $refundCalc['refund_vat'],
                    'commission_amount' => $refundCalc['reversed_commission'],
                    'seller_earning_amount' => $refundCalc['reversed_seller_earning'],
                    'is_full_refund' => $refundCalc['is_full_refund'],
                    'status' => MusicRefundAttempt::STATUS_COMPLETED,
                    'idempotency_key' => $idempotencyKey,
                    'cart_id' => $cartId,
                    'reason' => $reason,
                    'completed_at' => now(),
                ]);

                $walletHistory = $this->walletService->credit(
                    $buyer,
                    $actualRefundAmount,
                    'wallet_refund',
                    'music_order_refund',
                    (string) $order->id,
                    "Refund for music order #{$order->invoice}: {$reason}",
                    "استرداد مبلغ طلب الموسيقى #{$order->invoice}: {$reason}",
                    [
                        'order_id' => $order->id,
                        'invoice' => $order->invoice,
                        'destination' => MusicOrder::REFUND_DESTINATION_WALLET,
                        'original_gateway' => $order->payment_gateway,
                        'is_full_refund' => $refundCalc['is_full_refund'],
                        'admin_id' => optional($authorizingAdmin)->id,
                        'buyer_agreed_wallet_credit' => $order->payment_gateway !== MusicOrder::GATEWAY_WALLET,
                    ]
                );

                return $this->finalizeConfirmedRefund(
                    $order,
                    $attempt,
                    null,
                    $walletHistory->transaction_id,
                    $authorizingAdmin,
                    $reason,
                    $options
                );
            });
        }

        // --- 3. PAYTABS DIRECT CARD REFUND (TWO-PHASE DURABLE SAGA) ---
        if (empty($order->transaction_id)) {
            throw new MusicRefundException(
                'PAYMENT_REFERENCE_MISSING',
                'PayTabs transaction reference is missing on this order. Cannot execute card refund.'
            );
        }

        // Phase 1: Persist durable attempt BEFORE network call
        /** @var MusicRefundAttempt $attempt */
        $attempt = MusicRefundAttempt::create([
            'attempt_uuid' => $attemptUuid,
            'order_id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'admin_id' => optional($authorizingAdmin)->id,
            'destination' => MusicOrder::REFUND_DESTINATION_PAYMENT_METHOD,
            'payment_gateway' => $order->payment_gateway,
            'amount' => $actualRefundAmount,
            'currency' => $order->currency ?: 'SAR',
            'subtotal_amount' => $refundCalc['refund_subtotal'],
            'vat_amount' => $refundCalc['refund_vat'],
            'commission_amount' => $refundCalc['reversed_commission'],
            'seller_earning_amount' => $refundCalc['reversed_seller_earning'],
            'is_full_refund' => $refundCalc['is_full_refund'],
            'status' => MusicRefundAttempt::STATUS_PENDING_VERIFICATION,
            'idempotency_key' => $idempotencyKey,
            'original_transaction_ref' => $order->transaction_id,
            'cart_id' => $cartId,
            'reason' => $reason,
            'raw_request' => [
                'order_id' => $order->id,
                'tran_ref' => $order->transaction_id,
                'amount' => $actualRefundAmount,
                'cart_id' => $cartId,
            ],
        ]);

        // Phase 2: Invoke Payment Gateway
        try {
            $ptResult = $this->payTabsService->refundPayment(
                $order->transaction_id,
                $actualRefundAmount,
                $cartId,
                $reason,
                $order->currency ?: 'SAR'
            );
        } catch (\Throwable $e) {
            Log::error("[MusicRefundService] Exception during PayTabs refund call: " . $e->getMessage(), [
                'attempt_uuid' => $attempt->attempt_uuid,
                'order_id' => $order->id,
            ]);

            $attempt->update([
                'status' => MusicRefundAttempt::STATUS_PENDING_VERIFICATION,
                'error_code' => 'GATEWAY_REFUND_AMBIGUOUS',
                'error_message' => 'Network exception: ' . $e->getMessage(),
                'last_reconciled_at' => now(),
            ]);

            throw new MusicRefundException(
                'GATEWAY_REFUND_AMBIGUOUS',
                'PayTabs refund outcome is inconclusive due to network exception. Attempt remains pending verification.',
                502
            );
        }

        // Case A: Confirmed Gateway Success
        if (!empty($ptResult['success'])) {
            $refundTranRef = $ptResult['refund_tran_ref'] ?? ('REF-PT-' . strtoupper(Str::random(10)));
            $attempt->update([
                'provider_refund_ref' => $refundTranRef,
                'raw_response' => $ptResult['raw_response'] ?? null,
            ]);

            return DB::transaction(function () use (
                $order,
                $attempt,
                $refundTranRef,
                $authorizingAdmin,
                $reason,
                $options
            ) {
                return $this->finalizeConfirmedRefund(
                    $order,
                    $attempt,
                    $refundTranRef,
                    null,
                    $authorizingAdmin,
                    $reason,
                    $options
                );
            });
        }

        // Case B: Confirmed Definitive Decline
        if (($ptResult['status'] ?? '') === 'declined') {
            $attempt->update([
                'status' => MusicRefundAttempt::STATUS_DECLINED,
                'error_code' => 'GATEWAY_REFUND_DECLINED',
                'error_message' => $ptResult['message'] ?? 'Declined by payment provider.',
                'raw_response' => $ptResult['raw_response'] ?? null,
                'failed_at' => now(),
            ]);

            throw new MusicRefundException(
                'GATEWAY_REFUND_DECLINED',
                'PayTabs refund declined by payment provider: ' . ($ptResult['message'] ?? 'Declined')
            );
        }

        // Case C: Ambiguous / Timeout / Gateway Error
        $attempt->update([
            'status' => MusicRefundAttempt::STATUS_PENDING_VERIFICATION,
            'error_code' => 'GATEWAY_REFUND_AMBIGUOUS',
            'error_message' => $ptResult['message'] ?? 'Ambiguous gateway response',
            'raw_response' => $ptResult['raw_response'] ?? null,
            'last_reconciled_at' => now(),
        ]);

        throw new MusicRefundException(
            'GATEWAY_REFUND_AMBIGUOUS',
            'PayTabs refund outcome is inconclusive. Transaction was not confirmed. Please reconcile before retrying.',
            502
        );
    }

    /**
     * Reconcile an ambiguous refund attempt using PayTabs status inquiry or authorized manual resolution.
     *
     * @param int $attemptId Primary key of MusicRefundAttempt
     * @param array $options ['provider_ref' => string, 'manual_status' => string, 'notes' => string, 'admin' => Admin]
     * @return array Reconciled outcome report
     * @throws MusicRefundException
     */
    public function reconcileRefundAttempt(int $attemptId, array $options = []): array
    {
        return DB::transaction(function () use ($attemptId, $options) {
            /** @var MusicRefundAttempt|null $attempt */
            $attempt = MusicRefundAttempt::where('id', $attemptId)->lockForUpdate()->first();
            if (!$attempt) {
                throw new MusicRefundException('ATTEMPT_NOT_FOUND', 'Refund attempt record not found.', 404);
            }

            // Idempotent completion check
            if ($attempt->isCompleted()) {
                return [
                    'success' => true,
                    'idempotent_replay' => true,
                    'status' => 'completed',
                    'message_en' => 'Refund attempt is already confirmed and completed.',
                    'message_ar' => 'تم تأكيد واكتمال عملية الاسترداد مسبقاً.',
                    'attempt' => $attempt->fresh(),
                ];
            }

            if ($attempt->isFailed() || $attempt->isDeclined()) {
                return [
                    'success' => false,
                    'idempotent_replay' => true,
                    'status' => $attempt->status,
                    'message_en' => 'Refund attempt was already verified as failed/declined.',
                    'message_ar' => 'تم التحقق مسبقاً من فشل عملية الاسترداد.',
                    'attempt' => $attempt->fresh(),
                ];
            }

            if (!$attempt->isPendingVerification()) {
                throw new MusicRefundException(
                    'INVALID_ATTEMPT_STATUS',
                    "Only attempts in pending_verification status can be reconciled. Current status: {$attempt->status}"
                );
            }

            /** @var MusicOrder|null $order */
            $order = MusicOrder::where('id', $attempt->order_id)->lockForUpdate()->first();
            if (!$order) {
                throw new MusicRefundException('ORDER_NOT_FOUND', 'Associated order not found.', 404);
            }

            $admin = $options['admin'] ?? auth('admin')->user();
            $manualStatus = $options['manual_status'] ?? null;
            $providerRef = $options['provider_ref'] ?? null;
            $notes = $options['notes'] ?? null;

            $isConfirmedSuccess = false;
            $isConfirmedFailure = false;
            $queryRaw = null;

            if ($manualStatus === 'completed') {
                $isConfirmedSuccess = true;
            } elseif ($manualStatus === 'failed') {
                $isConfirmedFailure = true;
            } else {
                // Automated query to PayTabs
                $searchRef = $providerRef ?: $attempt->provider_refund_ref ?: $attempt->original_transaction_ref;
                if (!empty($searchRef)) {
                    $queryRes = $this->payTabsService->queryTransaction($searchRef);
                    $queryRaw = $queryRes['data'] ?? null;

                    if ($queryRes['success'] && !empty($queryRaw)) {
                        $respStatus = $queryRaw['payment_result']['response_status'] ?? null;
                        $tranType = strtolower((string) ($queryRaw['tran_type'] ?? ''));

                        if ($respStatus === 'A') {
                            $isConfirmedSuccess = true;
                            $providerRef = $queryRaw['tran_ref'] ?? $providerRef;
                        } elseif (in_array($respStatus, ['D', 'E'], true)) {
                            $isConfirmedFailure = true;
                        }
                    }
                }
            }

            // Confirmed Success Branch
            if ($isConfirmedSuccess) {
                return $this->finalizeConfirmedRefund(
                    $order,
                    $attempt,
                    $providerRef ?: $attempt->provider_refund_ref ?: ('REF-REC-' . strtoupper(Str::random(8))),
                    null,
                    $admin,
                    $notes ?: 'Reconciliation confirmed provider refund success',
                    $options,
                    $queryRaw
                );
            }

            // Confirmed Failure Branch (Release reservation so merchant can retry safely)
            if ($isConfirmedFailure) {
                $attempt->update([
                    'status' => MusicRefundAttempt::STATUS_FAILED,
                    'error_code' => 'GATEWAY_REFUND_CONFIRMED_FAILED',
                    'error_message' => $notes ?: 'Reconciliation confirmed refund was not processed by provider.',
                    'reconciliation_notes' => $notes,
                    'last_reconciled_at' => now(),
                    'failed_at' => now(),
                    'raw_response' => $queryRaw,
                ]);

                AdminAuditLog::record([
                    'action' => 'music_refund_reconcile_failed',
                    'resource_type' => 'music_refund_attempt',
                    'resource_id' => (string) $attempt->id,
                    'details_en' => sprintf(
                        "Reconciliation confirmed refund attempt #%s failed. Reservation of %s SAR released.",
                        $attempt->attempt_uuid,
                        $attempt->amount
                    ),
                    'details_ar' => sprintf(
                        "أكدت المطابقة فشل محاولة الاسترداد #%s. تم تحرير حجز %s ريال.",
                        $attempt->attempt_uuid,
                        $attempt->amount
                    ),
                    'admin_id' => optional($admin)->id,
                ]);

                return [
                    'success' => true,
                    'idempotent_replay' => false,
                    'status' => 'failed',
                    'message_en' => 'Reconciliation confirmed refund failed with provider. Reservation has been released.',
                    'message_ar' => 'أكدت المطابقة فشل عملية الاسترداد لدى مزود الدفع. تم تحرير المبلغ المحجوز.',
                    'attempt' => $attempt->fresh(),
                    'order' => [
                        'id' => $order->id,
                        'invoice' => $order->invoice,
                        'payment_status' => $order->payment_status,
                        'refund_amount' => (float) $order->refund_amount,
                        'remaining_refundable' => $order->getRemainingRefundableAmount(),
                        'effective_remaining_refundable' => $order->getEffectiveRemainingRefundableAmount(),
                    ],
                ];
            }

            // Inconclusive Branch
            $attempt->increment('reconciliation_attempts');
            $attempt->update([
                'last_reconciled_at' => now(),
                'reconciliation_notes' => $notes ?: 'PayTabs query returned inconclusive.',
                'raw_response' => $queryRaw,
            ]);

            throw new MusicRefundException(
                'GATEWAY_REFUND_AMBIGUOUS',
                'Reconciliation could not verify refund status with PayTabs. The attempt remains pending verification.',
                502
            );
        });
    }

    /**
     * Finalize an authoritative, confirmed refund and post immutable financial ledger reversals.
     */
    protected function finalizeConfirmedRefund(
        MusicOrder $order,
        MusicRefundAttempt $attempt,
        ?string $gatewayRefundRef,
        ?string $walletTxnId,
        ?Admin $authorizingAdmin,
        string $reason,
        array $options = [],
        ?array $rawResponse = null
    ): array {
        $actualRefundAmount = (float) $attempt->amount;
        $targetDestination = $attempt->destination;

        // 1. Seller Ledger Reversal with Over-Reversal Prevention
        $effectiveReversedSellerEarning = 0.00;
        if (!empty($order->seller_id)) {
            $earnedHalalas = (int) MusicSellerLedgerEntry::where('order_id', $order->id)
                ->where('seller_id', $order->seller_id)
                ->where('entry_type', MusicSellerLedgerEntry::TYPE_EARNING)
                ->sum(DB::raw('round(net_earning * 100)'));

            $alreadyReversedHalalas = (int) MusicSellerLedgerEntry::where('order_id', $order->id)
                ->where('seller_id', $order->seller_id)
                ->where('entry_type', MusicSellerLedgerEntry::TYPE_REFUND_REVERSAL)
                ->sum(DB::raw('round(abs(net_earning) * 100)'));

            $remainingSellerHalalas = max(0, $earnedHalalas - $alreadyReversedHalalas);
            $targetReversalHalalas = MusicFinancialCalculationService::toHalalas($attempt->seller_earning_amount);
            $clampedReversalHalalas = min($targetReversalHalalas, $remainingSellerHalalas);
            $effectiveReversedSellerEarning = MusicFinancialCalculationService::fromHalalas($clampedReversalHalalas);

            MusicSellerLedgerEntry::create([
                'seller_id' => $order->seller_id,
                'order_id' => $order->id,
                'track_id' => $order->track_id,
                'entry_type' => MusicSellerLedgerEntry::TYPE_REFUND_REVERSAL,
                'gross_amount' => -$attempt->subtotal_amount,
                'commission_deducted' => -$attempt->commission_amount,
                'vat_deducted' => 0.00,
                'net_earning' => -$effectiveReversedSellerEarning,
                'currency' => $order->currency ?: 'SAR',
                'status' => MusicSellerLedgerEntry::STATUS_REVERSED,
                'metadata' => [
                    'invoice' => $order->invoice,
                    'refund_attempt_uuid' => $attempt->attempt_uuid,
                    'refund_reason' => $reason,
                    'refund_amount' => $actualRefundAmount,
                    'refund_destination' => $targetDestination,
                    'is_full_refund' => $attempt->is_full_refund,
                ],
            ]);
        }

        // 2. Update Order Totals
        if ($targetDestination === MusicOrder::REFUND_DESTINATION_WALLET) {
            $order->refund_amount_wallet = round((float) $order->refund_amount_wallet + $actualRefundAmount, 2);
        } else {
            $order->refund_amount_gateway = round((float) $order->refund_amount_gateway + $actualRefundAmount, 2);
        }

        $newRefundTotal = round(((float) $order->refund_amount) + $actualRefundAmount, 2);
        $order->refund_amount = $newRefundTotal;
        $order->refund_destination = $targetDestination;
        $order->refund_id = $attempt->attempt_uuid;
        $order->refund_reason = $reason;
        $order->refunded_at = now();

        $isFullyRefunded = ($order->getRemainingRefundableAmount() <= 0.00);
        if ($isFullyRefunded) {
            $order->payment_status = MusicOrder::STATUS_REFUNDED;
        }

        // 3. Mark Attempt Completed
        $attempt->update([
            'status' => MusicRefundAttempt::STATUS_COMPLETED,
            'provider_refund_ref' => $gatewayRefundRef ?: $attempt->provider_refund_ref,
            'raw_response' => $rawResponse ?: $attempt->raw_response,
            'completed_at' => now(),
        ]);

        $refundEntry = [
            'order_id' => $order->id,
            'currency' => $order->currency ?: 'SAR',
            'paid_amount' => (float) $order->amount,
            'already_refunded' => round($newRefundTotal - $actualRefundAmount, 2),
            'refund_amount' => $actualRefundAmount,
            'remaining_after_refund' => $order->getRemainingRefundableAmount(),
            'refund_subtotal' => (float) $attempt->subtotal_amount,
            'refund_vat' => (float) $attempt->vat_amount,
            'reversed_commission' => (float) $attempt->commission_amount,
            'reversed_commission_vat' => 0.00,
            'reversed_seller_earning' => (float) $attempt->seller_earning_amount,
            'is_full_refund' => $attempt->is_full_refund,
            'reason' => $reason,
            'timestamp' => now()->toIso8601String(),
            'refund_id' => $attempt->attempt_uuid,
            'destination' => $targetDestination,
            'wallet_transaction_id' => $walletTxnId,
            'gateway_transaction_id' => $gatewayRefundRef,
            'effective_reversed_seller_earning' => $effectiveReversedSellerEarning,
            'admin_id' => optional($authorizingAdmin)->id,
            'buyer_agreed_wallet_credit' => !empty($options['buyer_agreed_wallet_credit']),
            'idempotency_key' => $attempt->idempotency_key,
        ];

        $history = $order->refund_metadata ?: [];
        $history[] = $refundEntry;
        $order->refund_metadata = $history;
        $order->save();

        // 4. Entitlement Revocation (Full refund only; preserved on partial refund)
        $entitlement = MusicEntitlement::where('order_id', $order->id)->first();
        $entitlementRevoked = false;
        if ($isFullyRefunded && $entitlement && $entitlement->is_active) {
            $entitlement->revoke("Order fully refunded: {$reason}");
            $entitlementRevoked = true;
        }

        // 5. Immutable Admin Audit Trail
        AdminAuditLog::record([
            'action' => 'music_order_refund',
            'resource_type' => 'music_order',
            'resource_id' => (string) $order->id,
            'details_en' => sprintf(
                "Confirmed refund of %s SAR via %s on order #%s (%s)",
                $actualRefundAmount,
                $targetDestination,
                $order->invoice,
                $reason
            ),
            'details_ar' => sprintf(
                "تم تأكيد استرداد %s ريال عبر %s للطلب #%s (%s)",
                $actualRefundAmount,
                $targetDestination,
                $order->invoice,
                $reason
            ),
            'admin_id' => optional($authorizingAdmin)->id,
            'old_values' => [
                'payment_status' => $order->getOriginal('payment_status'),
                'refund_amount' => $order->getOriginal('refund_amount'),
            ],
            'new_values' => [
                'payment_status' => $order->payment_status,
                'refund_amount' => $order->refund_amount,
                'refund_destination' => $targetDestination,
                'wallet_transaction_id' => $walletTxnId,
                'gateway_transaction_id' => $gatewayRefundRef,
                'is_fully_refunded' => $isFullyRefunded,
                'entitlement_revoked' => $entitlementRevoked,
            ],
        ]);

        return [
            'success' => true,
            'idempotent_replay' => false,
            'message_en' => 'Refund processed successfully.',
            'message_ar' => 'تمت معالجة الاسترداد بنجاح.',
            'refund' => $refundEntry,
            'order' => [
                'id' => $order->id,
                'invoice' => $order->invoice,
                'payment_status' => $order->payment_status,
                'refund_amount' => (float) $order->refund_amount,
                'refund_amount_wallet' => (float) $order->refund_amount_wallet,
                'refund_amount_gateway' => (float) $order->refund_amount_gateway,
                'remaining_refundable' => $order->getRemainingRefundableAmount(),
                'effective_remaining_refundable' => $order->getEffectiveRemainingRefundableAmount(),
                'is_fully_refunded' => $isFullyRefunded,
                'entitlement_active' => $entitlement ? (bool) $entitlement->fresh()->is_active : false,
            ],
        ];
    }
}
