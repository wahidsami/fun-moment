<?php

namespace Modules\Subscription\Services;

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Subscription\Entities\SellerSubscription;
use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\SubscriptionHistory;
use Modules\Wallet\Services\WalletService;

class SubscriptionService
{
    /**
     * Subscribe a seller to a plan or renew existing subscription.
     */
    public function subscribeOrRenew(int $sellerId, int $planId, string $paymentGateway = 'wallet'): SellerSubscription
    {
        return DB::transaction(function () use ($sellerId, $planId, $paymentGateway) {
            $plan = Subscription::where('id', $planId)->where('status', 1)->firstOrFail();
            $seller = User::findOrFail($sellerId);

            $price = (float) $plan->price;

            // Handle wallet payment if applicable
            if ($paymentGateway === 'wallet' && $price > 0) {
                $walletService = app(WalletService::class);
                $walletService->debit(
                    $sellerId,
                    $price,
                    'subscription_purchase',
                    'sub_plan_' . $plan->id . '_user_' . $sellerId . '_' . time(),
                    'Subscription purchase/renewal: ' . $plan->title
                );
            }

            // Calculate expiration date
            $now = Carbon::now();
            if ($plan->type === 'monthly') {
                $expireDate = $now->copy()->addDays(30);
            } elseif ($plan->type === 'yearly') {
                $expireDate = $now->copy()->addDays(365);
            } elseif ($plan->type === 'lifetime') {
                $expireDate = $now->copy()->addDays(3650);
            } else {
                $expireDate = $now->copy()->addDays(30);
            }

            $currentSub = SellerSubscription::where('seller_id', $sellerId)->first();

            $connectToAdd = $plan->type === 'lifetime' ? 1000000 : (int) $plan->connect;
            $serviceToAdd = (int) $plan->service;
            $jobToAdd = (int) $plan->job;

            $newConnect = $currentSub ? ($currentSub->connect + $connectToAdd) : $connectToAdd;
            $newService = $currentSub ? ($currentSub->service + $serviceToAdd) : $serviceToAdd;
            $newJob = $currentSub ? ($currentSub->job + $jobToAdd) : $jobToAdd;

            $sellerSub = SellerSubscription::updateOrCreate(
                ['seller_id' => $sellerId],
                [
                    'subscription_id' => $plan->id,
                    'type' => $plan->type,
                    'price' => $price,
                    'connect' => $newConnect,
                    'service' => $newService,
                    'job' => $newJob,
                    'initial_connect' => $connectToAdd,
                    'initial_service' => $serviceToAdd,
                    'initial_job' => $jobToAdd,
                    'expire_date' => $expireDate,
                    'payment_gateway' => $paymentGateway,
                    'payment_status' => 'complete',
                    'status' => 1,
                ]
            );

            // Record history
            SubscriptionHistory::create([
                'seller_id' => $sellerId,
                'subscription_id' => $plan->id,
                'type' => $plan->type,
                'price' => $price,
                'connect' => $connectToAdd,
                'service' => $serviceToAdd,
                'job' => $jobToAdd,
                'expire_date' => $expireDate,
                'payment_gateway' => $paymentGateway,
                'payment_status' => 'complete',
                'status' => 1,
            ]);

            return $sellerSub;
        });
    }

    /**
     * Check if seller has valid subscription entitlement and remaining quota.
     */
    public function checkEntitlement(int $sellerId, string $quotaType = 'connect'): bool
    {
        $sub = SellerSubscription::where('seller_id', $sellerId)->where('status', 1)->first();
        if (!$sub) {
            return false;
        }

        if ($sub->isExpired()) {
            return false;
        }

        if ($quotaType === 'connect') {
            return $sub->connect > 0;
        }

        if ($quotaType === 'service') {
            return $sub->service > 0;
        }

        if ($quotaType === 'job') {
            return $sub->job > 0;
        }

        return true;
    }

    /**
     * Consume connect quota for a seller.
     */
    public function consumeConnect(int $sellerId, int $amount = 1): bool
    {
        $sub = SellerSubscription::where('seller_id', $sellerId)->lockForUpdate()->first();
        if (!$sub || $sub->connect < $amount) {
            return false;
        }

        $sub->connect -= $amount;
        $sub->save();
        return true;
    }

    /**
     * Admin Overview Metrics.
     */
    public function getAdminOverview(): array
    {
        return [
            'total_plans' => Subscription::count(),
            'active_subscribers' => SellerSubscription::where('status', 1)->where('expire_date', '>', now())->count(),
            'expired_subscribers' => SellerSubscription::where(function ($q) {
                $q->where('status', 0)->orWhere('expire_date', '<=', now());
            })->count(),
            'total_revenue' => (float) SubscriptionHistory::where('payment_status', 'complete')->sum('price'),
        ];
    }
}
