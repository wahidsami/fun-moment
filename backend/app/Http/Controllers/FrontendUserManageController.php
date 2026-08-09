<?php

namespace App\Http\Controllers;

use App\Category;
use App\Country;
use App\Helpers\DataTableHelpers\General;
use App\Helpers\GenerateUserToken;
use App\ServiceArea;
use App\ServiceCity;
use App\User;
use App\Helpers\FlashMsg;
use Illuminate\Http\JsonResponse;
use Twilio\Rest\Client;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\BasicMail;
use App\Mail\SingleMailToUser;
use App\Accountdeactive;
use App\MediaUpload;
use App\Service;
use App\SupportTicket;
use App\SupportTicketMessage;
use App\Serviceinclude;
use App\PayoutRequest;
use App\ToDoList;
use App\Review;
use App\Order;
use App\Serviceadditional;
use App\Servicebenifit;
use App\Day;
use App\Schedule;
use App\ExtraService;
use App\EditServiceHistory;
use App\OnlineServiceFaq;
use App\ServiceCoupon;
use App\SellerVerify;
use App\OrderAdditional;
use App\Report;
use App\ReportChatMessage;
use App\OrderCompleteDecline;
use App\Actions\Media\MediaHelper;
use Illuminate\Support\Str;
use Modules\Subscription\Entities\SellerSubscription;
use Modules\Subscription\Entities\SubscriptionHistory;
use Modules\JobPost\Entities\BuyerJob;
use Modules\JobPost\Entities\JobRequest;
use Modules\JobPost\Entities\JobRequestConversation;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;
use Modules\LiveChat\Entities\LiveChatMessage;

class FrontendUserManageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:user-list|user-create|user-edit|user-delete',['only' => ['all_user', 'apiUsers', 'apiUpdateUserStatus', 'apiUpdateUserBalance']]);
        $this->middleware('permission:user-delete',['only' => ['bulk_action','new_user_delete']]);
    }

    public function apiUsers(Request $request): JsonResponse
    {
        $users = User::with(['country', 'sellerVerify'])->orderByDesc('id')->get()->map(function (User $user) {
            return $this->formatUserPayload($user);
        })->values();

        return response()->json([
            'status' => 'success',
            'users' => $users,
            'counts' => [
                'total' => $users->count(),
                'sellers' => $users->where('role', 'seller')->count(),
                'buyers' => $users->where('role', 'buyer')->count(),
                'active' => $users->where('status', 'active')->count(),
                'suspended' => $users->where('status', 'suspended')->count(),
            ],
        ]);
    }

    public function apiUpdateUserStatus(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,pending',
        ]);

        $user = User::with(['country', 'sellerVerify'])->findOrFail($id);
        $status = $validated['status'];
        $userStatusValue = $status === 'active' ? 1 : 0;

        DB::transaction(function () use ($user, $userStatusValue) {
            $user->update([
                'user_status' => $userStatusValue,
            ]);

            if ($user->user_type === 0) {
                Service::where('seller_id', $user->id)->update(['status' => $userStatusValue]);
            }
        });

        $freshUser = User::with(['country', 'sellerVerify'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => __('User status updated successfully'),
            'user' => $this->formatUserPayload($freshUser),
        ]);
    }

    public function apiUpdateUserBalance(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'balance' => 'required|numeric|min:0',
        ]);

        $user = User::with(['country', 'sellerVerify'])->findOrFail($id);
        Wallet::updateOrInsert(
            ['buyer_id' => $user->id],
            ['balance' => $validated['balance']]
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Wallet balance updated successfully'),
            'user' => $this->formatUserPayload($user),
        ]);
    }

    private function formatUserPayload(User $user): array
    {
        $walletBalance = (float) (Wallet::where('buyer_id', $user->id)->value('balance') ?? 0);
        $role = (int) $user->user_type === 0 ? 'seller' : 'buyer';
        $status = (int) $user->user_status === 1 ? 'active' : 'suspended';
        $countryName = optional($user->country)->country ?? '';
        $avatar = null;

        if (!empty($user->image)) {
            $attachment = get_attachment_image_by_id($user->image);
            $avatar = is_array($attachment) ? ($attachment['img_url'] ?? null) : null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'status' => $status,
            'avatar' => $avatar,
            'phone' => $user->phone,
            'country' => $countryName,
            'created_at' => optional($user->created_at)->toDateString(),
            'wallet_balance' => $walletBalance,
            'email_verified' => (bool) $user->email_verified,
            'seller_verified' => optional($user->sellerVerify)->status === 1,
        ];
    }

    


    public function loginUsingToken($token = null, $id = null){

        if(empty($token)){
            return to_route('user.login');
        }

        abort_if(empty(Auth::guard('admin')->user()), 404);

        $user = null;
        if(!empty($id)){
            $user = User::find($id);
        }

        $hash_token = hash_hmac(
            'sha512',
            $user->username,
            $user->id
        );

        if(!hash_equals($hash_token,$token)){
            return to_route('admin.login');
        }

        //login using super admin id
        if (Auth::guard('web')->loginUsingId($user->id)){
            GenerateUserToken::regenerate($user);
            if($user->user_type === 1){
                return to_route('buyer.dashboard');
            }elseif($user->user_type === 0){
                return to_route('seller.dashboard');
            }
        }
        return to_route('admin.login');
        //redirect to admin panel home page
    }

    public function all_user(Request $request)
    {

        if ($request->ajax()){
            $data = User::with('sellerVerify')->take(10);
            if ($request->has('search.value')) {
                $searchValue = $request->input('search.value');
                $data->where(function ($query) use ($searchValue) {
                    $query->where('name', 'LIKE', '%' . $searchValue . '%')
                        ->orWhere('username', 'LIKE', '%' . $searchValue . '%')
                        ->orWhere('phone', 'LIKE', '%' . $searchValue . '%')
                        ->orWhere('email', 'LIKE', '%' . $searchValue . '%');
                });
            }

            return \DataTables::of($data)

                ->addIndexColumn()

                ->addColumn('checkbox',function ($row){
                    return General::bulkCheckbox($row->id);
                })
                ->addColumn('id',function ($row) {
                    return $row->id;
                })
                ->addColumn('name',function ($row){
                        $user_type = $row->user_type==0 ? __("Seller") : __("Buyer");
                        return $row->name." "."<".$row->username.">"."(".$user_type.")";
                })
                ->addColumn('user_status',function ($row) {
                    $url = route('admin.frontend.user.status',$row->id);
                    $user_status = $row->user_status==0 ? '<span class="text-warning">'. __('Inactive') .'</span>' : '<span class="text-info">'. __('Active') .'</span>';
                    $markup = $user_status.General::statusChange($url);
                    return $markup;
                })
                ->addColumn('user_verify',function ($row) {
                    $url = route('admin.frontend.seller.profile.view',$row->id);
                    $user_status = optional($row->sellerVerify)->status==1 ? '<span class="text-warning">'. __('Verified') .'</span>' : '<span class="text-info">'. __('Not Verified') .'</span>';
                    $markup = $user_status.'<a class="btn btn-info" href="'.$url.'"><i class="ti-eye"></i></a>';
                    return $markup;

                })
                ->addColumn('email_verify',function ($row) {
                    $url = route('admin.frontend.user.email.verify.code',$row->id);
                    if ($row->email_verified == 1){
                        $user_status = '<i class="fas fa-check-circle text-success mx-3"></i>';
                        $markup =  $row->email.$user_status;
                    }else{
                        // email verified
                        $verified_url = route('admin.frontend.seller.email.verify',$row->id);
                        if ($row->email_verified == 0 ){
                            $seller_email_verify = General::statusChange($verified_url);
                        }
                        $user_status = $row->email_verified == 1 ? '<i class="fas fa-check-circle text-success mx-3"></i>' : '<i class="fas fa-times-circle text-danger"></i>';
                        $markup =  $row->email." ".$user_status.'<a class="btn btn-primary btn-sm mb-3 mr-1 subcategory_edit_btn d-block" href="'.$url.'">'.__('Send Code').'</a>'. $seller_email_verify;
                    }
                    return $markup;
                })
                ->addColumn('phone',function ($row) {
                    if(empty(get_static_option('disable_user_otp_verify'))){
                        $url = route('admin.frontend.user.otp.verify.code',$row->id);
                        $row->phone;
                        $verified_url = route('admin.frontend.seller.otp.verify',$row->id);

                        if ($row->otp_verified != 1 ){
                            $seller_phone_verify = General::statusChange($verified_url);
                            $user_status = $row->otp_verified == 1 ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>';
                            $markup =  $row->phone." ".$user_status.'<a class="btn btn-primary btn-sm mb-3 mr-1 subcategory_edit_btn d-block" href="'.$url.'">'.__('Send OTP Code').'</a>'. $seller_phone_verify;
                        }else{
                            $markup =  $row->phone.'<i class="fa fa-check-circle text-success mx-2"></i>';
                        }
                    }else{
                        $markup =  $row->phone;
                    }
                    return $markup;
                })
                ->addColumn('action', function($row){
                    $action = '';
                    $testimonial_img = get_attachment_image_by_id($row->image,null,true);
                    $img_url = $testimonial_img['img_url'];
                    $action .= ' <a href="#"
                                   data-toggle="modal"
                                   data-target="#edit_user_info_modal"
                                   class="btn btn-info btn-sm mb-3 mr-1 edit_user_info_btn"
                                   data-id="'.$row->id.'"
                                   data-email="'.$row->email.'"
                                   data-username="'.$row->username.'"
                                   data-name="'.$row->name.'"
                                   data-phone="'.$row->phone.'"
                                   data-country="'.$row->country_id.'"
                                   data-city="'.$row->service_city.'"
                                   data-area="'.$row->service_area.'"
                                   data-address="'.$row->address.'"
                                   data-tax_number="'.$row->tax_number.'"
                                   data-user_type="'.$row->user_type.'"
                                   data-imageid="'.$row->image.'"
                                   data-image="'.$img_url.'"
                                >
                                    <i class="ti-user"></i>
                                </a>';
                    $action .= '<a href="#"
                                   data-toggle="modal"
                                   data-target="#change_password_modal"
                                   class="btn btn-warning btn-sm mb-3 mr-1 change_password_modal_btn"
                                   data-id="'.$row->id.'"
                                   data-email="'.$row->email.'">
                                    <i class="ti-key"></i>
                                </a>';
                    $admin = auth()->guard('admin')->user();
                    if ($admin->can('user-delete')){
                        $action .= General::deletePopover(route('admin.frontend.user.delete',$row->id));
                    }
                    if ($admin->can('email-verify-code')){
                        $action .= '<a href="#"
                                        data-toggle="modal"
                                        data-target="#send_mail_modal"
                                        class="btn btn-primary btn-xs mb-3 mr-1 send_mail_modal_btn"
                                        data-id="'.$row->id.'"
                                        data-email="'.$row->email.'"
                                    >
                                        '.__('Send Email').'
                                    </a>';

                        if(empty(get_static_option('disable_user_email_verify'))){
                            if(!empty($row->email_verified)){
                                $hash_token = hash_hmac('sha512',$row->username, $row->id);
                                $action .= '<a href="' . route('user.login.with.token', ['token' => $hash_token, 'id' => $row->id]) . '"
                             class="btn btn-info btn-xs mb-3 mr-1 send_mail_modal_btn"
                             target="_blank">
                             ' . __('Login User Account') . '
                           </a>';
                            }
                        }elseif(empty(get_static_option('disable_user_otp_verify'))){
                            if(!empty($row->otp_verified === 1)){
                                $hash_token = hash_hmac('sha512',$row->username, $row->id);
                                $action .= '<a href="' . route('user.login.with.token', ['token' => $hash_token, 'id' => $row->id]) . '"
                             class="btn btn-info btn-xs mb-3 mr-1 send_mail_modal_btn"
                             target="_blank">
                             ' . __('Login User Account') . '
                           </a>';
                            }
                        }
                    }

                    return $action;
                })
                ->rawColumns(['checkbox','action','user_status','user_verify','email_verify', 'phone'])
                ->make(true);
        }
        $countries = Country::all();
        $cities = ServiceCity::all();
        $areas = ServiceArea::all();
        return view('backend.frontend-user.all-user',compact('countries', 'cities', 'areas'));
    }

    public function sellerAddPage()
    {

        $countries = Country::where('status', 1)->get();
        return view('backend.pages.seller-verify.add-seller', compact('countries'));
    }

    public function sellerCreate(Request $request)
    {
            $request->validate([
                'name' => 'required|max:191',
                'email' => 'required|email|unique:users|max:191',
                'username' => 'required|unique:users|max:191',
                'phone' => 'required|unique:users|max:191',
                'password' => 'required|max:191'
            ]);

            $email_verify_tokn = rand(111,999).rand(222,888);
            if(empty(get_static_option('disable_user_otp_verify'))){
                $user_number = $request->valid_phone_number;
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
                'user_type' => 0,
                'terms_conditions' =>1,
                'email_verify_token'=> $email_verify_tokn,
            ]);


        // opt create and update
        if(empty(get_static_option('disable_user_otp_verify'))){
            if ($user){
                if (!empty(get_static_option('user_otp_expire_time'))){
                        $add_second = get_static_option('user_otp_expire_time');
                        $user->update([
                            'otp_code' => rand(123456, 999999),
                            'otp_expire_at' => now()->addSecond($add_second)
                        ]);
                }else{
                    $user->update([
                        'otp_code' => rand(123456, 999999),
                        'otp_expire_at' => now()->addMinutes(1)
                    ]);
                }

                // opt sent to seller
                $otp_with_message = __('Login OTP is:');
                $message = $otp_with_message.' ' .$user->otp_code;
                try {
                    $account_sid = getenv("TWILIO_SID");
                    $auth_token = getenv("TWILIO_AUTH_TOKEN");
                    $twilio_number = getenv("TWILIO_NUMBER");
                    $client = new Client($account_sid, $auth_token);
                    $client->messages->create($user->phone, [
                        'from' => $twilio_number,
                        'body' => $message]);
                    info(__('SMS Sent Successfully.'));
                } catch (Exception $e) {
                    info("Error: ". $e->getMessage());
                }

            }
        }

        // send email to seller
        if($user){
            try {
                $message = get_static_option('user_email_verify_message');
                $message = str_replace(["@name", "@email_verify_tokn"],[$user->name, $email_verify_tokn],$message);
                Mail::to($user->email)->send(new BasicMail([
                    'subject' => get_static_option('user_email_verify_subject'),
                    'message' => $message
                ]));
            } catch (\Exception $e) {

            }
        }

        // seller Verify info add
        if($user){
            SellerVerify::create([
                'seller_id' => $user->id,
                'status' => 0,
            ]);
        }

        return redirect()->route('admin.frontend.seller.all')->with(FlashMsg::item_new('Seller created success--'));

    }

    public function sellerAll()
    {
        $all_user = User::with('sellerVerify')
            ->where('user_type',0)
            ->get();
        return view('backend.pages.seller-verify.verify')->with(['all_user' => $all_user]);
    }

    public function userStatus($id=null)
    {   
        $user_status = User::select('user_status')->find($id);
        User::where('id', $id)->update([
            'user_status' => $user_status->user_status== 0 ? 1 : 0
        ]);

        $user_status_2 = User::select('user_status')->find($id);
        if($user_status_2->user_status == 0){
            Service::where('seller_id',$id)
            ->update(['status'=>0]);
        }
        if($user_status_2->user_status == 1){
            Service::where('seller_id',$id)
            ->update(['status'=>1]);
        }
        return redirect()->back()->with(FlashMsg::item_new('Status change success--'));
    }

   //seller profile view
    public function sellerProfileView($id=null){
        $seller_details = User::with('sellerVerify')->where('id',$id)->first();
        return view('backend.frontend-user.seller-details',compact('seller_details'));
    }

    //seller verify
    public function sellerVerify($id)
    {

        $seller_status = SellerVerify::select('id','seller_id','status')->where('seller_id',$id)->firstOrCreate([
            'seller_id' => $id,
            'status' => 0,
        ]);


       $verify_seller = SellerVerify::where('seller_id', $id)->update([
            'status' => $seller_status->status === 0 ? 1 : 0
        ]);

       if($verify_seller){
           $seller = User::select('id','email','name')->where('id',$id)->first();
           try {
               $message = get_static_option('admin_seller_verification_message') ?? '';
               $message = str_replace(["@name"],[$seller->name],$message);
               Mail::to($seller ->email)->send(new BasicMail([
                   'subject' => get_static_option('admin_seller_verification_subject') ?? __('Seller Verification Success'),
                   'message' => $message
               ]));
           } catch (\Exception $e) {
               return redirect()->back()->with(FlashMsg::item_new($e->getMessage()));
           }
       }

        return redirect()->back()->with(FlashMsg::item_new('Status change success--'));
    }

    public function userDelete($id=null)
    {

        $user_info = User::find($id);

        //delete user related data
        if($user_info){
            $user_column = $user_info->user_type === 0 ? "seller_id" : "buyer_id";
        }else{
            return redirect()->back()->with(FlashMsg::item_delete('User not found--'));
        }

        //check user type
        if($user_info->user_type === 0){
            //delete seller related data
            //delete user service
            $services = Service::where("seller_id",$user_info->id)->delete();
            //delete payout history
            PayoutRequest::where("seller_id",$user_info->id)->delete();
            
            //delete subscription
            if(moduleExists("Subscription")){
                SubscriptionHistory::where('seller_id',$user_info->id)->delete();
                SellerSubscription::where('seller_id',$user_info->id)->delete();
            }
            
            //schedule
            Schedule::where("seller_id",$user_info->id)->delete();
            SellerVerify::where("seller_id",$user_info->id)->delete();
            Servicebenifit::where("seller_id",$user_info->id)->delete();
            Serviceadditional::where("seller_id",$user_info->id)->delete();
            Serviceinclude::where("seller_id",$user_info->id)->delete();
            ServiceCoupon::where("seller_id",$user_info->id)->delete();
            OnlineServiceFaq::where("seller_id",$user_info->id)->delete();
            EditServiceHistory::where("seller_id",$user_info->id)->delete();
            Day::where("seller_id",$user_info->id)->delete();
        
        
        }else{
            //delete buyer related data
            if(moduleExists("JobPost")){
                $jobs = BuyerJob::where('buyer_id',$user_info->id)->get();
                foreach($jobs as $job){
                    JobRequest::where('job_post_id',$job->id)->delete();
                    $job->delete();
                }
            }
            //delete jobs
        }
        
        
        
        $media_uploads = MediaUpload::where(["user_id" => $user_info->id,"type" => "web"])->get();
        foreach($media_uploads as $media){
            //delete media uploader records 
            MediaHelper::delete_user_media_image($media->id);
            $media->delete();
        }
        
        
        //report
        Accountdeactive::where("user_id",$user_info->id)->delete();

        
        // Wallet
         if(moduleExists("Wallet")){
                $wallet = Wallet::where('buyer_id',$user_info->id)->delete();
                WalletHistory::where('buyer_id',$user_info->id)->delete();
            }
        
        //delete order
        $orders = Order::where($user_column,$user_info->id)->get();
        foreach($orders as $order){
            OrderAdditional::where("order_id",$order->id)->delete();
            OrderCompleteDecline::where("order_id",$order->id)->delete();
            ExtraService::where("order_id",$order->id)->delete();
            $reports = Report::where($user_column,$user_info->id)->get();
            foreach($reports as $report){
                ReportChatMessage::where("report_id",$order->id)->delete();
                $report->delete();
            }
            $order->delete();
        }
        
        //review 
        Review::where($user_column,$user_info->id)->delete();
        ToDoList::where("user_id",$user_info->id)->delete();
       
        
       //delete support ticket
        $support_tickets =  SupportTicket::where($user_column,$user_info->id)->get();
        foreach($support_tickets as $ticket){
             //delete support ticket messages
            SupportTicketMessage::where("support_ticket_id",$ticket->id)->delete();
            $ticket->delete();
        }

        //delete live chat records
        //LiveChat
         if(moduleExists("LiveChat")){
            $wallet = LiveChatMessage::where($user_column,$user_info->id)->delete();
        }
        
        
        $user_info->delete();
        return redirect()->back()->with(FlashMsg::item_new('User delete success--'));
    }

    public function bulk_action(Request $request)
    {
        $all = User::find($request->ids);
        foreach ($all as $item) {
            $item->delete();
        }
        return response()->json(['status' => 'ok']);
    }

    public function email_verify_code($id=null){
       $user_details =  User::find($id);
       try {
            $message = get_static_option('admin_user_verification_code_message') ?? '';
            $message = str_replace(["@name","@verification_code"],[$user_details->name,$user_details->email_verify_token],$message);
            Mail::to($user_details->email)->send(new BasicMail([
                'subject' => get_static_option('admin_user_verification_code_subject') ?? __('Verification Code Send Success'),
                'message' => $message
            ]));
        }catch (\Exception $e){
             return redirect()->back()->with(FlashMsg::item_new( $e->getMessage()));
        }
       return redirect()->back()->with(FlashMsg::item_new(__('Verification Code Send Success')));
    }

    public function seller_email_verify_status($id=null){

        $user_details = User::select('id','email','email_verified')->where('id',$id)->first();

        User::where('id', $id)->update([
            'email_verified' => $user_details->user_status== Null ? 1 : 0
        ]);

       try {
            $message = __('Email Verified Success');
            Mail::to($user_details->email)->send(new BasicMail([
                'subject' => __('Email Verified'),
                'message' => $message
            ]));
        }catch (\Exception $e){
             return redirect()->back()->with(FlashMsg::item_new( $e->getMessage()));
        }
       return redirect()->back()->with(FlashMsg::item_new(__('Email Verified Success')));
    }


    public function otp_verify_code($id=null){
          $user=  User::find($id);

        if ($user){
            if (!empty(get_static_option('user_otp_expire_time'))){
                $add_second = get_static_option('user_otp_expire_time');
                $user->update([
                    'otp_code' => rand(123456, 999999),
                    'otp_expire_at' => now()->addSecond($add_second)
                ]);
            }else{
                $user->update([
                    'otp_code' => rand(123456, 999999),
                    'otp_expire_at' => now()->addMinutes(1)
                ]);
            }

            // opt sent to seller
            $otp_with_message = __('Login OTP is:');
            $message = $otp_with_message.' ' .$user->otp_code;

            try {
                $account_sid = getenv("TWILIO_SID");
                $auth_token = getenv("TWILIO_AUTH_TOKEN");
                $twilio_number = getenv("TWILIO_NUMBER");
                $client = new Client($account_sid, $auth_token);
                $client->messages->create($user->phone, [
                    'from' => $twilio_number,
                    'body' => $message]);
                info(__('SMS Sent Successfully.'));
            } catch (\Twilio\Exceptions\RestException $e) {
                $errorResponse = $this->handleTwilioError($e->getCode());
                return redirect()->back()->with(FlashMsg::item_delete(__($errorResponse['message'])));
            }
        }else{
            return redirect()->back()->with(FlashMsg::item_delete(__('user not found')));
        }

        return redirect()->back()->with(FlashMsg::item_new(__('OTP Code Send Success')));
    }

    private function handleTwilioError($errorCode)
    {
        $errorResponses = [
            30001 => ['status' => 429, 'message' => __("Queue overflow. Please try again later.")],
            30002 => ['status' => 403, 'message' => __("Your account is suspended. Please contact support.")],
            30003 => ['status' => 400, 'message' => __("Unreachable destination handset. Please check the number.")],
            30004 => ['status' => 403, 'message' => __("Message blocked. The recipient cannot receive messages.")],
            30005 => ['status' => 400, 'message' => __("Unknown destination handset. Please check the number.")],
            30006 => ['status' => 400, 'message' => __("The destination number is a landline or unreachable.")],
            30007 => ['status' => 403, 'message' => __("Carrier violation. The message content was flagged.")],
            30008 => ['status' => 500, 'message' => __("Unknown error. Please try again later.")],
            30009 => ['status' => 400, 'message' => __("Missing segment. One or more segments of your message were not received.")],
            30010 => ['status' => 402, 'message' => __("Message price exceeds max price. The price of your message exceeds the allowed maximum.")],
            63001 => ['status' => 401, 'message' => __("Channel authentication failed. Check your credentials.")],
            63002 => ['status' => 404, 'message' => __("Channel could not find From address. Verify the channel endpoint address.")],
            63003 => ['status' => 404, 'message' => __("Channel could not find To address. The destination address is incorrect.")],
            63005 => ['status' => 400, 'message' => __("Channel did not accept the given content. Please check the content format.")],
            63006 => ['status' => 400, 'message' => __("Could not format the given content for the channel.")],
            63007 => ['status' => 404, 'message' => __("Twilio could not find a Channel with the specified From address.")],
            63008 => ['status' => 500, 'message' => __("Could not execute the request due to misconfigured channel module. Please check your configuration.")],
            63009 => ['status' => 500, 'message' => __("Channel returned an error during execution. See the specific error for more information.")],
            63010 => ['status' => 500, 'message' => __("Channels - Twilio Internal error.")],
            63012 => ['status' => 500, 'message' => __("Channel returned an internal error that prevented request completion.")],
            63013 => ['status' => 403, 'message' => __("Message send failed due to violation of Channel provider's policy.")],
            63014 => ['status' => 403, 'message' => __("Message delivery failed because it was blocked by a user action.")],

            // Common Twilio error codes
            20001 => ['status' => 400, 'message' => __("The 'To' number is not a valid phone number.")],
            20002 => ['status' => 400, 'message' => __("The 'From' number is not a valid phone number.")],
            20003 => ['status' => 400, 'message' => __("The 'To' number is not reachable.")],
            20004 => ['status' => 400, 'message' => __("The 'To' number is not SMS-capable.")],
            21211 => ['status' => 400, 'message' => __("The phone number provided is not a valid phone number.")],
            21601 => ['status' => 400, 'message' => __("The message could not be delivered to the recipient.")],
            21602 => ['status' => 400, 'message' => __("The message failed because the recipient was unable to receive it.")],
            21603 => ['status' => 400, 'message' => __("The message was rejected by the recipient's carrier.")],
            21604 => ['status' => 400, 'message' => __("The message was rejected by the recipient's phone.")],
            21605 => ['status' => 400, 'message' => __("The message was not delivered due to a network issue.")],

            // Add any additional Twilio error codes and custom responses here
            'default' => ['status' => 500, 'message' => __("An error occurred. Please try again later.")]
        ];

        return $errorResponses[$errorCode] ?? $errorResponses['default'];
    }


    public function seller_otp_verify_status($id=null){

        $user = User::select('id','email', 'otp_code','otp_verified', 'otp_expire_at')->where('id',$id)->first();

        if($user){
            User::where('id', $id)->update([
                'otp_verified' => 1,
            ]);
        }

        try {
            $message = __('OTP Verified Success');
            Mail::to($user->email)->send(new BasicMail([
                'subject' => __('OTP Verified'),
                'message' => $message
            ]));
        }catch (\Exception $e){
            return redirect()->back()->with(FlashMsg::item_new( $e->getMessage()));
        }
        return redirect()->back()->with(FlashMsg::item_new(__('OTP Verified Success')));
    }

    // seller service location add
    public function sellerServiceLocation($id=null){
        $seller = User::find($id);
        $country_code = Country::where('status', 1)->pluck('country_code')->toArray();
        return view('backend.pages.seller-verify.seller-service-location', compact('seller', 'country_code'));
    }

    public function sellerServiceLocationAdd(Request $request){
        try {
            $seller = User::find($request->seller_id);
            if ($seller) {
                $seller->latitude = $request->latitude;
                $seller->longitude = $request->longitude;
                $seller->seller_address = $request->seller_address;
                $seller->save();
            }
        } catch (\Exception $e) {
            return redirect()->back()->with(FlashMsg::item_new( $e->getMessage()));
        }

        return redirect()->back()->with(FlashMsg::item_new(__('Seller Service Location Added Success')));
    }

    public function send_mail_to_single_user(Request $request){

        $this->validate($request,[
           'email' => 'required|email',
           'subject' => 'required',
           'message' => 'required',
        ]);

        try {
            Mail::to($request->email)->send(new BasicMail([
                'subject' => $request->subject,
                'message' => $request->message,
            ]));

        }catch (\Exception $ex){
            return redirect()->back()->with(FlashMsg::item_delete($ex->getMessage()));
        }

        return redirect()->back()->with([
            'msg' => __('Mail Send Success...'),
            'type' => 'success'
        ]);
    }
    
     public function deactive_user()
    {
        $all_user = Accountdeactive::all();
        return view('backend.frontend-user.all-deactive-user')->with(['all_user' => $all_user]);
    }

    public function getCity(Request $request)
    {
        $cities = ServiceCity::where('country_id', $request->country_id)->where('status', 1)->get();
        return response()->json([
            'status' => 'success',
            'cities' => $cities,
        ]);
    }

    public function getAarea(Request $request)
    {
        $areas = ServiceArea::where('service_city_id', $request->city_id)->where('status', 1)->get();
        return response()->json([
            'status' => 'success',
            'areas' => $areas,
        ]);
    }

    public function updateUserInfo (Request $request)
    {
        $this->validate($request, [
            'edit_name' => 'required'
        ]);

        $old_image = User::select('image')->where('email',$request->edit_email)->first();
        User::where('email', $request->edit_email)
            ->update([
                'name' => $request->edit_name,
                'phone' => $request->edit_phone,
                'country_id' => $request->edit_country,
                'service_city' => $request->edit_city,
                'service_area' => $request->edit_area,
                'address' => $request->edit_address,
                'tax_number' => $request->tax_number,
                'image' => $request->edit_image ?? $old_image->image,
            ]);
        return redirect()->back()->with(FlashMsg::item_new(__('User info update success')));
    }

    public function changeUserPassword(Request $request){
        $request->validate([
            'user_new_password_email' => 'required|email',
            'user_new_password' => 'required',
        ]);

        User::where('email',$request->user_new_password_email)->update(['password' => Hash::make($request->user_new_password)]);
        try {
            $message = get_static_option('admin_user_new_password_message') ?? '';
            $message = str_replace(["@new_password"],[$request->user_new_password],$message);
            Mail::to($request ->user_new_password_email)->send(new BasicMail([
                'subject' => get_static_option('admin_user_new_password_subject') ?? __('Password Change Success'),
                'message' => $message
            ]));
        } catch (\Exception $e) {
            return redirect()->back()->with(FlashMsg::item_new($e->getMessage()));
        }

        return redirect()->back()->with([
            'msg' => __('Mail Send Success...'),
            'type' => 'success'
        ]);
    }

}
