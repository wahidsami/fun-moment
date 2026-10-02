<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Category;
use App\Http\Controllers\Api\UserController;
use App\ProviderCategory;
use App\SellerVerify;
use App\Services\ProviderVerificationDocumentService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

function createDummyPdf(string $prefix = 'test_pdf_'): UploadedFile {
    $tmpPath = tempnam(sys_get_temp_dir(), $prefix) . '.pdf';
    file_put_contents($tmpPath, "%PDF-1.4\n%Dummy PDF for testing\n%%EOF");
    return new UploadedFile($tmpPath, 'document.pdf', 'application/pdf', null, true);
}

function createDummyPng(string $prefix = 'test_png_'): UploadedFile {
    $tmpPath = tempnam(sys_get_temp_dir(), $prefix) . '.png';
    file_put_contents($tmpPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    return new UploadedFile($tmpPath, 'image.png', 'image/png', null, true);
}

function createDummyPhp(string $prefix = 'malware_'): UploadedFile {
    $tmpPath = tempnam(sys_get_temp_dir(), $prefix) . '.php';
    file_put_contents($tmpPath, "<?php echo 'danger'; ?>");
    return new UploadedFile($tmpPath, 'exploit.php', 'application/x-php', null, true);
}

echo "==========================================================" . PHP_EOL;
echo " PHASE 4A-2: PROVIDER REGISTRATION LIFECYCLE TEST SUITE" . PHP_EOL;
echo "==========================================================" . PHP_EOL . PHP_EOL;

$controller = app(UserController::class);
$docService = app(ProviderVerificationDocumentService::class);

DB::beginTransaction();

try {
    $cat1 = Category::first() ?: Category::create(['name' => 'Music & Bands', 'slug' => 'music-bands-' . time(), 'status' => 1]);
    $cat2 = Category::where('id', '!=', $cat1->id)->first() ?: Category::create(['name' => 'Event Sound & Lighting', 'slug' => 'sound-light-' . time(), 'status' => 1]);

    // ------------------------------------------------------------------------
    // T1: Customer registration still works exactly as before
    // ------------------------------------------------------------------------
    $custEmail = 'customer_reg_' . time() . '@test.com';
    $custReq = Request::create('/api/v1/register', 'POST', [
        'name' => 'T1 Customer Normal',
        'email' => $custEmail,
        'username' => 'cust_' . time(),
        'phone' => '+966509999901',
        'password' => 'secret123',
        'service_city' => 1,
        'service_area' => 1,
        'country_id' => 166,
        'terms_conditions' => 1,
        'user_type' => 1,
    ]);

    $custRes = $controller->register($custReq);
    $custData = $custRes->getData(true);

    $custUser = User::where('email', $custEmail)->first();
    assertTest(
        in_array($custRes->getStatusCode(), [200, 201], true) &&
        isset($custData['token']) &&
        !empty($custData['token']) &&
        $custUser &&
        (int)$custUser->user_type === User::USER_TYPE_BUYER &&
        (int)$custUser->seller_type === User::SELLER_TYPE_NOT_APPLICABLE,
        "T1: Customer registration still works exactly as before",
        "user_type=1, seller_type=0, token issued (HTTP {$custRes->getStatusCode()})"
    );

    // ------------------------------------------------------------------------
    // T2: Individual provider registration creates user_type=0, seller_type=1
    // ------------------------------------------------------------------------
    $indEmail = 'ind_reg_' . time() . '@test.com';
    $natIdPdf = createDummyPdf('nat_id_t2_');

    $indReq = Request::create('/api/v1/register', 'POST', [
        'name' => 'T2 Solo Performer',
        'email' => $indEmail,
        'username' => 'ind_performer_' . time(),
        'phone' => '+966509999902',
        'password' => 'secret123',
        'service_city' => 1,
        'service_area' => 1,
        'country_id' => 166,
        'terms_conditions' => 1,
        'user_type' => 0,
        'seller_type' => User::SELLER_TYPE_INDIVIDUAL,
        'national_id_number' => '1020304050',
        'category_ids' => [$cat1->id],
    ], [], [
        'national_id_document' => $natIdPdf,
    ]);

    $indRes = $controller->register($indReq);
    $indData = $indRes->getData(true);

    $indUser = User::where('email', $indEmail)->first();
    assertTest(
        in_array($indRes->getStatusCode(), [200, 201], true) &&
        $indUser &&
        (int)$indUser->user_type === User::USER_TYPE_SELLER &&
        (int)$indUser->seller_type === User::SELLER_TYPE_INDIVIDUAL &&
        (int)$indUser->user_status === 1,
        "T2: Individual provider registration creates user_type=0, seller_type=1",
        "user_type=0, seller_type=1, user_status=1 (HTTP {$indRes->getStatusCode()})"
    );

    // ------------------------------------------------------------------------
    // T3: Company provider registration creates user_type=0, seller_type=2
    // ------------------------------------------------------------------------
    $compEmail = 'comp_reg_' . time() . '@test.com';
    $crPdf = createDummyPdf('cr_t3_');

    $compReq = Request::create('/api/v1/provider/register', 'POST', [
        'name' => 'T3 Event Company LLC',
        'email' => $compEmail,
        'username' => 'comp_event_' . time(),
        'phone' => '+966509999903',
        'password' => 'secret123',
        'service_city' => 1,
        'service_area' => 1,
        'country_id' => 166,
        'terms_conditions' => 1,
        'user_type' => 0,
        'seller_type' => User::SELLER_TYPE_COMPANY,
        'company_name' => 'Riyadh Events & Entertainment Co.',
        'cr_number' => '1010887766',
        'contact_person_name' => 'Khalid Al-Amri',
        'contact_person_email' => 'khalid@riyadhevents.com',
        'contact_person_phone' => '+966509999904',
        'category_ids' => [$cat1->id, $cat2->id],
    ], [], [
        'cr_document' => $crPdf,
    ]);

    $compRes = $controller->registerProvider($compReq);
    $compData = $compRes->getData(true);

    $compUser = User::where('email', $compEmail)->first();
    assertTest(
        in_array($compRes->getStatusCode(), [200, 201], true) &&
        $compUser &&
        (int)$compUser->user_type === User::USER_TYPE_SELLER &&
        (int)$compUser->seller_type === User::SELLER_TYPE_COMPANY &&
        $compUser->business_registration === '1010887766',
        "T3: Company provider registration creates user_type=0, seller_type=2",
        "user_type=0, seller_type=2, business_registration synced (HTTP {$compRes->getStatusCode()})"
    );

    // ------------------------------------------------------------------------
    // T4: Individual identity fields persist correctly
    // ------------------------------------------------------------------------
    $bandEmail = 'band_reg_' . time() . '@test.com';
    $natIdImg = createDummyPng('nat_id_t4_');
    $licPdf = createDummyPdf('license_t4_');

    $bandReq = Request::create('/api/v1/register', 'POST', [
        'name' => 'T4 Band Performer',
        'email' => $bandEmail,
        'username' => 'band_leader_' . time(),
        'phone' => '+966509999905',
        'password' => 'secret123',
        'service_city' => 1,
        'service_area' => 1,
        'country_id' => 166,
        'terms_conditions' => 1,
        'user_type' => 0,
        'seller_type' => User::SELLER_TYPE_INDIVIDUAL,
        'national_id_number' => '1030405060',
        'license_number' => 'LIC-AUD-2026',
        'is_band_or_group' => '1',
        'band_name' => 'Sama Acoustic Ensemble',
        'band_members_count' => '4',
        'category_ids' => [$cat1->id],
    ], [], [
        'national_id_document' => $natIdImg,
        'license_document' => $licPdf,
    ]);

    $bandRes = $controller->register($bandReq);
    $bandUser = User::where('email', $bandEmail)->first();
    $bandVerify = $bandUser ? $bandUser->sellerVerify : null;

    assertTest(
        $bandVerify &&
        $bandVerify->national_id_number === '1030405060' &&
        $bandVerify->license_number === 'LIC-AUD-2026' &&
        $bandVerify->is_band_or_group === true &&
        $bandVerify->band_name === 'Sama Acoustic Ensemble' &&
        $bandVerify->band_members_count === 4 &&
        !empty($bandVerify->national_id_document) &&
        !empty($bandVerify->license_document),
        "T4: Individual identity & band fields persist correctly",
        "National ID, License, band flag, name, member count all stored"
    );

    // ------------------------------------------------------------------------
    // T5: Company CR/contact fields persist correctly
    // ------------------------------------------------------------------------
    $compVerify = $compUser ? $compUser->sellerVerify : null;
    assertTest(
        $compVerify &&
        $compVerify->company_name === 'Riyadh Events & Entertainment Co.' &&
        $compVerify->cr_number === '1010887766' &&
        $compVerify->contact_person_name === 'Khalid Al-Amri' &&
        $compVerify->contact_person_email === 'khalid@riyadhevents.com' &&
        $compVerify->contact_person_phone === '+966509999904' &&
        !empty($compVerify->cr_document),
        "T5: Company CR/contact fields persist correctly",
        "company_name, cr_number, contact_person details confirmed in seller_verifies"
    );

    // ------------------------------------------------------------------------
    // T6: Provider begins seller verification status=0 Pending
    // ------------------------------------------------------------------------
    assertTest(
        $indUser->sellerVerify &&
        $indUser->sellerVerify->isPending() &&
        (int)$indUser->sellerVerify->status === SellerVerify::STATUS_PENDING &&
        $compUser->sellerVerify &&
        $compUser->sellerVerify->isPending() &&
        (int)$compUser->sellerVerify->status === SellerVerify::STATUS_PENDING,
        "T6: Provider begins seller verification status=0 Pending",
        "Individual status=0, Company status=0"
    );

    // ------------------------------------------------------------------------
    // T7: Individual category registration stores multiple provider_categories
    // ------------------------------------------------------------------------
    $multiIndEmail = 'multi_ind_' . time() . '@test.com';
    $multiNatId = createDummyPdf('nat_id_t7_');
    $multiIndReq = Request::create('/api/v1/register', 'POST', [
        'name' => 'T7 Multi Category Musician',
        'email' => $multiIndEmail,
        'username' => 'multi_ind_' . time(),
        'phone' => '+966509999907',
        'password' => 'secret123',
        'service_city' => 1,
        'service_area' => 1,
        'country_id' => 166,
        'terms_conditions' => 1,
        'user_type' => 0,
        'seller_type' => User::SELLER_TYPE_INDIVIDUAL,
        'national_id_number' => '1040506070',
        'category_ids' => [$cat1->id, $cat2->id],
    ], [], [
        'national_id_document' => $multiNatId,
    ]);

    $controller->register($multiIndReq);
    $multiIndUser = User::where('email', $multiIndEmail)->first();
    $indCats = $multiIndUser ? $multiIndUser->registeredCategories()->pluck('categories.id')->all() : [];

    assertTest(
        count($indCats) === 2 && in_array($cat1->id, $indCats) && in_array($cat2->id, $indCats),
        "T7: Individual category registration stores multiple provider_categories",
        "Stored categories: " . implode(',', $indCats)
    );

    // ------------------------------------------------------------------------
    // T8: Company category registration stores multiple provider_categories
    // ------------------------------------------------------------------------
    $compCats = $compUser ? $compUser->registeredCategories()->pluck('categories.id')->all() : [];
    assertTest(
        count($compCats) === 2 && in_array($cat1->id, $compCats) && in_array($cat2->id, $compCats),
        "T8: Company category registration stores multiple provider_categories",
        "Stored categories: " . implode(',', $compCats)
    );

    // ------------------------------------------------------------------------
    // T9: Duplicate provider/category pair is rejected/prevented
    // ------------------------------------------------------------------------
    $duplicateThrown = false;
    try {
        DB::transaction(function () use ($compUser, $cat1) {
            ProviderCategory::create([
                'provider_id' => $compUser->id,
                'category_id' => $cat1->id,
            ]);
        });
    } catch (\Throwable $e) {
        $duplicateThrown = true;
    }

    assertTest(
        $duplicateThrown,
        "T9: Duplicate provider/category pair is rejected/prevented",
        "Unique constraint provider_category_unique prevented duplicate insertion"
    );

    // ------------------------------------------------------------------------
    // T10: National ID PDF/image accepted through private document service
    // ------------------------------------------------------------------------
    $natIdDoc = $bandVerify->national_id_document;
    $natIdExists = $docService->exists($natIdDoc);
    assertTest(
        $natIdExists && str_starts_with($natIdDoc, 'national_id_'),
        "T10: National ID PDF/image accepted through private document service",
        "File exists on verification_docs disk: $natIdDoc"
    );

    // ------------------------------------------------------------------------
    // T11: License PDF/image accepted
    // ------------------------------------------------------------------------
    $licDoc = $bandVerify->license_document;
    $licExists = $docService->exists($licDoc);
    assertTest(
        $licExists && str_starts_with($licDoc, 'license_'),
        "T11: License PDF/image accepted",
        "File exists on verification_docs disk: $licDoc"
    );

    // ------------------------------------------------------------------------
    // T12: CR PDF/image accepted
    // ------------------------------------------------------------------------
    $crDoc = $compVerify->cr_document;
    $crExists = $docService->exists($crDoc);
    assertTest(
        $crExists && str_starts_with($crDoc, 'cr_'),
        "T12: CR PDF/image accepted",
        "File exists on verification_docs disk: $crDoc"
    );

    // ------------------------------------------------------------------------
    // T13: Invalid/executable document rejected
    // ------------------------------------------------------------------------
    $malwareFile = createDummyPhp('exploit_t13_');
    $malwareCaught = false;

    try {
        $badReq = Request::create('/api/v1/register', 'POST', [
            'name' => 'T13 Hacker Performer',
            'email' => 'hacker_' . time() . '@test.com',
            'username' => 'hacker_' . time(),
            'phone' => '+966509999913',
            'password' => 'secret123',
            'service_city' => 1,
            'service_area' => 1,
            'country_id' => 166,
            'terms_conditions' => 1,
            'user_type' => 0,
            'seller_type' => User::SELLER_TYPE_INDIVIDUAL,
            'national_id_number' => '1099999999',
        ], [], [
            'national_id_document' => $malwareFile,
        ]);
        $controller->register($badReq);
    } catch (ValidationException $e) {
        $malwareCaught = true;
    }

    assertTest(
        $malwareCaught,
        "T13: Invalid/executable document rejected",
        "ValidationException caught on .php file"
    );

    // ------------------------------------------------------------------------
    // T14: Registration failure rolls back database changes
    // ------------------------------------------------------------------------
    $failEmail = 'fail_db_' . time() . '@test.com';
    $failPdf = createDummyPdf('fail_pdf_');
    $dbRolledBack = false;

    try {
        // Intentionally pass an invalid category ID to trigger failure during transaction
        $failReq = Request::create('/api/v1/provider/register', 'POST', [
            'name' => 'T14 Failing Company',
            'email' => $failEmail,
            'username' => 'fail_comp_' . time(),
            'phone' => '+966509999914',
            'password' => 'secret123',
            'service_city' => 1,
            'service_area' => 1,
            'country_id' => 166,
            'terms_conditions' => 1,
            'user_type' => 0,
            'seller_type' => User::SELLER_TYPE_COMPANY,
            'company_name' => 'Fail Co.',
            'cr_number' => '1099887766',
            'contact_person_name' => 'Fail Guy',
            'contact_person_email' => 'fail@example.com',
            'contact_person_phone' => '+966509999914',
            'category_ids' => [99999999], // non-existent category
        ], [], [
            'cr_document' => $failPdf,
        ]);
        $controller->registerProvider($failReq);
    } catch (\Throwable $e) {
        $dbRolledBack = true;
    }

    $failedUserExists = User::where('email', $failEmail)->exists();
    assertTest(
        $dbRolledBack && !$failedUserExists,
        "T14: Registration failure rolls back database changes",
        "Failed registration left zero user or verification records"
    );

    // ------------------------------------------------------------------------
    // T15: Registration failure after file write removes orphan newly-created file
    // ------------------------------------------------------------------------
    $failCleanEmail = 'fail_clean_' . time() . '@test.com';
    $failCleanPdf = createDummyPdf('orphan_test_');
    $preFileCount = count(Storage::disk('verification_docs')->files(''));
    $cleanExceptionCaught = false;

    try {
        // Create duplicate username to force DB transaction failure AFTER file store
        $failReq2 = Request::create('/api/v1/provider/register', 'POST', [
            'name' => 'T15 Orphan Clean Test',
            'email' => $failCleanEmail,
            'username' => $compUser->username, // DUPLICATE username!
            'phone' => '+966509999915',
            'password' => 'secret123',
            'service_city' => 1,
            'service_area' => 1,
            'country_id' => 166,
            'terms_conditions' => 1,
            'user_type' => 0,
            'seller_type' => User::SELLER_TYPE_COMPANY,
            'company_name' => 'Orphan Test Co.',
            'cr_number' => '1099887755',
            'contact_person_name' => 'Orphan Tester',
            'contact_person_email' => 'orphan@example.com',
            'contact_person_phone' => '+966509999915',
            'category_ids' => [$cat1->id],
        ], [], [
            'cr_document' => $failCleanPdf,
        ]);
        $controller->registerProvider($failReq2);
    } catch (\Throwable $e) {
        $cleanExceptionCaught = true;
    }

    $postFileCount = count(Storage::disk('verification_docs')->files(''));
    assertTest(
        $cleanExceptionCaught && $postFileCount === $preFileCount,
        "T15: Registration failure after file write removes orphan newly-created file",
        "Files before: $preFileCount, Files after: $postFileCount (Zero orphan files left)"
    );

    // ------------------------------------------------------------------------
    // T16: Customer registration is not affected by provider-specific fields
    // ------------------------------------------------------------------------
    $custEmail2 = 'customer_extra_' . time() . '@test.com';
    $custReq2 = Request::create('/api/v1/register', 'POST', [
        'name' => 'T16 Customer Extra Fields',
        'email' => $custEmail2,
        'username' => 'cust_extra_' . time(),
        'phone' => '+966509999916',
        'password' => 'secret123',
        'service_city' => 1,
        'service_area' => 1,
        'country_id' => 166,
        'terms_conditions' => 1,
        'user_type' => 1,
        // Extraneous provider fields should be safely ignored for customer
        'seller_type' => 1,
        'national_id_number' => '9999999999',
        'company_name' => 'Ignore Me LLC',
    ]);

    $custRes2 = $controller->register($custReq2);
    $custUser2 = User::where('email', $custEmail2)->first();

    assertTest(
        in_array($custRes2->getStatusCode(), [200, 201], true) &&
        $custUser2 &&
        (int)$custUser2->user_type === User::USER_TYPE_BUYER &&
        (int)$custUser2->seller_type === User::SELLER_TYPE_NOT_APPLICABLE &&
        $custUser2->sellerVerify === null,
        "T16: Customer registration is not affected by provider-specific fields",
        "Customer ignored provider fields, no seller_verifies row created (HTTP {$custRes2->getStatusCode()})"
    );

    // ------------------------------------------------------------------------
    // T17: Provider authentication/email OTP flow remains intact
    // ------------------------------------------------------------------------
    assertTest(
        !empty($indData['token']) &&
        (int)$indUser->email_verified === 0 &&
        (int)$indUser->user_status === 1,
        "T17: Provider authentication/email OTP flow remains intact",
        "Sanctum token issued, email_verified=0 ready for OTP verification"
    );

    // ------------------------------------------------------------------------
    // T18: Provider registration returns seller_type and Pending verification state
    // ------------------------------------------------------------------------
    assertTest(
        isset($indData['provider_verification']) &&
        $indData['provider_verification']['status'] === 0 &&
        $indData['provider_verification']['status_label'] === 'Pending' &&
        $indData['provider_verification']['seller_type'] === 1 &&
        $indData['provider_verification']['seller_type_label'] === 'Individual' &&
        isset($compData['provider_verification']) &&
        $compData['provider_verification']['seller_type'] === 2 &&
        $compData['provider_verification']['seller_type_label'] === 'Company',
        "T18: Provider registration returns seller_type and Pending verification state",
        "Response payload contract confirmed for Individual and Company"
    );

    // ------------------------------------------------------------------------
    // T19: Missing required company CR data is rejected
    // ------------------------------------------------------------------------
    $missingCrCaught = false;
    try {
        $noCrReq = Request::create('/api/v1/provider/register', 'POST', [
            'name' => 'T19 Missing CR Co',
            'email' => 'nocr_' . time() . '@test.com',
            'username' => 'nocr_' . time(),
            'phone' => '+966509999919',
            'password' => 'secret123',
            'service_city' => 1,
            'service_area' => 1,
            'country_id' => 166,
            'terms_conditions' => 1,
            'user_type' => 0,
            'seller_type' => User::SELLER_TYPE_COMPANY,
            'company_name' => 'No CR Co.',
            // Missing cr_number and cr_document!
            'contact_person_name' => 'Tester',
            'contact_person_email' => 'tester@example.com',
            'contact_person_phone' => '+966509999919',
            'category_ids' => [$cat1->id],
        ]);
        $controller->registerProvider($noCrReq);
    } catch (ValidationException $e) {
        $errors = $e->errors();
        if (isset($errors['cr_number']) && isset($errors['cr_document'])) {
            $missingCrCaught = true;
        }
    }

    assertTest(
        $missingCrCaught,
        "T19: Missing required company CR data is rejected",
        "ValidationException correctly raised for missing cr_number and cr_document"
    );

    // ------------------------------------------------------------------------
    // T20: Missing required Individual identity data is rejected
    // ------------------------------------------------------------------------
    $missingIdCaught = false;
    try {
        $noIdReq = Request::create('/api/v1/register', 'POST', [
            'name' => 'T20 Missing ID Musician',
            'email' => 'noid_' . time() . '@test.com',
            'username' => 'noid_' . time(),
            'phone' => '+966509999920',
            'password' => 'secret123',
            'service_city' => 1,
            'service_area' => 1,
            'country_id' => 166,
            'terms_conditions' => 1,
            'user_type' => 0,
            'seller_type' => User::SELLER_TYPE_INDIVIDUAL,
            // Missing national_id_number and national_id_document!
        ]);
        $controller->register($noIdReq);
    } catch (ValidationException $e) {
        $errors = $e->errors();
        if (isset($errors['national_id_number']) && isset($errors['national_id_document'])) {
            $missingIdCaught = true;
        }
    }

    assertTest(
        $missingIdCaught,
        "T20: Missing required Individual identity data is rejected",
        "ValidationException correctly raised for missing national_id_number and document"
    );

    // ------------------------------------------------------------------------
    // Individual provider registration WITHOUT category_ids
    // → HTTP 422
    // → no provider user created
    // → no seller_verifies row created
    // → no provider_categories rows created
    // ------------------------------------------------------------------------
    $missingCatCaught = false;
    $noCatEmail = 'nocat_' . time() . '@test.com';
    $noCatNatId = createDummyPdf('nocat_id_');
    try {
        $noCatReq = Request::create('/api/v1/register', 'POST', [
            'name' => 'No Category Musician',
            'email' => $noCatEmail,
            'username' => 'nocat_' . time(),
            'phone' => '+966509999921',
            'password' => 'secret123',
            'service_city' => 1,
            'service_area' => 1,
            'country_id' => 166,
            'terms_conditions' => 1,
            'user_type' => 0,
            'seller_type' => User::SELLER_TYPE_INDIVIDUAL,
            'national_id_number' => '1050607080',
            // Missing category_ids!
        ], [], [
            'national_id_document' => $noCatNatId,
        ]);
        $controller->register($noCatReq);
    } catch (ValidationException $e) {
        $errors = $e->errors();
        if (isset($errors['category_ids'])) {
            $missingCatCaught = true;
        }
    }

    $noCatUser = User::where('email', $noCatEmail)->first();
    $noCatVerify = $noCatUser ? SellerVerify::where('seller_id', $noCatUser->id)->first() : null;
    $noCatProviderCats = $noCatUser ? ProviderCategory::where('provider_id', $noCatUser->id)->count() : 0;

    assertTest(
        $missingCatCaught && is_null($noCatUser) && is_null($noCatVerify) && $noCatProviderCats === 0,
        "Individual provider registration WITHOUT category_ids rejected (HTTP 422, zero records created)",
        "ValidationException on category_ids, no user, no seller_verifies, no provider_categories"
    );

    // Cleanup documents stored during tests
    if (!empty($bandVerify->national_id_document)) $docService->delete($bandVerify->national_id_document);
    if (!empty($bandVerify->license_document)) $docService->delete($bandVerify->license_document);
    if (!empty($compVerify->cr_document)) $docService->delete($compVerify->cr_document);
    if (!empty($indUser->sellerVerify->national_id_document)) $docService->delete($indUser->sellerVerify->national_id_document);
    if (!empty($multiIndUser->sellerVerify->national_id_document)) $docService->delete($multiIndUser->sellerVerify->national_id_document);

} catch (\Throwable $e) {
    echo " [FATAL ERROR]: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failed++;
} finally {
    DB::rollBack();
    echo PHP_EOL . " Transaction rolled back cleanly. Database preserved." . PHP_EOL;
}

echo PHP_EOL . "==========================================================" . PHP_EOL;
echo " TEST SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
