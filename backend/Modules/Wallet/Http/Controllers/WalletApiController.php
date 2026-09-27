<?php

namespace Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;
use Modules\Wallet\Services\WalletService;

class WalletApiController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /* -------------------------------------------------------------
     * BUYER WALLET ENDPOINTS
     * ------------------------------------------------------------- */

    public function buyerBalance(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['msg' => __('Unauthorized')], 401);
        }

        $wallet = Wallet::getOrCreateForUser($user->id);

        return response()->json([
            'status' => 'success',
            'balance' => amount_with_currency_symbol($wallet->balance),
            'raw_balance' => (float) $wallet->balance,
            'pending_balance' => (float) $wallet->pending_balance,
            'currency' => $wallet->currency ?? 'SAR',
            'wallet_status' => (int) $wallet->status === 1 ? 'active' : 'suspended',
        ], 200);
    }

    public function buyerHistory(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['msg' => __('Unauthorized')], 401);
        }

        $wallet = Wallet::getOrCreateForUser($user->id);

        $histories = WalletHistory::where('wallet_id', $wallet->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($h) {
                return [
                    'id' => (int) $h->id,
                    'buyer_id' => (int) $h->buyer_id,
                    'user_id' => (int) $h->user_id,
                    'entry_type' => (string) $h->entry_type,
                    'amount' => (float) $h->amount,
                    'balance_before' => (float) $h->balance_before,
                    'balance_after' => (float) $h->balance_after,
                    'payment_gateway' => (string) ($h->payment_gateway ?? 'wallet'),
                    'payment_status' => (string) ($h->payment_status ?? 'complete'),
                    'status' => (int) $h->status,
                    'transaction_id' => (string) $h->transaction_id,
                    'description_en' => (string) ($h->description_en ?? ''),
                    'description_ar' => (string) ($h->description_ar ?? ''),
                    'created_at' => optional($h->created_at)->toDateTimeString(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'history' => $histories,
        ], 200);
    }

    public function buyerDeposit(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_gateway' => 'required|string|max:50',
            'manual_payment_image' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
        ]);

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['msg' => __('Unauthorized')], 401);
        }

        $imagePath = null;
        if ($request->hasFile('manual_payment_image')) {
            $file = $request->file('manual_payment_image');
            $fileName = 'deposit_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('assets/uploads/manual-payment/'), $fileName);
            $imagePath = 'assets/uploads/manual-payment/' . $fileName;
        }

        $deposit = $this->walletService->createDepositRequest(
            $user,
            (float) $request->amount,
            $request->payment_gateway,
            $imagePath
        );

        return response()->json([
            'status' => 'success',
            'msg' => __('Deposit request initialized'),
            'deposit_info' => [
                'wallet_history_id' => (int) $deposit->id,
                'transaction_id' => (string) $deposit->transaction_id,
                'amount' => (float) $deposit->amount,
                'payment_gateway' => (string) $deposit->payment_gateway,
                'payment_status' => (string) $deposit->payment_status,
            ],
        ], 200);
    }

    public function buyerDepositPaymentStatus(Request $request): JsonResponse
    {
        $request->validate([
            'wallet_history_id' => 'required|integer',
        ]);

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['msg' => __('Unauthorized')], 401);
        }

        try {
            $deposit = WalletHistory::where('id', $request->wallet_history_id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if ($deposit->payment_status === 'complete') {
                $wallet = Wallet::getOrCreateForUser($user->id);
                return response()->json([
                    'status' => 'success',
                    'msg' => __('deposit already completed'),
                    'balance' => amount_with_currency_symbol($wallet->balance),
                ], 200);
            }

            // For automated online gateways, verify and complete
            $completed = $this->walletService->completeDeposit($deposit->id, $request->input('transaction_id'));
            $wallet = Wallet::getOrCreateForUser($user->id);

            return response()->json([
                'status' => 'success',
                'msg' => __('wallet deposit success'),
                'balance' => amount_with_currency_symbol($wallet->balance),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['msg' => $e->getMessage()], 422);
        }
    }

    public function buyerDeduct(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['msg' => __('Unauthorized')], 401);
        }

        try {
            $history = $this->walletService->debit(
                $user,
                (float) $request->amount,
                'service_booking',
                $request->input('order_id'),
                "Payment for order #{$request->input('order_id')}",
                "دفع قيمة الطلب #{$request->input('order_id')}"
            );

            $wallet = Wallet::getOrCreateForUser($user->id);

            return response()->json([
                'status' => 'success',
                'msg' => __('wallet charge success'),
                'balance' => amount_with_currency_symbol($wallet->balance),
                'transaction_id' => $history->transaction_id,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'msg' => $e->getMessage(),
            ], 422);
        }
    }

    /* -------------------------------------------------------------
     * SELLER / PROVIDER WALLET ENDPOINTS
     * ------------------------------------------------------------- */

    public function sellerBalance(Request $request): JsonResponse
    {
        $seller = auth('sanctum')->user();
        if (!$seller || (int) $seller->user_type !== 0) {
            return response()->json(['msg' => __('Unauthorized provider')], 403);
        }

        $wallet = Wallet::getOrCreateForUser($seller->id);

        return response()->json([
            'status' => 'success',
            'balance' => amount_with_currency_symbol($wallet->balance),
            'raw_balance' => (float) $wallet->balance,
            'pending_balance' => (float) $wallet->pending_balance,
            'total_earned' => (float) $wallet->total_earned,
            'currency' => $wallet->currency ?? 'SAR',
            'wallet_status' => (int) $wallet->status === 1 ? 'active' : 'suspended',
        ], 200);
    }

    public function sellerHistory(Request $request): JsonResponse
    {
        $seller = auth('sanctum')->user();
        if (!$seller || (int) $seller->user_type !== 0) {
            return response()->json(['msg' => __('Unauthorized provider')], 403);
        }

        $wallet = Wallet::getOrCreateForUser($seller->id);

        $histories = WalletHistory::where('wallet_id', $wallet->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($h) {
                return [
                    'id' => (int) $h->id,
                    'user_id' => (int) $h->user_id,
                    'entry_type' => (string) $h->entry_type,
                    'amount' => (float) $h->amount,
                    'balance_before' => (float) $h->balance_before,
                    'balance_after' => (float) $h->balance_after,
                    'payment_gateway' => (string) ($h->payment_gateway ?? 'wallet'),
                    'payment_status' => (string) ($h->payment_status ?? 'complete'),
                    'status' => (int) $h->status,
                    'transaction_id' => (string) $h->transaction_id,
                    'description_en' => (string) ($h->description_en ?? ''),
                    'description_ar' => (string) ($h->description_ar ?? ''),
                    'created_at' => optional($h->created_at)->toDateTimeString(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'history' => $histories,
        ], 200);
    }

    public function sellerRequestPayout(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:10',
            'payment_gateway' => 'required|string|max:50',
            'seller_note' => 'nullable|string|max:500',
        ]);

        $seller = auth('sanctum')->user();
        if (!$seller || (int) $seller->user_type !== 0) {
            return response()->json(['msg' => __('Unauthorized provider')], 403);
        }

        try {
            $payout = $this->walletService->requestPayout(
                $seller,
                (float) $request->amount,
                $request->payment_gateway,
                $request->seller_note
            );

            return response()->json([
                'status' => 'success',
                'msg' => __('Payout request submitted successfully and funds placed on hold.'),
                'payout_id' => $payout->id,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['msg' => $e->getMessage()], 422);
        }
    }
}
