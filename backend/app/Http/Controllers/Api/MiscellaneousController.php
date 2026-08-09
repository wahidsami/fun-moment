<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Language;
use App\AmountSettings;
use App\AdminCommission;
use App\Accountdeactive;
use App\Service;
use App\Order;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\Admin\ModuleFlagService;
use Xgenious\Paymentgateway\Facades\XgPaymentGateway;

class MiscellaneousController extends Controller
{
    public function __construct(private readonly ModuleFlagService $moduleFlagService)
    {
    }

    public function modulePermission(){
        $payload = $this->moduleFlagService->payload();

        return response()->success([
            'permissions'=> [
                "JobPost" => moduleExists("JobPost"),
                "LiveChat" => moduleExists("LiveChat"),
                "Subscription" => moduleExists("Subscription"),
                "Wallet" => moduleExists("Wallet"),
            ],
            'modules' => $payload['modules'],
            'feature_flags' => $payload['feature_flags'],
        ]);
    }
    public function currencyInfo(){

        return response()->success([
            'currency'=> [ 
                "symbol" => site_currency_symbol(),
                "code" => get_static_option('site_global_currency'),
                "position" => get_static_option('site_currency_symbol_position')
            ],
        ]);
    }
    
    public function amountSettings(){
        
        $amount_settings = AmountSettings::first();
        
        return response()->success([
            'amount_settings'=> [ 
                "min_amount" => $amount_settings->min_amount,
                "max_amount" => $amount_settings->max_amount
            ],
        ]);
    }

    public function adminCommissionType(){
        $admin_commission = AdminCommission::first();
        $commission_amount = $admin_commission->system_type;
        return response()->success([
               "admin_commission" => $commission_amount
            ]);
    }

    public function accountDelete(Request $request){
        
        $validator = Validator::make($request->all(), [
           'reason' => 'required',
           'description' => 'required',
           'password' => 'required',
       ]);

       if ($validator->fails()) {
            return response()->json([
               'error' => true,
               'message' => $validator->errors()
           ],422);
       }
       
       
       $userDetails = auth('sanctum')->check() ? auth('sanctum')->user() : null;
       
       if(is_null($userDetails)){
           return response()->error([
               'message'=> __('Account Delete Success')
           ]);
       }
       
       if (Hash::check($request->password, $userDetails->password)) {
           
           $auth_seller_id = $userDetails->id;
           //first seller order status check
           $all_orders = Order::where('seller_id', $auth_seller_id)->where('status', 1)->count();
           
               if ($all_orders > 1) {
                   return response()->error(['message'=> __('Your have active orders. Please complete them before trying to delete your account.')]);
           } else {
               Accountdeactive::create([
                   'user_id' => $auth_seller_id,
                   'reason' => $request->reason,
                   'description' => $request->description,
                   'status' => 1,
                   'account_status' => 1,
               ]);
               
               Service::where('seller_id', $auth_seller_id)->update(['status' => 0]);
           }
           
           return response()->success([
               'message'=> __('Your Account Delete Successfully')
           ]);
       }else{
           return response()->error([
               'message'=> __('password does not matched')
           ]);
       }
       
       return response()->error([
           'message'=> __('something went wrong, try after sometime')
       ]);
   }

    
}
