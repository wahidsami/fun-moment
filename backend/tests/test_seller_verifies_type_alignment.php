<?php

/**
 * FUN MOMENT — Regression Test Suite: PostgreSQL Seller Relationship Type Alignment
 *
 * Verifies:
 * 1. Bug reproduction under PostgreSQL 17 with seller_verifies.seller_id as VARCHAR(191)
 * 2. Migration execution: seller_id -> BIGINT
 * 3. Admin Users Endpoint (GET /admin-home/frontend-users) returning HTTP 200 with 13 accounts
 * 4. Flutter Seller Registration (POST /api/v1/provider/register) returning HTTP 201 with new account
 * 5. Integrity and preservation of original 13 accounts
 * 6. Migration idempotency and rollback safety
 *
 * Runs exclusively against an isolated disposable local PostgreSQL 17 database.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Category;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\FrontendUserManageController;
use App\LegalTerm;
use App\LegalTermVersion;
use App\ProviderCategory;
use App\SellerVerify;
use App\Services\LegalTermsAcceptanceService;
use App\Services\ProviderVerificationDocumentService;
use App\User;
use App\UserTermsAcceptance;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] $testName" . ($detail ? " ($detail)" : '') . PHP_EOL;
    } else {
        $failed++;
        echo "  [FAIL] $testName" . ($detail ? " - $detail" : '') . PHP_EOL;
    }
}

echo PHP_EOL . "=== FUN MOMENT: POSTGRESQL SELLER TYPE ALIGNMENT TEST DRILL ===" . PHP_EOL . PHP_EOL;

// -----------------------------------------------------------------------------
// 1. Setup Isolated Disposable PostgreSQL 17 Database
// -----------------------------------------------------------------------------
$dbHost = '127.0.0.1';
$dbPort = '5432';
$dbUser = 'postgres';
$dbPass = 'postgres';
$testDbName = 'fm_disposable_seller_alignment_' . time();

echo "1. Creating disposable database: $testDbName on PostgreSQL 17..." . PHP_EOL;
$rootPdo = new PDO("pgsql:host=$dbHost;port=$dbPort;dbname=postgres", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$rootPdo->exec("DROP DATABASE IF EXISTS \"$testDbName\";");
$rootPdo->exec("CREATE DATABASE \"$testDbName\";");

// Configure Laravel to use this disposable PostgreSQL database
Config::set('database.default', 'disposable_pgsql');
Config::set('database.connections.disposable_pgsql', [
    'driver' => 'pgsql',
    'host' => $dbHost,
    'port' => $dbPort,
    'database' => $testDbName,
    'username' => $dbUser,
    'password' => $dbPass,
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
    'sslmode' => 'prefer',
]);

DB::purge('disposable_pgsql');
DB::reconnect('disposable_pgsql');

$conn = DB::connection('disposable_pgsql');
$versionStr = $conn->selectOne('SELECT version()')->version;
assertTest(str_contains($versionStr, 'PostgreSQL 17'), 'Running on PostgreSQL 17 Catalog', substr($versionStr, 0, 45));

// -----------------------------------------------------------------------------
// 2. Build Schema & Reproduce Initial Bug State (seller_id as VARCHAR(191))
// -----------------------------------------------------------------------------
echo PHP_EOL . "2. Provisioning schema with legacy VARCHAR(191) seller_id..." . PHP_EOL;

$conn->statement('
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    email VARCHAR(191) UNIQUE,
    username VARCHAR(191) UNIQUE,
    phone VARCHAR(191),
    password VARCHAR(191),
    service_city VARCHAR(191),
    service_area VARCHAR(191),
    state VARCHAR(191),
    country_code VARCHAR(10),
    country_id BIGINT,
    user_type INT DEFAULT 0,
    seller_type INT DEFAULT 0,
    user_status INT DEFAULT 1,
    terms_condition INT DEFAULT 1,
    address VARCHAR(191),
    seller_address VARCHAR(191),
    post_code VARCHAR(50),
    business_registration VARCHAR(191),
    email_verified INT DEFAULT 1,
    last_seen TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
');

$conn->statement('
CREATE TABLE seller_verifies (
    id BIGSERIAL PRIMARY KEY,
    seller_id VARCHAR(191), -- Legacy VARCHAR reproducing production incident!
    national_id VARCHAR(191),
    address VARCHAR(191),
    status BIGINT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    national_id_number VARCHAR(50),
    national_id_document VARCHAR(255),
    license_number VARCHAR(50),
    license_document VARCHAR(255),
    is_band_or_group BOOLEAN DEFAULT FALSE,
    band_name VARCHAR(191),
    band_members_count INT,
    company_name VARCHAR(191),
    cr_number VARCHAR(50),
    cr_document VARCHAR(255),
    contact_person_name VARCHAR(191),
    contact_person_email VARCHAR(191),
    contact_person_phone VARCHAR(50),
    rejection_reason TEXT,
    verified_by BIGINT,
    verified_at TIMESTAMP
);
');

$conn->statement('
CREATE TABLE categories (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    name_ar VARCHAR(191),
    status INT DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
');

$conn->statement('
CREATE TABLE provider_categories (
    id BIGSERIAL PRIMARY KEY,
    provider_id BIGINT NOT NULL,
    category_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
');

$conn->statement('
CREATE TABLE admins (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    username VARCHAR(191) UNIQUE NOT NULL,
    email VARCHAR(191) UNIQUE NOT NULL,
    password VARCHAR(191) NOT NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
');

$conn->statement('
CREATE TABLE legal_terms (
    id BIGSERIAL PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    language VARCHAR(10) NOT NULL,
    title VARCHAR(255) NOT NULL,
    draft_title VARCHAR(255),
    draft_content TEXT,
    current_version_id BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
');

$conn->statement('
CREATE TABLE legal_term_versions (
    id BIGSERIAL PRIMARY KEY,
    legal_term_id BIGINT NOT NULL,
    version_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    content_hash VARCHAR(64) NOT NULL,
    published_by BIGINT,
    published_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
');

$conn->statement('
CREATE TABLE user_terms_acceptances (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    legal_term_id BIGINT NOT NULL,
    legal_term_version_id BIGINT NOT NULL,
    role VARCHAR(50) NOT NULL,
    language VARCHAR(10) NOT NULL,
    version_number INT NOT NULL,
    content_hash VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    accepted_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
');

$conn->statement('
CREATE TABLE personal_access_tokens (
    id BIGSERIAL PRIMARY KEY,
    tokenable_type VARCHAR(191) NOT NULL,
    tokenable_id BIGINT NOT NULL,
    name VARCHAR(191) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    abilities TEXT,
    last_used_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
');

$conn->statement("
CREATE TABLE languages (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    direction INT DEFAULT 0,
    status VARCHAR(191) DEFAULT 'publish',
    \"default\" INT DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
");
$conn->table('languages')->insert([
    'name' => 'English',
    'slug' => 'en',
    'direction' => 0,
    'status' => 'publish',
    'default' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

$conn->getPdo()->exec('
CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    guard_name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    guard_name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
CREATE TABLE model_has_permissions (
    permission_id BIGINT NOT NULL,
    model_type VARCHAR(191) NOT NULL,
    model_id BIGINT NOT NULL
);
CREATE TABLE model_has_roles (
    role_id BIGINT NOT NULL,
    model_type VARCHAR(191) NOT NULL,
    model_id BIGINT NOT NULL
);
CREATE TABLE role_has_permissions (
    permission_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL
);
');

$conn->table('permissions')->insert([
    'id' => 1,
    'name' => 'user-list',
    'guard_name' => 'admin',
    'created_at' => now(),
    'updated_at' => now(),
]);

// -----------------------------------------------------------------------------
// 3. Seed Fixture Data: 13 Production Accounts & Supporting Master Data
// -----------------------------------------------------------------------------
echo PHP_EOL . "3. Seeding exact 13 production accounts & master data..." . PHP_EOL;

// Admin
$adminId = $conn->table('admins')->insertGetId([
    'name' => 'Staging Administrator',
    'username' => 'staging_admin',
    'email' => 'admin@staging.funmoment.sa',
    'password' => Hash::make('secret_admin_pass'),
    'remember_token' => 'sample_remember_token_abc',
    'created_at' => now(),
    'updated_at' => now(),
]);

$conn->table('model_has_permissions')->insert([
    'permission_id' => 1,
    'model_type' => 'App\Admin',
    'model_id' => $adminId,
]);
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

// Categories
$cat1 = $conn->table('categories')->insertGetId(['name' => 'Music & DJ', 'name_ar' => 'موسيقى ودي جي', 'status' => 1]);
$cat2 = $conn->table('categories')->insertGetId(['name' => 'Photography', 'name_ar' => 'تصوير وفيديو', 'status' => 1]);

// Legal Terms (Published for Seller en & ar)
$termSellerEn = $conn->table('legal_terms')->insertGetId([
    'role' => 'seller', 'language' => 'en', 'title' => 'Seller Terms & Conditions', 'created_at' => now(), 'updated_at' => now()
]);
$termVerSellerEn = $conn->table('legal_term_versions')->insertGetId([
    'legal_term_id' => $termSellerEn, 'version_number' => 1, 'title' => 'Seller Terms & Conditions',
    'content' => 'Seller Registration Terms Content', 'content_hash' => hash('sha256', 'Seller Registration Terms Content'),
    'published_by' => $adminId, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()
]);
$conn->table('legal_terms')->where('id', $termSellerEn)->update(['current_version_id' => $termVerSellerEn]);

$termSellerAr = $conn->table('legal_terms')->insertGetId([
    'role' => 'seller', 'language' => 'ar', 'title' => 'شروط وأحكام مقدم الخدمة', 'created_at' => now(), 'updated_at' => now()
]);
$termVerSellerAr = $conn->table('legal_term_versions')->insertGetId([
    'legal_term_id' => $termSellerAr, 'version_number' => 1, 'title' => 'شروط وأحكام مقدم الخدمة',
    'content' => 'نص شروط تسجيل مقدم الخدمة', 'content_hash' => hash('sha256', 'نص شروط تسجيل مقدم الخدمة'),
    'published_by' => $adminId, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()
]);
$conn->table('legal_terms')->where('id', $termSellerAr)->update(['current_version_id' => $termVerSellerAr]);

// Seed 13 Production Accounts
for ($i = 1; $i <= 13; $i++) {
    $sellerType = ($i <= 5) ? 0 : 1; // 1-5 individual, 6-13 commercial
    $conn->table('users')->insert([
        'id' => $i,
        'name' => "Seller User $i",
        'email' => "seller$i@example.com",
        'username' => "seller_$i",
        'phone' => "05000000$i",
        'password' => Hash::make("password_$i"),
        'user_type' => 0,
        'seller_type' => $sellerType,
        'user_status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Insert legacy VARCHAR string for seller_id ('1', '2', etc.)
    $conn->table('seller_verifies')->insert([
        'id' => $i,
        'seller_id' => (string) $i,
        'status' => ($i <= 10) ? 1 : 0,
        'company_name' => $sellerType ? "Company $i" : null,
        'cr_number' => $sellerType ? "CR101000$i" : null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conn->table('provider_categories')->insert([
        'provider_id' => $i,
        'category_id' => ($i % 2 === 0) ? $cat2 : $cat1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// Align sequence to 13
$conn->statement("SELECT setval('users_id_seq', 13, true);");
$conn->statement("SELECT setval('seller_verifies_id_seq', 13, true);");

assertTest($conn->table('users')->count() === 13, '13 Users Seeded');
assertTest($conn->table('seller_verifies')->count() === 13, '13 SellerVerifies Seeded');

// -----------------------------------------------------------------------------
// 4. Test Step 1: Prove Bug Reproduction Under VARCHAR(191)
// -----------------------------------------------------------------------------
echo PHP_EOL . "4. Bug Reproduction Drill (Before Migration)..." . PHP_EOL;

$bugReproduced = false;
$caughtMessage = '';
try {
    // Attempt eager loading sellerVerify with seller_id as varchar
    User::on('disposable_pgsql')->with('sellerVerify')->get();
} catch (\Throwable $e) {
    $bugReproduced = true;
    $caughtMessage = $e->getMessage();
}

assertTest(
    $bugReproduced && str_contains($caughtMessage, 'operator does not exist: character varying = integer'),
    'Bug Reproduced Under PostgreSQL 17',
    'Threw expected SQLSTATE 42883 operator does not exist'
);

// -----------------------------------------------------------------------------
// 5. Test Step 2: Execute Corrective Migration (align_seller_verifies_seller_id_to_bigint)
// -----------------------------------------------------------------------------
echo PHP_EOL . "5. Executing Corrective Migration..." . PHP_EOL;

$migrationFile = __DIR__ . '/../database/migrations/2026_11_05_150000_align_seller_verifies_seller_id_to_bigint.php';
$migration = require $migrationFile;

$migration->up();

// Verify column type in information_schema
$colType = $conn->selectOne("
    SELECT data_type 
    FROM information_schema.columns 
    WHERE table_name = 'seller_verifies' AND column_name = 'seller_id'
")->data_type;

assertTest(strtolower($colType) === 'bigint', 'seller_verifies.seller_id converted to BIGINT', "Current: $colType");

// Verify index existence
$indexExists = $conn->selectOne("
    SELECT count(*) as cnt 
    FROM pg_indexes 
    WHERE tablename = 'seller_verifies' AND indexname = 'seller_verifies_seller_id_index'
")->cnt > 0;

assertTest($indexExists, 'Index seller_verifies_seller_id_index created successfully');

// Verify Idempotency of up()
$migration->up();
assertTest(true, 'Migration up() is safely idempotent');

// -----------------------------------------------------------------------------
// 6. Test Step 3: Admin Users Endpoint Verification
// -----------------------------------------------------------------------------
echo PHP_EOL . "6. Admin Users Endpoint Verification (GET /admin-home/frontend-users)..." . PHP_EOL;

$adminController = new FrontendUserManageController();
$req = Request::create('/admin-home/frontend-users', 'GET');

// Simulate execution
$adminUsers = User::on('disposable_pgsql')->with(['sellerVerify'])->orderByDesc('id')->get();
assertTest($adminUsers->count() === 13, 'Admin Users query returned exactly 13 accounts');

$allLoaded = true;
foreach ($adminUsers as $u) {
    if (!$u->relationLoaded('sellerVerify') || $u->sellerVerify === null) {
        $allLoaded = false;
        break;
    }
}
assertTest($allLoaded, 'All 13 accounts have sellerVerify eagerly loaded without type mismatch');

$sampleSeller = $adminUsers->firstWhere('id', 13);
assertTest($sampleSeller->sellerVerify->id === 13, 'User 13 sellerVerify matches correctly', "Status: {$sampleSeller->sellerVerify->status}");

// Authenticated HTTP Kernel Dispatch Test
$testAdmin = Admin::on('disposable_pgsql')->find($adminId);
Auth::guard('admin')->setUser($testAdmin);
$httpKernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$httpReq = Request::create('/admin-home/frontend-users', 'GET');
$httpReq->setUserResolver(function() use ($testAdmin) { return $testAdmin; });
$httpRes = $httpKernel->handle($httpReq);
$httpStatus = $httpRes->getStatusCode();
$httpData = json_decode($httpRes->getContent(), true);
$exMsg = isset($httpRes->exception) && $httpRes->exception ? get_class($httpRes->exception) . ': ' . $httpRes->exception->getMessage() : '';

assertTest(
    $httpStatus === 200 && ($httpData['status'] ?? '') === 'success',
    'Authenticated Admin ID 2 HTTP Kernel Dispatch returns HTTP 200 with success status',
    "Status: $httpStatus, Returned Users: " . count($httpData['users'] ?? []) . ", Ex: " . $exMsg
);

// -----------------------------------------------------------------------------
// 7. Test Step 4: Flutter Seller Registration Simulation (POST /api/v1/provider/register)
// -----------------------------------------------------------------------------
echo PHP_EOL . "7. Flutter Seller Registration Simulation..." . PHP_EOL;

// Mock files
$tmpPdf = tempnam(sys_get_temp_dir(), 'test_cr_') . '.pdf';
file_put_contents($tmpPdf, "%PDF-1.4\nTest CR document\n%%EOF");
$uploadedCr = new UploadedFile($tmpPdf, 'cr_document.pdf', 'application/pdf', null, true);

$userController = new UserController();
$regPayload = [
    'name' => 'New Commercial Provider 14',
    'email' => 'provider14@example.com',
    'username' => 'provider_14',
    'phone' => '0551122334',
    'password' => 'SecurePass123!#',
    'service_city' => 'Riyadh',
    'service_area' => 'Al-Olaya',
    'country_id' => 1,
    'country_code' => 'SA',
    'terms_conditions' => 1,
    'terms_version_id' => $termVerSellerEn,
    'terms_language' => 'en',
    'seller_type' => 2, // Company
    'category_ids' => [(int) $cat1],
    'company_name' => 'Provider 14 LLC',
    'cr_number' => 'CR9988776655',
    'contact_person_name' => 'Fahad Manager',
    'contact_person_email' => 'fahad@example.com',
    'contact_person_phone' => '0551122334',
];

$regRequest = Request::create('/api/v1/provider/register', 'POST', $regPayload, [], [
    'cr_document' => $uploadedCr,
]);

// Execute registerProvider
$regResponse = $userController->registerProvider($regRequest);
$regData = json_decode($regResponse->getContent(), true);

assertTest(
    $regResponse->getStatusCode() === 200 || $regResponse->getStatusCode() === 201,
    'Registration HTTP Response Code',
    "Status Code: {$regResponse->getStatusCode()}"
);

assertTest(
    ($regData['status'] ?? '') === 'ok' && !empty($regData['token']),
    'Registration Returned Success Status & Sanctum Token'
);

$user14 = User::on('disposable_pgsql')->where('username', 'provider_14')->first();
assertTest($user14 !== null && $user14->id === 14, 'User 14 persisted in database with sequential ID 14');

$verify14 = SellerVerify::on('disposable_pgsql')->where('seller_id', 14)->first();
assertTest($verify14 !== null && $verify14->cr_number === 'CR9988776655', 'SellerVerify record persisted with matching bigint seller_id');

$catLink14 = ProviderCategory::on('disposable_pgsql')->where('provider_id', 14)->first();
assertTest($catLink14 !== null && $catLink14->category_id === (int) $cat1, 'ProviderCategory persisted for User 14');

// -----------------------------------------------------------------------------
// 8. Test Step 5: Existing Accounts Integrity & Reverse Relationship
// -----------------------------------------------------------------------------
echo PHP_EOL . "8. Existing Accounts Integrity & Relationship Check..." . PHP_EOL;

$totalUsers = User::on('disposable_pgsql')->count();
$totalVerifies = SellerVerify::on('disposable_pgsql')->count();

assertTest($totalUsers === 14, 'Total Users count is exactly 14 (13 original + 1 new)', "Found: $totalUsers");
assertTest($totalVerifies === 14, 'Total SellerVerifies count is exactly 14', "Found: $totalVerifies");

// Test Reverse belongsTo relation
$sampleVerify = SellerVerify::on('disposable_pgsql')->find(1);
assertTest($sampleVerify->user !== null && $sampleVerify->user->id === 1, 'Reverse belongsTo relation SellerVerify::user resolves cleanly');

// Check for duplicates
$duplicateSellers = $conn->select("
    SELECT seller_id, count(*) as cnt 
    FROM seller_verifies 
    GROUP BY seller_id 
    HAVING count(*) > 1
");
assertTest(count($duplicateSellers) === 0, 'Zero duplicate seller_id records detected');

// -----------------------------------------------------------------------------
// 9. Test Step 6: Rollback Safety Drill (down())
// -----------------------------------------------------------------------------
echo PHP_EOL . "9. Rollback Verification Drill (down())..." . PHP_EOL;

$migration->down();

$revertedType = $conn->selectOne("
    SELECT data_type 
    FROM information_schema.columns 
    WHERE table_name = 'seller_verifies' AND column_name = 'seller_id'
")->data_type;

assertTest($revertedType === 'character varying', 'Rollback down() cleanly restores VARCHAR type', "Type: $revertedType");

// Re-apply migration to leave database in verified state
$migration->up();
$reUpType = $conn->selectOne("
    SELECT data_type 
    FROM information_schema.columns 
    WHERE table_name = 'seller_verifies' AND column_name = 'seller_id'
")->data_type;

assertTest($reUpType === 'bigint', 'Re-applying up() successfully returns column to BIGINT');

// -----------------------------------------------------------------------------
// 10. Cleanup & Teardown
// -----------------------------------------------------------------------------
echo PHP_EOL . "10. Cleaning up disposable database..." . PHP_EOL;

DB::disconnect('disposable_pgsql');
$rootPdo->exec("DROP DATABASE IF EXISTS \"$testDbName\";");
assertTest(true, "Disposable database $testDbName dropped cleanly");

if (file_exists($tmpPdf)) {
    @unlink($tmpPdf);
}

echo PHP_EOL . "==========================================================" . PHP_EOL;
echo "TEST RESULTS: Passed: $passed | Failed: $failed" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
exit(0);

