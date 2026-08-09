<?php

namespace App\Http\Controllers\Frontend;

use DB;
use Auth;
use App\User;
use App\Admin;
use App\Order;
use App\Report;
use App\Review;
use App\Country;
use App\Service;
use App\ServiceArea;
use App\ServiceCity;
use App\ExtraService;
use App\OrderInclude;
use App\SupportTicket;
use App\Mail\BasicMail;
use App\Mail\OrderMail;
use App\Accountdeactive;
use App\OrderAdditional;
use App\Helpers\FlashMsg;
use App\AdminNotification;
use App\ReportChatMessage;
use App\SupportDepartment;
use Illuminate\Support\Str;
use App\Services\SMSService;
use Illuminate\Http\Request;
use App\OrderCompleteDecline;
use App\SupportTicketMessage;
use App\Events\SupportMessage;
use App\Helpers\ModuleMetaData;
use App\OrderBookingDateTimeChange;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\Facades\Image;
use Modules\JobPost\Entities\BuyerJob;
use App\Helpers\PaymentGatewayRequestHelper;
use App\Notifications\TicketNotificationSeller;
use Xgenious\Paymentgateway\Facades\XgPaymentGateway;

class BuyerController extends Controller
{
    private const CANCEL_ROUTE = 'buyer.order.extra.service.payment.cancel';
    private const SUCCESS_ROUTE = 'buyer.order.extra.service.payment.success';
    public function __construct(){
        $this->middleware('inactiveuser');
    }

    public function buyerDashboard()
    {
        $buyer_id = Auth::guard('web')->user()->id;

        $pending_order = Order::where(['buyer_id'=>$buyer_id, 'status'=>0])->count();
        $active_order = Order::where(['buyer_id'=>$buyer_id, 'status'=>1])->count();
        $complete_order = Order::where(['buyer_id'=>$buyer_id, 'status'=>2])->count();
        $total_order = Order::where('buyer_id',$buyer_id)->count();
        $last_10_order = Order::where('buyer_id',$buyer_id)->take(10)->latest()->get();
        $last_6_order_dash_two = Order::where('buyer_id',$buyer_id)->take(6)->latest()->get();
        $last_10_tickets = SupportTicket::where('buyer_id',$buyer_id)->take(10)->latest()->get();

        return view('frontend.user.buyer.dashboard.dashboard',compact('pending_order','active_order','complete_order','total_order','last_10_order','last_10_tickets', 'last_6_order_dash_two'));
    }



    public function buyerOrders(Request $request)
    {
        if(!empty($request->order_id || $request->order_date|| $request->payment_status || $request->order_status || $request->total || $request->service_title || $request->seller_name)){


            $orders_query = Order::with('online_order_ticket')->where('buyer_id', Auth::guard('web')->user()->id)->where('job_post_id', NULL);


            // search by order ID
            if (!empty($request->order_id)){
                $orders_query->where('id', $request->order_id);
            }
            // search by order create date
            if (!empty($request->order_date)){
                $start_date = \Str::of($request->order_date)->before('to');
                $end_date = \Str::of($request->order_date)->after('to');
                $orders_query->whereBetween('created_at', [$start_date,$end_date]);
            }
            // search by payment status
            if (!empty($request->payment_status)){
                $orders_query->where('payment_status', $request->payment_status);
            }
            // search by order status
            if (!empty($request->order_status)){
                if ($request->order_status == 'pending'){
                    $orders_query->where('status', 0);
                }else{
                    $orders_query->where('status', $request->order_status);
                }

            }

            // search by order amount
            if (!empty($request->total)){
                $orders_query->where('payment_status', $request->total);
            }

            // search by service title
            if (!empty($request->service_title)){
                $service_id = Service::select('id', 'title')->where('title',  'LIKE', "%{$request->service_title}%")->pluck('id')->toArray();
                $orders_query->whereIn('service_id', $service_id);
            }

            // search by seller name
            if (!empty($request->seller_name)){
                $seller_id = User::select('id', 'name')->where('name',  'LIKE', "%{$request->seller_name}%")->pluck('id')->toArray();
                $orders_query->whereIn('seller_id', $seller_id);
            }

            $orders = $orders_query->latest()->paginate(10);

        }else{
            $orders = Order::with('online_order_ticket')
                ->where('buyer_id', Auth::guard('web')->user()->id)
                ->where('job_post_id', NULL)
                ->latest()->paginate(10);
        }

       return view('frontend.user.buyer.order.orders', compact('orders'));


    }

    public function buyerJobOrders(Request $request)
    {

        if(!empty($request->order_id || $request->order_date|| $request->payment_status || $request->order_status || $request->total || $request->job_title || $request->seller_name)){

             $orders_query = Order::with('online_order_ticket')
                    ->where('buyer_id', Auth::guard('web')->user()->id)
                    ->where('job_post_id', '!=', NULL);

            // search by order ID
            if (!empty($request->order_id)){
                $orders_query->where('id', $request->order_id);
            }
            // search by order create date
            if (!empty($request->order_date)){
                $start_date = \Str::of($request->order_date)->before('to');
                $end_date = \Str::of($request->order_date)->after('to');
                $orders_query->whereBetween('created_at', [$start_date,$end_date]);
            }
            // search by payment status
            if (!empty($request->payment_status)){
                $orders_query->where('payment_status', $request->payment_status);
            }
            // search by order status
            if (!empty($request->order_status)){
                if ($request->order_status == 'pending'){
                    $orders_query->where('status', 0);
                }else{
                    $orders_query->where('status', $request->order_status);
                }

            }

            // search by order amount
            if (!empty($request->total)){
                $orders_query->where('payment_status', $request->total);
            }

            // search by job title
            if (!empty($request->job_title)){
                $job_id = BuyerJob::select('id', 'title')->where('title',  'LIKE', "%{$request->job_title}%")->pluck('id')->toArray();
                $orders_query->whereIn('job_post_id', $job_id);
            }

            // search by seller name
            if (!empty($request->seller_name)){
                $seller_id = User::select('id', 'name')->where('name',  'LIKE', "%{$request->seller_name}%")->pluck('id')->toArray();
                $orders_query->whereIn('seller_id', $seller_id);
            }

            $orders = $orders_query->latest()->paginate(10);

        }else{
            $orders = Order::with('online_order_ticket')
                ->where('buyer_id', Auth::guard('web')->user()->id)
                ->where('job_post_id', '!=', NULL)
                ->latest()->paginate(10);
        }

        return view('frontend.user.buyer.order.orders', compact('orders'));
    }

    public function orderDetails($id=null)
    {
        $order_details = Order::with('seller')
            ->where('id',$id)
            ->where('buyer_id',Auth::guard('web')->user()->id)->first();
        $order_declines_history = OrderCompleteDecline::where('order_id',$id)->latest()->get();

        if(!is_null($order_details)){
            $order_includes = OrderInclude::where('order_id',$id)->get();
            $order_additionals = OrderAdditional::where('order_id',$id)->get();

            $buyer = Auth::guard('web')->user();
            $buyer_unread_notifications = $buyer->unreadNotifications()->count();
            if ($buyer_unread_notifications > 0) {
                foreach ($buyer->unreadNotifications as $notification) {
                    if (isset($notification->data['order_id']) && $order_details->id == $notification->data['order_id']) {
                        $notification = $buyer->Notifications->find($notification->id);
                        if($notification){
                            $notification->markAsRead();
                        }
                    }
                }
            }

            return view('frontend.user.buyer.order.order-details', compact('order_details','order_includes','order_additionals','order_declines_history'));
        }
        abort(404);
    }

    public function orderBookingDateChangeRequestApprove($order_id=null)
    {

        $buyer_id = Auth::guard('web')->user()->id;
        $order_booking_details = OrderBookingDateTimeChange::where('order_id', $order_id)->first();
        $order_info = Order::where('id', $order_id)->where('buyer_id', $buyer_id)->first();

        if (!$order_booking_details) {
            toastr_error(__('Date & Time change request not found.'));
            return redirect()->back();
        }

        $order_booking_details->update(['status' => 1]);

        // update order
        $order_update = [
            'date' => $order_booking_details->date ?? $order_info->date,
            'schedule' => $order_booking_details->schedule ?? $order_info->schedule,
        ];

        Order::where('id', $order_id)
            ->where('buyer_id', $buyer_id)
            ->update($order_update);

        $seller_email = User::select(['id','email'])->where('id',$order_info->seller_id)->first();

        //Send decline mail to seller and admin
        try {
            $message_body_admin = __('A buyer has been decline a request to booking date change an order. Order ID #'). $order_id.'</br>';
            $message_body_seller = __('Your request to booking date change an order has been decline by the buyer. Order ID #'). $order_id.'</br>';

            $message = $message_body_admin;
            // mail to admin
            Mail::to(get_static_option('site_global_email'))->queue(new BasicMail([
                'subject' => __('Order Booking Date Change Decline'),
                'message' => $message
            ]));

            $message = $message_body_seller;
            // mail to seller
            Mail::to($seller_email->email)->queue(new BasicMail([
                'subject' => __('Order Booking Date Change Decline'),
                'message' => $message
            ]));

             //send sms 

             $smsService=new SMSService();
             $seller_phone=User::select('phone')->where('id',$order_info->seller_id)->first();
             $message_for_seller=  __('Your request to booking date change an order has been decline by the buyer. Order ID #'). $order_id;
             $message_for_admin= __('A buyer has been decline a request to booking date change an order. Order ID #'). $order_id;
             $seller_phone=$seller_phone->phone;
             //send sms to seller
             $smsService->send_sms($seller_phone,  $message_for_seller);
 
            /*umber = '';
 
             $smsService->send_sms($number,  $message_for_seller);*/

 
             $admins = Admin::all(); 
             foreach ($admins as $admin)  // Send SMS to all super admin
             {
 
                 if ($admin->role == "Super Admin") {
                     
                     $smsService->send_sms($admin->phone,  $message_for_admin);
                     //msService->send_sms($number,  $message_for_admin);
                 }
             }


        } catch (\Exception $e) {
        }

        toastr_success(__('Date & Time change request approved.'));
        return redirect()->back();
    }

    public function orderBookingDateChangeRequestDecline(Request $request)
    {

        if(empty($request->decline_reason)){
            toastr_warning(__('You must write a short description to decline the request.'));
            return back();
        }

        $request->validate([
            'decline_reason'=>'min:20|max:1000'
        ]);

        OrderBookingDateTimeChange::where('order_id',$request->order_id)->update([
            'rejection_reason'=>$request->decline_reason,
            'status'=> 2,
        ]);

        $seller_email = User::select(['id','email'])->where('id',$request->seller_id)->first();

        //Send decline mail to seller and admin
        try {
            $message_body_admin = __('A buyer has been decline a request to booking date change an order. Order ID #'). $request->order_id.'</br>';
            $message_body_seller = __('Your request to booking date change an order has been decline by the buyer. Order ID #'). $request->order_id.'</br>';

            $message = $message_body_admin;
            // mail to admin
            Mail::to(get_static_option('site_global_email'))->queue(new BasicMail([
                'subject' => __('Order Booking Date Change Decline'),
                'message' => $message
            ]));

            $message = $message_body_seller;
            // mail to seller
            Mail::to($seller_email->email)->queue(new BasicMail([
                'subject' => __('Order Booking Date Change Decline'),
                'message' => $message
            ]));

            //send sms 
            $order_info=Order::where("id",$request->order_id)->first();
            $order_id=$request->order_id;

            $smsService=new SMSService();
            $seller_phone=User::select('phone')->where('id',$order_info->seller_id)->first();
            $message_for_seller=  __('Your request to booking date change an order has been decline by the buyer. Order ID #'). $order_id;
            $message_for_admin= __('A buyer has been decline a request to booking date change an order. Order ID #'). $order_id;
            
            //send sms to seller
            $seller_phone=$seller_phone->phone;
            
             
            $smsService->send_sms($seller_phone,  $message_for_seller);

            /*$number='

            $smsService->send_sms($number,  $message_for_seller);*/


            $admins = Admin::all(); 
            foreach ($admins as $admin)  // Send SMS to all super admin
            {

                if ($admin->role == "Super Admin") {
                    
                    $smsService->send_sms($admin->phone,  $message_for_admin);
                   //smsService->send_sms($number,  $message_for_admin);
                }
            }

        } catch (\Exception $e) {
            return redirect()->back()->with(FlashMsg::item_new($e->getMessage()));
        }

        toastr_warning(__('Order complete request decline successfully'));
        return back();
    }

    public function orderCompleteRequestApprove($id=null)
    {
        $orderDetails  = Order::where('id',$id)->first();
        $orderDetails->update(['order_complete_request'=>2,'status'=>2]);
        toastr_success(__('Order complete request successfully approved.'));
        \Session::flash('open_review_modal', 'yes');
        \Session::flash('CompleteOrderId', $id);
        \Session::flash('seller_id', $orderDetails->seller_id);
        \Session::flash('service_id', $orderDetails->service_id);
        return redirect()->back();
    }

    public function orderCancel($id=null)
    {
        Order::where('id',$id)->update(['payment_status'=>'','status'=>4]);
        toastr_success(__('Order successfully cancelled.'));
        return redirect()->back();
    }

    public function orderCompleteRequestDecline(Request $request)
    {

        if(empty($request->decline_reason)){
            toastr_warning(__('You must write a short description to decline the request.'));
            return back();
        }

        $request->validate([
            'decline_reason'=>'min:20|max:1000'
        ]);

        OrderCompleteDecline::where('order_id',$request->order_id)->update([
            'decline_reason'=>$request->decline_reason,
        ]);
        Order::where('id',$request->order_id)->update(['order_complete_request'=>3]);
        $seller_email = User::select(['id','email'])->where('id',$request->seller_id)->first();

        //Send decline mail to seller and admin
        try {
            $message_body_admin = __('A buyer has been decline a request to complete an order. Order ID #'). $request->order_id.'</br>';
            $message_body_seller = __('Your request to complete an order has been decline by the buyer. Order ID #'). $request->order_id.'</br>';
            $message = get_static_option('buyer_to_admin_extra_service_message');
            $message = str_replace(["@order_id"],[$request->order_id],$message);
            Mail::to(get_static_option('site_global_email'))->queue(new BasicMail([
                'subject' =>get_static_option('buyer_order_decline_subject') ?? __('Order Complete Decline'),
                'message' => $message
            ]));

            $message = get_static_option('buyer_order_decline_message');
            $message = str_replace(["@order_id"],[$request->order_id],$message);
            Mail::to($seller_email->email)->queue(new BasicMail([
                'subject' =>get_static_option('buyer_order_decline_subject') ?? __('Order Complete Decline'),
                'message' => $message
            ]));
            //send sms 

            $smsService=new SMSService();
            $seller_phone=User::select('phone')->where('id',$request->seller_id)->first();
            $message_for_seller=   __('Your request to complete an order has been decline by the buyer. Order ID #'). $request->order_id;
            $message_for_admin= __('A buyer has been decline a request to complete an order. Order ID #'). $request->order_id;
            
            //send sms to seller
            $seller_phone=$seller_phone->phone;
            
             
            $smsService->send_sms($seller_phone,  $message_for_seller);

            /*umber = '';

            $smsService->send_sms($number,  $message_for_seller);*/


            $admins = Admin::all(); 
            foreach ($admins as $admin)  // Send SMS to all super admin
            {

                if ($admin->role == "Super Admin") {
                    
                    $smsService->send_sms($admin->phone,  $message_for_admin);
                    //msService->send_sms($number,  $message_for_admin);
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->with(FlashMsg::item_new($e->getMessage()));
        }

        toastr_success(__('Order complete request decline successfully'));
        return back();
    }

    public function orderRequestDeclineHistory($id)
    {
        $order_id = $id;
        $decline_histories = OrderCompleteDecline::latest()->where('order_id',$id)->paginate(10);
        return view('frontend.user.buyer.order.decline-history',compact('decline_histories','order_id'));
    }

    //buyer report
    public function reportUs(Request $request)
    {
        $request->validate([
            'report' => 'required',
        ]);

        $buyer_id = Auth::guard()->check() ? Auth::guard('web')->user()->id : NULL;
        $is_report_exist = Report::where(['order_id'=>$request->order_id , 'report_from'=>'buyer'])->first();

        if($is_report_exist){
            toastr_error(__('Report Already Created For This Order'));
            return redirect()->back();
        }

        $report = Report::create([
            'order_id' => $request->order_id,
            'service_id' => $request->service_id,
            'seller_id' => $request->seller_id,
            'buyer_id' => $buyer_id,
            'report_from' => 'buyer',
            'report_to' => 'seller',
            'report' => $request->report,
        ]);

        $last_report_id = $report->id;

        try {
            $message = get_static_option('buyer_report_message');
            $message = str_replace(["@report_id"],[$last_report_id],$message);
            Mail::to(get_static_option('site_global_email'))->queue(new BasicMail([
                'subject' => get_static_option('buyer_report_subject') ?? __('Buyer New Report'),
                'message' => $message
            ]));
        } catch (\Exception $e) {
            return redirect()->back()->with(FlashMsg::item_new($e->getMessage()));
        }

        toastr_success(__('Report Send Success'));
        return redirect()->back();
    }

    public function reportList(Request $request)
    {

        if(!empty($request->order_id || $request->report_id || $request->report_date)){
            $reports_query = Report::where('buyer_id', Auth::guard('web')->user()->id);
            if (!empty($request->order_id)){
                $reports_query->where('order_id', $request->order_id);
            }
            if (!empty($request->report_id)){
                $reports_query->where('id', $request->report_id);
            }
            // search by date range
            if (!empty($request->report_date)){
                $start_date = \Str::of($request->report_date)->before('to');
                $end_date = \Str::of($request->report_date)->after('to');
                $reports_query->whereBetween('created_at', [$start_date,$end_date]);
            }
            $reports = $reports_query->paginate(10);

        }else{
            $reports = Report::where('buyer_id',Auth::guard('web')->user()->id)->paginate(10);
        }

        return view('frontend.user.buyer.report.report-list',compact('reports'));
    }

    public function chat_to_admin(Request $request, $report_id)
    {
        $buyer_id = Auth::guard('web')->user()->id;
        if($request->isMethod('post')){
            $this->validate($request,[
                'message' => 'required',
                'notify' => 'nullable|string',
                'attachment' => 'nullable|mimes:zip,jpg,jpeg,png,pdf,webp,xlsx, csv, xls,docx',
            ]);

            $ticket_info = ReportChatMessage::create([
                'report_id' => $report_id,
                'buyer_id' => $buyer_id,
                'message' => $request->message,
                'type' =>'buyer',
                'notify' => $request->send_notify_mail ? 'on' : 'off',
            ]);


            if ($request->hasFile('attachment')){
                $uploaded_file = $request->attachment;
                $file_extension = $uploaded_file->extension();
                $file_name =  pathinfo($uploaded_file->getClientOriginalName(),PATHINFO_FILENAME).time().'.'.$file_extension;

                // file scan start
                $file_extension = $uploaded_file->getClientOriginalExtension();
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $processed_image = Image::make($uploaded_file);
                    $image_default_width = $processed_image->width();
                    $image_default_height = $processed_image->height();

                    $processed_image->resize($image_default_width, $image_default_height, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $processed_image->save('assets/uploads/ticket/' . $file_name);
                }else{
                    $uploaded_file->move('assets/uploads/ticket',$file_name);
                } // file scan end

                $ticket_info->attachment = $file_name;
                $ticket_info->save();
            }

            //send mail to user 20202020
//            event(new SupportMessage($ticket_info));
            return redirect()->back()->with(FlashMsg::item_new(__('Message Send')));
        }
        $report_details = Report::where('id',$report_id)->where('buyer_id',$buyer_id)->first();
        $all_messages = ReportChatMessage::where('report_id',$report_id)
            ->where('buyer_id',$buyer_id)
            ->get();
        $q = $request->q ?? '';
        return view('frontend.user.buyer.report.report-chat',compact('report_details','all_messages','q'));

    }

    public function buyerProfile()
    {
            $cities = ServiceCity::where('status',1)->get();
            $areas = ServiceArea::where('status',1)->get();
            $countries = Country::where('status',1)->get();

        return view('frontend.user.buyer.profile.buyer-profile', compact('countries', 'areas' ,'cities'));
    }

    public function buyerProfileEdit(Request $request)
    {

        if ($request->isMethod('post')) {
            $user = Auth::guard('web')->user()->id;
            $request->validate([
                'name' => 'required|max:191',
                'email' => 'required|max:191|email|unique:users,email,'.$user,
                'phone' => 'required|max:191',
                'service_area' => 'required|max:191',
                'post_code' => 'required|max:191',
                'address' => 'required|max:191',
            ]);
            $old_image = User::select('image')->where('id',Auth::guard('web')->user()->id)->first();
            User::where('id', Auth::guard('web')->user()->id)
                ->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'image' => $request->image ?? $old_image->image,
                    'profile_background' => $request->profile_background ?? $old_image->profile_background,
                    'service_city' => $request->service_city,
                    'service_area' => $request->service_area,
                    'country_id' => $request->country,
                    'post_code' => $request->post_code,
                    'address' => $request->address,
                    'about' => $request->about,
                ]);
            toastr_success(__('Profile Update Success---'));
            return redirect()->back();
        }

 	$cities = ServiceCity::where('status',1)->take(500)->get();

        $areas = ServiceArea::where('status',1)->get();
        $countries = Country::where('status',1)->get();
        return view('frontend.user.buyer.profile.buyer-profile-edit',compact('cities','areas','countries'));
    }

    public function buyerAccountSetting(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'current_password' => 'required|min:6',
                'new_password' => 'required|min:6',
                'confirm_password' => 'required|min:6',
            ]);

            $buyer = User::where('id', Auth::user()->id)->first();

            if (Hash::check($request->current_password, $buyer->password)) {
                if ($request->new_password == $request->confirm_password) {
                    User::where('id', $buyer->id)->update([
                        'password' => Hash::make($request->new_password),
                        'password_changed_at' => now(),
                    ]);
                    toastr_success(__('Password Update Success---'));
                    return redirect()->back();
                }
                toastr_error(__('Password and Confirm Password not match---'));
                return redirect()->back();
            }
            toastr_error(__('Current Password is Wrong---'));
            return redirect()->back();
        }
        $user = Accountdeactive::select('user_id','status')->where('user_id', Auth::guard('web')->user()->id)->first();
        return view('frontend.user.buyer.profile.buyer-account-settings', compact('user'));
    }

    // buyer account Deactivate
    public function accountDeactive(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'reason' => 'required',
                'description' => 'required|max:150',
            ]);

            //buyer order status check
            $buyer_id =  Auth::guard('web')->user()->id;
            $all_orders = Order::where('buyer_id', $buyer_id)->where('status', 1)->count();
            if ($all_orders >1){
                toastr_error(__('Your have active orders. Please complete them before trying to delete your account.'));
                return redirect()->back();
            }else{

                //check buyer job post
                if(moduleExists('JobPost')) {
                    $buyer_all_job_post = BuyerJob::where('buyer_id', Auth::guard('web')->user()->id)->get();
                    if (!empty($buyer_all_job_post)) {
                        BuyerJob::where('buyer_id', Auth::guard('web')->user()->id)->update(['status' => 0]);
                    }
                }

                //Deactivate Account
                Accountdeactive::create([
                    'user_id' => Auth::guard('web')->user()->id,
                    'reason' => $request['reason'],
                    'description' => $request['description'],
                    'status' => 0,
                    'account_status' => 0,
                ]);
                toastr_error(__('Your Account Successfully Deactivate'));
                return redirect()->back();
            }
        }
    }

    // buyer account delete
    public function accountDelete(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'reason' => 'required',
                'description' => 'required|max:150',
            ]);

            $auth_buyer_id =  Auth::guard('web')->user()->id;

            //first seller order status check
            $all_orders = Order::where('buyer_id', $auth_buyer_id)->where('status', 1)->count();
            if ($all_orders >1){
                toastr_error(__('Your have active orders. Please complete them before trying to delete your account.'));
                return redirect()->back();
            }else{
                Accountdeactive::create([
                    'user_id' => Auth::guard('web')->user()->id,
                    'reason' => $request['reason'],
                    'description' => $request['description'],
                    'status' => 1,
                    'account_status' => 1,
                ]);
                if(moduleExists('JobPost')) {
                    BuyerJob::where('buyer_id', Auth::guard('web')->user()->id)->update(['status' => 0]);
                }
                toastr_error(__('Your Account Delete Successfully'));
            }

            return redirect()->route('buyer.logout');
        }
    }

    // buyer account Deactivate Cancel
    public function accountDeactiveCancel($id = null)
    {
        $account_details = Accountdeactive::where('user_id', $id)->first();

        if (!empty($account_details)){
            $account_details->delete();
        }

        // check buyer job post
        if(moduleExists('JobPost')) {
            $buyer_all_job_post = BuyerJob::where('buyer_id', Auth::guard('web')->user()->id)->get();
            if (!empty($buyer_all_job_post)) {
                BuyerJob::where('buyer_id', Auth::guard('web')->user()->id)->update(['status' => 1]);
            }
        }
        toastr_success(__('Your Account Successfully Active'));
        return redirect()->back();
    }

    public function buyerLogout()
    {
        Auth::logout();
        return redirect('/');
    }

    //support tickets
    public function allTickets(Request $request)
    {
        if(!empty($request->title || $request->order_id || $request->ticket_id || $request->ticket_date)){
            $tickets_query = SupportTicket::where('buyer_id', Auth::guard('web')->user()->id);
            if (!empty($request->title)){
                $tickets_query->where('title', 'LIKE', "%{$request->title}%");
            }
            if (!empty($request->order_id)){
                $tickets_query->where('order_id', $request->order_id);
            }
            if (!empty($request->ticket_id)){
                $tickets_query->where('id', $request->ticket_id);
            }

            // search by date range
            if (!empty($request->ticket_date)){
                $start_date = \Str::of($request->ticket_date)->before('to');
                $end_date = \Str::of($request->ticket_date)->after('to');
                $tickets_query->whereBetween('created_at', [$start_date,$end_date]);
            }

            $tickets = $tickets_query->orderBy('id','desc')->paginate(10);
        }else{
            $tickets = SupportTicket::where('buyer_id',Auth::guard('web')->user()->id)->orderBy('id','desc')->paginate(10);
        }

        $orders = Order::where('buyer_id', Auth::guard('web')->user()->id)
            ->where('payment_status', '!=','')
            ->whereNotNull('buyer_id')
            ->latest()->get();

        return view('frontend.user.buyer.support-ticket.all-tickets', compact('tickets','orders'));
    }

    //add new ticket
    public function addNewTicket(Request $request, $id=null)
    {
        if($request->isMethod('post')){

            $this->validate($request,[
                'title' => 'required|string|max:191',
                'subject' => 'required|string|max:191',
                'priority' => 'required|string|max:191',
                'order_id' => 'required',
            ],[
                'title.required' => __('title required'),
                'subject.required' =>  __('subject required'),
                'priority.required' =>  __('priority required')
            ]);

            if($request->order_id){
                $seller_id = Order::select('seller_id')->where('id',$request->order_id)->first();
            }

            SupportTicket::create([
                'title' => $request->title,
                'description' => $request->description,
                'subject' => $request->subject,
                'status' => 'open',
                'priority' => $request->priority,
                'buyer_id' => Auth::guard('web')->user()->id,
                'seller_id' => $seller_id->seller_id,
                'service_id' => $request->service_id,
                'order_id' => $request->order_id,
            ]);
            toastr_success(__('Ticket successfully created.'));
            $last_ticket_id = DB::getPdo()->lastInsertId();
            $last_ticket = SupportTicket::where('id',$last_ticket_id)->first();

            // send order ticket notification to seller
            $seller = User::where('id',$last_ticket->seller_id)->first();
            if($seller){
                $order_ticcket_message = __('You have a new order ticket');
                $seller ->notify(new TicketNotificationSeller($last_ticket_id , $seller_id, $last_ticket->seller_id,$order_ticcket_message ));
            }

            // admin notification add
            AdminNotification::create(['ticket_id' => $last_ticket_id]);

            //Send ticket mail to seller and admin
            try {
                $message = get_static_option('buyer_report_message');
                $message = str_replace(["@order_ticket_id","@report_id"],[$last_ticket_id],$message);
                Mail::to(get_static_option('site_global_email'))->queue(new BasicMail([
                    'subject' => get_static_option('buyer_report_subject') ?? __('New Order Ticket'),
                    'message' => $message
                ]));
                Mail::to($seller->email)->queue(new BasicMail([
                    'subject' => get_static_option('buyer_report_subject') ?? __('New Order Ticket'),
                    'message' => $message
                ]));
            } catch (\Exception $e) {
                return redirect()->back()->with(FlashMsg::item_new($e->getMessage()));
            }

            return redirect()->back();
        }

        $order = Order::select('id','service_id','seller_id')
            ->where('id',$id)
            ->where('buyer_id',Auth::guard('web')->user()->id)
            ->first();

        return view('frontend.user.buyer.support-ticket.add-new-ticket', compact('order'));
    }

    public function ticketDelete($id=null)
    {
        SupportTicket::find($id)->delete();
        toastr_error(__('Ticket Delete Success---'));
        return redirect()->back();
    }

    //view ticket
    public function view_ticket(Request $request,$id)
    {
        $ticket_details = SupportTicket::findOrFail($id);
        $all_messages = SupportTicketMessage::where(['support_ticket_id'=>$id])->get();
        $q = $request->q ?? '';
        foreach(Auth::guard('web')->user()->unreadNotifications as $notification){
           if (isset($notification->data['last_ticket_id'])){
               if($ticket_details->id == $notification->data['last_ticket_id']){
                   $Notification = Auth::guard('web')->user()->Notifications->find($notification->id);
                   if($Notification){
                       $Notification->markAsRead();
                   }
                   return view('frontend.user.buyer.support-ticket.view-ticket', compact('ticket_details','all_messages','q'));
               }
           }

           if (isset($notification->data['order_id'])){
               if($ticket_details->id == $notification->data['order_id']){
                   $Notification = Auth::guard('web')->user()->Notifications->find($notification->id);
                   if($Notification){
                       $Notification->markAsRead();
                   }
                   return view('frontend.user.buyer.support-ticket.view-ticket', compact('ticket_details','all_messages','q'));
               }
           }

        }
        return view('frontend.user.buyer.support-ticket.view-ticket', compact('ticket_details','all_messages','q'));
    }

    public function allClearMessage(Request $request)
    {
        if (Auth::guard('web')->user()->unreadNotifications->count() >=1){
            Auth::guard('web')->user()->Notifications->markAsRead();
            toastr_success(__('Clear all Notifications Success---'));
        }else{
            toastr_error(__('No Notifications Found'));
        }
        return redirect()->back();
    }


    //priority status
    public function priorityChange(Request $request)
    {
        SupportTicket::where('id',$request->ticket_id)->update(['priority'=>$request->priority]);
        toastr_success(__('Priority Change Success---'));
        return redirect()->back();
    }

    //change status
    public function statusChange($id=null)
    {
        $status = SupportTicket::find($id);
        if($status->status=='open'){
            $status = 'close';
        }else{
            $status = 'open';
        }
        SupportTicket::where('id',$id)->update(['status'=>$status]);
        toastr_success(__('Status Change Success---'));
        return redirect()->back();
    }

    //send message
    public function support_ticket_message(Request $request)
    {
        $this->validate($request,[
            'ticket_id' => 'required',
            'user_type' => 'required|string|max:191',
            'message' => 'required',
            'send_notify_mail' => 'nullable|string',
            'file' => 'nullable|mimes:zip,jpg,jpeg,png,pdf,webp,xlsx, csv, xls,docx',
        ]);

        $ticket_info = SupportTicketMessage::create([
            'support_ticket_id' => $request->ticket_id,
            'type' => $request->user_type,
            'message' => $request->message,
            'notify' => $request->send_notify_mail ? 'on' : 'off',
        ]);

        if ($request->hasFile('file')){
            $uploaded_file = $request->file;
            $file_extension = $uploaded_file->extension();
            $file_name =  pathinfo($uploaded_file->getClientOriginalName(),PATHINFO_FILENAME).time().'.'.$file_extension;

            // file scan start
            $file_extension = $uploaded_file->getClientOriginalExtension();
            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $processed_image = Image::make($uploaded_file);
                $image_default_width = $processed_image->width();
                $image_default_height = $processed_image->height();

                $processed_image->resize($image_default_width, $image_default_height, function ($constraint) {
                    $constraint->aspectRatio();
                });
                $processed_image->save('assets/uploads/ticket/' . $file_name);
            }else{
                $uploaded_file->move('assets/uploads/ticket',$file_name);
            } // file scan end

            $ticket_info->attachment = $file_name;
            $ticket_info->save();
        }

        //send mail to user
        event(new SupportMessage($ticket_info));
        return redirect()->back()->with(FlashMsg::item_new(__('Message Send')));
    }

    //service review add
    public function serviceReviewfromDashboard(Request $request)
    {

        $request->validate([
            'rating' => 'required',
            'message' => 'required',
        ]);

        $review_count = Review::where('order_id',$request->order_id)
            ->where('type', 1)
            ->where('buyer_id',Auth::guard('web')->user()->id)->first();

        if(!$review_count){
            $review = Review::create([
                'order_id' => $request->order_id,
                'service_id' => $request->service_id ?? 0,
                'seller_id' => $request->seller_id,
                'buyer_id' => Auth::guard()->check() ? Auth::guard('web')->user()->id : NULL,
                'rating' => $request->rating,
                'name' => Auth::guard()->check() ? Auth::guard('web')->user()->name : NULL,
                'email' => Auth::guard()->check() ? Auth::guard('web')->user()->email : NULL,
                'message' => $request->message,
                'type' => 1,
            ]);
            if($review){
                toastr_success(__('Review Added Success---'));
                return redirect()->back();
            }
        }
        toastr_error(__('You Can Not Send Review More Than One'));
        return redirect()->back();
    }

    public function extraServiceDecline(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'order_id' => 'required|integer',
        ]);
        
        ExtraService::where(['order_id' => $request->order_id,'id' => $request->id])->update([
            'payment_status' => 'decline',
            'status' => 2,
        ]);

        toastr_error(__('Decline Success'));
        return redirect()->back();
    }

    public function extraServiceAccept(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'order_id' => 'required|integer',
        ]);
        $extra_service_details = ExtraService::with('order')->find($request->id);
        //extraServiceAccept
        $selected_payment_gateway = $request->selected_payment_gateway;
        session()->put('order_id',$extra_service_details->id);

        //manual payment
        if($request->selected_payment_gateway === 'manual_payment') {
            $request->validate([
                'manual_payment_image' => 'required'
            ]);

            if($request->hasFile('manual_payment_image')){
                $manual_payment_image = $request->manual_payment_image;
                $img_ext = $manual_payment_image->extension();
                $manual_payment_image_name = 'manual_attachment_'.time().'.'.$img_ext;
                $manual_image_path = 'assets/uploads/manual-payment/';

                // file scan start
                $uploaded_file = $manual_payment_image;
                $file_extension = $uploaded_file->getClientOriginalExtension();
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $processed_image = Image::make($uploaded_file);
                    $image_default_width = $processed_image->width();
                    $image_default_height = $processed_image->height();

                    $processed_image->resize($image_default_width, $image_default_height, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $processed_image->save($manual_image_path . $manual_payment_image_name);
                }else{
                    $manual_payment_image->move($manual_image_path,$manual_payment_image_name);
                } // file scan end

                ExtraService::where('id',$extra_service_details->id)->update([
                    'manual_payment_gateway_image'=>$manual_payment_image_name,
                    'payment_gateway'=>'Manual Payment',
                    'status'=>1,
                ]);
            }

            //todo send mail to seller and buyer
            try {
                //send mail to seller
                $seller_details = User::select('name','email','phone')->find(optional($extra_service_details->order)->seller_id);
                $message = get_static_option('buyer_to_seller_extra_service_message');
                $message_for_seller= str_replace(["@seller_name","@order_id"],[$seller_details->name,$extra_service_details->order_id],$message);
                Mail::to($seller_details->email)->queue(new BasicMail([
                    'subject' => get_static_option('buyer_extra_service_subject') ?? __('Extra Service Accepted'),
                    'message' => $message_for_seller
                ]));

                $buyer_details = User::select('name','email','phone')->find(optional($extra_service_details->order)->buyer_id);
                //send mail to buyer
                $message = get_static_option('buyer_extra_service_message');
                $message_for_buyer = str_replace(["@buyer_name","@order_id"],[$buyer_details->name,$extra_service_details->order_id],$message);
                Mail::to($buyer_details->email)->queue(new BasicMail([
                    'subject' => get_static_option('buyer_extra_service_subject') ?? __('Extra Service Accepted'),
                    'message' => $message,
                ]));
                //send sms 

            $smsService=new SMSService();
            $seller_phone=$seller_details->phone;
            
            $message="Hello @seller_name .Buyer accepted the extra service added buy you in your order.Order id: @order_id";
            $message_for_seller= str_replace(["@seller_name","@order_id"],[$seller_details->name,$extra_service_details->order_id],$message);
            //send sms to seller
            $smsService->send_sms($seller_phone,  $message_for_seller);

            /*umber = '';

            $smsService->send_sms($number,  $message_for_seller);*/

            $buyer_phone=$buyer_details->phone;
            $message="Hello @buyer_name.You have accepted extra service&nbsp; added by seller in your order.Order id: @order_id";
            $message_for_buyer = str_replace(["@buyer_name","@order_id"],[$buyer_details->name,$extra_service_details->order_id],$message);
            
            //send sms to buyer
            $smsService->send_sms($buyer_phone, $message_for_buyer);


            $admins = Admin::all(); 
            $message="Hello Admin.@buyer_name have accepted extra service added by seller in order.Order id: @order_id";
            $message_for_admin= str_replace(["@buyer_name","@order_id"],[$buyer_details->name,$extra_service_details->order_id],$message);
            
            
            foreach ($admins as $admin)  // Send SMS to all super admin
            {

                if ($admin->role == "Super Admin") {
                    
                    $smsService->send_sms($admin->phone,  $message_for_admin);
                    //msService->send_sms($number,  $message_for_admin);
                }
            }

            }catch (\Exception $e){
                //handle error
            }
            toastr_success(__('Order Created Success'));
            return back();
        }



       //if not manual_payment
        if($request->selected_payment_gateway !== 'manual_payment') {
        $global_currency = get_static_option('site_global_currency');
        $usd_conversion_rate =  get_static_option('site_' . strtolower($global_currency) . '_to_usd_exchange_rate');
        $inr_exchange_rate = getenv('INR_EXCHANGE_RATE');
        $ngn_exchange_rate = getenv('NGN_EXCHANGE_RATE');
        $zar_exchange_rate = getenv('ZAR_EXCHANGE_RATE');
        $brl_exchange_rate = getenv('BRL_EXCHANGE_RATE');
        $idr_exchange_rate = getenv('IDR_EXCHANGE_RATE');
        $myr_exchange_rate = getenv('MYR_EXCHANGE_RATE');

        if(Auth::guard('web')->check()){
            $user_name = Auth::guard('web')->user()->name;
            $user_email = Auth::guard('web')->user()->email;
        }

        $get_service_id_from_last_order = Order::select('service_id')->where('id',$request->order_id)->first();
        $title = Str::limit(strip_tags(optional($get_service_id_from_last_order->service)->title),20);
        $description = sprintf(__('Order id #%1$d Email: %2$s, Name: %3$s'),$request->order_id,$user_email,$user_name);
        $order_id =  $request->order_id;
        $total = $extra_service_details->total;
        $extra_service_id = $extra_service_details->id;

        // update payment gateway
            $extraService = ExtraService::find($extra_service_id);
            if ($extraService) {
                $extraService->update([
                    'payment_gateway' => $selected_payment_gateway,
                ]);
            }

        }



        if ($selected_payment_gateway === 'paypal'){
            try {
                $paypal_mode = getenv('PAYPAL_MODE');
                $client_id = $paypal_mode === 'sandbox' ? getenv('PAYPAL_SANDBOX_CLIENT_ID') : getenv('PAYPAL_LIVE_CLIENT_ID');
                $client_secret = $paypal_mode === 'sandbox' ? getenv('PAYPAL_SANDBOX_CLIENT_SECRET') : getenv('PAYPAL_LIVE_CLIENT_SECRET');
                $app_id = $paypal_mode === 'sandbox' ? getenv('PAYPAL_SANDBOX_APP_ID') : getenv('PAYPAL_LIVE_APP_ID');

                $paypal = XgPaymentGateway::paypal();

                $paypal->setClientId($client_id); // provide sandbox id if payment env set to true, otherwise provide live credentials
                $paypal->setClientSecret($client_secret); // provide sandbox id if payment env set to true, otherwise provide live credentials
                $paypal->setAppId($app_id); // provide sandbox id if payment env set to true, otherwise provide live credentials
                $paypal->setCurrency($global_currency);
                $paypal->setEnv($paypal_mode === 'sandbox'); //env must set as boolean, string will not work
                $paypal->setExchangeRate($usd_conversion_rate); // if INR not set as currency


                $redirect_url = $paypal->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.mollie.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'mollie'){

            try {
                $mollie_key = getenv('MOLLIE_KEY');
                $mollie = XgPaymentGateway::mollie();
                $mollie->setApiKey($mollie_key);
                $mollie->setCurrency($global_currency);
                $mollie->setEnv(true); //env must set as boolean, string will not work
                $mollie->setExchangeRate($usd_conversion_rate); // if INR not set as currency

                $redirect_url = $mollie->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.paypal.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'paytm'){
            try {
                return PaymentGatewayRequestHelper::paytm()->charge_customer($this->buildPaymentArg($extra_service_details,route('buyer.order.extra.service.payment.paytm.ipn')));
                $paytm_merchant_id = getenv('PAYTM_MERCHANT_ID');
                $paytm_merchant_key = getenv('PAYTM_MERCHANT_KEY');
                $paytm_merchant_website = getenv('PAYTM_MERCHANT_WEBSITE') ?? 'WEBSTAGING';
                $paytm_channel = getenv('PAYTM_CHANNEL') ?? 'WEB';
                $paytm_industry_type = getenv('PAYTM_INDUSTRY_TYPE') ?? 'Retail';
                $paytm_env = getenv('PAYTM_ENVIRONMENT');

                $paytm = XgPaymentGateway::paytm();
                $paytm->setMerchantId($paytm_merchant_id);
                $paytm->setMerchantKey($paytm_merchant_key);
                $paytm->setMerchantWebsite($paytm_merchant_website);
                $paytm->setChannel($paytm_channel);
                $paytm->setIndustryType($paytm_industry_type);
                $paytm->setCurrency($global_currency);
                $paytm->setEnv($paytm_env === 'local'); // this must be type of boolean , string will not work
                $paytm->setExchangeRate($inr_exchange_rate); // if INR not set as currency

                $redirect_url = $paytm->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.paytm.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'stripe'){
            try {
                $stripe_public_key = getenv('STRIPE_PUBLIC_KEY');
                $stripe_secret_key = getenv('STRIPE_SECRET_KEY');
                $stripe = XgPaymentGateway::stripe();
                $stripe->setSecretKey($stripe_secret_key);
                $stripe->setPublicKey($stripe_public_key);
                $stripe->setCurrency($global_currency);
                $stripe->setEnv(true); //env must set as boolean, string will not work
                $stripe->setExchangeRate($usd_conversion_rate); // if INR not set as currency

                $redirect_url = $stripe->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.stripe.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'razorpay'){
            try {
                $razorpay_api_key = getenv('RAZORPAY_API_KEY');
                $razorpay_api_secret = getenv('RAZORPAY_API_SECRET');
                $razorpay = XgPaymentGateway::razorpay();
                $razorpay->setApiKey($razorpay_api_key);
                $razorpay->setApiSecret($razorpay_api_secret);
                $razorpay->setCurrency($global_currency);
                $razorpay->setEnv(true); //env must set as boolean, string will not work
                $razorpay->setExchangeRate($inr_exchange_rate); // if INR not set as currency

                $redirect_url = $razorpay->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.razorpay.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'flutterwave'){
            try {
                $flutterwave_public_key = getenv("FLW_PUBLIC_KEY");
                $flutterwave_secret_key = getenv("FLW_SECRET_KEY");
                $flutterwave_secret_hash = getenv("FLW_SECRET_HASH");

                $flutterwave = XgPaymentGateway::flutterwave();
                $flutterwave->setPublicKey($flutterwave_public_key);
                $flutterwave->setSecretKey($flutterwave_secret_key);
                $flutterwave->setCurrency($global_currency);
                $flutterwave->setEnv(true); //env must set as boolean, string will not work
                $flutterwave->setExchangeRate($usd_conversion_rate); // if NGN not set as currency


                $redirect_url = $flutterwave->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.flutterwave.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'paystack'){
            try {
                $paystack_public_key = getenv('PAYSTACK_PUBLIC_KEY');
                $paystack_secret_key = getenv('PAYSTACK_SECRET_KEY');
                $paystack_merchant_email = getenv('MERCHANT_EMAIL');

                $paystack = XgPaymentGateway::paystack();
                $paystack->setPublicKey($paystack_public_key);
                $paystack->setSecretKey($paystack_secret_key);
                $paystack->setMerchantEmail($paystack_merchant_email);
                $paystack->setCurrency($global_currency);
                $paystack->setEnv(true); //env must set as boolean, string will not work
                $paystack->setExchangeRate($ngn_exchange_rate); // if NGN not set as currency

                $redirect_url = $paystack->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.paystack.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'marcadopago'){
            try {

                $mercadopago_client_id = getenv('MERCADO_PAGO_CLIENT_ID');
                $mercadopago_client_secret = getenv('MERCADO_PAGO_CLIENT_SECRET');
                $mercadopago_env =  getenv('MERCADO_PAGO_TEST_MOD') === 'true';

                $marcadopago = XgPaymentGateway::marcadopago();
                $marcadopago->setClientId($mercadopago_client_id);
                $marcadopago->setClientSecret($mercadopago_client_secret);
                $marcadopago->setCurrency($global_currency);
                $marcadopago->setExchangeRate($brl_exchange_rate); // if BRL not set as currency, you must have to provide exchange rate for it
                $marcadopago->setEnv($mercadopago_env); //true mean sandbox mode , false means live mode


                $redirect_url = $marcadopago->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.marcadopago.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'instamojo'){
            try {
                $instamojo_client_id = getenv('INSTAMOJO_CLIENT_ID');
                $instamojo_client_secret = getenv('INSTAMOJO_CLIENT_SECRET');
                $instamojo_env = getenv('INSTAMOJO_TEST_MODE') === 'true';

                $instamojo = XgPaymentGateway::instamojo();
                $instamojo->setClientId($instamojo_client_id);
                $instamojo->setSecretKey($instamojo_client_secret);
                $instamojo->setCurrency($global_currency);
                $instamojo->setEnv($instamojo_env); //true mean sandbox mode , false means live mode //env must set as boolean, string will not work
                $instamojo->setExchangeRate($inr_exchange_rate); // if INR not set as currency

                $redirect_url = $instamojo->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.instamojo.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'cashfree'){
            try {

                $cashfree_env = getenv('CASHFREE_TEST_MODE') === 'true';
                $cashfree_app_id = getenv('CASHFREE_APP_ID');
                $cashfree_secret_key = getenv('CASHFREE_SECRET_KEY');

                $cashfree = XgPaymentGateway::cashfree();
                $cashfree->setAppId($cashfree_app_id);
                $cashfree->setSecretKey($cashfree_secret_key);
                $cashfree->setCurrency($global_currency);
                $cashfree->setEnv($cashfree_env); //true means sandbox, false means live , //env must set as boolean, string will not work
                $cashfree->setExchangeRate($inr_exchange_rate); // if INR not set as currency

                $redirect_url = $cashfree->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.cashfree.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'payfast'){
            try {
                $random_order_id_1 = Str::random(30);
                $random_order_id_2 = Str::random(30);


                $payfast_merchant_id = getenv('PF_MERCHANT_ID');
                $payfast_merchant_key = getenv('PF_MERCHANT_KEY');
                $payfast_passphrase = getenv('PAYFAST_PASSPHRASE');
                $payfast_env = getenv('PF_MERCHANT_ENV') === 'true';

                $payfast = XgPaymentGateway::payfast();
                $payfast->setMerchantId($payfast_merchant_id);
                $payfast->setMerchantKey($payfast_merchant_key);
                $payfast->setPassphrase($payfast_passphrase);
                $payfast->setCurrency($global_currency);
                $payfast->setEnv($payfast_env); //env must set as boolean, string will not work
                $payfast->setExchangeRate($zar_exchange_rate); // if ZAR not set as currency

                $redirect_url = $payfast->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.payfast.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$random_order_id_1.$extra_service_id.$random_order_id_2),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'midtrans'){
            try {
                $midtrans_env =  getenv('MIDTRANS_ENVAIRONTMENT') === 'true';
                $midtrans_server_key = getenv('MIDTRANS_SERVER_KEY');
                $midtrans_client_key = getenv('MIDTRANS_CLIENT_KEY');

                $midtrans = XgPaymentGateway::midtrans();
                $midtrans->setClientKey($midtrans_client_key);
                $midtrans->setServerKey($midtrans_server_key);
                $midtrans->setCurrency($global_currency);
                $midtrans->setEnv($midtrans_env); //true mean sandbox mode , false means live mode
                $midtrans->setExchangeRate($idr_exchange_rate); // if IDR not set as currency

                $redirect_url = $midtrans->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.midtrans.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'squareup'){
            try {
                $squareup_env =  !empty(get_static_option('squareup_test_mode'));
                $squareup_location_id = get_static_option('squareup_location_id');
                $squareup_access_token = get_static_option('squareup_access_token');
                $squareup_application_id = get_static_option('squareup_application_id');

                $squareup = XgPaymentGateway::squareup();
                $squareup->setLocationId($squareup_location_id);
                $squareup->setAccessToken($squareup_access_token);
                $squareup->setApplicationId($squareup_application_id);
                $squareup->setCurrency($global_currency);
                $squareup->setEnv($squareup_env);
                $squareup->setExchangeRate($usd_conversion_rate); // if USD not set as currency

                $redirect_url = $squareup->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.squareup.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'cinetpay'){
            try {
                $cinetpay_env =  !empty(get_static_option('cinetpay_test_mode'));
                $cinetpay_site_id = get_static_option('cinetpay_site_id');
                $cinetpay_app_key = get_static_option('cinetpay_app_key');

                $cinetpay = XgPaymentGateway::cinetpay();
                $cinetpay->setAppKey($cinetpay_app_key);
                $cinetpay->setSiteId($cinetpay_site_id);
                $cinetpay->setCurrency($global_currency);
                $cinetpay->setEnv($cinetpay_env);
                $cinetpay->setExchangeRate($usd_conversion_rate); // if ['XOF', 'XAF', 'CDF', 'GNF', 'USD'] not set as currency

                $redirect_url = $cinetpay->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.cinetpay.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }
        elseif ($selected_payment_gateway === 'paytabs'){
            try {
                $paytabs_env =  !empty(get_static_option('paytabs_test_mode'));
                $paytabs_region = get_static_option('paytabs_region');
                $paytabs_profile_id = get_static_option('paytabs_profile_id');
                $paytabs_server_key = get_static_option('paytabs_server_key');

                $paytabs = XgPaymentGateway::paytabs();
                $paytabs->setProfileId($paytabs_profile_id);
                $paytabs->setRegion($paytabs_region);
                $paytabs->setServerKey($paytabs_server_key);
                $paytabs->setCurrency($global_currency);
                $paytabs->setEnv($paytabs_env);
                $paytabs->setExchangeRate($usd_conversion_rate); // if ['AED','EGP','SAR','OMR','JOD','USD'] not set as currency

                $redirect_url = $paytabs->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.paytabs.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$extra_service_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }

        }
        elseif ($selected_payment_gateway === 'billplz'){
            try {
                $billplz_env =  !empty(get_static_option('billplz_test_mode'));
                $billplz_key =  get_static_option('billplz_key');
                $billplz_xsignature =  get_static_option('billplz_xsignature');
                $billplz_collection_name =  get_static_option('billplz_collection_name');

                $billplz = XgPaymentGateway::billplz();
                $billplz->setKey($billplz_key);
                $billplz->setVersion('v4');
                $billplz->setXsignature($billplz_xsignature);
                $billplz->setCollectionName($billplz_collection_name);
                $billplz->setCurrency($global_currency);
                $billplz->setEnv($billplz_env);
                $billplz->setExchangeRate($myr_exchange_rate); // if ['MYR'] not set as currency
                $random_order_id_1 = Str::random(30);
                $random_order_id_2 = Str::random(30);
                $new_order_id = $random_order_id_1.$extra_service_id.$random_order_id_2;

                $redirect_url = $billplz->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.billplz.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$new_order_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }

        }
        elseif ($selected_payment_gateway === 'zitopay'){
            try {
                $zitopay_env =  !empty(get_static_option('zitopay_test_mode'));
                $zitopay_username =  get_static_option('zitopay_username');

                $zitopay = XgPaymentGateway::zitopay();
                $zitopay->setUsername($zitopay_username);
                $zitopay->setCurrency($global_currency);
                $zitopay->setEnv($zitopay_env);
                $zitopay->setExchangeRate($usd_conversion_rate);

                $random_order_id_1 = Str::random(30);
                $random_order_id_2 = Str::random(30);
                $new_order_id = $random_order_id_1.$extra_service_id.$random_order_id_2;

                $redirect_url = $zitopay->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.zitopay.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$new_order_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;


            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }elseif ($selected_payment_gateway === 'kineticpay'){
            try {
                $kineticpay_env =  !empty(get_static_option('kineticpay_test_mode'));
                $kineticpay_username =  get_static_option('kineticpay_username');

                $kineticpay = XgPaymentGateway::kineticpay();
                $kineticpay->setMerchantKey($kineticpay_username);
                $kineticpay->setBank(request()->kineticpay_bank);
                $kineticpay->setCurrency($global_currency);
                $kineticpay->setEnv($kineticpay_env);
                $kineticpay->setExchangeRate($usd_conversion_rate);

                $random_order_id_1 = Str::random(30);
                $random_order_id_2 = Str::random(30);
                $new_order_id = $random_order_id_1.$extra_service_id.$random_order_id_2;

                $redirect_url = $kineticpay->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('buyer.order.extra.service.payment.kineticpay.ipn'),
                    'order_id' => $extra_service_id,
                    'extra_service_id' => $extra_service_id,
                    'track' => \Str::random(36),
                    'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_id),
                    'success_url' => route(self::SUCCESS_ROUTE,$new_order_id),
                    'email' => $user_email,
                    'name' => $user_name,
                    'payment_type' => 'order',
                ]);
                session()->put('extra_service_id', $extra_service_id);
                return $redirect_url;

            }catch (\Exception $e){
                toastr_error($e->getMessage());
                return back();
            }
        }else{
            //todo check qixer meta data for new payment gateway
            $module_meta =  new ModuleMetaData();
            $list = $module_meta->getAllPaymentGatewayList();
            if (in_array($selected_payment_gateway,$list)){
                //todo call the module payment gateway customerCharge function

                $customerChargeMethod =  $module_meta->getChargeCustomerMethodNameByPaymentGatewayName($selected_payment_gateway);
                try {
                    $returned_val = $customerChargeMethod($this->buildPaymentArg($extra_service_details,route('buyer.order.extra.service.payment.zitopay.ipn')));
                    
                    if(is_array($returned_val) && isset($returned_val['route'])){
					   $return_url = !empty($returned_val['route']) ? $returned_val['route'] : route('homepage');
						return redirect()->away($return_url); 
					}
					
                }catch (\Exception $e){
                    toastr_error( $e->getMessage());
                    return back();
                }
            }
        }

        toastr_error(__('something went wrong, try after sometime'));
        return redirect()->back();
    }

    private function buildPaymentArg($extra_service_details,$ipn_route){

        return [
            'amount' => $extra_service_details->total, // amount you want to charge from customer
            'title' => $extra_service_details->title, // payment title
            'description' => '', // payment description
            'ipn_url' => $ipn_route, //you will get payment response in this route
            'order_id' => $extra_service_details->id, // your order number
            'track' => \Str::random(36), // a random number to keep track of your payment
            'cancel_url' => route(self::CANCEL_ROUTE,$extra_service_details->id), //payment gateway will redirect here if the payment is failed
            'success_url' => route(self::SUCCESS_ROUTE,$extra_service_details->id), // payment gateway will redirect here after success
            'email' => $extra_service_details->order?->buyer?->email, // user email
            'name' => $extra_service_details->order?->buyer?->name, // user name
            'payment_type' => 'extra_service', // which kind of payment your are receving from customer
        ];
    }

    //notifications
    public function allNotification(){
        return view('frontend.user.buyer.notification.all-notification');
    }

}
