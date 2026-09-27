<?php

namespace App\Http\Controllers;

use App\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\JobPost\Entities\BuyerJob;
use Modules\JobPost\Entities\JobRequest;
use Modules\JobPost\Services\JobPostService;

class AdminJobApiController extends Controller
{
    protected JobPostService $jobService;

    public function __construct(JobPostService $jobService)
    {
        $this->middleware('auth:admin');
        $this->jobService = $jobService;
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status'); // 'all', 'open', 'hired', 'paused'

        $query = BuyerJob::with(['buyer:id,name,email,phone', 'category:id,name'])
            ->withCount('job_requests');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('buyer', function ($bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== null && $status !== 'all') {
            if ($status === 'open') {
                $query->where('status', 1)->where('is_job_on', 1);
            } elseif ($status === 'hired') {
                $query->where('status', 2);
            } elseif ($status === 'paused') {
                $query->where(function ($q) {
                    $q->where('status', 0)->orWhere('is_job_on', 0);
                });
            }
        }

        $jobs = $query->orderBy('id', 'desc')->paginate(15);
        $metrics = $this->jobService->getAdminMetrics();

        return response()->json([
            'status' => 'success',
            'data' => [
                'metrics' => $metrics,
                'jobs' => $jobs,
            ],
        ], 200);
    }

    public function show(int $id): JsonResponse
    {
        $job = BuyerJob::with(['buyer:id,name,email,phone', 'category:id,name', 'subcategory:id,name', 'city:id,service_city'])
            ->withCount('job_requests')
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $job,
        ], 200);
    }

    public function proposals(int $id): JsonResponse
    {
        $proposals = JobRequest::with(['seller:id,name,email,phone', 'conversations'])
            ->where('job_post_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $proposals,
        ], 200);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|integer|in:0,1,2,3',
            'is_job_on' => 'nullable|integer|in:0,1',
        ]);

        $job = BuyerJob::findOrFail($id);
        $admin = Auth::guard('admin')->user();

        $oldStatus = $job->status;
        $oldIsOn = $job->is_job_on;

        if ($request->has('status')) {
            $job->status = (int) $request->status;
        }

        if ($request->has('is_job_on')) {
            $job->is_job_on = (int) $request->is_job_on;
        }

        $job->save();

        AdminAuditLog::record([
            'action' => 'job_status_update',
            'resource_type' => 'buyer_job',
            'resource_id' => $job->id,
            'details_en' => "Updated status of job #{$job->id} ({$job->title}) to {$job->status}",
            'details_ar' => "تم تحديث حالة الوظيفة #{$job->id} ({$job->title}) إلى {$job->status}",
            'old_values' => [
                'status' => $oldStatus,
                'is_job_on' => $oldIsOn,
            ],
            'new_values' => [
                'status' => $job->status,
                'is_job_on' => $job->is_job_on,
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Job status updated successfully.',
            'data' => $job,
        ], 200);
    }
}
