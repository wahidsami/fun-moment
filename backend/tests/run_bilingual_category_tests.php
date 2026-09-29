<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Category;
use App\Subcategory;
use App\ChildCategory;
use App\Http\Controllers\AdminCategoryApiController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$passed = 0;
$failed = 0;

function assertTest($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $description\n";
        $passed++;
    } else {
        echo " [FAIL] $description\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "FUN MOMENT — BILINGUAL CATEGORY TEST SUITE\n";
echo "========================================================\n\n";

$admin = Admin::where('email', 'admin@funmoments.local')->first() ?: Admin::first();
Auth::guard('admin')->setUser($admin);

// --------------------------------------------------------------------------
// TEST 1: SCHEMA VERIFICATION
// --------------------------------------------------------------------------
echo "--- 1. SCHEMA VERIFICATION ---\n";
assertTest("categories table has name_ar column", Schema::hasColumn('categories', 'name_ar'));
assertTest("subcategories table has name_ar column", Schema::hasColumn('subcategories', 'name_ar'));
assertTest("child_categories table has name_ar column", Schema::hasColumn('child_categories', 'name_ar'));
assertTest("categories table retains canonical name column", Schema::hasColumn('categories', 'name'));

// --------------------------------------------------------------------------
// TEST 2: BACKFILL VERIFICATION
// --------------------------------------------------------------------------
echo "\n--- 2. BACKFILL VERIFICATION ---\n";
$expectedBackfills = [
    'music-dj' => 'الموسيقى والـ DJ',
    'food-hospitality' => 'الأطعمة والضيافة',
    'sound-lighting' => 'الصوت والإضاءة',
    'event-setup-equipment' => 'تجهيز الفعاليات والمعدات',
    'decor-event-styling' => 'الديكور وتنسيق المناسبات',
    'photography-video' => 'التصوير والفيديو',
    'entertainment-activities' => 'الترفيه والأنشطة',
];

foreach ($expectedBackfills as $slug => $expectedAr) {
    $cat = Category::where('slug', $slug)->first();
    if ($cat) {
        assertTest("Category '{$slug}' has Arabic name '{$expectedAr}'", $cat->name_ar === $expectedAr);
        assertTest("Category '{$slug}' English name is preserved", !empty($cat->name));
    }
}

// --------------------------------------------------------------------------
// TEST 3: ADMIN CATEGORY API (GET) BILINGUAL RESPONSE
// --------------------------------------------------------------------------
echo "\n--- 3. ADMIN CATEGORY API (GET) ---\n";
$adminCatController = $app->make(AdminCategoryApiController::class);
$catRes = $adminCatController->apiCategories();
assertTest("apiCategories returns HTTP 200", $catRes->getStatusCode() === 200);

$catData = json_decode($catRes->getContent(), true);
$categoriesList = $catData['categories'] ?? [];
assertTest("apiCategories returns category array", count($categoriesList) > 0);

$sampleCat = null;
foreach ($categoriesList as $c) {
    if (!empty($c['nameAr']) && $c['nameAr'] !== $c['nameEn']) {
        $sampleCat = $c;
        break;
    }
}
assertTest("At least one category has distinct nameEn and nameAr", $sampleCat !== null);
if ($sampleCat) {
    assertTest("nameEn is string ({$sampleCat['nameEn']})", is_string($sampleCat['nameEn']));
    assertTest("nameAr is Arabic string ({$sampleCat['nameAr']})", is_string($sampleCat['nameAr']) && preg_match('/[\x{0600}-\x{06FF}]/u', $sampleCat['nameAr']));
}

// --------------------------------------------------------------------------
// TEST 4: ADMIN CATEGORY UPDATE (PERSISTS name_en AND name_ar)
// --------------------------------------------------------------------------
echo "\n--- 4. ADMIN CATEGORY UPDATE (Category #1) ---\n";
$cat1 = Category::find(1);
if ($cat1) {
    $origName = $cat1->name;
    $origNameAr = $cat1->name_ar;

    // Simulate React Admin edit of Category #1: English = "Music & DJ", Arabic = "الموسيقى والـ DJ"
    $updateReq = Request::create('/admin-home/categories-json/parent/1', 'POST', [
        'name_en' => 'Music & DJ',
        'name_ar' => 'الموسيقى والـ DJ',
        'slug' => 'music-dj',
        'status' => 'active',
        'sort_order' => 10,
    ]);

    $updateRes = $adminCatController->apiUpdateCategory($updateReq, 'parent', 1);
    assertTest("Update category #1 returns HTTP 200", $updateRes->getStatusCode() === 200);

    $updateData = json_decode($updateRes->getContent(), true);
    assertTest("Update response node.nameEn is 'Music & DJ'", ($updateData['node']['nameEn'] ?? '') === 'Music & DJ');
    assertTest("Update response node.nameAr is 'الموسيقى والـ DJ'", ($updateData['node']['nameAr'] ?? '') === 'الموسيقى والـ DJ');

    // Fresh fetch from database
    $freshCat1 = Category::find(1);
    assertTest("DB persists name as 'Music & DJ'", $freshCat1->name === 'Music & DJ');
    assertTest("DB persists name_ar as 'الموسيقى والـ DJ'", $freshCat1->name_ar === 'الموسيقى والـ DJ');
}

// --------------------------------------------------------------------------
// TEST 5: ADMIN SUBCATEGORY & CHILD CATEGORY BILINGUAL PERSISTENCE
// --------------------------------------------------------------------------
echo "\n--- 5. ADMIN SUBCATEGORY & CHILD CATEGORY PERSISTENCE ---\n";
// Create test subcategory
$subReq = Request::create('/admin-home/categories-json', 'POST', [
    'level' => 'sub',
    'parent_id' => 1,
    'name_en' => 'DJ Equipment Test',
    'name_ar' => 'معدات دي جي تجريبية',
    'slug' => 'dj-equipment-test-' . time(),
    'status' => 'active',
]);
$subRes = $adminCatController->apiCreateCategory($subReq);
assertTest("Create subcategory returns HTTP 200", $subRes->getStatusCode() === 200);
$subData = json_decode($subRes->getContent(), true);
assertTest("Subcategory node.nameEn is correct", ($subData['node']['nameEn'] ?? '') === 'DJ Equipment Test');
assertTest("Subcategory node.nameAr is correct", ($subData['node']['nameAr'] ?? '') === 'معدات دي جي تجريبية');

$subId = $subData['node']['id'] ?? null;
if ($subId) {
    // Create test child category
    $childReq = Request::create('/admin-home/categories-json', 'POST', [
        'level' => 'child',
        'parent_id' => $subId,
        'name_en' => 'Turntables Test',
        'name_ar' => 'أجهزة التشغيل تجريبية',
        'slug' => 'turntables-test-' . time(),
        'status' => 'active',
    ]);
    $childRes = $adminCatController->apiCreateCategory($childReq);
    assertTest("Create child category returns HTTP 200", $childRes->getStatusCode() === 200);
    $childData = json_decode($childRes->getContent(), true);
    assertTest("Child category node.nameEn is correct", ($childData['node']['nameEn'] ?? '') === 'Turntables Test');
    assertTest("Child category node.nameAr is correct", ($childData['node']['nameAr'] ?? '') === 'أجهزة التشغيل تجريبية');

    // Clean up test sub & child
    $childId = $childData['node']['id'] ?? null;
    if ($childId) {
        ChildCategory::destroy($childId);
    }
    Subcategory::destroy($subId);
    echo " [INFO] Cleaned up temporary test subcategory and child category\n";
}

// --------------------------------------------------------------------------
// TEST 6: PUBLIC CATEGORY API (GET) RETURNS name AND name_ar
// --------------------------------------------------------------------------
echo "\n--- 6. PUBLIC CATEGORY API ---\n";
$publicCatController = $app->make(CategoryController::class);
$publicRes = $publicCatController->category();
assertTest("Public Category API returns success status (200 or 201)", in_array($publicRes->getStatusCode(), [200, 201]));

$publicData = json_decode($publicRes->getContent(), true);
$publicCategories = $publicData['category'] ?? [];
assertTest("Public API returns category list", count($publicCategories) > 0);

$firstPublic = $publicCategories[0] ?? null;
if ($firstPublic) {
    assertTest("Public category has 'name' field", array_key_exists('name', $firstPublic));
    assertTest("Public category has 'name_ar' field", array_key_exists('name_ar', $firstPublic));
    assertTest("Public category preserves 'icon' field", array_key_exists('icon', $firstPublic));
    assertTest("Public category preserves 'mobile_icon' field", array_key_exists('mobile_icon', $firstPublic));
    assertTest("Public category preserves 'id' field", array_key_exists('id', $firstPublic));
}

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passed, Failed: $failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
