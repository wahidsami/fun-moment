<?php
/**
 * FUN MOMENT — STORAGE LINK & COVER SERVING VERIFICATION DRILL
 * Tests public disk symlinking, server.php routing, and image magic bytes.
 */

declare(strict_types=1);

echo "========================================================================\n";
echo "FUN MOMENT — STORAGE LINK & COVER SERVING DRILL\n";
echo "========================================================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertCheck(bool $condition, string $message): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$message}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$message}\n";
        $testsFailed++;
    }
}

// 1. Directory Structure Tests
echo "--- Section 1: Storage Directory Structure ---\n";
$baseDir = dirname(__DIR__);
$publicDir = $baseDir . '/public';
$storageDir = $baseDir . '/storage';
$appPublicDir = $storageDir . '/app/public';
$musicCoversDir = $appPublicDir . '/music_covers';

assertCheck(is_dir($publicDir), "public directory exists ({$publicDir})");
assertCheck(is_dir($storageDir), "storage directory exists ({$storageDir})");

if (!is_dir($musicCoversDir)) {
    mkdir($musicCoversDir, 0755, true);
}
assertCheck(is_dir($musicCoversDir), "storage/app/public/music_covers exists");

// 2. Symlink Mechanics Drill
echo "\n--- Section 2: Storage Symlink Creation & Idempotency ---\n";
$symlinkPath = $publicDir . '/test_storage_link';

if (file_exists($symlinkPath) || is_link($symlinkPath)) {
    if (is_link($symlinkPath)) {
        @unlink($symlinkPath);
    } else {
        @rmdir($symlinkPath);
    }
}

$isWindows = (PHP_OS_FAMILY === 'Windows');
$linkResult = false;

if ($isWindows) {
    // On Windows, test creating directory junction or symlink
    $linkResult = @symlink($appPublicDir, $symlinkPath);
    if (!$linkResult) {
        // Fallback for Windows without developer mode: mklink /J
        $cmd = sprintf('cmd /c mklink /J "%s" "%s" 2>NUL', str_replace('/', '\\', $symlinkPath), str_replace('/', '\\', $appPublicDir));
        exec($cmd, $out, $ret);
        $linkResult = ($ret === 0 && file_exists($symlinkPath));
    }
} else {
    // Native POSIX symlink
    $linkResult = @symlink($appPublicDir, $symlinkPath);
}

assertCheck($linkResult, "Directory link created successfully (POSIX symlink or Windows junction)");
assertCheck(file_exists($symlinkPath) && is_dir($symlinkPath), "Linked path resolves as valid directory");

// Drill B: Test resolving file inside linked path
$testJpgName = 'test_sample_' . uniqid() . '.jpg';
$testJpgPath = $musicCoversDir . '/' . $testJpgName;
// Authentic 1x1 JPEG binary bytes
$jpegBytes = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x01\x00\x60\x00\x60\x00\x00\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C\x20\x24\x2E\x27\x20\x22\x2C\x23\x1C\x1C\x28\x37\x29\x2C\x30\x31\x34\x34\x34\x1F\x27\x39\x3D\x38\x32\x3C\x2E\x33\x34\x32\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x1F\x00\x00\x01\x05\x01\x01\x01\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xBE\x00\xFF\xD9";
file_put_contents($testJpgPath, $jpegBytes);

$resolvedViaLink = $symlinkPath . '/music_covers/' . $testJpgName;
assertCheck(file_exists($resolvedViaLink), "File in target is accessible via link path");

// 3. Image Magic Byte Verification
echo "\n--- Section 3: Image Magic Header Byte Inspection ---\n";
$handle = fopen($testJpgPath, 'rb');
$header = fread($handle, 3);
fclose($handle);

assertCheck(bin2hex($header) === 'ffd8ff', "JPEG header matches binary magic signature 0xFFD8FF");

// Authentic 1x1 PNG binary bytes
$pngBytes = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A\x00\x00\x00\x0D\x49\x48\x44\x52\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1F\x15\xC4\x89\x00\x00\x00\x0D\x49\x44\x41\x54\x78\x9C\x63\x00\x01\x00\x00\x05\x00\x01\x0D\x0A\x2D\xB4\x00\x00\x00\x00\x49\x45\x4E\x44\xAE\x42\x60\x82";
$testPngName = 'test_sample_' . uniqid() . '.png';
$testPngPath = $musicCoversDir . '/' . $testPngName;
file_put_contents($testPngPath, $pngBytes);

$handlePng = fopen($testPngPath, 'rb');
$headerPng = fread($handlePng, 4);
fclose($handlePng);
assertCheck(bin2hex($headerPng) === '89504e47', "PNG header matches binary magic signature 0x89504E47");

// 4. Server.php Emulation Routing Logic
echo "\n--- Section 4: server.php Static Serving vs Laravel Fallback Logic ---\n";
// server.php checks: if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) { return false; }
$testUriExisting = '/test_storage_link/music_covers/' . $testJpgName;
$checkExisting = ($testUriExisting !== '/' && file_exists($publicDir . $testUriExisting));
assertCheck($checkExisting === true, "server.php correctly serves static file directly (returns false, bypassing Laravel)");

$testUriMissing = '/storage/music_covers/non_existent_file.jpg';
$checkMissing = ($testUriMissing !== '/' && file_exists($publicDir . $testUriMissing));
assertCheck($checkMissing === false, "server.php correctly falls back to index.php when symlink/file is absent");

// 5. Cleanup
echo "\n--- Section 5: Temporary Artifacts Cleanup ---\n";
@unlink($testJpgPath);
@unlink($testPngPath);
if ($isWindows && is_dir($symlinkPath) && !is_link($symlinkPath)) {
    @rmdir($symlinkPath);
} else {
    @unlink($symlinkPath);
}
assertCheck(!file_exists($testJpgPath), "Cleaned up temporary JPEG file");
assertCheck(!file_exists($testPngPath), "Cleaned up temporary PNG file");
assertCheck(!file_exists($symlinkPath), "Cleaned up temporary test symlink/junction");

echo "\n========================================================================\n";
echo "STORAGE LINK DRILL RESULTS:\n";
echo "  Passed: {$testsPassed}\n";
echo "  Failed: {$testsFailed}\n";
echo "========================================================================\n";

if ($testsFailed > 0) {
    exit(1);
}
exit(0);
