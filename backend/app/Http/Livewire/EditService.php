<?php

namespace App\Http\Livewire;

use App\AdminCommission;
use App\Category;
use App\ChildCategory;
use App\EditServiceHistory;
use App\MetaData;
use App\OnlineServiceFaq;
use App\SellerVerify;
use App\Service;
use App\Serviceadditional;
use App\Servicebenifit;
use App\Serviceinclude;
use App\Subcategory;
use App\Tax;
use App\User;
use Carbon\Carbon;
use Dflydev\DotAccessData\Data;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
class EditService extends Component
{
    public $online_offline_show_hide = false;
    public function openDiv()
    {
        if ($this->online_offline_show_hide =! $this->online_offline_show_hide){

        }
    }

    protected $listener = [
        'documentLoaded' => 'handleDocumentLoaded',
    ];

    public  $service_edit_title, $service_edit_slug, $service_edit_video;

    public $edit_service_id, $online_service, $services, $meta, $category, $subcategory, $child_category, $is_service_online;
    public $current_tab = "service-info-tab";
    public $hideSubmitButton = true;
    public $disabled = true;

    public function rules() {
        // online service
        if($this->is_service_online == true ){
            return[
                'category' => 'required',
                'subcategory' => '',
                'child_category' => '',
                'services.title' => 'required|max:191|unique:services,id,'.$this->edit_service_id,
                'services.description' => 'required|min:150',
                'services.slug' => 'required',
                'services.video' => 'max:3000',
                'services.image' => '',
                'services.image_gallery' => '',

                // online service
                'services.online_service_price' => 'required|integer',
                'services.delivery_days' => 'required|integer',
                'services.revision' => 'integer',
                'services.price' => 'required',


                // service attributes
                'include_service_title' => 'max:191',
                'include_service_price' => '',
                'additional_service_title' => 'max:191',
                'additional_service_price' => '',
                'additional_service_image' => '',
                'benifits' => 'max:191',
                'faqs_title' => 'max:191',
                'faqs_description' => '',
                'meta.meta_title' => 'max:191',
                'meta.meta_tags' => '',
                'meta.meta_description' => '',
                'meta.facebook_meta_tags' => '',
                'meta.facebook_meta_description' => '',
                'meta.facebook_meta_image' => 'nullable',
                'meta.twitter_meta_tags' => 'nullable',
                'meta.twitter_meta_description' => 'nullable',
                'meta.twitter_meta_image' => 'nullable',
                'services.is_service_online' => 'nullable',

                // service attributes for mount
                'include_service_inputs.*' => 'nullable',
                'additional_service_inputs.*' => 'nullable',
                'service_benefit_inputs.*' => 'nullable',
                'online_service_faq.*' => 'nullable',
            ];

        }else{
            return[
                'category' => 'required',
                'subcategory' => 'nullable',
                'child_category' => 'nullable',
                'services.title' => 'required|max:191|unique:services,id,'.$this->edit_service_id,
                'services.description' => 'required|min:150',
                'services.slug' => 'required',
                'services.video' => 'max:3000',
                'services.image' => 'nullable',
                'services.image_gallery' => 'nullable',
                'services.price' => 'nullable',

                // online service
                'services.online_service_price' => 'nullable',
                'services.delivery_days' => 'nullable',
                'services.revision' => 'nullable',

                // service attributes
                'include_service_title' => 'max:191',
                'include_service_price' => 'nullable',
                'additional_service_title' => 'nullable',
                'additional_service_price' => 'nullable',
                'additional_service_image' => 'nullable',
                'benifits' => 'max:191',
                'faqs_title' => 'max:191',
                'faqs_description' => 'nullable',
                'meta.meta_title' => 'max:191',
                'meta.meta_tags' => 'nullable',
                'meta.meta_description' => 'nullable',
                'meta.facebook_meta_tags' => 'nullable',
                'meta.facebook_meta_description' => 'nullable',
                'meta.facebook_meta_image' => 'nullable',
                'meta.twitter_meta_tags' => 'nullable',
                'meta.twitter_meta_description' => 'nullable',
                'meta.twitter_meta_image' => 'nullable',
                'services.is_service_online' => 'nullable',

                // service attributes for mount
                'include_service_inputs.*' => 'nullable',
                'additional_service_inputs.*' => 'nullable',
                'service_benefit_inputs.*' => 'nullable',
                'online_service_faq.*' => 'nullable',

            ];
        }
    }

//=============== Service Include ===============
    public $include_service_title, $include_service_price, $include_service_inputs = [], $i_include = 1;
    public function addIncludeServices($i_include)
    {
        $i_include = $i_include + 1;
        $this->i_include = $i_include;
        $this->include_service_inputs[] = $i_include;
    }
    public function removeIncludeService($i_include)
    {
        // todo:: count this method array is getter then one then remove item from array
        if(count($this->include_service_inputs) <= 1) return ;
        unset($this->include_service_inputs[$i_include]);
    }
    //=============== Service Additional ===============
    public $additional_service_inputs = [], $i_additional = 1, $additional_service_price, $additional_service_image, $additional_service_title, $benifits, $faqs_title, $faqs_description;
    public function addAdditionalService($i_additional)
    {
        $i_additional = $i_additional + 1;
        $this->i_additional = $i_additional;
        $this->additional_service_inputs[] = $i_additional;

    }
    public function removeAdditionalService($i_additional)
    {
        // todo:: count this method array is getter then one then remove item from array
        if(count($this->additional_service_inputs) <= 1) return ;
        unset($this->additional_service_inputs[$i_additional]);
    }

   //=============== Service Benefit ===============
    public $service_benefit_inputs = [], $i_benefit = 0;
    public function addBenefit($i_benefit )
    {
        $i_benefit = $i_benefit + 1;
        $this->i_benefit = $i_benefit;
        $this->service_benefit_inputs[] = $i_benefit;
    }
    public function removeBenefit($i_benefit)
    {
        // todo:: count this method array is getter then one then remove item from array
        if(count($this->service_benefit_inputs) <= 1) return ;
        unset($this->service_benefit_inputs[$i_benefit], $this->benifits[$i_benefit]);
    }

    //=============== Service Faq ===============
    public  $online_service_faq = [], $i_faq = 1;
    public function addFaq($i_faq)
    {
        $i_faq = $i_faq + 1;
        $this->i_faq = $i_faq;
        $this->online_service_faq[] = $i_faq;
    }

    public function removeFaq($i_faq)
    {
        // todo:: count this method array is getter then one then remove item from array
        if(count($this->online_service_faq) <= 1) return ;
        unset($this->online_service_faq[$i_faq]);
    }


    // todo: mount is service id wise data show
    public function mount(){
        $id = $this->edit_service_id;
        $this->services  = Service::with('subcategory', 'childcategory')->findOrFail($id);
        $this->category  = $this->services->category_id;
        $this->subcategory  = $this->services->subcategory_id;
        $this->child_category  = $this->services->child_category_id;
        $this->meta = $this->services->metaData;
        $this->service_edit_title = $this->services->title;
        $this->service_edit_slug = $this->services->slug;
        $this->service_edit_video = $this->services->video;

        $this->include_service_inputs = ServiceInclude::where('service_id', $id)->get()->toArray();
        $this->additional_service_inputs = ServiceAdditional::where('service_id', $id)->get()->toArray();
        $this->service_benefit_inputs = ServiceBenifit::where('service_id', $id)->get()->toArray();
        $this->online_service_faq = OnlineServiceFaq::where('service_id', $id)->get()->toArray();

        // todo:: check $this->include_service_inputs this property if empty then call below method
        if(empty($this->include_service_inputs)){
            $this->addIncludeServices(1);
        }

        // additional
        if(empty($this->additional_service_inputs)){
            $this->addAdditionalService(1);
        }

        //benefit inputs
        if(empty($this->service_benefit_inputs)){
            $this->addBenefit(1);
        }
        // faq
        if(empty($this->online_service_faq)){
            $this->addFaq(1);
        }


    }

    public function render()
    {
        $id = $this->edit_service_id;
        $this->services = Service::with('subcategory', 'childcategory')->findOrFail($id);
        $categories = Category::where('status', 1)->get();
        $sub_categories = Subcategory::all();
        $child_categories = ChildCategory::all();
        return view('livewire.edit-service-two', compact('categories', 'sub_categories', 'child_categories'));
    }


    // category value get
    public $listeners = [
        "service_category_sub_cat_child_cat",
    ];



    public function service_category_sub_cat_child_cat($service){

        $this->category = current($service) ?? '';
        $this->subcategory = $service[1] ?? '';
        $this->child_category = $service[2] ?? '';
        $this->services->title = $service[3] ?? '';
        $this->services->video = $service[4] ?? '';
        $this->services->slug = $service[5] ?? '';
        $this->services->description = $service[6] ?? '';
        $this->services->image = $service[7] ?? '';
        $this->services->image_gallery = $service[8] ?? '';
        $this->services->is_service_all_cities = $service[9] ?? '';
        $this->services->is_service_online = $service[10] ?? '';

          $this->meta['facebook_meta_image'] = $service[11] ?? null;
          $this->meta['twitter_meta_image'] = $service[12] ?? null;
          $this->meta['meta_tags'] = $service[13] ?? null;

        if ($this->services->is_service_all_cities == null){
            $this->services->is_service_all_cities = 1;
        }


        $this->updateService();
    }


    public function updateService(){

        $this->validate();
        $id = $this->edit_service_id;
        $seller_country = User::select('id','country_id')->where('country_id',Auth::guard('web')->user()->country_id)->first();
        $country_tax = Tax::select('tax')->where('country_id',$seller_country->country_id)->first();

        $old_image = Service::select('image','image_gallery')->where('id',$id)->first();
        $old_slug_get = Service::select('slug')->where('id',$id)->first();
        $old_slug = Str::slug($old_slug_get);
        $service_slug = Str::slug($this->service_edit_slug);

        if(get_static_option('service_create_status_settings') == 'approved'){
            $service_status = 1;
        }else{
            $service_status = 0;
        }
        if (empty($this->child_category)){
            $this->child_category = null;
        }

        if (empty($this->subcategory)){
            $this->subcategory = null;
        }

        $all_include_service = [];
        $all_additional_service = [];
        $all_benifits_service = [];
        $all_faq_service = [];
        $service_total_price = 0;
        Service::where('id', $id)->update([
            'category_id' => $this->category,
            'subcategory_id' => $this->subcategory,
            'child_category_id' => $this->child_category,
            'title' => $this->service_edit_title ?? '',
            'slug' => $service_slug ?? $old_slug,
            'description' => $this->services['description'] ?? '',
            'image' => $this->services['image'] ?? '',
            'image_gallery' => $this->services['image_gallery'] ?? '',
            'video' => $this->service_edit_video ?? '',
            'tax' => $country_tax->tax ?? 0,
            'status' => $service_status,
            'is_service_all_cities' => $this->services['is_service_all_cities'] ?? 0,
        ]);
        $service_meta_update =  Service::findOrFail($id);



        $Metas = '';
        if (!empty($this->meta)){
            $Metas = [
                'meta_title'=> purify_html($this->meta['meta_title'] ?? ''),
                'meta_tags'=> purify_html($this->meta['meta_tags'] ?? ''),
                'meta_description'=> purify_html($this->meta['meta_description'] ?? ''),

                'facebook_meta_tags'=> purify_html($this->meta['facebook_meta_tags'] ?? ''),
                'facebook_meta_description'=> purify_html($this->meta['facebook_meta_description'] ?? ''),
                'facebook_meta_image'=> $this->meta['facebook_meta_image'] ?? '',

                'twitter_meta_tags'=> purify_html($this->meta['twitter_meta_tags'] ?? ''),
                'twitter_meta_description'=> purify_html($this->meta['twitter_meta_description'] ?? ''),
                'twitter_meta_image'=> $this->meta['twitter_meta_image'] ?? '',
            ];
        }

        DB::beginTransaction();
        try {
            if (is_null($service_meta_update->metaData()->first())){
                $service_meta_update->metaData()->Create($Metas);
            }else{
                $service_meta_update->metaData()->update($Metas);
            }
            DB::commit();
        }catch (\Throwable $th){
            DB::rollBack();
        }


        // service history create
        EditServiceHistory::create([
            'service_id' => $id,
            'seller_id' => Auth::guard('web')->user()->id,
            'service_title' => $this->services['title'] ?? '',
            'service_description' => $this->services['description'] ?? '',
        ]);


        if (!empty($id)){
            Serviceinclude::where('service_id', $id)->delete();
            Serviceadditional::where('service_id', $id)->delete();
            Servicebenifit::where('service_id', $id)->delete();
            OnlineServiceFaq::where('service_id', $id)->delete();
        }


        // todo: if service is online on change only update online service first check (1: true or return is_bool value == false)
        $check_service =  Service::where('id', $id)->firstOrfail();
        $check_service->is_service_online = is_null($this->is_service_online) ? 0 : $this->is_service_online;
        $check_service->save();


        // include start first check online service
        if ($check_service->is_service_online == 1 ) {

            if(empty($this->services['revision'])){
                $this->services['revision'] = 0;
            }
            Service::where('id', $id)->update([
                'price' => $this->services['price'] ?? 0,
                'delivery_days' => $this->services['delivery_days'] ?? 0,
                'revision' =>  $this->services['revision'],
                'is_service_online' => 1,
            ]);
            if ($check_service->is_service_online) {

                if (count($this->include_service_inputs) > 0) {
                    foreach ($this->include_service_inputs as $key => $value) {
                        if (!empty($value['include_service_title'][$key])) {
                            $all_include_service[] = [
                                'service_id' => $id,
                                'seller_id' => Auth::guard('web')->user()->id,
                                'include_service_title' => $value['include_service_title'] ?? null,
                                'include_service_price' => 0,
                                'include_service_quantity' => 0,
                            ];
                        }
                    }
                }
                Serviceinclude::insert($all_include_service);
            }
        }else {

            // include service add
            $only_prices = [];
            if (count($this->include_service_inputs) > 0){
                foreach ($this->include_service_inputs as $key => $value) {
                    if (!empty($value['include_service_title'][$key])) {
                        $only_prices[] = $value['include_service_price'] ?? 0;
                        $all_include_service[] = [
                            'service_id' => $id,
                            'seller_id' => Auth::guard('web')->user()->id,
                            'include_service_title' => $value['include_service_title'] ?? null,
                            'include_service_price' => $value['include_service_price'] ?? 0,
                            'include_service_quantity' => 1,
                        ];
                    }
                }
            }

            Serviceinclude::insert($all_include_service);
            // update service online to offline
            Service::where('id', $id)->update(
                [
                    'price' => array_sum($only_prices),
                    'is_service_online' => 0,
                    'revision' => 0,
                    'delivery_days' => 0,
                ]);
        }


        // additional update
        if (count($this->additional_service_inputs) > 0) {
            foreach ($this->additional_service_inputs as $key => $value) {
                if (!empty($value['additional_service_title'][$key])) {
                    $all_additional_service[] = [
                        'service_id' => $id,
                        'seller_id' => Auth::guard('web')->user()->id,
                        'additional_service_title' => $value['additional_service_title'] ?? null,
                        'additional_service_price' => $value['additional_service_price'] ?? null,
                        'additional_service_quantity' => 1,
                        'additional_service_image' => $value['additional_service_image'] ?? null,

                    ];
                }
            }
        }
        Serviceadditional::insert($all_additional_service);

        // benefits update
        if (count($this->service_benefit_inputs) > 0) {
            foreach ($this->service_benefit_inputs as $key => $value) {
                if (!empty($value['benifits'][$key])) {
                    $all_benifits_service[] = [
                        'service_id' => $id,
                        'seller_id' => Auth::guard('web')->user()->id,
                        'benifits' => $value['benifits'] ?? null,
                    ];
                }
            }
        }
        Servicebenifit::insert($all_benifits_service);

        // online service update
        if ($check_service->is_service_online) {
            if (count($this->online_service_faq) > 0) {
                foreach ($this->online_service_faq as $key => $value) {
                    if (!empty($value['title'][$key])) {
                        $all_faq_service[] = [
                            'service_id' => $id,
                            'seller_id' => Auth::guard('web')->user()->id,
                            'title' => $value['title'] ?? null,
                            'description' => $value['description'] ?? null,
                        ];
                    }
                }
            }
        }
        OnlineServiceFaq::insert($all_faq_service);

        $this->dispatchBrowserEvent('alert', toastr_success(__('Service updated Success---')));
        return redirect()->route('seller.services');

    }


}