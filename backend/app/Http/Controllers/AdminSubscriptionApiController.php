<?php

namespace App\Http\Controllers;

use App\AdminAuditLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscription\Entities\SellerSubscription;
use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\SubscriptionHistory;
use Modules\Subscription\Services\SubscriptionService;

class AdminSubscriptionApiController extends Controller
{
    protected SubscriptionService $subService;

    public function __construct(SubscriptionService $subService)
    {
        $this->middleware('auth:admin');
        $this->subService = $subService;
    }

    public function index(): JsonResponse
    {
        $metrics = $this->subService->getAdminOverview();
        $plans = Subscription::orderBy('price', 'asc')->get();
        $subscribers = SellerSubscription::with(['seller:id,name,email,phone', 'subscription:id,title'])
            ->orderBy('id', 'desc')
            ->paginate(15);
        $recentHistories = SubscriptionHistory::with(['seller:id,name,email', 'subscription:id,title'])
            ->orderBy('id', 'desc')
            ->take(20)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'metrics' => $metrics,
                'plans' => $plans,
                'subscribers' => $subscribers,
                'recent_histories' => $recentHistories,
            ],
        ], 200);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'type' => 'required|string|in:monthly,yearly,lifetime',
            'price' => 'required|numeric|min:0',
            'connect' => 'required|integer|min:0',
            'service' => 'required|integer|min:0',
            'job' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|integer|in:0,1',
        ]);

        $plan = Subscription::create([
            'title' => $request->title,
            'type' => $request->type,
            'price' => $request->price,
            'connect' => $request->connect,
            'service' => $request->service,
            'job' => $request->job,
            'description' => $request->description,
            'status' => $request->status ?? 1,
        ]);

        AdminAuditLog::record([
            'action' => 'subscription_plan_create',
            'resource_type' => 'subscription_plan',
            'resource_id' => $plan->id,
            'details_en' => "Created new subscription tier: {$plan->title} ({$plan->price} SAR)",
            'details_ar' => "تم إنشاء باقة اشتراك جديدة: {$plan->title} ({$plan->price} ر.س)",
            'new_values' => $plan->toArray(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subscription plan created successfully.',
            'data' => $plan,
        ], 201);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'type' => 'required|string|in:monthly,yearly,lifetime',
            'price' => 'required|numeric|min:0',
            'connect' => 'required|integer|min:0',
            'service' => 'required|integer|min:0',
            'job' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|integer|in:0,1',
        ]);

        $plan = Subscription::findOrFail($id);
        $oldValues = $plan->toArray();

        $plan->update([
            'title' => $request->title,
            'type' => $request->type,
            'price' => $request->price,
            'connect' => $request->connect,
            'service' => $request->service,
            'job' => $request->job,
            'description' => $request->description,
            'status' => $request->status ?? $plan->status,
        ]);

        AdminAuditLog::record([
            'action' => 'subscription_plan_update',
            'resource_type' => 'subscription_plan',
            'resource_id' => $plan->id,
            'details_en' => "Updated subscription tier: {$plan->title}",
            'details_ar' => "تم تعديل باقة الاشتراك: {$plan->title}",
            'old_values' => $oldValues,
            'new_values' => $plan->toArray(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subscription plan updated successfully.',
            'data' => $plan,
        ], 200);
    }

    public function togglePlanStatus(Request $request, int $id): JsonResponse
    {
        $plan = Subscription::findOrFail($id);
        $oldStatus = $plan->status;
        $plan->status = $plan->status === 1 ? 0 : 1;
        $plan->save();

        AdminAuditLog::record([
            'action' => 'subscription_plan_status_toggle',
            'resource_type' => 'subscription_plan',
            'resource_id' => $plan->id,
            'details_en' => "Toggled status of plan #{$plan->id} to {$plan->status}",
            'details_ar' => "تم تغيير حالة باقة الاشتراك #{$plan->id} إلى {$plan->status}",
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $plan->status],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Plan status updated.',
            'data' => $plan,
        ], 200);
    }

    public function adjustSubscriber(Request $request, int $sellerId): JsonResponse
    {
        $request->validate([
            'connect' => 'nullable|integer',
            'service' => 'nullable|integer',
            'job' => 'nullable|integer',
            'days_to_add' => 'nullable|integer',
            'admin_note' => 'required|string|min:5',
        ]);

        $sub = SellerSubscription::where('seller_id', $sellerId)->firstOrFail();
        $oldValues = $sub->toArray();

        if ($request->has('connect')) {
            $sub->connect = max(0, (int) $request->connect);
        }
        if ($request->has('service')) {
            $sub->service = max(0, (int) $request->service);
        }
        if ($request->has('job')) {
            $sub->job = max(0, (int) $request->job);
        }
        if ($request->has('days_to_add') && (int) $request->days_to_add !== 0) {
            $baseDate = ($sub->expire_date && $sub->expire_date->isFuture()) ? $sub->expire_date : Carbon::now();
            $sub->expire_date = $baseDate->copy()->addDays((int) $request->days_to_add);
            $sub->status = 1;
        }

        $sub->save();

        AdminAuditLog::record([
            'action' => 'subscriber_quota_adjustment',
            'resource_type' => 'seller_subscription',
            'resource_id' => $sub->id,
            'details_en' => "Adjusted subscription quotas for seller #{$sellerId}. Note: {$request->admin_note}",
            'details_ar' => "تم تعديل حصص اشتراك المزود #{$sellerId}. ملاحظة: {$request->admin_note}",
            'old_values' => $oldValues,
            'new_values' => $sub->toArray(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subscriber quotas adjusted successfully.',
            'data' => $sub,
        ], 200);
    }
}
