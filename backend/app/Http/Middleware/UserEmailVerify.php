<?php

namespace App\Http\Middleware;

use Closure;

class UserEmailVerify
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(empty(get_static_option('disable_user_email_verify'))){
            if (auth('web')->check() && auth('web')->user()->email_verified == 0 && empty(get_static_option('disable_user_email_verify')) && request()->path() !== 'seller/logout'){
                return redirect()->route('email.verify');
            }
        }elseif(empty(get_static_option('disable_user_otp_verify'))){
            if (auth('web')->check() && auth('web')->user()->otp_verified == 0 && empty(get_static_option('disable_user_otp_verify')) && request()->path() !== 'seller/logout'){
                return redirect()->route('otp.verification', ['user_id' => auth('web')->user()->id]);
            }
        }

        return $next($request);
    }
}
