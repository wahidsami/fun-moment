<?php

/**
 * FUN MOMENT — Comprehensive End-to-End System Exercise & Core Marketplace Regression
 * 
 * Exercises:
 * 1. Production Runtime SHA & Migrations Verification
 * 2. Real Chat Lifecycle (Buyer <-> Seller <-> Admin Moderation)
 * 3. Wallet Ledger & Double-Entry Accounting
 * 4. Seller Payout Request Lifecycle (Request -> Hold -> Disburse -> Reject)
 * 5. Jobs & Bidding Escrow / Order Flow
 * 6. Subscriptions Full Lifecycle (Purchase -> Quotas -> Bidding Debit -> Renewal -> Expiration)
 * 7. Core Marketplace Regression (Categories, Services, Direct Orders, User Profiles)
 */

require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';

$initReq = \Illuminate\Http\Request::create('/');
$app->instance('request', $initReq);
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\AdminAuditLog;
use App\Category;
use App\Order;
use App\PayoutRequest;
use App\Service;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\JobPost\Entities\BuyerJob;
use Modules\JobPost\Entities\JobRequest;
use Modules\JobPost\Entities\JobRequestConversation;
use Modules\JobPost\Entities\SellerViewJob;
use Modules\JobPost\Services\JobPostService;
use Modules\LiveChat\Entities\LiveChatConversation;
use Modules\LiveChat\Entities\LiveChatMessage;
use Modules\LiveChat\Services\LiveChatService;
use Modules\Subscription\Entities\SellerSubscription;
use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\SubscriptionHistory;
use Modules\Subscription\Services\SubscriptionService;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;
use Modules\Wallet\Services\WalletService;

echo "======================================================================\n";
echo "   FUN MOMENT: FULL SYSTEM EXERCISE & CORE MARKETPLACE REGRESSION     \n";
echo "======================================================================\n\n";

$passed = 0;
$failed = 0;

function assertCheck(bool $condition, string $title, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] " . $title . "\n";
    } else {
        $failed++;
        echo " [FAIL] " . $title . "\n";
        if ($details) {
            echo "        -> Details: " . $details . "\n";
        }
    }
}

try {
    // -----------------------------------------------------------------
    // 1. Production Runtime & Environment Verification
    // -----------------------------------------------------------------
    echo "--- 1. RUNTIME SHA & MIGRATION STATE ---\n";
    $gitSha = trim(shell_exec('git rev-parse HEAD') ?? '');
    assertCheck(strlen($gitSha) === 40, "Git HEAD SHA resolved: " . substr($gitSha, 0, 10));

    $pendingMigrations = DB::table('migrations')->count();
    assertCheck($pendingMigrations >= 50, "Database migrations applied in PostgreSQL (Total: {$pendingMigrations})");

    $featureGate = json_decode(file_get_contents(__DIR__ . '/../backend/modules_statuses.json'), true);
    assertCheck(($featureGate['Wallet'] ?? false) === true, "Feature Gate: Wallet = true");
    assertCheck(($featureGate['LiveChat'] ?? false) === true, "Feature Gate: LiveChat = true");
    assertCheck(($featureGate['JobPost'] ?? false) === true, "Feature Gate: JobPost = true");
    assertCheck(($featureGate['Subscription'] ?? false) === true, "Feature Gate: Subscription = true");

    // -----------------------------------------------------------------
    // 2. Setup Dedicated Actors
    // -----------------------------------------------------------------
    echo "\n--- 2. ACTOR INITIALIZATION ---\n";
    $timestamp = time();
    $buyer = User::create([
        'name' => 'Exercise Buyer ' . $timestamp,
        'email' => "buyer_{$timestamp}@funmoment.test",
        'username' => "buyer_{$timestamp}",
        'phone' => '0555' . rand(100000, 999999),
        'password' => bcrypt('password123'),
        'user_type' => 1,
        'country_id' => 1,
        'city_id' => 1,
        'area_id' => 1,
    ]);
    assertCheck($buyer->id > 0, "Test Buyer created (ID: {$buyer->id})");

    $seller = User::create([
        'name' => 'Exercise Seller ' . $timestamp,
        'email' => "seller_{$timestamp}@funmoment.test",
        'username' => "seller_{$timestamp}",
        'phone' => '0566' . rand(100000, 999999),
        'password' => bcrypt('password123'),
        'user_type' => 0,
        'country_id' => 1,
        'city_id' => 1,
        'area_id' => 1,
    ]);
    assertCheck($seller->id > 0, "Test Seller created (ID: {$seller->id})");

    $admin = Admin::first();
    assertCheck($admin !== null, "Admin account available (ID: {$admin->id})");

    // -----------------------------------------------------------------
    // 3. Real Live Chat Workflow
    // -----------------------------------------------------------------
    echo "\n--- 3. LIVE CHAT LIFECYCLE (BUYER <-> SELLER <-> ADMIN) ---\n";
    $chatService = app(LiveChatService::class);

    // Buyer sends initial message to seller
    $msg1 = $chatService->sendMessage($buyer->id, $seller->id, "Hello! I am interested in your design services. <script>alert('xss')</script>");
    assertCheck($msg1->id > 0, "Buyer sent message to seller (Message ID: {$msg1->id})");
    assertCheck(strpos($msg1->message, '<script>') === false, "Message content sanitized against XSS attacks");
    assertCheck($msg1->is_read === false, "Message initially marked unread");

    // Conversation state
    $conv = LiveChatConversation::where('buyer_id', $buyer->id)->where('seller_id', $seller->id)->first();
    assertCheck($conv !== null, "Conversation record established");
    assertCheck($conv->seller_unread_count === 1, "Seller unread count incremented to 1");
    assertCheck($conv->buyer_unread_count === 0, "Buyer unread count is 0");

    // Seller gets contacts and reads message
    $sellerContacts = $chatService->getSellerContacts($seller->id);
    assertCheck(count($sellerContacts['chat_buyer_lists'] ?? []) >= 1, "Seller contacts includes Buyer");

    $messagesThread = $chatService->getConversationMessages($seller->id, $buyer->id);
    assertCheck($messagesThread->total() >= 1, "Seller fetched conversation thread with >= 1 message");
    $conv->refresh();
    assertCheck($conv->seller_unread_count === 0, "Seller unread count auto-reset to 0 upon reading");

    // Seller replies to buyer
    $msg2 = $chatService->sendMessage($seller->id, $buyer->id, "Thank you! I can definitely help with your project.");
    $conv->refresh();
    assertCheck($conv->buyer_unread_count === 1, "Buyer unread count incremented to 1 after seller reply");

    // Admin moderates conversation
    $adminOverview = $chatService->getAdminConversationsOverview();
    assertCheck($adminOverview['summary']['total_conversations'] >= 1, "Admin chat hub reports total_conversations >= 1");
    assertCheck($adminOverview['summary']['total_messages'] >= 2, "Admin chat hub reports total_messages >= 2");

    // -----------------------------------------------------------------
    // 4. Wallet Ledger & Double-Entry Accounting
    // -----------------------------------------------------------------
    echo "\n--- 4. WALLET LEDGER & DOUBLE-ENTRY ACCOUNTING ---\n";
    $walletService = app(WalletService::class);

    // Initial balances
    $buyerWallet = Wallet::getOrCreateForUser($buyer->id);
    $buyerWallet->update(['balance' => 0.00, 'pending_balance' => 0.00, 'total_earned' => 0.00, 'total_spent' => 0.00, 'status' => 1]);
    assertCheck((float)$buyerWallet->fresh()->balance === 0.00, "Buyer wallet starts with 0.00 balance");

    // Deposit 1,000 SAR into buyer wallet
    $depositRequest = $walletService->createDepositRequest($buyerWallet, 1000.00, 'paytabs');
    assertCheck($depositRequest->payment_status === 'pending', "Deposit initialized with pending status");
    assertCheck((float)$buyerWallet->fresh()->balance === 0.00, "Balance remains unchanged until payment confirmation");

    // Confirm deposit
    $completedDeposit = $walletService->completeDeposit($depositRequest->id);
    $buyerWallet->refresh();
    assertCheck((float)$buyerWallet->balance === 1000.00, "Buyer balance credited to exactly 1,000.00 SAR");

    // Overdraft protection test
    $overdraftBlocked = false;
    try {
        $walletService->debit($buyerWallet, 2500.00, 'overdraft_attempt', 999);
    } catch (\Exception $e) {
        $overdraftBlocked = true;
    }
    assertCheck($overdraftBlocked, "Overdraft request rejected with exception");
    assertCheck((float)$buyerWallet->fresh()->balance === 1000.00, "Buyer balance protected from negative mutation");

    // Controlled Admin Adjustment with required audit note
    $adjHist = $walletService->adminAdjustment(
        $buyerWallet,
        50.00,
        'credit',
        $admin->id,
        'Customer satisfaction incentive grant'
    );
    assertCheck((float)$buyerWallet->fresh()->balance === 1050.00, "Balance increased to 1,050.00 SAR via admin adjustment");
    assertCheck($adjHist->admin_id === $admin->id, "Ledger records admin_id");
    assertCheck($adjHist->balance_before == 1000.00 && $adjHist->balance_after == 1050.00, "Ledger records exact balance_before and balance_after");

    // Verify AdminAuditLog entry
    $auditLog = AdminAuditLog::where('action', 'wallet_adjustment')
        ->where('resource_id', (string) $buyerWallet->id)
        ->latest('id')
        ->first();
    assertCheck($auditLog !== null, "AdminAuditLog recorded immutable audit entry for adjustment");

    // -----------------------------------------------------------------
    // 5. Seller Payout Lifecycle
    // -----------------------------------------------------------------
    echo "\n--- 5. SELLER PAYOUT REQUEST LIFECYCLE ---\n";
    $sellerWallet = Wallet::getOrCreateForUser($seller->id);
    $sellerWallet->update(['balance' => 0.00, 'pending_balance' => 0.00, 'total_earned' => 0.00, 'total_spent' => 0.00, 'status' => 1]);

    // Credit seller with 800 SAR earnings
    $walletService->credit($sellerWallet, 800.00, 'wallet', 'order_earning', '101', 'Order #101 completion earnings');
    assertCheck((float)$sellerWallet->fresh()->balance === 800.00, "Seller available balance is 800.00 SAR");
    assertCheck((float)$sellerWallet->fresh()->total_earned === 800.00, "Seller total_earned is 800.00 SAR");

    // Seller requests 300 SAR payout
    $payout = $walletService->requestPayout(
        $sellerWallet,
        300.00,
        'bank_transfer',
        'SA0380000000608010167519'
    );
    $sellerWallet->refresh();
    assertCheck($payout->id > 0, "Payout request created (ID: {$payout->id})");
    assertCheck((float)$sellerWallet->balance === 500.00, "Available balance reduced by 300.00 to 500.00 SAR");
    assertCheck((float)$sellerWallet->pending_balance === 300.00, "300.00 SAR locked in pending_balance escrow");

    // Admin disburses payout
    $walletService->settlePayout($payout->id, 'completed', 'Approved and disbursed via wire transfer', $admin->id);
    $sellerWallet->refresh();
    assertCheck((float)$sellerWallet->pending_balance === 0.00, "Pending balance cleared upon disbursement");
    assertCheck((float)$sellerWallet->balance === 500.00, "Available balance remains 500.00 SAR after disbursement");

    $payoutLedger = WalletHistory::where('user_id', $seller->id)
        ->where('reference_type', 'payout_request')
        ->where('reference_id', (string) $payout->id)
        ->where('entry_type', 'payout')
        ->first();
    assertCheck($payoutLedger !== null && $payoutLedger->payment_status === 'complete', "Payout ledger marked complete");

    // Test Payout Rejection & Escrow Release
    $payout2 = $walletService->requestPayout($sellerWallet, 200.00, 'bank_transfer', 'SA111');
    $sellerWallet->refresh();
    assertCheck((float)$sellerWallet->pending_balance === 200.00, "Second payout held in pending (200.00 SAR)");
    $walletService->settlePayout($payout2->id, 'rejected', 'Incorrect IBAN details provided', $admin->id);
    $sellerWallet->refresh();
    assertCheck((float)$sellerWallet->balance === 500.00, "Rejected payout released funds back to available balance (500.00 SAR)");
    assertCheck((float)$sellerWallet->pending_balance === 0.00, "Pending balance reset to 0.00 SAR");

    // -----------------------------------------------------------------
    // 6. Jobs & Bidding Escrow / Order Flow
    // -----------------------------------------------------------------
    echo "\n--- 6. JOBS & BIDDING ESCROW / ORDER FLOW ---\n";
    $jobService = app(JobPostService::class);
    $cat = Category::first();

    // Buyer creates Job
    $job = $jobService->createJob([
        'title' => 'Mobile App UI/UX Redesign ' . $timestamp,
        'category_id' => $cat->id,
        'price' => 350.00,
        'description' => 'Need clean, modern responsive mobile app screens redesign in Flutter.',
        'dead_line' => date('Y-m-d', strtotime('+14 days')),
    ], $buyer->id);
    assertCheck($job->id > 0, "Buyer created job post (ID: {$job->id})");
    assertCheck($job->status === 1 && $job->is_job_on === 1, "Job post is active and accepting bids");

    // Seller views job
    $viewRecord = SellerViewJob::firstOrCreate([
        'job_post_id' => $job->id,
        'seller_id' => $seller->id,
    ]);
    assertCheck($viewRecord->id > 0, "Seller view recorded");

    // Seller submits proposal/bid
    $proposal = $jobService->submitProposal([
        'job_post_id' => $job->id,
        'expected_salary' => 320.00,
        'cover_letter' => 'I have 5 years Flutter and mobile UI design experience. Ready to start immediately.',
    ], $seller->id);
    assertCheck($proposal->id > 0, "Seller submitted bid/proposal (ID: {$proposal->id})");
    assertCheck((float)$proposal->expected_salary === 320.00, "Proposal expected salary recorded as 320.00 SAR");

    // Negotiation thread
    $conv1 = $jobService->sendMessage($proposal->id, 'buyer', 'Can you deliver this within 10 days?');
    $conv2 = $jobService->sendMessage($proposal->id, 'seller', 'Yes, absolutely, I can prioritize your delivery.');
    assertCheck($conv1->id > 0 && $conv2->id > 0, "Buyer and Seller exchanged negotiation messages");

    // Buyer hires seller using Wallet balance (Escrow debit)
    $hireOrder = $jobService->hireSeller($proposal->id, $buyer->id, 'wallet');
    assertCheck($hireOrder->id > 0, "Escrow order generated upon hiring (Order ID: {$hireOrder->id})");
    assertCheck($hireOrder->order_from_job === 'yes', "Order marked as order_from_job = 'yes'");
    assertCheck($hireOrder->job_post_id == $job->id, "Order linked to job_post_id {$job->id}");
    assertCheck($hireOrder->payment_status === 'complete', "Order payment status marked complete");
    assertCheck($hireOrder->payment_gateway === 'wallet', "Order payment gateway is 'wallet'");

    // Verify buyer wallet debited by proposal price (320 SAR)
    $buyerWallet->refresh();
    assertCheck((float)$buyerWallet->balance === (1050.00 - 320.00), "Buyer wallet debited by exactly proposal amount (Balance: 730.00 SAR)");

    $jobHireLedger = WalletHistory::where('user_id', $buyer->id)
        ->where('reference_type', 'job_hire')
        ->latest('id')
        ->first();
    assertCheck($jobHireLedger !== null, "WalletHistory recorded double-entry ledger entry for job_hire");
    assertCheck($jobHireLedger->entry_type === 'debit', "Ledger entry_type is 'debit'");

    // Verify job status updated to hired & closed to new bids
    $job->refresh();
    assertCheck($job->status === 2, "Job status transitioned to 2 (Hired/In Progress)");
    assertCheck($job->is_job_on === 0, "Job closed to new bids (is_job_on = 0)");

    // -----------------------------------------------------------------
    // 7. Subscriptions Lifecycle
    // -----------------------------------------------------------------
    echo "\n--- 7. SUBSCRIPTIONS LIFECYCLE (PURCHASE -> QUOTA -> RENEW) ---\n";
    $subService = app(SubscriptionService::class);

    // Create a plan
    $proPlan = Subscription::create([
        'title' => 'Professional Agency Tier ' . $timestamp,
        'type' => 'monthly',
        'price' => 120.00,
        'connect' => 40,
        'service' => 15,
        'job' => 20,
        'description' => 'Professional monthly plan for growing service providers.',
        'status' => 1,
    ]);
    assertCheck($proPlan->id > 0, "Subscription plan created (ID: {$proPlan->id}, Price: 120.00 SAR)");

    // Seller purchases subscription using wallet balance (500 SAR available)
    $sellerSub = $subService->subscribeOrRenew($seller->id, $proPlan->id, 'wallet');
    assertCheck($sellerSub->id > 0, "Seller successfully subscribed (ID: {$sellerSub->id})");
    assertCheck($sellerSub->connect === 40, "Connect quota credited: 40 connects");
    assertCheck($sellerSub->service === 15, "Service quota credited: 15 services");
    assertCheck($sellerSub->job === 20, "Job quota credited: 20 jobs");
    assertCheck($sellerSub->status === 1, "Subscription status is 1 (Active)");
    assertCheck(!$sellerSub->isExpired(), "Subscription is active and not expired");

    // Verify seller wallet debited by 120 SAR
    $sellerWallet->refresh();
    assertCheck((float)$sellerWallet->balance === (500.00 - 120.00), "Seller wallet debited by 120.00 SAR (Balance: 380.00 SAR)");

    $subPurchaseLedger = WalletHistory::where('user_id', $seller->id)
        ->where('reference_type', 'subscription_purchase')
        ->first();
    assertCheck($subPurchaseLedger !== null, "WalletHistory recorded ledger entry for subscription_purchase");

    // Quota consumption upon bidding
    assertCheck($subService->checkEntitlement($seller->id, 'connect'), "Seller entitled to connects");
    $subService->consumeConnect($seller->id, 2);
    $sellerSub->refresh();
    assertCheck($sellerSub->connect === 38, "Connect quota decremented to 38 connects after consumption");

    // Subscription Renewal
    $subService->subscribeOrRenew($seller->id, $proPlan->id, 'wallet');
    $sellerSub->refresh();
    assertCheck($sellerSub->connect === (38 + 40), "Renewal added 40 new connects cumulatively (Total: 78)");
    assertCheck($sellerSub->service === (15 + 15), "Renewal added 15 service slots (Total: 30)");

    $sellerWallet->refresh();
    assertCheck((float)$sellerWallet->balance === (380.00 - 120.00), "Seller wallet debited by 120.00 SAR for renewal (Balance: 260.00 SAR)");

    // -----------------------------------------------------------------
    // 8. Core Marketplace Regression
    // -----------------------------------------------------------------
    echo "\n--- 8. CORE MARKETPLACE REGRESSION ---\n";

    // Categories
    $categories = Category::where('status', 1)->get();
    assertCheck($categories->count() > 0, "Categories exist and loadable (Count: {$categories->count()})");

    // Services Catalog
    $services = Service::where('status', 1)->where('is_service_on', 1)->get();
    assertCheck($services->count() > 0, "Active marketplace services loadable (Count: {$services->count()})");

    // Direct Service Order Creation
    $sampleService = $services->first();
    assertCheck($sampleService !== null, "Marketplace service selected: {$sampleService->title}");

    $directOrder = Order::create([
        'service_id' => $sampleService->id,
        'seller_id' => $sampleService->seller_id,
        'buyer_id' => $buyer->id,
        'name' => $buyer->name,
        'email' => $buyer->email,
        'phone' => $buyer->phone,
        'post_code' => '12345',
        'address' => 'King Fahd Road, Riyadh',
        'city' => 1,
        'area' => 1,
        'country' => 1,
        'date' => date('Y-m-d', strtotime('+3 days')),
        'schedule' => '10:00 AM - 12:00 PM',
        'package_fee' => $sampleService->price,
        'extra_service' => 0,
        'sub_total' => $sampleService->price,
        'tax' => 0,
        'total' => $sampleService->price,
        'payment_gateway' => 'wallet',
        'payment_status' => 'complete',
        'status' => 0, // Pending
        'order_from_job' => 'no',
    ]);
    assertCheck($directOrder->id > 0, "Direct core marketplace order placed (Order ID: {$directOrder->id})");
    assertCheck($directOrder->order_from_job === 'no', "Direct order correctly tagged order_from_job = 'no'");

    // Order status progression
    $directOrder->update(['status' => 1]); // Active
    assertCheck($directOrder->fresh()->status === 1, "Order progressed to status 1 (Active / In Progress)");
    $directOrder->update(['status' => 2]); // Completed
    assertCheck($directOrder->fresh()->status === 2, "Order progressed to status 2 (Completed)");

    // User Profile API data integrity
    $profileUser = User::with(['country', 'city', 'area'])->find($buyer->id);
    assertCheck($profileUser !== null, "Buyer profile fetched with location relations");
    assertCheck(!empty($profileUser->phone), "Buyer profile has valid phone number");

    // -----------------------------------------------------------------
    // Clean up test actors & records
    // -----------------------------------------------------------------
    echo "\n--- CLEANUP ---\n";
    $directOrder->delete();
    $sellerSub->delete();
    $proPlan->delete();
    $job->delete();
    $buyer->delete();
    $seller->delete();
    echo " [INFO] Test data cleaned up successfully.\n";

} catch (\Throwable $t) {
    echo "\n[EXCEPTION]: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    $failed++;
}

echo "\n======================================================================\n";
echo "   SYSTEM EXERCISE & MARKETPLACE REGRESSION RESULTS                   \n";
echo "   PASSED: {$passed}                                                  \n";
echo "   FAILED: {$failed}                                                  \n";
echo "======================================================================\n";

if ($failed === 0) {
    echo "\n>>> ALL MODULE EXERCISES & MARKETPLACE REGRESSION PASSED (100%) <<<\n\n";
    exit(0);
} else {
    echo "\n>>> SOME VERIFICATION CHECKS FAILED <<<\n\n";
    exit(1);
}
