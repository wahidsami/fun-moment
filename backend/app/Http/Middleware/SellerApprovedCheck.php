<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerApprovedCheck
{
    /**
     * Handle an incoming request.
     *
     * Enforces that service provider accounts have approved commercial verification (seller_verifies.status === 1)
     * before accessing service creation, schedule management, or booking fulfillment endpoints.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('sanctum') ?: Auth::guard('sanctum')->user() ?: $request->user();

        if ($user && (int) $user->user_type === 0) { // Provider / Seller
            $sellerVerify = $user->sellerVerify;
            $status = $sellerVerify ? (int) $sellerVerify->status : 0;

            if ($status !== 1) {
                $statusLabel = $status === 2 ? 'rejected' : 'pending';
                return response()->json([
                    'status' => 'failed',
                    'message' => $status === 2
                        ? __('Your seller account verification was rejected. Please review your submitted documents or contact support.')
                        : __('Your seller account is pending commercial verification approval. You cannot perform this action until your documents are approved by an administrator.'),
                    'seller_verified' => false,
                    'commercial_status' => $status,
                    'commercial_status_label' => $statusLabel,
                ], 403);
            }
        }

        return $next($request);
    }
}

