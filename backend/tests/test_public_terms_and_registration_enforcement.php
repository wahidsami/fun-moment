<?php

/**
 * FUN MOMENT — PUBLIC TERMS API & REGISTRATION ENFORCEMENT TEST SUITE
 *
 * Runs strictly against an isolated in-memory SQLite database (:memory:).
 * Does NOT touch or modify PostgreSQL, MySQL, or any persistent database.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Category;
use App\Country;
use App\Http\Controllers\Api\LegalTermsApiController;
use App\Http\Controllers\Api\UserController;
use App\LegalTerm;
use App\LegalTermVersion;
use App\ProviderCategory;
use App\SellerVerify;
use App\User;
use App\UserTermsAcceptance;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

// 1. Configure isolated in-memory SQLite connection
Config::set('database.connections.sqlite_test_enforcement', [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
]);
Config::set('database.default', 'sqlite_test_enforcement');
DB::purge('sqlite_test_enforcement');
DB::reconnect('sqlite_test_enforcement');

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] $testName" . ($detail ? " ($detail)" : '') . PHP_EOL;
    } else {
        $failed++;
        echo " [FAIL] $testName" . ($detail ? " - $detail" : '') . PHP_EOL;
    }
}

echo "==================================================================" . PHP_EOL;
echo " PHASE 2C: PUBLIC TERMS API & REGISTRATION ENFORCEMENT TEST SUITE" . PHP_EOL;
echo " Connection: SQLite (:memory:) — Zero Persistent Footprint" . PHP_EOL;
echo "==================================================================" . PHP_EOL . PHP_EOL;

try {
    // ------------------------------------------------------------------------
    // SETUP: Create schema required for user registration and terms
    // ------------------------------------------------------------------------
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('username')->unique();
        $table->string('phone')->nullable();
        $table->string('password');
        $table->string('service_city')->nullable();
        $table->string('state')->nullable();
        $table->string('service_area')->nullable();
        $table->string('country_code')->nullable();
        $table->unsignedBigInteger('country_id')->nullable();
        $table->integer('user_type')->default(1);
        $table->integer('seller_type')->default(0);
        $table->integer('user_status')->default(1);
        $table->integer('terms_condition')->default(1);
        $table->text('address')->nullable();
        $table->text('seller_address')->nullable();
        $table->string('post_code')->nullable();
        $table->string('business_registration')->nullable();
        $table->string('google_id')->nullable();
        $table->string('facebook_id')->nullable();
        $table->string('apple_id')->nullable();
        $table->integer('email_verified')->default(0);
        $table->timestamps();
    });

    Schema::create('admins', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('username')->unique();
        $table->string('email');
        $table->string('password');
        $table->string('role')->default('super_admin');
        $table->timestamps();
    });

    Schema::create('countries', function (Blueprint $table) {
        $table->id();
        $table->string('country');
        $table->integer('status')->default(1);
        $table->timestamps();
    });

    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->integer('status')->default(1);
        $table->timestamps();
    });

    Schema::create('provider_categories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('provider_id');
        $table->unsignedBigInteger('category_id');
        $table->timestamps();
    });

    Schema::create('seller_verifies', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('seller_id');
        $table->integer('status')->default(0);
        $table->string('national_id_number')->nullable();
        $table->string('national_id_document')->nullable();
        $table->string('license_number')->nullable();
        $table->string('license_document')->nullable();
        $table->boolean('is_band_or_group')->default(false);
        $table->string('band_name')->nullable();
        $table->integer('band_members_count')->nullable();
        $table->string('company_name')->nullable();
        $table->string('cr_number')->nullable();
        $table->string('cr_document')->nullable();
        $table->string('contact_person_name')->nullable();
        $table->string('contact_person_email')->nullable();
        $table->string('contact_person_phone')->nullable();
        $table->timestamps();
    });

    Schema::create('personal_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->morphs('tokenable');
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    Schema::create('accountdeactives', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->integer('status')->default(0);
        $table->timestamps();
    });

    // Run Legal Terms migrations
    $m1 = require __DIR__ . '/../database/migrations/2026_10_14_000001_create_legal_terms_table.php';
    $m2 = require __DIR__ . '/../database/migrations/2026_10_14_000002_create_legal_term_versions_table.php';
    $m3 = require __DIR__ . '/../database/migrations/2026_10_14_000003_create_user_terms_acceptances_table.php';

    $m1->up();
    $m2->up();
    $m3->up();

    // Seed test master data
    $country = Country::create(['country' => 'Saudi Arabia', 'status' => 1]);
    $cat1 = Category::create(['name' => 'Music & Entertainment', 'status' => 1]);
    $cat2 = Category::create(['name' => 'Photography', 'status' => 1]);

    $publicController = new LegalTermsApiController();
    $userController = new UserController();

    // ------------------------------------------------------------------------
    // T1: Missing published terms returns 404 unavailable
    // ------------------------------------------------------------------------
    $reqT1 = Request::create('/api/v1/terms/current', 'GET', ['role' => 'buyer', 'lang' => 'ar']);
    $resT1 = $publicController->current($reqT1);
    assertTest(
        $resT1->status() === 404 && $resT1->getData(true)['status'] === 'unavailable',
        "T1: Requesting unpopulated terms returns 404 status unavailable",
        "Status: " . $resT1->status()
    );

    // ------------------------------------------------------------------------
    // T2: Invalid role/language parameters rejected with HTTP 422
    // ------------------------------------------------------------------------
    $reqT2 = Request::create('/api/v1/terms/current', 'GET', ['role' => 'superadmin', 'lang' => 'fr']);
    $resT2 = $publicController->current($reqT2);
    assertTest(
        $resT2->status() === 422,
        "T2: Invalid role or language returns HTTP 422 validation error",
        "Status: " . $resT2->status()
    );

    // ------------------------------------------------------------------------
    // T3: Publish test terms for buyer & seller in both languages
    // ------------------------------------------------------------------------
    $buyerArDoc = LegalTerm::findByRoleAndLanguage('buyer', 'ar');
    $buyerEnDoc = LegalTerm::findByRoleAndLanguage('buyer', 'en');
    $sellerArDoc = LegalTerm::findByRoleAndLanguage('seller', 'ar');
    $sellerEnDoc = LegalTerm::findByRoleAndLanguage('seller', 'en');

    $contentBuyerAr = "<h3>شروط المشتري</h3><p>محتوى شروط المشتري بالعربية v1</p>";
    $vBuyerAr = LegalTermVersion::create([
        'legal_term_id' => $buyerArDoc->id,
        'version_number' => 1,
        'title' => 'شروط وأحكام المشتري',
        'content' => $contentBuyerAr,
        'content_hash' => hash('sha256', $contentBuyerAr),
        'published_at' => now(),
    ]);
    $buyerArDoc->update(['current_version_id' => $vBuyerAr->id, 'draft_content' => 'UNPUBLISHED DRAFT TEXT']);

    $contentBuyerEn = "<h3>Buyer Terms</h3><p>Buyer terms in English v1</p>";
    $vBuyerEn = LegalTermVersion::create([
        'legal_term_id' => $buyerEnDoc->id,
        'version_number' => 1,
        'title' => 'Buyer Terms & Conditions',
        'content' => $contentBuyerEn,
        'content_hash' => hash('sha256', $contentBuyerEn),
        'published_at' => now(),
    ]);
    $buyerEnDoc->update(['current_version_id' => $vBuyerEn->id]);

    $contentSellerAr = "<h3>شروط مقدم الخدمة</h3><p>شروط مقدم الخدمة بالعربية v1</p>";
    $vSellerAr = LegalTermVersion::create([
        'legal_term_id' => $sellerArDoc->id,
        'version_number' => 1,
        'title' => 'شروط وأحكام مقدم الخدمة',
        'content' => $contentSellerAr,
        'content_hash' => hash('sha256', $contentSellerAr),
        'published_at' => now(),
    ]);
    $sellerArDoc->update(['current_version_id' => $vSellerAr->id]);

    $contentSellerEn = "<h3>Seller Terms</h3><p>Seller terms in English v1</p>";
    $vSellerEn = LegalTermVersion::create([
        'legal_term_id' => $sellerEnDoc->id,
        'version_number' => 1,
        'title' => 'Seller Terms & Conditions',
        'content' => $contentSellerEn,
        'content_hash' => hash('sha256', $contentSellerEn),
        'published_at' => now(),
    ]);
    $sellerEnDoc->update(['current_version_id' => $vSellerEn->id]);

    // Test T3: Draft content is strictly hidden; only published content is returned
    $reqT3 = Request::create('/api/v1/terms/current', 'GET', ['role' => 'buyer', 'lang' => 'ar']);
    $resT3 = $publicController->current($reqT3);
    $dataT3 = $resT3->getData(true);

    $noDraftExposed = !str_contains($dataT3['data']['content'], 'UNPUBLISHED DRAFT TEXT')
        && str_contains($dataT3['data']['content'], 'محتوى شروط المشتري بالعربية v1');

    assertTest(
        $resT3->status() === 200 && $noDraftExposed,
        "T3: Public endpoint returns only immutable published version, hiding draft content",
        "Version ID: {$dataT3['data']['version_id']}, Version: {$dataT3['data']['version_number']}"
    );

    // ------------------------------------------------------------------------
    // T4: Public current-terms succeeds for all 4 role/language combinations
    // ------------------------------------------------------------------------
    $resBuyerEn = $publicController->current(Request::create('/api/v1/terms/current', 'GET', ['role' => 'buyer', 'lang' => 'en']));
    $resSellerAr = $publicController->current(Request::create('/api/v1/terms/current', 'GET', ['role' => 'seller', 'lang' => 'ar']));
    $resSellerEn = $publicController->current(Request::create('/api/v1/terms/current', 'GET', ['role' => 'seller', 'lang' => 'en']));

    assertTest(
        $resBuyerEn->status() === 200 && $resSellerAr->status() === 200 && $resSellerEn->status() === 200,
        "T4: Public endpoint succeeds for buyer/en, seller/ar, and seller/en"
    );

    // ------------------------------------------------------------------------
    // T5: Buyer registration without terms_version_id fails
    // ------------------------------------------------------------------------
    $failedNoTerms = false;
    try {
        $reqBuyerNoTerms = Request::create('/api/v1/register', 'POST', [
            'name' => 'Buyer Test User',
            'email' => 'buyer_noterms@test.com',
            'username' => 'buyer_noterms',
            'phone' => '+966500000001',
            'password' => 'secret12345',
            'service_city' => 'Riyadh',
            'service_area' => 'Olaya',
            'country_id' => $country->id,
            'terms_conditions' => 1,
            // terms_version_id is missing!
            'terms_language' => 'en',
        ]);
        $userController->register($reqBuyerNoTerms);
    } catch (ValidationException $e) {
        $failedNoTerms = isset($e->errors()['terms_version_id']);
    }

    assertTest(
        $failedNoTerms,
        "T5: Buyer registration without terms_version_id fails with validation error"
    );

    // ------------------------------------------------------------------------
    // T6: Buyer registration with invalid terms_language fails
    // ------------------------------------------------------------------------
    $failedInvalidLang = false;
    try {
        $reqBuyerBadLang = Request::create('/api/v1/register', 'POST', [
            'name' => 'Buyer Test User',
            'email' => 'buyer_badlang@test.com',
            'username' => 'buyer_badlang',
            'phone' => '+966500000002',
            'password' => 'secret12345',
            'service_city' => 'Riyadh',
            'service_area' => 'Olaya',
            'country_id' => $country->id,
            'terms_conditions' => 1,
            'terms_version_id' => $vBuyerEn->id,
            'terms_language' => 'fr', // invalid language!
        ]);
        $userController->register($reqBuyerBadLang);
    } catch (ValidationException $e) {
        $failedInvalidLang = isset($e->errors()['terms_language']);
    }

    assertTest(
        $failedInvalidLang,
        "T6: Buyer registration with invalid terms_language fails with validation error"
    );

    // ------------------------------------------------------------------------
    // T7: Cross-role terms rejection (Buyer trying to submit Seller terms version)
    // ------------------------------------------------------------------------
    $failedCrossRole = false;
    try {
        $reqCrossRole = Request::create('/api/v1/register', 'POST', [
            'name' => 'Buyer Cross Role',
            'email' => 'buyer_cross@test.com',
            'username' => 'buyer_cross',
            'phone' => '+966500000003',
            'password' => 'secret12345',
            'service_city' => 'Riyadh',
            'service_area' => 'Olaya',
            'country_id' => $country->id,
            'terms_conditions' => 1,
            'terms_version_id' => $vSellerEn->id, // Seller terms version submitted for Buyer registration!
            'terms_language' => 'en',
        ]);
        $userController->register($reqCrossRole);
    } catch (ValidationException $e) {
        $failedCrossRole = isset($e->errors()['terms_version_id']);
    }

    assertTest(
        $failedCrossRole,
        "T7: Cross-role terms rejection: Buyer cannot register using Seller terms version ID"
    );

    // ------------------------------------------------------------------------
    // T8: Cross-language terms rejection (English version ID with Arabic language)
    // ------------------------------------------------------------------------
    $failedCrossLang = false;
    try {
        $reqCrossLang = Request::create('/api/v1/register', 'POST', [
            'name' => 'Buyer Cross Lang',
            'email' => 'buyer_crosslang@test.com',
            'username' => 'buyer_crosslang',
            'phone' => '+966500000004',
            'password' => 'secret12345',
            'service_city' => 'Riyadh',
            'service_area' => 'Olaya',
            'country_id' => $country->id,
            'terms_conditions' => 1,
            'terms_version_id' => $vBuyerEn->id, // English version ID with Arabic language parameter!
            'terms_language' => 'ar',
        ]);
        $userController->register($reqCrossLang);
    } catch (ValidationException $e) {
        $failedCrossLang = isset($e->errors()['terms_version_id']);
    }

    assertTest(
        $failedCrossLang,
        "T8: Cross-language terms rejection: English version ID rejected when Arabic is declared"
    );

    // ------------------------------------------------------------------------
    // T9: Stale version ID is rejected after a newer version is published
    // ------------------------------------------------------------------------
    $contentBuyerEnV2 = "<h3>Buyer Terms v2</h3><p>Updated Buyer terms in English</p>";
    $vBuyerEnV2 = LegalTermVersion::create([
        'legal_term_id' => $buyerEnDoc->id,
        'version_number' => 2,
        'title' => 'Buyer Terms & Conditions v2',
        'content' => $contentBuyerEnV2,
        'content_hash' => hash('sha256', $contentBuyerEnV2),
        'published_at' => now(),
    ]);
    $buyerEnDoc->update(['current_version_id' => $vBuyerEnV2->id]);

    $failedStale = false;
    try {
        $reqStale = Request::create('/api/v1/register', 'POST', [
            'name' => 'Buyer Stale Version',
            'email' => 'buyer_stale@test.com',
            'username' => 'buyer_stale',
            'phone' => '+966500000005',
            'password' => 'secret12345',
            'service_city' => 'Riyadh',
            'service_area' => 'Olaya',
            'country_id' => $country->id,
            'terms_conditions' => 1,
            'terms_version_id' => $vBuyerEn->id, // Stale version 1 submitted after version 2 published!
            'terms_language' => 'en',
        ]);
        $userController->register($reqStale);
    } catch (ValidationException $e) {
        $failedStale = isset($e->errors()['terms_version_id']);
    }

    assertTest(
        $failedStale,
        "T9: Stale version ID rejection: Version 1 rejected after Version 2 is published"
    );

    // ------------------------------------------------------------------------
    // T10: Fabricated non-existent version ID is rejected
    // ------------------------------------------------------------------------
    $failedFabricated = false;
    try {
        $reqFab = Request::create('/api/v1/register', 'POST', [
            'name' => 'Buyer Fabricated',
            'email' => 'buyer_fab@test.com',
            'username' => 'buyer_fab',
            'phone' => '+966500000006',
            'password' => 'secret12345',
            'service_city' => 'Riyadh',
            'service_area' => 'Olaya',
            'country_id' => $country->id,
            'terms_conditions' => 1,
            'terms_version_id' => 99999, // Fabricated ID!
            'terms_language' => 'en',
        ]);
        $userController->register($reqFab);
    } catch (ValidationException $e) {
        $failedFabricated = isset($e->errors()['terms_version_id']);
    }

    assertTest(
        $failedFabricated,
        "T10: Fabricated version ID rejected with validation error"
    );

    // ------------------------------------------------------------------------
    // T11: Successful buyer registration creates user and exactly 1 acceptance record
    // ------------------------------------------------------------------------
    $reqSuccessBuyer = Request::create('/api/v1/register', 'POST', [
        'name' => 'Authorized Buyer',
        'email' => 'auth_buyer@test.com',
        'username' => 'auth_buyer_1',
        'phone' => '+966500000007',
        'password' => 'secret12345',
        'service_city' => 'Riyadh',
        'service_area' => 'Olaya',
        'country_id' => $country->id,
        'terms_conditions' => 1,
        'terms_version_id' => $vBuyerEnV2->id,
        'terms_language' => 'en',
    ]);
    $resBuyer = $userController->register($reqSuccessBuyer);
    $buyerUser = User::where('email', 'auth_buyer@test.com')->first();
    $buyerAcceptance = UserTermsAcceptance::where('user_id', $buyerUser?->id)->get();

    $buyerValid = $buyerUser && $buyerAcceptance->count() === 1
        && $buyerAcceptance->first()->legal_term_version_id === $vBuyerEnV2->id
        && $buyerAcceptance->first()->version_number === 2
        && $buyerAcceptance->first()->content_hash === $vBuyerEnV2->content_hash
        && $buyerAcceptance->first()->role === 'buyer'
        && $buyerAcceptance->first()->language === 'en';

    assertTest(
        $buyerValid,
        "T11: Successful buyer registration creates user and exactly 1 acceptance record atomically",
        "User ID: {$buyerUser?->id}, Acceptance records: {$buyerAcceptance->count()}, Version: 2"
    );

    // ------------------------------------------------------------------------
    // T12: Failed registration creates NO orphan acceptance records
    // ------------------------------------------------------------------------
    $acceptancesBefore = UserTermsAcceptance::count();
    try {
        $reqFailBuyer = Request::create('/api/v1/register', 'POST', [
            'name' => 'Failing Buyer',
            'email' => 'auth_buyer@test.com', // Duplicate email will fail!
            'username' => 'failing_buyer',
            'phone' => '+966500000008',
            'password' => 'secret12345',
            'service_city' => 'Riyadh',
            'service_area' => 'Olaya',
            'country_id' => $country->id,
            'terms_conditions' => 1,
            'terms_version_id' => $vBuyerEnV2->id,
            'terms_language' => 'en',
        ]);
        $userController->register($reqFailBuyer);
    } catch (\Throwable $e) {
        // Expected duplicate email failure
    }
    $acceptancesAfter = UserTermsAcceptance::count();

    assertTest(
        $acceptancesBefore === $acceptancesAfter,
        "T12: Failed registration leaves zero orphan acceptance records",
        "Count before: $acceptancesBefore, Count after: $acceptancesAfter"
    );

    // ------------------------------------------------------------------------
    // T13: Seller registration without terms_version_id fails
    // ------------------------------------------------------------------------
    $sellerNoTermsFailed = false;
    try {
        $reqSellerNoTerms = Request::create('/api/v1/provider/register', 'POST', [
            'name' => 'Provider Test',
            'email' => 'provider_noterms@test.com',
            'username' => 'provider_noterms',
            'phone' => '+966500000009',
            'password' => 'secret12345',
            'service_city' => 'Riyadh',
            'service_area' => 'Olaya',
            'country_id' => $country->id,
            'terms_conditions' => 1,
            'seller_type' => 1,
            'category_ids' => [$cat1->id],
            'national_id_number' => '1020304050',
            // Missing terms_version_id!
        ]);
        $userController->registerProvider($reqSellerNoTerms);
    } catch (ValidationException $e) {
        $sellerNoTermsFailed = isset($e->errors()['terms_version_id']);
    }

    assertTest(
        $sellerNoTermsFailed,
        "T13: Seller registration without terms_version_id fails with validation error"
    );

    // ------------------------------------------------------------------------
    // T14: Seller registration attempting to use Buyer terms is rejected
    // ------------------------------------------------------------------------
    \Illuminate\Support\Facades\Storage::fake('private');
    $sellerNoTermsFailed = false;
    try {
        $fakeDoc = UploadedFile::fake()->create('national_id.pdf', 50);
        $reqSellerCross = Request::create('/api/v1/provider/register', 'POST', [
            'name' => 'Provider Cross Role',
            'email' => 'provider_cross@test.com',
            'username' => 'provider_cross',
            'phone' => '+966500000010',
            'password' => 'secret12345',
            'service_city' => 'Riyadh',
            'service_area' => 'Olaya',
            'country_id' => $country->id,
            'terms_conditions' => 1,
            'terms_version_id' => $vBuyerAr->id, // Buyer terms used for Provider registration!
            'terms_language' => 'ar',
            'seller_type' => 1,
            'category_ids' => [$cat1->id],
            'national_id_number' => '1020304050',
        ], [], ['national_id_document' => $fakeDoc]);
        $userController->registerProvider($reqSellerCross);
    } catch (ValidationException $e) {
        $sellerCrossRoleFailed = isset($e->errors()['terms_version_id']);
    }

    assertTest(
        $sellerCrossRoleFailed,
        "T14: Cross-role terms rejection: Seller cannot register using Buyer terms version ID"
    );

    // ------------------------------------------------------------------------
    // T15: Successful seller registration creates user, verify record, categories & acceptance atomically
    // ------------------------------------------------------------------------
    $fakeDoc2 = UploadedFile::fake()->create('national_id.pdf', 50);
    $reqSuccessSeller = Request::create('/api/v1/provider/register', 'POST', [
        'name' => 'Authorized Provider Individual',
        'email' => 'auth_provider@test.com',
        'username' => 'auth_provider_1',
        'phone' => '+966500000011',
        'password' => 'secret12345',
        'service_city' => 'Riyadh',
        'service_area' => 'Olaya',
        'country_id' => $country->id,
        'terms_conditions' => 1,
        'terms_version_id' => $vSellerAr->id,
        'terms_language' => 'ar',
        'seller_type' => 1,
        'category_ids' => [$cat1->id, $cat2->id],
        'national_id_number' => '1020304050',
    ], [], ['national_id_document' => $fakeDoc2]);
    $resSeller = $userController->registerProvider($reqSuccessSeller);
    $sellerUser = User::where('email', 'auth_provider@test.com')->first();
    $sellerAcceptance = UserTermsAcceptance::where('user_id', $sellerUser?->id)->get();
    $sellerVerify = SellerVerify::where('seller_id', $sellerUser?->id)->first();
    $providerCategories = ProviderCategory::where('provider_id', $sellerUser?->id)->count();

    $sellerValid = $sellerUser
        && $sellerAcceptance->count() === 1
        && $sellerAcceptance->first()->legal_term_version_id === $vSellerAr->id
        && $sellerAcceptance->first()->role === 'seller'
        && $sellerAcceptance->first()->language === 'ar'
        && $sellerVerify
        && $providerCategories === 2;

    assertTest(
        $sellerValid,
        "T15: Successful seller registration creates User, SellerVerify, ProviderCategory & Acceptance atomically",
        "Seller ID: {$sellerUser?->id}, Verify status: {$sellerVerify?->status}, Categories: $providerCategories"
    );

    // ------------------------------------------------------------------------
    // T16: Social login for existing user succeeds without requiring terms
    // ------------------------------------------------------------------------
    $existingUser = User::create([
        'name' => 'Existing Social User',
        'email' => 'existing_social@test.com',
        'username' => 'existing_social',
        'password' => Hash::make('password123'),
        'user_type' => 1,
        'google_id' => 'google_existing_123',
    ]);

    $reqExistingSocial = Request::create('/api/v1/social/login', 'POST', [
        'email' => 'existing_social@test.com',
        'id' => 'google_existing_123',
        'isGoogle' => 1,
    ]);
    $resExistingSocial = $userController->socialLogin($reqExistingSocial);

    assertTest(
        $resExistingSocial->getData(true)['users']['id'] === $existingUser->id,
        "T16: Social login for existing user succeeds without requiring terms fields"
    );

    // ------------------------------------------------------------------------
    // T17: Social login for first-time user without terms agreement is rejected
    // ------------------------------------------------------------------------
    $socialFirstTimeFailed = false;
    try {
        $reqNewSocialNoTerms = Request::create('/api/v1/social/login', 'POST', [
            'email' => 'new_social_noterms@test.com',
            'id' => 'google_new_999',
            'displayName' => 'New Social User',
            'isGoogle' => 1,
            // Missing terms_version_id!
        ]);
        $userController->socialLogin($reqNewSocialNoTerms);
    } catch (ValidationException $e) {
        $socialFirstTimeFailed = isset($e->errors()['terms_version_id']);
    }

    assertTest(
        $socialFirstTimeFailed,
        "T17: First-time social account creation without terms agreement is rejected"
    );

    // ------------------------------------------------------------------------
    // T18: Social login for first-time user with terms creates user & acceptance
    // ------------------------------------------------------------------------
    $reqNewSocialSuccess = Request::create('/api/v1/social/login', 'POST', [
        'email' => 'new_social_success@test.com',
        'id' => 'google_new_1001',
        'displayName' => 'Authorized Social User',
        'isGoogle' => 1,
        'terms_version_id' => $vBuyerAr->id,
        'terms_language' => 'ar',
    ]);
    $resNewSocial = $userController->socialLogin($reqNewSocialSuccess);
    $newSocialUser = User::where('email', 'new_social_success@test.com')->first();
    $newSocialAcceptance = UserTermsAcceptance::where('user_id', $newSocialUser?->id)->get();

    $socialValid = $newSocialUser
        && $newSocialAcceptance->count() === 1
        && $newSocialAcceptance->first()->legal_term_version_id === $vBuyerAr->id
        && $newSocialAcceptance->first()->role === 'buyer'
        && $newSocialAcceptance->first()->language === 'ar';

    assertTest(
        $socialValid,
        "T18: First-time social account creation with terms creates user and acceptance record atomically",
        "User ID: {$newSocialUser?->id}, Acceptance records: {$newSocialAcceptance->count()}"
    );

} catch (\Throwable $e) {
    echo " [FATAL ERROR]: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failed++;
}

echo PHP_EOL . "==================================================================" . PHP_EOL;
echo " TEST SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
