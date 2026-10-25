<?php

/**
 * FUN MOMENT — Phase 3A: Admin Music Marketplace Settings Test Suite
 *
 * Validates:
 * 1. Authorized administrator settings reads & writes via AdminMusicSettingController
 * 2. Unauthenticated and unauthorized access rejection (401 / 403)
 * 3. Strict server-side validation rules (bounds, formats, numeric limits)
 * 4. Independent AAC and M4A format configuration
 * 5. Settings-to-preview-regeneration integration via updateWithSideEffects()
 * 6. Duplicate-free, 0-job dispatch when values remain unchanged
 * 7. Secure response shapes without leaking credentials or private filesystem paths
 * 8. Public runtime config endpoint (/api/v1/music/config)
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Http\Controllers\AdminMusicSettingController;
use App\Http\Controllers\Api\MusicStreamController;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\AudioProcessorInterface;
use App\Services\Music\MusicAudioProcessingService;
use App\Services\Music\MusicStorageService;
use App\Services\Music\MusicStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

class SimpleTestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            $this->failures[] = $message;
            echo "  [FAIL] {$message}\n";
        }
    }

    public function getPassed(): int { return $this->passed; }
    public function getFailed(): int { return $this->failed; }
    public function getFailures(): array { return $this->failures; }
}

$runner = new SimpleTestRunner();

echo "========================================================================\n";
echo "FUN MOMENT — PHASE 3A ADMIN MUSIC SETTINGS TEST SUITE\n";
echo "========================================================================\n\n";

// Setup Test Admin Accounts with status = 1
$superAdmin = Admin::where('email', 'phase3a_superadmin@test.com')->first();
if (!$superAdmin) {
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Phase 3A Super Admin',
        'email' => 'phase3a_superadmin@test.com',
        'username' => 'phase3a_super',
        'password' => bcrypt('secret123'),
        'role' => 'Super Admin',
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $superAdmin = Admin::find($adminId);
}

$limitedAdmin = Admin::where('email', 'phase3a_limited@test.com')->first();
if (!$limitedAdmin) {
    $adminId = DB::table('admins')->insertGetId([
        'name' => 'Phase 3A Limited Admin',
        'email' => 'phase3a_limited@test.com',
        'username' => 'phase3a_limited',
        'password' => bcrypt('secret123'),
        'role' => 'support_agent',
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $limitedAdmin = Admin::find($adminId);
}

// Bind mock audio processor to safely track regeneration dispatches in tests
$mockProcessor = new class implements AudioProcessorInterface {
    public function isAvailable(): bool { return true; }
    public function probeMetadata(string $absolutePath): \App\Services\Music\AudioMetadata {
        return new \App\Services\Music\AudioMetadata(180, 'mp3', 192, 44100, 2);
    }
    public function generatePreview(string $sourcePath, string $outputPath, int $durationSeconds, bool $applyFade = true): bool {
        @file_put_contents($outputPath, 'mock_preview_data');
        return true;
    }
    public function generateStreamingRendition(string $sourcePath, string $outputPath, int $targetBitrateKbps = 192): bool {
        @file_put_contents($outputPath, 'mock_stream_data');
        return true;
    }
};

$storageService = app(MusicStorageService::class);
$processingService = new MusicAudioProcessingService($storageService, $mockProcessor);
app()->instance(MusicAudioProcessingService::class, $processingService);
$adminController = app(AdminMusicSettingController::class);

// ----------------------------------------------------------------------
// 1. Authorized Admin Settings Reads
// ----------------------------------------------------------------------
echo "--- 1. Testing Authorized Admin Settings Reads ---\n";

Auth::guard('admin')->setUser($superAdmin);

$reqIndex = Request::create('/admin-home/music-settings-json', 'GET');
$resIndex = $adminController->index($reqIndex);
$payloadIndex = json_decode($resIndex->getContent(), true);

$runner->assert($resIndex->getStatusCode() === 200, 'GET /admin-home/music-settings-json returns HTTP 200 for authorized Super Admin');
$runner->assert($payloadIndex['success'] === true, 'Response contains success = true');
$runner->assert(isset($payloadIndex['data']['music_marketplace_enabled']), 'Payload contains music_marketplace_enabled');
$runner->assert(isset($payloadIndex['data']['paid_preview_duration_seconds']), 'Payload contains paid_preview_duration_seconds');
$runner->assert(isset($payloadIndex['data']['max_audio_upload_mb']), 'Payload contains max_audio_upload_mb');
$runner->assert(isset($payloadIndex['data']['allowed_audio_formats']), 'Payload contains allowed_audio_formats array');
$runner->assert(isset($payloadIndex['runtime']['php_max_upload_mb']), 'Payload contains runtime php_max_upload_mb');
$runner->assert(isset($payloadIndex['runtime']['effective_max_upload_mb']), 'Payload contains runtime effective_max_upload_mb');
$runner->assert($payloadIndex['runtime']['server_clamped_preview_min'] === 5, 'Runtime reports clamped preview min as 5s');
$runner->assert($payloadIndex['runtime']['server_clamped_preview_max'] === 60, 'Runtime reports clamped preview max as 60s');

// ----------------------------------------------------------------------
// 2. Authentication & Authorization Guards
// ----------------------------------------------------------------------
echo "\n--- 2. Testing Authentication & Authorization Guards ---\n";

// Unauthenticated request
Auth::guard('admin')->logout();

$reqUnauth = Request::create('/admin-home/music-settings-json', 'GET');
$resUnauth = $adminController->index($reqUnauth);
$runner->assert($resUnauth->getStatusCode() === 403, 'Unauthenticated access to settings endpoint is strictly denied with HTTP 403');

// Limited Admin (support_agent role without settings permission)
Auth::guard('admin')->setUser($limitedAdmin);

$reqLimited = Request::create('/admin-home/music-settings-json', 'GET');
$resLimited = $adminController->index($reqLimited);
$runner->assert($resLimited->getStatusCode() === 403, 'Unauthorized admin role without settings privileges is rejected with HTTP 403');
$runner->assert(str_contains($resLimited->getContent(), 'FORBIDDEN'), 'Forbidden error code returned in response');

$reqLimitedPost = Request::create('/admin-home/music-settings-json', 'POST', [
    'music_marketplace_enabled' => '1',
    'paid_preview_duration_seconds' => 30,
    'max_audio_upload_mb' => 25,
    'platform_commission_rate' => 15.0,
    'allowed_audio_formats' => 'mp3,wav',
    'seller_submissions_enabled' => '1',
    'seller_tracks_require_approval' => '1',
    'offer_free_enabled' => '1',
    'offer_rent_enabled' => '1',
    'offer_sale_enabled' => '1',
    'default_rental_duration_days' => 7,
]);
$resLimitedPost = $adminController->update($reqLimitedPost);
$runner->assert($resLimitedPost->getStatusCode() === 403, 'Unauthorized admin write attempt is strictly rejected with HTTP 403');

// ----------------------------------------------------------------------
// 3. Authorized Settings Updates & Persistence
// ----------------------------------------------------------------------
echo "\n--- 3. Testing Authorized Settings Updates & Persistence ---\n";

Auth::guard('admin')->setUser($superAdmin);

$updateData = [
    'music_marketplace_enabled' => '1',
    'paid_preview_duration_seconds' => 25,
    'max_audio_upload_mb' => 30,
    'platform_commission_rate' => 12.50,
    'allowed_audio_formats' => ['mp3', 'wav', 'aac', 'flac', 'm4a'],
    'seller_submissions_enabled' => '1',
    'seller_tracks_require_approval' => '1',
    'offer_free_enabled' => '1',
    'offer_rent_enabled' => '1',
    'offer_sale_enabled' => '1',
    'default_rental_duration_days' => 14,
];

$reqUpdate = Request::create('/admin-home/music-settings-json', 'POST', $updateData);
$resUpdate = $adminController->update($reqUpdate);
$payloadUpdate = json_decode($resUpdate->getContent(), true);

$runner->assert($resUpdate->getStatusCode() === 200, 'POST /admin-home/music-settings-json returns HTTP 200 on valid update');
$runner->assert($payloadUpdate['success'] === true, 'Update response contains success = true');
$runner->assert($payloadUpdate['data']['music_marketplace_enabled'] === true, 'music_marketplace_enabled persisted as true');
$runner->assert($payloadUpdate['data']['paid_preview_duration_seconds'] === 25, 'paid_preview_duration_seconds updated to 25');
$runner->assert($payloadUpdate['data']['default_rental_duration_days'] === 14, 'default_rental_duration_days updated to 14');
$runner->assert($payloadUpdate['data']['platform_commission_rate'] === 12.50, 'platform_commission_rate updated to 12.50%');

// ----------------------------------------------------------------------
// 4. Server-Side Validation Rules
// ----------------------------------------------------------------------
echo "\n--- 4. Testing Server-Side Validation Rules ---\n";

// Test preview duration below minimum (4s < 5s)
try {
    $reqInvalidPreviewLow = Request::create('/admin-home/music-settings-json', 'POST', array_merge($updateData, [
        'paid_preview_duration_seconds' => 4,
    ]));
    $resInvalidPreviewLow = $adminController->update($reqInvalidPreviewLow);
    $runner->assert(false, 'Preview duration < 5 should fail validation');
} catch (\Illuminate\Validation\ValidationException $e) {
    $runner->assert(isset($e->errors()['paid_preview_duration_seconds']), 'Preview duration < 5s is strictly rejected by server validation');
}

// Test preview duration above maximum (65s > 60s)
try {
    $reqInvalidPreviewHigh = Request::create('/admin-home/music-settings-json', 'POST', array_merge($updateData, [
        'paid_preview_duration_seconds' => 65,
    ]));
    $resInvalidPreviewHigh = $adminController->update($reqInvalidPreviewHigh);
    $runner->assert(false, 'Preview duration > 60 should fail validation');
} catch (\Illuminate\Validation\ValidationException $e) {
    $runner->assert(isset($e->errors()['paid_preview_duration_seconds']), 'Preview duration > 60s is strictly rejected by server validation');
}

// Test negative commission
try {
    $reqInvalidComm = Request::create('/admin-home/music-settings-json', 'POST', array_merge($updateData, [
        'platform_commission_rate' => -5.0,
    ]));
    $resInvalidComm = $adminController->update($reqInvalidComm);
    $runner->assert(false, 'Negative commission should fail validation');
} catch (\Illuminate\Validation\ValidationException $e) {
    $runner->assert(isset($e->errors()['platform_commission_rate']), 'Negative commission rate is strictly rejected by server validation');
}

// Test unsupported audio format token (e.g. 'wma')
$reqInvalidFormat = Request::create('/admin-home/music-settings-json', 'POST', array_merge($updateData, [
    'allowed_audio_formats' => 'mp3,wav,wma',
]));
$resInvalidFormat = $adminController->update($reqInvalidFormat);
$runner->assert($resInvalidFormat->getStatusCode() === 422, 'Unsupported audio format token is rejected with HTTP 422');
$runner->assert(str_contains($resInvalidFormat->getContent(), 'UNSUPPORTED_FORMAT'), 'Response indicates UNSUPPORTED_FORMAT error code');

// Test empty audio format
$reqEmptyFormat = Request::create('/admin-home/music-settings-json', 'POST', array_merge($updateData, [
    'allowed_audio_formats' => '',
]));
try {
    $resEmptyFormat = $adminController->update($reqEmptyFormat);
    $runner->assert($resEmptyFormat->getStatusCode() === 422, 'Empty audio format string is rejected');
} catch (\Illuminate\Validation\ValidationException $e) {
    $runner->assert(isset($e->errors()['allowed_audio_formats']), 'Empty audio format is rejected by validation');
}

// Test upload limit exceeding PHP runtime ceiling
$phpCeilingMb = MusicSetting::getServerMaxUploadCeilingMb();
try {
    $reqExcessiveUpload = Request::create('/admin-home/music-settings-json', 'POST', array_merge($updateData, [
        'max_audio_upload_mb' => $phpCeilingMb + 10,
    ]));
    $resExcessiveUpload = $adminController->update($reqExcessiveUpload);
    $runner->assert(false, 'Upload limit exceeding PHP runtime ceiling should fail validation');
} catch (\Illuminate\Validation\ValidationException $e) {
    $runner->assert(isset($e->errors()['max_audio_upload_mb']), 'Configured upload limit exceeding PHP runtime ceiling is strictly rejected by server validation');
    $runner->assert(str_contains($e->errors()['max_audio_upload_mb'][0], (string) $phpCeilingMb), 'Validation error message informs admin of exact runtime ceiling');
}

// ----------------------------------------------------------------------
// 5. Independent AAC and M4A Configuration
// ----------------------------------------------------------------------
echo "\n--- 5. Testing Independent AAC and M4A Configuration ---\n";

// Disable M4A while keeping AAC
$reqDisableM4A = Request::create('/admin-home/music-settings-json', 'POST', array_merge($updateData, [
    'allowed_audio_formats' => ['mp3', 'wav', 'aac', 'flac'],
]));
$resDisableM4A = $adminController->update($reqDisableM4A);
$payloadDisableM4A = json_decode($resDisableM4A->getContent(), true);

$runner->assert(!in_array('m4a', $payloadDisableM4A['data']['allowed_audio_formats'], true), 'M4A is excluded from allowed formats');
$runner->assert(in_array('aac', $payloadDisableM4A['data']['allowed_audio_formats'], true), 'AAC remains independently enabled');
$runner->assert(!MusicSetting::isAudioFormatAllowed('m4a'), 'MusicSetting::isAudioFormatAllowed(m4a) returns false');
$runner->assert(MusicSetting::isAudioFormatAllowed('aac'), 'MusicSetting::isAudioFormatAllowed(aac) returns true');

// Disable AAC while keeping M4A
$reqDisableAAC = Request::create('/admin-home/music-settings-json', 'POST', array_merge($updateData, [
    'allowed_audio_formats' => ['mp3', 'wav', 'm4a', 'flac'],
]));
$resDisableAAC = $adminController->update($reqDisableAAC);
$payloadDisableAAC = json_decode($resDisableAAC->getContent(), true);

$runner->assert(!in_array('aac', $payloadDisableAAC['data']['allowed_audio_formats'], true), 'AAC is excluded from allowed formats');
$runner->assert(in_array('m4a', $payloadDisableAAC['data']['allowed_audio_formats'], true), 'M4A remains independently enabled');
$runner->assert(MusicSetting::isAudioFormatAllowed('m4a'), 'MusicSetting::isAudioFormatAllowed(m4a) returns true');
$runner->assert(!MusicSetting::isAudioFormatAllowed('aac'), 'MusicSetting::isAudioFormatAllowed(aac) returns false');

// ----------------------------------------------------------------------
// 6. Preview Duration Update & Safe Job Dispatch Lifecycle
// ----------------------------------------------------------------------
echo "\n--- 6. Testing Preview Duration Update & Safe Job Dispatch Lifecycle ---\n";

// Clean up any orphaned test tracks from prior runs
MusicTrack::where('audio_path_original', 'masters/test3a.wav')->delete();

// Create track with outdated preview duration to trigger regeneration
$sellerUser = \App\User::firstOrCreate(
    ['email' => 'phase3a_seller@test.com'],
    ['name' => 'Phase 3A Seller', 'username' => 'phase3aseller', 'password' => bcrypt('secret'), 'user_type' => 1]
);

$genre = \App\MusicGenre::first() ?: \App\MusicGenre::create([
    'name_en' => 'Electronic',
    'name_ar' => 'إلكتروني',
    'slug' => 'electronic-test-3a',
    'sort_order' => 1,
    'status' => 1,
]);

$tempMaster = tempnam(sys_get_temp_dir(), 'test_master_');
file_put_contents($tempMaster, 'dummy_master_audio_payload');
$masterResult = $storageService->storeMaster($tempMaster, 'test3a.mp3', 'mp3');
@unlink($tempMaster);

$tempPrev = tempnam(sys_get_temp_dir(), 'test_prev_');
file_put_contents($tempPrev, 'dummy_preview_audio_payload');
$storedPreview = $storageService->storePreview($tempPrev, 20, 1);
@unlink($tempPrev);

$testTrack = MusicTrack::create([
    'title_en' => 'Phase 3A Regeneration Track',
    'title_ar' => 'مسار اختبار إعادة التوليد',
    'artist_name' => 'Test Artist',
    'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
    'seller_id' => $sellerUser->id,
    'genre_id' => $genre->id,
    'audio_path_original' => $masterResult['storage_path'],
    'audio_path_preview' => $storedPreview,
    'duration_seconds' => 180,
    'preview_duration_seconds' => 20, // Outdated when setting becomes 35
    'media_status' => MusicTrack::MEDIA_STATUS_READY,
    'preview_version' => 1,
    'status' => MusicTrack::STATUS_APPROVED,
]);

// Update preview duration from 25 to 35
$reqChangeDuration = Request::create('/admin-home/music-settings-json', 'POST', array_merge($updateData, [
    'paid_preview_duration_seconds' => 35,
]));
$resChangeDuration = $adminController->update($reqChangeDuration);
$payloadChangeDuration = json_decode($resChangeDuration->getContent(), true);

$runner->assert($payloadChangeDuration['success'] === true, 'Preview duration updated to 35s');
$runner->assert(isset($payloadChangeDuration['dispatched_regeneration_jobs']['paid_preview_duration_seconds']), 'Dispatched regeneration jobs recorded in response');
$runner->assert($payloadChangeDuration['total_dispatched_jobs'] > 0, 'Total dispatched jobs count is positive for outdated tracks');

// Re-saving unchanged setting produces 0 duplicate jobs
$reqUnchangedDuration = Request::create('/admin-home/music-settings-json', 'POST', array_merge($updateData, [
    'paid_preview_duration_seconds' => 35,
]));
$resUnchangedDuration = $adminController->update($reqUnchangedDuration);
$payloadUnchangedDuration = json_decode($resUnchangedDuration->getContent(), true);

$runner->assert($payloadUnchangedDuration['total_dispatched_jobs'] === 0, 'Re-saving unchanged preview duration dispatches exactly 0 jobs');
$runner->assert(empty($payloadUnchangedDuration['dispatched_regeneration_jobs']), 'No dispatched jobs summary when settings value is unchanged');

// ----------------------------------------------------------------------
// 7. Security & Non-Disclosure Guarantees
// ----------------------------------------------------------------------
echo "\n--- 7. Testing Security & Non-Disclosure Guarantees ---\n";

$rawResponse = $resChangeDuration->getContent();

$runner->assert(!str_contains($rawResponse, 'secret'), 'Response does not expose password hashes or secrets');
$runner->assert(!str_contains($rawResponse, 'C:\\') && !str_contains($rawResponse, '/var/'), 'Response does not expose private server filesystem root paths');
$runner->assert(!str_contains($rawResponse, 'DB_') && !str_contains($rawResponse, 'APP_KEY'), 'Response does not expose database credentials or environment keys');

// ----------------------------------------------------------------------
// 8. Public Runtime Config API Endpoint (/api/v1/music/config)
// ----------------------------------------------------------------------
echo "\n--- 8. Testing Public Runtime Config API Endpoint ---\n";

// Logout to test purely anonymous public consumer (e.g. mobile app boot)
Auth::guard('admin')->logout();

$streamController = app(MusicStreamController::class);
$resPublicConfig = $streamController->config();
$payloadPublicConfig = json_decode($resPublicConfig->getContent(), true);

$runner->assert($resPublicConfig->getStatusCode() === 200, 'GET /api/v1/music/config is publicly accessible without authentication');
$runner->assert($payloadPublicConfig['success'] === true, 'Public config response has success = true');
$runner->assert(isset($payloadPublicConfig['data']['marketplace_enabled']), 'Public config provides marketplace_enabled switch');
$runner->assert(isset($payloadPublicConfig['data']['paid_preview_duration_seconds']), 'Public config provides paid_preview_duration_seconds');
$runner->assert(isset($payloadPublicConfig['data']['offers']['free']), 'Public config provides free offer status');
$runner->assert(isset($payloadPublicConfig['data']['offers']['rent']), 'Public config provides rent offer status');
$runner->assert(isset($payloadPublicConfig['data']['offers']['sale']), 'Public config provides sale offer status');
$runner->assert(!isset($payloadPublicConfig['data']['platform_commission_rate']), 'Public config strictly hides private admin commission rate');
$runner->assert(!isset($payloadPublicConfig['data']['max_audio_upload_mb']), 'Public config hides internal upload config');

// ----------------------------------------------------------------------
// 9. Server-Side Marketplace Disablement Enforcement
// ----------------------------------------------------------------------
echo "\n--- 9. Testing Server-Side Marketplace Disablement Enforcement ---\n";

// Disable marketplace globally
MusicSetting::set('music_marketplace_enabled', 0);

$buyerUser = \App\User::firstOrCreate(
    ['email' => 'phase3a_buyer@test.com'],
    ['name' => 'Phase 3A Buyer', 'username' => 'phase3abuyer', 'password' => bcrypt('secret'), 'user_type' => 0]
);

$entitledTrack = MusicTrack::create([
    'title_en' => 'Entitled Test Track',
    'title_ar' => 'مسار اختبار المشتري',
    'artist_name' => 'Artist Test',
    'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
    'seller_id' => $sellerUser->id,
    'genre_id' => $genre->id,
    'duration_seconds' => 180,
    'audio_path_original' => 'masters/phase3a_entitled.mp3',
    'audio_path_preview' => 'previews/phase3a_entitled_prev.mp3',
    'media_status' => MusicTrack::MEDIA_STATUS_READY,
    'status' => MusicTrack::STATUS_APPROVED,
]);

// 1. Guest request for stream ticket when marketplace is disabled is rejected with HTTP 403
$reqGuestTicket = Request::create("/api/v1/music/tracks/{$entitledTrack->id}/stream-ticket", 'GET');
$resGuestTicket = $streamController->requestStreamTicket($reqGuestTicket, $entitledTrack->id);
$runner->assert($resGuestTicket->getStatusCode() === 403, 'Unentitled guest ticket request is rejected with HTTP 403 when marketplace is disabled');
$payloadGuestTicket = json_decode($resGuestTicket->getContent(), true);
$runner->assert(str_contains($payloadGuestTicket['message'], 'disabled'), 'Guest ticket response clearly states marketplace is disabled');

// 2. Unentitled user playback request is also rejected
$unentitledUser = \App\User::firstOrCreate(
    ['email' => 'phase3a_unentitled@test.com'],
    ['name' => 'Phase 3A Unentitled', 'username' => 'phase3aunentitled', 'password' => bcrypt('secret'), 'user_type' => 0]
);
$streamService = app(MusicStreamService::class);
$accessUnentitled = $streamService->resolvePlaybackAccess($unentitledUser, $entitledTrack);
$runner->assert($accessUnentitled['authorized'] === false, 'Unentitled user playback access is rejected when marketplace is disabled');
$runner->assert($accessUnentitled['reason'] === 'Music marketplace is currently disabled', 'Reason explicitly states marketplace is currently disabled');

// 3. BUT customer with pre-existing perpetual sale entitlement STILL HAS ACCESS (preserves customer rights)
$saleOffer = \App\MusicOffer::create([
    'track_id' => $entitledTrack->id,
    'offer_type' => \App\MusicOffer::OFFER_TYPE_SALE,
    'price' => 20.00,
    'allow_download' => false,
    'is_active' => true,
]);
$buyerEntitlement = \App\MusicEntitlement::create([
    'user_id' => $buyerUser->id,
    'track_id' => $entitledTrack->id,
    'offer_id' => $saleOffer->id,
    'is_active' => true,
    'allow_download' => false,
]);

$accessBuyer = $streamService->resolvePlaybackAccess($buyerUser, $entitledTrack);
$runner->assert($accessBuyer['authorized'] === true, 'Prior buyer with perpetual entitlement is authorized even when marketplace is disabled');
$runner->assert($accessBuyer['mode'] === 'full', 'Prior buyer receives full stream mode');

// 4. Entitled buyer can request stream ticket even when marketplace is disabled
$reqBuyerTicket = Request::create("/api/v1/music/tracks/{$entitledTrack->id}/stream-ticket", 'GET');
$reqBuyerTicket->setUserResolver(fn() => $buyerUser);
$resBuyerTicket = $streamController->requestStreamTicket($reqBuyerTicket, $entitledTrack->id);
$runner->assert($resBuyerTicket->getStatusCode() === 200, 'Prior buyer can issue stream ticket when marketplace is disabled');
$payloadBuyerTicket = json_decode($resBuyerTicket->getContent(), true);
$runner->assert($payloadBuyerTicket['data']['mode'] === 'full', 'Issued ticket for prior buyer has mode = full');

// 5. When marketplace is re-enabled, guest can request preview stream ticket again
MusicSetting::set('music_marketplace_enabled', 1);
$resGuestEnabled = $streamController->requestStreamTicket($reqGuestTicket, $entitledTrack->id);
$runner->assert($resGuestEnabled->getStatusCode() === 200, 'Guest can request preview stream ticket when marketplace is enabled');
$payloadGuestEnabled = json_decode($resGuestEnabled->getContent(), true);
$runner->assert($payloadGuestEnabled['data']['mode'] === 'preview', 'Guest receives mode = preview when marketplace is enabled');

// Cleanup entitled track and offer
$buyerEntitlement->delete();
$saleOffer->delete();
$entitledTrack->delete();

// ----------------------------------------------------------------------
// Teardown / Cleanup
// ----------------------------------------------------------------------
if (isset($testTrack) && $testTrack) {
    \Illuminate\Support\Facades\Storage::disk(MusicStorageService::DISK)->delete($testTrack->audio_path_original);
    \Illuminate\Support\Facades\Storage::disk(MusicStorageService::DISK)->delete($testTrack->audio_path_preview);
    $testTrack->delete();
}

// Reset MusicSetting to canonical defaults
foreach (MusicSetting::getDefaults() as $k => $v) {
    MusicSetting::set($k, $v);
}

echo "\n======================================================\n";
echo "Phase 3A Admin Music Settings Test Suite Results:\n";
echo "  Passed: {$runner->getPassed()}\n";
echo "  Failed: {$runner->getFailed()}\n";
echo "======================================================\n";

if ($runner->getFailed() > 0) {
    echo "\nFailed Assertions:\n";
    foreach ($runner->getFailures() as $msg) {
        echo " - {$msg}\n";
    }
    exit(1);
}

exit(0);
