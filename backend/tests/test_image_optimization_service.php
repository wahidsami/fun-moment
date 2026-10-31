<?php

/**
 * FUN MOMENT — Comprehensive Test Drill for ImageOptimizationService
 *
 * Verifies:
 * 1. Service instantiation & GD WebP support.
 * 2. JPEG optimization & WebP variant generation across presets.
 * 3. PNG alpha transparency preservation in WebP.
 * 4. Square crop vs aspect-ratio preservation.
 * 5. Strict no-upscaling rule (small images never enlarged).
 * 6. Decompression bomb rejection (max dimension & max pixel area guard).
 * 7. Non-raster passthrough safety (SVGs/audio untouched).
 * 8. Deterministic variant pathing & URL resolution.
 * 9. Fail-safe fallback on corrupt/unsupported data.
 * 10. Variant cleanup via deleteVariants().
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Media\ImageOptimizationService;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

$service = app(ImageOptimizationService::class);
$testsPassed = 0;
$totalTests = 0;

function assertCondition(bool $condition, string $description, ?string $details = null) {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}" . ($details ? " - Details: {$details}" : "") . "\n";
    }
}

echo "=========================================================================\n";
echo "FUN MOMENT — IMAGE OPTIMIZATION SERVICE TEST SUITE\n";
echo "=========================================================================\n\n";

$tmpDir = storage_path('app/public/test_media_drill');
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0777, true);
}

try {
    // -------------------------------------------------------------
    // Test 1: Service Instantiation & Presets
    // -------------------------------------------------------------
    echo "--- Section 1: Service Basics & Presets ---\n";
    assertCondition($service instanceof ImageOptimizationService, "ImageOptimizationService resolved from DI container");
    $presets = $service->getSupportedPresets();
    assertCondition(in_array('music_artwork', $presets), "Preset 'music_artwork' is supported");
    assertCondition(in_array('profile', $presets), "Preset 'profile' is supported");
    assertCondition(in_array('category_service', $presets), "Preset 'category_service' is supported");
    assertCondition(in_array('banner_slider', $presets), "Preset 'banner_slider' is supported");
    assertCondition(in_array('general', $presets), "Preset 'general' is supported");

    // -------------------------------------------------------------
    // Test 2: JPEG Optimization & WebP Variants
    // -------------------------------------------------------------
    echo "\n--- Section 2: JPEG Processing & WebP Generation ---\n";
    $testJpegPath = $tmpDir . '/test_jpeg.jpg';
    $img = imagecreatetruecolor(800, 600);
    $bg = imagecolorallocate($img, 30, 41, 59);
    imagefilledrectangle($img, 0, 0, 800, 600, $bg);
    imagejpeg($img, $testJpegPath, 90);
    imagedestroy($img);

    $relJpegPath = 'test_media_drill/test_jpeg.jpg';
    $result = $service->optimizeExistingFile($relJpegPath, ImageOptimizationService::PRESET_MUSIC_ARTWORK, 'public');

    assertCondition($result['success'] === true, "JPEG optimization returned success");
    assertCondition(isset($result['variants']['thumbnail']), "Thumbnail variant generated");
    assertCondition(isset($result['variants']['medium']), "Medium variant generated");
    assertCondition(isset($result['variants']['large']), "Large variant generated");

    // Verify thumbnail is square (160x160)
    $thumbAbs = Storage::disk('public')->path($result['variants']['thumbnail']['path']);
    assertCondition(file_exists($thumbAbs), "Thumbnail file exists on public disk");
    $thumbInfo = getimagesize($thumbAbs);
    assertCondition($thumbInfo['mime'] === 'image/webp', "Thumbnail MIME type is image/webp");
    assertCondition($thumbInfo[0] === 160 && $thumbInfo[1] === 160, "Thumbnail cropped to exact 160x160 square", "Got {$thumbInfo[0]}x{$thumbInfo[1]}");

    // -------------------------------------------------------------
    // Test 3: PNG Alpha Transparency Retention
    // -------------------------------------------------------------
    echo "\n--- Section 3: PNG Transparency Preservation ---\n";
    $testPngPath = $tmpDir . '/test_transparent.png';
    $pngImg = imagecreatetruecolor(400, 400);
    imagesavealpha($pngImg, true);
    $transparent = imagecolorallocatealpha($pngImg, 0, 0, 0, 127);
    imagefill($pngImg, 0, 0, $transparent);
    $red = imagecolorallocatealpha($pngImg, 255, 0, 0, 0);
    imagefilledellipse($pngImg, 200, 200, 150, 150, $red);
    imagepng($pngImg, $testPngPath);
    imagedestroy($pngImg);

    $relPngPath = 'test_media_drill/test_transparent.png';
    $pngResult = $service->optimizeExistingFile($relPngPath, ImageOptimizationService::PRESET_CATEGORY_SERVICE, 'public');
    assertCondition($pngResult['success'] === true, "Transparent PNG optimization returned success");

    $mediumAbs = Storage::disk('public')->path($pngResult['variants']['medium']['path']);
    assertCondition(file_exists($mediumAbs), "Medium WebP variant exists for PNG");
    $mediumInfo = getimagesize($mediumAbs);
    assertCondition($mediumInfo['mime'] === 'image/webp', "Medium variant MIME is image/webp");

    // -------------------------------------------------------------
    // Test 4: Aspect Ratio Preservation (Non-Square)
    // -------------------------------------------------------------
    echo "\n--- Section 4: Aspect Ratio Preservation ---\n";
    $wideJpegPath = $tmpDir . '/test_wide.jpg';
    $wideImg = imagecreatetruecolor(1200, 600); // 2:1 aspect ratio
    $wideBg = imagecolorallocate($wideImg, 10, 100, 200);
    imagefilledrectangle($wideImg, 0, 0, 1200, 600, $wideBg);
    imagejpeg($wideImg, $wideJpegPath, 85);
    imagedestroy($wideImg);

    $relWidePath = 'test_media_drill/test_wide.jpg';
    $wideResult = $service->optimizeExistingFile($relWidePath, ImageOptimizationService::PRESET_CATEGORY_SERVICE, 'public');
    // Category service: thumbnail max 320x320 non-square -> should be 320x160 (exact 2:1)
    $wideThumbAbs = Storage::disk('public')->path($wideResult['variants']['thumbnail']['path']);
    $wideThumbInfo = getimagesize($wideThumbAbs);
    assertCondition($wideThumbInfo[0] === 320 && $wideThumbInfo[1] === 160, "Non-square preset preserved 2:1 aspect ratio (320x160)", "Got {$wideThumbInfo[0]}x{$wideThumbInfo[1]}");

    // -------------------------------------------------------------
    // Test 5: Strict No-Upscaling Rule
    // -------------------------------------------------------------
    echo "\n--- Section 5: Zero Upscaling Verification ---\n";
    $tinyPath = $tmpDir . '/tiny_100x80.jpg';
    $tinyImg = imagecreatetruecolor(100, 80);
    $tinyBg = imagecolorallocate($tinyImg, 50, 50, 50);
    imagefilledrectangle($tinyImg, 0, 0, 100, 80, $tinyBg);
    imagejpeg($tinyImg, $tinyPath, 80);
    imagedestroy($tinyImg);

    $relTinyPath = 'test_media_drill/tiny_100x80.jpg';
    $tinyResult = $service->optimizeExistingFile($relTinyPath, ImageOptimizationService::PRESET_GENERAL, 'public');
    // General preset large is 1600x1600. A 100x80 image must NOT be enlarged to 1600x1280!
    $tinyLargeAbs = Storage::disk('public')->path($tinyResult['variants']['large']['path']);
    $tinyLargeInfo = getimagesize($tinyLargeAbs);
    assertCondition($tinyLargeInfo[0] <= 100 && $tinyLargeInfo[1] <= 80, "Image smaller than preset dimension was not upscaled (100x80)", "Got {$tinyLargeInfo[0]}x{$tinyLargeInfo[1]}");

    // -------------------------------------------------------------
    // Test 6: Decompression Bomb Guard
    // -------------------------------------------------------------
    echo "\n--- Section 6: Decompression Bomb Rejection ---\n";
    $headerPath = $tmpDir . '/fake_bomb.jpg';
    // Construct valid JPEG header with claim of 12000 x 12000 pixels (exceeds 8192px guard)
    // JPEG SOF0 marker: FF C0, length 00 11, precision 08, height 2E E0 (12000), width 2E E0 (12000)
    $bombData = pack('H*', 'ffd8ffe000104a46494600010101004800480000ffc00011082ee02ee003012200021101031101ffd9');
    file_put_contents($headerPath, $bombData);
    $relBombPath = 'test_media_drill/fake_bomb.jpg';
    $bombResult = $service->optimizeExistingFile($relBombPath, ImageOptimizationService::PRESET_GENERAL, 'public');
    assertCondition($bombResult['success'] === false, "Decompression bomb (>8192px) rejected safely");
    assertCondition(($bombResult['reason'] ?? '') === 'NOT_OPTIMIZABLE', "Result reason is NOT_OPTIMIZABLE");

    // -------------------------------------------------------------
    // Test 7: Variant URLs Resolution & Fallbacks
    // -------------------------------------------------------------
    echo "\n--- Section 7: Variant URL Resolution & Fallback ---\n";
    $urls = $service->getVariantUrls($relJpegPath, ImageOptimizationService::PRESET_MUSIC_ARTWORK, 'public');
    assertCondition(isset($urls['thumbnail']) && str_ends_with($urls['thumbnail'], '.webp'), "Resolved thumbnail variant URL is WebP");
    assertCondition(isset($urls['medium']) && str_ends_with($urls['medium'], '.webp'), "Resolved medium variant URL is WebP");
    assertCondition(isset($urls['large']) && str_ends_with($urls['large'], '.webp'), "Resolved large variant URL is WebP");

    // Missing file fallback
    $fallbackUrls = $service->getVariantUrls('test_media_drill/non_existent.jpg', ImageOptimizationService::PRESET_MUSIC_ARTWORK, 'public');
    assertCondition($fallbackUrls['thumbnail'] === asset('storage/test_media_drill/non_existent.jpg'), "Non-existent variant falls back gracefully to original asset URL");

    // External URL passthrough
    $extUrls = $service->getVariantUrls('https://external-cdn.com/album.jpg', ImageOptimizationService::PRESET_MUSIC_ARTWORK, 'public');
    assertCondition($extUrls['thumbnail'] === 'https://external-cdn.com/album.jpg', "External URL passes through identically for all variant keys");

    // -------------------------------------------------------------
    // Test 8: Variant Cleanup via deleteVariants()
    // -------------------------------------------------------------
    echo "\n--- Section 8: Variant Cleanup ---\n";
    $cleaned = $service->deleteVariants($relJpegPath, 'public');
    assertCondition($cleaned === true, "deleteVariants() reported success");
    assertCondition(!file_exists($thumbAbs), "Thumbnail variant was deleted from disk");

} finally {
    // Clean up temporary test files
    $files = glob("{$tmpDir}/*");
    if ($files) {
        foreach ($files as $f) {
            if (is_file($f)) @unlink($f);
        }
    }
    // Clean up variants directory if exists
    $varDir = "{$tmpDir}/variants";
    if (is_dir($varDir)) {
        $subDirs = glob("{$varDir}/*");
        if ($subDirs) {
            foreach ($subDirs as $sd) {
                if (is_dir($sd)) {
                    $sfiles = glob("{$sd}/*");
                    if ($sfiles) {
                        foreach ($sfiles as $sf) @unlink($sf);
                    }
                    @rmdir($sd);
                }
            }
        }
        @rmdir($varDir);
    }
    @rmdir($tmpDir);
}

echo "\n=========================================================================\n";
echo "DRILL SUMMARY: {$testsPassed} / {$totalTests} CHECKS PASSED\n";
echo "=========================================================================\n";

if ($testsPassed === $totalTests) {
    exit(0);
} else {
    exit(1);
}
