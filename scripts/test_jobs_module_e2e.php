<?php

/**
 * End-to-End Verification Test Suite for Jobs & Bidding Module
 * FUN MOMENT Production Reconstruction
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\AdminAuditLog;
use App\Category;
use App\Http\Controllers\AdminJobApiController;
use App\Order;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\JobPost\Entities\BuyerJob;
use Modules\JobPost\Entities\JobRequest;
use Modules\JobPost\Entities\JobRequestConversation;
use Modules\JobPost\Entities\SellerViewJob;
use Modules\JobPost\Services\JobPostService;
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
echo "   STARTING JOBS & BIDDING MODULE E2E TEST SUITE    \n";
echo "====================================================\n\n";

try {
    // ----------------------------------------------------
    // Section 1: Relational Schema & Tables in PostgreSQL
    // ----------------------------------------------------
    echo "--- Section 1: Database Schema ---\n";
    assertTest(Schema::hasTable('buyer_jobs'), "buyer_jobs table exists in PostgreSQL");
    assertTest(Schema::hasTable('job_requests'), "job_requests table exists in PostgreSQL");
    assertTest(Schema::hasTable('job_request_conversations'), "job_request_conversations table exists in PostgreSQL");
    assertTest(Schema::hasTable('seller_view_jobs'), "seller_view_jobs table exists in PostgreSQL");

    assertTest(Schema::hasColumns('buyer_jobs', ['id', 'buyer_id', 'title', 'slug', 'price', 'is_job_on', 'status']), "buyer_jobs has required columns");
    assertTest(Schema::hasColumns('job_requests', ['id', 'job_post_id', 'buyer_id', 'seller_id', 'expected_salary', 'is_hired', 'status']), "job_requests has required columns");
    assertTest(Schema::hasColumns('job_request_conversations', ['id', 'job_request_id', 'type', 'message']), "job_request_conversations has required columns");
    assertTest(Schema::hasColumns('seller_view_jobs', ['id', 'job_post_id', 'seller_id']), "seller_view_jobs has required columns");

    // ----------------------------------------------------
    // Section 2: Eloquent Model Classes & Relations
    // ----------------------------------------------------
    echo "\n--- Section 2: Models & Classes ---\n";
    assertTest(class_exists(BuyerJob::class), "BuyerJob model class is loadable via autoloader");
    assertTest(class_exists(JobRequest::class), "JobRequest model class is loadable via autoloader");
    assertTest(class_exists(JobRequestConversation::class), "JobRequestConversation model class is loadable");
    assertTest(class_exists(SellerViewJob::class), "SellerViewJob model class is loadable");
    assertTest(class_exists(JobPostService::class), "JobPostService business service class is loadable");

    // ----------------------------------------------------
    // Section 3: Seed Test Actors (Buyer, Seller, Admin)
    // ----------------------------------------------------
    echo "\n--- Section 3: Actor Setup ---\n";
    $testCategory = Category::firstOrCreate(
        ['name' => 'E2E Testing Category'],
        ['slug' => 'e2e-testing-category', 'status' => 1]
    );
    assertTest($testCategory->id > 0, "Test category available (ID: {$testCategory->id})");

    $buyerEmail = 'e2e_buyer_' . time() . '@funmoment.test';
    $buyer = User::create([
        'name' => 'E2E Test Buyer',
        'username' => 'buyer_' . time(),
        'email' => $buyerEmail,
        'phone' => '+966500000001',
        'password' => bcrypt('Secret123!'),
        'user_type' => 1, // buyer
        'terms_condition' => 1,
    ]);
    assertTest($buyer->id > 0, "Test Buyer created (ID: {$buyer->id})");

    $sellerEmail = 'e2e_seller_' . time() . '@funmoment.test';
    $seller = User::create([
        'name' => 'E2E Test Seller',
        'username' => 'seller_' . time(),
        'email' => $sellerEmail,
        'phone' => '+966500000002',
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
    // Section 4: Buyer Job Creation Workflow
    // ----------------------------------------------------
    echo "\n--- Section 4: Buyer Job Creation Workflow ---\n";
    $jobService = app(JobPostService::class);

    $jobData = [
        'category_id' => $testCategory->id,
        'title' => 'Custom Website Design for FunMoment ' . time(),
        'description' => 'Detailed job post description for building a luxury marketplace UI.',
        'price' => 350.00,
        'is_job_online' => 1,
        'dead_line' => now()->addDays(14),
    ];

    $job = $jobService->createJob($jobData, $buyer->id);
    assertTest($job->id > 0, "BuyerJob post successfully created (ID: {$job->id})");
    assertTest($job->buyer_id === $buyer->id, "Job post belongs to buyer (Buyer ID: {$buyer->id})");
    assertTest($job->status === 1, "Job initial status is 1 (Active/Open)");
    assertTest($job->is_job_on === 1, "Job is accepting bids (is_job_on = 1)");
    assertTest(!empty($job->slug), "Slug was automatically generated: '{$job->slug}'");

    // ----------------------------------------------------
    // Section 5: Seller View Tracking
    // ----------------------------------------------------
    echo "\n--- Section 5: Seller View Tracking ---\n";
    $view = SellerViewJob::firstOrCreate([
        'job_post_id' => $job->id,
        'seller_id' => $seller->id,
    ]);
    assertTest($view->id > 0, "SellerViewJob record created for seller");

    $duplicateView = SellerViewJob::where('job_post_id', $job->id)->where('seller_id', $seller->id)->count();
    assertTest($duplicateView === 1, "Unique constraint prevents duplicate seller view records");

    // ----------------------------------------------------
    // Section 6: Seller Bidding / Proposal Submission
    // ----------------------------------------------------
    echo "\n--- Section 6: Bidding & Proposal Submission ---\n";
    $bidPrice = 280.00;
    $proposal = $jobService->submitProposal([
        'job_post_id' => $job->id,
        'expected_salary' => $bidPrice,
        'cover_letter' => 'I have 7 years of full-stack experience and can deliver this in 5 days.',
    ], $seller->id);

    assertTest($proposal->id > 0, "Seller proposal submitted successfully (Proposal ID: {$proposal->id})");
    assertTest((float)$proposal->expected_salary === 280.00, "Proposal expected salary recorded correctly (280.00 SAR)");
    assertTest($proposal->is_hired === 0, "Initial proposal status is_hired = 0");
    assertTest($proposal->status === 0, "Initial proposal status = 0 (Pending)");

    // Test rejection of duplicate bid
    $caughtDuplicate = false;
    try {
        $jobService->submitProposal([
            'job_post_id' => $job->id,
            'expected_salary' => 270.00,
            'cover_letter' => 'Second attempt bid',
        ], $seller->id);
    } catch (\Exception $e) {
        $caughtDuplicate = true;
    }
    assertTest($caughtDuplicate, "Duplicate proposal submission by same seller correctly rejected with exception");

    // Test rejection of buyer bidding on own job
    $caughtBuyerBid = false;
    try {
        $jobService->submitProposal([
            'job_post_id' => $job->id,
            'expected_salary' => 200.00,
            'cover_letter' => 'Buyer bidding on own job',
        ], $buyer->id);
    } catch (\Exception $e) {
        $caughtBuyerBid = true;
    }
    assertTest($caughtBuyerBid, "Buyer bidding on their own job correctly rejected with exception");

    // ----------------------------------------------------
    // Section 7: Proposal Q&A / Negotiation Messages
    // ----------------------------------------------------
    echo "\n--- Section 7: Negotiation Conversation ---\n";
    $buyerMsg = $jobService->sendMessage($proposal->id, 'buyer', 'Hello! Can you start immediately?');
    assertTest($buyerMsg->id > 0, "Buyer negotiation message recorded (ID: {$buyerMsg->id})");
    assertTest($buyerMsg->type === 'buyer', "Buyer message type recorded as 'buyer'");

    $sellerMsg = $jobService->sendMessage($proposal->id, 'seller', 'Yes, I can begin today upon contract confirmation.');
    assertTest($sellerMsg->id > 0, "Seller negotiation response recorded (ID: {$sellerMsg->id})");
    assertTest($sellerMsg->type === 'seller', "Seller message type recorded as 'seller'");

    $conversationCount = JobRequestConversation::where('job_request_id', $proposal->id)->count();
    assertTest($conversationCount === 2, "Both negotiation messages attached to proposal (Count: $conversationCount)");

    // ----------------------------------------------------
    // Section 8: Hiring Workflow with Escrow Wallet Deduction
    // ----------------------------------------------------
    echo "\n--- Section 8: Hiring & Wallet Escrow Workflow ---\n";
    $walletService = app(WalletService::class);

    // Deposit 500 SAR into buyer's wallet
    $walletService->credit(
        $buyer->id,
        500.00,
        'deposit',
        'e2e_seed_' . time(),
        'Seed deposit for job hire test'
    );
    $initialBuyerBalance = (float) Wallet::getOrCreateForUser($buyer->id)->balance;
    assertTest($initialBuyerBalance >= 500.00, "Buyer wallet seeded with funds (Balance: {$initialBuyerBalance} SAR)");

    // Execute atomic hire
    $order = $jobService->hireSeller($proposal->id, $buyer->id, 'wallet');
    assertTest($order->id > 0, "Order generated upon hiring seller (Order ID: {$order->id})");
    assertTest($order->order_from_job === 'yes', "Order marked as order_from_job = 'yes'");
    assertTest($order->job_post_id === $job->id, "Order linked to job_post_id {$job->id}");
    assertTest($order->payment_gateway === 'wallet', "Order payment gateway set to 'wallet'");
    assertTest($order->payment_status === 'complete', "Order payment status marked complete");

    // Verify Proposal status updated
    $proposal->refresh();
    assertTest($proposal->is_hired === 1, "Proposal marked is_hired = 1");
    assertTest($proposal->status === 1, "Proposal marked status = 1 (Hired)");

    // Verify Job status updated
    $job->refresh();
    assertTest($job->status === 2, "Job post status updated to 2 (Hired/In Progress)");
    assertTest($job->is_job_on === 0, "Job is_job_on set to 0 (No longer accepting new bids)");

    // Verify Wallet double-entry accounting
    $postHireBuyerBalance = (float) Wallet::getOrCreateForUser($buyer->id)->fresh()->balance;
    $expectedBalance = round($initialBuyerBalance - (float)$order->total, 2);
    assertTest(abs($postHireBuyerBalance - $expectedBalance) < 0.01, "Buyer wallet balance debited by exactly order total ({$order->total} SAR, Current: {$postHireBuyerBalance})");

    $ledgerEntry = WalletHistory::where('wallet_id', Wallet::getOrCreateForUser($buyer->id)->id)
        ->where('reference_type', 'job_hire')
        ->latest('id')
        ->first();
    assertTest($ledgerEntry !== null, "WalletHistory ledger entry recorded for job_hire");
    assertTest((float)$ledgerEntry->amount === (float)$order->total, "Ledger entry amount matches order total ({$order->total} SAR)");
    assertTest($ledgerEntry->entry_type === 'debit', "Ledger entry entry_type recorded as 'debit'");

    // ----------------------------------------------------
    // Section 9: Admin Control Plane & Moderation APIs
    // ----------------------------------------------------
    echo "\n--- Section 9: Admin Control Plane APIs ---\n";
    Auth::guard('admin')->login($admin);

    $adminController = app(AdminJobApiController::class);

    // 1. Admin Index with Metrics
    $reqIndex = Request::create('/admin-home/jobs-json', 'GET');
    $resIndex = $adminController->index($reqIndex);
    $indexData = $resIndex->getData(true);

    assertTest($resIndex->getStatusCode() === 200, "Admin jobs index returned HTTP 200");
    assertTest(isset($indexData['data']['metrics']['total_jobs']), "Admin response includes total_jobs metric");
    assertTest(isset($indexData['data']['metrics']['total_proposals']), "Admin response includes total_proposals metric");
    assertTest($indexData['data']['metrics']['total_jobs'] >= 1, "Admin metrics reflects at least 1 job");

    // 2. Admin Show Details
    $resShow = $adminController->show($job->id);
    $showData = $resShow->getData(true);
    assertTest($resShow->getStatusCode() === 200, "Admin job show details returned HTTP 200");
    assertTest($showData['data']['id'] === $job->id, "Job ID matches requested job");
    assertTest(isset($showData['data']['buyer']['name']), "Job buyer details included in admin payload");

    // 3. Admin Proposals List
    $resProposals = $adminController->proposals($job->id);
    $proposalsData = $resProposals->getData(true);
    assertTest($resProposals->getStatusCode() === 200, "Admin job proposals endpoint returned HTTP 200");
    assertTest(count($proposalsData['data']) >= 1, "Admin proposals list returns submitted proposals");
    assertTest(count($proposalsData['data'][0]['conversations']) === 2, "Admin proposal details include conversation messages");

    // 4. Admin Status Moderation & Audit Logging
    $reqUpdate = Request::create("/admin-home/jobs-json/{$job->id}/status", 'POST', [
        'status' => 1, // reopen
        'is_job_on' => 1,
    ]);
    $resUpdate = $adminController->updateStatus($reqUpdate, $job->id);
    $updateData = $resUpdate->getData(true);

    assertTest($resUpdate->getStatusCode() === 200, "Admin job status update returned HTTP 200");
    $job->refresh();
    assertTest($job->status === 1, "Job status successfully updated to 1 via admin");
    assertTest($job->is_job_on === 1, "Job is_job_on successfully updated to 1 via admin");

    $auditLog = AdminAuditLog::where('resource_type', 'buyer_job')
        ->where('resource_id', $job->id)
        ->where('action', 'job_status_update')
        ->latest('id')
        ->first();
    assertTest($auditLog !== null, "AdminAuditLog recorded for job_status_update");
    assertTest($auditLog->admin_id === $admin->id, "Audit log correctly references performing admin");

    // ----------------------------------------------------
    // Clean up test actors
    // ----------------------------------------------------
    echo "\n--- Cleanup ---\n";
    $job->delete();
    $buyer->delete();
    $seller->delete();
    echo " [INFO] Test data cleaned up successfully.\n";

} catch (\Throwable $t) {
    echo "\n[EXCEPTION CRITICAL]: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    $failed++;
}

echo "\n====================================================\n";
echo "   JOBS & BIDDING E2E TEST RESULTS:                 \n";
echo "   PASSED: $passed                                  \n";
echo "   FAILED: $failed                                  \n";
echo "====================================================\n";

if ($failed === 0) {
    echo "\n>>> ALL JOBS MODULE TESTS PASSED WITH 100% SUCCESS <<<\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED <<<\n";
    exit(1);
}
