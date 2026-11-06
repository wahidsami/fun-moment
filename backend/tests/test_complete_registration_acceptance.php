<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Category;
use App\LegalTerm;
use App\LegalTermVersion;
use App\ProviderCategory;
use App\SellerVerify;
use App\User;
use App\UserTermsAcceptance;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
echo " WORKSTREAM C: COMPLETE REGISTRATION & OTP ACCEPTANCE TESTS" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

DB::beginTransaction();

try {
    // 0. Ensure a published buyer and seller terms exist for testing
    $admin = App\Admin::first();
    $adminId = $admin ? $admin->id : 4;

    $buyerMaster = LegalTerm::firstOrCreate([
        'role' => LegalTerm::ROLE_BUYER,
        'language' => 'ar',
    ], [
        'title' => 'الشروط والأحكام للمشتري',
    ]);
    $buyerVersion = App\LegalTermVersion::create([
        'legal_term_id' => $buyerMaster->id,
        'version_number' => 9991,
        'title' => 'شروط وأحكام المشتري',
        'content' => '<p>محتوى الشروط والأحكام</p>',
        'content_hash' => hash('sha256', '<p>محتوى الشروط والأحكام</p>'),
        'published_by' => $adminId,
        'published_at' => now(),
    ]);
    $buyerMaster->current_version_id = $buyerVersion->id;
    $buyerMaster->save();

    $sellerMaster = LegalTerm::firstOrCreate([
        'role' => LegalTerm::ROLE_SELLER,
        'language' => 'ar',
    ], [
        'title' => 'الشروط والأحكام لمقدم الخدمة',
    ]);
    $sellerVersion = App\LegalTermVersion::create([
        'legal_term_id' => $sellerMaster->id,
        'version_number' => 9991,
        'title' => 'شروط وأحكام مقدم الخدمة',
        'content' => '<p>محتوى الشروط لمقدم الخدمة</p>',
        'content_hash' => hash('sha256', '<p>محتوى الشروط لمقدم الخدمة</p>'),
        'published_by' => $adminId,
        'published_at' => now(),
    ]);
    $sellerMaster->current_version_id = $sellerVersion->id;
    $sellerMaster->save();

    // Ensure at least one active category exists
    $testCategory = Category::where('status', 1)->first();
    if (!$testCategory) {
        $testCategory = Category::create([
            'name' => 'Entertainment',
            'status' => 1,
        ]);
    }

    $controller = app(\App\Http\Controllers\Api\UserController::class);

    // ---------------------------------------------------------------------
    // 1. BUYER REGISTRATION FLOW
    // ---------------------------------------------------------------------
    echo PHP_EOL . "--- 1. Buyer Registration Flow ---" . PHP_EOL;
    $buyerEmail = 'buyer_e2e_' . uniqid() . '@funmoments.sa';
    $buyerUsername = 'buyer_' . uniqid();
    $buyerPhone = '+96650' . rand(1000000, 9999999);

    $buyerRequest = Request::create('/api/v1/register', 'POST', [
        'name' => 'Sara Buyer',
        'email' => $buyerEmail,
        'username' => $buyerUsername,
        'phone' => $buyerPhone,
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

    assertCheck(in_array($buyerResponse->getStatusCode(), [200, 201], true), 'Buyer registration returns HTTP 200/201 success');
    assertCheck(isset($buyerData['users']['id']), 'Buyer registration returns persisted user ID');
    assertCheck(!empty($buyerData['token']), 'Buyer registration issues Sanctum bearer token');

    $buyerUser = User::find($buyerData['users']['id']);
    assertCheck($buyerUser->user_type === 1, 'Buyer record has user_type = 1 (Buyer)');
    assertCheck($buyerUser->service_city == 2 && $buyerUser->service_area == 2, 'Buyer record stores city ID 2 and area ID 2');

    $buyerAcceptance = UserTermsAcceptance::where('user_id', $buyerUser->id)->first();
    assertCheck(!empty($buyerAcceptance) && $buyerAcceptance->legal_term_version_id == $buyerVersion->id, 'Buyer legal terms acceptance is recorded in database');

    // ---------------------------------------------------------------------
    // 2. INDIVIDUAL SELLER REGISTRATION FLOW
    // ---------------------------------------------------------------------
    echo PHP_EOL . "--- 2. Individual Seller Registration Flow ---" . PHP_EOL;
    Storage::fake('local');
    $sellerEmail = 'seller_ind_' . uniqid() . '@funmoments.sa';
    $sellerUsername = 'seller_ind_' . uniqid();
    $sellerPhone = '+96650' . rand(1000000, 9999999);
    $fakeIdDoc = UploadedFile::fake()->create('national_id.pdf', 100, 'application/pdf');

    $sellerRequest = Request::create('/api/v1/register', 'POST', [
        'user_type' => 0,
        'seller_type' => 1,
        'name' => 'Ahmed Musician',
        'email' => $sellerEmail,
        'username' => $sellerUsername,
        'phone' => $sellerPhone,
        'password' => 'Password123!',
        'service_city' => 2,
        'service_area' => 2,
        'country_id' => 2,
        'national_id_number' => '1088776655',
        'category_ids' => json_encode([$testCategory->id]),
        'terms_conditions' => 1,
        'terms_version_id' => $sellerVersion->id,
        'terms_language' => 'ar',
    ], [], [
        'national_id_document' => $fakeIdDoc,
    ]);

    $sellerResponse = $controller->register($sellerRequest);
    $sellerData = json_decode($sellerResponse->getContent(), true);

    assertCheck(in_array($sellerResponse->getStatusCode(), [200, 201], true), 'Individual seller registration returns HTTP 200/201 success');
    assertCheck(!empty($sellerData['token']), 'Individual seller registration issues Sanctum bearer token');

    $sellerUser = User::find($sellerData['users']['id']);
    assertCheck($sellerUser->user_type === 0, 'Seller record has user_type = 0 (Seller)');
    assertCheck($sellerUser->seller_type === 1, 'Seller record has seller_type = 1 (Individual)');

    $sellerVerify = SellerVerify::where('seller_id', $sellerUser->id)->first();
    assertCheck(!empty($sellerVerify), 'SellerVerify record created with BIGINT seller_id = ' . $sellerUser->id);
    assertCheck($sellerVerify->national_id_number === '1088776655', 'SellerVerify stores national_id_number correctly');
    assertCheck($sellerVerify->status === 0, 'SellerVerify status is initialized to 0 (Pending admin approval)');

    $providerCat = ProviderCategory::where('provider_id', $sellerUser->id)->where('category_id', $testCategory->id)->first();
    assertCheck(!empty($providerCat), 'Seller is associated with chosen category ID ' . $testCategory->id);

    // ---------------------------------------------------------------------
    // 3. COMPANY SELLER REGISTRATION FLOW
    // ---------------------------------------------------------------------
    echo PHP_EOL . "--- 3. Company Seller Registration Flow ---" . PHP_EOL;
    $compEmail = 'company_' . uniqid() . '@funmoments.sa';
    $compUsername = 'company_' . uniqid();
    $compPhone = '+96650' . rand(1000000, 9999999);
    $compCrNumber = 'CR-' . rand(10000000, 99999999);
    $fakeCrDoc = UploadedFile::fake()->create('cr_document.pdf', 150, 'application/pdf');

    $compRequest = Request::create('/api/v1/register', 'POST', [
        'user_type' => 0,
        'seller_type' => 2,
        'name' => 'Fun Events Co.',
        'company_name' => 'Fun Events Company LLC',
        'cr_number' => $compCrNumber,
        'contact_person_name' => 'Khalid Manager',
        'contact_person_email' => 'khalid@funevents.sa',
        'contact_person_phone' => '+966509988776',
        'email' => $compEmail,
        'username' => $compUsername,
        'phone' => $compPhone,
        'password' => 'Password123!',
        'service_city' => 2,
        'service_area' => 2,
        'country_id' => 2,
        'category_ids' => [$testCategory->id],
        'terms_conditions' => 1,
        'terms_version_id' => $sellerVersion->id,
        'terms_language' => 'ar',
    ], [], [
        'cr_document' => $fakeCrDoc,
    ]);

    $compResponse = $controller->register($compRequest);
    $compData = json_decode($compResponse->getContent(), true);

    assertCheck(in_array($compResponse->getStatusCode(), [200, 201], true), 'Company seller registration returns HTTP 200/201 success');
    $compUser = User::find($compData['users']['id']);
    assertCheck($compUser->seller_type === 2, 'Company seller record has seller_type = 2 (Company)');
    assertCheck($compUser->business_registration === $compCrNumber, 'Company seller stores CR number in business_registration');

    $compVerify = SellerVerify::where('seller_id', $compUser->id)->first();
    assertCheck(!empty($compVerify) && $compVerify->cr_number === $compCrNumber, 'SellerVerify stores company CR number and verification profile');
    assertCheck($compVerify->contact_person_name === 'Khalid Manager', 'SellerVerify stores contact person details');

    // ---------------------------------------------------------------------
    // 4. DUPLICATE ACCOUNT DEFENSES
    // ---------------------------------------------------------------------
    echo PHP_EOL . "--- 4. Duplicate Account Protection ---" . PHP_EOL;

    // Duplicate email
    $dupEmailReq = Request::create('/api/v1/register', 'POST', [
        'name' => 'Duplicate Email User',
        'email' => $buyerEmail, // existing buyer email
        'username' => 'unique_user_' . uniqid(),
        'phone' => '+96650' . rand(1000000, 9999999),
        'password' => 'Password123!',
        'service_city' => 2,
        'service_area' => 2,
        'country_id' => 2,
        'terms_conditions' => 1,
        'terms_version_id' => $buyerVersion->id,
        'terms_language' => 'ar',
    ]);
    try {
        $controller->register($dupEmailReq);
        assertCheck(false, 'Duplicate email should throw validation exception');
    } catch (\Illuminate\Validation\ValidationException $e) {
        assertCheck(isset($e->errors()['email']), 'Registration with existing email is rejected by validator');
    }

    // Duplicate CR Number
    $dupCrReq = Request::create('/api/v1/register', 'POST', [
        'user_type' => 0,
        'seller_type' => 2,
        'name' => 'Another Company',
        'company_name' => 'Another Company LLC',
        'cr_number' => $compCrNumber, // existing CR
        'contact_person_name' => 'Person',
        'contact_person_email' => 'person@another.sa',
        'contact_person_phone' => '+966501112233',
        'email' => 'another_' . uniqid() . '@funmoments.sa',
        'username' => 'another_' . uniqid(),
        'phone' => '+96650' . rand(1000000, 9999999),
        'password' => 'Password123!',
        'service_city' => 2,
        'service_area' => 2,
        'country_id' => 2,
        'category_ids' => [$testCategory->id],
        'terms_conditions' => 1,
        'terms_version_id' => $sellerVersion->id,
        'terms_language' => 'ar',
    ], [], [
        'cr_document' => $fakeCrDoc,
    ]);
    try {
        $controller->register($dupCrReq);
        assertCheck(false, 'Duplicate CR number should throw validation exception');
    } catch (\Illuminate\Validation\ValidationException $e) {
        assertCheck(isset($e->errors()['cr_number']), 'Registration with existing CR number is rejected by validator');
    }

    // ---------------------------------------------------------------------
    // 5. OTP EMAIL DISPATCH & VERIFICATION FOR REGISTERED USER
    // ---------------------------------------------------------------------
    echo PHP_EOL . "--- 5. OTP Dispatch & Verification Flow ---" . PHP_EOL;

    Mail::fake();
    $otpReq = Request::create('/api/v1/send-otp-in-mail', 'POST', [
        'email' => $buyerEmail,
    ]);
    $otpResponse = $controller->sendOTP($otpReq);
    $otpData = json_decode($otpResponse->getContent(), true);

    assertCheck(in_array($otpResponse->getStatusCode(), [200, 201], true), 'POST /api/v1/send-otp-in-mail returns HTTP 200/201 success');

    $dispatchedMails = Mail::sent(App\Mail\BasicMail::class, function ($m) use ($buyerEmail) {
        return $m->hasTo($buyerEmail);
    });
    assertCheck(count($dispatchedMails) === 1, 'BasicMail OTP was sent to registered buyer address');

    // Extract OTP code from dispatched mail
    preg_match('/\b\d{4}\b/', $dispatchedMails[0]->data['message'], $m);
    $buyerOtp = $m[0] ?? null;
    assertCheck(!empty($buyerOtp), 'Extracted 4-digit OTP code from mail body');

    // Verify OTP
    $verifyReq = Request::create('/api/v1/verify-email-otp', 'POST', [
        'email' => $buyerEmail,
        'otp' => $buyerOtp,
    ]);
    $verifyResponse = $controller->verifyEmailOtp($verifyReq);
    assertCheck(in_array($verifyResponse->getStatusCode(), [200, 201], true), 'POST /api/v1/verify-email-otp returns HTTP 200/201 success');
    assertCheck($buyerUser->fresh()->email_verified == 1, 'Buyer email_verified is updated to 1');

} finally {
    DB::rollBack();
}

echo PHP_EOL . "==================================================================" . PHP_EOL;
echo " TEST SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
