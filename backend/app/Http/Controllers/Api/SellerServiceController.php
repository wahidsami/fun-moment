<?php

namespace App\Http\Controllers\Api;

use App\Actions\Media\MediaHelper;
use App\Category;
use App\ChildCategory;
use App\EditServiceHistory;
use App\Http\Controllers\Controller;
use App\Mail\BasicMail;
use App\OnlineServiceFaq;
use App\Service;
use App\Serviceadditional;
use App\Servicebenifit;
use App\Serviceinclude;
use App\Subcategory;
use App\Tax;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SellerServiceController extends Controller
{
    public function myService()
    {
        $services = Service::select(['id','title','image','price','is_service_online','view','is_service_on','status'])
            ->with('reviews_for_mobile')
            ->withCount('reviews','pendingOrder','completeOrder','cancelOrder')
            ->where('seller_id', Auth::guard('sanctum')->user()->id)
            ->latest()->paginate(10)
            ->withQueryString();

        if(!empty($services)){
            $service_image=[];
            foreach($services as $service){
                $service_image[]= get_attachment_image_by_id($service->image);
            }
            return response()->success([
                'my_services'=> $services,
                'service_image'=> $service_image,
            ]);
        }else{
            return response()->error([
                'my_services' => __('No service found'),
            ]);
        }
    }


    public function subCategoryByCategory($category)
    {
        if($category){
            $sub_category = Subcategory::where('category_id',$category)->get();
            return response()->success([
                'sub_category' => $sub_category,
            ]);
        }else{
            return response()->error([
                'message' => __('Category not found'),
            ]);
        }
    }

    public function childCategoryBySubcategory($sub_category)
    {
        if($sub_category){
            $child_category = ChildCategory::where('sub_category_id',$sub_category)->get();
            return response()->success([
                'child_category' => $child_category,
            ]);
        }else{
            return response()->error([
                'message' => __('Subcategory not found'),
            ]);
        }
    }
    
     public function ServiceOnOff($id)
    {
        $seller_id = Auth::guard('sanctum')->id();
        $service = Service::where('id', $id)->where('seller_id', $seller_id)->first();
        if (empty($service)) {
            return response()->error(['msg' => __('Service not found or unauthorized.')]);
        }

        $new_status = ($service->is_service_on == 1) ? 0 : 1;
        $service->update(['is_service_on' => $new_status]);
        $msg = ($new_status == 1) ? __('Service On Successfully.') : __('Service Off Successfully.');
        return response()->success(['msg' => $msg]);
    }

    public function addService(Request $request)
    {
        if ($request->isMethod('post')) {
            $user = Auth::guard('sanctum')->user();
            $city_id = $request->input('service_city_id') ?: $user->service_city;
            if (empty($city_id) || !\App\ServiceCity::where('id', $city_id)->exists()) {
                $defaultCity = \App\ServiceCity::where('status', 1)->first() ?: \App\ServiceCity::first();
                $city_id = $defaultCity ? $defaultCity->id : null;
            }
            if (empty($city_id)) {
                return response()->json([
                    'message' => __('Please complete your service city/location in your profile settings before creating a service.'),
                    'errors' => [
                        'service_city' => [__('Please complete your service city/location in your profile settings before creating a service.')]
                    ]
                ], 422);
            }

            $request->validate([
                'category_id' => 'required',
                'title' => 'required|max:191',
                'description' => 'required|min:10',
                'price' => 'required|numeric|min:0',
            ]);
            
            $seller_country_id = $user->country_id;
            $country_tax = $seller_country_id ? Tax::select('tax')->where('country_id', $seller_country_id)->first() : null;

            $image_id = null;
            if($request->file('image')){
                $media = MediaHelper::insert_media_image($request,'web','image');
                $image_id = $media ? $media->id : null;
            } elseif ($request->has('image') && is_numeric($request->image)) {
                $image_id = (int) $request->image;
            }
            
            if($request->file('image_gallery')){
                $media = MediaHelper::insert_media_image($request,'web','image_gallery');
                $image_id = $media ? $media->id : $image_id;
            }

            $durationInput = $request->input('duration') ?: $request->input('delivery_days', 1);
            $deliveryDays = (int) preg_replace('/[^0-9]/', '', (string)$durationInput) ?: 1;

            $service = new Service();
            $service->category_id = $request->category_id;
            $service->subcategory_id = $request->subcategory_id;
            $service->child_category_id = $request->child_category_id;
            $service->title = $request->title;
            $service->slug = createSlug($request->title, "service");
            $service->description = $request->description;
            $service->image = $image_id;
            $service->price = (float) $request->price;
            $service->delivery_days = $deliveryDays;
            $service->video = $request->video;
            $service->seller_id = $user->id;
            $service->service_city_id = $city_id;
            $service->service_area_id = $request->input('service_area_id') ?: $user->service_area;
            $service->status = 0;
            $service->is_service_on = 1;
            $service->tax = $country_tax->tax ?? 0;
            $service->is_service_all_cities = $request->is_service_all_cities ?? 0;

            $Metas = [
                'meta_title'=> purify_html($request->meta_title),
                'meta_tags'=> purify_html($request->meta_tags),
                'meta_description'=> purify_html($request->meta_description),

                'facebook_meta_tags'=> purify_html($request->facebook_meta_tags),
                'facebook_meta_description'=> purify_html($request->facebook_meta_description),
                'facebook_meta_image'=> $request->facebook_meta_image,

                'twitter_meta_tags'=> purify_html($request->twitter_meta_tags),
                'twitter_meta_description'=> purify_html($request->twitter_meta_description),
                'twitter_meta_image'=> $request->twitter_meta_image,
            ];
            $service->save();
            $last_service_id = $service->id;
            $service->metaData()->create($Metas);

            // Handle optional includes passed with create request
            if ($request->has('includes')) {
                $includesData = is_string($request->includes) ? json_decode($request->includes, true) : $request->includes;
                if (is_array($includesData)) {
                    foreach ($includesData as $inc) {
                        $incTitle = is_array($inc) ? ($inc['title'] ?? '') : (string)$inc;
                        if (!empty(trim($incTitle))) {
                            Serviceinclude::create([
                                'service_id' => $service->id,
                                'seller_id' => $user->id,
                                'include_service_title' => trim($incTitle),
                                'include_service_price' => (float) (is_array($inc) ? ($inc['price'] ?? 0) : 0),
                                'include_service_quantity' => (int) (is_array($inc) ? ($inc['quantity'] ?? 1) : 1),
                            ]);
                        }
                    }
                }
            }

            try {
                $message = get_static_option('service_approve_message');
                $message = str_replace(["@service_id"],[$last_service_id],$message);
                Mail::to(get_static_option('site_global_email'))->send(new BasicMail([
                    'subject' =>get_static_option('service_approve_subject') ?? __('New Service Approve Request'),
                    'message' => $message
                ]));
            } catch (\Exception $e) {
                //
            }

            $imgData = get_attachment_image_by_id($service->image);
            $imageUrl = !empty($imgData) ? ($imgData['img_url'] ?? null) : null;

            return response()->success([
                'msg'=> __('Service Successfully Add'),
                'id' => $last_service_id,
                'image_url' => $imageUrl,
                'service' => $service
            ]);
        }
    }

    public function serviceDetails($id)
    {
        $seller_id = Auth::guard('sanctum')->id();
        $service = Service::with(['category', 'subcategory', 'childcategory', 'serviceInclude', 'serviceAdditional'])
            ->where('id', $id)
            ->where('seller_id', $seller_id)
            ->first();

        if (!$service) {
            return response()->error(['message' => __('Service not found or unauthorized')]);
        }

        $imgData = get_attachment_image_by_id($service->image);
        $imageUrl = !empty($imgData) ? ($imgData['img_url'] ?? null) : null;

        return response()->success([
            'service' => $service,
            'image_url' => $imageUrl,
            'includes' => $service->serviceInclude,
            'additionals' => $service->serviceAdditional,
        ]);
    }
    
    public function updateService(Request $request)
    {
        if ($request->isMethod('post')) {
            $service_id = $request->input('service_id') ?: $request->input('id');
            $seller_id = Auth::guard('sanctum')->id();

            $service = Service::where('id', $service_id)->where('seller_id', $seller_id)->first();
            if (empty($service)) {
                return response()->error(['message' => __('Service not found or unauthorized')]);
            }

            $request->validate([
                'category_id' => 'nullable',
                'title' => 'required|max:191',
                'description' => 'required|min:10',
                'price' => 'nullable|numeric|min:0',
            ]);

            $image_id = $service->image;
            if($request->file('image')){
                $media = MediaHelper::insert_media_image($request,'web','image');
                $image_id = $media ? $media->id : $image_id;
            }
            if($request->file('image_gallery')){
                $media = MediaHelper::insert_media_image($request,'web','image_gallery');
                $image_id = $media ? $media->id : $image_id;
            }

            $durationInput = $request->input('duration') ?: $request->input('delivery_days');
            $deliveryDays = $durationInput !== null ? ((int) preg_replace('/[^0-9]/', '', (string)$durationInput) ?: 1) : $service->delivery_days;

            $updateData = [
                'title' => $request->title,
                'slug' => ($request->title !== $service->title) ? createSlug($request->title, "service") : $service->slug,
                'description' => $request->description,
                'image' => $image_id,
                'delivery_days' => $deliveryDays,
                'status' => 0, // Reset to pending approval on edit
            ];

            if ($request->filled('price')) {
                $updateData['price'] = (float) $request->price;
            }
            if ($request->filled('category_id')) {
                $updateData['category_id'] = $request->category_id;
            }
            if ($request->filled('subcategory_id')) {
                $updateData['subcategory_id'] = $request->subcategory_id;
            }
            if ($request->filled('child_category_id')) {
                $updateData['child_category_id'] = $request->child_category_id;
            }

            $service->update($updateData);

            // Handle updated includes if provided
            if ($request->has('includes')) {
                $includesData = is_string($request->includes) ? json_decode($request->includes, true) : $request->includes;
                if (is_array($includesData)) {
                    Serviceinclude::where('service_id', $service->id)->delete();
                    foreach ($includesData as $inc) {
                        $incTitle = is_array($inc) ? ($inc['title'] ?? '') : (string)$inc;
                        if (!empty(trim($incTitle))) {
                            Serviceinclude::create([
                                'service_id' => $service->id,
                                'seller_id' => $seller_id,
                                'include_service_title' => trim($incTitle),
                                'include_service_price' => (float) (is_array($inc) ? ($inc['price'] ?? 0) : 0),
                                'include_service_quantity' => (int) (is_array($inc) ? ($inc['quantity'] ?? 1) : 1),
                            ]);
                        }
                    }
                }
            }

            EditServiceHistory::create([
                'service_id' => $service->id,
                'seller_id' => $seller_id,
                'service_title' => $request->title,
                'service_description' => $request->description,
            ]);

            $imgData = get_attachment_image_by_id($service->image);
            $imageUrl = !empty($imgData) ? ($imgData['img_url'] ?? null) : null;

            return response()->success([
                'status' => 'success',
                'message'=> __('Service updated successfully and submitted for admin review.'),
                'service' => $service->fresh(),
                'image_url' => $imageUrl,
            ]);
        }
    }

    public function addServiceAttributesByID(Request $request,$id=null)
    {

        $get_service = Service::where('id',$id)->where('seller_id',Auth::guard('sanctum')->user()->id)->first();

        if($request->isMethod('post')) {

            $data = $request->all();
            $all_include_service = [];
            $all_additional_service = [];
            $all_benifits_service = [];
            $online_service_faqs = [];
            $service_total_price = 0;
            $service_total_price_with_new_added_attribute = 0;
            $service_count = 0;
            $new_include_services = [];
            $new_additional_services = [];
            $new_benifits_services = [];


            if(isset($data['is_service_online_id'])){
                if($data['is_service_online_id'] == 1){
                    if(isset($data['all_include_service'])){

                        $validator = Validator::make($request->all(), [
                            'online_service_price' => 'required|numeric|min:0',
                            'delivery_days' => 'required|integer|min:0'
                        ]);

                        if ($validator->fails()) {
                            return response()->json(['errors' => $validator->errors()], 422);
                        }

                        $new_include_services = json_decode($data['all_include_service']);
                        // include service
                        foreach ($new_include_services as  $value) {
                            foreach ($value as $service){
                                $all_include_service[] = (array) $service +
                                    [
                                        'seller_id' => Auth::guard('sanctum')->user()->id,
                                        'include_service_price' => 0,
                                        'include_service_quantity' => 0
                                    ];
                                $service_count++;
                            }
                        }

                        // update service for online
                        Service::where('id',$id)->update([
                            'price' => $data['online_service_price'],
                            'online_service_price' => $data['online_service_price'],
                            'delivery_days' => $data['delivery_days'],
                            'revision' => $data['revision'],
                            'is_service_online' => 1,
                        ]);

                    }
                }
            }else{
                if(isset($data['all_include_service'])){
                    $new_include_services = json_decode($data['all_include_service']);
                    foreach ($new_include_services as  $value) {
                        foreach ($value as $service){
                            $service = (array) $service;
                            $all_include_service[] = $service +
                                [
                                    'seller_id' => Auth::guard('sanctum')->user()->id,
                                    'include_service_price' => 0,
                                    'include_service_quantity' => 0
                                ];
                            $service_total_price += $service['include_service_price'] * $service['include_service_quantity'];
                            $service_count++;
                        }
                    }
                }
            }
            if($service_count>=1){
                Serviceinclude::insert($all_include_service);
                $service_old_price = Service::where('id',$id)->select('price')->first();
                $service_total_price_with_new_added_attribute =($service_old_price->price + $service_total_price);
                Service::where('id', $id)->update(['price' => $service_total_price_with_new_added_attribute]);
            }

            if(isset($data['all_additional_service'])) {
                $new_additional_services = json_decode($data['all_additional_service']);
                foreach ($new_additional_services as $value) {
                    foreach ($value as $service){
                        $all_additional_service[] = (array) $service +
                            [
                                'seller_id' => Auth::guard('sanctum')->user()->id,
                                'additional_service_price' => 0,
                                'additional_service_quantity' => 0
                            ];
                        $service_count++;
                    }
                }
            }
            if($service_count>=1){
                Serviceadditional::insert($all_additional_service);
            }

            if(isset($data['service_benifits'])) {
                $new_benifits = json_decode($data['service_benifits']);
                foreach ($new_benifits as $value) {
                    foreach ($value as $service){
                        $all_benifits_service[] = (array) $service +
                            [
                                'seller_id' => Auth::guard('sanctum')->user()->id,
                                'benifits' => 0,
                            ];
                        $service_count++;
                    }
                }
            }

            if($service_count>=1){
                Servicebenifit::insert($all_benifits_service);
            }

            if(isset($data['online_service_faqs'])){
                $new_faqs_services = json_decode($data['online_service_faqs']);
                foreach ($new_faqs_services as $value) {
                    foreach ($value as $service){
                        $online_service_faqs[] = (array) $service +
                            [
                                'seller_id' => Auth::guard('sanctum')->user()->id,
                                'title' => '',
                                'description' => '',
                            ];
                        $service_count++;
                    }
                }
            }
            if($service_count>=1){
                OnlineServiceFaq::insert($online_service_faqs);
            }

            if($service_count <= 0){
                return response()->error([
                    'message'=>__('Please input service attributes.')
                ]);
            }
            return response()->success([
                'message'=>__('Service attributes added success.')
            ]);
        }

        if($get_service !=''){
            return response()->error([
                'message'=>__('Service not found.')
            ]);
        }
    }

    public function updateServiceAttributesByID(Request $request,$id=null)
    {
        $get_service = Service::where('id',$id)->where('seller_id',Auth::guard('sanctum')->user()->id)->first();

        if($request->isMethod('post')) {
            $data = $request->all();

            $all_include_service = [];
            $all_additional_service = [];
            $all_benifits_service = [];
            $online_service_faqs = [];
            $service_total_price = 0;
            $service_total_price_with_new_added_attribute = 0;
            $service_count = 0;
            $new_include_services = [];
            $new_additional_services = [];
            $new_benifits_services = [];

            if(isset($data['is_service_online_id'])){
                if($data['is_service_online_id'] == 1){
                    if(isset($data['all_include_service'])){
                        $validator = Validator::make($request->all(), [
                            'online_service_price' => 'required|numeric|min:0',
                            'delivery_days' => 'required|integer|min:0'
                        ]);

                        if ($validator->fails()) {
                            return response()->json(['errors' => $validator->errors()], 422);
                        }

                        $new_include_services = json_decode($data['all_include_service']);
                        foreach ($new_include_services as  $value) {
                            foreach ($value as $service){
                                $all_include_service[] = (array) $service +
                                    [
                                        'seller_id' => Auth::guard('sanctum')->user()->id,
                                        'include_service_price' => 0,
                                        'include_service_quantity' => 0
                                    ];
                                $service_count++;
                            }
                        }

                        // update service for online
                        Service::where('id',$id)->update([
                            'price' => $data['online_service_price'],
                            'online_service_price' => $data['online_service_price'],
                            'delivery_days' => $data['delivery_days'],
                            'revision' => $data['revision'],
                            'is_service_online' => 1,
                        ]);
                    }
                }
            }else{
                if(isset($data['all_include_service'])){
                    $new_include_services = json_decode($data['all_include_service']);
                    foreach ($new_include_services as  $value) {
                        foreach ($value as $service){
                            $service = (array) $service;
                            $all_include_service[] = $service +
                                [
                                    'seller_id' => Auth::guard('sanctum')->user()->id,
                                    'include_service_price' => 0,
                                    'include_service_quantity' => 0
                                ];
                            $service_total_price += $service['include_service_price'] * $service['include_service_quantity'];
                            $service_count++;
                        }
                    }
                }
            }

            if($service_count>=1){
                Serviceinclude::where("service_id", $get_service->id)->delete();
                Serviceinclude::insert($all_include_service);
                $service_old_price = Service::where('id',$id)->select('price')->first();
                $service_total_price_with_new_added_attribute = $service_total_price;

                if(isset($data['is_service_online_id'])){
                    if($data['is_service_online_id'] == 1) {
                        Service::where('id', $id)->update(['price' => $data['online_service_price']]);
                    }
                }else{
                    Service::where('id', $id)->update(['price' => $service_total_price_with_new_added_attribute]);
                    // update service for online
                    Service::where('id',$id)->update([
                        'online_service_price' => 0,
                        'delivery_days' => 0,
                        'revision' => 0,
                        'is_service_online' => 0,
                    ]);
                }
            }

            if(isset($data['all_additional_service'])) {
                $new_additional_services = json_decode($data['all_additional_service']);
                foreach ($new_additional_services as $value) {
                    foreach ($value as $service){
                        $all_additional_service[] = (array) $service +
                            [
                                'seller_id' => Auth::guard('sanctum')->user()->id,
                                'additional_service_price' => 0,
                                'additional_service_quantity' => 0
                            ];
                        $service_count++;
                    }
                }
            }
            
            if($service_count>=1){
                Serviceadditional::where("service_id", $get_service->id)->delete();
                Serviceadditional::insert($all_additional_service);
            }

            if(isset($data['service_benifits'])) {
                $new_benifits = json_decode($data['service_benifits']);
                foreach ($new_benifits as $value) {
                    foreach ($value as $service){
                        $all_benifits_service[] = (array) $service +
                            [
                                'seller_id' => Auth::guard('sanctum')->user()->id,
                                'benifits' => 0,
                            ];
                        $service_count++;
                    }
                }
            }

            if($service_count>=1){
                Servicebenifit::where("service_id", $get_service->id)->delete();
                Servicebenifit::insert($all_benifits_service);
            }

            if(isset($data['online_service_faqs'])){
                $new_faqs_services = json_decode($data['online_service_faqs']);
                foreach ($new_faqs_services as $value) {
                    foreach ($value as $service){
                        $online_service_faqs[] = (array) $service +
                            [
                                'seller_id' => Auth::guard('sanctum')->user()->id,
                                'title' => '',
                                'description' => '',
                            ];
                        $service_count++;
                    }
                }
            }
            if($service_count>=1){
                OnlineServiceFaq::where("service_id", $get_service->id)->delete();
                OnlineServiceFaq::insert($online_service_faqs);
            }

            if($service_count <= 0){
                return response()->error([
                    'message'=>__('Please input service attributes.')
                ]);
            }
            return response()->success([
                'message'=>__('Service attributes updated success.')
            ]);
        }

        if($get_service !=''){
            return response()->error([
                'message'=>__('Service not found.')
            ]);
        }

    }
    
    public function showAttributes($id=null)
    {
        $seller_id = Auth::guard('sanctum')->user()->id;
        $service = Service::select(['id','title','image'])
            ->where('id',$id)
            ->where('seller_id',$seller_id)
            ->first();

        if(!empty($service)){
            $include_service = Serviceinclude::where('service_id',$id)->get();
            $additional_service = Serviceadditional::where('service_id',$id)->get();
            $service_benifit = Servicebenifit::where('service_id',$id)->get();
            return response()->success([
                'include_services'=>$include_service,
                'additional_service'=>$additional_service,
                'service_benifit'=>$service_benifit,
            ]);
        }
    }
    
    public function deleteIncludeService($id = null)
    {
        $seller_id = Auth::guard('sanctum')->user()->id;
        $include_details = Serviceinclude::find($id);
        if (!$include_details) {
            return response()->error(['message' => __('Include service not found.')]);
        }

        // Check ownership of the parent service
        $service_details = Service::where('id', $include_details->service_id)->where('seller_id', $seller_id)->first();
        if (!$service_details) {
            return response()->error(['message' => __('Unauthorized')]);
        }

        // Update service price
        $service_details->price -= $include_details->include_service_price * $include_details->include_service_quantity;
        $service_details->save();
        $include_details->delete();
        return response()->success([
            'message'=>__('Include Service Delete Success.')
        ]);
    }
    
    public function deleteAdditionalService($id = null)
    {
        $seller_id = Auth::guard('sanctum')->user()->id;
        $additional = Serviceadditional::where('id', $id)->where('seller_id', $seller_id)->first();
        if (!$additional) {
            return response()->error(['message' => __('Additional Service not found or unauthorized.')]);
        }
        $additional->delete();
        return response()->success([
            'message'=>__('Additional Service Delete Success.')
        ]);
    }
    
    public function deleteBenefits($id = null)
    {
        $seller_id = Auth::guard('sanctum')->user()->id;
        $benefit = Servicebenifit::where('id', $id)->where('seller_id', $seller_id)->first();
        if (!$benefit) {
            return response()->error(['message' => __('Service Benefit not found or unauthorized.')]);
        }
        $benefit->delete();
        return response()->success([
            'message'=>__('Service Benefit Delete Success')
        ]);
    }

    public function deleteService($id = null)
    {
        $seller_id = Auth::guard('sanctum')->user()->id;
        $service = Service::where('id', $id)->where('seller_id', $seller_id)->first();
        if (!$service) {
            return response()->error(['message' => __('Service not found or unauthorized.')]);
        }

        Serviceinclude::where('service_id', $id)->delete();
        Serviceadditional::where('service_id', $id)->delete();
        Servicebenifit::where('service_id', $id)->delete();
        OnlineServiceFaq::where('service_id', $id)->delete();
        $service->delete();
        return response()->success([
            'message'=>__('Service Delete Success'),
        ]);
    }
}
