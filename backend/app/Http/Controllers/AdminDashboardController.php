<?php

namespace App\Http\Controllers;

use App\Admin;
use App\AdminCommission;
use App\Category;
use App\Helpers\FlashMsg;
use App\Helpers\LanguageHelper;
use App\Language;
use App\Mail\BasicMail;
use App\User;
use App\Order;
use App\Service;
use App\PayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use DB;
use Illuminate\Support\Facades\Mail;
use Modules\Subscription\Entities\SubscriptionHistory;
use function GuzzleHttp\Promise\all;

class AdminDashboardController extends Controller
{
    public function __construct() {
        $this->middleware('auth:admin');
        $this->middleware('permission:appearance-menubar-settings',['only'=>['menubar_settings','update_menubar_settings']]);
        $this->middleware('permission:appearance-home-variant',['only'=>['home_variant','update_home_variant']]);
    }

    public function adminIndex()
    {

        $total_admin = Admin::count();
        $total_seller = User::where('user_type',0)->count();
        $total_buyer = User::where('user_type',1)->count();


        // Check Admin Commission
        $admin_commmission = AdminCommission::first();
        if(($admin_commmission->system_type ?? null) == 'subscription'){
            if(moduleExists("Subscription")){
                $total_earning = SubscriptionHistory::where('payment_status', 'complete')->sum('price');
            }else{
                $total_earning = 0;
            }
            $total_tax = Order::where('status', 2)->sum('tax');
        }else{
            $total_earning = Order::where('status',2)->sum('commission_amount');
            $total_tax = Order::where('status', 2)->sum('tax');
        }

        $pending_order = Order::where('status',0)->count();
        $cancel_order = Order::where('status',4)->count();

        $pending_service = Service::where('status',0)->count();
        $total_service = Service::count();
        $total_order = Order::count();

        $pending_payout_request = PayoutRequest::where('status',0)->count();
        $new_user_today = User::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at',Carbon::now()->month)
            ->whereDay('created_at',Carbon::now()->day)
            ->count();


        $most_viewed_10_services = Service::select('id','title','price','view')
        ->where(['status'=>1,'is_service_on'=>1])
        ->orderByDesc('view')
        ->take(10)
        ->get();

        $most_sell_10_services = Order::selectRaw('service_id, COUNT(orders.service_id) as total')
            ->with('service')
            ->groupBy('service_id')
            ->orderBy('total','desc')
            ->take(10)
            ->get();


        //get last 12 months data
        $month_list = [];
        $monthly_income_list = [];
        $monthly_order_list = [];

        for($i= 0; $i < 12; $i++){

            $month = \Carbon\Carbon::parse(date('Y') . '-01-01')->addMonth($i);
            $month_list[] = $month->shortMonthName;

            $monthly_income_list[] = Order::where('status',2)
            ->whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', $month)
            ->sum('commission_amount');

            $monthly_order_list[] = Order::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', $month)
            ->count();
        }


        //get last 30 days data
        $currentDateTime = Carbon::now()->format("d M");
        $days_list = [];
        $daily_income_list = [];
        $daily_order_list = [];

        for ($i=29; $i >= 0; $i--) { 
            $days_list[] = Carbon::parse($currentDateTime)->subDay($i)->format("d M");
            $daily_income_list[] = Order::where('status',2)
            ->whereDay('created_at',Carbon::now()
            ->subDay($i))
            ->sum('commission_amount');
            $daily_order_list[] = Order::whereYear('created_at', Carbon::now()->year)
            ->whereDay('created_at',Carbon::now()->subDay($i))
            ->count();
        }

        return view('backend.admin-home',compact(
            'total_admin',
            'total_seller',
            'total_buyer',
            'total_earning',
            'total_tax',
            'pending_order',
            'cancel_order',
            'pending_service',
            'pending_payout_request',
            'new_user_today',
            'most_viewed_10_services',
            'most_sell_10_services',
            'month_list',
            'days_list',
            'daily_income_list',
            'monthly_income_list',
            'monthly_order_list',
            'daily_order_list',
            'total_service',
            'total_order',
        ));
    }

    public function dashboardSummary(): JsonResponse
    {
        $summary = $this->buildDashboardSummary();

        return response()->json([
            'success' => true,
            'message_en' => 'Dashboard summary loaded successfully.',
            'message_ar' => 'تم تحميل ملخص لوحة التحكم بنجاح.',
            'data' => $summary,
        ]);
    }

    public function authCheck(): JsonResponse
    {
        $admin = Auth::guard('admin')->user();

        return response()->json([
            'success' => true,
            'message_en' => 'Admin session is active.',
            'message_ar' => 'جلسة المدير نشطة.',
            'data' => [
                'authenticated' => true,
                'admin' => [
                    'id' => $admin?->id,
                    'name' => $admin?->name,
                    'email' => $admin?->email,
                    'role' => method_exists($admin, 'getRoleNames') ? $admin->getRoleNames()->first() : null,
                ],
            ],
        ]);
    }

    public function settingsJson(): JsonResponse
    {
        $commission = AdminCommission::first();
        $baseCurrency = (string) (get_static_option('site_global_currency', 'SAR') ?? 'SAR');
        $exchangeRateKey = 'site_' . strtolower($baseCurrency) . '_to_usd_exchange_rate';

        return response()->json([
            'success' => true,
            'message_en' => 'Settings loaded successfully.',
            'message_ar' => 'تم تحميل الإعدادات بنجاح.',
            'data' => [
                'commission_percentage' => (float) ($commission->commission_charge ?? 15.0),
                'min_payout_amount' => (float) (get_static_option('min_payout_amount', 500) ?? 500),
                'maintenance_mode' => in_array(strtolower((string) get_static_option('site_maintenance_mode', 'off')), ['on', '1', 'true', 'yes'], true),
                'required_app_version' => (string) (get_static_option('required_app_version', '2.4.1') ?? '2.4.1'),
                'base_currency' => $baseCurrency,
                'exchange_rate_usd' => (float) (get_static_option($exchangeRateKey, 3.75) ?? 3.75),
            ],
        ]);
    }

    public function updateSettingsJson(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commission_percentage' => 'required|numeric|min:0',
            'min_payout_amount' => 'required|numeric|min:0',
            'maintenance_mode' => 'required|boolean',
            'required_app_version' => 'required|string|max:50',
            'base_currency' => 'required|string|max:10',
            'exchange_rate_usd' => 'required|numeric|min:0',
        ]);

        $commission = AdminCommission::first();
        if ($commission) {
            $commission->commission_charge_type = 'percentage';
            $commission->commission_charge = $validated['commission_percentage'];
            $commission->save();
            Cache::forget('admin_commission_data');
        } else {
            AdminCommission::create([
                'system_type' => 'commission',
                'commission_charge_type' => 'percentage',
                'commission_charge' => $validated['commission_percentage'],
            ]);
        }

        update_static_option('min_payout_amount', $validated['min_payout_amount']);
        update_static_option('site_maintenance_mode', $validated['maintenance_mode'] ? 'on' : 'off');
        update_static_option('required_app_version', $validated['required_app_version']);
        update_static_option('site_global_currency', $validated['base_currency']);
        update_static_option('site_' . strtolower($validated['base_currency']) . '_to_usd_exchange_rate', $validated['exchange_rate_usd']);

        return response()->json([
            'success' => true,
            'message_en' => 'Settings updated successfully.',
            'message_ar' => 'تم تحديث الإعدادات بنجاح.',
            'data' => [
                'commission_percentage' => (float) $validated['commission_percentage'],
                'min_payout_amount' => (float) $validated['min_payout_amount'],
                'maintenance_mode' => (bool) $validated['maintenance_mode'],
                'required_app_version' => $validated['required_app_version'],
                'base_currency' => $validated['base_currency'],
                'exchange_rate_usd' => (float) $validated['exchange_rate_usd'],
            ],
        ]);
    }

    public function lang_change_backend(Request $request)
    {
        $data = $request->lang ?? LanguageHelper::default_slug();
        return response()->json($data);
    }

    public function admin_settings(){
        return view('auth.admin.settings');
    }

    public function admin_profile_update(Request $request){
        $this->validate($request,[
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'image' => 'nullable|string',
             'description'=> 'nullable',
            'designation'=> 'nullable'
        ]);

        Admin::find(Auth::user()->id)->update([
            'name'=>$request->name,
            'email' => $request->email ,
            'image' => $request->image ,
            'description' => $request->description ,
            'designation' => $request->designation
        ]);
        return redirect()->back()->with(['msg' => __('Profile Update Success' ), 'type' => 'success']);
    }

    public function admin_password_chagne(Request $request){
        $this->validate($request, [
            'old_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed'
        ]);

        $user = Admin::findOrFail(Auth::guard('admin')->user()->id);

        if (Hash::check($request->old_password ,$user->password)){

            $user->password = Hash::make($request->password);
            $user->save();
            Auth::logout();

            return redirect()->route('admin.login')->with(['msg'=> __('Password Changed Successfully'),'type'=> 'success']);
        }

        return redirect()->back()->with(['msg'=> __('Somethings Going Wrong! Please Try Again or Check Your Old Password'),'type'=> 'danger']);
    }

    public function adminLogout(){
        Auth::logout();
        return redirect()->route('admin.login')->with(['msg'=>__('You Logged Out !!'),'type'=> 'danger']);
    }

    public function admin_profile(){
        return view('auth.admin.edit-profile');
    }

    public function admin_password(){
        return view('auth.admin.change-password');
    }

    public function menubar_settings(){
        $all_languages = Language::all();
        return view('backend.pages.menubar-settings')->with(['all_languages' => $all_languages]);;
    }

    public function update_menubar_settings(Request $request){
        $all_languages = Language::all();

        foreach ($all_languages as $lang){
            $this->validate($request, [
                'menubar_button_'.$lang->slug.'_text'=> 'nullable|string',
                'menubar_button_'.$lang->slug.'_url'=> 'nullable|string',
            ]);

            $fields = [
                'menubar_button_'.$lang->slug.'_text',
                'menubar_button_'.$lang->slug.'_url',
            ];
            foreach ($fields as $field){
                if ($request->has($field)){
                    update_static_option($field,$request->$field);
                }
            }
            update_static_option('menubar_button',$request->menubar_button);
        }

        return redirect()->back()->with(FlashMsg::settings_update());
    }

    public function cache_settings(){
          return view('backend.general-settings.cache-settings');
    }

    public function update_cache_settings(Request $request){

         $this->validate($request,[
            'cache_type' => 'required|string'
        ]);

        Artisan::call($request->cache_type.':clear');

        return redirect()->back()->with(['msg'=> __('Cache Cleaned...') ,'type'=> 'success']);
    }

    public function dark_mode_toggle(Request $request){
        
        $data = get_static_option('site_admin_dark_mode');
        if($request->mode == 'off' || empty($data)){
            update_static_option('site_admin_dark_mode','on');
        }
        if($request->mode == 'on'){
            update_static_option('site_admin_dark_mode','off');
        }

        return response()->json(['status'=>'done']);
    }

    // buyer seller dashboard variant
    public function dashboard_variant()
    {
        return view('backend.theme-variant.dashboard-variant');
    }

    public function update_dashboard_variant_buyer_seller(Request $request)
    {

            $this->validate($request, [
                'start_week_from' => 'required'
            ]);
            update_static_option('start_week_from', $request->start_week_from);
            return redirect()->back()->with(['msg' => __('Updated..'), 'type' => 'success']);

    }

    private function buildDashboardSummary(): array
    {
        $adminCommission = AdminCommission::first();
        $commissionType = $adminCommission->system_type ?? null;

        $currentRevenue = $commissionType === 'subscription'
            ? (moduleExists('Subscription') ? SubscriptionHistory::where('payment_status', 'complete')->sum('price') : 0)
            : Order::where('status', 2)->sum('commission_amount');

        $previousRevenue = $commissionType === 'subscription'
            ? (moduleExists('Subscription') ? SubscriptionHistory::where('payment_status', 'complete')->whereBetween('created_at', [now()->subDays(60), now()->subDays(31)])->sum('price') : 0)
            : Order::where('status', 2)->whereBetween('created_at', [now()->subDays(60), now()->subDays(31)])->sum('commission_amount');

        $totalOrders = Order::count();
        $currentOrders = Order::whereBetween('created_at', [now()->subDays(30), now()])->count();
        $previousOrders = Order::whereBetween('created_at', [now()->subDays(60), now()->subDays(31)])->count();

        $currentServices = Service::where(['status' => 1, 'is_service_on' => 1])->count();
        $previousServices = Service::where(['status' => 1, 'is_service_on' => 1])->whereBetween('created_at', [now()->subDays(60), now()->subDays(31)])->count();

        $totalUsers = User::whereIn('user_type', [0, 1])->count();
        $currentUsers = User::whereIn('user_type', [0, 1])->whereBetween('created_at', [now()->subDays(30), now()])->count();
        $previousUsers = User::whereIn('user_type', [0, 1])->whereBetween('created_at', [now()->subDays(60), now()->subDays(31)])->count();

        $chartSeries = $this->buildChartSeries();
        $categoryDistribution = $this->buildCategoryDistribution($currentServices);

        return [
            'overview_stats' => [
                'total_revenue' => (float) $currentRevenue,
                'revenue_growth' => $this->calculateGrowth($currentRevenue, $previousRevenue),
                'total_orders' => $totalOrders,
                'orders_growth' => $this->calculateGrowth($currentOrders, $previousOrders),
                'active_services' => $currentServices,
                'services_growth' => $this->calculateGrowth($currentServices, $previousServices),
                'total_users' => $totalUsers,
                'users_growth' => $this->calculateGrowth($currentUsers, $previousUsers),
            ],
            'chart_series' => $chartSeries,
            'activity_logs' => $this->buildActivityLogs(),
            'category_distribution' => $categoryDistribution,
            'system_health' => [
                'laravel_uptime' => 'running',
                'db_connection_status' => 'healthy',
                'active_jobs' => $this->countActiveJobsSafely(),
            ],
        ];
    }

    private function countActiveJobsSafely(): int
    {
        $buyerJobPath = base_path('Modules/JobPost/Entities/BuyerJob.php');
        $sellerJobPath = base_path('Modules/JobPost/Entities/SellerJob.php');

        if (file_exists($buyerJobPath) && class_exists(\Modules\JobPost\Entities\BuyerJob::class)) {
            return (int) \Modules\JobPost\Entities\BuyerJob::count();
        }

        if (file_exists($sellerJobPath) && class_exists(\Modules\JobPost\Entities\SellerJob::class)) {
            return (int) \Modules\JobPost\Entities\SellerJob::count();
        }

        return 0;
    }

    private function buildCategoryDistribution(int $activeServicesCount): array
    {
        if ($activeServicesCount <= 0) {
            return [];
        }

        return Category::select('id', 'name')
            ->withCount('services')
            ->where('status', 1)
            ->orderByDesc('services_count')
            ->take(6)
            ->get()
            ->map(function (Category $category) use ($activeServicesCount) {
                $percentage = $activeServicesCount > 0 ? round(($category->services_count / $activeServicesCount) * 100, 1) : 0;

                return [
                    'category_id' => $category->id,
                    'name_en' => $category->name,
                    'name_ar' => $category->name,
                    'percentage' => $percentage,
                    'order_count' => $category->services_count,
                ];
            })
            ->values()
            ->all();
    }

    private function buildActivityLogs(): array
    {
        $logs = [];

        foreach (Order::latest()->take(2)->get() as $order) {
            $logs[] = [
                'id' => 'order-'.$order->id,
                'timestamp' => optional($order->created_at)->diffForHumans(),
                'textEn' => sprintf('Order #%s updated with status %s', $order->id, $order->status),
                'textAr' => sprintf('تم تحديث الطلب رقم %s بالحالة %s', $order->id, $order->status),
            ];
        }

        foreach (Service::latest()->take(1)->get() as $service) {
            $logs[] = [
                'id' => 'service-'.$service->id,
                'timestamp' => optional($service->created_at)->diffForHumans(),
                'textEn' => sprintf('Service "%s" was created or updated', $service->title),
                'textAr' => sprintf('تم إنشاء الخدمة أو تحديثها: %s', $service->title),
            ];
        }

        foreach (User::latest()->take(1)->get() as $user) {
            $logs[] = [
                'id' => 'user-'.$user->id,
                'timestamp' => optional($user->created_at)->diffForHumans(),
                'textEn' => sprintf('New user account registered: %s', $user->name),
                'textAr' => sprintf('تم تسجيل حساب جديد للمستخدم: %s', $user->name),
            ];
        }

        return array_slice($logs, 0, 4);
    }

    private function buildChartSeries(): array
    {
        $series = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();

            $series[] = [
                'date' => $date->toDateString(),
                'labelEn' => $date->format('D'),
                'labelAr' => $date->locale('ar')->translatedFormat('D'),
                'revenue' => (float) Order::where('status', 2)
                    ->whereDate('created_at', $date)
                    ->sum('commission_amount'),
                'orders' => (int) Order::whereDate('created_at', $date)->count(),
            ];
        }

        return $series;
    }

    private function calculateGrowth(float|int $current, float|int $previous): float
    {
        if ((float) $previous <= 0) {
            return 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

}
