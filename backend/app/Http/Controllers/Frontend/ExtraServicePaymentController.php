<?php

namespace App\Http\Controllers\Frontend;

use App\ExtraService;
use App\Helpers\PaymentGatewayRequestHelper;
use App\Http\Controllers\Controller;
use App\Mail\BasicMail;
use App\Mail\OrderMail;
use App\Notifications\TicketNotificationSeller;
use App\Report;
use App\Review;
use DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\OrderAdditional;
use App\OrderInclude;
use App\ServiceCity;
use App\ServiceArea;
use App\Country;
use App\Order;
use App\User;
use Auth;
use App\SupportTicket;
use App\SupportDepartment;
use App\SupportTicketMessage;
use App\Events\SupportMessage;
use App\Helpers\FlashMsg;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Xgenious\Paymentgateway\Facades\XgPaymentGateway;

class ExtraServicePaymentController extends Controller
{
    private const CANCEL_ROUTE = 'buyer.order.extra.service.payment.cancel';
    private const SUCCESS_ROUTE = 'buyer.order.extra.service.payment.success';

    public function __construct(){
        $this->middleware('inactiveuser');
    }

    public function paypal_ipn(Request $request)
    {
       try{
           $paypal_mode = getenv('PAYPAL_MODE');
           $client_id = $paypal_mode === 'sandbox' ? getenv('PAYPAL_SANDBOX_CLIENT_ID') : getenv('PAYPAL_LIVE_CLIENT_ID');
           $client_secret = $paypal_mode === 'sandbox' ? getenv('PAYPAL_SANDBOX_CLIENT_SECRET') : getenv('PAYPAL_LIVE_CLIENT_SECRET');
           $app_id = $paypal_mode === 'sandbox' ? getenv('PAYPAL_SANDBOX_APP_ID') : getenv('PAYPAL_LIVE_APP_ID');
           $paypal = XgPaymentGateway::paypal();
           $paypal->setClientId($client_id);
           $paypal->setClientSecret($client_secret);
           $paypal->setEnv($paypal_mode === 'sandbox');
           $paypal->setAppId($app_id);
           $payment_data = $paypal->ipn_response();

           if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
               $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
               $this->send_order_mail($payment_data['order_id']);
               toastr_success(__('Payment successfully'));
               return redirect()->route('buyer.orders');
           }

           if(!empty(session()->get('extra_service_id'))){
               $this->update_status_cancel(session()->get('extra_service_id'));
           }

           return self::cancel_page();

       }catch (\Exception $e){
           //
       }
    }

    public function mollie_ipn()
    {

      try{
          $mollie_key = getenv('MOLLIE_KEY');
          $mollie = XgPaymentGateway::mollie();
          $mollie->setApiKey($mollie_key);
          $mollie->setEnv(true);
          $payment_data = $mollie->ipn_response();

          if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
              $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
              $this->send_order_mail($payment_data['order_id']);
              toastr_success(__('Payment successfully'));
              return redirect()->route('buyer.orders');
          }

          if(!empty(session()->get('extra_service_id'))){
              $this->update_status_cancel(session()->get('extra_service_id'));
          }

          return self::cancel_page();

      }catch (\Exception $e){
         //
      }
    }



    public function paytm_ipn(Request $request)
    {
       try{
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
           $paytm->setEnv($paytm_env === 'local'); //env must set as boolean, string will not work

           $payment_data = $paytm->ipn_response();

           if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
               $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
               $this->send_order_mail($payment_data['order_id']);
               toastr_success(__('Payment successfully'));
               return redirect()->route('buyer.orders');
           }

           if(!empty(session()->get('extra_service_id'))){
               $this->update_status_cancel(session()->get('extra_service_id'));
           }

           return self::cancel_page();

       }catch (\Exception $e){
           //
       }
    }

    public function stripe_ipn(Request $request)
    {
        try{
            $stripe_public_key = getenv('STRIPE_PUBLIC_KEY');
            $stripe_secret_key = getenv('STRIPE_SECRET_KEY');
            $stripe = XgPaymentGateway::stripe();
            $stripe->setSecretKey($stripe_secret_key);
            $stripe->setPublicKey($stripe_public_key);
            $stripe->setEnv(true); //env must set as boolean, string will not work

            $payment_data = $stripe->ipn_response();

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }
    public function razorpay_ipn(Request $request)
    {
        try{

            $razorpay_api_key = getenv('RAZORPAY_API_KEY');
            $razorpay_api_secret = getenv('RAZORPAY_API_SECRET');

            $razorpay = XgPaymentGateway::razorpay();
            $razorpay->setApiKey($razorpay_api_key);
            $razorpay->setApiSecret($razorpay_api_secret);

            $payment_data = $razorpay->ipn_response();

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }

    public function flutterwave_ipn(Request $request)
    {
        try{
            $flutterwave_public_key = getenv("FLW_PUBLIC_KEY");
            $flutterwave_secret_key = getenv("FLW_SECRET_KEY");
            $flutterwave_secret_hash = getenv("FLW_SECRET_HASH");

            $flutterwave = XgPaymentGateway::flutterwave();
            $flutterwave->setPublicKey($flutterwave_public_key);
            $flutterwave->setSecretKey($flutterwave_secret_key);
            $flutterwave->setEnv(true); //env must set as boolean, string will not work

            $payment_data = $flutterwave->ipn_response();

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }
    public function marcadopago_ipn(Request $request)
    {
        try{
            $mercadopago_client_id = getenv('MERCADO_PAGO_CLIENT_ID');
            $mercadopago_client_secret = getenv('MERCADO_PAGO_CLIENT_SECRET');
            $mercadopago_env =  getenv('MERCADO_PAGO_TEST_MOD') === 'true';

            $marcadopago = XgPaymentGateway::marcadopago();
            $marcadopago->setClientId($mercadopago_client_id);
            $marcadopago->setClientSecret($mercadopago_client_secret);
            $marcadopago->setEnv($mercadopago_env); ////true mean sandbox mode , false means live mode
            $payment_data = $marcadopago->ipn_response();

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }
    public function cashfree_ipn(Request $request)
    {
        try{
            $cashfree_env = getenv('CASHFREE_TEST_MODE') === 'true';
            $cashfree_app_id = getenv('CASHFREE_APP_ID');
            $cashfree_secret_key = getenv('CASHFREE_SECRET_KEY');

            $cashfree = XgPaymentGateway::cashfree();
            $cashfree->setAppId($cashfree_app_id);
            $cashfree->setSecretKey($cashfree_secret_key);

            $payment_data = $cashfree->ipn_response();

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }

    public function instamojo_ipn(Request $request)
    {
        try{
            $instamojo_client_id = getenv('INSTAMOJO_CLIENT_ID');
            $instamojo_client_secret = getenv('INSTAMOJO_CLIENT_SECRET');
            $instamojo_env = getenv('INSTAMOJO_TEST_MODE') === 'true';

            $instamojo = XgPaymentGateway::instamojo();
            $instamojo->setClientId($instamojo_client_id);
            $instamojo->setSecretKey($instamojo_client_secret);
            $instamojo->setEnv($instamojo_env); //true mean sandbox mode , false means live mode //env must set as boolean, string will not work
            $payment_data = $instamojo->ipn_response();


            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }

    public function payfast_ipn()
    {
        try{
            $payfast_merchant_id = getenv('PF_MERCHANT_ID');
            $payfast_merchant_key = getenv('PF_MERCHANT_KEY');
            $payfast_passphrase = getenv('PAYFAST_PASSPHRASE');
            $payfast_env = getenv('PAYFAST_PASSPHRASE') === 'true';
            $payfast = XgPaymentGateway::payfast();
            $payfast->setMerchantId($payfast_merchant_id);
            $payfast->setMerchantKey($payfast_merchant_key);
            $payfast->setPassphrase($payfast_passphrase);
            $payfast->setEnv($payfast_env); //env must set as boolean, string will not work

            $payment_data = $payfast->ipn_response();

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }
    public function midtrans_ipn()
    {
        try{
            $midtrans_env =  getenv('MIDTRANS_ENVAIRONTMENT') === 'true';
            $midtrans_server_key = getenv('MIDTRANS_SERVER_KEY');
            $midtrans_client_key = getenv('MIDTRANS_CLIENT_KEY');
            $midtrans = XgPaymentGateway::midtrans();
            $midtrans->setClientKey($midtrans_client_key);
            $midtrans->setServerKey($midtrans_server_key);
            $midtrans->setEnv($midtrans_env); //true mean sandbox mode , false means live mode

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }

    public function squareup_ipn(Request $request)
    {
        try{
            $squareup_env =  !empty(get_static_option('squareup_test_mode'));
            $squareup_location_id = get_static_option('cinetpay_site_id');
            $squareup_access_token = get_static_option('squareup_access_token');
            $squareup_application_id = get_static_option('squareup_application_id');

            $squareup = XgPaymentGateway::squareup();
            $squareup->setLocationId($squareup_location_id);
            $squareup->setAccessToken($squareup_access_token);
            $squareup->setApplicationId($squareup_application_id);
            $squareup->setEnv($squareup_env);

            $payment_data = $squareup->ipn_response();

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }
    public function cinetpay_ipn(Request $request)
    {
        try{
            $cinetpay_env =  !empty(get_static_option('cinetpay_test_mode'));
            $cinetpay_site_id = get_static_option('cinetpay_site_id');
            $cinetpay_app_key = get_static_option('cinetpay_app_key');

            $cinetpay = XgPaymentGateway::cinetpay();
            $cinetpay->setAppKey($cinetpay_app_key);
            $cinetpay->setSiteId($cinetpay_site_id);
            $cinetpay->setEnv($cinetpay_env);

            $payment_data = $cinetpay->ipn_response();

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }

    public function paytabs_ipn(Request $request)
    {
        try{
            $paytabs_env =  !empty(get_static_option('paytabs_test_mode'));
            $paytabs_region = get_static_option('paytabs_region');
            $paytabs_profile_id = get_static_option('paytabs_profile_id');
            $paytabs_server_key = get_static_option('paytabs_server_key');

            $paytabs = XgPaymentGateway::paytabs();
            $paytabs->setProfileId($paytabs_profile_id);
            $paytabs->setRegion($paytabs_region);
            $paytabs->setServerKey($paytabs_server_key);
            $paytabs->setEnv($paytabs_env);

            $payment_data = $paytabs->ipn_response();


            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }
    public function billplz_ipn(Request $request)
    {
        try{
            $billplz_env =  !empty(get_static_option('billplz_test_mode'));
            $billplz_key =  get_static_option('billplz_key');
            $billplz_xsignature =  get_static_option('billplz_xsignature');
            $billplz_collection_name =  get_static_option('billplz_collection_name');

            $billplz = XgPaymentGateway::billplz();
            $billplz->setKey($billplz_key);
            $billplz->setVersion('v4');
            $billplz->setXsignature($billplz_xsignature);
            $billplz->setCollectionName($billplz_collection_name);
            $billplz->setEnv($billplz_env);

            $payment_data = $billplz->ipn_response();

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }
    public function zitopay_ipn(Request $request)
    {
        try{
            $zitopay_env =  !empty(get_static_option('zitopay_test_mode'));
            $zitopay_username =  get_static_option('zitopay_username');

            $zitopay = XgPaymentGateway::zitopay();
            $zitopay->setUsername($zitopay_username);
            $zitopay->setEnv($zitopay_env);

            $payment_data = $zitopay->ipn_response();

            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }

    public function kineticpay_ipn(Request $request)
    {
        try{
            $kineticpay_env =  !empty(get_static_option('kineticpay_test_mode'));
            $kineticpay_username =  get_static_option('kineticpay_username');

            $kineticpay = XgPaymentGateway::kineticpay();
            $kineticpay->setMerchantKey($kineticpay_username);
            $kineticpay->setBank(request()->kineticpay_bank);
            $kineticpay->setEnv($kineticpay_env);

            $payment_data = $kineticpay->ipn_response();


            if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
                $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
                $this->send_order_mail($payment_data['order_id']);
                toastr_success(__('Payment successfully'));
                return redirect()->route('buyer.orders');
            }

            if(!empty(session()->get('extra_service_id'))){
                $this->update_status_cancel(session()->get('extra_service_id'));
            }

            return self::cancel_page();

        }catch (\Exception $e){
            //
        }
    }


    private  function PaymentUpdateHandle($payment_data){

        if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
            $extraService = ExtraService::find($payment_data['order_id']);
            $this->update_database($payment_data['order_id'], $payment_data['transaction_id']);
            $this->send_order_mail($extraService);
            return redirect()->route('buyer.orders');
        }
        abort(404);
    }


    private function update_database($extra_service_id,$transaction_id){
        ExtraService::find($extra_service_id)->update([
           'payment_status' => 'complete',
           'transaction_id' => $transaction_id,
           'status' => 1,
        ]);

    }

    private function send_order_mail($extra_service)
    {
        //todo send mail to seller and buyer
        try {
            //send mail to seller
            $seller_details = User::select('name','email')->find(optional($extra_service->order)->seller_id);
            $message = get_static_option('buyer_to_seller_extra_service_message');
            $message = str_replace(["@seller_name","@order_id"],[$seller_details->name,$extra_service->order_id],$message);

            Mail::to($seller_details->email)->send(new BasicMail([
                'subject' => __('Extra service added in your order #').$extra_service->order_id,
                'message' => $message
            ]));

            $buyer_details = User::select('name','email')->find(optional($extra_service->order)->buyer_id);
            //send mail to buyer
            $message = get_static_option('buyer_extra_service_message');
            $message = str_replace(["@buyer_name","@order_id"],[$buyer_details->name,$extra_service->order_id],$message);

            Mail::to($buyer_details->email)->send(new BasicMail([
                'subject' => __('Extra service added in your order #').$extra_service->order_id,
                'message' => $message
            ]));


        }catch (\Exception $e){
            //handle error
        }

    }

}
