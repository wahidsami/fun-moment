<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserEmailVerify
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Check API / Sanctum authenticated user
        $sanctumUser = $request->user('sanctum') ?: Auth::guard('sanctum')->user() ?: $request->user();
        if ($sanctumUser) {
            // Check email verification status
            if ((int) $sanctumUser->email_verified !== 1) {
                return response()->json([
                    'status' => 'failed',
                    'message' => __('Your email address is not verified. Please verify your email before continuing.'),
                    'requires_verification' => true,
                    'email' => $sanctumUser->email,
                    'email_verified' => false,
                ], 403);
            }

            // Check account status (suspension/inactive)
            if ($sanctumUser->user_status !== null && (int) $sanctumUser->user_status === 0) {
                return response()->json([
                    'status' => 'failed',
                    'message' => __('Your account has been deactivated or suspended. Please contact support.'),
                    'account_status' => 'suspended',
                ], 403);
            }
        }

        // 2. Existing Web Guard logic
        if (Auth::guard('web')->check()) {
            $webUser = Auth::guard('web')->user();

            if ($webUser->user_status !== null && (int) $webUser->user_status === 0) {
                Auth::guard('web')->logout();
                return redirect()->route('user.login')->with([
                    'msg' => __('Your account is inactive! Please contact the administrator'),
                    'type' => 'danger',
                ]);
            }

            if (empty(get_static_option('disable_user_email_verify'))) {
                if ((int) $webUser->email_verified === 0 && request()->path() !== 'seller/logout') {
                    if ($request->expectsJson() || $request->is('api/*')) {
                        return response()->json([
                            'status' => 'failed',
                            'message' => __('Your email address is not verified. Please verify your email before continuing.'),
                            'requires_verification' => true,
                            'email' => $webUser->email,
                            'email_verified' => false,
                        ], 403);
                    }
                    return redirect()->route('email.verify');
                }
            } elseif (empty(get_static_option('disable_user_otp_verify'))) {
                if ((int) $webUser->otp_verified === 0 && request()->path() !== 'seller/logout') {
                    if ($request->expectsJson() || $request->is('api/*')) {
                        return response()->json([
                            'status' => 'failed',
                            'message' => __('Your phone number is not verified.'),
                            'requires_verification' => true,
                        ], 403);
                    }
                    return redirect()->route('otp.verification', ['user_id' => $webUser->id]);
                }
            }
        }

        return $next($request);
    }
}
