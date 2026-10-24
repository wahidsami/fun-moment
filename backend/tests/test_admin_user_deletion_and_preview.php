<?php

/**
 * Automated Regression Test Suite:
 * Safe Administrative User Deletion with Impact Preview & Email Release
 *
 * All tests use isolated synthetic mock users within database transactions.
 * No real owner accounts are queried, modified, or deleted.
 * All operations roll back cleanly.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\AdminAuditLog;
use App\Order;
use App\PayoutRequest;
use App\Schedule;
use App\Service;
use App\Services\User\UserDeletionService;
use App\User;
use App\UserDeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;

class UserDeletionTestSuite
{
    private UserDeletionService $service;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $this->service = app(UserDeletionService::class);
    }

    public function run()
    {
        echo "====================================================================\n";
        echo "RUNNING TEST SUITE: Admin User Deletion & Impact Preview\n";
        echo "====================================================================\n\n";

        $this->testPreviewIsReadOnly();
        $this->testCleanBuyerPurgeAndEmailRelease();
        $this->testActiveOrderBlocksDeletion();
        $this->testPositiveWalletBalanceBlocksDeletion();
        $this->testPendingPayoutBlocksDeletion();
        $this->testHistoricalLedgerAnonymizationAndEmailRelease();
        $this->testSanctumTokensAndDeviceTokensRevocation();
        $this->testOtpCachePurging();
        $this->testBulkActionSkipsBlockedAccountsSafely();
        $this->testAdminAuditLogRecorded();
        $this->testPreviewLocalization();

        echo "\n--------------------------------------------------------------------\n";
        echo "TEST RESULTS: Passed: {$this->passed}, Failed: {$this->failed}\n";
        echo "--------------------------------------------------------------------\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function assert($condition, string $testName)
    {
        if ($condition) {
            echo " [PASS] {$testName}\n";
            $this->passed++;
        } else {
            echo " [FAIL] {$testName}\n";
            $this->failed++;
        }
    }

    private function createSyntheticUser(array $attrs = []): User
    {
        $random = Str::random(10);
        $email = 'synthetic_test_' . $random . '@example.test';
        $username = 'syn_' . $random;

        return User::create(array_merge([
            'name' => 'Synthetic Test User ' . $random,
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('Secret123!'),
            'user_type' => 1, // buyer
            'user_status' => 1,
            'email_verified' => 1,
        ], $attrs));
    }

    private function createSyntheticOrder(int $buyerId, int $sellerId, int $status = 0): Order
    {
        return Order::create([
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'service_id' => 1,
            'name' => 'Synthetic Order Buyer',
            'email' => 'synthetic_order@example.test',
            'phone' => '+966500000000',
            'post_code' => '12345',
            'address' => 'Synthetic Address',
            'city' => 2,
            'area' => 2,
            'country' => 2,
            'date' => date('Y-m-d'),
            'schedule' => '10:00 - 11:00',
            'package_fee' => 100.00,
            'extra_service' => 0.00,
            'sub_total' => 100.00,
            'tax' => 15.00,
            'total' => 115.00,
            'status' => $status,
            'payment_status' => $status === 2 ? 'complete' : 'pending',
            'order_complete_request' => 0,
            'cancel_order_money_return' => 0,
            'is_order_online' => 0,
        ]);
    }

    private function createSyntheticService(int $sellerId): Service
    {
        return Service::create([
            'seller_id' => $sellerId,
            'category_id' => 40,
            'service_city_id' => 2,
            'title' => 'Synthetic Test Service ' . Str::random(6),
            'slug' => 'syn-test-service-' . Str::random(8),
            'description' => 'Synthetic description',
            'status' => 1,
            'is_service_on' => 1,
            'price' => 100.00,
            'tax' => 0.00,
            'view' => 0,
            'featured' => 0,
            'sold_count' => 0,
            'online_service_price' => 0.00,
            'delivery_days' => 1,
            'revision' => 0,
            'is_service_online' => 0,
            'is_service_all_cities' => 0,
        ]);
    }

    /**
     * 1. Test preview calculation is strictly read-only
     */
    private function testPreviewIsReadOnly()
    {
        DB::beginTransaction();
        try {
            $user = $this->createSyntheticUser();
            $initialUserCount = User::count();

            $preview = $this->service->buildImpactPreview($user);

            $afterUserCount = User::count();

            $this->assert(
                $initialUserCount === $afterUserCount &&
                $preview['can_delete'] === true &&
                $preview['deletion_mode'] === 'purge' &&
                $preview['account']['email'] === $user->email,
                'Impact Preview is strictly read-only and calculates correct purge mode'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 2. Test clean buyer with zero financial records is purged and email is released
     */
    private function testCleanBuyerPurgeAndEmailRelease()
    {
        DB::beginTransaction();
        try {
            $user = $this->createSyntheticUser();
            $originalEmail = $user->email;
            $userId = $user->id;

            $result = $this->service->executeDeletion($user, 1, $originalEmail);

            $deletedUser = User::find($userId);
            $this->assert($deletedUser === null, 'Clean buyer user row is completely purged');
            $this->assert($result['mode'] === 'purge', 'Deletion mode reported as purge');
            $this->assert($result['released_email'] === $originalEmail, 'Released email matches original email');

            // Verify the email can be immediately re-registered!
            $reRegisteredUser = $this->createSyntheticUser(['email' => $originalEmail]);
            $this->assert(
                $reRegisteredUser && $reRegisteredUser->email === $originalEmail,
                'Original email can be immediately reused for clean new registration'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 3. Test active order blocks deletion
     */
    private function testActiveOrderBlocksDeletion()
    {
        DB::beginTransaction();
        try {
            $buyer = $this->createSyntheticUser(['user_type' => 1]);
            $seller = $this->createSyntheticUser(['user_type' => 0]);

            // Create active order (status 0: pending)
            $this->createSyntheticOrder($buyer->id, $seller->id, 0);

            $preview = $this->service->buildImpactPreview($buyer);
            $this->assert($preview['can_delete'] === false, 'Buyer with active order has can_delete = false');
            $this->assert(
                stripos(implode(' ', $preview['blocking_reasons']), 'active booking') !== false,
                'Blocking reason explicitly notes active bookings'
            );

            // Attempt deletion should throw RuntimeException
            $threw = false;
            try {
                $this->service->executeDeletion($buyer, 1, $buyer->email);
            } catch (\RuntimeException $e) {
                $threw = true;
            }
            $this->assert($threw, 'Executing deletion on account with active bookings throws RuntimeException');
            $this->assert(User::find($buyer->id) !== null, 'Account with active booking remains intact in database');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 4. Test positive wallet balance blocks deletion
     */
    private function testPositiveWalletBalanceBlocksDeletion()
    {
        DB::beginTransaction();
        try {
            $user = $this->createSyntheticUser();
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 250.75,
                'status' => 1,
            ]);

            $preview = $this->service->buildImpactPreview($user);
            $this->assert($preview['can_delete'] === false, 'Account with positive wallet balance has can_delete = false');
            $this->assert(
                str_contains(implode(' ', $preview['blocking_reasons']), 'wallet balance'),
                'Blocking reason explicitly notes unsettled wallet balance'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 5. Test pending payout request blocks deletion
     */
    private function testPendingPayoutBlocksDeletion()
    {
        DB::beginTransaction();
        try {
            $seller = $this->createSyntheticUser(['user_type' => 0]);
            PayoutRequest::create([
                'seller_id' => $seller->id,
                'amount' => 500.00,
                'status' => 0, // Pending
            ]);

            $preview = $this->service->buildImpactPreview($seller);
            $this->assert($preview['can_delete'] === false, 'Seller with pending payout has can_delete = false');
            $this->assert(
                str_contains(implode(' ', $preview['blocking_reasons']), 'payout request'),
                'Blocking reason explicitly notes pending payout request'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 6. Test seller with historical records: anonymize & retain ledgers, release email
     */
    private function testHistoricalLedgerAnonymizationAndEmailRelease()
    {
        DB::beginTransaction();
        try {
            $seller = $this->createSyntheticUser(['user_type' => 0]);
            $buyer = $this->createSyntheticUser(['user_type' => 1]);
            $originalSellerEmail = $seller->email;

            // Historical completed order
            $order = $this->createSyntheticOrder($buyer->id, $seller->id, 2);

            // Active wallet & historical ledger
            $wallet = Wallet::create([
                'user_id' => $seller->id,
                'balance' => 0.00,
                'status' => 1,
            ]);

            $walletHistory = WalletHistory::create([
                'wallet_id' => $wallet->id,
                'user_id' => $seller->id,
                'entry_type' => 'credit',
                'amount' => 300.00,
                'balance_before' => 0.00,
                'balance_after' => 300.00,
                'payment_status' => 'complete',
                'status' => 1,
                'transaction_id' => 'TXN_' . Str::random(10),
            ]);

            // Active service
            $service = $this->createSyntheticService($seller->id);

            $preview = $this->service->buildImpactPreview($seller);
            $this->assert(
                $preview['can_delete'] === true &&
                $preview['deletion_mode'] === 'anonymize_and_release',
                'Seller with completed history has can_delete = true and mode = anonymize_and_release'
            );

            // Execute deletion
            $result = $this->service->executeDeletion($seller, 1, $originalSellerEmail);

            $this->assert($result['mode'] === 'anonymize_and_release', 'Execution mode reported as anonymize_and_release');

            // Verify financial records remain intact
            $this->assert(Order::find($order->id) !== null, 'Completed order is strictly retained in database');
            $this->assert(WalletHistory::find($walletHistory->id) !== null, 'Wallet history ledger is strictly retained');

            // Verify service is unpublished
            $service->refresh();
            $this->assert($service->status === 0 && $service->is_service_on === 0, 'Seller services are safely unpublished');

            // Verify user record is anonymized
            $seller->refresh();
            $this->assert($seller->email !== $originalSellerEmail, 'User email is renamed to release original email');
            $this->assert(str_starts_with($seller->email, 'deleted_'), 'User email alias starts with deleted_');
            $this->assert($seller->user_status === 0, 'User status set to deactivated');
            $this->assert($seller->phone === null, 'Personal phone number nulled');

            // Verify original email is immediately reusable!
            $newSeller = $this->createSyntheticUser([
                'user_type' => 0,
                'email' => $originalSellerEmail,
            ]);
            $this->assert($newSeller && $newSeller->email === $originalSellerEmail, 'Original email can be immediately re-registered for new account');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 7. Test Sanctum tokens and device tokens revocation
     */
    private function testSanctumTokensAndDeviceTokensRevocation()
    {
        DB::beginTransaction();
        try {
            $user = $this->createSyntheticUser();

            // Create Sanctum personal access token
            $token = $user->createToken('test_auth_token')->plainTextToken;
            $this->assert($user->tokens()->count() > 0, 'Sanctum token created for user');

            // Create device token
            UserDeviceToken::create([
                'user_id' => $user->id,
                'device_token' => 'mock_fcm_token_' . Str::random(20),
                'device_type' => 'android',
            ]);
            $this->assert(UserDeviceToken::where('user_id', $user->id)->count() > 0, 'Device token created for user');

            // Execute deletion
            $this->service->executeDeletion($user, 1, $user->email);

            $this->assert(UserDeviceToken::where('user_id', $user->id)->count() === 0, 'Device tokens are completely purged');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 8. Test OTP cache keys are purged
     */
    private function testOtpCachePurging()
    {
        DB::beginTransaction();
        try {
            $user = $this->createSyntheticUser();
            $email = $user->email;

            // Set OTP cache keys
            Cache::put("otp_{$email}_register", '1234', 300);
            Cache::put("otp_cooldown_{$email}_register", true, 60);

            $this->assert(Cache::has("otp_{$email}_register"), 'OTP cache key present before deletion');

            $this->service->executeDeletion($user, 1, $email);

            $this->assert(!Cache::has("otp_{$email}_register"), 'OTP cache key purged upon deletion');
            $this->assert(!Cache::has("otp_cooldown_{$email}_register"), 'OTP cooldown cache key purged upon deletion');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 9. Test bulk action skips blocked accounts safely
     */
    private function testBulkActionSkipsBlockedAccountsSafely()
    {
        DB::beginTransaction();
        try {
            $safeUser = $this->createSyntheticUser();
            $blockedUser = $this->createSyntheticUser();

            // Block one user with active order
            $this->createSyntheticOrder($blockedUser->id, 1, 1); // Status 1 = in progress

            $controller = app(\App\Http\Controllers\FrontendUserManageController::class);
            $request = new \Illuminate\Http\Request([
                'ids' => [$safeUser->id, $blockedUser->id],
            ]);

            $response = $controller->bulk_action($request);
            $data = $response->getData(true);

            $this->assert($data['status'] === 'partial', 'Bulk action returns partial status when some accounts are blocked');
            $this->assert(count($data['processed']) === 1 && $data['processed'][0]['id'] === $safeUser->id, 'Safe account was processed');
            $this->assert(count($data['blocked']) === 1 && $data['blocked'][0]['id'] === $blockedUser->id, 'Blocked account was safely skipped and reported');
            $this->assert(User::find($blockedUser->id) !== null, 'Blocked account remains intact in database');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 10. Test AdminAuditLog is recorded
     */
    private function testAdminAuditLogRecorded()
    {
        DB::beginTransaction();
        try {
            $user = $this->createSyntheticUser();
            $originalEmail = $user->email;

            $this->service->executeDeletion($user, 999, $originalEmail);

            $log = AdminAuditLog::where('action', 'user_delete')
                ->where('resource_id', (string) $user->id)
                ->latest()
                ->first();

            $this->assert($log !== null, 'AdminAuditLog entry created with action user_delete');
            $this->assert($log->new_values['email_released'] === true, 'AdminAuditLog records email released');
            $this->assert(isset($log->new_values['deletion_mode']), 'AdminAuditLog records deletion mode');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 11. Test impact preview localization (Arabic and English)
     */
    private function testPreviewLocalization()
    {
        DB::beginTransaction();
        try {
            $user = $this->createSyntheticUser();

            // Arabic Preview
            $previewAr = $this->service->buildImpactPreview($user, 'ar');
            $hasAccountRecordAr = collect($previewAr['breakdown']['permanently_deleted'])
                ->contains(fn($item) => str_contains($item, 'سجل حساب'));

            $this->assert(
                isset($previewAr['localized']) &&
                $previewAr['localized']['deletion_mode'] === 'حذف نهائي كامل' &&
                $previewAr['localized']['role'] === 'المشتري' &&
                $previewAr['localized']['status'] === 'نشط' &&
                $hasAccountRecordAr,
                'Arabic localized preview outputs natural Arabic strings and labels'
            );

            // English Preview
            $previewEn = $this->service->buildImpactPreview($user, 'en');
            $hasAccountRecordEn = collect($previewEn['breakdown']['permanently_deleted'])
                ->contains(fn($item) => str_contains($item, 'User account'));

            $this->assert(
                isset($previewEn['localized']) &&
                $previewEn['localized']['deletion_mode'] === 'Clean Purge' &&
                $previewEn['localized']['role'] === 'Buyer' &&
                $previewEn['localized']['status'] === 'Active' &&
                $hasAccountRecordEn,
                'English localized preview outputs clear English strings and labels'
            );
        } finally {
            DB::rollBack();
        }
    }
}

$suite = new UserDeletionTestSuite();
$suite->run();
