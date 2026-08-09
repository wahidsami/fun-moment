<?php

namespace App\Http\Middleware;

use App\Language;
use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SetLang
{

    public function handle($request, Closure $next)
    {
        $defaultLang =  Language::where('default',1)->first();
        if (session()->has('lang')) {
            $current_lang = Language::where('slug',session()->get('lang'))->first();
            if (!empty($current_lang)){
                Carbon::setLocale($current_lang->slug);
                app()->setLocale($current_lang->slug);
            }else {
                session()->forget('lang');
            }
        }else{
            if (!empty($defaultLang) && !empty($defaultLang->slug)) {
                Carbon::setLocale($defaultLang->slug);
                app()->setLocale($defaultLang->slug);
            } else {
                Carbon::setLocale(config('app.locale', 'en'));
                app()->setLocale(config('app.locale', 'en'));
            }
        }
        return $next($request);
    }
}
