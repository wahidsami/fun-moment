<?php
/**
 * Automated Verification for Carousel App Section Destinations
 * 
 * Tests:
 * 1. Controlled section registry & Admin lookups
 * 2. Creating promotional card targeting Music Marketplace
 * 3. Creating promotional card targeting other sections (all_categories, bookings, etc.)
 * 4. Backward-compatible category and external URL slide creation
 * 5. Editing existing cards without destination data loss
 * 6. Target validation rejecting invalid section keys
 * 7. Public API serialization & backward compatibility
 * 8. Safe fallback sanitization for unknown/corrupt destination values
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Category;
use App\Http\Controllers\AdminSliderApiController;
use App\Http\Controllers\Api\SliderController;
use App\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    } catch (\Throwable $e) {
        echo " [FAIL] $description -> " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "FUN MOMENT — CAROUSEL APP SECTION DESTINATIONS TEST\n";
echo "========================================================\n\n";

$admin = Admin::first();
if ($admin) {
    Auth::guard('admin')->setUser($admin);
}

$adminController = new AdminSliderApiController();
$publicController = $app->make(SliderController::class);

// --- 1. SECTION REGISTRY & LOOKUPS ---
echo "--- 1. SECTION REGISTRY & LOOKUPS ---\n";

it("1.1 Model exposes canonical SUPPORTED_APP_SECTIONS registry", function() {
    $sections = Slider::SUPPORTED_APP_SECTIONS;
    $keys = array_column($sections, 'key');
    return in_array('music_marketplace', $keys, true)
        && in_array('all_categories', $keys, true)
        && in_array('bookings', $keys, true)
        && in_array('discover', $keys, true)
        && in_array('saved', $keys, true)
        && in_array('wallet', $keys, true)
        && in_array('top_services', $keys, true);
});

it("1.2 Admin API index returns sections in lookups payload", function() use ($adminController) {
    $res = $adminController->index();
    $data = json_decode($res->getContent(), true);
    return isset($data['lookups']['sections'])
        && is_array($data['lookups']['sections'])
        && count($data['lookups']['sections']) >= 7
        && ($data['counts']['sections_count'] ?? 0) >= 7;
});

it("1.3 Dedicated lookups endpoint supports type=section and search query", function() use ($adminController) {
    $req = Request::create('/admin-home/sliders-json/lookups', 'GET', [
        'type' => 'section',
        'search' => 'music',
    ]);
    $res = $adminController->lookups($req);
    $data = json_decode($res->getContent(), true);
    $sections = $data['sections'] ?? [];
    return count($sections) === 1
        && ($sections[0]['key'] ?? '') === 'music_marketplace'
        && empty($data['services'])
        && empty($data['categories']);
});

// --- 2. SECTION DESTINATION CRUD ---
echo "\n--- 2. SECTION DESTINATION CRUD ---\n";

$createdSlideIds = [];

it("2.1 Admin can create a promotional card targeting Music Marketplace", function() use ($adminController, &$createdSlideIds) {
    $req = Request::create('/admin-home/sliders-json', 'POST', [
        'background_image' => 'assets/images/app/home/fun_moment_home_hero_01.png',
        'title' => 'Explore the Music Marketplace',
        'title_ar' => 'استكشف سوق الموسيقى والإنتاج الصوتي',
        'sub_title' => 'Book DJs, sound equipment and tracks',
        'sub_title_ar' => 'احجز خدمات الدي جي وهندسة الصوت',
        'btn_text_en' => 'Go to Marketplace',
        'btn_text_ar' => 'إلى سوق الموسيقى',
        'action_type' => 'section',
        'action_target' => 'music_marketplace',
        'status' => 1,
        'order' => 10,
    ]);
    $res = $adminController->store($req);
    $data = json_decode($res->getContent(), true);
    if (($data['status'] ?? '') !== 'success') return false;

    $slide = $data['slider'];
    $createdSlideIds[] = $slide['id'];

    return $slide['action_type'] === 'section'
        && $slide['action_target'] === 'music_marketplace'
        && ($slide['section_meta']['key'] ?? '') === 'music_marketplace'
        && ($slide['section_meta']['label_en'] ?? '') === 'Music Marketplace';
});

it("2.2 Admin can create a promotional card targeting All Categories section", function() use ($adminController, &$createdSlideIds) {
    $req = Request::create('/admin-home/sliders-json', 'POST', [
        'background_image' => 'assets/images/app/home/fun_moment_home_hero_01.png',
        'title' => 'Browse All Categories',
        'title_ar' => 'تصفح كافة التصنيفات',
        'action_type' => 'section',
        'action_target' => 'all_categories',
        'status' => 1,
        'order' => 11,
    ]);
    $res = $adminController->store($req);
    $data = json_decode($res->getContent(), true);
    if (($data['status'] ?? '') !== 'success') return false;

    $slide = $data['slider'];
    $createdSlideIds[] = $slide['id'];

    return $slide['action_type'] === 'section'
        && $slide['action_target'] === 'all_categories';
});

it("2.3 Admin can edit existing card destination without data loss", function() use ($adminController, &$createdSlideIds) {
    $slideId = end($createdSlideIds);
    $updateReq = Request::create("/admin-home/sliders-json/{$slideId}", 'POST', [
        'title' => 'Updated All Categories Title',
        'action_type' => 'section',
        'action_target' => 'bookings',
    ]);
    $res = $adminController->update($updateReq, $slideId);
    $data = json_decode($res->getContent(), true);
    if (($data['status'] ?? '') !== 'success') return false;

    $slide = $data['slider'];
    return $slide['title'] === 'Updated All Categories Title'
        && $slide['action_type'] === 'section'
        && $slide['action_target'] === 'bookings'
        && ($slide['section_meta']['key'] ?? '') === 'bookings';
});

// --- 3. BACKWARD COMPATIBILITY (CATEGORY, URL, SERVICE) ---
echo "\n--- 3. BACKWARD COMPATIBILITY ---\n";

it("3.1 Existing Category redirect destination functions properly", function() use ($adminController, &$createdSlideIds) {
    $category = Category::where('status', 1)->first() ?? Category::first();
    $catId = $category ? (string) $category->id : '1';

    $req = Request::create('/admin-home/sliders-json', 'POST', [
        'background_image' => 'assets/images/app/home/fun_moment_home_hero_01.png',
        'title' => 'Category Test Slide',
        'action_type' => 'category',
        'action_target' => $catId,
        'status' => 1,
    ]);
    $res = $adminController->store($req);
    $data = json_decode($res->getContent(), true);
    if (($data['status'] ?? '') !== 'success') return false;

    $slide = $data['slider'];
    $createdSlideIds[] = $slide['id'];

    return $slide['action_type'] === 'category'
        && (string) $slide['action_target'] === $catId;
});

it("3.2 External URL destination functions properly", function() use ($adminController, &$createdSlideIds) {
    $req = Request::create('/admin-home/sliders-json', 'POST', [
        'background_image' => 'assets/images/app/home/fun_moment_home_hero_01.png',
        'title' => 'External URL Test Slide',
        'action_type' => 'url',
        'action_target' => 'https://funmoments.com/terms-and-conditions',
        'status' => 1,
    ]);
    $res = $adminController->store($req);
    $data = json_decode($res->getContent(), true);
    if (($data['status'] ?? '') !== 'success') return false;

    $slide = $data['slider'];
    $createdSlideIds[] = $slide['id'];

    return $slide['action_type'] === 'url'
        && $slide['action_target'] === 'https://funmoments.com/terms-and-conditions';
});

// --- 4. VALIDATION & ERROR GUARDS ---
echo "\n--- 4. VALIDATION & ERROR GUARDS ---\n";

it("4.1 Rejects unsupported/invalid section key with HTTP 422", function() use ($adminController) {
    $caught422 = false;
    try {
        $req = Request::create('/admin-home/sliders-json', 'POST', [
            'background_image' => 'assets/images/app/home/sample.png',
            'title' => 'Malicious/Invalid Section Slide',
            'action_type' => 'section',
            'action_target' => 'unsupported_secret_admin_route',
        ]);
        $adminController->store($req);
    } catch (HttpException $e) {
        $caught422 = ($e->getStatusCode() === 422);
    }
    return $caught422;
});

it("4.2 Rejects empty section target with HTTP 422", function() use ($adminController) {
    $caught422 = false;
    try {
        $req = Request::create('/admin-home/sliders-json', 'POST', [
            'background_image' => 'assets/images/app/home/sample.png',
            'title' => 'Empty Section Slide',
            'action_type' => 'section',
            'action_target' => '',
        ]);
        $adminController->store($req);
    } catch (HttpException $e) {
        $caught422 = ($e->getStatusCode() === 422);
    }
    return $caught422;
});

// --- 5. PUBLIC API SERIALIZATION & FALLBACK SANITIZATION ---
echo "\n--- 5. PUBLIC API SERIALIZATION & FALLBACK ---\n";

it("5.1 Public /api/v1/slider correctly serializes section destinations", function() use ($publicController) {
    $req = Request::create('/api/v1/slider', 'GET', ['lang' => 'en']);
    $res = $publicController->slider($req);
    $data = json_decode($res->getContent(), true);
    $slides = $data['slider-details'] ?? [];

    $musicSlide = null;
    foreach ($slides as $s) {
        if (($s['action_type'] ?? '') === 'section' && ($s['action_target'] ?? '') === 'music_marketplace') {
            $musicSlide = $s;
            break;
        }
    }

    return $musicSlide !== null
        && $musicSlide['action_type'] === 'section'
        && $musicSlide['action_target'] === 'music_marketplace';
});

it("5.2 Public /api/v1/slider safely sanitizes corrupted/unknown section target to none", function() use ($publicController, &$createdSlideIds) {
    // Manually force an invalid section into DB to test public API sanitization fallback
    $badSlide = Slider::create([
        'background_image' => 'assets/images/app/home/sample.png',
        'title' => 'Corrupted Slide Test',
        'sub_title' => 'Testing fallback',
        'action_type' => 'section',
        'action_target' => 'non_existent_corrupted_key_9999',
        'status' => 1,
        'order' => 999,
    ]);
    $createdSlideIds[] = $badSlide->id;

    $req = Request::create('/api/v1/slider', 'GET', ['lang' => 'en']);
    $res = $publicController->slider($req);
    $data = json_decode($res->getContent(), true);
    $slides = $data['slider-details'] ?? [];

    $found = null;
    foreach ($slides as $s) {
        if ($s['id'] === $badSlide->id) {
            $found = $s;
            break;
        }
    }

    return $found !== null
        && $found['action_type'] === 'none'
        && $found['action_target'] === null;
});

// Clean up created slides
foreach ($createdSlideIds as $id) {
    Slider::where('id', $id)->delete();
}

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passed, Failed: $failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
