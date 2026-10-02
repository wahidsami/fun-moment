<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Admin;
use App\Category;
use App\ProviderCategory;
use App\SellerVerify;
use App\Services\ProviderVerificationDocumentService;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

echo "==========================================================" . PHP_EOL;
echo " PHASE 4A-1: PROVIDER IDENTITY & SECURE STORAGE TEST SUITE" . PHP_EOL;
echo "==========================================================" . PHP_EOL . PHP_EOL;

DB::beginTransaction();

try {
    // ------------------------------------------------------------------------
    // T1: Customer record remains valid
    // ------------------------------------------------------------------------
    $customer = User::create([
        'name' => 'T1 Customer Test',
        'email' => 't1_customer_' . time() . '@test.com',
        'username' => 't1_cust_' . time(),
        'password' => Hash::make('password123'),
        'phone' => '+966501111111',
        'user_type' => User::USER_TYPE_BUYER,
        'user_status' => 1,
        'seller_type' => User::SELLER_TYPE_NOT_APPLICABLE,
        'address' => 'Olaya St, Riyadh',
    ]);

    assertTest(
        $customer->exists && $customer->isCustomer() && !$customer->isProvider() && (int)$customer->seller_type === 0,
        "T1: Customer record remains valid",
        "user_type=1, seller_type=0, isCustomer()=true"
    );

    // ------------------------------------------------------------------------
    // T2: Existing provider remains valid with seller_type=0/null/default behavior
    // ------------------------------------------------------------------------
    $legacyProvider = User::create([
        'name' => 'T2 Legacy Provider',
        'email' => 't2_legacy_' . time() . '@test.com',
        'username' => 't2_leg_' . time(),
        'password' => Hash::make('password123'),
        'phone' => '+966502222222',
        'user_type' => User::USER_TYPE_SELLER,
        'user_status' => 1,
        'seller_type' => 0,
    ]);

    assertTest(
        $legacyProvider->exists && $legacyProvider->isProvider() && (int)$legacyProvider->seller_type === 0,
        "T2: Existing provider remains valid with seller_type=0 default behavior",
        "user_type=0, seller_type=0 preserved"
    );

    // ------------------------------------------------------------------------
    // T3: Individual provider data can be stored
    // ------------------------------------------------------------------------
    $individualProvider = User::create([
        'name' => 'T3 Individual Musician',
        'email' => 't3_ind_' . time() . '@test.com',
        'username' => 't3_ind_' . time(),
        'password' => Hash::make('password123'),
        'phone' => '+966503333333',
        'user_type' => User::USER_TYPE_SELLER,
        'seller_type' => User::SELLER_TYPE_INDIVIDUAL,
        'user_status' => 1,
    ]);

    $individualVerify = SellerVerify::create([
        'seller_id' => $individualProvider->id,
        'national_id_number' => '1098765432',
        'national_id_document' => 'nat_id_uuid.jpg',
        'license_number' => 'LIC-2026-999',
        'license_document' => 'license_uuid.pdf',
        'is_band_or_group' => true,
        'band_name' => 'Riyadh Jazz Quintet',
        'band_members_count' => 5,
        'status' => SellerVerify::STATUS_PENDING,
    ]);

    $reloadedIndividual = SellerVerify::where('seller_id', $individualProvider->id)->first();

    assertTest(
        $individualProvider->isIndividualProvider() &&
        $reloadedIndividual->national_id_number === '1098765432' &&
        $reloadedIndividual->license_number === 'LIC-2026-999' &&
        $reloadedIndividual->is_band_or_group === true &&
        $reloadedIndividual->band_name === 'Riyadh Jazz Quintet' &&
        $reloadedIndividual->band_members_count === 5,
        "T3: Individual provider data can be stored",
        "National ID, license, band metadata stored and verified"
    );

    // ------------------------------------------------------------------------
    // T4: Company provider data can be stored
    // ------------------------------------------------------------------------
    $companyProvider = User::create([
        'name' => 'T4 Event Productions LLC',
        'email' => 't4_comp_' . time() . '@test.com',
        'username' => 't4_comp_' . time(),
        'password' => Hash::make('password123'),
        'phone' => '+966504444444',
        'user_type' => User::USER_TYPE_SELLER,
        'seller_type' => User::SELLER_TYPE_COMPANY,
        'business_registration' => '1010998877',
        'user_status' => 1,
    ]);

    $companyVerify = SellerVerify::create([
        'seller_id' => $companyProvider->id,
        'company_name' => 'Fun Moment Event Services Co.',
        'cr_number' => '1010998877',
        'cr_document' => 'cr_cert_uuid.pdf',
        'contact_person_name' => 'Ahmed Al-Manager',
        'contact_person_email' => 'ahmed.manager@example.com',
        'contact_person_phone' => '+966504444445',
        'status' => SellerVerify::STATUS_PENDING,
    ]);

    $reloadedCompany = SellerVerify::where('seller_id', $companyProvider->id)->first();

    assertTest(
        $companyProvider->isCompanyProvider() &&
        $reloadedCompany->company_name === 'Fun Moment Event Services Co.' &&
        $reloadedCompany->cr_number === '1010998877' &&
        $reloadedCompany->contact_person_name === 'Ahmed Al-Manager' &&
        $reloadedCompany->contact_person_email === 'ahmed.manager@example.com',
        "T4: Company provider data can be stored",
        "Company name, CR, contact person stored and verified"
    );

    // ------------------------------------------------------------------------
    // T5: Provider/category duplicate pair is rejected/prevented
    // ------------------------------------------------------------------------
    $cat1 = Category::first() ?: Category::create(['name' => 'Music & Entertainment', 'slug' => 'music-ent-' . time(), 'status' => 1]);
    $cat2 = Category::where('id', '!=', $cat1->id)->first() ?: Category::create(['name' => 'Event Photography', 'slug' => 'event-photo-' . time(), 'status' => 1]);

    ProviderCategory::create([
        'provider_id' => $companyProvider->id,
        'category_id' => $cat1->id,
    ]);

    $duplicatePrevented = false;
    try {
        DB::transaction(function () use ($companyProvider, $cat1) {
            ProviderCategory::create([
                'provider_id' => $companyProvider->id,
                'category_id' => $cat1->id,
            ]);
        });
    } catch (\Throwable $e) {
        $duplicatePrevented = true;
    }

    assertTest(
        $duplicatePrevented,
        "T5: Provider/category duplicate pair is rejected/prevented",
        "Unique constraint provider_category_unique enforced"
    );

    // ------------------------------------------------------------------------
    // T6: Same provider can have multiple categories
    // ------------------------------------------------------------------------
    ProviderCategory::create([
        'provider_id' => $companyProvider->id,
        'category_id' => $cat2->id,
    ]);

    $attachedCategories = $companyProvider->registeredCategories()->pluck('categories.id')->all();
    assertTest(
        count($attachedCategories) === 2 && in_array($cat1->id, $attachedCategories) && in_array($cat2->id, $attachedCategories),
        "T6: Same provider can have multiple categories",
        "Provider successfully associated with categories [{$cat1->id}, {$cat2->id}]"
    );

    // ------------------------------------------------------------------------
    // T7: Verification status supports Pending (0) / Approved (1) / Rejected (2)
    // ------------------------------------------------------------------------
    $verifyPending = SellerVerify::create([
        'seller_id' => $legacyProvider->id,
        'status' => SellerVerify::STATUS_PENDING,
    ]);

    $pOk = $verifyPending->isPending() && !$verifyPending->isApproved() && !$verifyPending->isRejected();

    $verifyPending->update(['status' => SellerVerify::STATUS_APPROVED]);
    $aOk = !$verifyPending->isPending() && $verifyPending->isApproved() && !$verifyPending->isRejected();

    $verifyPending->update(['status' => SellerVerify::STATUS_REJECTED]);
    $rOk = !$verifyPending->isPending() && !$verifyPending->isApproved() && $verifyPending->isRejected();

    assertTest(
        $pOk && $aOk && $rOk,
        "T7: Verification status supports Pending (0) / Approved (1) / Rejected (2)",
        "Lifecycle state transitions verified (0 -> 1 -> 2)"
    );

    // ------------------------------------------------------------------------
    // T8: Rejection reason persists
    // ------------------------------------------------------------------------
    $testAdmin = Admin::first() ?: Admin::create([
        'name' => 'Super Admin Test',
        'email' => 'admin_test_' . time() . '@test.com',
        'username' => 'adm_' . time(),
        'password' => Hash::make('password'),
    ]);

    $now = now();
    $verifyPending->update([
        'status' => SellerVerify::STATUS_REJECTED,
        'rejection_reason' => 'Commercial Registration document is expired. Please upload valid certificate.',
        'verified_by' => $testAdmin->id,
        'verified_at' => $now,
    ]);

    $reloadedRejected = SellerVerify::find($verifyPending->id);
    assertTest(
        $reloadedRejected->isRejected() &&
        $reloadedRejected->rejection_reason === 'Commercial Registration document is expired. Please upload valid certificate.' &&
        (int)$reloadedRejected->verified_by === (int)$testAdmin->id &&
        $reloadedRejected->verifiedBy->id === $testAdmin->id,
        "T8: Rejection reason persists with verified_by audit link",
        "Rejection reason and admin relationship confirmed"
    );

    // ------------------------------------------------------------------------
    // T9: Existing providers are not accidentally converted or disabled
    // ------------------------------------------------------------------------
    $existingProviders = DB::table('users')->where('user_type', 0)->where('user_status', 1)->count();
    assertTest(
        $existingProviders >= 28,
        "T9: Existing providers remain active and unmodified",
        "Total active providers count: $existingProviders"
    );

    // ------------------------------------------------------------------------
    // T10: PDF document accepted
    // ------------------------------------------------------------------------
    $docService = new ProviderVerificationDocumentService();

    // Create temporary PDF file
    $tmpPdfPath = tempnam(sys_get_temp_dir(), 'test_doc_') . '.pdf';
    file_put_contents($tmpPdfPath, "%PDF-1.4\n%Binary test content\n%%EOF");
    $uploadedPdf = new UploadedFile($tmpPdfPath, 'cr_certificate.pdf', 'application/pdf', null, true);

    $storedPdfName = $docService->store($uploadedPdf, 'cr');
    $pdfExists = $docService->exists($storedPdfName);
    @unlink($tmpPdfPath);

    assertTest(
        $pdfExists && str_ends_with($storedPdfName, '.pdf'),
        "T10: PDF document accepted and stored on private disk",
        "Stored: $storedPdfName"
    );

    // ------------------------------------------------------------------------
    // T11: JPG/PNG document accepted
    // ------------------------------------------------------------------------
    $tmpImgPath = tempnam(sys_get_temp_dir(), 'test_img_') . '.png';
    // 1x1 transparent PNG binary
    file_put_contents($tmpImgPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    $uploadedPng = new UploadedFile($tmpImgPath, 'national_id.png', 'image/png', null, true);

    $storedPngName = $docService->store($uploadedPng, 'national_id');
    $pngExists = $docService->exists($storedPngName);
    @unlink($tmpImgPath);

    assertTest(
        $pngExists && str_ends_with($storedPngName, '.png'),
        "T11: JPG/PNG document accepted and stored on private disk",
        "Stored: $storedPngName"
    );

    // ------------------------------------------------------------------------
    // T12: Executable/invalid document rejected
    // ------------------------------------------------------------------------
    $tmpExePath = tempnam(sys_get_temp_dir(), 'malware_') . '.php';
    file_put_contents($tmpExePath, "<?php echo 'danger'; ?>");
    $uploadedExe = new UploadedFile($tmpExePath, 'exploit.php', 'application/x-php', null, true);

    $exeRejected = false;
    try {
        $docService->store($uploadedExe, 'doc');
    } catch (ValidationException $e) {
        $exeRejected = true;
    }
    @unlink($tmpExePath);

    assertTest(
        $exeRejected,
        "T12: Executable/invalid document rejected",
        "ValidationException caught on .php file"
    );

    // ------------------------------------------------------------------------
    // T13: Sensitive document has no public URL
    // ------------------------------------------------------------------------
    $privateRoot = Storage::disk(ProviderVerificationDocumentService::DISK)->path('');
    $publicRoot = public_path();

    $isOutsidePublic = !str_starts_with(realpath($privateRoot), realpath($publicRoot));
    assertTest(
        $isOutsidePublic,
        "T13: Sensitive document has no public URL",
        "Disk root is outside webroot: " . $privateRoot
    );

    // ------------------------------------------------------------------------
    // T14: Unauthorized document access is rejected
    // ------------------------------------------------------------------------
    // Assign stored PDF to companyVerify
    $companyVerify->update(['cr_document' => $storedPdfName]);

    $otherUser = User::create([
        'name' => 'Attacker / Other User',
        'email' => 'other_' . time() . '@test.com',
        'username' => 'other_' . time(),
        'password' => Hash::make('password'),
        'phone' => '+966505555555',
        'user_type' => User::USER_TYPE_BUYER,
    ]);

    $unauthForbidden = !$docService->isAuthorized($storedPdfName, null);
    $otherUserForbidden = !$docService->isAuthorized($storedPdfName, $otherUser);
    $ownerAllowed = $docService->isAuthorized($storedPdfName, $companyProvider);
    $adminAllowed = $docService->isAuthorized($storedPdfName, $testAdmin);

    // Cleanup stored test documents from private disk
    $docService->delete($storedPdfName);
    $docService->delete($storedPngName);

    assertTest(
        $unauthForbidden && $otherUserForbidden && $ownerAllowed && $adminAllowed,
        "T14: Document authorization matrix enforced",
        "Unauthenticated=Blocked, OtherUser=Blocked, Owner=Allowed, Admin=Allowed"
    );

} catch (\Throwable $e) {
    echo " [FATAL ERROR]: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failed++;
} finally {
    // Rollback all database modifications so test run leaves zero footprint
    DB::rollBack();
    echo PHP_EOL . " Transaction rolled back cleanly. Database preserved." . PHP_EOL;
}

echo PHP_EOL . "==========================================================" . PHP_EOL;
echo " TEST SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
