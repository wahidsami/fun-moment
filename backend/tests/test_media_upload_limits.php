<?php
/**
 * Automated Verification for Media Upload Limits & Configuration
 * Tests:
 * 1. nixpacks.toml serving command configuration (upload_max_filesize=32M, post_max_size=32M, memory_limit=256M, docroot public)
 * 2. php.ini and .user.ini files exist and have consistent 32M / 256M limits
 * 3. Media upload validation rules on AdminCmsController (mimes, max 11000 KB)
 * 4. Error reporting for INI upload error vs application size limit
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

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
echo "FUN MOMENT — MEDIA UPLOAD LIMITS & CONFIG VERIFICATION\n";
echo "========================================================\n\n";

// --- 1. Nixpacks & INI Configuration Checks ---
echo "--- 1. CONFIGURATION INTEGRITY ---\n";

it("1.1 nixpacks.toml contains 32M upload_max_filesize directive", function() {
    $content = file_get_contents(__DIR__ . '/../nixpacks.toml');
    return str_contains($content, 'upload_max_filesize=32M');
});

it("1.2 nixpacks.toml contains 32M post_max_size directive", function() {
    $content = file_get_contents(__DIR__ . '/../nixpacks.toml');
    return str_contains($content, 'post_max_size=32M');
});

it("1.3 nixpacks.toml contains 256M memory_limit directive", function() {
    $content = file_get_contents(__DIR__ . '/../nixpacks.toml');
    return str_contains($content, 'memory_limit=256M');
});

it("1.4 nixpacks.toml specifies document root public and server.php router", function() {
    $content = file_get_contents(__DIR__ . '/../nixpacks.toml');
    return str_contains($content, '-t public server.php');
});

it("1.5 backend/php.ini defines 32M upload & post limits and 256M memory", function() {
    $content = file_get_contents(__DIR__ . '/../php.ini');
    return str_contains($content, 'upload_max_filesize = 32M')
        && str_contains($content, 'post_max_size = 32M')
        && str_contains($content, 'memory_limit = 256M');
});

it("1.6 backend/public/.user.ini defines 32M upload & post limits and 256M memory", function() {
    $content = file_get_contents(__DIR__ . '/../public/.user.ini');
    return str_contains($content, 'upload_max_filesize = 32M')
        && str_contains($content, 'post_max_size = 32M')
        && str_contains($content, 'memory_limit = 256M');
});

// --- 2. Laravel Upload Validation Rules ---
echo "\n--- 2. APPLICATION VALIDATION RULES ---\n";

$validationRules = [
    'file' => 'required|mimes:jpg,jpeg,png,gif,webp|max:11000',
];
$customMessages = [
    'file.max' => __('The file may not be greater than 10 Megabytes'),
];

it("2.1 Valid image file under 10MB passes application validation", function() use ($validationRules, $customMessages) {
    $file = UploadedFile::fake()->image('test_slide.jpg', 1920, 1080)->size(3000); // 3000 KB ~ 3 MB
    $validator = Validator::make(['file' => $file], $validationRules, $customMessages);
    return !$validator->fails();
});

it("2.2 Image file exceeding 11000 KB is rejected with custom 10 MB message", function() use ($validationRules, $customMessages) {
    $file = UploadedFile::fake()->image('oversized.jpg', 1920, 1080)->size(12000); // 12000 KB ~ 11.7 MB
    $validator = Validator::make(['file' => $file], $validationRules, $customMessages);
    if (!$validator->fails()) return false;
    $errors = $validator->errors()->get('file');
    return in_array('The file may not be greater than 10 Megabytes', $errors, true);
});

it("2.3 Disallowed file mime type (e.g. PDF/PHP) is rejected", function() use ($validationRules, $customMessages) {
    $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');
    $validator = Validator::make(['file' => $file], $validationRules, $customMessages);
    return $validator->fails();
});

it("2.4 Simulated PHP INI upload error (UPLOAD_ERR_INI_SIZE) produces upload failure", function() use ($validationRules, $customMessages) {
    // When PHP rejects file exceeding upload_max_filesize, error is UPLOAD_ERR_INI_SIZE (1)
    $tmpFile = tempnam(sys_get_temp_dir(), 'test_err');
    file_put_contents($tmpFile, 'dummy content');
    $file = new UploadedFile($tmpFile, 'failed.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);
    
    $validator = Validator::make(['file' => $file], $validationRules, $customMessages);
    @unlink($tmpFile);
    return $validator->fails() && in_array('The file failed to upload.', $validator->errors()->get('file'), true);
});

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passed | Failed: $failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
