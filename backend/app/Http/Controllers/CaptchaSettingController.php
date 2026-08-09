<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\FlashMsg;

class CaptchaSettingController extends Controller
{
    public function captchaPageSettings(Request $request)
    {
         if($request->isMethod('post')){

            $this->validate($request, [
                'recaptcha_2_site_key' => 'nullable',
            ]);

            $field = 'recaptcha_2_site_key';
           
            update_static_option($field, $request->$field);
            
            return redirect()->back()->with(FlashMsg::settings_update());
        }
        return view('backend.pages.appearance-settings.captcha-settings');
    }
}
