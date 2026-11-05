<?php

/**
 * FUN MOMENT — SELLER REGISTRATION & POSTGRESQL BIGINT RELATIONSHIP SUITE
 *
 * Verifies:
 * 1. Individual Provider Registration (seller_type: 1) with document upload and category attachment.
 * 2. Company Provider Registration (seller_type: 2) with CR number, document, contact person details.
 * 3. Schema alignment: seller_verifies.seller_id is BIGINT, zero PostgreSQL type cast errors.
 * 4. Post-commit eager loading ($user->load(['sellerVerify', 'registeredCategories'])).
 * 5. Direct bidirectional Eloquent relationships:
 *    - User::sellerVerify (hasOne)
 *    - SellerVerify::user (belongsTo)
 * 6. Zero persistent database footprint (wrapped in rolled back transaction).
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Category;
use App\Http\Controllers\Api\UserController;
use App\LegalTerm;
use App\LegalTermVersion;
use App\ProviderCategory;
use App\SellerVerify;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
echo " SELLER REGISTRATION & BIGINT RELATIONSHIP VERIFICATION" . PHP_EOL;
echo " Database: PostgreSQL 17.4 (Rolled Back Fixture)" . PHP_EOL;
echo "==================================================================" . PHP_EOL . PHP_EOL;

DB::beginTransaction();

try {
    $userController = app(UserController::class);
    $admin = Admin::first();
    $adminId = $admin ? $admin->id : 1;
    $category = Category::where('status', 1)->first() ?: Category::first();

    // 1. Setup mock published seller legal terms
    $masterSellerAr = LegalTerm::where('role', 'seller')->where('language', 'ar')->first();
    $versionSellerAr = LegalTermVersion::create([
        'legal_term_id' => $masterSellerAr->id,
        'version_number' => 1,
        'title' => 'شروط وأحكام مقدم الخدمة — مسودة اختبار',
        'content' => '<p>شروط الخدمة لمقدمي الخدمات</p>',
        'content_hash' => hash('sha256', '<p>شروط الخدمة لمقدمي الخدمات</p>'),
        'published_by' => $adminId,
        'published_at' => now(),
    ]);
    $masterSellerAr->current_version_id = $versionSellerAr->id;
    $masterSellerAr->save();

    // Create dummy PDF for document upload
    $tempDocPath = tempnam(sys_get_temp_dir(), 'test_doc_');
    file_put_contents($tempDocPath, '%PDF-1.4 dummy test document');
    $dummyFile = new UploadedFile($tempDocPath, 'national_id.pdf', 'application/pdf', null, true);

    // ------------------------------------------------------------------------
    // T1: Individual Provider Registration (seller_type: 1)
    // ------------------------------------------------------------------------
    $indivEmail = 'indiv_seller_' . Str::random(8) . '@example.test';
    $indivUsername = 'indiv_' . Str::random(8);

    $reqIndiv = Request::create('/api/v1/provider/register', 'POST', [
        'name' => 'Individual Provider Test',
        'email' => $indivEmail,
        'username' => $indivUsername,
        'phone' => '966509999991',
        'password' => 'SecurePass123!',
        'service_city' => '1',
        'service_area' => '1',
        'country_id' => '1',
        'country_code' => 'SA',
        'terms_conditions' => '1',
        'terms_version_id' => $versionSellerAr->id,
        'terms_language' => 'ar',
        'seller_type' => '1', // Individual
        'national_id_number' => '1098765432',
        'category_ids' => [$category->id],
    ], [], [
        'national_id_document' => $dummyFile,
    ]);

    $resIndiv = $userController->registerProvider($reqIndiv);
    $dataIndiv = $resIndiv->getData(true);

    assertTest(
        $resIndiv->getStatusCode() === 201,
        "T1: Individual provider registration returns HTTP 201",
        "Status: " . $resIndiv->getStatusCode()
    );

    $indivId = $dataIndiv['users']['id'] ?? null;
    $indivUser = User::find($indivId);

    assertTest(
        $indivUser !== null
        && (int) $indivUser->user_type === User::USER_TYPE_SELLER
        && (int) $indivUser->seller_type === User::SELLER_TYPE_INDIVIDUAL,
        "T2: Individual user created with user_type=0 (seller) and seller_type=1 (individual)",
        "User ID: {$indivId}"
    );

    assertTest(
        !empty($dataIndiv['token']),
        "T3: Sanctum authentication token issued upon individual registration"
    );

    // ------------------------------------------------------------------------
    // T4: BIGINT Relationship Verification (Individual)
    // ------------------------------------------------------------------------
    $loadedIndiv = User::with(['sellerVerify', 'registeredCategories'])->find($indivId);

    assertTest(
        $loadedIndiv->sellerVerify !== null,
        "T4: Post-registration eager load (\$user->load('sellerVerify')) succeeds without type mismatch"
    );

    assertTest(
        (int) $loadedIndiv->sellerVerify->seller_id === (int) $indivId,
        "T5: seller_verifies.seller_id exactly matches users.id as BIGINT",
        "seller_id: {$loadedIndiv->sellerVerify->seller_id}"
    );

    assertTest(
        (int) $loadedIndiv->sellerVerify->status === SellerVerify::STATUS_PENDING,
        "T6: SellerVerify initial status is 0 (Pending Admin Verification)"
    );

    assertTest(
        $loadedIndiv->registeredCategories->contains('id', $category->id),
        "T7: Provider registeredCategories relation resolves attached category",
        "Category ID: {$category->id}"
    );

    // ------------------------------------------------------------------------
    // T8: Company Provider Registration (seller_type: 2)
    // ------------------------------------------------------------------------
    $tempCrPath = tempnam(sys_get_temp_dir(), 'test_cr_');
    file_put_contents($tempCrPath, '%PDF-1.4 dummy cr document');
    $dummyCrFile = new UploadedFile($tempCrPath, 'cr_document.pdf', 'application/pdf', null, true);

    $compEmail = 'comp_seller_' . Str::random(8) . '@example.test';
    $compUsername = 'comp_' . Str::random(8);
    $crNumber = '1010' . rand(100000, 999999);

    $reqComp = Request::create('/api/v1/provider/register', 'POST', [
        'name' => 'Company Provider Test',
        'email' => $compEmail,
        'username' => $compUsername,
        'phone' => '966509999992',
        'password' => 'SecurePass123!',
        'service_city' => '1',
        'service_area' => '1',
        'country_id' => '1',
        'country_code' => 'SA',
        'terms_conditions' => '1',
        'terms_version_id' => $versionSellerAr->id,
        'terms_language' => 'ar',
        'seller_type' => '2', // Company
        'company_name' => 'Acrobatics and Events LLC',
        'cr_number' => $crNumber,
        'contact_person_name' => 'Tariq Al-Mansoor',
        'contact_person_email' => 'tariq@events.example.test',
        'contact_person_phone' => '966509999993',
        'category_ids' => [$category->id],
    ], [], [
        'cr_document' => $dummyCrFile,
    ]);

    $resComp = $userController->registerProvider($reqComp);
    $dataComp = $resComp->getData(true);

    assertTest(
        $resComp->getStatusCode() === 201,
        "T8: Company provider registration returns HTTP 201",
        "Status: " . $resComp->getStatusCode()
    );

    $compId = $dataComp['users']['id'] ?? null;
    $compUser = User::find($compId);

    assertTest(
        $compUser !== null
        && (int) $compUser->user_type === User::USER_TYPE_SELLER
        && (int) $compUser->seller_type === User::SELLER_TYPE_COMPANY
        && $compUser->business_registration === $crNumber,
        "T9: Company user created with user_type=0, seller_type=2, business_registration=CR",
        "User ID: {$compId}"
    );

    // ------------------------------------------------------------------------
    // T10: Direct Bidirectional Eloquent & SQL Join Integrity
    // ------------------------------------------------------------------------
    $loadedComp = User::with('sellerVerify')->find($compId);
    assertTest(
        $loadedComp->sellerVerify !== null && $loadedComp->sellerVerify->cr_number === $crNumber,
        "T10: Company SellerVerify relation accurately loads CR details"
    );

    // Reverse belongsTo relationship check
    $sellerVerifyRecord = SellerVerify::where('seller_id', $compId)->first();
    assertTest(
        $sellerVerifyRecord !== null && $sellerVerifyRecord->user !== null && (int) $sellerVerifyRecord->user->id === (int) $compId,
        "T11: Reverse belongsTo relation (SellerVerify::user) resolves parent User cleanly",
        "Parent ID: " . ($sellerVerifyRecord && $sellerVerifyRecord->user ? $sellerVerifyRecord->user->id : 'none')
    );

    // Direct SQL join query assertion matching admin users view
    $rawJoinResult = DB::select("
        SELECT u.id, u.name, u.email, sv.seller_id, sv.status, sv.company_name 
        FROM users u 
        INNER JOIN seller_verifies sv ON sv.seller_id = u.id 
        WHERE u.id = ?
    ", [$compId]);

    assertTest(
        count($rawJoinResult) === 1 && (int) $rawJoinResult[0]->seller_id === (int) $compId,
        "T12: Direct PostgreSQL INNER JOIN on users.id = seller_verifies.seller_id executes cleanly",
        "Zero type-mismatch errors"
    );

    @unlink($tempDocPath);
    @unlink($tempCrPath);

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
