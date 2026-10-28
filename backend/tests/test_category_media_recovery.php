<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Category;
use App\Console\Commands\RestoreOfficialCategoryMediaCommand;
use App\MediaUpload;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$description}" . ($details ? " - {$details}" : '') . "\n";
        $failed++;
    }
}

echo "=========================================================================\n";
echo "FUN MOMENT — CATEGORY MEDIA PERSISTENCE RECOVERY TEST SUITE\n";
echo "=========================================================================\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "Active DB: " . config('database.connections.' . config('database.default') . '.database') . "\n\n";

// -----------------------------------------------------------------------------
// TEST 1: SAFETY GUARD ENFORCEMENT
// -----------------------------------------------------------------------------
echo "[1/4] Verifying Safety Guards Against Production Database...\n";

// Calling artisan categories:restore-media without --force on non-staging database must return non-zero exit code
$exitCode = Artisan::call('categories:restore-media');
$output = Artisan::output();
assertTest("Command aborts execution on non-staging database without --force (exit code != 0)", $exitCode !== 0, "Exit code: {$exitCode}");
assertTest("Command output specifies safety abort", str_contains($output, 'SAFETY ABORT') || str_contains($output, 'TARGET MISMATCH'));

// -----------------------------------------------------------------------------
// TEST 2: DRY-RUN EXECUTION
// -----------------------------------------------------------------------------
echo "\n[2/4] Testing Dry-Run Mode...\n";
$exitCodeDry = Artisan::call('categories:restore-media', ['--force' => true, '--dry-run' => true]);
$outputDry = Artisan::output();
assertTest("Dry run returns exit code 0", $exitCodeDry === 0);
assertTest("Dry run output confirms DRY-RUN mode", str_contains($outputDry, 'MODE: DRY-RUN'));
assertTest("Dry run contains all 8 official category slugs", 
    str_contains($outputDry, 'music-dj') &&
    str_contains($outputDry, 'food-hospitality') &&
    str_contains($outputDry, 'sound-lighting') &&
    str_contains($outputDry, 'event-setup-equipment') &&
    str_contains($outputDry, 'decor-event-styling') &&
    str_contains($outputDry, 'photography-video') &&
    str_contains($outputDry, 'entertainment-activities') &&
    str_contains($outputDry, 'body-guards')
);

// -----------------------------------------------------------------------------
// TEST 3: TRANSACTIONAL RECOVERY DRILL (ISOLATED RUN WITH ROLLBACK)
// -----------------------------------------------------------------------------
echo "\n[3/4] Testing Idempotent Media Attachment inside Isolated Transaction...\n";

DB::beginTransaction();

try {
    // Ensure 8 categories exist inside transaction
    foreach (RestoreOfficialCategoryMediaCommand::OFFICIAL_CATEGORY_MEDIA as $slug => $meta) {
        $cat = Category::where('slug', $slug)->first();
        if (!$cat) {
            Category::create([
                'name' => $meta['name'],
                'name_ar' => 'اختبار ' . $meta['name'],
                'slug' => $slug,
                'sort_order' => 10,
                'status' => 1,
                'image' => null,
                'mobile_icon' => null,
            ]);
        }
    }

    // Initial check: mobile_icon should currently be null or detached
    $catBefore = Category::where('slug', 'music-dj')->first();
    assertTest("Category 'music-dj' exists prior to media restore", $catBefore !== null);

    // Execute recovery command with --force
    $exitCodeRun = Artisan::call('categories:restore-media', ['--force' => true]);
    $outputRun = Artisan::output();
    assertTest("Recovery execution returns exit code 0", $exitCodeRun === 0);

    // Verify media_uploads records created
    $expectedFiles = RestoreOfficialCategoryMediaCommand::OFFICIAL_CATEGORY_MEDIA;
    foreach ($expectedFiles as $slug => $meta) {
        $media = MediaUpload::where('path', $meta['file'])->first();
        assertTest("Media record exists for '{$meta['file']}' ({$slug})", $media !== null);
        if ($media) {
            assertTest("Media record for '{$slug}' has correct title", $media->title === $meta['title']);
            assertTest("Media record for '{$slug}' has dimensions", !empty($media->dimensions));
            assertTest("Media record for '{$slug}' has size", !empty($media->size));
            assertTest("Media record for '{$slug}' is type 'admin'", $media->type === 'admin');

            $cat = Category::where('slug', $slug)->orderBy('id', 'asc')->first();
            assertTest("Category '{$slug}' is linked to media_uploads ID {$media->id}", 
                (string) $cat->mobile_icon === (string) $media->id && (string) $cat->image === (string) $media->id
            );
            assertTest("Category '{$slug}' preserves sort order", (int) $cat->sort_order >= 0);
            assertTest("Category '{$slug}' preserves Arabic name", !empty($cat->name_ar));
            assertTest("Category '{$slug}' remains active", (int) $cat->status === 1);
        }
    }

    // -----------------------------------------------------------------------------
    // TEST 4: IDEMPOTENCY DRILL (SECOND RUN)
    // -----------------------------------------------------------------------------
    echo "\n[4/4] Verifying Idempotency on Second Run...\n";

    $mediaCountBefore = MediaUpload::whereIn('path', array_column($expectedFiles, 'file'))->count();
    assertTest("Initial media count equals 8", $mediaCountBefore === 8);

    // Run again
    $exitCodeRun2 = Artisan::call('categories:restore-media', ['--force' => true]);
    assertTest("Second run returns exit code 0", $exitCodeRun2 === 0);

    $mediaCountAfter = MediaUpload::whereIn('path', array_column($expectedFiles, 'file'))->count();
    assertTest("Second run created zero duplicate media records (count remains 8)", $mediaCountAfter === 8);

    foreach ($expectedFiles as $slug => $meta) {
        $cat = Category::where('slug', $slug)->orderBy('id', 'asc')->first();
        $media = MediaUpload::where('path', $meta['file'])->first();
        assertTest("Category '{$slug}' maintains identical association ({$media->id})", (string) $cat->mobile_icon === (string) $media->id);
    }

} finally {
    DB::rollBack();
    echo "\n[ROLLBACK] Database transaction safely rolled back. Zero permanent database mutations.\n";
}

echo "=========================================================================\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "=========================================================================\n";

if ($failed > 0) {
    exit(1);
}

