<?php

require_once __DIR__ . '/../backend/vendor/autoload.php';

$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\User;
use App\Admin;
use App\AdminAuditLog;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;
use Modules\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;

echo "========================================================\n";
echo "   FUN MOMENT — WALLET MODULE END-TO-END VERIFICATION   \n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest($condition, $description) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $description\n";
        $passed++;
    } else {
        echo " [FAIL] $description\n";
        $failed++;
    }
}

try {
    // 1. Verify DB Schema & Constraints
    echo "\n--- 1. DATABASE SCHEMA & CONSTRAINTS ---\n";
    $walletsTableExists = DB::getSchemaBuilder()->hasTable('wallets');
    assertTest($walletsTableExists, "Table 'wallets' exists in PostgreSQL");

    $historyTableExists = DB::getSchemaBuilder()->hasTable('wallet_histories');
    assertTest($historyTableExists, "Table 'wallet_histories' exists in PostgreSQL");

    // 2. Setup Test Entities
    echo "\n--- 2. INITIALIZE USERS & WALLETS ---\n";
    $testBuyer = User::firstOrCreate(
        ['email' => 'wallet_test_buyer@funmoment.test'],
        ['name' => 'Wallet Test Buyer', 'username' => 'wallet_buyer', 'password' => bcrypt('password123'), 'user_type' => 1]
    );
    assertTest($testBuyer->id > 0, "Buyer user initialized (ID: {$testBuyer->id})");

    $testSeller = User::firstOrCreate(
        ['email' => 'wallet_test_seller@funmoment.test'],
        ['name' => 'Wallet Test Seller', 'username' => 'wallet_seller', 'password' => bcrypt('password123'), 'user_type' => 0]
    );
    assertTest($testSeller->id > 0, "Seller user initialized (ID: {$testSeller->id})");

    $testAdmin = Admin::first();
    if (!$testAdmin) {
        $testAdmin = Admin::create([
            'name' => 'Test Admin',
            'username' => 'test_admin',
            'email' => 'admin_test@funmoment.test',
            'password' => bcrypt('password123'),
        ]);
    }
    assertTest($testAdmin->id > 0, "Admin user present (ID: {$testAdmin->id})");

    // Reset test wallets to 0
    WalletHistory::whereIn('user_id', [$testBuyer->id, $testSeller->id])->delete();
    $buyerWallet = Wallet::getOrCreateForUser($testBuyer->id);
    $buyerWallet->update(['balance' => 0.00, 'pending_balance' => 0.00, 'total_earned' => 0.00, 'total_spent' => 0.00, 'status' => 1]);
    
    $sellerWallet = Wallet::getOrCreateForUser($testSeller->id);
    $sellerWallet->update(['balance' => 0.00, 'pending_balance' => 0.00, 'total_earned' => 0.00, 'total_spent' => 0.00, 'status' => 1]);

    assertTest((float)$buyerWallet->fresh()->balance === 0.00, "Buyer wallet initialized with 0 balance");
    assertTest((float)$sellerWallet->fresh()->balance === 0.00, "Seller wallet initialized with 0 balance");

    $walletService = app(WalletService::class);

    // 3. Deposit Flow (Credit Ledger & Balance)
    echo "\n--- 3. DEPOSIT WORKFLOW & LEDGER INTEGRITY ---\n";
    $depositRequest = $walletService->createDepositRequest($testBuyer, 500.00, 'bank_transfer');
    assertTest($depositRequest->entry_type === 'credit', "Deposit history created with entry_type = 'credit'");
    assertTest($depositRequest->payment_status === 'pending', "Deposit history payment_status = 'pending'");
    assertTest((float)$buyerWallet->fresh()->balance === 0.00, "Buyer wallet balance remains unchanged before confirmation");

    $completedDeposit = $walletService->completeDeposit($depositRequest->id);
    $buyerWallet->refresh();
    assertTest($completedDeposit->payment_status === 'complete', "Deposit history marked as complete");
    assertTest((float)$buyerWallet->balance === 500.00, "Buyer balance accurately increased to 500.00");
    assertTest((float)$completedDeposit->balance_before === 0.00, "Ledger records balance_before = 0.00");
    assertTest((float)$completedDeposit->balance_after === 500.00, "Ledger records balance_after = 500.00");

    // 4. Debit Flow & Insufficient Balance Protection
    echo "\n--- 4. DEBIT & OVERDRAFT PROTECTION ---\n";
    $overdraftThrown = false;
    try {
        $walletService->debit($buyerWallet, 600.00, 'order_payment', 'ord_test_overdraft');
    } catch (\Exception $e) {
        $overdraftThrown = true;
    }
    assertTest($overdraftThrown, "Overdraft correctly rejected with exception");
    assertTest((float)$buyerWallet->fresh()->balance === 500.00, "Buyer balance protected from negative value (still 500.00)");

    // Valid debit
    $debitHistory = $walletService->debit($buyerWallet, 150.00, 'order_payment', 'ord_test_valid', 'Order #101 payment');
    $buyerWallet->refresh();
    assertTest((float)$buyerWallet->balance === 350.00, "Buyer balance accurately debited to 350.00");
    assertTest((float)$buyerWallet->total_spent === 150.00, "Buyer total_spent accurately updated to 150.00");
    assertTest((float)$debitHistory->balance_before === 500.00, "Ledger balance_before recorded as 500.00");
    assertTest((float)$debitHistory->balance_after === 350.00, "Ledger balance_after recorded as 350.00");

    // 5. Seller Earnings & Payout Workflow (Hold, Disburse, Release)
    echo "\n--- 5. SELLER EARNINGS & PAYOUT WORKFLOW ---\n";
    // Credit seller earnings
    $walletService->credit($sellerWallet, 1000.00, 'wallet', 'order_earning', 'ord_earn_1', 'Service earnings from Order #201');
    $sellerWallet->refresh();
    assertTest((float)$sellerWallet->balance === 1000.00, "Seller credited with 1000.00 earnings");
    assertTest((float)$sellerWallet->total_earned === 1000.00, "Seller total_earned accurately updated to 1000.00");

    // Seller requests payout of 400.00
    $payoutRequest = $walletService->requestPayout($sellerWallet, 400.00, 'bank_transfer', 'SA1234567890');
    $sellerWallet->refresh();
    assertTest((float)$sellerWallet->balance === 600.00, "Available balance reduced by 400.00 upon payout request");
    assertTest((float)$sellerWallet->pending_balance === 400.00, "Funds held in pending_balance (400.00)");
    assertTest($payoutRequest->id > 0, "Payout request created (ID: {$payoutRequest->id})");

    // Admin approves payout
    $walletService->settlePayout($payoutRequest->id, 'completed', 'Approved by admin finance', $testAdmin->id);
    $sellerWallet->refresh();
    assertTest((float)$sellerWallet->pending_balance === 0.00, "Pending balance cleared upon payout disbursement");
    assertTest((float)$sellerWallet->balance === 600.00, "Available balance remains 600.00 after disbursement");
    
    $payoutSettled = WalletHistory::where('reference_type', 'payout_request')
        ->where('reference_id', (string) $payoutRequest->id)
        ->where('entry_type', 'payout')
        ->first();
    assertTest($payoutSettled !== null, "Ledger recorded 'payout' disbursement entry");
    assertTest($payoutSettled->payment_status === 'complete', "Payout disbursement status complete");

    // Payout Rejection & Release
    $payoutRejectReq = $walletService->requestPayout($sellerWallet, 200.00, 'bank_transfer');
    $sellerWallet->refresh();
    assertTest((float)$sellerWallet->balance === 400.00 && (float)$sellerWallet->pending_balance === 200.00, "Second payout held (bal: 400, pending: 200)");
    
    $walletService->settlePayout($payoutRejectReq->id, 'rejected', 'Rejected due to invalid IBAN', $testAdmin->id);
    $sellerWallet->refresh();
    assertTest((float)$sellerWallet->balance === 600.00 && (float)$sellerWallet->pending_balance === 0.00, "Rejected payout released funds back to balance (bal: 600, pending: 0)");

    // 6. Admin Controlled Adjustments & Audit Logging
    echo "\n--- 6. CONTROLLED ADMIN ADJUSTMENTS & AUDIT TRAIL ---\n";
    $initialAuditCount = AdminAuditLog::where('action', 'wallet_adjustment')->count();
    $adjustment = $walletService->adminAdjustment(
        $buyerWallet,
        75.00,
        'credit',
        $testAdmin->id,
        'Compensation for promotional credit'
    );
    $buyerWallet->refresh();
    assertTest((float)$buyerWallet->balance === 425.00, "Admin credit increased balance from 350.00 to 425.00");
    assertTest($adjustment->entry_type === 'adjustment', "Ledger entry_type is 'adjustment'");
    assertTest($adjustment->admin_id === $testAdmin->id, "Ledger records admin_id");
    assertTest($adjustment->admin_note === 'Compensation for promotional credit', "Ledger records admin_note");

    $newAuditCount = AdminAuditLog::where('action', 'wallet_adjustment')->count();
    assertTest($newAuditCount === $initialAuditCount + 1, "AdminAuditLog recorded immutable audit entry");

    // Admin Debit Adjustment
    $walletService->adminAdjustment(
        $buyerWallet,
        25.00,
        'debit',
        $testAdmin->id,
        'Administrative correction'
    );
    $buyerWallet->refresh();
    assertTest((float)$buyerWallet->balance === 400.00, "Admin debit decreased balance to 400.00");

    // 7. Wallet Suspension & Security Policy
    echo "\n--- 7. WALLET SUSPENSION SECURITY GUARD ---\n";
    $buyerWallet->update(['status' => 0]);
    $suspendedBlock = false;
    try {
        $walletService->debit($buyerWallet, 10.00, 'order_payment');
    } catch (\Exception $e) {
        $suspendedBlock = true;
    }
    assertTest($suspendedBlock, "Debit blocked on suspended wallet");

    $buyerWallet->update(['status' => 1]); // restore to active
    assertTest($buyerWallet->fresh()->status === 1, "Wallet restored to active");

    // 8. Controller JSON Response Contracts
    echo "\n--- 8. API RESPONSE CONTRACTS ---\n";
    $apiController = app(\Modules\Wallet\Http\Controllers\WalletApiController::class);
    $adminController = app(\Modules\Wallet\Http\Controllers\AdminWalletApiController::class);

    // Mock authenticated user for Sanctum in API test
    auth('sanctum')->setUser($testBuyer);

    $req = \Illuminate\Http\Request::create('/api/v1/user/wallet/balance', 'GET');
    $balanceResp = $apiController->buyerBalance($req);
    $balanceData = $balanceResp->getData(true);
    assertTest($balanceResp->getStatusCode() === 200, "Buyer balance API returns HTTP 200");
    assertTest(isset($balanceData['balance']), "Buyer balance response includes 'balance' string");
    assertTest(isset($balanceData['raw_balance']) && (float)$balanceData['raw_balance'] === 400.00, "Buyer balance response includes exact 'raw_balance' 400.00");

    $historyResp = $apiController->buyerHistory($req);
    $historyData = $historyResp->getData(true);
    assertTest($historyResp->getStatusCode() === 200, "Buyer history API returns HTTP 200");
    assertTest(is_array($historyData['history']) && count($historyData['history']) > 0, "Buyer history response includes history array with entries");

    // Admin API
    $adminReq = \Illuminate\Http\Request::create('/admin-home/wallets-json', 'GET');
    $adminListResp = $adminController->index($adminReq);
    $adminListData = $adminListResp->getData(true);
    assertTest($adminListResp->getStatusCode() === 200, "Admin wallets list API returns HTTP 200");
    assertTest(isset($adminListData['summary']['total_wallets']), "Admin summary contains total_wallets KPI");
    assertTest(isset($adminListData['wallets']) && count($adminListData['wallets']) >= 2, "Admin list returns wallets collection");

    $adminDetailResp = $adminController->show($testBuyer->id);
    $adminDetailData = $adminDetailResp->getData(true);
    assertTest($adminDetailResp->getStatusCode() === 200, "Admin wallet details API returns HTTP 200");
    assertTest(isset($adminDetailData['ledger']) && count($adminDetailData['ledger']) > 0, "Admin details includes full chronological ledger");

} catch (\Throwable $e) {
    echo "\n[ERROR EXCEPTION] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n========================================================\n";
echo "   WALLET MODULE TEST RESULTS: Passed: $passed, Failed: $failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
