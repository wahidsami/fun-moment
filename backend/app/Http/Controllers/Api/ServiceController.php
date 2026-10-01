<?php

namespace App\Http\Controllers\Api;

use App\Actions\Media\MediaHelper;
use App\AdminCommission;
use App\Category;
use App\ChildCategory;
use App\Country;
use App\Day;
use App\Http\Controllers\Controller;
use App\Mail\OrderMail;
use App\Notifications\OrderNotification;
use App\Order;
use App\OrderAdditional;
use App\OrderInclude;
use App\Review;
use App\Schedule;
use App\Service;
use App\Servicebenifit;
use App\ServiceCity;
use App\ServiceCoupon;
use App\Serviceinclude;
use App\Subcategory;
use App\Tax;
use App\User;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Modules\Wallet\Entities\Wallet;
use Xgenious\Paymentgateway\Facades\XgPaymentGateway;


class ServiceController extends Controller
{
    
    public function embedCodeTest(){
        $iframe_string = '<iframe width="560" height="315" src="https://www.youtube.com/embed/Uc5i1AKaSTs" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        // $result = '';
        preg_match('/src="([^"]+)"/', $iframe_string, $match);
        //$url = $match[1];
        // $result = Str::after($sr,'src="');
        
        return response()->error([
            'message'=>  end($match),
        ]);
        
    }
    //top selling services
    public function topService(){
        
        $top_services_query = Service::query()->select('id','title','image','price','seller_id')
            ->with('reviews_for_mobile')
            ->whereHas('reviews_for_mobile')
            ->where('status','1')
            ->where('is_service_on','1')
            ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                $q->whereHas('seller_subscription');
            });
            
            
        if(!empty(request()->get('state_id'))){
            $top_services_query->where('service_city_id',request()->get('state_id'));
        }
          
            
        $top_services_query->orderBy('sold_count','Desc');
        
        if(!empty(request()->get('paginate'))){
            $top_services = $top_services_query->paginate(request()->get('paginate'))->withQueryString();
        }else{
             $top_services = $top_services_query->take(10)->get();
        }
        
       
            
        $service_image=[];
        $service_seller_name=[];
        $reviewer_image=[];
        foreach($top_services as $service){
            $service_image[]= get_attachment_image_by_id($service->image);
            $service_seller_name[]= optional($service->seller_for_mobile)->name;
            foreach($service->reviews_for_mobile as $review){
                $reviewer_image[]=get_attachment_image_by_id(optional($review->buyer_for_mobile)->image);
            }
        }

        if($top_services){
            return response()->success([
                'top_services'=>$top_services,
                'service_image'=>$service_image,
                'service_seller_name'=>$service_seller_name,
                'reviewer_image'=>$reviewer_image,
            ]);
        }
        return response()->error([
            'message'=>__('Service Not Available'),
        ]);
    }

    //latest services
    public function latestService()
    {
        $latest_services_query = Service::query()->select('id','title','image','price','seller_id')
            ->with('reviews_for_mobile')
            ->where('status','1')
            ->where('is_service_on','1')
            ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                $q->whereHas('seller_subscription');
            })
        ;
            
        if(!empty(request()->get('state_id'))){
            $latest_services_query->where('service_city_id',request()->get('state_id'));
        }
        
        $latest_services  = $latest_services_query->latest()
            ->take(10)
            ->get();
        $service_image=[];
        $service_seller_name=[];
        $reviewer_image=[];
        foreach($latest_services as $service){
            $service_image[]= get_attachment_image_by_id($service->image);
            $service_seller_name[]= optional($service->seller_for_mobile)->name;
            foreach($service->reviews_for_mobile as $review){
                $reviewer_image[]=get_attachment_image_by_id(optional($review->buyer_for_mobile)->image);
            }
        }

        if($latest_services){
            return response()->success([
                'latest_services'=>$latest_services,
                'service_image'=>$service_image,
                'service_seller_name'=>$service_seller_name,
                'reviewer_image'=>$reviewer_image,
            ]);
        }
        return response()->error([
            'message'=>__('Service Not Available'),
        ]);
    }

    // service details
    public function serviceDetails($id=null){
    
        $service_details = Service::with([
            'serviceFaq',
            'seller_for_mobile',
            'reviews_for_mobile.buyer',
            'serviceCity'
        ])->where('id', $id)->where('status', 1)->where('is_service_on', 1)->first();
        
        if (auth('sanctum')->check() && auth('sanctum')->user()->user_type === 0) {
            $service_details = Service::with([
                'serviceFaq',
                'seller_for_mobile',
                'reviews_for_mobile.buyer',
                'serviceCity'
            ])->where('id', $id)->first();
        }

        if (is_null($service_details)) {
            return response()->json(["msg" => __("service not found")], 404);
        }
        $service_image = get_attachment_image_by_id($service_details->image);
        $service_seller_name = optional($service_details->seller_for_mobile)->name;
        $service_seller_image_Id = optional($service_details->seller_for_mobile)->image;
        $service_seller_image = get_attachment_image_by_id($service_seller_image_Id);
        $seller_complete_order = Order::where('seller_id', $service_details->seller_id)->where('status', 2)->count();
        $seller_cancelled_order = Order::where('seller_id', $service_details->seller_id)->where('status', 4)->count();
        $seller_rating = Review::where('seller_id', $service_details->seller_id)->avg('rating');
        $seller_rating_percentage_value = round($seller_rating * 20);
        $seller_from = optional(optional($service_details->seller_for_mobile)->country)->country;
        $seller_since = User::select('created_at')->where('id', $service_details->seller_id)->where('user_status', 1)->first();
        $service_includes = Serviceinclude::select('id', 'service_id', 'include_service_title')->where('service_id', $service_details->id)->get();
        $service_benifits = Servicebenifit::select('id', 'service_id', 'benifits')->where('service_id', $service_details->id)->get();

        $order_completion_rate = 0;
        if ($seller_complete_order > 0 || $seller_cancelled_order > 0) {
            $order_completion_rate = $seller_complete_order / ($seller_complete_order + $seller_cancelled_order) * 100;
        }

        $service_reviews = $service_details->reviews_for_mobile->transform(function ($item) {
            $buyer_details = $item->buyer;
            $item->buyer_name = !is_null($buyer_details) ? $buyer_details->name : 'Unknown';
            $image_url = null;
            if (!is_null($buyer_details) && !empty($buyer_details->image)) {
                $img_attachment = get_attachment_image_by_id($buyer_details->image);
                $image_url = (!empty($img_attachment) && !empty($img_attachment['img_url'])) ? $img_attachment['img_url'] : null;
            }
            $item->buyer_image = $image_url;
            return $item;
        });

        $reviewer_image = [];
        foreach ($service_details->reviews_for_mobile as $review) {
            $buyer_img_id = optional($review->buyer)->image;
            $img_data = !empty($buyer_img_id) ? get_attachment_image_by_id($buyer_img_id) : null;
            $reviewer_image[] = (!empty($img_data) && !empty($img_data['img_url'])) ? $img_data : null;
        }

        $service_video_url = $service_details->video;
        $video_match_url = null;
        if (!empty($service_video_url)) {
            if (preg_match('/src="([^"]+)"/', $service_video_url, $service_video_url_match)) {
                $video_match_url = end($service_video_url_match);
            } else {
                $video_match_url = $service_video_url;
            }
        }

        return response()->json([
            'service_details' => $service_details,
            'service_image' => $service_image,
            'service_seller_name' => $service_seller_name,
            'service_seller_image' => is_array($service_seller_image) && !empty($service_seller_image) ? $service_seller_image : null,
            'seller_complete_order' => $seller_complete_order,
            'seller_rating' => $seller_rating_percentage_value,
            'order_completion_rate' => round($order_completion_rate),
            'seller_from' => $seller_from,
            'seller_since' => $seller_since,
            'service_includes' => $service_includes,
            'service_benifits' => $service_benifits,
            'service_reviews' => $service_reviews,
            'reviewer_image' => $reviewer_image,
            'video_url' => $video_match_url,
        ], 200);
    }

    //service rating
    public function serviceRating(Request $request,$id=null){
        $request->validate([
            'rating' => 'required|integer',
            'name' => 'required|max:191',
            'email' => 'required|max:191',
            'message' => 'required',
        ]);

        $service_details = Service::select('id','seller_id')->where('id',$id)->first();
        $order_count = Order::where(['service_id' => $service_details->id,'buyer_id' => auth('sanctum')->user()->id,'status' => 'complete' ])->count();
        
        
        if(!empty($order_count) && $order_count > 0){
            //todo add another filter to check this buyer already leave a review in this or not
            $old_review = Review::where(['service_id' => $service_details->id,'buyer_id' => auth('sanctum')->user()->id])->count();
            if($old_review > 0){
                 return response()->error([
                        'message'=>__('you have already leave a review in this service'),
                    ]); 
            }
             Review::create([
                'service_id' => $service_details->id,
                'seller_id' => $service_details->seller_id,
                'buyer_id' => auth('sanctum')->user()->id,
                'rating' => $request->rating,
                'name' => $request->name,
                'email' => $request->email,
                'message' => $request->message,
            ]);
    
            return response()->success([
                'message'=>__('Review Added Success'),
            ]);
        }
        
        return response()->error([
            'message'=>__('You need to buy this service to leave feedback'),
        ]);
        
    }

    //all services
    public function allServices(){
        $all_services_query = Service::query()->with('seller_for_mobile','reviews_for_mobile','serviceCity')
            ->select('id','seller_id','title','price','image','is_service_online','service_city_id')
            ->where('status', 1)
            ->where('is_service_on', 1)
            ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                $q->whereHas('seller_subscription');
            });
            
        if(!empty(request()->get('state_id'))){
            $all_services_query->where('service_city_id',request()->get('state_id'));
        }
        
        $all_services = $all_services_query->OrderBy('id','desc')
            ->paginate(10)
            ->withQueryString();

        if($all_services){
            foreach($all_services as $service){
                $service_image[] = get_attachment_image_by_id($service->image);
                $service_country[] = optional(optional($service->serviceCity)->countryy)->country;
            }
            return response()->success([
                'all_services'=>$all_services,
                'service_image'=>$service_image,
            ]);
        }
        
        return response()->error([
            'message'=>__('Service Not Available'),
        ]);
    }

    //service search by category
    public function searchByCategory($category_id=null)
    {

        $all_services_query = Service::query()->with('seller_for_mobile','reviews_for_mobile','serviceCity')
            ->select('id','seller_id','title','price','image','is_service_online','service_city_id')
            ->where('status', 1)
            ->where('is_service_on', 1)
            ->where('category_id', $category_id)
            ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                $q->whereHas('seller_subscription');
            });
            
            if(!empty(request()->get('state_id'))){
                $all_services_query->where('service_city_id',request()->get('state_id'));
            }
        
        $all_services  =   $all_services_query->OrderBy('id','desc')
            ->paginate(10)
            ->withQueryString();

        if($all_services->count() >=1){
            foreach($all_services as $service){
                $service_image[] = get_attachment_image_by_id($service->image);
                $service_country[] = optional(optional($service->serviceCity)->countryy)->country;
            }
            return response()->success([
                'all_services'=>$all_services,
                'service_image'=>$service_image,
            ]);
        }
        return response()->error([
            'message'=>__('Service Not Found'),
        ]);
    }

    //service search by category and subcategory
    public function searchBySubCategory($category_id,$subcategory_id)
    {

        $all_services = Service::with('seller_for_mobile','reviews_for_mobile','serviceCity')
            ->select('id','seller_id','title','price','image','is_service_online','service_city_id')
            ->where('status', 1)
            ->where('is_service_on', 1)
            ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                $q->whereHas('seller_subscription');
            })
            ->where('category_id', $category_id)
            ->where('subcategory_id', $subcategory_id)
            ->OrderBy('id','desc')
            ->paginate(10)
             ->withQueryString();

        if($all_services->count() >=1){
            foreach($all_services as $service){
                $service_image[] = get_attachment_image_by_id($service->image);
                $service_country[] = optional(optional($service->serviceCity)->countryy)->country;
            }
            return response()->success([
                'all_services'=>$all_services,
                'service_image'=>$service_image,
            ]);
        }
        return response()->error([
            'message'=>__('Service Not Found'),
        ]);
    }

    //service search by category, subcategory and rating
    public function searchByRating($category_id=null,$subcategory_id=null,$rating=null)
    {
        if(isset($rating)){
            $rating = (int) $rating;
            $all_services = Service::with('seller_for_mobile','reviews_for_mobile','serviceCity')
                ->select('id','seller_id','title','price','image','is_service_online','service_city_id')
                ->where('status', 1)
                ->where('is_service_on', 1)
                ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                    $q->whereHas('seller_subscription');
                })
                ->where('category_id', $category_id)
                ->where('subcategory_id', $subcategory_id);

            $all_services = $all_services->whereHas('reviews', function ($q) use ($rating) {
                $q->groupBy('reviews.id')
                    ->havingRaw('AVG(reviews.rating) >= ?', [$rating])
                    ->havingRaw('AVG(reviews.rating) < ?', [$rating + 1]);
            });
            
            if(!empty(request()->get('state_id'))){
                $all_services->where('service_city_id',request()->get('state_id'));
            }
            
            $all_services = $all_services>OrderBy('id','desc')
                ->paginate(10)
                 ->withQueryString();

            $service_image[]='';
            if($all_services->count() >=1){
                foreach($all_services as $service){
                    $service_image[] = get_attachment_image_by_id($service->image);
                    $service_country[] = optional(optional($service->serviceCity)->countryy)->country;
                }
                return response()->success([
                    'all_services'=>$all_services,
                    'service_image'=>$service_image,
                ]);
            }
            return response()->error([
                'message'=>__('Service Not Found'),
            ]);
        }

    }

    //service search by category, subcategory and rating and sort by
    public function searchBySort()
    {
        $service_quyery = Service::query();
        $service_quyery->with('seller_for_mobile','reviews_for_mobile','serviceCity');
        $service_quyery->select('id','seller_id','title','price','image','is_service_online','service_city_id')
            ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                $q->whereHas('seller_subscription');
            });
        if(!empty(request()->get('cat'))){
            $service_quyery->where('category_id',request()->get('cat'));
        }
        if(!empty(request()->get('subcat'))){
            $service_quyery->where('subcategory_id',request()->get('subcat'));
        }
        if(!empty(request()->get('rating'))){
            $rating = (int) request()->get('rating');
            $service_quyery->whereHas('reviews', function ($q) use ($rating) {
                $q->groupBy('reviews.id')
                    ->havingRaw('AVG(reviews.rating) >= ?', [$rating])
                    ->havingRaw('AVG(reviews.rating) < ?', [$rating + 1]);
            });
        }

        if(!empty(request()->get('sortby'))){

            if (request()->get('sortby') == 'latest_service') {
                $service_quyery->orderBy('id', 'Desc');
            }
            if (request()->get('sortby') == 'lowest_price') {
                $service_quyery->orderBy('price', 'Asc');
            }
            if (request()->get('sortby') == 'highest_price') {
                $service_quyery->orderBy('price', 'Desc');
            }

        }
        $all_services = $service_quyery->where('status', 1)
            ->where('is_service_on', 1)
            ->OrderBy('id','desc')
            ->paginate(10)
             ->withQueryString();

        $service_image = [];
        if($all_services->count() >=1){
            foreach($all_services as $service){
                $service_image[] = get_attachment_image_by_id($service->image);
                $service_country[] = optional(optional($service->serviceCity)->countryy)->country;
            }
            return response()->success([
                'all_services'=>$all_services,
                'service_image'=>$service_image,
            ]);
        }
        return response()->error([
            'message'=>__('Service Not Found'),
        ]);

    }

    //service book
    public function serviceBook($id=null)
    {
        $service = Service::with('serviceAdditional','serviceInclude','serviceBenifit','seller_for_mobile','serviceCity')
            ->select('id','seller_id','title','price','tax','image','is_service_online','service_city_id')
            ->where('status', 1)
            ->where('is_service_on', 1)
            ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                $q->whereHas('seller_subscription');
            })
            ->where('id', $id)
            ->first();

        $service_image[]='';
        if(isset($service)){
            $service_image[] = get_attachment_image_by_id($service->image);
            return response()->success([
                'service'=>$service,
                'service_image'=>$service_image,
            ]);
        }
        return response()->error([
            'message'=>__('Service Not Found'),
        ]);
    }

    //get schedule by seller / service
    public function scheduleByDay($day,$seller_id)
    {
        if (empty($day) || empty($seller_id)) {
            return response()->json(['status' => __('no schedule')]);
        }

        $service_id = request()->query('service_id') ?? request()->service_id;

        $dayMap = [
            'sunday' => 'Sun', 'sun' => 'Sun',
            'monday' => 'Mon', 'mon' => 'Mon',
            'tuesday' => 'Tue', 'tue' => 'Tue',
            'wednesday' => 'Wed', 'wed' => 'Wed',
            'thursday' => 'Thu', 'thu' => 'Thu',
            'friday' => 'Fri', 'fri' => 'Fri',
            'saturday' => 'Sat', 'sat' => 'Sat',
        ];

        $cleanDay = strtolower(trim($day));
        $shortDay = $dayMap[$cleanDay] ?? substr(ucfirst($cleanDay), 0, 3);

        $dayQuery = Day::select('id', 'day','total_day', 'status', 'service_id')
            ->where(function ($q) use ($day, $shortDay) {
                $q->where('day', $day)
                  ->orWhere('day', $shortDay);
            })
            ->where('seller_id', $seller_id);

        if (!empty($service_id)) {
            // First search for service-specific day
            $get_day = (clone $dayQuery)->where('service_id', $service_id)->first();
            // If service has no specific day record, fallback to seller-level
            if (!$get_day) {
                $get_day = (clone $dayQuery)->whereNull('service_id')->first();
            }
        } else {
            $get_day = (clone $dayQuery)->whereNull('service_id')->first() ?? $dayQuery->first();
        }

        if (!$get_day || (int) $get_day->status === 0) {
            return response()->json([
                'status' => __('no schedule'),
            ]);
        }

        $schedulesQuery = Schedule::select('id', 'schedule', 'status', 'service_id')
            ->where('seller_id', $seller_id)
            ->where('day_id', $get_day->id)
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', 1)
                  ->orWhere('status', '1');
            });

        if (!empty($service_id)) {
            $schedules = (clone $schedulesQuery)->where('service_id', $service_id)->orderBy('id', 'asc')->get();
            if ($schedules->isEmpty() && is_null($get_day->service_id)) {
                $schedules = $schedulesQuery->whereNull('service_id')->orderBy('id', 'asc')->get();
            }
        } else {
            $schedules = $schedulesQuery->orderBy('id', 'asc')->get();
        }

        if ($schedules->count() >= 1) {
            return response()->json([
                'day' => $get_day,
                'schedules' => $schedules,
            ]);
        }
        return response()->json([
            'status' => __('no schedule'),
        ]);
    }

    // coupon apply
    public function couponApply(Request $request)
    {
        if(!isset($request->coupon_code)){
            return response()->error([
                'message'=>__('Please enter your coupon'),
            ]);
        }

        $coupon_code = ServiceCoupon::where('code',$request->coupon_code)->first();
        $current_date = date('Y-m-d');

        if(!empty($coupon_code)){

            // Inactive coupon check
            if($coupon_code->status != 1){
                return response()->error([
                    'status' => __('inactive'),
                    'msg' => __('Coupon is Inactive'),
                ]);
            }

            // Admin coupons (user_type === 'admin' or seller_id is null) are platform-wide.
            // Seller coupons are restricted to that seller's services.
            $isAdminCoupon = ($coupon_code->user_type === 'admin' || is_null($coupon_code->seller_id));
            if(!$isAdminCoupon && (int)$coupon_code->seller_id !== (int)$request->seller_id){
                return response()->error([
                    'message'=>__('Coupon is not Applicable for this Service'),
                ]);
            }

            if($coupon_code->code == $request->coupon_code && $coupon_code->expire_date >= $current_date){

                if($coupon_code->discount_type == 'percentage'){
                    $coupon_amount = ($request->total_amount * $coupon_code->discount)/100;
                    return response()->success([
                        'status' => __('success'),
                        'coupon_amount' => $coupon_amount,
                    ]);
                }else{
                    $coupon_amount = $coupon_code->discount;
                    return response()->success([
                        'status' => __('success'),
                        'coupon_amount' => $coupon_amount,
                    ]);
                }
            }

            if($coupon_code->expire_date < $current_date ){
                return response()->error([
                    'status' => __('expired'),
                    'msg' => __('Coupon is Expired'),
                ]);
            }
        }else{
            return response()->error([
                'status' => __('invalid'),
                'msg' => __('Coupon is Invalid'),
            ]);
        }

    }

    // service city
    public function serviceCity()
    {
        $service_city = ServiceCity::query()->select('id','service_city')->where('status',1)->get();
        
        if($service_city){
            return response()->success([
                'service_city'=>$service_city,
            ]);
        }
        return response()->error([
            'message'=>__('Service City Not Available'),
        ]);
    }

    public function serviceCategory()
    {
        $categories = Category::select('id', 'name', 'status')
            ->whereHas('services')
            ->where('status', 1)
            ->orderBy('name', 'asc')
            ->paginate(20);

        if($categories){
            return response()->success([
                'categories' => $categories,
            ]);
        }else{
            return response()->error([
                'message' => __('Category not found'),
            ]);
        }
    }

    public function serviceSubCategory()
    {
        $category_ids  = Category::select('id', 'name', 'status')->whereHas('services')->where('status', 1)->pluck('id');
        $sub_category  = Subcategory::whereIn('category_id', $category_ids)->where('status', 1) ->orderBy('name', 'asc')->paginate(20);

        if($sub_category){
            return response()->success([
                'sub_category' => $sub_category
            ]);
        }else{
            return response()->error([
                'message' => __('Sub Category not found'),
            ]);
        }
    }
    public function serviceChildCategory()
    {
        $category_ids  = Category::whereHas('services')->where('status', 1)->pluck('id');
        $sub_category_ids  = Subcategory::whereIn('category_id', $category_ids)->where('status', 1)->pluck('id');
        $child_category  = ChildCategory::whereIn('sub_category_id', $sub_category_ids)->where('status', 1)->orderBy('name', 'asc')->paginate(20);
        if($child_category){
            return response()->success([
                'child_category' => $child_category
            ]);
        }else{
            return response()->error([
                'message' => __('child category not found'),
            ]);
        }
    }

    public function serviceSearch(Request $request)
    {

        $services = Service::query();
        $services->with('seller_for_mobile','reviews_for_mobile','serviceCity');
        $services->where('status',1)
            ->where('is_service_on',1)
            ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                $q->whereHas('seller_subscription');
            });

        // lat long wise filter
        $latitude = request()->get('latitude');
        $longitude = request()->get('longitude');
        $radius = 0;
        if(!empty(get_static_option("google_map_settings"))){
            if(empty(request()->get('remotely_button_filter'))){
                if(!empty(request()->get('latitude')) && !empty(request()->get('longitude'))){
                    // Calculate the radius in kilometers (adjust as needed)
                    $distance_radius_km_get = request()->get('distance_kilometers_value');
                    $distance_radius_km = (int) $distance_radius_km_get;
                    if($distance_radius_km == 0){
                        $radius = 50;
                    }else{
                        $radius = $distance_radius_km;
                    }

                    // Use a subquery to calculate the distance
                    $services->join('users', 'services.seller_id', '=', 'users.id')
                    ->selectRaw(
                        "services.*,
                            (6371 * acos(
                                cos(radians(?)) * cos(radians(users.latitude)) * cos(radians(users.longitude) - radians(?)) +
                                sin(radians(?)) * sin(radians(users.latitude))
                            )) AS distance",
                        [$latitude, $longitude, $latitude]
                    )->havingRaw('distance <= ?', [$radius])
                     ->orderBy('distance', 'asc');
                }
            }
        }

          // filter with google map start
        $google_map_status =   get_static_option("google_map_settings");
            if(!empty(get_static_option("google_map_settings"))) {
                if (!empty(request()->get('all_button_filter_value'))) {
                    $services->where('status', 1);
                } elseif (!empty(request()->get('in_person_filter_value'))) {
                    $services->where('is_service_online', 0);
                } elseif (!empty(request()->get('remotely_button_filter'))) {
                    $services->where('is_service_online', 1);
                }
            }
          // filter with google map end


        // search by text
        if(!empty($request->search_text)){
            $services->Where('title', 'LIKE', '%' . $request->search_text . '%')
                ->orWhere('description', 'LIKE', '%' . $request->search_text . '%');
        }

        // online service
        if(!empty($request->is_service_online)){
            $is_online = (int) $request->is_service_online;
            $services->where('is_service_online',$is_online);
        }

        // service country
        if (!empty(request()->get("country"))) {
            $service_country = Country::find(request()->get("country"));
            $service_country_ids = $service_country->cities->pluck("id")->toArray();
            $services->whereIn("service_city_id", $service_country_ids);
        }
        // service city
        if(!empty($request->service_city_id)){
            $services->where('service_city_id',$request->service_city_id);
        }
        // service area
        if (!empty(request()->get("area"))) {
            $services->where("service_area_id", request()->get("area"))->get();
        }

        // category
        if (!empty(request()->get("cat"))) {
            $services->where("category_id", request()->get("cat"));
        }

        // sub category
        if (!empty(request()->get("subcat"))) {
            $services->where("subcategory_id", request()->get("subcat"));
        }
          // child category
        if (!empty(request()->get("child_cat"))) {
            $services->where(
                "child_category_id",
                request()->get("child_cat")
            );
        }

        // rating by filter
        if (!empty(request()->get("rating"))) {
            $rating = (int) request()->get("rating");
            $services->whereHas("reviews", function ($q) use ($rating) {
                $q->groupBy("reviews.id")
                    ->havingRaw("AVG(reviews.rating) >= ?", [$rating])
                    ->havingRaw("AVG(reviews.rating) < ?", [$rating + 1]);
            });
        }

        // Filter by price range value
        $service_min_main_price_set = Service::select('id', 'price', 'online_service_price')->get();
        $max_price = max(
            intval($service_min_main_price_set->max('price')),
            intval($service_min_main_price_set->max('online_service_price'))
        );
        $min_price = '1';
        $max_price = $max_price ?? '9999';
        if (!empty(request()->get('price_range_value'))) {
            $priceRange = request()->get('price_range_value');
            list($minPrice, $maxPrice) = explode(',', $priceRange);
            $services->whereBetween('price', [$minPrice, $maxPrice]);
        }

        // sort-by filter
        if (!empty(request()->get("sortby"))) {
            if (request()->get("sortby") == "latest_service") {
                $services->orderBy("id", "Desc");
            }
            if (request()->get("sortby") == "lowest_price") {
                $services->orderBy("price", "Asc");
            }
            if (request()->get("sortby") == "highest_price") {
                $services->orderBy("price", "Desc");
            }
            if (request()->get("sortby") == "best_selling") {
                $services->orderBy("sold_count", "Desc");
            }
            if (request()->get("sortby") == "featured") {
                $services->where("featured", 1);
            }
            if (request()->get("sortby") == "popular") {
                $services->with('reviews')->orderBy('view','DESC');
            }
            if (request()->get("sortby") == "1") {
                $services->where("is_service_online", 1);
            }
        }else{
            $services->orderBy('id', 'desc');
        }

        // final get
        $services = $services->where('status', 1)->paginate(10);

       // service image url set
        $serviceData = [];
        if ($services) {
            foreach ($services as $service) {
                $image = get_attachment_image_by_id($service->image);
                $image_url = $image['img_url'] ?? null;

                $serviceData[] = [
                    'service' => $service,
                    'image_url' => $image_url,
                ];
            }
            return response()->success([
                'main_services' => $serviceData,
                'max_price' => $max_price,
                'google_map_status' => $google_map_status,
            ]);
        }

        return response()->error([
            'message'=>__('No Service Found'),
        ]);
    }

    public function homeSearch(Request $request)
    {
        $services = Service::query();
        $services->with('seller_for_mobile','reviews_for_mobile','serviceCity');
        $services->where('status',1)
            ->where('is_service_on',1)
            ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                $q->whereHas('seller_subscription');
            });

        if(!empty($request->search_text)){
            $services->Where('title', 'LIKE', '%' . $request->search_text . '%')
                ->orWhere('description', 'LIKE', '%' . $request->search_text . '%');
        }

        $is_online = (int) $request->is_service_online;
        $services->where('is_service_online',$is_online);

        if(!empty($request->service_city_id)){
            $services->where('service_city_id',$request->service_city_id);
        }

        $services =  $services->orderBy('id', 'desc')->where('status', 1)->get();

        $service_image = [];
        if(!is_null($services)){
            foreach($services as $service){
                $service_image[] = get_attachment_image_by_id($service->image);
            }
            return response()->success([
                'services'=> $services,
                'service_image'=>$service_image,
            ]);
        }

        return response()->error([
            'message'=>__('No Service Found'),
        ]);
    }

    // order create
    public function order(Request $request)
    {
        $is_service_online_bool = $request->is_service_online === '1';
        if ($is_service_online_bool) {
            $request->validate([
                'name' => 'required|max:191',
                'email' => 'required|max:191',
                'phone' => 'required|max:191',
                'address' => 'nullable|max:191',
                'choose_service_city' => 'nullable',
                'choose_service_area' => 'nullable',
                'choose_service_country' => 'nullable',
                'date' => 'nullable|max:191',
                'schedule' => 'nullable|max:191',
                'include_services' => 'nullable',
                'include_services.*.title' => 'nullable',
                'include_services.*.price' => 'nullable',
                'include_services.*.quantity' => 'nullable',
            ]);
        }

        $commission = AdminCommission::first();

        $payment_status = 'pending';

        if (empty($request->seller_id)) {
            return response()->error([
                'message' => __('Seller Id missing, please try another seller services'),
            ]);
        }

        if ($request->selected_payment_gateway === 'manual_payment') {
            $this->validate($request, [
                'manual_payment_image' => 'required|mimes:jpg,jpeg,png,pdf'
            ]);
        }

        $idempotencyKey = $request->input('idempotency_key') ?: $request->header('X-Idempotency-Key');
        if ($idempotencyKey !== null) {
            $idempotencyKey = trim((string) $idempotencyKey);
            if (strlen($idempotencyKey) === 0) {
                $idempotencyKey = null;
            } elseif (strlen($idempotencyKey) > 64 || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $idempotencyKey)) {
                return response()->json([
                    'error' => true,
                    'message' => __('Invalid or oversized idempotency key'),
                ], 422);
            }
        }

        $buyer_id = Auth::guard('sanctum')->check() ? Auth::guard('sanctum')->user()->id : ($request->buyer_id ? (int)$request->buyer_id : null);

        $reqDateYmd = null;
        $reqSlot = null;
        $parseSlotSec = null;

        if (!$is_service_online_bool && !empty($request->date) && !empty($request->schedule)) {
            try {
                $requestedDateObj = \Carbon\Carbon::parse($request->date);
                $reqDateYmd = $requestedDateObj->format('Y-m-d');
            } catch (\Exception $e) {
                $reqDateYmd = null;
            }

            $parseSlotSec = function ($slotStr) {
                $parts = explode('-', (string)$slotStr);
                if (count($parts) !== 2) return null;
                $s = strtotime(trim($parts[0]));
                $e = strtotime(trim($parts[1]));
                if ($s === false || $e === false) return null;
                $sSec = (int) date('H', $s) * 3600 + (int) date('i', $s) * 60;
                $eSec = (int) date('H', $e) * 3600 + (int) date('i', $e) * 60;
                return ['start' => $sSec, 'end' => $eSec];
            };

            $reqSlot = $parseSlotSec($request->schedule);
        }

        return DB::transaction(function () use (
            $request,
            $is_service_online_bool,
            $commission,
            $payment_status,
            $buyer_id,
            $idempotencyKey,
            $reqDateYmd,
            $reqSlot,
            $parseSlotSec
        ) {
            // 1. CONCURRENCY SERIALIZATION VIA POSTGRESQL TRANSACTION ADVISORY LOCKS
            if (DB::getDriverName() === 'pgsql') {
                if ($idempotencyKey) {
                    // Serialize rapid duplicate submissions using the exact submission idempotency key
                    DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["idempotency:{$idempotencyKey}"]);
                } elseif ($buyer_id) {
                    // Fallback serialization for legacy clients without an idempotency key
                    DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["buyer_order:{$buyer_id}:{$request->service_id}"]);
                }
                if (!$is_service_online_bool && !empty($request->date) && !empty($request->schedule) && $reqDateYmd) {
                    // Serialize concurrent attempts for the EXACT SAME booking slot & provider
                    DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["slot_booking:{$request->seller_id}:{$reqDateYmd}:{$request->schedule}"]);
                }
            }

            // 2. TRUE SUBMISSION IDEMPOTENCY / RETRY PROTECTION
            $existingBuyerOrder = null;
            if ($idempotencyKey) {
                $existingBuyerOrder = Order::where('idempotency_key', $idempotencyKey)->first();
            }

            if ($existingBuyerOrder) {
                $service_sold_count = Service::select('sold_count')->where('id', $existingBuyerOrder->service_id)->first();
                $random_order_id_1 = Str::random(30);
                $random_order_id_2 = Str::random(30);
                $new_order_id = $random_order_id_1 . $existingBuyerOrder->id . $random_order_id_2;

                return response()->success([
                    'order_id' => $existingBuyerOrder->id,
                    'shortage_balance' => 0,
                    'wallet_balance_status' => '',
                    'service_sold_count' => $service_sold_count,
                    'package_fee' => float_amount_with_currency_symbol($existingBuyerOrder->package_fee),
                    'extra_service' => float_amount_with_currency_symbol($existingBuyerOrder->extra_service),
                    'sub_total' => float_amount_with_currency_symbol($existingBuyerOrder->sub_total),
                    'tax_amount' => float_amount_with_currency_symbol($existingBuyerOrder->tax),
                    'total' => float_amount_with_currency_symbol($existingBuyerOrder->total),
                    'coupon_code' => $existingBuyerOrder->coupon_code,
                    'coupon_type' => $existingBuyerOrder->coupon_type,
                    'coupon_amount' => float_amount_with_currency_symbol($existingBuyerOrder->coupon_amount),
                    'commission_amount' => float_amount_with_currency_symbol($existingBuyerOrder->commission_amount),
                    'success_url' => route('frontend.order.payment.success', $new_order_id),
                    'cancel_url' => route('frontend.order.payment.cancel.static', $existingBuyerOrder->id),
                    'paytm_details' => null
                ]);
            }

            // 3. AUTHORITATIVE CONFLICT CHECK INSIDE TRANSACTION & LOCK BOUNDARY
            if (!$is_service_online_bool && !empty($request->date) && !empty($request->schedule)) {
                $existingOrders = Order::where('seller_id', $request->seller_id)
                    ->where('status', '!=', 4) // exclude cancelled orders
                    ->where(function ($q) {
                        // Confirmed/completed/delivered OR paid orders
                        $q->whereIn('status', [1, 2, 3])
                          ->orWhere('payment_status', 'complete')
                          // Offline orders pending confirmation (COD, manual payment)
                          ->orWhere(function ($q2) {
                              $q2->where('status', 0)
                                 ->whereIn('payment_gateway', ['cash_on_delivery', 'manual_payment'])
                                 ->whereNotIn('payment_status', ['failed', 'canceled']);
                          })
                          // Online orders currently holding the slot while payment is pending (20-minute reservation window)
                          ->orWhere(function ($q3) {
                              $q3->where('status', 0)
                                 ->whereNotIn('payment_gateway', ['cash_on_delivery', 'manual_payment'])
                                 ->where('payment_status', 'pending')
                                 ->where('created_at', '>=', now()->subMinutes(20));
                          });
                    })
                    ->get();

                foreach ($existingOrders as $ord) {
                    if (empty($ord->date)) continue;
                    $ordDateYmd = null;
                    try {
                        $ordDateYmd = \Carbon\Carbon::parse($ord->date)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $ordDateYmd = null;
                    }

                    if ($reqDateYmd && $ordDateYmd && $reqDateYmd === $ordDateYmd) {
                        $ordSlot = $parseSlotSec ? $parseSlotSec($ord->schedule) : null;
                        $hasOverlap = false;

                        if ($reqSlot && $ordSlot) {
                            if ($reqSlot['start'] < $ordSlot['end'] && $reqSlot['end'] > $ordSlot['start']) {
                                $hasOverlap = true;
                            }
                        } elseif (trim($ord->schedule) === trim($request->schedule)) {
                            $hasOverlap = true;
                        }

                        if ($hasOverlap) {
                            return response()->json([
                                'error' => true,
                                'message' => __('This time slot conflicts with an existing booking for this provider. Please choose another time slot.'),
                            ], 422);
                        }
                    }
                }
            }

            // 4. SERVICE DETAILS & PRICING CALCULATIONS
            $service_details = Service::where('id', $request->service_id)->first();
            if (!$service_details) {
                return response()->error(['message' => __('Service not found')]);
            }

            $package_fee = $is_service_online_bool ? $service_details->price : 0;
            $includedServicesData = [];
            if (isset($request->include_services)) {
                $included_services = !empty($request->include_services) ? json_decode($request->include_services, true) : [];
                $items = is_array($included_services) ? current($included_services) : [];
                if (is_array($items)) {
                    foreach ($items as $requested_service) {
                        $package_fee += $requested_service['quantity'] * $requested_service['price'];
                        $includedServicesData[] = [
                            'title' => $requested_service['title'],
                            'price' => $requested_service['price'],
                            'quantity' => $requested_service['quantity'],
                        ];
                    }
                }
            } elseif ($request->is_service_online === 0 && count($request->include_services) < 1) {
                return response()->error([
                    'message' => __('Include service required'),
                ]);
            }

            $extra_service = 0;
            $additionalServicesData = [];
            if (!empty($request->additional_services)) {
                $additional_services = !empty($request->additional_services) ? json_decode($request->additional_services, true) : [];
                $addItems = is_array($additional_services) ? current($additional_services) : [];
                if (is_array($addItems)) {
                    foreach ($addItems as $requested_additional) {
                        $extra_service += $requested_additional['quantity'] * $requested_additional['additional_service_price'];
                        $additionalServicesData[] = [
                            'title' => $requested_additional['additional_service_title'],
                            'price' => $requested_additional['additional_service_price'],
                            'quantity' => $requested_additional['quantity'],
                        ];
                    }
                }
            }

            $tax_amount = 0;
            $service_details_for_book = Service::select('id', 'service_city_id')->where('id', $request->service_id)->first();
            $service_country = optional(optional($service_details_for_book->serviceCity)->countryy)->id;
            $country_tax = Tax::select('id', 'tax')->where('country_id', $service_country)->first();
            $sub_total = $package_fee + $extra_service;
            if (!is_null($country_tax)) {
                $tax_amount = ($sub_total * $country_tax->tax) / 100;
            }
            $total = $sub_total + $tax_amount;

            // Calculate coupon amount
            $coupon_code = '';
            $coupon_type = '';
            $coupon_amount = 0;

            if (!empty($request->coupon_code)) {
                $coupon_obj = ServiceCoupon::where('code', $request->coupon_code)->first();
                $current_date = date('Y-m-d');
                if (!empty($coupon_obj)) {
                    $isAdminCoupon = ($coupon_obj->user_type === 'admin' || is_null($coupon_obj->seller_id));
                    $isSellerMatch = $isAdminCoupon || ((int)$coupon_obj->seller_id === (int)$request->seller_id);
                    $isActive = ((int)$coupon_obj->status === 1);
                    $isValidDate = ($coupon_obj->expire_date >= $current_date);

                    if ($isActive && $isSellerMatch && $isValidDate) {
                        if ($coupon_obj->discount_type == 'percentage') {
                            $coupon_amount = ($total * $coupon_obj->discount) / 100;
                            $total = $total - $coupon_amount;
                            $coupon_code = $request->coupon_code;
                            $coupon_type = 'percentage';
                        } else {
                            $coupon_amount = $coupon_obj->discount;
                            $total = $total - $coupon_amount;
                            $coupon_code = $request->coupon_code;
                            $coupon_type = 'amount';
                        }
                    } else {
                        $coupon_code = '';
                    }
                }
            }

            $total = round($total, 2);

            // Commission amount
            $commission_amount = 0;
            if ($commission && $commission->commission_charge_type == 'percentage') {
                $commission_amount = ($sub_total * $commission->commission_charge) / 100;
            } elseif ($commission) {
                $commission_amount = $commission->commission_charge;
            }

            // 5. WALLET ATOMIC SAFETY & ROW LOCKING
            $shortage_balance = 0;
            $wallet_balance_status = '';
            $walletRow = null;

            if ($request->selected_payment_gateway === 'wallet') {
                if (!$buyer_id) {
                    return response()->json([
                        'error' => true,
                        'message' => __('Authentication required for wallet payment'),
                    ], 401);
                }

                if (moduleExists('Wallet')) {
                    // Exclusive row lock on buyer's wallet row before reading balance
                    $walletRow = Wallet::where(function ($q) use ($buyer_id) {
                        $q->where('buyer_id', $buyer_id)->orWhere('user_id', $buyer_id);
                    })->lockForUpdate()->first();

                    $currentBalance = $walletRow ? (float) $walletRow->balance : 0.0;

                    if (!$walletRow || $currentBalance < (float) $total) {
                        // Insufficient funds: abort transaction without leaving partially created or paid order
                        $shortage = float_amount_with_currency_symbol($total - $currentBalance);
                        return response()->json([
                            'error' => true,
                            'message' => __('Wallet balance not available'),
                            'wallet_balance_status' => __('Wallet balance not available'),
                            'shortage_balance' => $shortage,
                        ], 422);
                    }
                }
            }

            // 6. CREATE ORDER ATOMICALLY WITH EXACT STATUS
            $initialOrderStatus = ($request->selected_payment_gateway === 'wallet') ? 1 : 0;
            $initialPaymentStatus = ($request->selected_payment_gateway === 'wallet') ? 'complete' : $payment_status;

            $order = Order::create([
                'service_id' => $request->service_id,
                'seller_id' => $request->seller_id,
                'buyer_id' => $buyer_id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'post_code' => $request->post_code ?: '00000',
                'address' => $request->address ?: 'N/A',
                'city' => (int) ($request->choose_service_city ?: ($request->city ?: 1)),
                'area' => (int) ($request->choose_service_area ?: ($request->area ?: 1)),
                'country' => (int) ($request->choose_service_country ?: ($request->country ?: 1)),
                'date' => !$is_service_online_bool ? $request->date : '00.00.00',
                'schedule' => !$is_service_online_bool ? $request->schedule : '00.00.00',
                'package_fee' => $package_fee,
                'is_order_online' => $is_service_online_bool ? 1 : '0',
                'extra_service' => $extra_service,
                'sub_total' => $sub_total,
                'tax' => $tax_amount,
                'total' => $total,
                'commission_type' => $commission ? $commission->commission_charge_type : 'percentage',
                'commission_charge' => $commission ? $commission->commission_charge : 0,
                'commission_amount' => $commission_amount,
                'status' => $initialOrderStatus,
                'order_note' => $request->order_note,
                'payment_gateway' => $request->selected_payment_gateway,
                'payment_status' => $initialPaymentStatus,
                'coupon_code' => $coupon_code,
                'coupon_type' => $coupon_type,
                'coupon_amount' => $coupon_amount,
                'idempotency_key' => $idempotencyKey,
            ]);

            $last_order_id = $order->id;

            // Create includes and additional records
            foreach ($includedServicesData as $inc) {
                OrderInclude::create([
                    'order_id' => $last_order_id,
                    'title' => $inc['title'],
                    'price' => $inc['price'],
                    'quantity' => $inc['quantity'],
                ]);
            }

            foreach ($additionalServicesData as $add) {
                OrderAdditional::create([
                    'order_id' => $last_order_id,
                    'title' => $add['title'],
                    'price' => $add['price'],
                    'quantity' => $add['quantity'],
                ]);
            }

            // Increment sold count
            $service_sold_count = Service::select('sold_count')->where('id', $request->service_id)->first();
            Service::where('id', $request->service_id)->update(['sold_count' => ($service_sold_count->sold_count ?? 0) + 1]);

            // Save manual payment image if uploaded
            if ($request->selected_payment_gateway === 'manual_payment') {
                if ($image = $request->file('manual_payment_image')) {
                    $imageName = 'manual_attachment_' . time() . '-' . uniqid() . '.' . $image->getClientOriginalExtension();

                    $uploaded_file = $request->manual_payment_image;
                    $file_extension = $uploaded_file->getClientOriginalExtension();
                    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $processed_image = Image::make($uploaded_file);
                        $image_default_width = $processed_image->width();
                        $image_default_height = $processed_image->height();

                        $processed_image->resize($image_default_width, $image_default_height, function ($constraint) {
                            $constraint->aspectRatio();
                        });
                        $processed_image->save('assets/uploads/manual-payment/' . $imageName);
                    } else {
                        $image->move('assets/uploads/manual-payment/', $imageName);
                    }

                    Order::where('id', $last_order_id)->update([
                        'manual_payment_image' => $imageName
                    ]);
                }
            }

            // Atomically deduct wallet balance and log history
            if ($request->selected_payment_gateway === 'wallet' && $walletRow) {
                $newBalance = round($currentBalance - $total, 2);
                $walletRow->update([
                    'balance' => $newBalance,
                    'total_spent' => round((float) ($walletRow->total_spent ?? 0) + $total, 2),
                ]);

                if (class_exists('Modules\Wallet\Entities\WalletHistory')) {
                    try {
                        $txnId = 'WTX-' . date('Ymd') . '-' . strtoupper(Str::random(10));
                        \Modules\Wallet\Entities\WalletHistory::create([
                            'wallet_id' => $walletRow->id,
                            'user_id' => $buyer_id,
                            'buyer_id' => $buyer_id,
                            'entry_type' => 'debit',
                            'amount' => $total,
                            'balance_before' => $currentBalance,
                            'balance_after' => $newBalance,
                            'payment_gateway' => 'wallet',
                            'payment_status' => 'complete',
                            'status' => 1,
                            'reference_type' => 'service_booking',
                            'reference_id' => (string) $last_order_id,
                            'transaction_id' => $txnId,
                            'description_en' => "Payment for booking #{$last_order_id}",
                            'description_ar' => "دفع قيمة الحجز #{$last_order_id}",
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('[WalletHistory Error] ' . $e->getMessage());
                    }
                }
            }

            // 7. PROVIDER NOTIFICATIONS (ONLY FOR NEW ORDERS THAT ARE IMMEDIATELY ACTIONABLE)
            $order_details = Order::find($last_order_id);

            $isImmediateOrActionablePayment = ($request->selected_payment_gateway === 'cash_on_delivery')
                || ($request->selected_payment_gateway === 'wallet' && $order_details->payment_status === 'complete');

            if ($isImmediateOrActionablePayment) {
                $seller = User::where('id', $request->seller_id)->first();
                $order_message = __('You have a new order');
                if ($seller) {
                    try {
                        $seller->notify(new OrderNotification(
                            $last_order_id,
                            $request->service_id,
                            $request->seller_id,
                            $request->buyer_id,
                            $order_message,
                            'new_booking',
                            $order_details->status,
                            'seller'
                        ));
                    } catch (\Exception $e) {
                        \Log::error('[Order Notification Error] ' . $e->getMessage());
                    }
                }

                try {
                    $mail_subject = get_static_option('new_order_email_subject') ?? __('New Order #');
                    $message_for_buyer = get_static_option('new_order_buyer_message') ?? __('You have successfully placed an order #');
                    $message_for_seller_admin = get_static_option('new_order_admin_seller_message') ?? __('You have a new order #');
                    if (!empty($order_details->email)) {
                        Mail::to($order_details->email)->send(new OrderMail($mail_subject, $order_details, $message_for_buyer));
                    }
                    if ($seller && !empty($seller->email)) {
                        Mail::to($seller->email)->send(new OrderMail($mail_subject, $order_details, $message_for_seller_admin));
                    }
                    if (get_static_option('site_global_email')) {
                        Mail::to(get_static_option('site_global_email'))->send(new OrderMail($mail_subject, $order_details, $message_for_seller_admin));
                    }
                } catch (\Exception $e) {
                    \Log::error('[Order Mail Error] ' . $e->getMessage());
                }
            }

            // 8. RESPONSE URLS & PAYTM CHARGE
            $random_order_id_1 = Str::random(30);
            $random_order_id_2 = Str::random(30);
            $new_order_id = $random_order_id_1 . $last_order_id . $random_order_id_2;
            $paytm_details = null;

            if ($request->has('paytm') && !empty($request->has('paytm'))) {
                $user_info = Auth::guard('sanctum')->user();
                $title = Str::limit(strip_tags($service_details->title), 20);
                $description = sprintf(__('Order id #%1$d Email: %2$s, Name: %3$s'), $last_order_id, $user_info->email, $user_info->name);
                $paytm_details = XgPaymentGateway::paytm()->charge_customer([
                    'amount' => $total,
                    'title' => $title,
                    'description' => $description,
                    'ipn_url' => route('frontend.paytm.ipn'),
                    'order_id' => $last_order_id,
                    'track' => \Str::random(36),
                    'success_url' => route('frontend.order.payment.success', $new_order_id),
                    'cancel_url' => route('frontend.order.payment.cancel.static', $last_order_id),
                    'email' => $user_info->email,
                    'name' => $user_info->name,
                    'payment_type' => 'order',
                ]);
            }

            return response()->success([
                'order_id' => $last_order_id,
                'shortage_balance' => $shortage_balance,
                'wallet_balance_status' => $wallet_balance_status,
                'service_sold_count' => $service_sold_count,
                'package_fee' => float_amount_with_currency_symbol($package_fee),
                'extra_service' => float_amount_with_currency_symbol($extra_service),
                'sub_total' => float_amount_with_currency_symbol($sub_total),
                'tax_amount' => float_amount_with_currency_symbol($tax_amount),
                'total' => float_amount_with_currency_symbol($total),
                'coupon_code' => $coupon_code,
                'coupon_type' => $coupon_type,
                'coupon_amount' => float_amount_with_currency_symbol($coupon_amount),
                'commission_amount' => float_amount_with_currency_symbol($commission_amount),
                'success_url' => route('frontend.order.payment.success', $new_order_id),
                'cancel_url' => route('frontend.order.payment.cancel.static', $last_order_id),
                'paytm_details' => $paytm_details
            ]);
        });
    }

    public function imageUpload(Request $request){
        $this->validate($request, [
            'file' => 'nullable|mimes:jpg,jpeg,png,gif|max:11000'
        ]);
        MediaHelper::insert_media_image($request);
        $last_image_id = DB::getPdo()->lastInsertId();
        return response()->success([
            'image_id'=> $last_image_id,
        ]);
    }

    public function manualPaymentImage(Request $request){
        $request->validate([
            'image' => 'required|mimes:jpeg,jpg,png,bmp'
        ]);

        if(isset($request->order_id)){
            if ($image = $request->file('image')) {
                $imageName = 'manual_attachment_'.time().'-'.uniqid().'.'.$image->getClientOriginalExtension();

                // file scan start
                $uploaded_file = $request->image;
                $file_extension = $uploaded_file->getClientOriginalExtension();
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $processed_image = Image::make($uploaded_file);
                    $image_default_width = $processed_image->width();
                    $image_default_height = $processed_image->height();

                    $processed_image->resize($image_default_width, $image_default_height, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $processed_image->save('assets/uploads/manual-payment/' . $imageName);
                }else{
                    $image->move('assets/uploads/manual-payment/', $imageName);
                } // file scan end

                $update = Order::where('id',$request->order_id)->update([
                    'manual_payment_image'=>$imageName
                ]);
            }
        }
    }
    public function paymentStatusUpdate(Request $request){
          $request->validate([
            'order_id' => 'required|integer'
        ]);
        $order_details = Order::find($request->order_id);

        if (is_null($order_details)) {
            return response()->error(['message' => __('Order not found')]);
        }

        $user_id = Auth::guard("sanctum")->id();
        if ($order_details->buyer_id !== null && $order_details->buyer_id !== $user_id) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized action for this order.')
            ], 403);
        }

        if ($order_details->payment_gateway === 'paytabs') {
            return response()->json([
                'success' => false,
                'message' => __('PayTabs payments must be verified via server verification endpoint.')
            ], 422);
        }

        $order_details->payment_status = 'complete';
        $order_details->save();

        if($request->has('job_id') && $request->job_id === $order_details->job_post_id){
            BuyerJob::where('id',$request->job_id)->update(['status' => 1]);
        }
        return response()->success(['message' => __('payment status update success')]);
        
         
    }
}
