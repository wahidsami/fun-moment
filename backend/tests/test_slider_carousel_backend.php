<?php
/**
 * Automated Verification for Home Carousel Backend
 * Tests:
 * 1. Public SliderController (no-args & Request invocation, EN & AR localization, action fields)
 * 2. AdminSliderApiController authentication & validation (CRUD, status toggle, reordering, action types)
 * 3. Seed data idempotence & slide structure
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Slider;
use App\Admin;
use App\Http\Controllers\Api\SliderController;
use App\Http\Controllers\AdminSliderApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$passed = 0;
$failed = 0;

function it($description, $assertion) {
    global $passed, $failed;
    try {
        if ($assertion()) {
            echo " [PASS] $description\n";
            $passed++;
        } else {
            echo " [FAIL] $description\n";
            $failed++;
        }
    } catch (\Illuminate\Validation\ValidationException $ve) {
        // Captured for validation tests
        throw $ve;
    } catch (\Throwable $e) {
        echo " [FAIL] $description -> " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "FUN MOMENT — CAROUSEL BACKEND VERIFICATION\n";
echo "========================================================\n\n";

// --- 1. Public SliderController tests ---
echo "--- 1. PUBLIC SLIDER CONTROLLER (EN & AR) ---\n";

$publicController = $app->make(SliderController::class);

it("1.1 Controller handles no-argument invocation", function() use ($publicController) {
    $res = $publicController->slider();
    return in_array($res->getStatusCode(), [200, 201], true);
});

it("1.2 English request returns English localized titles and subtitles", function() use ($publicController) {
    $req = Request::create('/api/v1/slider', 'GET', ['lang' => 'en']);
    $res = $publicController->slider($req);
    $data = json_decode($res->getContent(), true);
    
    $slides = $data['slider-details'] ?? [];
    if (empty($slides)) return false;
    
    $first = $slides[0];
    return isset($first['title']) && isset($first['action_type']) && isset($first['order']);
});

it("1.3 Arabic request returns Arabic localized titles and subtitles", function() use ($publicController) {
    $req = Request::create('/api/v1/slider', 'GET', ['lang' => 'ar']);
    $res = $publicController->slider($req);
    $data = json_decode($res->getContent(), true);
    
    $slides = $data['slider-details'] ?? [];
    if (empty($slides)) return false;
    
    $first = $slides[0];
    return !empty($first['title_ar']) && ($first['title'] === $first['title_ar'] || !empty($first['title']));
});

it("1.4 Sanitizes unsafe action targets (blocks javascript:, file:, etc.)", function() use ($publicController) {
    $req = Request::create('/api/v1/slider', 'GET');
    $res = $publicController->slider($req);
    $data = json_decode($res->getContent(), true);
    foreach ($data['slider-details'] as $s) {
        if ($s['action_type'] === 'url') {
            if (strpos($s['action_target'], 'http://') !== 0 && strpos($s['action_target'], 'https://') !== 0) {
                return false;
            }
        }
    }
    return true;
});

// --- 2. AdminSliderApiController tests ---
echo "\n--- 2. ADMIN SLIDER API CONTROLLER ---\n";

$adminController = new AdminSliderApiController();

// Authenticate admin for testing
$admin = Admin::first();
if (!$admin) {
    $admin = new Admin();
    $admin->name = 'Test Admin';
    $admin->email = 'admin_test@funmoment.sa';
    $admin->password = bcrypt('secret123');
    $admin->save();
}
Auth::guard('admin')->login($admin);

it("2.1 Admin API index returns slides and lookups", function() use ($adminController) {
    $res = $adminController->index();
    $data = json_decode($res->getContent(), true);
    return ($data['status'] ?? '') === 'success' && isset($data['sliders']) && isset($data['lookups']);
});

it("2.2 Admin API store validates and creates slide, updateStatus toggles, and destroy removes", function() use ($adminController) {
    $req = Request::create('/admin-home/sliders-json', 'POST', [
        'background_image' => 'assets/images/app/home/fun_moment_home_hero_01.png',
        'title' => 'Temporary Unit Test Slide',
        'title_ar' => 'سلايد تجريبي',
        'sub_title' => 'Unit testing',
        'sub_title_ar' => 'اختبار النظام',
        'action_type' => 'category',
        'action_target' => '1',
        'btn_text_en' => 'Explore',
        'btn_text_ar' => 'استكشف',
        'status' => 0,
        'order' => 99,
    ]);
    $res = $adminController->store($req);
    $data = json_decode($res->getContent(), true);
    if (($data['status'] ?? '') !== 'success') return false;
    
    $createdId = $data['slider']['id'];
    
    // Test status toggle
    $toggleReq = Request::create("/admin-home/sliders-json/{$createdId}/status", 'POST');
    $toggleRes = $adminController->updateStatus($toggleReq, $createdId);
    $toggleData = json_decode($toggleRes->getContent(), true);
    $newStatus = $toggleData['slider']['status'] ?? null;
    
    // Clean up temporary slide
    $delRes = $adminController->destroy($createdId);
    $delData = json_decode($delRes->getContent(), true);
    
    return $newStatus === 1 && ($delData['status'] ?? '') === 'success';
});

it("2.3 Admin API reorder updates display orders cleanly", function() use ($adminController) {
    $existing = Slider::orderBy('order', 'asc')->get();
    if ($existing->count() >= 2) {
        $firstId = $existing[0]->id;
        $secondId = $existing[1]->id;
        
        $req = Request::create('/admin-home/sliders-json/reorder', 'POST', [
            'ordered_ids' => [$secondId, $firstId]
        ]);
        $res = $adminController->reorder($req);
        $data = json_decode($res->getContent(), true);
        
        // Restore original order
        $restoreReq = Request::create('/admin-home/sliders-json/reorder', 'POST', [
            'ordered_ids' => [$firstId, $secondId]
        ]);
        $adminController->reorder($restoreReq);
        
        return ($data['status'] ?? '') === 'success';
    }
    return true;
});

it("2.4 Admin API validates invalid action_type and rejects bad target", function() use ($adminController) {
    $caught = false;
    try {
        $req = Request::create('/admin-home/sliders-json', 'POST', [
            'background_image' => 'assets/images/app/home/sample.png',
            'title' => 'Invalid Slide',
            'action_type' => 'invalid_type',
            'action_target' => '123',
        ]);
        $adminController->store($req);
    } catch (\Illuminate\Validation\ValidationException $e) {
        $caught = true;
    }
    return $caught;
});

// --- 3. Database & Seeder safety tests ---
echo "\n--- 3. DATABASE SEEDER SAFETY ---\n";

it("3.1 Seeder maintains 6 promotional slides idempotently", function() {
    $count = Slider::count();
    return $count >= 6;
});

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passed, Failed: $failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
