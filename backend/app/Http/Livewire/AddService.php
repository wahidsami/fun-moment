<?php

namespace App\Http\Livewire;

use App\AdminCommission;
use App\Category;
use App\Mail\BasicMail;
use App\OnlineServiceFaq;
use App\SellerVerify;
use App\Service;
use App\Serviceadditional;
use App\Servicebenifit;
use App\Serviceinclude;
use App\Tax;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Auth;
use Illuminate\Support\Facades\Request;

class AddService extends Component
{

    public $online_offline_show_hide = false;
    public function openDiv()
    {
        if ($this->online_offline_show_hide =! $this->online_offline_show_hide){

        }
    }

    public $online_service, $services, $meta, $category, $subcategory, $child_category, $is_service_online;
    public $current_tab = "service-info-tab";
    public $hideSubmitButton = true;
    public $disabled = true;


    public function rules() {
        // online service
        if($this->is_service_online == true ){
            return[
                'category' => 'required',
                'services.title' => 'required|max:191|unique:services',
                'services.description' => 'required|min:150',
                'services.slug' => 'required',

                // online service
                'online_service.delivery_days' => 'required',
                'online_service.revision' => 'nullable',
                'online_service.online_service_price' => 'required',

            ];

        }else{
            return[
            'category' => 'required',
            'services.title' => 'required|max:191|unique:services',
            'services.description' => 'required|min:150',
            'services.slug' => 'required',
            ];
        }
    }

    // include service
    public $include_service_title, $include_service_price,$includes_services = [], $i_include = 1;
    public function addIncludeServices($i_include)
    {
        $i_include = $i_include + 1;
        $this->i_include = $i_include;
        $this->includes_services[] = $i_include;
        $this->current_tab = 'service-attribute-tab';
    }
    public function remove($i_include)
    {
        unset($this->includes_services[$i_include]);
    }

 //include service
    public $additional_service_title, $additional_service_price, $additional_service_image, $additional_service_inputs = [], $i_additional = 1;
    public function addAdditionalService($i_additional)
    {

        $i_additional = $i_additional + 1;
        $this->i_additional = $i_additional;
        $this->additional_service_inputs[] = $i_additional;
        $this->current_tab = 'service-attribute-tab';

    }
    public function removeAdditionalService($i_additional)
    {
        unset($this->additional_service_inputs[$i_additional]);
    }


// Benefit and faq
    public $service_benefit_inputs = [], $benifits, $faq_inputs = [], $faqs_description, $faqs_title, $i_benefit = 1, $i_faq =1;
    public function addBenefit($i_benefit)
    {
        $i_benefit = $i_benefit + 1;
        $this->i_benefit = $i_benefit;
        $this->service_benefit_inputs[] = $i_benefit;
        $this->current_tab = 'service-attribute-tab';

    }
    public function removeBenefit($i_benefit)
    {
        unset($this->service_benefit_inputs[$i_benefit]);
    }
    public function addFaq($i_faq)
    {
        $i_faq = $i_faq + 1;
        $this->i_additional = $i_faq;
        $this->faq_inputs[] = $i_faq;
        $this->current_tab = 'service-attribute-tab';
    }
    public function removeFaq($i_faq)
    {
        unset($this->faq_inputs[$i_faq]);
    }
 //==============================end


    public function render()
    {
        $categories = Category::where('status', 1)->get();
        return view('livewire.add-service-two', compact('categories'));
    }

    public function serviceResetForm()
    {
        $this->services = '';
        $this->meta = '';
        $this->category = '';
        $this->subcategory = '';
        $this->child_category = '';
        $this->online_service = '';
    }


    public function serviceStore()
    {

        $commissionGlobal = AdminCommission::first();
        if(moduleExists('Subscription') && $commissionGlobal->system_type == 'subscription' && empty(auth('web')->user()->subscribedSeller)){
            session()->flash('message', __('you must have to subscribe any of our package in order to start selling your services.'));
            return back();
        }

            //seller Verify check
            if (get_static_option('service_create_settings') == 'verified_seller'){
                $seller = SellerVerify::select('seller_id','status')->where('seller_id',Auth::guard('web')->user()->id)->first();
                $seller_verified_status = $seller?->status ?? 0;
                if($seller_verified_status != 1 ){
                    session()->flash('message', __('You are not verified. to add services you must have to verify your account first'));
                    return redirect()->back();
                }
            }
          
            //todo: check subscription step:1 commission type check step:2 subscription check step:3 subscription
            // type example(monthly, yearly, liveTime) Step:4 seller total service check to subscription service count

            //commission type check
            $commission = AdminCommission::first();
            if($commission->system_type == 'subscription'){
                if(subscriptionModuleExistsAndEnable('Subscription')){
                    $seller_subscription = \Modules\Subscription\Entities\SellerSubscription::where('seller_id', Auth::guard('web')->user()->id)->first();
                    if(is_null($seller_subscription)){
                        session()->flash('message', __('you have to subscribe a package to create services'));
                        return redirect()->back();
                    }
                    $seller_total_service_count = Service::where('seller_id', Auth::guard('web')->user()->id)->count();

                    if ($seller_subscription->type === 'monthly'){                      
                        // check seller connect,service,expire date
                        if ($seller_subscription->service == 0  && $seller_subscription->expire_date <= Carbon::now()){
                            session()->flash('message', __('Your Subscription is expired'));
                            return redirect()->back();
                        }elseif($seller_subscription->service < $seller_total_service_count){
                            session()->flash('message', __('Your subscription service limit is over!. please renew it'));
                            return redirect()->back();
                        }elseif ($seller_subscription->expire_date <= Carbon::now()){
                            session()->flash('message', __('Your Subscription is expired'));
                            return redirect()->back();
                        }
                    }elseif ($seller_subscription->type === 'yearly'){
                        // check seller connect,service,expire date
                        if ($seller_subscription->expire_date <= Carbon::now()){
                            session()->flash('message', __('Your Subscription is expired'));
                            return redirect()->back();
                        }elseif ($seller_subscription->service < $seller_total_service_count){
                            session()->flash('message', __('Your subscription service limit is over!. please renew it'));
                             return redirect()->back();
                         }elseif ($seller_subscription->expire_date <= Carbon::now()){
                             session()->flash('message', __('Your Subscription is expired'));
                             return redirect()->back();
                         }
                     }
                }
            }

           $this->validate();

            $seller_country = User::select('id', 'country_id')->where('country_id', Auth::guard('web')->user()->country_id)->first();
            $country_tax = Tax::select('tax')->where('country_id', $seller_country->country_id)->first();

            if(get_static_option('service_create_status_settings') == 'approved'){
                $service_status = 1;
            }else{
                $service_status = 0;
            }

            $service_title_slug = Str::slug($this->services['slug'], '-',null);

            $service = new Service();
            $service->category_id = $this->category;
            $service->subcategory_id = $this->subcategory;
            $service->child_category_id = $this->child_category;
            $service->title = $this->services['title'] ?? '';
            $service->slug = $service_title_slug ?? '';
            $service->description = $this->services['description'] ?? '';
            $service->image = $this->services['image'] ?? '';
            $service->image_gallery = $this->services['image_gallery'] ?? '';
            $service->video = $this->services['video'] ?? '';
            $service->seller_id = Auth::guard('web')->user()->id;
            $service->service_city_id = Auth::guard('web')->user()->service_city;
            $service->service_area_id = Auth::guard('web')->user()->service_area;
            $service->status = $service_status;
            $service->tax = $country_tax->tax ?? 0;
            $service->is_service_all_cities = $this->services['is_service_all_cities'] ?? 0;

            $Metas = '';
            if (!empty($this->meta)){
                $Metas = [
                    'meta_title' => purify_html($this->meta['meta_title'] ?? ''),
                    'meta_tags' => purify_html($this->meta['meta_tags'] ?? ''),
                    'meta_description' => purify_html($this->meta['meta_description'] ?? ''),

                    'facebook_meta_tags' => purify_html($this->meta['facebook_meta_tags'] ?? ''),
                    'facebook_meta_description' => purify_html($this->meta['facebook_meta_description'] ?? ''),
                    'facebook_meta_image' => $this->meta['facebook_meta_image'] ?? 0,

                    'twitter_meta_tags' => purify_html($this->meta['twitter_meta_tags'] ?? ''),
                    'twitter_meta_description' => purify_html($this->meta['twitter_meta_description'] ?? ''),
                    'twitter_meta_image' => $this->meta['twitter_meta_image'] ?? '',
                ];
            }


            $service->save();
            $last_service_id = DB::getPdo()->lastInsertId();


            if (!empty($this->meta)){
                $service->metaData()->create($Metas);
            }

            // send mail
            try {
                $message = get_static_option('service_approve_message');
                $message = str_replace(["@service_id"], [$last_service_id], $message);
                Mail::to(get_static_option('site_global_email'))->send(new BasicMail([
                    'subject' => get_static_option('service_approve_subject') ?? __('New Service Approve Request'),
                    'message' => $message
                ]));
            } catch (\Exception $e) {
                //
            }

           $service = Service::findOrFail($last_service_id);

           $all_include_service = [];
           $all_additional_service = [];
           $all_benifits_service = [];
           $online_service_faqs = [];
           $service_total_price = 0;

        if ($this->is_service_online === true) {
            Service::where('id', $service->id)->update([
                'price' => $this->online_service['online_service_price'] ?? 0,
                'delivery_days' => $this->online_service['delivery_days'] ?? 0,
                'revision' => $this->online_service['revision'] ?? 0,
                'is_service_online' => 1,
            ]);
            if ($this->is_service_online === true) {
                if (!empty($this->include_service_title)) {
                    foreach ($this->include_service_title as $key => $value) {
                        $all_include_service[] = [
                            'service_id' => $service->id,
                            'seller_id' => Auth::guard('web')->user()->id,
                            'include_service_title' => $this->include_service_title[$key],
                            'include_service_price' => 0,
                            'include_service_quantity' => 0,
                        ];
                    }
                }
                Serviceinclude::insert($all_include_service);
            }
           }else {
          // include service add
            if (!empty($this->include_service_title)){
                foreach ($this->include_service_title as $key => $value) {
               
                    $all_include_service[] = [
                        'service_id' => $service->id,
                        'seller_id' => Auth::guard('web')->user()->id,
                        'include_service_title' => $this->include_service_title[$key] ?? null,
                        'include_service_price' => $this->include_service_price[$key] ?? 0,
                        'include_service_quantity' => 1,
                    ];

                    $service_total_price += $this->include_service_price[$key] * 1;
                }
            }
            Serviceinclude::insert($all_include_service);
            Service::where('id', $service->id)->update(['price' => $service_total_price]);
        }


        // additional service add
        if (!empty($this->additional_service_title)) {
            foreach ($this->additional_service_title as $key => $value) {
                if (!empty($this->additional_service_title[$key])) {
                    $all_additional_service[] = [
                        'service_id' => $service->id,
                        'seller_id' => Auth::guard('web')->user()->id,
                        'additional_service_title' => $this->additional_service_title[$key] ?? null,
                        'additional_service_price' => $this->additional_service_price[$key] ?? null,
                        'additional_service_quantity' => 1,
                        'additional_service_image' => $this->additional_service_image[$key] ?? null,
                    ];
                }
            }
        }
        Serviceadditional::insert($all_additional_service);

        // benefits add
         if (!empty($this->benifits)) {
             foreach ($this->benifits as $key => $value) {
                 $all_benifits_service[] = [
                     'service_id' =>$service->id,
                     'seller_id' => Auth::guard('web')->user()->id,
                     'benifits' => $this->benifits[$key] ?? null,
                 ];
             }
         }
        Servicebenifit::insert($all_benifits_service);

        if (!empty($this->faqs_title)) {
            foreach ($this->faqs_title as $key => $value) {
                if (!empty($this->faqs_title[$key])) {
                    $online_service_faqs[] = [
                        'service_id' => $service->id,
                        'seller_id' => Auth::guard('web')->user()->id,
                        'title' => $this->faqs_title[$key] ?? null,
                        'description' => $this->faqs_description[$key] ?? null,
                    ];
                }
            }
        }
        OnlineServiceFaq::insert($online_service_faqs);

        // if Subscription system remove service in seller subscription service
        if($commission->system_type == 'subscription') {
            if (subscriptionModuleExistsAndEnable('Subscription')) {
                \Modules\Subscription\Entities\SellerSubscription::where('seller_id', Auth::guard('web')->user()->id)->update([
                    'service' => DB::raw(sprintf("service - %s", (int)strip_tags(1))),
                ]);
            }
        }

        // reset service form and message show
        $this->serviceResetForm();
        $this->dispatchBrowserEvent('alert', toastr_success(__('Service Added Success---')));
        return redirect()->route('seller.services');

    }

}
