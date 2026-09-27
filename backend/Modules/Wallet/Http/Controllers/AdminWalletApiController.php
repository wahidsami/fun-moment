<?php

namespace Modules\Wallet\Http\Controllers;

use App\AdminAuditLog;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;
use Modules\Wallet\Services\WalletService;

class AdminWalletApiController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->middleware('auth:admin');
        $this->walletService = $walletService;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->apiIndex($request);
    }

    public function show(int $userId): JsonResponse
    {
        return $this->apiUserWallet($userId);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $query = Wallet::with('user');

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->whereHas('user', function ($q) use ($s) {
                $q->where('name', 'ilike', "%{$s}%")
                    ->orWhere('email', 'ilike', "%{$s}%")
                    ->orWhere('phone', 'ilike', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $statusVal = $request->status === 'active' ? 1 : 0;
            $query->where('status', $statusVal);
        }

        $wallets = $query->orderByDesc('balance')->get()->map(function (Wallet $w) {
            $user = $w->user;
            return [
                'id' => (int) $w->id,
                'user_id' => (int) $w->user_id,
                'user_name' => optional($user)->name ?? 'User #' . $w->user_id,
                'user_email' => optional($user)->email ?? '',
                'user_phone' => optional($user)->phone ?? '',
                'role' => (int) optional($user)->user_type === 0 ? 'seller' : 'buyer',
                'balance' => (float) $w->balance,
                'pending_balance' => (float) $w->pending_balance,
                'total_earned' => (float) $w->total_earned,
                'total_spent' => (float) $w->total_spent,
                'status' => (int) $w->status === 1 ? 'active' : 'suspended',
                'currency' => $w->currency ?? 'SAR',
                'updated_at' => optional($w->updated_at)->toDateTimeString(),
            ];
        });

        $allWallets = Wallet::all();
        $summary = [
            'total_wallets' => $allWallets->count(),
            'active_wallets' => $allWallets->where('status', 1)->count(),
            'frozen_wallets' => $allWallets->where('status', 0)->count(),
            'total_circulation' => round((float) $allWallets->sum('balance'), 2),
            'total_pending' => round((float) $allWallets->sum('pending_balance'), 2),
            'total_earned' => round((float) $allWallets->sum('total_earned'), 2),
            'total_spent' => round((float) $allWallets->sum('total_spent'), 2),
            'currency' => 'SAR',
        ];

        return response()->json([
            'status' => 'success',
            'summary' => $summary,
            'wallets' => $wallets,
        ]);
    }

    public function apiUserWallet(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $wallet = Wallet::getOrCreateForUser($userId);

        $ledger = WalletHistory::where('wallet_id', $wallet->id)
            ->with('admin')
            ->orderByDesc('id')
            ->get()
            ->map(function (WalletHistory $h) {
                return [
                    'id' => (int) $h->id,
                    'transaction_id' => (string) $h->transaction_id,
                    'entry_type' => (string) $h->entry_type,
                    'amount' => (float) $h->amount,
                    'balance_before' => (float) $h->balance_before,
                    'balance_after' => (float) $h->balance_after,
                    'payment_gateway' => (string) ($h->payment_gateway ?? 'wallet'),
                    'payment_status' => (string) ($h->payment_status ?? 'complete'),
                    'reference_type' => (string) ($h->reference_type ?? ''),
                    'reference_id' => (string) ($h->reference_id ?? ''),
                    'description_en' => (string) ($h->description_en ?? ''),
                    'description_ar' => (string) ($h->description_ar ?? ''),
                    'admin_name' => optional($h->admin)->name,
                    'admin_note' => (string) ($h->admin_note ?? ''),
                    'created_at' => optional($h->created_at)->toDateTimeString(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'phone' => (string) $user->phone,
                'role' => (int) $user->user_type === 0 ? 'seller' : 'buyer',
            ],
            'wallet' => [
                'id' => (int) $wallet->id,
                'balance' => (float) $wallet->balance,
                'pending_balance' => (float) $wallet->pending_balance,
                'total_earned' => (float) $wallet->total_earned,
                'total_spent' => (float) $wallet->total_spent,
                'status' => (int) $wallet->status === 1 ? 'active' : 'suspended',
                'currency' => $wallet->currency ?? 'SAR',
            ],
            'ledger' => $ledger,
        ]);
    }

    public function apiAdjustBalance(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'direction' => 'required|in:credit,debit',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        $admin = Auth::guard('admin')->user();
        $adminId = $admin ? $admin->id : 1;

        try {
            $history = $this->walletService->adminAdjustment(
                $userId,
                (float) $validated['amount'],
                $validated['direction'],
                $adminId,
                $validated['reason']
            );

            $wallet = Wallet::where('user_id', $userId)->first();

            return response()->json([
                'status' => 'success',
                'message' => 'Wallet balance adjusted successfully.',
                'transaction_id' => $history->transaction_id,
                'balance' => (float) $wallet->balance,
                'history' => $history,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function apiUpdateStatus(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended',
        ]);

        $statusVal = $validated['status'] === 'active' ? 1 : 0;
        $wallet = Wallet::where('user_id', $userId)->firstOrFail();
        $oldStatus = (int) $wallet->status === 1 ? 'active' : 'suspended';
        $wallet->status = $statusVal;
        $wallet->save();

        $admin = Auth::guard('admin')->user();
        AdminAuditLog::record(
            $admin ? $admin->id : 1,
            'wallet_status_update',
            "Wallet #{$wallet->id} (User #{$userId})",
            "Changed wallet status from {$oldStatus} to {$validated['status']}",
            "تغيير حالة المحفظة من {$oldStatus} إلى {$validated['status']}",
            ['status' => $oldStatus],
            ['status' => $validated['status']],
            $request->ip()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Wallet status updated to {$validated['status']}.",
            'wallet_status' => $validated['status'],
        ]);
    }
}
