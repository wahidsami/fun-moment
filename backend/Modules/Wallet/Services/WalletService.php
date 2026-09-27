<?php

namespace Modules\Wallet\Services;

use App\User;
use App\AdminAuditLog;
use App\PayoutRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;

class WalletService
{
    /**
     * Helper to resolve user ID from User, Wallet, or int.
     */
    protected function resolveUserId($user): int
    {
        if ($user instanceof Wallet) {
            return (int) $user->user_id;
        }
        if ($user instanceof User) {
            return (int) $user->id;
        }
        return (int) $user;
    }

    /**
     * Credit funds to a user's wallet.
     *
     * @param User|Wallet|int $user
     * @param float $amount
     * @param string $gateway
     * @param string|null $refType
     * @param string|null $refId
     * @param string|null $descEn
     * @param string|null $descAr
     * @param array $meta
     * @return WalletHistory
     * @throws \Exception
     */
    public function credit(
        $user,
        float $amount,
        string $gateway = 'wallet',
        ?string $refType = null,
        ?string $refId = null,
        ?string $descEn = null,
        ?string $descAr = null,
        array $meta = []
    ): WalletHistory {
        $userId = $this->resolveUserId($user);
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Credit amount must be greater than zero.");
        }

        return DB::transaction(function () use ($userId, $amount, $gateway, $refType, $refId, $descEn, $descAr, $meta) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::getOrCreateForUser($userId);
                // Re-lock
                $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            }

            if ((int) $wallet->status !== 1) {
                throw new \Exception("Wallet is suspended or inactive.");
            }

            $before = (float) $wallet->balance;
            $after = round($before + $amount, 2);

            $wallet->balance = $after;

            // Lifetime tracking
            $userObj = User::find($userId);
            if ($userObj && (int) $userObj->user_type === 0 && in_array($refType, ['order', 'job_hire', 'service_earning', 'order_earning', 'earning'])) {
                $wallet->total_earned = round((float) $wallet->total_earned + $amount, 2);
            }
            $wallet->save();

            $txnId = 'WTX-' . date('Ymd') . '-' . strtoupper(Str::random(10));

            return WalletHistory::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'buyer_id' => $userId,
                'entry_type' => 'credit',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'payment_gateway' => $gateway,
                'payment_status' => 'complete',
                'status' => 1,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'transaction_id' => $txnId,
                'description_en' => $descEn ?? "Credited {$amount} SAR to wallet",
                'description_ar' => $descAr ?? "تم إضافة {$amount} ريال إلى المحفظة",
                'metadata' => $meta,
            ]);
        });
    }

    /**
     * Debit funds from a user's wallet.
     *
     * @param User|Wallet|int $user
     * @param float $amount
     * @param string|null $refType
     * @param string|null $refId
     * @param string|null $descEn
     * @param string|null $descAr
     * @param array $meta
     * @return WalletHistory
     * @throws \Exception
     */
    public function debit(
        $user,
        float $amount,
        ?string $refType = null,
        ?string $refId = null,
        ?string $descEn = null,
        ?string $descAr = null,
        array $meta = []
    ): WalletHistory {
        $userId = $this->resolveUserId($user);
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Debit amount must be greater than zero.");
        }

        return DB::transaction(function () use ($userId, $amount, $refType, $refId, $descEn, $descAr, $meta) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::getOrCreateForUser($userId);
                $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            }

            if ((int) $wallet->status !== 1) {
                throw new \Exception("Wallet is suspended or inactive.");
            }

            $before = (float) $wallet->balance;
            if ($before < $amount) {
                throw new \Exception("Insufficient wallet balance. Available: {$before}, Requested: {$amount}");
            }

            $after = round($before - $amount, 2);
            $wallet->balance = $after;
            $wallet->total_spent = round((float) $wallet->total_spent + $amount, 2);
            $wallet->save();

            $txnId = 'WTX-' . date('Ymd') . '-' . strtoupper(Str::random(10));

            return WalletHistory::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'buyer_id' => $userId,
                'entry_type' => 'debit',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'payment_gateway' => 'wallet',
                'payment_status' => 'complete',
                'status' => 1,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'transaction_id' => $txnId,
                'description_en' => $descEn ?? "Debited {$amount} SAR from wallet",
                'description_ar' => $descAr ?? "تم خصم {$amount} ريال من المحفظة",
                'metadata' => $meta,
            ]);
        });
    }

    /**
     * Hold funds in pending balance (escrow / payout hold).
     */
    public function hold(
        $user,
        float $amount,
        ?string $refType = null,
        ?string $refId = null,
        ?string $descEn = null,
        ?string $descAr = null,
        array $meta = []
    ): WalletHistory {
        $userId = $this->resolveUserId($user);
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Hold amount must be greater than zero.");
        }

        return DB::transaction(function () use ($userId, $amount, $refType, $refId, $descEn, $descAr, $meta) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet || (float) $wallet->balance < $amount) {
                throw new \Exception("Insufficient spendable balance to place on hold.");
            }

            $before = (float) $wallet->balance;
            $after = round($before - $amount, 2);
            $wallet->balance = $after;
            $wallet->pending_balance = round((float) $wallet->pending_balance + $amount, 2);
            $wallet->save();

            $txnId = 'WTX-HLD-' . date('Ymd') . '-' . strtoupper(Str::random(8));

            return WalletHistory::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'buyer_id' => $userId,
                'entry_type' => 'hold',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'payment_gateway' => 'wallet',
                'payment_status' => 'pending',
                'status' => 1,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'transaction_id' => $txnId,
                'description_en' => $descEn ?? "Held {$amount} SAR in escrow",
                'description_ar' => $descAr ?? "تم حجز مبلغ {$amount} ريال في الضمان المعلق",
                'metadata' => $meta,
            ]);
        });
    }

    /**
     * Release held funds back to spendable balance.
     */
    public function release(
        $user,
        float $amount,
        ?string $refType = null,
        ?string $refId = null,
        ?string $descEn = null,
        ?string $descAr = null,
        array $meta = []
    ): WalletHistory {
        $userId = $this->resolveUserId($user);
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Release amount must be greater than zero.");
        }

        return DB::transaction(function () use ($userId, $amount, $refType, $refId, $descEn, $descAr, $meta) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet || (float) $wallet->pending_balance < $amount) {
                throw new \Exception("Insufficient pending balance to release.");
            }

            $before = (float) $wallet->balance;
            $after = round($before + $amount, 2);
            $wallet->balance = $after;
            $wallet->pending_balance = round((float) $wallet->pending_balance - $amount, 2);
            $wallet->save();

            $txnId = 'WTX-REL-' . date('Ymd') . '-' . strtoupper(Str::random(8));

            return WalletHistory::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'buyer_id' => $userId,
                'entry_type' => 'release',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'payment_gateway' => 'wallet',
                'payment_status' => 'complete',
                'status' => 1,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'transaction_id' => $txnId,
                'description_en' => $descEn ?? "Released {$amount} SAR back to balance",
                'description_ar' => $descAr ?? "تم تحرير {$amount} ريال وإعادتها للرصيد المتاح",
                'metadata' => $meta,
            ]);
        });
    }

    /**
     * Create deposit intent / pending deposit request.
     */
    public function createDepositRequest(
        $user,
        float $amount,
        string $gateway,
        ?string $imagePath = null
    ): WalletHistory {
        $userId = $this->resolveUserId($user);
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Deposit amount must be greater than zero.");
        }

        $wallet = Wallet::getOrCreateForUser($userId);
        $txnId = 'WDEP-' . date('Ymd') . '-' . strtoupper(Str::random(10));

        return WalletHistory::create([
            'wallet_id' => $wallet->id,
            'user_id' => $userId,
            'buyer_id' => $userId,
            'entry_type' => 'credit',
            'amount' => $amount,
            'balance_before' => (float) $wallet->balance,
            'balance_after' => (float) $wallet->balance, // Unchanged until complete
            'payment_gateway' => $gateway,
            'payment_status' => 'pending',
            'status' => 1,
            'reference_type' => 'deposit',
            'transaction_id' => $txnId,
            'manual_payment_image' => $imagePath,
            'description_en' => "Pending deposit of {$amount} SAR via {$gateway}",
            'description_ar' => "طلب إيداع قيد الانتظار بمبلغ {$amount} ريال عبر {$gateway}",
        ]);
    }

    /**
     * Complete a pending deposit.
     */
    public function completeDeposit(int $historyId, ?string $txnRef = null): WalletHistory
    {
        return DB::transaction(function () use ($historyId, $txnRef) {
            $history = WalletHistory::where('id', $historyId)->lockForUpdate()->firstOrFail();
            if ($history->payment_status === 'complete') {
                return $history;
            }

            $wallet = Wallet::where('id', $history->wallet_id)->lockForUpdate()->firstOrFail();
            $before = (float) $wallet->balance;
            $after = round($before + (float) $history->amount, 2);

            $wallet->balance = $after;
            $wallet->save();

            $history->payment_status = 'complete';
            $history->balance_before = $before;
            $history->balance_after = $after;
            if ($txnRef) {
                $history->reference_id = $txnRef;
            }
            $history->save();

            return $history;
        });
    }

    /**
     * Admin controlled adjustment.
     */
    public function adminAdjustment(
        $user,
        float $amount,
        string $direction, // 'credit' or 'debit'
        int $adminId,
        string $reason,
        ?string $descEn = null,
        ?string $descAr = null
    ): WalletHistory {
        $userId = $this->resolveUserId($user);
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Adjustment amount must be positive.");
        }
        if (!in_array($direction, ['credit', 'debit'])) {
            throw new \InvalidArgumentException("Direction must be credit or debit.");
        }

        return DB::transaction(function () use ($userId, $amount, $direction, $adminId, $reason, $descEn, $descAr) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::getOrCreateForUser($userId);
                $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            }

            $before = (float) $wallet->balance;
            if ($direction === 'debit') {
                if ($before < $amount) {
                    throw new \Exception("Cannot debit {$amount} SAR. Current balance is {$before} SAR.");
                }
                $after = round($before - $amount, 2);
            } else {
                $after = round($before + $amount, 2);
            }

            $wallet->balance = $after;
            $wallet->save();

            $txnId = 'WADJ-' . date('Ymd') . '-' . strtoupper(Str::random(8));

            $history = WalletHistory::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'buyer_id' => $userId,
                'entry_type' => 'adjustment',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'payment_gateway' => 'admin_adjustment',
                'payment_status' => 'complete',
                'status' => 1,
                'reference_type' => 'admin_adjustment',
                'reference_id' => (string) $adminId,
                'transaction_id' => $txnId,
                'admin_id' => $adminId,
                'admin_note' => $reason,
                'description_en' => $descEn ?? "Admin {$direction} adjustment of {$amount} SAR: {$reason}",
                'description_ar' => $descAr ?? "تعديل إداري ({$direction}) بمبلغ {$amount} ريال: {$reason}",
            ]);

            $adminObj = \App\Admin::find($adminId);
            AdminAuditLog::record([
                'admin_id' => $adminId,
                'admin_name' => optional($adminObj)->name ?? optional($adminObj)->username ?? 'Admin',
                'admin_email' => optional($adminObj)->email,
                'action' => 'wallet_adjustment',
                'resource_type' => 'wallet',
                'resource_id' => (string) $wallet->id,
                'details_en' => "Adjusted balance by {$direction} {$amount} SAR. Reason: {$reason}",
                'details_ar' => "تعديل رصيد المحفظة بمقدار {$amount} ريال ({$direction}). السبب: {$reason}",
                'old_values' => ['balance_before' => $before, 'direction' => $direction, 'amount' => $amount],
                'new_values' => ['balance_after' => $after],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return $history;
        });
    }

    /**
     * Seller Payout Linkage: Request a payout.
     */
    public function requestPayout($seller, float $amount, string $gateway, ?string $note = null): PayoutRequest
    {
        $sellerId = $this->resolveUserId($seller);
        $user = User::find($sellerId);
        if (!$user || (int) $user->user_type !== 0) {
            throw new \Exception("Only verified providers/sellers can request payouts.");
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Payout amount must be greater than zero.");
        }

        return DB::transaction(function () use ($sellerId, $amount, $gateway, $note) {
            $wallet = Wallet::where('user_id', $sellerId)->lockForUpdate()->first();
            if (!$wallet || (float) $wallet->balance < $amount) {
                throw new \Exception("Insufficient wallet balance for payout. Available: " . ($wallet->balance ?? 0));
            }

            // Hold funds
            $before = (float) $wallet->balance;
            $after = round($before - $amount, 2);
            $wallet->balance = $after;
            $wallet->pending_balance = round((float) $wallet->pending_balance + $amount, 2);
            $wallet->save();

            $payout = PayoutRequest::create([
                'seller_id' => $sellerId,
                'amount' => $amount,
                'payment_gateway' => $gateway,
                'status' => 0, // Pending
                'seller_note' => $note,
            ]);

            $txnId = 'WPO-' . date('Ymd') . '-' . strtoupper(Str::random(8));

            WalletHistory::create([
                'wallet_id' => $wallet->id,
                'user_id' => $sellerId,
                'buyer_id' => $sellerId,
                'entry_type' => 'hold',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'payment_gateway' => $gateway,
                'payment_status' => 'pending',
                'status' => 1,
                'reference_type' => 'payout_request',
                'reference_id' => (string) $payout->id,
                'transaction_id' => $txnId,
                'description_en' => "Payout request #{$payout->id} placed on hold ({$amount} SAR)",
                'description_ar' => "طلب تحويل أرباح رقم #{$payout->id} قيد الحجز المعلق ({$amount} ريال)",
            ]);

            return $payout;
        });
    }

    /**
     * Seller Payout Linkage: Settle a payout (completed or rejected).
     */
    public function settlePayout(int $payoutId, string $status, ?string $adminNote = null, ?int $adminId = null): PayoutRequest
    {
        return DB::transaction(function () use ($payoutId, $status, $adminNote, $adminId) {
            $payout = PayoutRequest::where('id', $payoutId)->lockForUpdate()->firstOrFail();
            $sellerId = $payout->seller_id;
            $amount = (float) $payout->amount;

            $wallet = Wallet::where('user_id', $sellerId)->lockForUpdate()->firstOrFail();

            if ($status === 'completed' || $status === '1') {
                // Funds disbursed: deduct from pending_balance
                $wallet->pending_balance = max(0, round((float) $wallet->pending_balance - $amount, 2));
                $wallet->save();

                $payout->status = 1; // Completed
                $payout->admin_note = $adminNote;
                $payout->save();

                WalletHistory::where('reference_type', 'payout_request')
                    ->where('reference_id', (string) $payout->id)
                    ->where('entry_type', 'hold')
                    ->update(['payment_status' => 'complete']);

                $txnId = 'WPO-DISB-' . date('Ymd') . '-' . strtoupper(Str::random(8));

                WalletHistory::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $sellerId,
                    'buyer_id' => $sellerId,
                    'entry_type' => 'payout',
                    'amount' => $amount,
                    'balance_before' => (float) $wallet->balance,
                    'balance_after' => (float) $wallet->balance,
                    'payment_gateway' => $payout->payment_gateway ?? 'payout',
                    'payment_status' => 'complete',
                    'status' => 1,
                    'reference_type' => 'payout_request',
                    'reference_id' => (string) $payout->id,
                    'transaction_id' => $txnId,
                    'admin_id' => $adminId,
                    'admin_note' => $adminNote,
                    'description_en' => "Payout #{$payout->id} disbursed ({$amount} SAR)",
                    'description_ar' => "تم صرف أرباح التحويل #{$payout->id} بنجاح ({$amount} ريال)",
                ]);
            } elseif ($status === 'rejected' || $status === '2') {
                // Rejected: return funds from pending_balance back to balance
                $wallet->pending_balance = max(0, round((float) $wallet->pending_balance - $amount, 2));
                $before = (float) $wallet->balance;
                $after = round($before + $amount, 2);
                $wallet->balance = $after;
                $wallet->save();

                $payout->status = 2; // Rejected
                $payout->admin_note = $adminNote;
                $payout->save();

                WalletHistory::where('reference_type', 'payout_request')
                    ->where('reference_id', (string) $payout->id)
                    ->where('entry_type', 'hold')
                    ->update(['payment_status' => 'rejected']);

                $txnId = 'WPO-REJ-' . date('Ymd') . '-' . strtoupper(Str::random(8));

                WalletHistory::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $sellerId,
                    'buyer_id' => $sellerId,
                    'entry_type' => 'release',
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'payment_gateway' => $payout->payment_gateway ?? 'payout',
                    'payment_status' => 'complete',
                    'status' => 1,
                    'reference_type' => 'payout_request',
                    'reference_id' => (string) $payout->id,
                    'transaction_id' => $txnId,
                    'admin_id' => $adminId,
                    'admin_note' => $adminNote,
                    'description_en' => "Payout #{$payout->id} rejected. Funds restored to balance.",
                    'description_ar' => "تم رفض طلب التحويل #{$payout->id}. أعيدت الأموال إلى الرصيد المتاح.",
                ]);
            }

            return $payout;
        });
    }
}
