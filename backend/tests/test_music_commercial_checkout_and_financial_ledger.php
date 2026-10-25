<?php

/**
 * FUN MOMENT — Phase 5 Commercial Checkout, Rentals, Payments, Commission, VAT & Financial Ledger Test Suite
 *
 * Verifies:
 * 1. Exact money calculations and integer halala math
 * 2. Saudi ZATCA VAT scenarios (Deemed Supplier, Intermediary, Platform Direct, Inclusive vs Exclusive)
 * 3. Commercial settings governance, validation, and audit logging
 * 4. Server-side checkout disablement gate (music_checkout_enabled = 0)
 * 5. Order creation, server authority, and request idempotency
 * 6. Wallet payment processing, atomic debit, and balance checks
 * 7. PayTabs callback verification, signature validation, and failure isolation
 * 8. Entitlement fulfillment (sale perpetual vs rental duration expiry)
 * 9. Seller earnings ledger entries and refund reversals
 * 10. Admin refund workflow, wallet credit, and entitlement revocation
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\AdminAuditLog;
use App\MusicEntitlement;
use App\MusicGenre;
use App\MusicOffer;
use App\MusicOrder;
use App\MusicRefundAttempt;
use App\MusicSellerLedgerEntry;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\MusicFinancialCalculationService;
use App\Services\Music\MusicFulfillmentService;
use App\Services\Music\MusicRefundService;
use App\Services\Music\MusicStreamService;
use App\Services\Payment\PayTabsPaymentService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Services\WalletService;

class Phase5TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function run(): void
    {
        echo "\n========================================================================" . PHP_EOL;
        echo "FUN MOMENT — PHASE 5 COMMERCIAL CHECKOUT & FINANCIAL LEDGER TEST SUITE" . PHP_EOL;
        echo "========================================================================\n" . PHP_EOL;

        DB::beginTransaction();

        try {
            $this->testExactHalalaArithmetic();
            $this->testTaxCalculationScenarios();
            $this->testPriceDisplayConventions();
            $this->testAdminCommercialSettingsAndAuditLog();
            $this->testCheckoutReadinessGate();
            $this->testOrderCreationAndIdempotency();
            $this->testWalletPaymentAndAtomicDebit();
            $this->testPayTabsSignatureAndCallbackFulfillment();
            $this->testEntitlementIssuanceAndLicensingRules();
            $this->testAdminRefundWorkflowAndSellerLedgerReversal();
            $this->testPhase51WalletAndGatewayRefundScenarios();
        } catch (\Throwable $e) {
            $this->failed++;
            $this->errors[] = "Fatal test exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
            echo "  [FATAL ERROR] " . $e->getMessage() . PHP_EOL;
        } finally {
            DB::rollBack();
        }

        echo "\n======================================================" . PHP_EOL;
        echo "Phase 5 Test Suite Results:" . PHP_EOL;
        echo "  Passed: {$this->passed}" . PHP_EOL;
        echo "  Failed: {$this->failed}" . PHP_EOL;
        echo "======================================================\n" . PHP_EOL;

        if ($this->failed > 0) {
            echo "Failures:\n";
            foreach ($this->errors as $err) {
                echo "  - {$err}\n";
            }
            exit(1);
        }

        exit(0);
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}" . PHP_EOL;
        } else {
            $this->failed++;
            $this->errors[] = $message;
            echo "  [FAIL] {$message}" . PHP_EOL;
        }
    }

    // --------------------------------------------------------------------------
    // Test Helpers
    // --------------------------------------------------------------------------

    private function createUser(string $type = 'buyer', string $vatStatus = 'unverified'): User
    {
        return User::create([
            'name' => 'Test User ' . uniqid(),
            'email' => 'user_' . uniqid() . '@example.com',
            'username' => 'user_' . uniqid(),
            'password' => bcrypt('password123'),
            'user_type' => $type === 'seller' ? User::USER_TYPE_SELLER : User::USER_TYPE_BUYER,
            'user_status' => 1,
            'terms_condition' => 1,
            'vat_registered' => $vatStatus === 'verified',
            'vat_registration_number' => $vatStatus === 'verified' ? '300000000000003' : null,
            'vat_verification_status' => $vatStatus,
            'tax_residence_country' => 'SA',
        ]);
    }

    private function createTrack(User $seller): MusicTrack
    {
        $genre = MusicGenre::first() ?: MusicGenre::create([
            'name_en' => 'Electronic',
            'name_ar' => 'إلكتروني',
            'slug' => 'electronic-' . uniqid(),
            'status' => 1,
        ]);

        return MusicTrack::create([
            'title_en' => 'Phase 5 Commercial Track',
            'title_ar' => 'مقطع تجاري مرحلة 5',
            'artist_name' => 'DJ Test',
            'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
            'seller_id' => $seller->id,
            'genre_id' => $genre->id,
            'status' => MusicTrack::STATUS_APPROVED,
            'media_status' => MusicTrack::MEDIA_STATUS_READY,
            'duration_seconds' => 180,
            'preview_duration_seconds' => 20,
            'audio_format' => 'mp3',
            'audio_path_original' => 'masters/test.mp3',
            'audio_path_preview' => 'previews/test_preview.mp3',
            'audio_path_stream' => 'streams/test_stream.mp3',
            'rights_declaration_type' => 'exclusive',
            'rights_agreement_accepted' => true,
        ]);
    }

    private function createAdmin(string $role = 'Super Admin'): Admin
    {
        $id = DB::table('admins')->insertGetId([
            'name' => 'Admin ' . uniqid(),
            'username' => 'admin_' . uniqid(),
            'email' => 'admin_' . uniqid() . '@funmoment.sa',
            'password' => bcrypt('password123'),
            'role' => $role,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return Admin::find($id);
    }

    // --------------------------------------------------------------------------
    // Test Suites
    // --------------------------------------------------------------------------

    /**
     * 1. Test Exact Halala Math
     */
    private function testExactHalalaArithmetic(): void
    {
        echo "\n--- 1. Testing Exact Integer Halala Arithmetic ---" . PHP_EOL;

        $this->assert(MusicFinancialCalculationService::toHalalas(10.00) === 1000, "10.00 SAR converts to 1000 halalas");
        $this->assert(MusicFinancialCalculationService::toHalalas(19.99) === 1999, "19.99 SAR converts to 1999 halalas");
        $this->assert(MusicFinancialCalculationService::toHalalas(0.05) === 5, "0.05 SAR converts to 5 halalas");
        $this->assert(MusicFinancialCalculationService::fromHalalas(1000) === 10.00, "1000 halalas converts back to 10.00 SAR");
        $this->assert(MusicFinancialCalculationService::fromHalalas(1999) === 19.99, "1999 halalas converts back to 19.99 SAR");

        // Rounding precision check on 1/3 split
        $halalas = 1000;
        $split = (int) round($halalas * (15.0 / 100.0));
        $this->assert($split === 150, "15% of 1000 halalas is exact 150 halalas (1.50 SAR)");
    }

    /**
     * 2. Test ZATCA VAT Scenarios
     */
    private function testTaxCalculationScenarios(): void
    {
        echo "\n--- 2. Testing Saudi ZATCA Tax Calculation Scenarios ---" . PHP_EOL;

        $sellerUnregistered = $this->createUser('seller', 'unverified');
        $trackA = $this->createTrack($sellerUnregistered);

        $offerSale = MusicOffer::create([
            'track_id' => $trackA->id,
            'offer_type' => MusicOffer::OFFER_TYPE_SALE,
            'price' => 100.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $calcService = new MusicFinancialCalculationService();

        // Scenario A: Deemed Supplier (Platform is deemed supplier for non-VAT registered seller)
        // Listed: 100 SAR, VAT enabled (15%), Commission (15%)
        $breakdownA = $calcService->calculateOfferBreakdown($offerSale, $sellerUnregistered, [
            'vat_enabled' => true,
            'vat_rate' => 15.00,
            'commission_rate' => 15.00,
            'price_display_mode' => 'exclusive',
        ]);

        $this->assert($breakdownA['tax_treatment'] === MusicOrder::TAX_DEEMED_SUPPLIER, "Non-VAT registered seller resolves as deemed_supplier");
        $this->assert($breakdownA['subtotal'] === 100.00, "Subtotal is 100.00 SAR");
        $this->assert($breakdownA['vat_rate'] === 15.00, "Buyer VAT rate is 15.00%");
        $this->assert($breakdownA['vat_amount'] === 15.00, "Buyer VAT amount is 15.00 SAR (15% of 100)");
        $this->assert($breakdownA['total_amount'] === 115.00, "Buyer total payable is 115.00 SAR (100 + 15)");
        $this->assert($breakdownA['commission_amount'] === 15.00, "Platform commission is 15.00 SAR (15% of 100 SAR subtotal)");
        $this->assert($breakdownA['seller_earning'] === 85.00, "Seller net earning is 85.00 SAR (100 - 15)");
        $this->assert($breakdownA['vat_collected_for_remittance'] === 15.00, "Platform collects 15.00 SAR VAT for ZATCA remittance");

        // Scenario B: Intermediary (Seller is verified VAT-registered supplier)
        $sellerRegistered = $this->createUser('seller', 'verified');
        $trackB = $this->createTrack($sellerRegistered);
        $offerRent = MusicOffer::create([
            'track_id' => $trackB->id,
            'offer_type' => MusicOffer::OFFER_TYPE_RENT,
            'price' => 50.00,
            'currency' => 'SAR',
            'rental_duration_days' => 7,
            'is_active' => true,
        ]);

        $breakdownB = $calcService->calculateOfferBreakdown($offerRent, $sellerRegistered, [
            'vat_enabled' => true,
            'vat_rate' => 15.00,
            'commission_rate' => 10.00,
            'price_display_mode' => 'exclusive',
        ]);

        $this->assert($breakdownB['tax_treatment'] === MusicOrder::TAX_INTERMEDIARY, "Verified VAT-registered seller resolves as intermediary");
        $this->assert($breakdownB['commission_amount'] === 5.00, "Platform commission is 5.00 SAR (10% of 50 SAR)");
        $this->assert($breakdownB['commission_vat_amount'] === 0.75, "VAT on platform service fee is 0.75 SAR (15% of 5.00 SAR)");
        $this->assert($breakdownB['platform_fee_total'] === 5.75, "Total platform fee charged to seller is 5.75 SAR (5.00 + 0.75)");

        // Scenario C: Direct Platform Track (Admin Owned)
        $admin = $this->createAdmin();
        $adminTrack = MusicTrack::create([
            'title_en' => 'Admin Owned Track',
            'title_ar' => 'مقطع ملك المنصة',
            'artist_name' => 'Platform Sounds',
            'owner_type' => MusicTrack::OWNER_TYPE_ADMIN,
            'admin_id' => $admin->id,
            'genre_id' => $trackA->genre_id,
            'status' => MusicTrack::STATUS_APPROVED,
            'media_status' => MusicTrack::MEDIA_STATUS_READY,
            'duration_seconds' => 120,
            'preview_duration_seconds' => 20,
            'audio_format' => 'mp3',
            'audio_path_original' => 'masters/admin.mp3',
            'rights_declaration_type' => 'exclusive',
            'rights_agreement_accepted' => true,
        ]);

        $adminOffer = MusicOffer::create([
            'track_id' => $adminTrack->id,
            'offer_type' => MusicOffer::OFFER_TYPE_SALE,
            'price' => 200.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $breakdownC = $calcService->calculateOfferBreakdown($adminOffer, null, [
            'vat_enabled' => true,
            'vat_rate' => 15.00,
            'commission_rate' => 15.00,
        ]);

        $this->assert($breakdownC['tax_treatment'] === MusicOrder::TAX_DIRECT_PLATFORM, "Admin track resolves as direct_platform");
        $this->assert($breakdownC['seller_earning'] === 0.00, "Seller earning on admin track is strictly 0.00 SAR");
        $this->assert($breakdownC['platform_revenue'] === 200.00, "Platform retains 100% of subtotal as revenue (200.00 SAR)");
        $this->assert($breakdownC['total_amount'] === 230.00, "Total payable is 230.00 SAR (200 + 30 VAT)");
    }

    /**
     * 3. Test Price Display Conventions (Exclusive vs Inclusive)
     */
    private function testPriceDisplayConventions(): void
    {
        echo "\n--- 3. Testing Price Display Conventions (Inclusive vs Exclusive) ---" . PHP_EOL;

        $calcService = new MusicFinancialCalculationService();
        $seller = $this->createUser('seller');
        $track = $this->createTrack($seller);

        // Listed price = 115.00 SAR inclusive of 15% VAT
        $offer = MusicOffer::create([
            'track_id' => $track->id,
            'offer_type' => MusicOffer::OFFER_TYPE_SALE,
            'price' => 115.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $inclusive = $calcService->calculateOfferBreakdown($offer, $seller, [
            'vat_enabled' => true,
            'vat_rate' => 15.00,
            'commission_rate' => 10.00,
            'price_display_mode' => 'inclusive',
        ]);

        $this->assert($inclusive['price_display_mode'] === 'inclusive', "Display mode reported as inclusive");
        $this->assert($inclusive['total_amount'] === 115.00, "Inclusive buyer total payable equals listed price (115.00 SAR)");
        $this->assert($inclusive['subtotal'] === 100.00, "Extracted subtotal before VAT is exactly 100.00 SAR");
        $this->assert($inclusive['vat_amount'] === 15.00, "Extracted VAT amount is exactly 15.00 SAR");
        $this->assert($inclusive['commission_amount'] === 10.00, "Commission is 10.00 SAR (10% of 100 SAR subtotal)");
        $this->assert($inclusive['seller_earning'] === 90.00, "Seller earning is 90.00 SAR (100 - 10)");
    }

    /**
     * 4. Test Admin Commercial Settings & Audit Trail
     */
    private function testAdminCommercialSettingsAndAuditLog(): void
    {
        echo "\n--- 4. Testing Admin Commercial Settings & Audit Log ---" . PHP_EOL;

        MusicSetting::set('music_checkout_enabled', '0');
        MusicSetting::set('music_vat_enabled', '0');
        MusicSetting::set('music_vat_rate', '15.00');
        MusicSetting::set('platform_commission_rate', '12.50');

        $this->assert(!MusicSetting::isCheckoutEnabled(), "Checkout is initially disabled by default");
        $this->assert(!MusicSetting::isVatEnabled(), "VAT calculation is initially disabled by default");
        $this->assert(MusicSetting::getVatRate() === 15.00, "Default configured VAT rate is 15.00%");
        $this->assert((float) MusicSetting::get('platform_commission_rate') === 12.50, "Platform commission is 12.50%");

        // Test Public Config non-disclosure
        $publicConfig = MusicSetting::toPublicConfig();
        $this->assert(isset($publicConfig['checkout_enabled']), "Public config includes checkout_enabled switch");
        $this->assert(isset($publicConfig['vat_enabled']), "Public config includes vat_enabled switch");
        $this->assert(!isset($publicConfig['platform_commission_rate']), "Public config strictly hides platform commission rate");

        // Test Admin Update with Audit Log
        $admin = $this->createAdmin();
        auth('admin')->login($admin);

        $controller = app(\App\Http\Controllers\AdminMusicSettingController::class);
        $req = Request::create('/admin-home/music-settings-json', 'POST', [
            'music_marketplace_enabled' => '1',
            'paid_preview_duration_seconds' => 25,
            'max_audio_upload_mb' => 20,
            'platform_commission_rate' => 18.00,
            'allowed_audio_formats' => 'mp3,wav,aac,flac,m4a',
            'seller_submissions_enabled' => '1',
            'seller_tracks_require_approval' => '1',
            'offer_free_enabled' => '1',
            'offer_rent_enabled' => '1',
            'offer_sale_enabled' => '1',
            'default_rental_duration_days' => 14,
            'music_vat_enabled' => '1',
            'music_vat_rate' => 15.00,
            'music_checkout_enabled' => '1',
            'music_price_display_mode' => 'exclusive',
            'music_tax_policy_status' => 'approved',
        ]);

        $res = $controller->update($req);
        $resData = json_decode($res->getContent(), true);

        $this->assert($res->getStatusCode() === 200, "Admin updates commercial settings with HTTP 200");
        $this->assert($resData['data']['music_checkout_enabled'] === true, "Checkout switch updated to true");
        $this->assert($resData['data']['music_vat_enabled'] === true, "VAT switch updated to true");
        $this->assert((float) $resData['data']['platform_commission_rate'] === 18.00, "Commission rate updated to 18.00%");

        // Verify AdminAuditLog was created
        $audit = AdminAuditLog::where('action', 'update_music_settings')->latest()->first();
        $this->assert($audit !== null, "AdminAuditLog entry created for settings update");
        $this->assert($audit->admin_id === $admin->id, "Audit log attributes admin ID correctly");
    }

    /**
     * 5. Test Checkout Readiness Gate (music_checkout_enabled = 0)
     */
    private function testCheckoutReadinessGate(): void
    {
        echo "\n--- 5. Testing Server Checkout Readiness Gate ---" . PHP_EOL;

        // Force disable checkout
        MusicSetting::set('music_checkout_enabled', '0');

        $buyer = $this->createUser('buyer');
        $seller = $this->createUser('seller');
        $track = $this->createTrack($seller);
        $offer = MusicOffer::create([
            'track_id' => $track->id,
            'offer_type' => MusicOffer::OFFER_TYPE_SALE,
            'price' => 50.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        auth('sanctum')->setUser($buyer);

        $orderController = app(\App\Http\Controllers\Api\MusicOrderController::class);
        $req = Request::create('/api/v1/music/orders', 'POST', [
            'offer_id' => $offer->id,
            'payment_gateway' => 'wallet',
        ]);

        $res = $orderController->store($req);
        $resData = json_decode($res->getContent(), true);

        $this->assert($res->getStatusCode() === 403, "Order creation strictly rejected with HTTP 403 when checkout is disabled");
        $this->assert($resData['error_code'] === 'CHECKOUT_DISABLED', "Error code CHECKOUT_DISABLED returned");
        $this->assert(MusicOrder::count() === 0, "No order records created while checkout is disabled");
    }

    /**
     * 6. Test Order Creation, Server Calculation Authority & Idempotency
     */
    private function testOrderCreationAndIdempotency(): void
    {
        echo "\n--- 6. Testing Order Creation, Server Authority & Idempotency ---" . PHP_EOL;

        // Enable checkout for authorized testing
        MusicSetting::set('music_checkout_enabled', '1');
        MusicSetting::set('music_vat_enabled', '1');
        MusicSetting::set('music_vat_rate', '15.00');
        MusicSetting::set('platform_commission_rate', '10.00');

        $buyer = $this->createUser('buyer');
        $seller = $this->createUser('seller');
        $track = $this->createTrack($seller);
        $offer = MusicOffer::create([
            'track_id' => $track->id,
            'offer_type' => MusicOffer::OFFER_TYPE_RENT,
            'price' => 20.00,
            'currency' => 'SAR',
            'rental_duration_days' => 7,
            'is_active' => true,
        ]);

        // Fund buyer wallet
        $walletService = app(WalletService::class);
        $walletService->credit($buyer, 100.00, 'test_topup');

        auth('sanctum')->setUser($buyer);

        $orderController = app(\App\Http\Controllers\Api\MusicOrderController::class);
        $idempotencyKey = 'IDEMP-' . uniqid();

        // Client attempts to spoof price as 1.00 SAR
        $req = Request::create('/api/v1/music/orders', 'POST', [
            'offer_id' => $offer->id,
            'payment_gateway' => 'wallet',
            'idempotency_key' => $idempotencyKey,
            'amount' => 1.00, // Tampered client price!
            'commission_rate' => 0.00, // Tampered commission!
        ]);

        $res = $orderController->store($req);
        $resData = json_decode($res->getContent(), true);

        $this->assert($res->getStatusCode() === 201, "Order creates successfully with HTTP 201");
        $order = MusicOrder::where('idempotency_key', $idempotencyKey)->first();
        $this->assert($order !== null, "Order stored in database with idempotency key");

        // Verify server ignored client spoofed amounts
        $this->assert((float) $order->subtotal === 20.00, "Server calculated subtotal of 20.00 SAR (client spoof ignored)");
        $this->assert((float) $order->vat_amount === 3.00, "Server calculated 15% VAT of 3.00 SAR");
        $this->assert((float) $order->amount === 23.00, "Total payable is 23.00 SAR (20 + 3)");
        $this->assert((float) $order->commission_amount === 2.00, "Server calculated 10% commission of 2.00 SAR");
        $this->assert((float) $order->seller_earning === 18.00, "Server calculated seller earning of 18.00 SAR (20 - 2)");

        // Idempotency: Resubmit with same key
        $res2 = $orderController->store($req);
        $res2Data = json_decode($res2->getContent(), true);

        $this->assert($res2->getStatusCode() === 200, "Repeated idempotency request returns HTTP 200");
        $this->assert($res2Data['already_exists'] === true, "Response indicates existing order retrieved");
        $this->assert($res2Data['order']['id'] === $order->id, "Retrieved order matches existing order ID");
        $this->assert(MusicOrder::where('idempotency_key', $idempotencyKey)->count() === 1, "Exactly one order created");
    }

    /**
     * 7. Test Wallet Payment & Atomic Debit
     */
    private function testWalletPaymentAndAtomicDebit(): void
    {
        echo "\n--- 7. Testing Wallet Payment, Atomic Debit & Balance Checks ---" . PHP_EOL;

        MusicSetting::set('music_checkout_enabled', '1');
        MusicSetting::set('music_vat_enabled', '0'); // No VAT for simple balance check
        MusicSetting::set('platform_commission_rate', '10.00');

        $buyer = $this->createUser('buyer');
        $seller = $this->createUser('seller');
        $track = $this->createTrack($seller);
        $offer = MusicOffer::create([
            'track_id' => $track->id,
            'offer_type' => MusicOffer::OFFER_TYPE_SALE,
            'price' => 50.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $walletService = app(WalletService::class);
        $wallet = Wallet::getOrCreateForUser($buyer->id);

        auth('sanctum')->setUser($buyer);
        $orderController = app(\App\Http\Controllers\Api\MusicOrderController::class);

        // Subcase A: Insufficient Wallet Balance (0 SAR available, 50 SAR required)
        $reqFail = Request::create('/api/v1/music/orders', 'POST', [
            'offer_id' => $offer->id,
            'payment_gateway' => 'wallet',
        ]);
        $resFail = $orderController->store($reqFail);
        $resFailData = json_decode($resFail->getContent(), true);

        $this->assert($resFail->getStatusCode() === 422, "Insufficient wallet balance returns HTTP 422");
        $this->assert($resFailData['error_code'] === 'INSUFFICIENT_WALLET_BALANCE', "Error code INSUFFICIENT_WALLET_BALANCE returned");
        $this->assert((float) $wallet->fresh()->balance === 0.00, "Wallet balance untouched after rejected debit");

        // Subcase B: Sufficient Balance Debit
        $walletService->credit($buyer, 100.00, 'test_fund');
        $this->assert((float) $wallet->fresh()->balance === 100.00, "Wallet successfully credited to 100.00 SAR");

        $reqSuccess = Request::create('/api/v1/music/orders', 'POST', [
            'offer_id' => $offer->id,
            'payment_gateway' => 'wallet',
        ]);
        $resSuccess = $orderController->store($reqSuccess);
        $resSuccessData = json_decode($resSuccess->getContent(), true);

        $this->assert($resSuccess->getStatusCode() === 201, "Sufficient balance order succeeds with HTTP 201");
        $this->assert($resSuccessData['payment_status'] === MusicOrder::STATUS_COMPLETED, "Payment status completed");
        $this->assert((float) $wallet->fresh()->balance === 50.00, "Wallet atomically debited to 50.00 SAR (100 - 50)");

        // Subcase C: Prevent duplicate perpetual purchase
        $reqDuplicate = Request::create('/api/v1/music/orders', 'POST', [
            'offer_id' => $offer->id,
            'payment_gateway' => 'wallet',
        ]);
        $resDuplicate = $orderController->store($reqDuplicate);
        $resDupData = json_decode($resDuplicate->getContent(), true);

        $this->assert($resDuplicate->getStatusCode() === 422, "Duplicate purchase of perpetual track rejected with 422");
        $this->assert($resDupData['error_code'] === 'ALREADY_PURCHASED', "Error code ALREADY_PURCHASED returned");
        $this->assert((float) $wallet->fresh()->balance === 50.00, "Wallet was not debited for duplicate purchase attempt");
    }

    /**
     * 8. Test PayTabs Callback Verification & Fulfillment
     */
    private function testPayTabsSignatureAndCallbackFulfillment(): void
    {
        echo "\n--- 8. Testing PayTabs Signature, IPN Callback & Fulfillment ---" . PHP_EOL;

        $buyer = $this->createUser('buyer');
        $seller = $this->createUser('seller');
        $track = $this->createTrack($seller);
        $offer = MusicOffer::create([
            'track_id' => $track->id,
            'offer_type' => MusicOffer::OFFER_TYPE_RENT,
            'price' => 30.00,
            'currency' => 'SAR',
            'rental_duration_days' => 14,
            'is_active' => true,
        ]);

        $order = MusicOrder::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'track_id' => $track->id,
            'offer_id' => $offer->id,
            'offer_type' => 'rent',
            'subtotal' => 30.00,
            'amount' => 34.50, // 30 + 15% VAT
            'currency' => 'SAR',
            'payment_gateway' => 'paytabs',
            'payment_status' => MusicOrder::STATUS_PENDING,
        ]);

        $payTabsService = app(PayTabsPaymentService::class);
        $orderController = app(\App\Http\Controllers\Api\MusicOrderController::class);

        // Subcase A: Invalid signature rejected
        $reqBadSig = Request::create('/api/v1/music/payments/paytabs/callback', 'POST', [], [], [], [
            'HTTP_SIGNATURE' => 'invalid_forged_signature',
        ], json_encode(['cart_id' => (string) $order->id]));

        $resBadSig = $orderController->handlePayTabsCallback($reqBadSig);
        $this->assert($resBadSig->getStatusCode() === 403, "Invalid PayTabs signature rejected with HTTP 403");

        // Subcase B: Authorized Callback with Valid Signature
        $serverKey = config('paytabs.server_key') ?: get_static_option('paytabs_server_key') ?: 'TEST_KEY';
        $tranRef = 'TST-PT-' . uniqid();
        $payload = [
            'cart_id' => (string) $order->id,
            'cart_amount' => 34.50,
            'cart_currency' => 'SAR',
            'tran_ref' => $tranRef,
            'payment_result' => [
                'response_status' => 'A', // Authorized
                'response_code' => '000',
                'response_message' => 'Authorised',
            ],
            'payment_info' => [
                'payment_method' => 'card',
                'card_scheme' => 'Visa',
                'card_type' => 'Credit',
            ],
        ];

        $rawBody = json_encode($payload);
        $validSignature = hash_hmac('sha256', $rawBody, $serverKey);

        $reqGood = Request::create('/api/v1/music/payments/paytabs/callback', 'POST', [], [], [], [
            'HTTP_SIGNATURE' => $validSignature,
            'CONTENT_TYPE' => 'application/json',
        ], $rawBody);

        $resGood = $orderController->handlePayTabsCallback($reqGood);
        $this->assert($resGood->getStatusCode() === 200, "Valid PayTabs callback accepted with HTTP 200");
        $this->assert($order->fresh()->isCompleted(), "Order marked completed upon verified PayTabs callback");
        $this->assert($order->fresh()->transaction_id === $tranRef, "Transaction reference stored on order");

        // Subcase C: Repeat Callback Idempotency
        $resRepeat = $orderController->handlePayTabsCallback($reqGood);
        $this->assert($resRepeat->getStatusCode() === 200, "Repeat PayTabs callback handled idempotently (HTTP 200)");
        $this->assert(MusicEntitlement::where('order_id', $order->id)->count() === 1, "Exactly one entitlement exists");
    }

    /**
     * 9. Test Entitlement Fulfillment & Stream Authorization
     */
    private function testEntitlementIssuanceAndLicensingRules(): void
    {
        echo "\n--- 9. Testing Entitlement Issuance & Licensing Rules ---" . PHP_EOL;

        $buyer = $this->createUser('buyer');
        $seller = $this->createUser('seller');
        $track = $this->createTrack($seller);

        // Rent Offer
        $rentOffer = MusicOffer::create([
            'track_id' => $track->id,
            'offer_type' => MusicOffer::OFFER_TYPE_RENT,
            'price' => 15.00,
            'currency' => 'SAR',
            'rental_duration_days' => 5,
            'is_active' => true,
        ]);

        $orderRent = MusicOrder::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'track_id' => $track->id,
            'offer_id' => $rentOffer->id,
            'offer_type' => 'rent',
            'subtotal' => 15.00,
            'amount' => 15.00,
            'currency' => 'SAR',
            'payment_gateway' => 'wallet',
            'payment_status' => MusicOrder::STATUS_PENDING,
        ]);

        $fulfillmentService = app(MusicFulfillmentService::class);
        $res = $fulfillmentService->fulfillOrder($orderRent, 'wallet', 'WTX-TEST-1');

        /** @var MusicEntitlement $entitlement */
        $entitlement = $res['entitlement'];
        $this->assert($entitlement !== null, "Entitlement created upon fulfillment");
        $this->assert(!$entitlement->isPerpetual(), "Rental entitlement is NOT perpetual");
        $this->assert($entitlement->expires_at !== null, "Rental entitlement has non-null expires_at");
        $this->assert(!$entitlement->allow_download, "Master download is strictly false");
        $this->assert(!$entitlement->commercial_use, "Commercial use rights is strictly false");
        $this->assert(!$entitlement->remix_rights, "Remix rights is strictly false");
        $this->assert($entitlement->access_type === 'stream_only', "Access type is strictly stream_only");

        // Verify Stream Authorization grants 'full' mode
        $streamService = app(MusicStreamService::class);
        $authRes = $streamService->resolvePlaybackAccess($buyer, $track);
        $this->assert($authRes['mode'] === 'full', "Entitled rental buyer receives full streaming authorization");

        // Verify Expired Rental loses full mode
        $entitlement->update(['expires_at' => now()->subHour()]);
        $authExpired = $streamService->resolvePlaybackAccess($buyer, $track);
        $this->assert($authExpired['mode'] === 'preview', "Expired rental immediately loses full access and falls back to preview");
    }

    /**
     * 10. Test Admin Refund Workflow & Seller Ledger Reversal
     */
    private function testAdminRefundWorkflowAndSellerLedgerReversal(): void
    {
        echo "\n--- 10. Testing Admin Refund Workflow, Wallet Credit & Ledger Reversal ---" . PHP_EOL;

        $buyer = $this->createUser('buyer');
        $seller = $this->createUser('seller');
        $track = $this->createTrack($seller);
        $offer = MusicOffer::create([
            'track_id' => $track->id,
            'offer_type' => MusicOffer::OFFER_TYPE_SALE,
            'price' => 50.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        // Complete order via wallet
        $walletService = app(WalletService::class);
        $wallet = Wallet::getOrCreateForUser($buyer->id);
        $walletService->credit($buyer, 50.00, 'test_fund');

        auth('sanctum')->setUser($buyer);
        $orderController = app(\App\Http\Controllers\Api\MusicOrderController::class);
        $res = $orderController->store(Request::create('/api/v1/music/orders', 'POST', [
            'offer_id' => $offer->id,
            'payment_gateway' => 'wallet',
        ]));
        $resData = json_decode($res->getContent(), true);
        $orderId = $resData['order']['id'];

        /** @var MusicOrder $order */
        $order = MusicOrder::find($orderId);
        $this->assert($order->isCompleted(), "Order is completed");
        $this->assert((float) $wallet->fresh()->balance === 0.00, "Buyer wallet balance is 0.00 SAR after purchase");

        // Verify seller ledger entry exists
        $earningEntry = MusicSellerLedgerEntry::where('order_id', $order->id)->where('entry_type', 'earning')->first();
        $this->assert($earningEntry !== null, "Seller earnings entry exists in ledger");
        $this->assert((float) $earningEntry->net_earning === (float) $order->seller_earning, "Ledger net earning matches order snapshot");

        // Execute Admin Refund
        $admin = $this->createAdmin();
        auth('admin')->login($admin);

        $adminOrderController = app(\App\Http\Controllers\AdminMusicOrderController::class);
        $refundReq = Request::create("/admin-home/music-orders-json/{$order->id}/refund", 'POST', [
            'reason' => 'Customer requested cancellation within guarantee window',
        ]);

        $refundRes = $adminOrderController->refund($refundReq, $order->id);
        $refundResData = json_decode($refundRes->getContent(), true);

        $this->assert($refundRes->getStatusCode() === 200, "Admin refund returns HTTP 200");
        $this->assert($refundResData['order']['payment_status'] === MusicOrder::STATUS_REFUNDED, "Order status transitioned to refunded");

        // Verify Buyer Wallet Credited
        $buyerWalletAfter = (float) $wallet->fresh()->balance;
        $this->assert($buyerWalletAfter === (float) $order->amount, "Buyer wallet refunded full order amount of {$order->amount} SAR");

        // Verify Seller Ledger Reversal Entry
        $reversalEntry = MusicSellerLedgerEntry::where('order_id', $order->id)
            ->where('entry_type', MusicSellerLedgerEntry::TYPE_REFUND_REVERSAL)
            ->first();

        $this->assert($reversalEntry !== null, "Negative reversal entry created in seller financial ledger");
        $this->assert((float) $reversalEntry->net_earning === -((float) $order->seller_earning), "Reversal net earning is negative of original earning");

        // Verify Entitlement Revocation
        $entitlement = MusicEntitlement::where('order_id', $order->id)->first();
        $this->assert(!$entitlement->is_active, "Entitlement is_active set to false");
        $this->assert($entitlement->revoked_at !== null, "Entitlement revoked_at timestamp populated");

        // Verify Admin Audit Log
        $audit = AdminAuditLog::where('action', 'music_order_refund')->where('resource_id', (string) $order->id)->first();
        $this->assert($audit !== null, "AdminAuditLog recorded for music order refund");

        // Verify Duplicate Refund Rejected
        $refundReq2 = Request::create("/admin-home/music-orders-json/{$order->id}/refund", 'POST', [
            'reason' => 'Duplicate refund attempt',
        ]);
        $refundRes2 = $adminOrderController->refund($refundReq2, $order->id);
        $this->assert($refundRes2->getStatusCode() === 422, "Repeated refund rejected with HTTP 422 (ALREADY_FULLY_REFUNDED)");
    }

    /**
     * 11. Test Phase 5.1: Customer Wallet Refund Policy & Financial Integrity
     */
    private function testPhase51WalletAndGatewayRefundScenarios(): void
    {
        echo "\n--- 11. Testing Phase 5.1: Customer Wallet Refund Policy & Financial Integrity ---" . PHP_EOL;

        $admin = $this->createAdmin();
        auth('admin')->login($admin);
        $orderController = app(\App\Http\Controllers\AdminMusicOrderController::class);
        $apiOrderController = app(\App\Http\Controllers\Api\MusicOrderController::class);
        $walletService = app(WalletService::class);

        // --- SUBTEST A: Multiple Partial Wallet Refunds with Final Full Refund ---
        $buyerA = $this->createUser('buyer');
        $sellerA = $this->createUser('seller');
        $trackA = $this->createTrack($sellerA);
        $offerA = MusicOffer::create([
            'track_id' => $trackA->id,
            'offer_type' => MusicOffer::OFFER_TYPE_SALE,
            'price' => 60.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $walletA = Wallet::getOrCreateForUser($buyerA->id);
        $walletService->credit($buyerA, 60.00, 'test_fund');

        auth('sanctum')->setUser($buyerA);
        $resOrderA = $apiOrderController->store(Request::create('/api/v1/music/orders', 'POST', [
            'offer_id' => $offerA->id,
            'payment_gateway' => 'wallet',
        ]));
        $orderAId = json_decode($resOrderA->getContent(), true)['order']['id'];
        /** @var MusicOrder $orderA */
        $orderA = MusicOrder::find($orderAId);

        $this->assert($orderA->isCompleted(), "Order A is completed");
        $earningA = MusicSellerLedgerEntry::where('order_id', $orderA->id)->where('entry_type', 'earning')->first();
        $this->assert($earningA->status === MusicSellerLedgerEntry::STATUS_PAYABLE, "Seller earning entry initial status is 'payable' (not settled)");

        // Partial Refund 1: 20 SAR of 60 SAR
        $refundReqA1 = Request::create("/admin-home/music-orders-json/{$orderA->id}/refund", 'POST', [
            'reason' => 'Partial refund step 1',
            'refund_amount' => 20.00,
            'destination' => 'customer_wallet',
        ]);
        $resA1 = $orderController->refund($refundReqA1, $orderA->id);
        $dataA1 = json_decode($resA1->getContent(), true);

        $this->assert($resA1->getStatusCode() === 200, "Partial refund 1 returns HTTP 200");
        $this->assert($dataA1['order']['payment_status'] === MusicOrder::STATUS_COMPLETED, "Order status remains completed after partial refund");
        $this->assert((float) $dataA1['order']['remaining_refundable'] === 40.00, "Remaining refundable is 40.00 SAR");
        $this->assert((float) $walletA->fresh()->balance === 20.00, "Buyer wallet balance credited 20.00 SAR");
        $this->assert($dataA1['order']['entitlement_active'] === true, "Entitlement remains active after partial refund");

        // Partial Refund 2: 25 SAR of remaining 40 SAR
        $refundReqA2 = Request::create("/admin-home/music-orders-json/{$orderA->id}/refund", 'POST', [
            'reason' => 'Partial refund step 2',
            'refund_amount' => 25.00,
            'destination' => 'customer_wallet',
        ]);
        $resA2 = $orderController->refund($refundReqA2, $orderA->id);
        $dataA2 = json_decode($resA2->getContent(), true);

        $this->assert($resA2->getStatusCode() === 200, "Partial refund 2 returns HTTP 200");
        $this->assert((float) $dataA2['order']['remaining_refundable'] === 15.00, "Remaining refundable is 15.00 SAR");
        $this->assert((float) $walletA->fresh()->balance === 45.00, "Buyer wallet cumulative balance is 45.00 SAR (20 + 25)");
        $this->assert($orderA->fresh()->entitlement->is_active, "Entitlement still active after second partial refund");

        // Attempt over-refund: requesting 20 SAR when only 15 SAR remaining
        $refundReqExceed = Request::create("/admin-home/music-orders-json/{$orderA->id}/refund", 'POST', [
            'reason' => 'Excessive refund attempt',
            'refund_amount' => 20.00,
        ]);
        $resExceed = $orderController->refund($refundReqExceed, $orderA->id);
        $this->assert($resExceed->getStatusCode() === 422, "Exceeding remaining balance rejected with HTTP 422");
        $this->assert(json_decode($resExceed->getContent(), true)['error_code'] === 'REFUND_AMOUNT_EXCEEDED', "REFUND_AMOUNT_EXCEEDED error returned");
        $this->assert((float) $walletA->fresh()->balance === 45.00, "Buyer wallet balance untouched after rejected refund");

        // Final Partial Refund 3: 15 SAR (exact remaining)
        $refundReqA3 = Request::create("/admin-home/music-orders-json/{$orderA->id}/refund", 'POST', [
            'reason' => 'Final full remaining refund',
            'refund_amount' => 15.00,
        ]);
        $resA3 = $orderController->refund($refundReqA3, $orderA->id);
        $dataA3 = json_decode($resA3->getContent(), true);

        $this->assert($resA3->getStatusCode() === 200, "Final partial refund returns HTTP 200");
        $this->assert($dataA3['order']['payment_status'] === MusicOrder::STATUS_REFUNDED, "Order status transitions to refunded upon reaching 0 remaining");
        $this->assert((float) $dataA3['order']['remaining_refundable'] === 0.00, "Remaining refundable is 0.00 SAR");
        $this->assert((float) $walletA->fresh()->balance === 60.00, "Buyer wallet fully restored to 60.00 SAR");
        $this->assert(!$orderA->fresh()->entitlement->is_active, "Entitlement revoked upon final full refund");
        $this->assert($orderA->fresh()->entitlement->revoked_at !== null, "Entitlement revoked_at timestamp set");

        // --- SUBTEST B: Idempotency Key Handling ---
        $buyerB = $this->createUser('buyer');
        $sellerB = $this->createUser('seller');
        $trackB = $this->createTrack($sellerB);
        $offerB = MusicOffer::create([
            'track_id' => $trackB->id,
            'offer_type' => MusicOffer::OFFER_TYPE_SALE,
            'price' => 30.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);
        $walletB = Wallet::getOrCreateForUser($buyerB->id);
        $walletService->credit($buyerB, 30.00, 'test_fund');
        auth('sanctum')->setUser($buyerB);
        $resOrderB = $apiOrderController->store(Request::create('/api/v1/music/orders', 'POST', [
            'offer_id' => $offerB->id,
            'payment_gateway' => 'wallet',
        ]));
        $orderBId = json_decode($resOrderB->getContent(), true)['order']['id'];

        $idempKey = 'REF-IDEMP-' . uniqid();
        $refundReqIdemp1 = Request::create("/admin-home/music-orders-json/{$orderBId}/refund", 'POST', [
            'reason' => 'Idempotent refund attempt 1',
            'refund_amount' => 10.00,
            'idempotency_key' => $idempKey,
        ]);
        $resIdemp1 = $orderController->refund($refundReqIdemp1, $orderBId);
        $this->assert($resIdemp1->getStatusCode() === 200, "Initial idempotent refund succeeds with 200");
        $this->assert((float) $walletB->fresh()->balance === 10.00, "Wallet credited 10 SAR on initial refund");

        // Replay same request with same idempotency key
        $refundReqIdemp2 = Request::create("/admin-home/music-orders-json/{$orderBId}/refund", 'POST', [
            'reason' => 'Idempotent refund attempt 1',
            'refund_amount' => 10.00,
            'idempotency_key' => $idempKey,
        ]);
        $resIdemp2 = $orderController->refund($refundReqIdemp2, $orderBId);
        $dataIdemp2 = json_decode($resIdemp2->getContent(), true);

        $this->assert($resIdemp2->getStatusCode() === 200, "Replayed idempotent refund returns HTTP 200");
        $this->assert($dataIdemp2['idempotent_replay'] === true, "Response confirms idempotent_replay = true");
        $this->assert((float) $walletB->fresh()->balance === 10.00, "Wallet NOT double-credited on replayed idempotent request");

        // --- SUBTEST C: PayTabs Refund Approved via Original Payment Method ---
        $buyerC = $this->createUser('buyer');
        $sellerC = $this->createUser('seller');
        $trackC = $this->createTrack($sellerC);
        $offerC = MusicOffer::create([
            'track_id' => $trackC->id,
            'offer_type' => MusicOffer::OFFER_TYPE_SALE,
            'price' => 50.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);
        $orderC = MusicOrder::create([
            'invoice' => 'INV-PT-' . uniqid(),
            'buyer_id' => $buyerC->id,
            'seller_id' => $sellerC->id,
            'seller_name' => $sellerC->name,
            'seller_email' => $sellerC->email,
            'buyer_name' => $buyerC->name,
            'buyer_email' => $buyerC->email,
            'track_id' => $trackC->id,
            'offer_id' => $offerC->id,
            'offer_type' => 'sale',
            'subtotal' => 50.00,
            'vat_rate' => 0.00,
            'vat_amount' => 0.00,
            'amount' => 50.00,
            'currency' => 'SAR',
            'commission_rate' => 10.00,
            'commission_amount' => 5.00,
            'commission_vat_amount' => 0.00,
            'seller_earning' => 45.00,
            'tax_treatment' => 'deemed_supplier',
            'price_display_mode' => 'exclusive',
            'payment_gateway' => 'paytabs',
            'payment_status' => MusicOrder::STATUS_COMPLETED,
            'transaction_id' => 'TST2429801991823',
            'paid_at' => now(),
        ]);
        MusicEntitlement::create([
            'order_id' => $orderC->id,
            'user_id' => $buyerC->id,
            'track_id' => $trackC->id,
            'offer_id' => $offerC->id,
            'offer_type' => 'sale',
            'access_type' => 'stream_only',
            'license_type' => 'stream_only',
            'is_active' => true,
        ]);
        MusicSellerLedgerEntry::create([
            'seller_id' => $sellerC->id,
            'order_id' => $orderC->id,
            'track_id' => $trackC->id,
            'entry_type' => MusicSellerLedgerEntry::TYPE_EARNING,
            'gross_amount' => 50.00,
            'commission_deducted' => 5.00,
            'vat_deducted' => 0.00,
            'net_earning' => 45.00,
            'currency' => 'SAR',
            'status' => MusicSellerLedgerEntry::STATUS_PAYABLE,
        ]);

        // Mock PayTabs Responses dynamically based on URL and cart_description / reason
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            $data = $request->data();

            if (str_contains($url, 'payment/query')) {
                $tranRef = (string) ($data['tran_ref'] ?? '');
                if (str_contains($tranRef, 'FAIL') || str_contains($tranRef, 'DECLINE')) {
                    return Http::response([
                        'tran_ref' => $tranRef,
                        'tran_type' => 'Refund',
                        'payment_result' => [
                            'response_status' => 'D',
                            'response_code' => '100',
                            'response_message' => 'Declined by bank',
                        ],
                    ], 200);
                }

                return Http::response([
                    'tran_ref' => $tranRef ?: 'TST2429801991825',
                    'tran_type' => 'Refund',
                    'cart_amount' => '20.00',
                    'payment_result' => [
                        'response_status' => 'A',
                        'response_code' => '000',
                        'response_message' => 'Authorised',
                    ],
                ], 200);
            }

            $description = $data['cart_description'] ?? '';

            if (str_contains($description, 'Declined refund test')) {
                return Http::response([
                    'tran_ref' => 'TST2429801991825',
                    'tran_type' => 'Refund',
                    'cart_amount' => '20.00',
                    'payment_result' => [
                        'response_status' => 'D',
                        'response_code' => '100',
                        'response_message' => 'Declined by issuer',
                    ],
                ], 200);
            }

            if (str_contains($description, 'Ambiguous refund test')) {
                return Http::response(['message' => 'Internal gateway timeout'], 500);
            }

            return Http::response([
                'tran_ref' => 'TST2429801991899',
                'tran_type' => 'Refund',
                'cart_amount' => '50.00',
                'payment_result' => [
                    'response_status' => 'A',
                    'response_code' => '000',
                    'response_message' => 'Authorised',
                ],
            ], 200);
        });

        $refundReqC = Request::create("/admin-home/music-orders-json/{$orderC->id}/refund", 'POST', [
            'reason' => 'Customer requested card refund',
            'destination' => 'original_payment_method',
        ]);
        $resC = $orderController->refund($refundReqC, $orderC->id);
        $dataC = json_decode($resC->getContent(), true);

        $this->assert($resC->getStatusCode() === 200, "PayTabs original payment refund returns HTTP 200");
        $this->assert($dataC['order']['payment_status'] === MusicOrder::STATUS_REFUNDED, "Order C marked refunded");
        $this->assert($orderC->fresh()->refund_destination === 'original_payment_method', "Order refund_destination recorded as original_payment_method");
        $this->assert((float) $orderC->fresh()->refund_amount_gateway === 50.00, "refund_amount_gateway incremented to 50.00 SAR");
        $this->assert((float) $orderC->fresh()->refund_amount_wallet === 0.00, "refund_amount_wallet remains 0.00 SAR (no wallet credit)");

        // --- SUBTEST D: PayTabs Order Refunded to Customer Wallet with Consent ---
        $orderD = MusicOrder::create([
            'invoice' => 'INV-PT-' . uniqid(),
            'buyer_id' => $buyerC->id,
            'seller_id' => $sellerC->id,
            'seller_name' => $sellerC->name,
            'seller_email' => $sellerC->email,
            'buyer_name' => $buyerC->name,
            'buyer_email' => $buyerC->email,
            'track_id' => $trackC->id,
            'offer_id' => $offerC->id,
            'offer_type' => 'sale',
            'subtotal' => 40.00,
            'vat_rate' => 0.00,
            'vat_amount' => 0.00,
            'amount' => 40.00,
            'currency' => 'SAR',
            'commission_rate' => 10.00,
            'commission_amount' => 4.00,
            'commission_vat_amount' => 0.00,
            'seller_earning' => 36.00,
            'tax_treatment' => 'deemed_supplier',
            'price_display_mode' => 'exclusive',
            'payment_gateway' => 'paytabs',
            'payment_status' => MusicOrder::STATUS_COMPLETED,
            'transaction_id' => 'TST2429801991824',
            'paid_at' => now(),
        ]);
        $walletC = Wallet::getOrCreateForUser($buyerC->id);
        $walletCBalanceBefore = (float) $walletC->fresh()->balance;

        // Attempt wallet credit without consent
        $refundReqDNoConsent = Request::create("/admin-home/music-orders-json/{$orderD->id}/refund", 'POST', [
            'reason' => 'Wallet refund attempt without consent',
            'destination' => 'customer_wallet',
            'buyer_agreed_wallet_credit' => false,
        ]);
        $resDNoConsent = $orderController->refund($refundReqDNoConsent, $orderD->id);
        $this->assert($resDNoConsent->getStatusCode() === 422, "PayTabs wallet credit without consent rejected with 422");
        $this->assert(json_decode($resDNoConsent->getContent(), true)['error_code'] === 'WALLET_CREDIT_CONSENT_REQUIRED', "WALLET_CREDIT_CONSENT_REQUIRED error returned");

        // Wallet credit with explicit consent
        $refundReqDWithConsent = Request::create("/admin-home/music-orders-json/{$orderD->id}/refund", 'POST', [
            'reason' => 'Customer requested wallet credit in ticket #998',
            'destination' => 'customer_wallet',
            'buyer_agreed_wallet_credit' => true,
        ]);
        $resDWithConsent = $orderController->refund($refundReqDWithConsent, $orderD->id);
        $this->assert($resDWithConsent->getStatusCode() === 200, "PayTabs wallet credit with consent returns HTTP 200");
        $this->assert((float) $walletC->fresh()->balance === $walletCBalanceBefore + 40.00, "Buyer wallet credited with full 40.00 SAR");
        $this->assert((float) $orderD->fresh()->refund_amount_wallet === 40.00, "refund_amount_wallet recorded as 40.00 SAR");
        $this->assert((float) $orderD->fresh()->refund_amount_gateway === 0.00, "refund_amount_gateway remains 0.00 SAR");

        // --- SUBTEST E: PayTabs Ambiguous / Declined Outcomes ---
        $orderE = MusicOrder::create([
            'invoice' => 'INV-PT-' . uniqid(),
            'buyer_id' => $buyerC->id,
            'seller_id' => $sellerC->id,
            'seller_name' => $sellerC->name,
            'seller_email' => $sellerC->email,
            'buyer_name' => $buyerC->name,
            'buyer_email' => $buyerC->email,
            'track_id' => $trackC->id,
            'offer_id' => $offerC->id,
            'offer_type' => 'sale',
            'subtotal' => 20.00,
            'vat_rate' => 0.00,
            'vat_amount' => 0.00,
            'amount' => 20.00,
            'currency' => 'SAR',
            'commission_rate' => 10.00,
            'commission_amount' => 2.00,
            'commission_vat_amount' => 0.00,
            'seller_earning' => 18.00,
            'tax_treatment' => 'deemed_supplier',
            'price_display_mode' => 'exclusive',
            'payment_gateway' => 'paytabs',
            'payment_status' => MusicOrder::STATUS_COMPLETED,
            'transaction_id' => 'TST2429801991825',
            'paid_at' => now(),
        ]);

        // Execute PayTabs Declined Refund
        $refundReqEDecline = Request::create("/admin-home/music-orders-json/{$orderE->id}/refund", 'POST', [
            'reason' => 'Declined refund test',
            'destination' => 'original_payment_method',
        ]);
        $resEDecline = $orderController->refund($refundReqEDecline, $orderE->id);
        $resEDeclineData = json_decode($resEDecline->getContent(), true);
        $this->assert($resEDecline->getStatusCode() === 422, "Declined gateway refund returns HTTP 422");
        $this->assert(($resEDeclineData['error_code'] ?? null) === 'GATEWAY_REFUND_DECLINED', "GATEWAY_REFUND_DECLINED error code returned");
        $this->assert($orderE->fresh()->isCompleted(), "Order remains completed after declined refund attempt");
        $this->assert(MusicRefundAttempt::where('order_id', $orderE->id)->where('status', MusicRefundAttempt::STATUS_DECLINED)->exists(), "Declined attempt recorded in database");
        $this->assert(!$orderE->fresh()->hasUnresolvedRefundAttempt(), "Order has no unresolved attempts after decline");

        // Execute PayTabs Ambiguous Refund (Provider timeout / 500 error)
        MusicEntitlement::create([
            'order_id' => $orderE->id,
            'user_id' => $buyerC->id,
            'track_id' => $trackC->id,
            'offer_id' => $offerC->id,
            'offer_type' => 'sale',
            'access_type' => 'stream_only',
            'license_type' => 'stream_only',
            'is_active' => true,
        ]);
        MusicSellerLedgerEntry::create([
            'seller_id' => $sellerC->id,
            'order_id' => $orderE->id,
            'track_id' => $trackC->id,
            'entry_type' => MusicSellerLedgerEntry::TYPE_EARNING,
            'gross_amount' => 20.00,
            'commission_deducted' => 2.00,
            'vat_deducted' => 0.00,
            'net_earning' => 18.00,
            'currency' => 'SAR',
            'status' => MusicSellerLedgerEntry::STATUS_PAYABLE,
        ]);

        $refundReqEAmbiguous = Request::create("/admin-home/music-orders-json/{$orderE->id}/refund", 'POST', [
            'reason' => 'Ambiguous refund test',
            'destination' => 'original_payment_method',
        ]);
        $resEAmbiguous = $orderController->refund($refundReqEAmbiguous, $orderE->id);
        $this->assert($resEAmbiguous->getStatusCode() === 502, "Ambiguous gateway response returns HTTP 502");
        $this->assert(json_decode($resEAmbiguous->getContent(), true)['error_code'] === 'GATEWAY_REFUND_AMBIGUOUS', "GATEWAY_REFUND_AMBIGUOUS error code returned");
        $this->assert($orderE->fresh()->isCompleted(), "Order remains completed (recoverable) after ambiguous gateway response");
        $this->assert((float) $orderE->fresh()->refund_amount === 0.00, "refund_amount not incremented on ambiguous gateway response");

        // Durable Attempt & Reservation Verification
        $pendingAttemptE = MusicRefundAttempt::where('order_id', $orderE->id)
            ->where('status', MusicRefundAttempt::STATUS_PENDING_VERIFICATION)
            ->first();
        $this->assert($pendingAttemptE !== null, "Durable refund attempt persisted in pending_verification status");
        $this->assert($orderE->fresh()->hasUnresolvedRefundAttempt(), "Order hasUnresolvedRefundAttempt() reports true");
        $this->assert((float) $orderE->fresh()->getPendingRefundReservationAmount() === 20.00, "getPendingRefundReservationAmount() reflects 20.00 SAR reservation");
        $this->assert((float) $orderE->fresh()->getEffectiveRemainingRefundableAmount() === 0.00, "getEffectiveRemainingRefundableAmount() clamped to 0.00 SAR");
        $this->assert((bool) $orderE->fresh()->entitlement->is_active === true, "Entitlement remains active while attempt is unresolved");

        // Retrying while first attempt is unresolved is STRICTLY BLOCKED
        $refundReqERetry = Request::create("/admin-home/music-orders-json/{$orderE->id}/refund", 'POST', [
            'reason' => 'Duplicate refund retry while pending',
            'destination' => 'original_payment_method',
        ]);
        $resERetry = $orderController->refund($refundReqERetry, $orderE->id);
        $this->assert($resERetry->getStatusCode() === 422, "Retry while pending is rejected with HTTP 422");
        $this->assert(json_decode($resERetry->getContent(), true)['error_code'] === 'REFUND_PENDING_RECONCILIATION', "REFUND_PENDING_RECONCILIATION error returned");
        $this->assert((float) $orderE->fresh()->refund_amount === 0.00, "Order refund_amount remains 0.00 SAR after rejected retry");

        // Reconciliation Later Confirms Provider Succeeded
        $reconcileReqE = Request::create("/admin-home/music-orders-json/refund-attempts/{$pendingAttemptE->id}/reconcile", 'POST', [
            'provider_ref' => 'TST2429801991825',
            'notes' => 'Confirmed authorized via PayTabs merchant portal',
        ]);
        $resReconcileE = $orderController->reconcileAttempt($reconcileReqE, $pendingAttemptE->id);
        $this->assert($resReconcileE->getStatusCode() === 200, "Reconciliation confirming success returns HTTP 200");
        $this->assert($pendingAttemptE->fresh()->isCompleted(), "Refund attempt status transitioned to completed");
        $this->assert($orderE->fresh()->payment_status === MusicOrder::STATUS_REFUNDED, "Order marked refunded upon confirmed reconciliation");
        $this->assert((float) $orderE->fresh()->refund_amount_gateway === 20.00, "refund_amount_gateway updated to 20.00 SAR");
        $this->assert((float) $orderE->fresh()->getRemainingRefundableAmount() === 0.00, "Remaining refundable balance is 0.00 SAR");
        $this->assert((bool) $orderE->fresh()->entitlement->is_active === false, "Entitlement revoked upon full confirmed reconciliation");
        $reversalLedgerE = MusicSellerLedgerEntry::where('order_id', $orderE->id)
            ->where('entry_type', MusicSellerLedgerEntry::TYPE_REFUND_REVERSAL)
            ->first();
        $this->assert($reversalLedgerE !== null, "Seller ledger reversal entry created upon reconciliation");
        $this->assert((float) $reversalLedgerE->net_earning === -18.00, "Reversal net earning matches seller attributable earning (-18.00 SAR)");

        // Repeated reconciliation request handled idempotently
        $resReconcileRepeat = $orderController->reconcileAttempt($reconcileReqE, $pendingAttemptE->id);
        $this->assert($resReconcileRepeat->getStatusCode() === 200, "Repeated reconciliation returns HTTP 200");
        $this->assert(json_decode($resReconcileRepeat->getContent(), true)['idempotent_replay'] === true, "idempotent_replay = true on repeat reconciliation");

        // Reconciliation Confirms Provider Failure (Reservation Released, Retry Permitted)
        $orderE2 = MusicOrder::create([
            'invoice' => 'INV-PT-' . uniqid(),
            'buyer_id' => $buyerC->id,
            'seller_id' => $sellerC->id,
            'seller_name' => $sellerC->name,
            'seller_email' => $sellerC->email,
            'buyer_name' => $buyerC->name,
            'buyer_email' => $buyerC->email,
            'track_id' => $trackC->id,
            'offer_id' => $offerC->id,
            'offer_type' => 'sale',
            'subtotal' => 30.00,
            'vat_rate' => 0.00,
            'vat_amount' => 0.00,
            'amount' => 30.00,
            'currency' => 'SAR',
            'commission_rate' => 10.00,
            'commission_amount' => 3.00,
            'commission_vat_amount' => 0.00,
            'seller_earning' => 27.00,
            'tax_treatment' => 'deemed_supplier',
            'price_display_mode' => 'exclusive',
            'payment_gateway' => 'paytabs',
            'payment_status' => MusicOrder::STATUS_COMPLETED,
            'transaction_id' => 'TST2429801991830',
            'paid_at' => now(),
        ]);
        MusicEntitlement::create([
            'order_id' => $orderE2->id,
            'user_id' => $buyerC->id,
            'track_id' => $trackC->id,
            'offer_id' => $offerC->id,
            'offer_type' => 'sale',
            'access_type' => 'stream_only',
            'license_type' => 'stream_only',
            'is_active' => true,
        ]);

        $refundReqE2Ambiguous = Request::create("/admin-home/music-orders-json/{$orderE2->id}/refund", 'POST', [
            'reason' => 'Ambiguous refund test for failure reconciliation',
            'destination' => 'original_payment_method',
        ]);
        $resE2Ambiguous = $orderController->refund($refundReqE2Ambiguous, $orderE2->id);
        $this->assert($resE2Ambiguous->getStatusCode() === 502, "Order E2 returns HTTP 502 on ambiguous call");
        $pendingAttemptE2 = MusicRefundAttempt::where('order_id', $orderE2->id)
            ->where('status', MusicRefundAttempt::STATUS_PENDING_VERIFICATION)
            ->first();
        $this->assert($pendingAttemptE2 !== null, "Attempt E2 is pending_verification");
        $this->assert($orderE2->fresh()->hasUnresolvedRefundAttempt(), "Order E2 has unresolved attempt");

        // Reconcile confirming failure (e.g. tran_ref containing FAIL_ triggers provider failure)
        $reconcileReqE2 = Request::create("/admin-home/music-orders-json/refund-attempts/{$pendingAttemptE2->id}/reconcile", 'POST', [
            'provider_ref' => 'FAIL_TRAN_REF_999',
            'notes' => 'PayTabs query confirmed refund failed / declined',
        ]);
        $resReconcileE2 = $orderController->reconcileAttempt($reconcileReqE2, $pendingAttemptE2->id);
        $this->assert($resReconcileE2->getStatusCode() === 200, "Reconciliation confirming failure returns HTTP 200");
        $this->assert($pendingAttemptE2->fresh()->isFailed(), "Attempt E2 status transitioned to failed");
        $this->assert(!$orderE2->fresh()->hasUnresolvedRefundAttempt(), "Order E2 hasUnresolvedRefundAttempt() is now false (reservation released)");
        $this->assert((float) $orderE2->fresh()->getEffectiveRemainingRefundableAmount() === 30.00, "Order E2 effective remaining restored to 30.00 SAR");
        $this->assert((bool) $orderE2->fresh()->entitlement->is_active === true, "Order E2 entitlement remains active after confirmed failure");

        // Now retrying refund for Order E2 SUCCEEDS (e.g. credit to wallet with consent)
        $walletCBalanceBeforeE2 = (float) $walletC->fresh()->balance;
        $refundReqE2Retry = Request::create("/admin-home/music-orders-json/{$orderE2->id}/refund", 'POST', [
            'reason' => 'Retry refund into customer wallet with consent',
            'destination' => 'customer_wallet',
            'buyer_agreed_wallet_credit' => true,
        ]);
        $resE2Retry = $orderController->refund($refundReqE2Retry, $orderE2->id);
        $this->assert($resE2Retry->getStatusCode() === 200, "Retry refund after failed reconciliation succeeds with HTTP 200");
        $this->assert((float) $walletC->fresh()->balance === $walletCBalanceBeforeE2 + 30.00, "Customer wallet credited with 30.00 SAR");
        $this->assert($orderE2->fresh()->payment_status === MusicOrder::STATUS_REFUNDED, "Order E2 marked refunded");

        // Concurrent Reservation Exhaustion Prevention
        $orderE3 = MusicOrder::create([
            'invoice' => 'INV-PT-' . uniqid(),
            'buyer_id' => $buyerC->id,
            'seller_id' => $sellerC->id,
            'seller_name' => $sellerC->name,
            'seller_email' => $sellerC->email,
            'buyer_name' => $buyerC->name,
            'buyer_email' => $buyerC->email,
            'track_id' => $trackC->id,
            'offer_id' => $offerC->id,
            'offer_type' => 'sale',
            'subtotal' => 50.00,
            'vat_rate' => 0.00,
            'vat_amount' => 0.00,
            'amount' => 50.00,
            'currency' => 'SAR',
            'commission_rate' => 10.00,
            'commission_amount' => 5.00,
            'commission_vat_amount' => 0.00,
            'seller_earning' => 45.00,
            'tax_treatment' => 'deemed_supplier',
            'price_display_mode' => 'exclusive',
            'payment_gateway' => 'paytabs',
            'payment_status' => MusicOrder::STATUS_COMPLETED,
            'transaction_id' => 'TST2429801991840',
            'paid_at' => now(),
        ]);
        // Attempt 1: Partial refund 30.00 SAR with ambiguous timeout
        $refundReqE3Part1 = Request::create("/admin-home/music-orders-json/{$orderE3->id}/refund", 'POST', [
            'reason' => 'Ambiguous refund test partial reservation',
            'refund_amount' => 30.00,
            'destination' => 'original_payment_method',
        ]);
        $resE3Part1 = $orderController->refund($refundReqE3Part1, $orderE3->id);
        $this->assert($resE3Part1->getStatusCode() === 502, "Part 1 returns HTTP 502");
        $this->assert((float) $orderE3->fresh()->getPendingRefundReservationAmount() === 30.00, "30.00 SAR is reserved in pending status");
        $this->assert((float) $orderE3->fresh()->getEffectiveRemainingRefundableAmount() === 20.00, "Effective remaining is 20.00 SAR (50 - 30)");

        // Attempt 2: Another request while attempt 1 is unresolved is blocked
        $refundReqE3Part2 = Request::create("/admin-home/music-orders-json/{$orderE3->id}/refund", 'POST', [
            'reason' => 'Second attempt while first is pending',
            'refund_amount' => 30.00,
            'destination' => 'original_payment_method',
        ]);
        $resE3Part2 = $orderController->refund($refundReqE3Part2, $orderE3->id);
        $this->assert($resE3Part2->getStatusCode() === 422, "Second attempt while first is pending is blocked with HTTP 422");
        $this->assert(json_decode($resE3Part2->getContent(), true)['error_code'] === 'REFUND_PENDING_RECONCILIATION', "REFUND_PENDING_RECONCILIATION error returned");

        // --- SUBTEST F: Prevention of Seller-Payable Over-Reversal ---
        $sellerF = $this->createUser('seller');
        $buyerF = $this->createUser('buyer');
        $trackF = $this->createTrack($sellerF);
        $offerF = MusicOffer::create([
            'track_id' => $trackF->id,
            'offer_type' => MusicOffer::OFFER_TYPE_SALE,
            'price' => 100.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);
        $orderF = MusicOrder::create([
            'invoice' => 'INV-OVERREV-' . uniqid(),
            'buyer_id' => $buyerF->id,
            'seller_id' => $sellerF->id,
            'seller_name' => $sellerF->name,
            'seller_email' => $sellerF->email,
            'buyer_name' => $buyerF->name,
            'buyer_email' => $buyerF->email,
            'track_id' => $trackF->id,
            'offer_id' => $offerF->id,
            'offer_type' => 'sale',
            'subtotal' => 100.00,
            'vat_rate' => 0.00,
            'vat_amount' => 0.00,
            'amount' => 100.00,
            'currency' => 'SAR',
            'commission_rate' => 15.00,
            'commission_amount' => 15.00,
            'commission_vat_amount' => 0.00,
            'seller_earning' => 85.00,
            'tax_treatment' => 'deemed_supplier',
            'price_display_mode' => 'exclusive',
            'payment_gateway' => 'wallet',
            'payment_status' => MusicOrder::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);
        MusicSellerLedgerEntry::create([
            'seller_id' => $sellerF->id,
            'order_id' => $orderF->id,
            'track_id' => $trackF->id,
            'entry_type' => MusicSellerLedgerEntry::TYPE_EARNING,
            'gross_amount' => 100.00,
            'commission_deducted' => 15.00,
            'vat_deducted' => 0.00,
            'net_earning' => 85.00,
            'currency' => 'SAR',
            'status' => MusicSellerLedgerEntry::STATUS_PAYABLE,
        ]);
        MusicSellerLedgerEntry::create([
            'seller_id' => $sellerF->id,
            'order_id' => $orderF->id,
            'track_id' => $trackF->id,
            'entry_type' => MusicSellerLedgerEntry::TYPE_REFUND_REVERSAL,
            'gross_amount' => -90.00,
            'commission_deducted' => -10.00,
            'vat_deducted' => 0.00,
            'net_earning' => -80.00,
            'currency' => 'SAR',
            'status' => MusicSellerLedgerEntry::STATUS_REVERSED,
        ]);
        $walletF = Wallet::getOrCreateForUser($buyerF->id);
        $refundReqF = Request::create("/admin-home/music-orders-json/{$orderF->id}/refund", 'POST', [
            'reason' => 'Test seller over-reversal prevention',
            'refund_amount' => 10.00,
        ]);
        $resF = $orderController->refund($refundReqF, $orderF->id);
        $this->assert($resF->getStatusCode() === 200, "Refund with over-reversal protection returns HTTP 200");

        $latestReversalF = MusicSellerLedgerEntry::where('order_id', $orderF->id)
            ->where('entry_type', MusicSellerLedgerEntry::TYPE_REFUND_REVERSAL)
            ->latest('id')
            ->first();
        $this->assert((float) $latestReversalF->net_earning === -5.00, "Seller reversal strictly clamped to remaining 5.00 SAR (no over-reversal)");
    }
}

// Execute Runner
$runner = new Phase5TestRunner();
$runner->run();
