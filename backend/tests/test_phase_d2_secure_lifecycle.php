<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Category;
use App\LegalTerm;
use App\LegalTermVersion;
use App\ProviderCategory;
use App\SellerVerify;
use App\Services\Auth\OtpVerificationService;
use App\Services\Verification\ProviderVerificationDocumentService;
use App\User;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SellerServiceController;
use App\Http\Controllers\FrontendUserManageController;
use App\Http\Middleware\UserEmailVerify;
use App\Http\Middleware\SellerApprovedCheck;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

$passed = 0;
$failed = 0;

function assertCheck($condition, $description) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $description" . PHP_EOL;
        $passed++;
    } else {
        echo "  [FAIL] $description" . PHP_EOL;
        $failed++;
    }
}

echo "==================================================================" . PHP_EOL;
echo " PHASE D2: SECURE OTP REGISTRATION & ACCOUNT LIFECYCLE TEST SUITE" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

DB::beginTransaction();

try {
    $realMailManager = $app->make('mail.manager');
    Mail::fake();

    // -------------------------------------------------------------------------
    // 0. Test Setup & Fixtures
    // -------------------------------------------------------------------------
    $admin = Admin::first();
    $adminId = $admin ? $admin->id : 4;

    // Published buyer terms
    $buyerMaster = LegalTerm::firstOrCreate([
        'role' => LegalTerm::ROLE_BUYER,
        'language' => 'ar',
    ], [
        'title' => 'الشروط والأحكام للمشتري',
    ]);
    $buyerVersion = LegalTermVersion::create([
        'legal_term_id' => $buyerMaster->id,
        'version_number' => 9991,
        'title' => 'شروط المشتري للاختبار D2',
        'content' => '<p>شروط المشتري D2</p>',
        'content_hash' => hash('sha256', '<p>شروط المشتري D2</p>'),
        'published_by' => $adminId,
        'published_at' => now(),
    ]);
    $buyerMaster->current_version_id = $buyerVersion->id;
    $buyerMaster->save();

    // Published seller terms
    $sellerMaster = LegalTerm::firstOrCreate([
        'role' => LegalTerm::ROLE_SELLER,
        'language' => 'ar',
    ], [
        'title' => 'الشروط والأحكام لمقدم الخدمة',
    ]);
    $sellerVersion = LegalTermVersion::create([
        'legal_term_id' => $sellerMaster->id,
        'version_number' => 9991,
        'title' => 'شروط مقدم الخدمة للاختبار D2',
        'content' => '<p>شروط مقدم الخدمة D2</p>',
        'content_hash' => hash('sha256', '<p>شروط مقدم الخدمة D2</p>'),
        'published_by' => $adminId,
        'published_at' => now(),
    ]);
    $sellerMaster->current_version_id = $sellerVersion->id;
    $sellerMaster->save();

    $testCategory = Category::where('status', 1)->first();
    if (!$testCategory) {
        $testCategory = Category::create([
            'name' => 'Entertainment',
            'status' => 1,
        ]);
    }

    // Verify User #16 baseline
    $account16 = User::find(16);
    if (!$account16) {
        $account16 = new User();
        $account16->id = 16;
        $account16->name = 'Test Provider 16';
        $account16->email = 'provider16@test.local';
        $account16->username = 'provider16_test';
        $account16->phone = '+966501616161';
        $account16->password = Hash::make('Secret123!');
        $account16->user_type = 0;
        $account16->user_status = 1;
        $account16->email_verified = 1;
        $account16->save();
    }
    $account16Snapshot = $account16->only(['id', 'email', 'username', 'phone', 'user_type', 'user_status', 'email_verified']);

    $controller = app(UserController::class);
    $sellerServiceController = app(SellerServiceController::class);
    $frontendUserController = app(FrontendUserManageController::class);
    $otpService = app(OtpVerificationService::class);
    Storage::fake('local');

    // -------------------------------------------------------------------------
    // REQ 1 & REQ 3: Buyer Registration does NOT issue full token, sets unverified
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 1 & 3: Buyer Registration Security Baseline ---" . PHP_EOL;

    $buyerEmail = 'd2_buyer_' . uniqid() . '@funmoments.sa';
    $buyerRequest = Request::create('/api/v1/register', 'POST', [
        'name' => 'D2 Buyer',
        'email' => $buyerEmail,
        'username' => 'd2buyer_' . uniqid(),
        'phone' => '+96650' . rand(1000000, 9999999),
        'password' => 'Password123!',
        'service_city' => 2,
        'service_area' => 2,
        'country_id' => 2,
        'terms_conditions' => 1,
        'terms_version_id' => $buyerVersion->id,
        'terms_language' => 'ar',
    ]);
    $buyerResponse = $controller->register($buyerRequest);
    $buyerData = json_decode($buyerResponse->getContent(), true);

    assertCheck(in_array($buyerResponse->getStatusCode(), [200, 201], true), 'Buyer registration returns HTTP 201/200');
    assertCheck(array_key_exists('token', $buyerData) && $buyerData['token'] === null, 'Buyer registration response explicitly returns token = null');
    assertCheck(isset($buyerData['requires_verification']) && $buyerData['requires_verification'] === true, 'Buyer registration returns requires_verification = true');

    $createdBuyer = User::find($buyerData['users']['id']);
    assertCheck($createdBuyer && (int) $createdBuyer->user_status === 1, 'Buyer persisted with user_status = 1 (active)');
    assertCheck($createdBuyer && (int) $createdBuyer->email_verified === 0, 'Buyer persisted with email_verified = 0 (unverified)');

    // -------------------------------------------------------------------------
    // REQ 2 & REQ 12: Seller Registration does NOT issue full token, commercial status pending
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 2 & 12: Seller Registration Security & Commercial State ---" . PHP_EOL;

    $sellerEmail = 'd2_seller_' . uniqid() . '@funmoments.sa';
    $fakeIdDoc = UploadedFile::fake()->create('seller_id.pdf', 100, 'application/pdf');
    $sellerRequest = Request::create('/api/v1/register', 'POST', [
        'user_type' => 0,
        'seller_type' => 1,
        'name' => 'D2 Seller',
        'email' => $sellerEmail,
        'username' => 'd2seller_' . uniqid(),
        'phone' => '+96650' . rand(1000000, 9999999),
        'password' => 'Password123!',
        'service_city' => 2,
        'service_area' => 2,
        'country_id' => 2,
        'national_id_number' => '1099887766',
        'category_ids' => json_encode([$testCategory->id]),
        'terms_conditions' => 1,
        'terms_version_id' => $sellerVersion->id,
        'terms_language' => 'ar',
    ], [], [
        'national_id_document' => $fakeIdDoc,
    ]);
    $sellerResponse = $controller->register($sellerRequest);
    $sellerData = json_decode($sellerResponse->getContent(), true);

    assertCheck(in_array($sellerResponse->getStatusCode(), [200, 201], true), 'Seller registration returns HTTP 201/200');
    assertCheck(array_key_exists('token', $sellerData) && $sellerData['token'] === null, 'Seller registration response explicitly returns token = null');
    assertCheck(isset($sellerData['requires_verification']) && $sellerData['requires_verification'] === true, 'Seller registration returns requires_verification = true');

    $createdSeller = User::find($sellerData['users']['id']);
    assertCheck($createdSeller && (int) $createdSeller->user_status === 1, 'Seller persisted with user_status = 1 (active)');
    assertCheck($createdSeller && (int) $createdSeller->email_verified === 0, 'Seller persisted with email_verified = 0 (unverified)');

    $sellerVerify = SellerVerify::where('seller_id', $createdSeller->id)->first();
    assertCheck($sellerVerify && (int) $sellerVerify->status === 0, 'Seller commercial verification status is 0 (Pending admin review)');

    // -------------------------------------------------------------------------
    // REQ 4: Initial OTP dispatch uses email_verify purpose
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 4: Initial OTP Dispatch Purpose ---" . PHP_EOL;

    $normalizedBuyerEmail = strtolower(trim($buyerEmail));
    $cacheKeyEmailVerify = "otp_{$normalizedBuyerEmail}_email_verify";
    $otpCachedData = Cache::get($cacheKeyEmailVerify);

    assertCheck(!empty($otpCachedData) && is_array($otpCachedData), 'OTP is cached under the otp_{email}_email_verify key');
    assertCheck(isset($otpCachedData['code_hash']), 'Cached OTP contains cryptographic hash (not plain text)');

    // -------------------------------------------------------------------------
    // REQ 5 & 6: Delivery Failure Handling & Safe Recovery
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 5 & 6: Delivery Failure & Safe Recovery ---" . PHP_EOL;

    // Simulate transport failure using failing transport
    Mail::swap($realMailManager);
    Mail::extend('failing_transport', function() {
        return new class implements \Symfony\Component\Mailer\Transport\TransportInterface {
            public function send(\Symfony\Component\Mime\RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): ?\Symfony\Component\Mailer\SentMessage {
                throw new \Symfony\Component\Mailer\Exception\TransportException('Simulated Mail Outage');
            }
            public function __toString(): string { return 'failing://'; }
        };
    });
    $prevMailer = config('mail.default');
    config([
        'mail.mailers.failing' => ['transport' => 'failing_transport'],
        'mail.default' => 'failing',
    ]);

    $failBuyerEmail = 'd2_fail_buyer_' . uniqid() . '@funmoments.sa';
    $failRequest = Request::create('/api/v1/register', 'POST', [
        'name' => 'Fail Buyer',
        'email' => $failBuyerEmail,
        'username' => 'failbuyer_' . uniqid(),
        'phone' => '+96650' . rand(1000000, 9999999),
        'password' => 'Password123!',
        'service_city' => 2,
        'service_area' => 2,
        'country_id' => 2,
        'terms_conditions' => 1,
        'terms_version_id' => $buyerVersion->id,
        'terms_language' => 'ar',
    ]);
    $failResponse = $controller->register($failRequest);
    $failData = json_decode($failResponse->getContent(), true);

    assertCheck($failResponse->getStatusCode() === 201, 'Registration persists user even if mail dispatch throws');
    assertCheck($failData['otp_sent'] === false, 'Response explicitly reports otp_sent = false on delivery failure');
    assertCheck($failData['requires_verification'] === true, 'Response instructs client that verification is still required');

    $failUser = User::find($failData['users']['id']);
    assertCheck($failUser && (int) $failUser->email_verified === 0, 'User left in pending unverified state');

    // Restore clean Mail::fake()
    config(['mail.default' => $prevMailer]);
    Mail::fake();

    // -------------------------------------------------------------------------
    // REQ 7: Resend OTP explicitly uses email_verify purpose & respects cooldown
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 7: Resend OTP Purpose & Cooldown ---" . PHP_EOL;

    $resendRequest = Request::create('/api/v1/send-otp-in-mail', 'POST', [
        'email' => $failBuyerEmail,
        'purpose' => 'email_verify',
    ]);
    $resendResponse = $controller->sendOTP($resendRequest);
    $resendData = json_decode($resendResponse->getContent(), true);

    assertCheck(in_array($resendResponse->getStatusCode(), [200, 201], true), 'Resend OTP with purpose email_verify succeeds');

    // Immediate second resend should hit 60-second cooldown (HTTP 429)
    $rapidResendResponse = $controller->sendOTP($resendRequest);
    assertCheck($rapidResendResponse->getStatusCode() === 429, 'Immediate second resend is throttled with HTTP 429 cooldown');

    // -------------------------------------------------------------------------
    // REQ 8: Invalid OTP cannot verify account
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 8: Invalid OTP Rejection ---" . PHP_EOL;

    $invalidVerifyRequest = Request::create('/api/v1/verify-email-otp', 'POST', [
        'email' => $buyerEmail,
        'otp' => '0000', // Invalid code
    ]);
    $invalidVerifyResponse = $controller->verifyEmailOtp($invalidVerifyRequest);
    $invalidVerifyData = json_decode($invalidVerifyResponse->getContent(), true);

    assertCheck($invalidVerifyResponse->getStatusCode() === 422, 'Invalid OTP returns HTTP 422');
    $createdBuyer->refresh();
    assertCheck((int) $createdBuyer->email_verified === 0, 'User email_verified remains 0 after invalid OTP attempt');

    // -------------------------------------------------------------------------
    // REQ 9: Expired OTP cannot verify account
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 9: Expired OTP Rejection ---" . PHP_EOL;

    $expiredBuyerEmail = 'expired_' . uniqid() . '@funmoments.sa';
    $userForExpiry = User::create([
        'name' => 'Expired User',
        'email' => $expiredBuyerEmail,
        'username' => 'exp_' . uniqid(),
        'phone' => '+96650' . rand(1000000, 9999999),
        'password' => Hash::make('Password123!'),
        'user_type' => 1,
        'user_status' => 1,
        'email_verified' => 0,
    ]);

    // Force expired cache entry
    $normExpEmail = strtolower(trim($expiredBuyerEmail));
    $expKey = "otp_{$normExpEmail}_email_verify";
    Cache::put($expKey, [
        'code_hash' => hash_hmac('sha256', '1234', (string) config('app.key')),
        'created_at' => now()->subMinutes(16)->timestamp, // Older than 15-min TTL
        'expires_at' => now()->subMinute()->timestamp,
        'attempts' => 0,
    ], now()->addMinutes(15));

    $expiredVerifyRequest = Request::create('/api/v1/verify-email-otp', 'POST', [
        'email' => $expiredBuyerEmail,
        'otp' => '1234',
    ]);
    $expiredVerifyResponse = $controller->verifyEmailOtp($expiredVerifyRequest);
    assertCheck(in_array($expiredVerifyResponse->getStatusCode(), [400, 422], true), 'Expired OTP returns HTTP 400/422');
    $userForExpiry->refresh();
    assertCheck((int) $userForExpiry->email_verified === 0, 'User email_verified remains 0 after expired OTP attempt');

    // -------------------------------------------------------------------------
    // REQ 10 & 11: Valid OTP atomically verifies account & issues Sanctum Bearer Token
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 10 & 11: Successful OTP Verification & Token Issuance ---" . PHP_EOL;

    // Seed known OTP for buyer
    $knownOtp = '7892';
    Cache::put($cacheKeyEmailVerify, [
        'code_hash' => hash_hmac('sha256', $knownOtp, (string) config('app.key')),
        'created_at' => now()->timestamp,
        'expires_at' => now()->addMinutes(15)->timestamp,
        'attempts' => 0,
    ], now()->addMinutes(15));

    $validVerifyRequest = Request::create('/api/v1/verify-email-otp', 'POST', [
        'email' => $buyerEmail,
        'otp' => $knownOtp,
    ]);
    $validVerifyResponse = $controller->verifyEmailOtp($validVerifyRequest);
    $validVerifyData = json_decode($validVerifyResponse->getContent(), true);

    assertCheck(in_array($validVerifyResponse->getStatusCode(), [200, 201], true), 'Valid OTP verification returns HTTP 200/201');
    assertCheck(!empty($validVerifyData['token']), 'Valid OTP verification generates and returns Sanctum Bearer Token');

    $createdBuyer->refresh();
    assertCheck((int) $createdBuyer->email_verified === 1, 'User email_verified is now 1 in database');

    // Single-use: Repeated verification with same code must fail immediately
    $reuseResponse = $controller->verifyEmailOtp($validVerifyRequest);
    assertCheck($reuseResponse->getStatusCode() === 422, 'OTP code is atomically consumed; second attempt with same code returns 422');

    // -------------------------------------------------------------------------
    // REQ 16: Unverified user cannot log in (returns HTTP 403)
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 16: Login Gate for Unverified Accounts ---" . PHP_EOL;

    // 1. Attempt login with unverified seller account
    $unverifiedLoginRequest = Request::create('/api/v1/login', 'POST', [
        'email' => $sellerEmail,
        'password' => 'Password123!',
    ]);
    $unverifiedLoginResponse = $controller->login($unverifiedLoginRequest);
    $unverifiedLoginData = json_decode($unverifiedLoginResponse->getContent(), true);

    assertCheck($unverifiedLoginResponse->getStatusCode() === 403, 'Login with unverified email returns HTTP 403');
    assertCheck(isset($unverifiedLoginData['requires_verification']) && $unverifiedLoginData['requires_verification'] === true, 'Login 403 payload includes requires_verification = true');

    // 2. Attempt login with suspended user (user_status = 0)
    $suspendedUser = User::create([
        'name' => 'Suspended User',
        'email' => 'suspended_' . uniqid() . '@funmoments.sa',
        'username' => 'susp_' . uniqid(),
        'phone' => '+96650' . rand(1000000, 9999999),
        'password' => Hash::make('Password123!'),
        'user_type' => 1,
        'user_status' => 0, // Suspended
        'email_verified' => 1,
    ]);
    $suspendedLoginRequest = Request::create('/api/v1/login', 'POST', [
        'email' => $suspendedUser->email,
        'password' => 'Password123!',
    ]);
    $suspendedLoginResponse = $controller->login($suspendedLoginRequest);
    assertCheck($suspendedLoginResponse->getStatusCode() === 403, 'Login with suspended account returns HTTP 403');

    // 3. Attempt login with verified buyer account
    $verifiedLoginRequest = Request::create('/api/v1/login', 'POST', [
        'email' => $buyerEmail,
        'password' => 'Password123!',
    ]);
    $verifiedLoginResponse = $controller->login($verifiedLoginRequest);
    $verifiedLoginData = json_decode($verifiedLoginResponse->getContent(), true);

    assertCheck(in_array($verifiedLoginResponse->getStatusCode(), [200, 201], true), 'Login with verified active account succeeds with HTTP 200/201');
    assertCheck(!empty($verifiedLoginData['token']), 'Verified login issues Sanctum token');

    // -------------------------------------------------------------------------
    // REQ 17: UserEmailVerify Middleware Blocks Legacy/Unverified Tokens
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 17: UserEmailVerify Middleware Route Protection ---" . PHP_EOL;

    $middleware = new UserEmailVerify();

    // 1. Request with unverified seller
    $fakeSanctumRequestUnverified = Request::create('/api/v1/user/profile-info', 'GET');
    $fakeSanctumRequestUnverified->setUserResolver(function () use ($createdSeller) {
        return $createdSeller;
    });

    $middlewareBlockedResponse = $middleware->handle($fakeSanctumRequestUnverified, function ($req) {
        return response()->json(['status' => 'success', 'data' => 'reached_controller']);
    });

    assertCheck($middlewareBlockedResponse->getStatusCode() === 403, 'UserEmailVerify middleware blocks unverified user with HTTP 403');
    $blockedData = json_decode($middlewareBlockedResponse->getContent(), true);
    assertCheck(isset($blockedData['requires_verification']) && $blockedData['requires_verification'] === true, 'Middleware returns requires_verification = true');

    // 2. Request with verified buyer
    $fakeSanctumRequestVerified = Request::create('/api/v1/user/profile-info', 'GET');
    $fakeSanctumRequestVerified->setUserResolver(function () use ($createdBuyer) {
        return $createdBuyer;
    });

    $middlewarePassedResponse = $middleware->handle($fakeSanctumRequestVerified, function ($req) {
        return response()->json(['status' => 'success', 'data' => 'reached_controller']);
    });

    assertCheck($middlewarePassedResponse->getStatusCode() === 200, 'UserEmailVerify middleware allows verified user through');

    // -------------------------------------------------------------------------
    // REQ 13, 14, 15: Seller Commercial Access Gates (Pending, Approved, Rejected)
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 13, 14, 15: Seller Commercial Verification Gates ---" . PHP_EOL;

    $sellerGateMiddleware = new SellerApprovedCheck();

    // 1. Pending seller (seller_verifies.status = 0)
    $createdSeller->refresh();
    $sellerRequestPending = Request::create('/api/v1/seller/service/add-service', 'POST');
    $sellerRequestPending->setUserResolver(function () use ($createdSeller) {
        return $createdSeller;
    });

    $gatePendingResponse = $sellerGateMiddleware->handle($sellerRequestPending, function ($req) {
        return response()->json(['status' => 'success', 'data' => 'service_created']);
    });

    assertCheck($gatePendingResponse->getStatusCode() === 403, 'SellerApprovedCheck middleware blocks pending seller with HTTP 403');
    $gatePendingData = json_decode($gatePendingResponse->getContent(), true);
    assertCheck($gatePendingData['commercial_status'] === 0, 'Middleware reports commercial_status = 0 (Pending)');

    // Controller defense-in-depth test on ServiceOnOff
    Auth::guard('sanctum')->setUser($createdSeller);
    $controllerDirectResponse = $sellerServiceController->ServiceOnOff(new Request(['id' => 1]));
    assertCheck($controllerDirectResponse->getStatusCode() === 403, 'SellerServiceController::ServiceOnOff defense-in-depth blocks pending seller');

    // 2. Rejected seller (seller_verifies.status = 2)
    $sellerVerify->status = SellerVerify::STATUS_REJECTED;
    $sellerVerify->save();
    $createdSeller->refresh();

    $gateRejectedResponse = $sellerGateMiddleware->handle($sellerRequestPending, function ($req) {
        return response()->json(['status' => 'success', 'data' => 'service_created']);
    });

    assertCheck($gateRejectedResponse->getStatusCode() === 403, 'SellerApprovedCheck middleware blocks rejected seller with HTTP 403');
    $gateRejectedData = json_decode($gateRejectedResponse->getContent(), true);
    assertCheck($gateRejectedData['commercial_status'] === 2, 'Middleware reports commercial_status = 2 (Rejected)');

    // 3. Approved seller (seller_verifies.status = 1)
    $sellerVerify->status = SellerVerify::STATUS_APPROVED;
    $sellerVerify->save();
    $createdSeller->refresh();

    $gateApprovedResponse = $sellerGateMiddleware->handle($sellerRequestPending, function ($req) {
        return response()->json(['status' => 'success', 'data' => 'service_created']);
    });

    assertCheck($gateApprovedResponse->getStatusCode() === 200, 'SellerApprovedCheck middleware allows approved seller through');

    // -------------------------------------------------------------------------
    // REQ 18 & 19: Safe Unverified Retry vs Password Mismatch vs Verified Overwrite
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 18 & 19: Registration Retry Safety Guarantees ---" . PHP_EOL;

    // Create an unverified user for retry tests
    $unverifiedRetryEmail = 'retry_user_' . uniqid() . '@funmoments.sa';
    $firstRetryReq = Request::create('/api/v1/register', 'POST', [
        'name' => 'Retry User Initial',
        'email' => $unverifiedRetryEmail,
        'username' => 'retry_' . uniqid(),
        'phone' => '+96650' . rand(1000000, 9999999),
        'password' => 'InitialPassword123!',
        'service_city' => 2,
        'service_area' => 2,
        'country_id' => 2,
        'terms_conditions' => 1,
        'terms_version_id' => $buyerVersion->id,
        'terms_language' => 'ar',
    ]);
    $firstRetryResp = $controller->register($firstRetryReq);
    $firstRetryData = json_decode($firstRetryResp->getContent(), true);
    $initialUserId = $firstRetryData['users']['id'];

    // Test 18: Retry with matching password updates existing record without duplicate
    $secondRetryReq = Request::create('/api/v1/register', 'POST', [
        'name' => 'Retry User Updated Name',
        'email' => $unverifiedRetryEmail,
        'username' => 'retry_' . uniqid(),
        'phone' => '+96650' . rand(1000000, 9999999),
        'password' => 'InitialPassword123!', // Matching password
        'service_city' => 2,
        'service_area' => 2,
        'country_id' => 2,
        'terms_conditions' => 1,
        'terms_version_id' => $buyerVersion->id,
        'terms_language' => 'ar',
    ]);
    $secondRetryResp = $controller->register($secondRetryReq);
    $secondRetryData = json_decode($secondRetryResp->getContent(), true);

    assertCheck($secondRetryData['users']['id'] === $initialUserId, 'Registration retry with matching password updates existing user ID (no duplicate)');
    assertCheck(User::where('email', $unverifiedRetryEmail)->count() === 1, 'Exactly 1 user record exists in database for this email');

    // Test 19: Retry with MISMATCHING password fails validation
    $mismatchPassCaught = false;
    try {
        $mismatchRetryReq = Request::create('/api/v1/register', 'POST', [
            'name' => 'Attacker Overwrite',
            'email' => $unverifiedRetryEmail,
            'username' => 'attacker_' . uniqid(),
            'phone' => '+96650' . rand(1000000, 9999999),
            'password' => 'WrongPassword999!', // Mismatched password
            'service_city' => 2,
            'service_area' => 2,
            'country_id' => 2,
            'terms_conditions' => 1,
            'terms_version_id' => $buyerVersion->id,
            'terms_language' => 'ar',
        ]);
        $controller->register($mismatchRetryReq);
    } catch (\Illuminate\Validation\ValidationException $ve) {
        $mismatchPassCaught = true;
    }
    assertCheck($mismatchPassCaught, 'Retry with mismatching password throws ValidationException, preventing account takeover');

    // Test 18b: Verified accounts can NEVER be overwritten by registration
    $verifiedOverwriteCaught = false;
    try {
        $overwriteReq = Request::create('/api/v1/register', 'POST', [
            'name' => 'Overwrite Verified',
            'email' => $buyerEmail, // Verified buyer
            'username' => 'buyer_ovw_' . uniqid(),
            'phone' => '+96650' . rand(1000000, 9999999),
            'password' => 'Password123!',
            'service_city' => 2,
            'service_area' => 2,
            'country_id' => 2,
            'terms_conditions' => 1,
            'terms_version_id' => $buyerVersion->id,
            'terms_language' => 'ar',
        ]);
        $controller->register($overwriteReq);
    } catch (\Illuminate\Validation\ValidationException $ve) {
        $verifiedOverwriteCaught = true;
    }
    assertCheck($verifiedOverwriteCaught, 'Registration retry against an already verified account throws ValidationException');

    // -------------------------------------------------------------------------
    // Admin Dashboard formatUserPayload contract
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- Admin Dashboard formatUserPayload Contract ---" . PHP_EOL;

    $reflection = new \ReflectionClass($frontendUserController);
    $formatMethod = $reflection->getMethod('formatUserPayload');
    $formatMethod->setAccessible(true);

    $unverifiedSellerPayload = $formatMethod->invoke($frontendUserController, $createdSeller);
    assertCheck(isset($unverifiedSellerPayload['email_verified']), 'formatUserPayload contains email_verified key');
    assertCheck($unverifiedSellerPayload['email_verified'] === false, 'Unverified user formatUserPayload returns email_verified = false');
    assertCheck(isset($unverifiedSellerPayload['lifecycle_status']), 'formatUserPayload contains lifecycle_status key');
    assertCheck($unverifiedSellerPayload['lifecycle_status'] === 'unverified_email', 'Unverified seller lifecycle_status = unverified_email');

    $createdBuyer->refresh();
    $verifiedBuyerPayload = $formatMethod->invoke($frontendUserController, $createdBuyer);
    assertCheck($verifiedBuyerPayload['email_verified'] === true, 'Verified buyer formatUserPayload returns email_verified = true');
    assertCheck($verifiedBuyerPayload['lifecycle_status'] === 'active', 'Verified buyer lifecycle_status = active');

    // -------------------------------------------------------------------------
    // REQ 20: User #16 Safety and State Preservation
    // -------------------------------------------------------------------------
    echo PHP_EOL . "--- REQ 20: Account #16 Integrity & State Preservation ---" . PHP_EOL;

    $account16Final = User::find(16);
    $account16FinalSnapshot = $account16Final->only(['id', 'email', 'username', 'phone', 'user_type', 'user_status', 'email_verified']);
    assertCheck($account16Snapshot === $account16FinalSnapshot, 'User #16 attributes are 100% unchanged after full test suite execution');

} catch (\Throwable $e) {
    echo "EXCEPTION OCCURRED: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failed++;
} finally {
    // Guaranteed Transactional Rollback
    DB::rollBack();
    echo PHP_EOL . "Database transaction rolled back cleanly. Zero permanent mutations." . PHP_EOL;
}

echo PHP_EOL . "==================================================================" . PHP_EOL;
echo " PHASE D2 SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
