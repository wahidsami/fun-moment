<?php

namespace App\Http\Controllers\Auth;

use App\Accountdeactive;
use App\Country;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Mail\BasicMail;
use App\User;
use Session;
use Str;
use Twilio\Rest\Client;
use Exception;


class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
//    protected $redirectTo = '/';
    public function redirectTo()
    {
        return route('homepage');
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('guest:admin')->except('logout');
    }

    /**
     * Override username functions
     * @since 1.0.0
     * */
    public function username()
    {
        return 'username';
    }

    /**
     * show admin login page
     * @since 1.0.0
     * */
    public function showAdminLoginForm()
    {
        return view('auth.admin.login');
    }

    /**
     * admin login system
     * */
    public function adminLogin(Request $request)
    {
        $email_or_username = filter_var($request->username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $this->validate($request, [
            'username' => 'required|string',
            'password' => 'required|min:6'
        ], [
            'username.required' => sprintf(__('required').'%s',$email_or_username),
            'password.required' => __('password required')
        ]);

        if (Auth::guard('admin')->attempt([$email_or_username => $request->username, 'password' => $request->password], $request->get('remember'))) {

            return response()->json([
                'msg' => __('Login Success Redirecting'),
                'type' => 'success',
                'status' => 'ok'
            ]);
        }
        return response()->json([
            'msg' => sprintf(__('Your or Password Is Wrong !!').'%s',$email_or_username),
            'type' => 'danger',
            'status' => 'not_ok'
        ]);
    }

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function userLogin(Request $request)
    {
        if($request->isMethod('post')){
            $email_or_username = filter_var($request->username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|min:6'
            ],
                [
                    'username.required' => sprintf(__('required').'%s',$email_or_username),
                    'password.required' => __('password required')
                ]);

            if (Auth::guard('web')->attempt([$email_or_username => $request->username, 'password' => $request->password],$request->get('remember'))){
                // check account delete status
                $user_account_status = Accountdeactive::where('user_id', Auth::guard('web')->user()->id)
                    ->where('status', 1)
                    ->first();

                if (!empty($user_account_status) && $user_account_status->status === 1){
                    Auth::guard('web')->logout();
                    return response()->json([
                        'msg' => __('Your account has been deleted'),
                        'type' => 'danger',
                        'status' => 'account-delete'
                    ]);
                }else{
                    if(Auth::user()->user_type==0){
                        return response()->json([
                            'msg' => __('Login Success Redirecting'),
                            'type' => 'success',
                            'status' => 'seller-login'
                        ]);

                    }else{
                        return response()->json([
                            'msg' => __('Login Success Redirecting'),
                            'type' => 'success',
                            'status' => 'buyer-login'
                        ]);
                    }

                }
            }

            return response()->json([
                'msg' => sprintf(__('Your %s or Password Is Wrong !!'),$email_or_username),
                'type' => 'danger',
                'status' => 'not_ok'
            ]);
        }

        return view('frontend.user.login');
    }



    // user login page get
    public function setPhoneNumber(Request $request)
    {

        if($request->isMethod('post')){
            if(!empty($request->full_number)){
                $user_details = User::where('phone', $request->full_number)->first();
                if (empty($user_details)){
                    $cleaned_number = str_replace('-', '', $request->phone);
                    $remove_plus_number = str_replace('+', '', $request->full_number);
                    $trim_first_two_string = substr($request->full_number, 2);
                    $trim_first_three_string = substr($request->full_number, 3);
                    $trim_first_four_string = substr($request->full_number, 4);

                    $user_details = User::where(function ($query) use ($cleaned_number, $request, $remove_plus_number, $trim_first_two_string, $trim_first_three_string, $trim_first_four_string) {
                        $query->where('phone', $cleaned_number)
                            ->orWhere('phone', $request->input('full_number'))
                            ->orWhere('phone', $remove_plus_number)
                            ->orWhere('phone', $trim_first_two_string)
                            ->orWhere('phone', $trim_first_three_string)
                            ->orWhere('phone', $trim_first_four_string);
                    })->first();

                    if (!empty($user_details->phone) && strpos($user_details->phone, '+') !== 0) {
                        // update phone number
                        $user_details->update([
                            'phone' => $request->full_number,
                        ]);
                    }

                }
            }else{
                return redirect()->back()->with([ 'msg' => __('Phone Number is required'), 'type' => 'danger' ]);
            }

            if (!empty($user_details)){
                if($user_details->otp_code && now()->isAfter($user_details->otp_expire_at) || is_null($user_details->otp_code)){
                        /* Generate An OTP */
                        $this->generateOtp($user_details->phone);
                        $this->sendSMS($user_details->phone);
                }
                return view('frontend.user.otp-verification',compact('user_details'));
            }else{
                return back()->with(['msg' => __('Your Phone Number is Not match'),'type' => 'danger']);
            }
        }

        $countries = Country::where('status', 1)->get();
        $restricted_countries = $countries->pluck('country_code')->toJson();
        return view('frontend.user.set-phone-number-to-login-otp-code', compact('restricted_countries'));
    }


    // login with OTP
    public function loginWithOtpCode(Request $request)
    {

        $user_details = User::where(['id' => $request->user_id,'otp_code' => $request->otp_code])->first();
        if (empty($request->otp_code)){
            return back()->with(['msg' => __('OTP code is required'),'type' => 'danger']);
        }

        if (empty($user_details)){
            $user_details = User::findOrFail($request->user_id);
            toastr()->error(__('OTP code doesn’t match.'));
            return view('frontend.user.otp-verification', compact('user_details'));
        }

        if ($user_details->otp_code && now()->isAfter($user_details->otp_expire_at)){
            $user_details = User::findOrFail($request->user_id);
            toastr()->error(__('Your OTP has been expired.'));
            return view('frontend.user.otp-verification', compact('user_details'));
        }

        if(!is_null($user_details)){
            Auth::login($user_details);
            if($user_details->user_type==0){
                return redirect()->route('seller.dashboard');
            }else{
                return redirect()->route('buyer.dashboard');
            }
        }

    }

    public function resentOtpCodeLogin($user_id){

        $user_details = User::findOrFail($user_id);
        if(!empty($user_details->otp_code)) {
            /* Generate An OTP */
            if($user_details->otp_code && now()->isAfter($user_details->otp_expire_at)){
                $userOtp = $this->generateOtp($user_details->phone);
                $this->sendSMS($user_details->phone);
            }
        }

        return view('frontend.user.otp-verification', compact('user_details'));
    }


    public function generate(Request $request)
    {
        /* Generate An OTP */
        $userOtp = $this->generateOtp($request->phone);
        $this->sendSMS($request->phone);
        return redirect()->route('otp.verification', ['user_id' => $userOtp->id])
            ->with('success',  __("OTP has been sent on Your Mobile Number."));
    }

    // todo: first user get then user otp create in user table
    public function generateOtp($phone_no)
    {
            $userOtp = User::select('id', 'otp_code', 'otp_expire_at', 'phone')->where('phone', $phone_no)->first();
            /* Create a New OTP */
            if (!empty($userOtp)){
                $now = now();
                if (!empty(get_static_option('user_otp_expire_time'))){
                    if (get_static_option('user_otp_expire_time') == 30){
                        $add_second = get_static_option('user_otp_expire_time');
                        User::where('id', $userOtp->id)->update([
                            'otp_code' => rand(123456, 999999),
                            'otp_expire_at' => $now->addSecond($add_second)
                        ]);
                    }else{
                        $add_minutes = get_static_option('user_otp_expire_time');
                        User::where('id', $userOtp->id)->update([
                            'otp_code' => rand(123456, 999999),
                            'otp_expire_at' => $now->addMinutes($add_minutes)
                        ]);
                    }
                }else{
                    User::where('id', $userOtp->id)->update([
                        'otp_code' => rand(123456, 999999),
                        'otp_expire_at' => $now->addMinutes(1)
                    ]);
                }

            }


    }


    //todo: otp send code with Twilio
    public function sendSMS($receiverNumber)
    {
        // find user
        $user_details = User::select('id', 'otp_code', 'otp_expire_at')->where('phone', $receiverNumber)->first();
        $otp_with_message = __('Login OTP is');
        $message = $otp_with_message. ': ' .$user_details->otp_code;
        try{
            $account_sid = getenv("TWILIO_SID");
            $auth_token = getenv("TWILIO_AUTH_TOKEN");
            $twilio_number = getenv("TWILIO_NUMBER");
            $client = new Client($account_sid, $auth_token);

            $client->messages->create($receiverNumber,  [
                'from' => $twilio_number,
                'body' => $message
            ]);
            info(__('SMS Sent Successfully.'));

        } catch (Exception $e) {
            info("Error: ". $e->getMessage());
        }
    }


    public function userLoginOnline(Request $request)
    {
        $email_or_username = filter_var($request->username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|min:6'
        ],
            [
                'username.required' => sprintf(__('required').'%s',$email_or_username),
                'password.required' => __('password required')
            ]);

        if (Auth::guard('web')->attempt([$email_or_username => $request->username, 'password' => $request->password],$request->get('remember'))){

            return redirect()->back();

        }
        return redirect()->back();
    }

    public function userForgetPassword(Request $request){

        if($request->isMethod('post')){
            $this->validate($request,[
                'email' => 'required|email'
            ],[
                'email.required' => __('Email is required')
            ]);

            $email = User::select('email')->where('email',$request->email)->count();
            if($email >= 1){
                $password = Str::random(6);
                $new_password = Hash::make($password );
                User::where('email',$request->email)->update(['password'=>$new_password]);
                try {
                    $message_body = __('Here is your new password').' <span class="verify-code">'.$password.'</span>';
                    Mail::to($request->email)->send(new BasicMail([
                        'subject' => __('Your new password send'),
                        'message' => $message_body
                    ]));
                }catch (\Exception $e){

                }

                return redirect()->back()->with(['msg' => __('Password generate success.Check email for new password'),'type' => 'success' ]);
            }
            return redirect()->back()->with(Session::flash('msg', __('Email does not exists') ));
        }
        return view('frontend.user.forget-password-form');
    }


}