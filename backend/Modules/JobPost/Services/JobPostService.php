<?php

namespace Modules\JobPost\Services;

use App\AdminCommission;
use App\Order;
use App\Tax;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\JobPost\Entities\BuyerJob;
use Modules\JobPost\Entities\JobRequest;
use Modules\JobPost\Entities\JobRequestConversation;
use Modules\Wallet\Services\WalletService;

class JobPostService
{
    /**
     * Create a new job post by a buyer.
     */
    public function createJob(array $data, int $buyerId): BuyerJob
    {
        $slugBase = $data['slug'] ?? $data['title'];
        $slug = Str::slug($slugBase);
        $count = BuyerJob::where('slug', 'like', $slug . '%')->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        return BuyerJob::create([
            'category_id' => $data['category_id'] ?? $data['category'] ?? 1,
            'subcategory_id' => $data['subcategory_id'] ?? $data['subcategory'] ?? null,
            'child_category_id' => $data['child_category_id'] ?? null,
            'buyer_id' => $buyerId,
            'country_id' => $data['country_id'] ?? 0,
            'city_id' => $data['city_id'] ?? 0,
            'title' => $data['title'],
            'slug' => $slug,
            'description' => $data['description'] ?? '',
            'image' => $data['image'] ?? null,
            'is_job_online' => !empty($data['is_job_online']) ? 1 : 0,
            'price' => $data['price'] ?? 0.00,
            'dead_line' => $data['dead_line'] ?? now()->addDays(7),
            'view' => 0,
            'is_job_on' => 1,
            'status' => 1,
        ]);
    }

    /**
     * Submit a proposal / bid by a seller on a job.
     */
    public function submitProposal(array $data, int $sellerId): JobRequest
    {
        $job = BuyerJob::findOrFail($data['job_post_id']);

        if ($job->buyer_id === $sellerId) {
            throw new \Exception(__('You cannot apply for your own job.'));
        }

        if ($job->status !== 1 || $job->is_job_on !== 1) {
            throw new \Exception(__('This job is not currently accepting applications.'));
        }

        $existing = JobRequest::where('job_post_id', $job->id)
            ->where('seller_id', $sellerId)
            ->first();

        if ($existing) {
            throw new \Exception(__('You have already submitted a proposal for this job.'));
        }

        return JobRequest::create([
            'job_post_id' => $job->id,
            'buyer_id' => $job->buyer_id,
            'seller_id' => $sellerId,
            'expected_salary' => $data['expected_salary'],
            'cover_letter' => $data['cover_letter'] ?? '',
            'is_hired' => 0,
            'is_rejected' => 0,
            'status' => 0,
        ]);
    }

    /**
     * Hire a seller for a job with atomic transaction, wallet deduction, and order creation.
     */
    public function hireSeller(int $jobRequestId, int $buyerId, string $paymentGateway = 'wallet'): Order
    {
        return DB::transaction(function () use ($jobRequestId, $buyerId, $paymentGateway) {
            $proposal = JobRequest::with(['job', 'seller'])
                ->lockForUpdate()
                ->where('id', $jobRequestId)
                ->where('buyer_id', $buyerId)
                ->firstOrFail();

            if ($proposal->is_hired === 1) {
                throw new \Exception(__('A seller has already been hired for this proposal.'));
            }

            $job = BuyerJob::lockForUpdate()->find($proposal->job_post_id);
            if (!$job) {
                throw new \Exception(__('Job post not found.'));
            }

            // Calculate commission
            $adminCommission = AdminCommission::first();
            $commissionType = $adminCommission ? $adminCommission->commission_charge_type : 'percentage';
            $commissionCharge = $adminCommission ? $adminCommission->commission_charge : 0;
            $commissionAmount = ($commissionType === 'percentage')
                ? ($proposal->expected_salary * $commissionCharge) / 100
                : $commissionCharge;

            // Calculate tax
            $taxAmount = 0.00;
            if (!empty($job->country_id)) {
                $countryTax = Tax::where('country_id', $job->country_id)->value('tax');
                if ($countryTax) {
                    $taxAmount = ($proposal->expected_salary * $countryTax) / 100;
                }
            }

            $total = round($proposal->expected_salary + $taxAmount, 2);

            $buyer = User::find($buyerId);

            // Create corresponding Order
            $order = Order::create([
                'service_id' => 0,
                'seller_id' => $proposal->seller_id,
                'buyer_id' => $buyerId,
                'name' => $buyer && !empty($buyer->name) ? $buyer->name : 'Buyer',
                'email' => $buyer && !empty($buyer->email) ? $buyer->email : 'buyer@funmoment.test',
                'phone' => $buyer && !empty($buyer->phone) ? $buyer->phone : '0500000000',
                'post_code' => $buyer && !empty($buyer->post_code) ? $buyer->post_code : '0000',
                'address' => $buyer && !empty($buyer->address) ? $buyer->address : 'Main St',
                'city' => !empty($buyer?->service_city) ? (int)$buyer->service_city : (!empty($job->city_id) ? (int)$job->city_id : 1),
                'area' => !empty($buyer?->service_area) ? (int)$buyer->service_area : 1,
                'country' => !empty($buyer?->country_id) ? (int)$buyer->country_id : (!empty($job->country_id) ? (int)$job->country_id : 1),
                'date' => now()->toDateString(),
                'schedule' => 'Job Contract',
                'package_fee' => 0,
                'extra_service' => 0,
                'sub_total' => $total,
                'tax' => $taxAmount,
                'total' => $total,
                'commission_type' => $commissionType,
                'commission_charge' => $commissionCharge,
                'commission_amount' => $commissionAmount,
                'status' => 0, // In progress / pending seller acceptance
                'payment_gateway' => $paymentGateway,
                'payment_status' => $paymentGateway === 'wallet' ? 'complete' : 'pending',
                'order_from_job' => 'yes',
                'job_post_id' => $job->id,
                'is_order_online' => $job->is_job_online,
            ]);

            $order->invoice = 'INV' . $order->id;
            $order->save();

            // Handle wallet payment with atomic double-entry deduction
            if ($paymentGateway === 'wallet') {
                $walletService = app(WalletService::class);
                $walletService->debit(
                    $buyerId,
                    $total,
                    'job_hire',
                    'job_' . $job->id . '_order_' . $order->id,
                    'Escrow payment for job hire: ' . $job->title . ' (Order #' . $order->id . ')'
                );
            }

            // Mark proposal as hired
            $proposal->is_hired = 1;
            $proposal->status = 1;
            $proposal->save();

            // Mark job status as hired/in progress
            $job->status = 2; // hired
            $job->is_job_on = 0; // stop taking new bids
            $job->save();

            return $order;
        });
    }

    /**
     * Send a conversation negotiation message.
     */
    public function sendMessage(int $jobRequestId, string $type, string $message, ?string $attachment = null): JobRequestConversation
    {
        return JobRequestConversation::create([
            'job_request_id' => $jobRequestId,
            'type' => $type,
            'message' => $message,
            'attachment' => $attachment,
            'notify' => 'off',
        ]);
    }

    /**
     * Get Admin metrics for jobs.
     */
    public function getAdminMetrics(): array
    {
        return [
            'total_jobs' => BuyerJob::count(),
            'open_jobs' => BuyerJob::where('status', 1)->where('is_job_on', 1)->count(),
            'hired_jobs' => BuyerJob::where('status', 2)->count(),
            'total_proposals' => JobRequest::count(),
            'hired_proposals' => JobRequest::where('is_hired', 1)->count(),
        ];
    }
}
