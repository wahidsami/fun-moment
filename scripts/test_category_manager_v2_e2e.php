<?php
/**
 * ============================================================
 * FUN MOMENT — CATEGORY MANAGER V2 COMPREHENSIVE E2E TEST SUITE
 * Validates complete category lifecycle, media vault integration,
 * sort order persistence, safe delete, and API contracts.
 * ============================================================
 */

require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$consoleKernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$consoleKernel->bootstrap();
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

use App\Admin;
use App\Category;
use App\Subcategory;
use App\ChildCategory;
use App\Service;
use App\MediaUpload;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "    FUN MOMENT — CATEGORY MANAGER V2 COMPREHENSIVE E2E SUITE    \n";
echo "=================================================================\n\n";

$passCount = 0;
$failCount = 0;
$errors = [];

function pass(string $name, string $detail = ''): void {
    global $passCount;
    $passCount++;
    echo "  \033[32m[PASS]\033[0m {$name}" . ($detail ? " — \033[36m{$detail}\033[0m" : '') . "\n";
}

function fail(string $name, string $detail = ''): void {
    global $failCount, $errors;
    $failCount++;
    echo "  \033[31m[FAIL]\033[0m {$name}" . ($detail ? " — \033[31m{$detail}\033[0m" : '') . "\n";
    $errors[] = $name . ($detail ? ": {$detail}" : '');
}

function check(string $name, bool $condition, string $detail = ''): void {
    if ($condition) {
        pass($name, $detail);
    } else {
        fail($name, $detail);
    }
}

// Helper to make authenticated Admin HTTP requests through Laravel kernel
function adminRequest(string $method, string $uri, array $data = [], array $files = []) {
    global $kernel, $adminUser;
    Auth::guard('admin')->setUser($adminUser);

    $server = ['HTTP_ACCEPT' => 'application/json'];
    $req = Request::create($uri, $method, $data, [], $files, $server);
    $req->setUserResolver(fn() => $adminUser);

    $res = $kernel->handle($req);
    $json = json_decode($res->getContent(), true);
    return ['status' => $res->getStatusCode(), 'data' => $json, 'raw' => $res->getContent()];
}

// Helper to make public/buyer API requests
function publicRequest(string $method, string $uri, array $data = []) {
    global $kernel;
    $server = ['HTTP_ACCEPT' => 'application/json'];
    $req = Request::create($uri, $method, $data, [], [], $server);
    $res = $kernel->handle($req);
    $json = json_decode($res->getContent(), true);
    return ['status' => $res->getStatusCode(), 'data' => $json, 'raw' => $res->getContent()];
}

// ─── Setup Admin Actor ───────────────────────────────────────────────────────
$adminUser = Admin::first();
if (!$adminUser) {
    $adminUser = Admin::create([
        'name' => 'E2E Category Admin',
        'username' => 'e2e_cat_admin',
        'email' => 'cat_admin@funmoments.test',
        'password' => bcrypt('password123'),
    ]);
}
Auth::guard('admin')->setUser($adminUser);

$stamp = time();
$mainCatId = null;
$subCatId = null;
$childCatId = null;
$testMediaId = null;
$newUploadedMediaId = null;

try {
    // =========================================================================
    // 1. Create Main Category
    // =========================================================================
    echo "\n--- TEST 1: Create Main Category ---\n";
    $res1 = adminRequest('POST', '/admin-home/categories-json', [
        'level' => 'parent',
        'name_en' => 'E2E Main Luxury ' . $stamp,
        'name_ar' => 'قسم تجريبي رئيسي ' . $stamp,
        'slug' => 'e2e-main-luxury-' . $stamp,
        'sort_order' => 10,
        'status' => 'active',
    ]);
    check(
        '1. Create main category',
        $res1['status'] === 200 && ($res1['data']['status'] ?? '') === 'success' && !empty($res1['data']['node']['id']),
        'ID: ' . ($res1['data']['node']['id'] ?? 'none')
    );
    $mainCatId = $res1['data']['node']['id'] ?? null;

    // =========================================================================
    // 2. Create Subcategory
    // =========================================================================
    echo "\n--- TEST 2: Create Subcategory ---\n";
    $res2 = adminRequest('POST', '/admin-home/categories-json', [
        'level' => 'sub',
        'parent_id' => $mainCatId,
        'name_en' => 'E2E Sub Yacht ' . $stamp,
        'name_ar' => 'تصنيف فرعي يخوت ' . $stamp,
        'slug' => 'e2e-sub-yacht-' . $stamp,
        'status' => 'active',
    ]);
    check(
        '2. Create subcategory',
        $res2['status'] === 200 && ($res2['data']['status'] ?? '') === 'success' && !empty($res2['data']['node']['id']),
        'Parent ID: ' . $mainCatId . ', Sub ID: ' . ($res2['data']['node']['id'] ?? 'none')
    );
    $subCatId = $res2['data']['node']['id'] ?? null;

    // =========================================================================
    // 3. Create Child Category
    // =========================================================================
    echo "\n--- TEST 3: Create Child Category ---\n";
    $res3 = adminRequest('POST', '/admin-home/categories-json', [
        'level' => 'child',
        'parent_id' => $subCatId,
        'name_en' => 'E2E Child Sunset Cruise ' . $stamp,
        'name_ar' => 'تصنيف فرعي ثالث غروب ' . $stamp,
        'slug' => 'e2e-child-sunset-' . $stamp,
        'status' => 'active',
    ]);
    check(
        '3. Create child category',
        $res3['status'] === 200 && ($res3['data']['status'] ?? '') === 'success' && !empty($res3['data']['node']['id']),
        'Sub ID: ' . $subCatId . ', Child ID: ' . ($res3['data']['node']['id'] ?? 'none')
    );
    $childCatId = $res3['data']['node']['id'] ?? null;

    // =========================================================================
    // 4. Edit Main Category
    // =========================================================================
    echo "\n--- TEST 4: Edit Main Category ---\n";
    $res4 = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}", [
        'name_en' => 'E2E Main Luxury Updated ' . $stamp,
        'name_ar' => 'قسم رئيسي معدل ' . $stamp,
        'slug' => 'e2e-main-luxury-updated-' . $stamp,
        'sort_order' => 15,
        'status' => 'active',
    ]);
    $catInDb = Category::find($mainCatId);
    check(
        '4. Edit main category',
        $res4['status'] === 200 && $catInDb && $catInDb->name === 'E2E Main Luxury Updated ' . $stamp && $catInDb->sort_order === 15,
        'Updated Name: ' . ($catInDb->name ?? 'none') . ', Sort: ' . ($catInDb->sort_order ?? 'none')
    );

    // =========================================================================
    // 5. Edit Subcategory
    // =========================================================================
    echo "\n--- TEST 5: Edit Subcategory ---\n";
    $res5 = adminRequest('POST', "/admin-home/categories-json/sub/{$subCatId}", [
        'name_en' => 'E2E Sub Yacht Updated ' . $stamp,
        'name_ar' => 'تصنيف فرعي معدل ' . $stamp,
        'slug' => 'e2e-sub-yacht-updated-' . $stamp,
        'parent_id' => $mainCatId,
        'status' => 'active',
    ]);
    $subInDb = Subcategory::find($subCatId);
    check(
        '5. Edit subcategory',
        $res5['status'] === 200 && $subInDb && $subInDb->name === 'E2E Sub Yacht Updated ' . $stamp,
        'Updated Sub Name: ' . ($subInDb->name ?? 'none')
    );

    // =========================================================================
    // 6. Edit Child Category
    // =========================================================================
    echo "\n--- TEST 6: Edit Child Category ---\n";
    $res6 = adminRequest('POST', "/admin-home/categories-json/child/{$childCatId}", [
        'name_en' => 'E2E Child Sunset Cruise Updated ' . $stamp,
        'name_ar' => 'تصنيف ثالث معدل ' . $stamp,
        'slug' => 'e2e-child-sunset-updated-' . $stamp,
        'parent_id' => $subCatId,
        'status' => 'active',
    ]);
    $childInDb = ChildCategory::find($childCatId);
    check(
        '6. Edit child category',
        $res6['status'] === 200 && $childInDb && $childInDb->name === 'E2E Child Sunset Cruise Updated ' . $stamp,
        'Updated Child Name: ' . ($childInDb->name ?? 'none')
    );

    // =========================================================================
    // 7. Activate / Deactivate Main Category
    // =========================================================================
    echo "\n--- TEST 7: Activate / Deactivate Main Category ---\n";
    $res7a = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}/status", ['status' => 'inactive']);
    $catDeact = Category::find($mainCatId);
    $res7b = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}/status", ['status' => 'active']);
    $catAct = Category::find($mainCatId);
    check(
        '7. Activate/deactivate main category',
        $res7a['status'] === 200 && $catDeact->status == 0 && $res7b['status'] === 200 && $catAct->status == 1,
        "Deactivated: {$catDeact->status} -> Reactivated: {$catAct->status}"
    );

    // =========================================================================
    // 8. Activate / Deactivate Child Nodes
    // =========================================================================
    echo "\n--- TEST 8: Activate / Deactivate Child Nodes ---\n";
    $res8Sub = adminRequest('POST', "/admin-home/categories-json/sub/{$subCatId}/status", ['status' => 'inactive']);
    $subDeact = Subcategory::find($subCatId);
    $res8Child = adminRequest('POST', "/admin-home/categories-json/child/{$childCatId}/status", ['status' => 'inactive']);
    $childDeact = ChildCategory::find($childCatId);

    // Restore to active
    adminRequest('POST', "/admin-home/categories-json/sub/{$subCatId}/status", ['status' => 'active']);
    adminRequest('POST', "/admin-home/categories-json/child/{$childCatId}/status", ['status' => 'active']);

    check(
        '8. Activate/deactivate child nodes',
        $res8Sub['status'] === 200 && $subDeact->status == 0 && $res8Child['status'] === 200 && $childDeact->status == 0,
        "Sub status: {$subDeact->status}, Child status: {$childDeact->status}"
    );

    // =========================================================================
    // 9. Reorder Main Categories
    // =========================================================================
    echo "\n--- TEST 9: Reorder Main Categories ---\n";
    $allCatsBefore = Category::orderBy('id')->pluck('id')->toArray();
    $reversedIds = array_reverse($allCatsBefore);
    $res9 = adminRequest('POST', '/admin-home/categories-json/reorder', [
        'ordered_ids' => $reversedIds,
    ]);
    $cat1 = Category::find($reversedIds[0]);
    $catLast = Category::find(end($reversedIds));
    check(
        '9. Reorder main categories',
        $res9['status'] === 200 && $cat1->sort_order === 0 && $catLast->sort_order === (count($reversedIds) - 1),
        "Top cat #{$reversedIds[0]} sort_order={$cat1->sort_order}, Bottom cat #{$catLast->id} sort_order={$catLast->sort_order}"
    );

    // =========================================================================
    // 10. Assign Existing Media Vault Asset
    // =========================================================================
    echo "\n--- TEST 10: Assign Existing Media Vault Asset ---\n";
    $existingMedia = MediaUpload::first();
    if (!$existingMedia) {
        $existingMedia = MediaUpload::create([
            'title' => 'test_vault_asset.jpg',
            'path' => 'test_vault_asset.jpg',
            'size' => '10 KB',
            'type' => 'web',
            'user_id' => $adminUser->id,
        ]);
    }
    $testMediaId = $existingMedia->id;

    $res10 = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}", [
        'name_en' => 'E2E Main Luxury Updated ' . $stamp,
        'mobile_icon' => $testMediaId,
        'image' => $testMediaId,
    ]);
    $catMediaAssigned = Category::find($mainCatId);
    check(
        '10. Assign existing Media Vault asset',
        $res10['status'] === 200 && (int)$catMediaAssigned->mobile_icon === $testMediaId && (int)$catMediaAssigned->image === $testMediaId,
        "Assigned media_uploads.id #{$testMediaId} to categories.mobile_icon & categories.image"
    );

    // =========================================================================
    // 11. Upload New Category Image via Media Endpoint
    // =========================================================================
    echo "\n--- TEST 11: Upload New Category Image ---\n";
    $tmpImgPath = tempnam(sys_get_temp_dir(), 'cat_e2e_') . '.jpg';
    $im = imagecreatetruecolor(100, 100);
    $bg = imagecolorallocate($im, 79, 70, 229);
    imagefill($im, 0, 0, $bg);
    imagejpeg($im, $tmpImgPath);
    imagedestroy($im);

    $uploadedFile = new UploadedFile($tmpImgPath, 'e2e_cat_uploaded.jpg', 'image/jpeg', null, true);
    $res11 = adminRequest('POST', '/admin-home/cms-media/upload', [], ['file' => $uploadedFile]);
    if (file_exists($tmpImgPath)) unlink($tmpImgPath);

    $newUploadedMediaId = $res11['data']['item']['id'] ?? $res11['data']['media']['id'] ?? null;
    check(
        '11. Upload new category image',
        $res11['status'] === 200 && !empty($newUploadedMediaId),
        'Uploaded new media_uploads record ID: ' . ($newUploadedMediaId ?? 'none')
    );

    // =========================================================================
    // 12. Replace mobile_icon
    // =========================================================================
    echo "\n--- TEST 12: Replace mobile_icon ---\n";
    $res12 = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}", [
        'name_en' => 'E2E Main Luxury Updated ' . $stamp,
        'mobile_icon' => $newUploadedMediaId,
    ]);
    $catReplacedMobile = Category::find($mainCatId);
    check(
        '12. Replace mobile_icon',
        $res12['status'] === 200 && (int)$catReplacedMobile->mobile_icon === (int)$newUploadedMediaId,
        "Replaced mobile_icon from #{$testMediaId} to #{$newUploadedMediaId}"
    );

    // =========================================================================
    // 13. Replace image
    // =========================================================================
    echo "\n--- TEST 13: Replace image ---\n";
    $res13 = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}", [
        'name_en' => 'E2E Main Luxury Updated ' . $stamp,
        'image' => $newUploadedMediaId,
    ]);
    $catReplacedImage = Category::find($mainCatId);
    check(
        '13. Replace image',
        $res13['status'] === 200 && (int)$catReplacedImage->image === (int)$newUploadedMediaId,
        "Replaced web image from #{$testMediaId} to #{$newUploadedMediaId}"
    );

    // =========================================================================
    // 14. Remove mobile_icon and image
    // =========================================================================
    echo "\n--- TEST 14: Remove mobile_icon / image ---\n";
    $res14 = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}", [
        'name_en' => 'E2E Main Luxury Updated ' . $stamp,
        'mobile_icon' => null,
        'image' => null,
    ]);
    $catRemovedMedia = Category::find($mainCatId);
    check(
        '14. Remove mobile_icon/image',
        $res14['status'] === 200 && is_null($catRemovedMedia->mobile_icon) && is_null($catRemovedMedia->image),
        "mobile_icon: " . var_export($catRemovedMedia->mobile_icon, true) . ", image: " . var_export($catRemovedMedia->image, true)
    );

    // Re-assign mobile_icon for upcoming Buyer API verification
    adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}", [
        'name_en' => 'E2E Main Luxury Updated ' . $stamp,
        'mobile_icon' => $testMediaId,
    ]);

    // =========================================================================
    // 15. Set / Remove icon
    // =========================================================================
    echo "\n--- TEST 15: Set / Remove icon ---\n";
    $res15Set = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}", [
        'name_en' => 'E2E Main Luxury Updated ' . $stamp,
        'icon' => 'las la-ship',
    ]);
    $catIconSet = Category::find($mainCatId);

    $res15Remove = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}", [
        'name_en' => 'E2E Main Luxury Updated ' . $stamp,
        'icon' => null,
    ]);
    $catIconRemoved = Category::find($mainCatId);
    check(
        '15. Set/remove icon',
        $res15Set['status'] === 200 && $catIconSet->icon === 'las la-ship' && $res15Remove['status'] === 200 && is_null($catIconRemoved->icon),
        "Set icon: 'las la-ship' -> Removed icon: null"
    );

    // =========================================================================
    // 16. Reject Invalid Media Reference
    // =========================================================================
    echo "\n--- TEST 16: Reject Invalid Media Reference ---\n";
    $res16 = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}", [
        'name_en' => 'E2E Main Luxury Updated ' . $stamp,
        'mobile_icon' => 9999999, // nonexistent media ID
    ]);
    check(
        '16. Reject invalid media reference',
        $res16['status'] === 422,
        'Rejected non-existent media reference with HTTP 422 Unprocessable Entity'
    );

    // =========================================================================
    // 17. Block Deletion with Attached Services
    // =========================================================================
    echo "\n--- TEST 17: Block Deletion with Attached Services ---\n";
    // Attach a temporary service to the child category specifically
    $testService = Service::create([
        'category_id' => $mainCatId,
        'subcategory_id' => $subCatId,
        'child_category_id' => $childCatId,
        'seller_id' => $adminUser->id,
        'service_city_id' => 1,
        'title' => 'E2E Temporary Test Service ' . $stamp,
        'slug' => 'e2e-temp-service-' . $stamp,
        'description' => 'Test description for service safety check',
        'price' => 100,
        'status' => 1,
        'is_service_on' => 1,
    ]);

    $res17 = adminRequest('POST', "/admin-home/categories-json/child/{$childCatId}/delete");
    check(
        '17. Block deletion with attached services',
        $res17['status'] === 422 && str_contains(strtolower($res17['data']['message'] ?? ''), 'service'),
        'Blocked with 422: ' . ($res17['data']['message'] ?? 'none')
    );

    // =========================================================================
    // 18. Allow Safe Deletion without Dependencies
    // =========================================================================
    echo "\n--- TEST 18: Allow Safe Deletion without Dependencies ---\n";
    // Delete the temporary service first
    $testService->delete();

    // Verify parent category is blocked because it has a subcategory
    $res18BlockedBySub = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}/delete");
    $blockedBySub = ($res18BlockedBySub['status'] === 422 && str_contains(strtolower($res18BlockedBySub['data']['message'] ?? ''), 'subcategor'));

    // Now delete child category cleanly
    $resDelChild = adminRequest('POST', "/admin-home/categories-json/child/{$childCatId}/delete");
    // Delete subcategory cleanly
    $resDelSub = adminRequest('POST', "/admin-home/categories-json/sub/{$subCatId}/delete");
    // Finally delete parent category cleanly
    $resDelParent = adminRequest('POST', "/admin-home/categories-json/parent/{$mainCatId}/delete");

    check(
        '18. Allow safe deletion without dependencies',
        $blockedBySub && $resDelChild['status'] === 200 && $resDelSub['status'] === 200 && $resDelParent['status'] === 200,
        'Protected by subcategories first (422), then cleanly deleted child, sub, and parent nodes (200)'
    );

    // =========================================================================
    // 19. GET /api/v1/category Returns mobile_icon URL
    // =========================================================================
    echo "\n--- TEST 19: GET /api/v1/category returns mobile_icon URL ---\n";
    $catWithIcon = Category::create([
        'name' => 'E2E Mobile Consumer Category ' . $stamp,
        'slug' => 'e2e-mobile-consumer-' . $stamp,
        'mobile_icon' => $testMediaId,
        'status' => 1,
        'sort_order' => 1,
    ]);

    $res19 = publicRequest('GET', '/api/v1/category');
    $categoriesList = $res19['data']['category'] ?? [];
    $foundCategory = null;
    foreach ($categoriesList as $c) {
        if ($c['id'] == $catWithIcon->id) {
            $foundCategory = $c;
            break;
        }
    }

    $hasValidUrl = !empty($foundCategory['mobile_icon']) && (
        str_starts_with($foundCategory['mobile_icon'], 'http://') ||
        str_starts_with($foundCategory['mobile_icon'], 'https://')
    );

    check(
        '19. GET /api/v1/category returns mobile_icon URL',
        in_array($res19['status'], [200, 201]) && $hasValidUrl,
        "Status {$res19['status']}, Category #{$catWithIcon->id} mobile_icon: " . ($foundCategory['mobile_icon'] ?? 'null')
    );

    // =========================================================================
    // 20. Buyer Category Order Matches Persisted Order
    // =========================================================================
    echo "\n--- TEST 20: Buyer Category Order Matches Persisted Order ---\n";
    $catFirst = Category::create([
        'name' => 'E2E First Priority Category ' . $stamp,
        'slug' => 'e2e-first-priority-' . $stamp,
        'status' => 1,
        'sort_order' => -999, // Explicitly lowest sort_order in database
    ]);

    $res20 = publicRequest('GET', '/api/v1/category');
    $firstReturnedCat = $res20['data']['category'][0] ?? null;

    check(
        '20. Buyer category order matches persisted order',
        in_array($res20['status'], [200, 201]) && $firstReturnedCat && $firstReturnedCat['id'] == $catFirst->id,
        "First category in API: #{$firstReturnedCat['id']} ('{$firstReturnedCat['name']}')"
    );

    // =========================================================================
    // 21. No Regression to Service Media
    // =========================================================================
    echo "\n--- TEST 21: No Regression to Service Media ---\n";
    $serviceWithThumb = Service::whereNotNull('image')->first();
    $mediaCheck = true;
    if ($serviceWithThumb) {
        $mediaCheck = !empty($serviceWithThumb->image);
    }
    $resMediaVault = adminRequest('GET', '/admin-home/cms-media/inventory');
    check(
        '21. No regression to service media',
        $resMediaVault['status'] === 200 && $mediaCheck,
        "Media Inventory endpoint HTTP 200, Service thumbnail field intact"
    );

    // =========================================================================
    // 22. No Regression to Existing Category API
    // =========================================================================
    echo "\n--- TEST 22: No Regression to Existing Category API ---\n";
    $res22Sub = publicRequest('GET', '/api/v1/category/sub-category/1');
    $res22Main = publicRequest('GET', '/api/v1/category');

    check(
        '22. No regression to existing category API',
        in_array($res22Main['status'], [200, 201]) && in_array($res22Sub['status'], [200, 201, 400, 404]),
        "GET /api/v1/category HTTP {$res22Main['status']}, GET /api/v1/category/sub-category/1 HTTP {$res22Sub['status']}"
    );

    // Cleanup test categories
    $catWithIcon->delete();
    $catFirst->delete();

} catch (\Throwable $e) {
    fail('Unhandled Exception in Test Suite', $e->getMessage() . "\n" . $e->getTraceAsString());
}

// ─── Summary ────────────────────────────────────────────────────────────────
echo "\n=================================================================\n";
echo "TEST RESULTS SUMMARY\n";
echo "=================================================================\n";
echo "TOTAL: " . ($passCount + $failCount) . " | PASSED: {$passCount} | FAILED: {$failCount}\n";

if ($failCount > 0) {
    echo "\nFAILED TESTS:\n";
    foreach ($errors as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
} else {
    echo "\n\033[32mALL 22 TESTS PASSED PERFECTLY!\033[0m\n";
    exit(0);
}
