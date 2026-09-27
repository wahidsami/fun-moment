<?php

namespace App\Http\Controllers;

use App\AdminAuditLog;
use App\Mail\BasicMail;
use App\PayoutRequest;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminPayoutApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function apiPayouts(): JsonResponse
    {
        $payouts = PayoutRequest::with('seller')
            ->orderByDesc('id')
            ->get()
            ->map(function (PayoutRequest $pr) {
                return $this->formatPayoutPayload($pr);
            })
            ->values();

        $summary = [
            'total_requests' => $payouts->count(),
            'pending_count' => $payouts->where('status', 'pending')->count(),
            'completed_count' => $payouts->where('status', 'completed')->count(),
            'rejected_count' => $payouts->where('status', 'rejected')->count(),
            'total_amount' => round($payouts->sum('amount'), 2),
            'completed_amount' => round($payouts->where('status', 'completed')->sum('amount'), 2),
        ];

        return response()->json([
            'status' => 'success',
            'payouts' => $payouts,
            'summary' => $summary,
        ]);
    }

    public function apiUpdatePayoutStatus(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,completed,rejected',
            'admin_note' => 'nullable|string|max:1000',
            'payment_receipt' => 'nullable|string|max:255',
        ]);

        $payout = PayoutRequest::with('seller')->findOrFail($id);
        $oldStatus = $payout->status;

        $legacyStatusMap = [
            'pending' => 0,
            'completed' => 1,
            'rejected' => 2,
        ];

        $numericStatus = $legacyStatusMap[$validated['status']] ?? 0;

        $updateData = [
            'status' => $numericStatus,
        ];

        if ($request->has('admin_note')) {
            $updateData['admin_note'] = $validated['admin_note'];
        }

        if ($request->has('payment_receipt')) {
            $updateData['payment_receipt'] = $validated['payment_receipt'];
        }

        $payout->update($updateData);

        // If completed, notify seller
        if ($numericStatus === 1 && $payout->seller && !empty($payout->seller->email)) {
            try {
                $templateMessage = get_static_option('admin_withdraw_amount_send_message') ?? '';
                $amountFormatted = $payout->amount . ' SAR';
                $message = str_replace(["@name", "@withdraw_amount"], [$payout->seller->name, $amountFormatted], $templateMessage);
                Mail::to($payout->seller->email)->send(new BasicMail([
                    'subject' => get_static_option('admin_withdraw_amount_send_subject') ?? __('Payment Request Completed'),
                    'message' => $message ?: __("Your payout request for :amount has been completed.", ['amount' => $amountFormatted]),
                ]));
            } catch (\Throwable $e) {
                // Ignore mail sending failure
            }
        }

        AdminAuditLog::record([
            'action' => 'payout_status_update',
            'resource_type' => 'PayoutRequest',
            'resource_id' => (string) $id,
            'details_en' => "Payout request #{$id} status changed to {$validated['status']} (Amount: {$payout->amount} SAR)",
            'details_ar' => "تم تحديث حالة طلب السحب #{$id} إلى {$validated['status']} (المبلغ: {$payout->amount} ريال)",
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $numericStatus, 'admin_note' => $payout->admin_note],
        ]);

        $fresh = $payout->fresh('seller');

        return response()->json([
            'status' => 'success',
            'message' => __('Payout status updated successfully.'),
            'payout' => $this->formatPayoutPayload($fresh),
        ]);
    }

    private function formatPayoutPayload(PayoutRequest $pr): array
    {
        $statusStr = match ((int) $pr->status) {
            1 => 'completed',
            2 => 'rejected',
            default => 'pending',
        };

        return [
            'id' => (int) $pr->id,
            'seller_id' => (int) $pr->seller_id,
            'seller_name' => optional($pr->seller)->name ?? __('Unknown Provider'),
            'seller_email' => optional($pr->seller)->email ?? '',
            'seller_phone' => optional($pr->seller)->phone ?? '',
            'amount' => (float) $pr->amount,
            'payment_gateway' => $pr->payment_gateway ?? 'bank_transfer',
            'status' => $statusStr,
            'seller_note' => $pr->seller_note ?? '',
            'admin_note' => $pr->admin_note ?? '',
            'payment_receipt' => $pr->payment_receipt ?? null,
            'created_at' => optional($pr->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($pr->updated_at)->format('Y-m-d H:i:s'),
        ];
    }
}
