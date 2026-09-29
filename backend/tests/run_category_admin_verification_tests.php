<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Category;
use App\Subcategory;
use App\ChildCategory;
use App\Service;
use App\Http\Controllers\AdminCategoryApiController;
use App\Http\Controllers\AdminRoleManageController;
use App\Http\Controllers\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
echo "FUN MOMENT — CATEGORY MANAGER & ADMIN DIRECTORY VERIFICATION\n";
echo "========================================================\n\n";

$admin = Admin::where('email', 'admin@funmoments.local')->first() ?: Admin::first();
Auth::guard('admin')->setUser($admin);

// --------------------------------------------------------------------------
// TEST 1: ADMIN DIRECTORY AUTHORIZATION (SUPER ADMIN 200, UNAUTH 401/403)
// --------------------------------------------------------------------------
echo "--- 1. ADMIN DIRECTORY AUTHORIZATION ---\n";

$roleController = $app->make(AdminRoleManageController::class);

// Authenticated Super Admin
$dirRes = $roleController->apiDirectory();
assertTest("Super Admin accessing /admin-home/admin-directory returns HTTP 200", $dirRes->getStatusCode() === 200);
$dirData = json_decode($dirRes->getContent(), true);
assertTest("Admin directory returns status 'success'", ($dirData['status'] ?? '') === 'success');
assertTest("Admin directory returns admins list", isset($dirData['admins']) && is_array($dirData['admins']));
assertTest("Admin directory returns roles list", isset($dirData['roles']) && is_array($dirData['roles']));

// Non-Super Admin access check via middleware simulation
Auth::guard('admin')->logout();
$unauthReq = Request::create('/admin-home/admin-directory', 'GET');
$unauthReq->headers->set('Accept', 'application/json');
$unauthRes = $app->handle($unauthReq);
assertTest("Unauthenticated request to /admin-home/admin-directory is protected (401 or redirect)", in_array($unauthRes->getStatusCode(), [401, 302, 403]));

// Re-login Super Admin
Auth::guard('admin')->setUser($admin);

// --------------------------------------------------------------------------
// TEST 2: SAFE CATEGORY DELETION WITH DEPENDENCIES (HTTP 422)
// --------------------------------------------------------------------------
echo "\n--- 2. CATEGORY DELETION SAFE-GUARDS ---\n";

$catController = $app->make(AdminCategoryApiController::class);

// Child Category 1 has 2 services attached
$childDelReq = Request::create('/admin-home/categories-json/child/1/delete', 'POST');
$childDelRes = $catController->apiDeleteCategory($childDelReq, 'child', 1);
assertTest("Child category with attached services returns HTTP 422", $childDelRes->getStatusCode() === 422);
$childDelData = json_decode($childDelRes->getContent(), true);
assertTest("HTTP 422 contains exact safe deletion error message", strpos($childDelData['message'] ?? '', 'services linked to it') !== false);
assertTest("HTTP 422 includes dependency_type 'services'", ($childDelData['dependency_type'] ?? '') === 'services');
assertTest("HTTP 422 includes attached services count", ($childDelData['count'] ?? 0) >= 2);
assertTest("HTTP 422 includes sample attached services list", !empty($childDelData['services']));

// Subcategory 1 has child category 1 and services attached
$subDelReq = Request::create('/admin-home/categories-json/sub/1/delete', 'POST');
$subDelRes = $catController->apiDeleteCategory($subDelReq, 'sub', 1);
assertTest("Subcategory with child categories returns HTTP 422", $subDelRes->getStatusCode() === 422);
$subDelData = json_decode($subDelRes->getContent(), true);
assertTest("Subcategory 422 contains child category dependency message", strpos($subDelData['message'] ?? '', 'child categories linked') !== false);

// --------------------------------------------------------------------------
// TEST 3: SERVICE CONTROLLER CATEGORY HIERARCHY PAYLOAD & CREATION/UPDATE
// --------------------------------------------------------------------------
echo "\n--- 3. SERVICE CATEGORY PAYLOAD & CHILD CATEGORY SUPPORT ---\n";

$svcController = $app->make(ServiceController::class);
$svcListRes = $svcController->apiServices();
assertTest("ServiceController apiServices returns HTTP 200", $svcListRes->getStatusCode() === 200);
$svcListData = json_decode($svcListRes->getContent(), true);

$categoryWithSubs = collect($svcListData['categories'])->first(function($c) { return !empty($c['subcategories']); });
assertTest("Category payload returns subcategories", !empty($categoryWithSubs['subcategories']));
$firstSub = $categoryWithSubs['subcategories'][0] ?? null;
assertTest("Subcategory payload includes childcategories", isset($firstSub['childcategories']) && is_array($firstSub['childcategories']));

$sampleService = $svcListData['services'][0] ?? null;
assertTest("Service payload exposes subcategory_id", array_key_exists('subcategory_id', $sampleService));
assertTest("Service payload exposes child_category_id", array_key_exists('child_category_id', $sampleService));

// Test Service Update with child_category_id
$targetService = Service::find(1);
$origCatId = $targetService->category_id;
$origSubId = $targetService->subcategory_id;
$origChildId = $targetService->child_category_id;

$updateReq = Request::create('/admin-home/services-json/1', 'POST', [
    'title_en' => $targetService->title,
    'title_ar' => $targetService->title,
    'category_id' => $origCatId,
    'subcategory_id' => $origSubId,
    'child_category_id' => 1,
    'seller_id' => $targetService->seller_id,
    'price' => $targetService->price,
    'duration' => '2 days',
    'description_en' => $targetService->description,
    'description_ar' => $targetService->description,
]);
$updateRes = $svcController->apiUpdateService($updateReq, 1);
assertTest("Service update with child_category_id returns HTTP 200", $updateRes->getStatusCode() === 200);
$targetService->refresh();
assertTest("Database record child_category_id preserved", (int)$targetService->child_category_id === 1);

// --------------------------------------------------------------------------
// TEST 4: TRANSACTIONAL REASSIGNMENT & SAFE DELETION
// --------------------------------------------------------------------------
echo "\n--- 4. SERVICE REASSIGNMENT & RESOLUTION WORKFLOW ---\n";

// Create a temporary child category to test reassignment and clean deletion
$tempCat = Category::firstOrCreate(['name' => 'Temp Reassign Test Category'], ['slug' => 'temp-reassign-cat', 'status' => 1]);
$tempSub = Subcategory::firstOrCreate(['category_id' => $tempCat->id, 'name' => 'Temp Reassign Test Sub'], ['slug' => 'temp-reassign-sub', 'status' => 1]);
$tempChildSource = ChildCategory::firstOrCreate(['category_id' => $tempCat->id, 'sub_category_id' => $tempSub->id, 'name' => 'Temp Source Child'], ['slug' => 'temp-src-child', 'status' => 1]);
$tempChildTarget = ChildCategory::firstOrCreate(['category_id' => $tempCat->id, 'sub_category_id' => $tempSub->id, 'name' => 'Temp Target Child'], ['slug' => 'temp-tgt-child', 'status' => 1]);

// Create a temporary service attached to tempChildSource
$tempService = Service::create([
    'title' => 'Temporary Reassignment Test Service',
    'slug' => 'temp-reassign-svc-' . time(),
    'category_id' => $tempCat->id,
    'subcategory_id' => $tempSub->id,
    'child_category_id' => $tempChildSource->id,
    'seller_id' => $admin->id,
    'service_city_id' => 1,
    'price' => 200,
    'status' => 1,
    'is_service_on' => 1,
    'description' => 'Test description',
]);

// Attempt to delete tempChildSource -> must be blocked with 422
$delBlockedRes = $catController->apiDeleteCategory(Request::create('/delete', 'POST'), 'child', $tempChildSource->id);
assertTest("Child category with attached service blocked with HTTP 422", $delBlockedRes->getStatusCode() === 422);

// Reassign the service from tempChildSource to tempChildTarget
$reassignReq = Request::create('/admin-home/categories-json/reassign-services', 'POST', [
    'from_level' => 'child',
    'from_id' => $tempChildSource->id,
    'to_category_id' => $tempCat->id,
    'to_subcategory_id' => $tempSub->id,
    'to_child_category_id' => $tempChildTarget->id,
]);
$reassignRes = $catController->apiReassignServices($reassignReq);
assertTest("Reassign services returns HTTP 200", $reassignRes->getStatusCode() === 200);
$reassignData = json_decode($reassignRes->getContent(), true);
assertTest("Reassign returns success message", strpos($reassignData['message'] ?? '', 'reassigned successfully') !== false);

// Verify tempService moved to tempChildTarget
$tempService->refresh();
assertTest("Service child_category_id moved to target child", (int)$tempService->child_category_id === $tempChildTarget->id);

// Now delete tempChildSource -> must SUCCEED with HTTP 200
$delSuccessRes = $catController->apiDeleteCategory(Request::create('/delete', 'POST'), 'child', $tempChildSource->id);
assertTest("After reassignment, child category deletes cleanly with HTTP 200", $delSuccessRes->getStatusCode() === 200);

// Cleanup temporary test items
$tempService->delete();
$tempChildTarget->delete();
$tempSub->delete();
$tempCat->delete();

echo "\n========================================================\n";
echo "VERIFICATION SUMMARY: $passed PASSED, $failed FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
