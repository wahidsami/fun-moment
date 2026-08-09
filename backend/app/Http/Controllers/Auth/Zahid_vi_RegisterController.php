<?php

namespace App\Http\Controllers\Auth;

use App\Admin;
use App\Helpers\FlashMsg;
use App\Http\Controllers\Controller;
use App\User;
use Carbon\Carbon;
use Dflydev\DotAccessData\Data;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\BasicMail;
use App\ServiceCity;
use App\ServiceArea;
use App\Country;
use App\SellerVerify;
use Toastr;
use Str;
USE Auth;
use Session;
use DB;
use Twilio\Rest\Client;
use Exception;

class Zahid_vi_RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
//    protected $redirectTo = '/';
    public function redirectTo(){
        return route('homepage');
    }
    public function __construct()
    {
        $this->middleware('guest');
        $this->middleware('guest:admin');
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:191'],
            'captcha_token' => ['nullable'],
            'username' => ['required', 'string', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ],[
            'captcha_token.required' => __('google captcha is required'),
            'name.required' => __('name is required'),
            'name.max' => __('name is must be between 191 character'),
            'username.required' => __('username is required'),
            'username.max' => __('username is must be between 191 character'),
            'username.unique' => __('username is already taken'),
            'email.unique' => __('email is already taken'),
            'email.required' => __('email is required'),
            'password.required' => __('password is required'),
            'password.confirmed' => __('both password does not matched'),
        ]);
    }
    protected function adminValidator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:admins'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    protected function create(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['user_name'],
            'phone' => $data['phone'],
            'service_city' => $data['service_city'],
            'service_area' => $data['service_area'],
            'password' => Hash::make($data['password']),
        ]);
        return $user;
    }

    public function userRegister(Request $request)
    {   
        if($request->isMethod('post')){

                // check is seller area is required or null
                if($request->get_user_type == 0){

                    if(empty(get_static_option('seller_service_area_required'))){

                        // if OTP is enabled
                        if (empty(get_static_option('disable_user_otp_verify'))){
                            $phone_number_unique = Str::replace(['-', ' '], '', $request->phone);
                            $country_code_with_code = '+'.$request->country_code.$phone_number_unique;
                            $existingUser = User::where('phone', $country_code_with_code)->first();
                            if ($existingUser) {
                                return redirect()->back()->withErrors(['phone' => 'Phone number is already taken']);
                            }
                        }

                        $request->validate([
                            'name' => 'required|max:191',
                            'email' => 'required|email|unique:users|max:191',
                            'username' => 'required|unique:users|max:191',
                            'phone' => 'required|unique:users|max:191',
                            'password' => 'required|max:191',
                            'service_city' => 'required',
                            'country' => 'required',
                        ]);
                    }
                }else{

                    // if OTP is enabled
                    if (empty(get_static_option('disable_user_otp_verify'))){
                        $phone_number_unique = Str::replace(['-', ' '], '', $request->phone);
                        $country_code_with_code = '+'.$request->country_code.$phone_number_unique;
                        $existingUser = User::where('phone', $country_code_with_code)->first();
                        if ($existingUser) {
                            return redirect()->back()->withErrors(['phone' => 'Phone number is already taken']);
                        }
                    }

                    $request->validate([
                        'name' => 'required|max:191',
                        'email' => 'required|email|unique:users|max:191',
                        'username' => 'required|unique:users|max:191',
                        'phone' => 'required|unique:users|max:191',
                        'password' => 'required|max:191',
                        'service_city' => 'required',
                        'service_area' => 'required',
                        'country' => 'required',
                    ]);
                }

            $email_verify_tokn = Str::random(8);
            $user_type = get_static_option('buyer_register_on_off') ==='off' ? 0 : $request->get_user_type;

            if(empty(get_static_option('disable_user_otp_verify'))){
               $user_number = $request->full_number;
            }else{
                $user_number = $request->phone;
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $request->username,
                'phone' => $user_number,
                'password' => Hash::make($request->password),
                'service_city' => $request->service_city,
                'service_area' => $request->service_area,
                'country_id' => $request->country,
                'user_type' => $user_type,
                'terms_conditions' =>1,
                'email_verify_token'=> $email_verify_tokn,
            ]);

            if(empty(get_static_option('disable_user_email_verify'))){
                if($user){
                    if($request->get_user_type==0){
                        $user_type = 'seller';
                    }else{
                        $user_type = 'buyer';
                    }

                    try {
                        $message = get_static_option('user_email_verify_message');
                        $message = str_replace(["@name", "@email_verify_tokn"],[$user->name, $email_verify_tokn],$message);
                        Mail::to($user->email)->send(new BasicMail([
                            'subject' => get_static_option('user_email_verify_subject'),
                            'message' => $message
                        ]));

                        $message = get_static_option('user_register_message');
                        $message = str_replace(["@name", "@type","@username","@email"],[$user->name, $user_type, $user->username, $user->email], $message);
                        Mail::to(get_static_option('site_global_email'))->send(new BasicMail([
                            'subject' => get_static_option('user_register_subject') ?? __('New User Registration'),
                            'message' => $message
                        ]));
                    } catch (\Exception $e) {

                    }
                }
            }


            if($request->get_user_type==0){
                $last_order_id = DB::getPdo()->lastInsertId();
                 SellerVerify::create([
                    'seller_id' => $last_order_id,
                    'status' => 0,
                ]);
            }

             if (Auth::guard('web')->attempt(['username' => $request->username, 'password' => $request->password], $request->get('remember'))) {
                if(Auth::user()->user_type==0){
                    return redirect()->route('seller.dashboard');
                }else{
                   return redirect()->route('buyer.dashboard');
                }
            }
            
            return back()->with([
                   'msg' => __('Email or password does not match'),
                   'type' => 'danger',
            ]);
        }

        $cities = ServiceCity::all();
        $countries = Country::all();
        return view('frontend.user.register',compact('cities','countries'));
    }



    // user register after opt view page
    public function otpVerification($user_id, Request $request){
        if(empty(Auth::guard('web')->user()->otp_code)) {
            $user_details = Auth::guard('web')->user();
            /* Generate An OTP */
            $userOtp = $this->generateOtp($user_details->phone ?? 0);
            $this->sendSMS($user_details->phone ?? 0);
        }

        // todo:: fetch user data from database by using User model
        $user = User::select("id","otp_expire_at")->findOrFail($user_id);
        // todo:: now otp_expire_at should be carbon date
        $expireDate = Carbon::parse($user->otp_expire_at)->diffInSeconds(Carbon::now());
        $user_details = $request->user_from == 'login' ? "user_from_login" : "";

        return view('frontend.user.otp-verification', compact('user_id', 'user','expireDate','user_details'));
    }

    public function resentOtpCode($user_id){
        if(!empty(Auth::guard('web')->user()->otp_code)) {
            $user_details = Auth::guard('web')->user();
            /* Generate An OTP */
            $userOtp = $this->generateOtp($user_details->phone);
            $this->sendSMS($user_details->phone);
        }

        $expireDate = $this->generateOptSeconds($user_id);

        return view('frontend.user.otp-verification', compact('user_id','expireDate'));
    }

    public function generateOptSeconds($user_id){
        // todo:: fetch user data from database by using User model
        $user = User::select("id","otp_expire_at")->findOrFail($user_id);
        // todo:: now otp_expire_at should be carbon date
        $expireDate = Carbon::now()->diffInSeconds(Carbon::parse($user->otp_expire_at));
        $diff = Carbon::now()->diff(Carbon::parse($user->otp_expire_at));

        if($diff->invert == 0){
            $expireDate = $expireDate <= get_static_option("user_otp_expire_time") ? $expireDate : 0;
        }else{
            $expireDate = 0;
        }

        return $expireDate;
    }



    public function emailVerify(Request $request)
    {

        // todo: first check user login with opt 2nd check user register with OTP
           if (!empty($request->phone)){
               $user_details = User::where('phone', $request->phone)->first();
           }else{
               $user_details = Auth::guard('web')->user();
           }


        // todo: if request is post and user otp code not null and null then code exit
        if($request->isMethod('get')) {
            if (empty(get_static_option('disable_user_otp_verify'))) {
                if(empty(Auth::guard('web')->user()->otp_code)) {
                    /* Generate An OTP */
                    $userOtp = $this->generateOtp($user_details->phone);
                    $this->sendSMS($user_details->phone);
                    return redirect()->route('otp.verification', ['user_id' => $user_details->id])->with('success', __("OTP has been sent on Your Mobile Number."));
                }
            }
        }


        // input code
        if($request->isMethod('post')){
            // if email and OTP is required
         if(empty(get_static_option('disable_user_otp_verify'))){
             $request->validate([
                 'otp_code' => 'required|max:191'
             ]);

             if($request->otp_code == ''){
                 return  back();
             }

                  // todo: first check user login with opt 2nd check user register with OTP
                   if (!empty($request->phone)) {
                       $userOtp = User::where('phone', $request->phone)->first();
                   }else{
                       $userOtp = User::where('id', $request->user_id)->where('otp_code', $request->otp_code)->first();
                   }

                   // todo: validation (01:empty) (02:not match) (03:time expire)
                    $now = now();


                    if (empty($userOtp)) {
                        return redirect()->route('otp.verification', ['user_id' => $user_details->id])->with(['msg' => __('Your OTP is not correct.') ,'type' => 'danger' ]);
                    }elseif(!empty($request->phone)) {
                        if ($userOtp->otp_code != $request->otp_code){
                            return redirect()->back()->with([
                                'msg' => __('Your OTP is not correct.'),
                                'type' => 'danger'
                            ]);
                        }

                        if ($request->otp_code == null){
                            return redirect()->back()->with([
                                'msg' => __('Your OTP is not correct.'),
                                'type' => 'danger'
                            ]);
                        }

                        if ($userOtp && $now->isAfter($userOtp->otp_expire_at)){
                            return redirect()->back()->with([
                                'msg' => __('Your OTP has been expired.'),
                                'type' => 'danger'
                            ]);
                        }

                    }elseif($userOtp && $now->isAfter($userOtp->otp_expire_at)){
                        return redirect()->route('otp.verification', ['user_id' => $user_details->id])->with(['msg' => __('Your OTP has been expired.') ,'type' => 'danger' ]);
                    }



                 // if only OTP is verify is required
                $user_details = User::where(['otp_code' => $request->otp_code,'phone' => $user_details->phone])->first();
                if(!is_null($user_details)){
                    $user_details->otp_verified = 1;
                    $user_details->save();

                    if($user_details->user_type==0){
                        return redirect()->route('seller.dashboard');
                    }else{
                        return redirect()->route('buyer.dashboard');
                    }
                }

                // if only email verify is required
            }elseif(empty(get_static_option('disable_user_email_verify'))){
                $this->validate($request,[
                    'email_verify_token' => 'required|max:191'
                ],[
                    'email_verify_token.required' => __('verify code is required')
                ]);
                $user_details = User::where(['email_verify_token' => $request->email_verify_token,'email' => $user_details->email ])->first();
                if(!is_null($user_details)){
                    $user_details->email_verified = 1;
                    $user_details->save();
                    if($user_details->user_type==0){
                        return redirect()->route('seller.dashboard');
                    }else{
                        return redirect()->route('buyer.dashboard');
                    }
                }
                return redirect()->back()->with(['msg' => __('Your verification code is wrong.') ,'type' => 'danger' ]);
            }
        }

      if(empty(get_static_option('disable_user_email_verify'))){
            $verify_token = $user_details->email_verify_token ?? null;
            try {
                //check user has verify token has or not
                if(is_null($verify_token)){
                    $verify_token = Str::random(8);
                    $user_details->email_verify_token = Str::random(8);
                    $user_details->save();

                    $message = get_static_option('user_email_verify_message');
                    $message = str_replace(["@name", "@email_verify_tokn"],[$user_details->name, $verify_token],$message);
                    Mail::to($user_details->email)->send(new BasicMail([
                        'subject' => get_static_option('user_email_verify_subject'),
                        'message' => $message
                    ]));
                }

            }catch (\Exception $e){
            }
            return view('frontend.user.email-verify');
        }

    }
    
    public function resendCode(){
        $user_details = Auth::guard('web')->user();
        $verify_token = $user_details->email_verify_token ?? null;
        try {
            
                if(is_null($verify_token)){
                    $verify_token = Str::random(8);
                    $user_details->email_verify_token = Str::random(8);
                    $user_details->save();
                }
                
                $message = get_static_option('user_email_verify_message');
                $message = str_replace(["@name", "@email_verify_tokn"],[$user_details->name, $verify_token],$message);
                
                Mail::to($user_details->email)->send(new BasicMail([
                    'subject' => get_static_option('user_email_verify_subject'),
                    'message' => $message
                ]));
        }catch (\Exception $e){
        }
        return redirect()->back()->with(['msg' => __('Resend Email Verify Code, Please check your inbox of spam.') ,'type' => 'success' ]);
    }





    public function generate(Request $request)
    {
        /* Generate An OTP */
        $userOtp = $this->generateOtp($request->phone);
        $this->sendSMS($request->phone);
        return redirect()->route('otp.verification', ['user_id' => $userOtp->id])
            ->with('success',  "OTP has been sent on Your Mobile Number.");
    }

    // todo: first user get then user otp create in user table
    public function generateOtp($phone_no)
    {
        $userOtp = User::select('id', 'otp_code', 'otp_expire_at')->where('phone', $phone_no)->first();
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
        $otp_with_message = __('Login OTP is:');
        $message = $otp_with_message.' ' .$user_details->otp_code;
        try {
            $account_sid = getenv("TWILIO_SID");
            $auth_token = getenv("TWILIO_AUTH_TOKEN");
            $twilio_number = getenv("TWILIO_NUMBER");
            $client = new Client($account_sid, $auth_token);
            $client->messages->create($receiverNumber, [
                'from' => $twilio_number,
                'body' => $message]);
            info(__('SMS Sent Successfully.'));

        } catch (Exception $e) {
            info("Error: ". $e->getMessage());
        }
    }


}