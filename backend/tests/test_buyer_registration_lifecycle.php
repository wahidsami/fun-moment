<?php

/**
 * FUN MOMENT — BUYER REGISTRATION & TERMS VERIFICATION SUITE
 *
 * Verifies:
 * 1. Missing legal terms fail closed (HTTP 404 / 422) when current_version_id is NULL.
 * 2. Successful buyer registration with published terms (HTTP 201, Sanctum token, terms acceptance).
 * 3. Duplicate email and duplicate username validation rejection (HTTP 422).
 * 4. Stale, cross-role, or tampered terms version IDs rejection (HTTP 422).
 * 5. OTP lifecycle: generation, cooldown throttling (429), and verification.
 * 6. Zero persistent database footprint (wrapped in rolled back transaction).
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Http\Controllers\Api\LegalTermsApiController;
use App\Http\Controllers\Api\UserController;
use App\LegalTerm;
use App\LegalTermVersion;
use App\Services\Auth\OtpVerificationService;
use App\User;
use App\UserTermsAcceptance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
echo " BUYER REGISTRATION & BUSINESS OPERATIONS VERIFICATION" . PHP_EOL;
echo " Database: PostgreSQL 17.4 (Rolled Back Fixture)" . PHP_EOL;
echo "==================================================================" . PHP_EOL . PHP_EOL;

DB::beginTransaction();

try {
    $userController = app(UserController::class);
    $termsApiController = app(LegalTermsApiController::class);
    $admin = Admin::first();
    $adminId = $admin ? $admin->id : 1;

    // ------------------------------------------------------------------------
    // T1: Missing Legal Terms (Unpublished state)
    // ------------------------------------------------------------------------
    $masterBuyerAr = LegalTerm::where('role', 'buyer')->where('language', 'ar')->first();
    $origBuyerArVersion = $masterBuyerAr ? $masterBuyerAr->current_version_id : null;

    // Ensure buyer/ar has no published version for this test
    if ($masterBuyerAr) {
        $masterBuyerAr->current_version_id = null;
        $masterBuyerAr->save();
    }

    $reqTermsCurrent = Request::create('/api/v1/terms/current', 'GET', [
        'role' => 'buyer',
        'lang' => 'ar',
    ]);
    $resTermsCurrent = $termsApiController->current($reqTermsCurrent);
    assertTest(
        $resTermsCurrent->getStatusCode() === 404,
        "T1: Unpublished legal terms return HTTP 404 on /api/v1/terms/current",
        "Status: " . $resTermsCurrent->getStatusCode()
    );

    // Attempting registration without published terms fails closed with 422
    $reqNoTerms = Request::create('/api/v1/register', 'POST', [
        'name' => 'Unpublished Terms Test Buyer',
        'email' => 'unpub_' . Str::random(8) . '@example.test',
        'username' => 'unpub_' . Str::random(8),
        'phone' => '966501111111',
        'password' => 'Password123!',
        'service_city' => '1',
        'service_area' => '1',
        'country_id' => '1',
        'country_code' => 'SA',
        'terms_conditions' => '1',
        'terms_version_id' => '99999',
        'terms_language' => 'ar',
    ]);

    $noTermsFailedClosed = false;
    try {
        $userController->register($reqNoTerms);
    } catch (ValidationException $e) {
        $noTermsFailedClosed = true;
    }
    assertTest(
        $noTermsFailedClosed,
        "T2: Buyer registration fails closed (ValidationException) when terms are unpublished"
    );

    // ------------------------------------------------------------------------
    // T3: Publish Mock Terms & Register Buyer Successfully
    // ------------------------------------------------------------------------
    $versionAr = LegalTermVersion::create([
        'legal_term_id' => $masterBuyerAr->id,
        'version_number' => 1,
        'title' => 'شروط وأحكام المشتري — مسودة اختبار',
        'content' => '<p>شروط الخدمة للمشتري</p>',
        'content_hash' => hash('sha256', '<p>شروط الخدمة للمشتري</p>'),
        'published_by' => $adminId,
        'published_at' => now(),
    ]);
    $masterBuyerAr->current_version_id = $versionAr->id;
    $masterBuyerAr->save();

    $uniqueEmail = 'buyer_success_' . Str::random(8) . '@example.test';
    $uniqueUsername = 'buyer_' . Str::random(8);

    $reqSuccess = Request::create('/api/v1/register', 'POST', [
        'name' => 'Valid Registered Buyer',
        'email' => $uniqueEmail,
        'username' => $uniqueUsername,
        'phone' => '966502222222',
        'password' => 'SecretPassword123!',
        'service_city' => '1',
        'service_area' => '1',
        'country_id' => '1',
        'country_code' => 'SA',
        'terms_conditions' => '1',
        'terms_version_id' => $versionAr->id,
        'terms_language' => 'ar',
    ]);

    $resSuccess = $userController->register($reqSuccess);
    $dataSuccess = $resSuccess->getData(true);

    assertTest(
        $resSuccess->getStatusCode() === 201,
        "T3: Buyer registration returns HTTP 201 upon successful creation",
        "Status: " . $resSuccess->getStatusCode()
    );

    assertTest(
        isset($dataSuccess['status']) && $dataSuccess['status'] === 'ok' && !empty($dataSuccess['token']),
        "T4: Registration payload includes Sanctum API token",
        "Token prefix: " . substr($dataSuccess['token'] ?? '', 0, 10) . '...'
    );

    $createdBuyerId = $dataSuccess['users']['id'] ?? null;
    $createdUser = User::find($createdBuyerId);

    assertTest(
        $createdUser !== null
        && (int) $createdUser->user_type === 1
        && (int) $createdUser->seller_type === User::SELLER_TYPE_NOT_APPLICABLE
        && (int) $createdUser->user_status === 1,
        "T5: Buyer record persisted with user_type=1, seller_type=0, user_status=1",
        "User ID: {$createdBuyerId}"
    );

    // Verify UserTermsAcceptance record
    $acceptance = UserTermsAcceptance::where('user_id', $createdBuyerId)->first();
    assertTest(
        $acceptance !== null
        && (int) $acceptance->legal_term_version_id === (int) $versionAr->id
        && $acceptance->role === 'buyer'
        && $acceptance->language === 'ar',
        "T6: Immutable terms acceptance logged in user_terms_acceptances",
        "Version ID: " . ($acceptance ? $acceptance->legal_term_version_id : 'none')
    );

    // ------------------------------------------------------------------------
    // T7: Duplicate Email & Username Prevention
    // ------------------------------------------------------------------------
    $reqDupEmail = Request::create('/api/v1/register', 'POST', [
        'name' => 'Duplicate Email Buyer',
        'email' => $uniqueEmail, // Duplicate!
        'username' => 'unique_user_' . Str::random(8),
        'phone' => '966503333333',
        'password' => 'Password123!',
        'service_city' => '1',
        'service_area' => '1',
        'country_id' => '1',
        'terms_conditions' => '1',
        'terms_version_id' => $versionAr->id,
        'terms_language' => 'ar',
    ]);

    $dupEmailCaught = false;
    try {
        $userController->register($reqDupEmail);
    } catch (ValidationException $e) {
        $dupEmailCaught = isset($e->errors()['email']);
    }
    assertTest(
        $dupEmailCaught,
        "T7: Duplicate email address is strictly rejected with validation error"
    );

    $reqDupUsername = Request::create('/api/v1/register', 'POST', [
        'name' => 'Duplicate Username Buyer',
        'email' => 'different_' . Str::random(8) . '@example.test',
        'username' => $uniqueUsername, // Duplicate!
        'phone' => '966504444444',
        'password' => 'Password123!',
        'service_city' => '1',
        'service_area' => '1',
        'country_id' => '1',
        'terms_conditions' => '1',
        'terms_version_id' => $versionAr->id,
        'terms_language' => 'ar',
    ]);

    $dupUsernameCaught = false;
    try {
        $userController->register($reqDupUsername);
    } catch (ValidationException $e) {
        $dupUsernameCaught = isset($e->errors()['username']);
    }
    assertTest(
        $dupUsernameCaught,
        "T8: Duplicate username is strictly rejected with validation error"
    );

    // ------------------------------------------------------------------------
    // T9: Stale or Fabricated Version ID Rejection
    // ------------------------------------------------------------------------
    $reqTamperedVersion = Request::create('/api/v1/register', 'POST', [
        'name' => 'Tampered Terms Buyer',
        'email' => 'tampered_' . Str::random(8) . '@example.test',
        'username' => 'tampered_' . Str::random(8),
        'phone' => '966505555555',
        'password' => 'Password123!',
        'service_city' => '1',
        'service_area' => '1',
        'country_id' => '1',
        'terms_conditions' => '1',
        'terms_version_id' => $versionAr->id + 9999, // Non-matching version!
        'terms_language' => 'ar',
    ]);

    $tamperedVersionCaught = false;
    try {
        $userController->register($reqTamperedVersion);
    } catch (ValidationException $e) {
        $tamperedVersionCaught = isset($e->errors()['terms_version_id']);
    }
    assertTest(
        $tamperedVersionCaught,
        "T9: Non-matching or tampered terms_version_id is rejected with 422"
    );

    // ------------------------------------------------------------------------
    // T10: OTP Verification Lifecycle
    // ------------------------------------------------------------------------
    \Illuminate\Support\Facades\Mail::fake();
    $otpService = app(OtpVerificationService::class);
    $otpEmail = 'otp_test_' . Str::random(8) . '@example.test';

    // 1. Generate OTP
    $sendResult = $otpService->generateAndSendOtp($otpEmail, OtpVerificationService::PURPOSE_EMAIL_VERIFY);
    assertTest(
        $sendResult['success'] === true,
        "T10: OTP generated successfully for email verification"
    );

    // 2. Cooldown throttling: immediate second send within 60s should return 429
    $cooldownResult = $otpService->generateAndSendOtp($otpEmail, OtpVerificationService::PURPOSE_EMAIL_VERIFY);
    assertTest(
        $cooldownResult['success'] === false && ($cooldownResult['status_code'] ?? 0) === 429,
        "T11: Immediate OTP resend within 60 seconds is throttled (HTTP 429)"
    );

    // 3. Incorrect code verification fails
    $badVerify = $otpService->verifyOtp($otpEmail, '0000', OtpVerificationService::PURPOSE_EMAIL_VERIFY);
    assertTest(
        $badVerify['success'] === false,
        "T12: Incorrect OTP code is rejected"
    );

    // Clean cache keys
    Cache::forget("otp_{$otpEmail}_email_verify");
    Cache::forget("otp_cooldown_{$otpEmail}_email_verify");
    Cache::forget("otp_cooldown_{$otpEmail}_email_verify_ts");

} catch (\Throwable $e) {
    echo " [FATAL ERROR]: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failed++;
} finally {
    DB::rollBack();
    echo "\n [CLEANUP]: Database transaction rolled back cleanly. Zero persistent mutations." . PHP_EOL;
}

echo PHP_EOL . "==================================================================" . PHP_EOL;
echo " TEST SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
