<?php

/**
 * End-to-End Verification Test Suite for Subscription & Monetization Module
 * FUN MOMENT Production Reconstruction
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\AdminAuditLog;
use App\Http\Controllers\AdminSubscriptionApiController;
use App\Http\Controllers\Api\SellerSubscriptionController;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Subscription\Entities\SellerSubscription;
use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\SubscriptionHistory;
use Modules\Subscription\Services\SubscriptionService;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;
use Modules\Wallet\Services\WalletService;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $description) {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] $description\n";
    } else {
        $failed++;
        echo " [FAIL] $description\n";
    }
}

echo "====================================================\n";
echo "  STARTING SUBSCRIPTION MODULE E2E TEST SUITE       \n";
echo "====================================================\n\n";

try {
    // ----------------------------------------------------
    // Section 1: Relational Schema & Tables in PostgreSQL
    // ----------------------------------------------------
    echo "--- Section 1: Database Schema ---\n";
    assertTest(Schema::hasTable('subscriptions'), "subscriptions table exists in PostgreSQL");
    assertTest(Schema::hasTable('seller_subscriptions'), "seller_subscriptions table exists in PostgreSQL");
    assertTest(Schema::hasTable('subscription_histories'), "subscription_histories table exists in PostgreSQL");

    assertTest(Schema::hasColumns('subscriptions', ['id', 'title', 'type', 'price', 'connect', 'service', 'job', 'status']), "subscriptions table has required columns");
    assertTest(Schema::hasColumns('seller_subscriptions', ['id', 'seller_id', 'subscription_id', 'connect', 'service', 'job', 'expire_date', 'status']), "seller_subscriptions table has required columns");
    assertTest(Schema::hasColumns('subscription_histories', ['id', 'seller_id', 'subscription_id', 'price', 'expire_date', 'payment_status']), "subscription_histories table has required columns");

    // ----------------------------------------------------
    // Section 2: Eloquent Model Classes & Relations
    // ----------------------------------------------------
    echo "\n--- Section 2: Models & Classes ---\n";
    assertTest(class_exists(Subscription::class), "Subscription model class is loadable");
    assertTest(class_exists(SellerSubscription::class), "SellerSubscription model class is loadable");
    assertTest(class_exists(SubscriptionHistory::class), "SubscriptionHistory model class is loadable");
    assertTest(class_exists(SubscriptionService::class), "SubscriptionService service class is loadable");

    // ----------------------------------------------------
    // Section 3: Actor Setup
    // ----------------------------------------------------
    echo "\n--- Section 3: Actor Setup ---\n";
    $sellerEmail = 'e2e_sub_seller_' . time() . '@funmoment.test';
    $seller = User::create([
        'name' => 'E2E Subscription Seller',
        'username' => 'sub_seller_' . time(),
        'email' => $sellerEmail,
        'phone' => '+966500000003',
        'password' => bcrypt('Secret123!'),
        'user_type' => 0, // seller
        'terms_condition' => 1,
    ]);
    assertTest($seller->id > 0, "Test Seller created (ID: {$seller->id})");

    $admin = Admin::first();
    if (!$admin) {
        $admin = Admin::create([
            'name' => 'E2E Super Admin',
            'username' => 'e2e_superadmin_' . time(),
            'email' => 'superadmin_' . time() . '@funmoment.test',
            'password' => bcrypt('AdminSecret123!'),
            'role' => 'Super Admin',
        ]);
    }
    assertTest($admin->id > 0, "Admin account available (ID: {$admin->id})");

    // ----------------------------------------------------
    // Section 4: Subscription Plan Creation
    // ----------------------------------------------------
    echo "\n--- Section 4: Subscription Plans ---\n";
    $monthlyPlan = Subscription::create([
        'title' => 'Starter Monthly Pro ' . time(),
        'type' => 'monthly',
        'price' => 75.00,
        'connect' => 25,
        'service' => 8,
        'job' => 10,
        'description' => 'Great starter tier for independent service providers.',
        'status' => 1,
    ]);
    assertTest($monthlyPlan->id > 0, "Monthly subscription plan created (ID: {$monthlyPlan->id}, 75 SAR)");

    $yearlyPlan = Subscription::create([
        'title' => 'Annual Business Elite ' . time(),
        'type' => 'yearly',
        'price' => 699.00,
        'connect' => 300,
        'service' => 50,
        'job' => 100,
        'description' => 'Full annual entitlement with bulk connect quota.',
        'status' => 1,
    ]);
    assertTest($yearlyPlan->id > 0, "Yearly subscription plan created (ID: {$yearlyPlan->id}, 699 SAR)");

    // ----------------------------------------------------
    // Section 5: Subscription Purchase via Escrow Wallet
    // ----------------------------------------------------
    echo "\n--- Section 5: Subscription Purchase via Wallet ---\n";
    $walletService = app(WalletService::class);
    $subService = app(SubscriptionService::class);

    // Seed seller wallet with 400 SAR
    $walletService->credit(
        $seller->id,
        400.00,
        'deposit',
        'e2e_sub_seed_' . time(),
        'Seed deposit for subscription purchase'
    );
    $initialBalance = (float) Wallet::getOrCreateForUser($seller->id)->balance;
    assertTest($initialBalance >= 400.00, "Seller wallet seeded (Balance: {$initialBalance} SAR)");

    // Seller subscribes to monthly plan (75 SAR)
    $sellerSub = $subService->subscribeOrRenew($seller->id, $monthlyPlan->id, 'wallet');
    assertTest($sellerSub->id > 0, "SellerSubscription record created (ID: {$sellerSub->id})");
    assertTest($sellerSub->seller_id === $seller->id, "Subscription assigned to seller {$seller->id}");
    assertTest($sellerSub->connect === 25, "Connect quota credited correctly (25 connects)");
    assertTest($sellerSub->service === 8, "Service quota credited correctly (8 services)");
    assertTest($sellerSub->job === 10, "Job quota credited correctly (10 jobs)");
    assertTest($sellerSub->status === 1, "Subscription status is 1 (Active)");
    assertTest(!$sellerSub->isExpired(), "Subscription is currently active and not expired");

    // Verify wallet deduction
    $postSubBalance = (float) Wallet::getOrCreateForUser($seller->id)->fresh()->balance;
    $expectedBalance = round($initialBalance - 75.00, 2);
    assertTest(abs($postSubBalance - $expectedBalance) < 0.01, "Seller wallet debited by exactly plan price (Current: {$postSubBalance} SAR)");

    // Verify WalletHistory double-entry record
    $walletLedger = WalletHistory::where('wallet_id', Wallet::getOrCreateForUser($seller->id)->id)
        ->where('reference_type', 'subscription_purchase')
        ->latest('id')
        ->first();
    assertTest($walletLedger !== null, "WalletHistory ledger entry recorded for subscription_purchase");
    assertTest((float)$walletLedger->amount === 75.00, "Ledger entry amount matches plan price (75.00 SAR)");
    assertTest($walletLedger->entry_type === 'debit', "Ledger entry entry_type is 'debit'");

    // Verify SubscriptionHistory record
    $subHistory = SubscriptionHistory::where('seller_id', $seller->id)->latest('id')->first();
    assertTest($subHistory !== null, "SubscriptionHistory log entry created");
    assertTest((float)$subHistory->price === 75.00, "SubscriptionHistory price matches 75.00 SAR");
    assertTest($subHistory->payment_status === 'complete', "SubscriptionHistory payment status is complete");

    // ----------------------------------------------------
    // Section 6: Entitlement & Quota Consumption
    // ----------------------------------------------------
    echo "\n--- Section 6: Entitlement & Quota Consumption ---\n";
    assertTest($subService->checkEntitlement($seller->id, 'connect'), "checkEntitlement returns true for connects");
    assertTest($subService->checkEntitlement($seller->id, 'service'), "checkEntitlement returns true for services");
    assertTest($subService->checkEntitlement($seller->id, 'job'), "checkEntitlement returns true for jobs");

    // Consume 1 connect
    $consumed = $subService->consumeConnect($seller->id, 1);
    assertTest($consumed === true, "consumeConnect successfully consumed 1 connect");
    $sellerSub->refresh();
    assertTest($sellerSub->connect === 24, "Remaining connects decremented to 24");

    // ----------------------------------------------------
    // Section 7: Subscription Renewal Workflow
    // ----------------------------------------------------
    echo "\n--- Section 7: Subscription Renewal Workflow ---\n";
    $renewedSub = $subService->subscribeOrRenew($seller->id, $monthlyPlan->id, 'wallet');
    assertTest($renewedSub->connect === (24 + 25), "Renewal added 25 new connects to remaining quota (Total: {$renewedSub->connect})");
    assertTest($renewedSub->service === (8 + 8), "Renewal added 8 new service quota (Total: {$renewedSub->service})");

    $afterRenewBalance = (float) Wallet::getOrCreateForUser($seller->id)->fresh()->balance;
    $expectedRenewBalance = round($postSubBalance - 75.00, 2);
    assertTest(abs($afterRenewBalance - $expectedRenewBalance) < 0.01, "Seller wallet debited for renewal (Current: {$afterRenewBalance} SAR)");

    // ----------------------------------------------------
    // Section 8: Expiration Handling
    // ----------------------------------------------------
    echo "\n--- Section 8: Expiration Checks ---\n";
    $sellerSub->expire_date = Carbon::now()->subDays(5);
    $sellerSub->save();
    assertTest($sellerSub->isExpired() === true, "isExpired returns true for past expiration date");
    assertTest($subService->checkEntitlement($seller->id, 'connect') === false, "checkEntitlement returns false when expired");

    // ----------------------------------------------------
    // Section 9: Seller API Endpoints
    // ----------------------------------------------------
    echo "\n--- Section 9: Seller API Endpoints ---\n";
    // Authenticate seller via sanctum
    Auth::guard('sanctum')->setUser($seller);

    $sellerSubController = app(SellerSubscriptionController::class);

    // Plans list
    $resPlans = $sellerSubController->subscription_plans();
    $plansData = $resPlans->getData(true);
    assertTest(in_array($resPlans->getStatusCode(), [200, 201]), "Seller subscription_plans API returned HTTP {$resPlans->getStatusCode()}");
    $plansList = $plansData['data']['subscription_plans'] ?? $plansData['subscription_plans'] ?? null;
    assertTest(is_array($plansList), "Plans list returned in payload");
    assertTest(count($plansList) >= 2, "Contains at least 2 active plans");

    // Current info
    $resInfo = $sellerSubController->subscription_info();
    $infoData = $resInfo->getData(true);
    assertTest(in_array($resInfo->getStatusCode(), [200, 201]), "Seller subscription_info API returned HTTP {$resInfo->getStatusCode()}");
    $infoObj = $infoData['data']['subscription_info'] ?? $infoData['subscription_info'] ?? null;
    assertTest($infoObj !== null && $infoObj['seller_id'] === $seller->id, "Subscription info matches seller");

    // History
    $resHist = $sellerSubController->subscription_history();
    $histData = $resHist->getData(true);
    assertTest(in_array($resHist->getStatusCode(), [200, 201]), "Seller subscription_history API returned HTTP {$resHist->getStatusCode()}");
    $histList = $histData['data']['subscription_history'] ?? $histData['subscription_history'] ?? null;
    assertTest(is_array($histList) && count($histList) >= 2, "History reflects 2 purchases (initial + renewal)");

    // ----------------------------------------------------
    // Section 10: Admin Control Plane APIs
    // ----------------------------------------------------
    echo "\n--- Section 10: Admin Control Plane APIs ---\n";
    Auth::guard('admin')->login($admin);

    $adminSubController = app(AdminSubscriptionApiController::class);

    // 1. Admin Index with Metrics
    $resAdminIndex = $adminSubController->index();
    $adminData = $resAdminIndex->getData(true);
    assertTest($resAdminIndex->getStatusCode() === 200, "Admin subscriptions index returned HTTP 200");
    assertTest(isset($adminData['data']['metrics']['total_plans']), "Admin response includes total_plans metric");
    assertTest(isset($adminData['data']['metrics']['total_revenue']), "Admin response includes total_revenue metric");
    assertTest($adminData['data']['metrics']['total_revenue'] >= 150.00, "Total revenue reflects purchases (>= 150 SAR)");

    // 2. Admin Create Plan
    $reqCreate = Request::create('/admin-home/subscriptions-json/plans', 'POST', [
        'title' => 'VIP Lifetime Enterprise ' . time(),
        'type' => 'lifetime',
        'price' => 1999.00,
        'connect' => 99999,
        'service' => 100,
        'job' => 100,
        'description' => 'Unlimited access tier for premium agencies.',
        'status' => 1,
    ]);
    $resCreate = $adminSubController->storePlan($reqCreate);
    assertTest($resCreate->getStatusCode() === 201, "Admin storePlan created plan with HTTP 201");
    $createdPlanId = $resCreate->getData(true)['data']['id'];

    // Verify audit log for plan creation
    $auditPlanCreate = AdminAuditLog::where('resource_type', 'subscription_plan')
        ->where('resource_id', $createdPlanId)
        ->where('action', 'subscription_plan_create')
        ->latest('id')
        ->first();
    assertTest($auditPlanCreate !== null, "AdminAuditLog recorded for subscription_plan_create");

    // 3. Admin Toggle Plan Status
    $resToggle = $adminSubController->togglePlanStatus(new Request(), $createdPlanId);
    assertTest($resToggle->getStatusCode() === 200, "Admin togglePlanStatus returned HTTP 200");
    assertTest(Subscription::find($createdPlanId)->status === 0, "Plan status toggled to 0 (Disabled)");

    // 4. Admin Adjust Subscriber Quotas
    $reqAdjust = Request::create("/admin-home/subscriptions-json/subscribers/{$seller->id}/adjust", 'POST', [
        'connect' => 100,
        'service' => 20,
        'days_to_add' => 60,
        'admin_note' => 'Customer support courtesy bonus granted for high customer satisfaction score.',
    ]);
    $resAdjust = $adminSubController->adjustSubscriber($reqAdjust, $seller->id);
    assertTest($resAdjust->getStatusCode() === 200, "Admin adjustSubscriber returned HTTP 200");

    $sellerSub->refresh();
    assertTest($sellerSub->connect === 100, "Seller connects adjusted to 100");
    assertTest($sellerSub->service === 20, "Seller services adjusted to 20");
    assertTest($sellerSub->expire_date->isFuture(), "Seller subscription expiration extended into future");

    $auditAdjust = AdminAuditLog::where('resource_type', 'seller_subscription')
        ->where('resource_id', $sellerSub->id)
        ->where('action', 'subscriber_quota_adjustment')
        ->latest('id')
        ->first();
    assertTest($auditAdjust !== null, "AdminAuditLog recorded for subscriber_quota_adjustment with admin note");

    // ----------------------------------------------------
    // Clean up
    // ----------------------------------------------------
    echo "\n--- Cleanup ---\n";
    $sellerSub->delete();
    Subscription::where('id', $createdPlanId)->delete();
    $monthlyPlan->delete();
    $yearlyPlan->delete();
    $seller->delete();
    echo " [INFO] Test data cleaned up successfully.\n";

} catch (\Throwable $t) {
    echo "\n[EXCEPTION CRITICAL]: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    $failed++;
}

echo "\n====================================================\n";
echo "   SUBSCRIPTION E2E TEST RESULTS:                   \n";
echo "   PASSED: $passed                                  \n";
echo "   FAILED: $failed                                  \n";
echo "====================================================\n";

if ($failed === 0) {
    echo "\n>>> ALL SUBSCRIPTION MODULE TESTS PASSED WITH 100% SUCCESS <<<\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED <<<\n";
    exit(1);
}
