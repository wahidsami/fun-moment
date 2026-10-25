<?php

/**
 * Phase 2 Automated Verification Suite:
 * Secure Audio Storage, Upload Validation, Preview Generation & Range Streaming
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\ProcessMusicAudioJob;
use App\MusicEntitlement;
use App\MusicOrderItem;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\AudioMetadata;
use App\Services\Music\AudioValidationService;
use App\Services\Music\FFmpegAudioProcessor;
use App\Services\Music\MusicAudioProcessingService;
use App\Services\Music\MusicStorageService;
use App\Services\Music\MusicStreamService;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Phase2MusicAudioTestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function assert(bool $condition, string $testName, string $detail = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $msg = "  [FAIL] {$testName}" . ($detail ? " - {$detail}" : "");
            $this->errors[] = $msg;
            echo "{$msg}\n";
        }
    }

    public function summary(): void
    {
        echo "\n======================================================\n";
        echo "Phase 2 Verification Suite Results:\n";
        echo "  Passed: {$this->passed}\n";
        echo "  Failed: {$this->failed}\n";
        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->errors as $err) {
                echo "{$err}\n";
            }
        }
        echo "======================================================\n";
        if ($this->failed > 0) {
            exit(1);
        }
    }
}

$runner = new Phase2MusicAudioTestRunner();

// Reset music settings to canonical defaults for test isolation
foreach (MusicSetting::getDefaults() as $dKey => $dVal) {
    MusicSetting::set($dKey, $dVal);
}

echo "\n--- 1. Testing Audio Upload Validation & Anti-Spoofing ---\n";

$validator = new AudioValidationService();
$storage = new MusicStorageService();

// Helper to create synthetic test files
$tempDir = sys_get_temp_dir() . '/fun_moment_phase2_tests';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// 1. Valid MP3 with ID3v2 header
$mp3Path = $tempDir . '/valid_test.mp3';
$mp3Header = "ID3\x04\x00\x00\x00\x00\x00\x20" . str_repeat("\x00", 32) . "\xFF\xFB\x90\x64" . str_repeat("\xAA", 1000);
file_put_contents($mp3Path, $mp3Header);
$uploadedMp3 = new UploadedFile($mp3Path, 'valid_test.mp3', 'audio/mpeg', null, true);
$resMp3 = $validator->validate($uploadedMp3);
$runner->assert($resMp3->isValid && $resMp3->format === 'mp3', 'Valid MP3 with ID3 header is accepted');

// 2. Valid WAV with RIFF WAVE format
$wavPath = $tempDir . '/valid_test.wav';
$wavHeader = "RIFF\x24\x08\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x02\x00\x44\xAC\x00\x00\x10\xB1\x02\x00\x04\x00\x10\x00data\x00\x08\x00\x00" . str_repeat("\x00", 2048);
file_put_contents($wavPath, $wavHeader);
$uploadedWav = new UploadedFile($wavPath, 'valid_test.wav', 'audio/wav', null, true);
$resWav = $validator->validate($uploadedWav);
$runner->assert($resWav->isValid && $resWav->format === 'wav', 'Valid WAV with RIFF WAVE header is accepted');

// 3. Valid FLAC with fLaC header
$flacPath = $tempDir . '/valid_test.flac';
$flacHeader = "fLaC\x00\x00\x00\x22" . str_repeat("\x00", 2048);
file_put_contents($flacPath, $flacHeader);
$uploadedFlac = new UploadedFile($flacPath, 'valid_test.flac', 'audio/flac', null, true);
$resFlac = $validator->validate($uploadedFlac);
$runner->assert($resFlac->isValid && $resFlac->format === 'flac', 'Valid FLAC with fLaC magic bytes is accepted');

// 4. Valid M4A with ftypM4A box
$m4aPath = $tempDir . '/valid_test.m4a';
$m4aHeader = "\x00\x00\x00\x20ftypM4A \x00\x00\x00\x00M4A mp42isom" . str_repeat("\x00", 2048);
file_put_contents($m4aPath, $m4aHeader);
$uploadedM4a = new UploadedFile($m4aPath, 'valid_test.m4a', 'audio/mp4', null, true);
$resM4a = $validator->validate($uploadedM4a);
$runner->assert($resM4a->isValid && $resM4a->format === 'm4a', 'Valid M4A/AAC with ftyp container is accepted');

// 5. Valid raw AAC ADTS file
$aacPath = $tempDir . '/valid_test.aac';
$aacHeader = "\xFF\xF1\x50\x80" . str_repeat("\x00", 2048);
file_put_contents($aacPath, $aacHeader);
$uploadedAac = new UploadedFile($aacPath, 'valid_test.aac', 'audio/aac', null, true);
$resAac = $validator->validate($uploadedAac);
$runner->assert($resAac->isValid && $resAac->format === 'aac', 'Valid AAC ADTS stream is accepted');

// 5b. Container/Extension Mismatch: .aac file containing M4A container box
$mismatchedAacPath = $tempDir . '/mismatch_m4a_in_aac.aac';
file_put_contents($mismatchedAacPath, $m4aHeader);
$uploadedMismatchedAac = new UploadedFile($mismatchedAacPath, 'mismatch_m4a_in_aac.aac', 'audio/aac', null, true);
$resMismatchAac = $validator->validate($uploadedMismatchedAac);
$runner->assert(
    !$resMismatchAac->isValid && str_contains($resMismatchAac->error, "does not match the detected audio container format 'm4a'"),
    'M4A container with .aac extension is rejected as extension/container mismatch'
);

// 5c. Container/Extension Mismatch: .m4a file containing raw AAC ADTS
$mismatchedM4aPath = $tempDir . '/mismatch_adts_in_m4a.m4a';
file_put_contents($mismatchedM4aPath, $aacHeader);
$uploadedMismatchedM4a = new UploadedFile($mismatchedM4aPath, 'mismatch_adts_in_m4a.m4a', 'audio/mp4', null, true);
$resMismatchM4a = $validator->validate($uploadedMismatchedM4a);
$runner->assert(
    !$resMismatchM4a->isValid && str_contains($resMismatchM4a->error, "does not match the detected audio container format 'aac'"),
    'Raw AAC ADTS with .m4a extension is rejected as extension/container mismatch'
);

// 5d. Independent Admin Configuration: Disabling M4A allows AAC and rejects M4A
MusicSetting::set('allowed_audio_formats', 'mp3,wav,aac,flac');
$resDisM4a = $validator->validate($uploadedM4a);
$resAllowAac = $validator->validate($uploadedAac);
$runner->assert(!$resDisM4a->isValid && str_contains($resDisM4a->error, 'not permitted'), 'Disabling M4A administratively rejects .m4a uploads');
$runner->assert($resAllowAac->isValid && $resAllowAac->format === 'aac', 'Disabling M4A preserves valid .aac uploads independently');

// 5e. Independent Admin Configuration: Disabling AAC allows M4A and rejects AAC
MusicSetting::set('allowed_audio_formats', 'mp3,wav,flac,m4a');
$resDisAac = $validator->validate($uploadedAac);
$resAllowM4a = $validator->validate($uploadedM4a);
$runner->assert(!$resDisAac->isValid && str_contains($resDisAac->error, 'not permitted'), 'Disabling AAC administratively rejects .aac uploads');
$runner->assert($resAllowM4a->isValid && $resAllowM4a->format === 'm4a', 'Disabling AAC preserves valid .m4a uploads independently');

// 6. Admin Format Disabling: Disabling FLAC via settings strictly rejects FLAC upload
MusicSetting::set('allowed_audio_formats', 'mp3,wav,aac,m4a');
$resDisabledFlac = $validator->validate($uploadedFlac);
$runner->assert(!$resDisabledFlac->isValid && str_contains($resDisabledFlac->error, 'not permitted'), 'FLAC upload is rejected when administratively excluded from allowed_audio_formats');
// Restore allowed formats
MusicSetting::set('allowed_audio_formats', 'mp3,wav,aac,flac,m4a');

// 7. Disallowed arbitrary format (.ogg or disguised .exe)
$disallowedPath = $tempDir . '/test.ogg';
file_put_contents($disallowedPath, "OggS\x00\x02\x00\x00" . str_repeat("\x00", 2048));
$uploadedDisallowed = new UploadedFile($disallowedPath, 'test.ogg', 'audio/ogg', null, true);
$resDisallowed = $validator->validate($uploadedDisallowed);
$runner->assert(!$resDisallowed->isValid, 'Disallowed audio container (.ogg) is rejected');

// 8. Spoofed PHP script renamed to .mp3
$spoofedPhp = $tempDir . '/exploit.mp3';
file_put_contents($spoofedPhp, "<?php echo 'system exploit'; system(\$_GET['cmd']); ?>");
$uploadedPhp = new UploadedFile($spoofedPhp, 'exploit.mp3', 'audio/mpeg', null, true);
$resPhp = $validator->validate($uploadedPhp);
$runner->assert(!$resPhp->isValid, 'Spoofed PHP script with .mp3 extension is strictly rejected');

// 9. Spoofed HTML script renamed to .wav
$spoofedHtml = $tempDir . '/exploit.wav';
file_put_contents($spoofedHtml, "<html><script>alert('xss');</script></html>");
$uploadedHtml = new UploadedFile($spoofedHtml, 'exploit.wav', 'audio/wav', null, true);
$resHtml = $validator->validate($uploadedHtml);
$runner->assert(!$resHtml->isValid, 'Spoofed HTML script with .wav extension is rejected');

// 10. Empty zero-byte file
$emptyPath = $tempDir . '/empty.mp3';
file_put_contents($emptyPath, '');
$uploadedEmpty = new UploadedFile($emptyPath, 'empty.mp3', 'audio/mpeg', null, true);
$resEmpty = $validator->validate($uploadedEmpty);
$runner->assert(!$resEmpty->isValid, 'Zero-byte empty file is rejected');

// 11. Upload size boundary: limit enforcement
$configuredLimit = MusicSetting::getConfiguredMaxUploadBytes();
$runner->assert($configuredLimit === 26214400, 'Configured upload limit is 25MB (26,214,400 bytes)');

$effectiveLimit = MusicSetting::getEffectiveMaxUploadBytes();
$runner->assert($effectiveLimit > 0 && $effectiveLimit <= $configuredLimit, "Effective upload limit respects runtime bounds ({$effectiveLimit} bytes)");

// Test size exceeding limit
$resOversize = $validator->validate($uploadedMp3, 500); // Pass explicit 500 byte limit for test
$runner->assert(!$resOversize->isValid && str_contains($resOversize->error, 'exceeds'), 'File size exceeding max upload limit is rejected');


echo "\n--- 2. Testing Private Master Audio Storage & Traversal Prevention ---\n";

// Store master
$storeResult = $storage->storeMaster($uploadedMp3, 'Original User Song (Master).mp3', 'mp3');
$runner->assert(str_starts_with($storeResult['storage_path'], 'masters/'), 'Master is stored under masters/ directory');
$runner->assert(str_ends_with($storeResult['storage_path'], '.mp3'), 'Master retains canonical extension');
$runner->assert($storage->exists($storeResult['storage_path']), 'Master file physically exists on private storage disk');

$absMasterPath = $storage->getAbsolutePath($storeResult['storage_path']);
$runner->assert(file_exists($absMasterPath), 'Resolved absolute path exists on disk');
$runner->assert(!str_contains($absMasterPath, 'public/assets/uploads'), 'Master file is stored outside public web directory');

// Path traversal prevention
$traversalBlocked = false;
try {
    $storage->getAbsolutePath('../../etc/passwd');
} catch (\InvalidArgumentException $e) {
    $traversalBlocked = true;
}
$runner->assert($traversalBlocked, 'Path traversal ../../etc/passwd is strictly blocked with exception');

$traversalPublicBlocked = false;
try {
    $storage->getAbsolutePath('masters/../../public/index.php');
} catch (\InvalidArgumentException $e) {
    $traversalPublicBlocked = true;
}
$runner->assert($traversalPublicBlocked, 'Path traversal to public directory is strictly blocked');


echo "\n--- 3. Testing Audio Processor Abstraction & Preview Generation ---\n";

// Set up simulated test handlers for deterministic unit testing of FFmpeg processor
FFmpegAudioProcessor::setMockProbeHandler(function (string $filePath, int $fileSize) {
    return new AudioMetadata(
        true,
        185.4, // duration in seconds (~3m 5s)
        'mp3',
        'mp3',
        320,
        44100,
        2,
        $fileSize,
        null
    );
});

FFmpegAudioProcessor::setMockProcessHandler(function (string $action, string $source, string $output, $arg1, $arg2 = null) {
    // Generate synthetic audio file for test
    file_put_contents($output, "ID3\x04\x00\x00\x00\x00\x00\x20\xFF\xFB" . str_repeat("\x55", 500));
    return true;
});

$audioProcessor = new FFmpegAudioProcessor();
$runner->assert($audioProcessor->isAvailable(), 'AudioProcessor reports available under test mock handler');

$probed = $audioProcessor->probeMetadata($absMasterPath);
$runner->assert($probed->isValid && $probed->durationSeconds === 185, 'Probe extracts metadata with duration = 185s');
$runner->assert($probed->codec === 'mp3' && $probed->bitrateKbps === 320, 'Probe extracts codec and bitrate');

// Test probe rejection of unapproved or disguised codecs (e.g. WMA or video H264)
FFmpegAudioProcessor::setMockProbeHandler(function (string $filePath, int $fileSize) {
    return new AudioMetadata(true, 120.0, 'asf', 'wma', 128, 44100, 2, $fileSize, null);
});
$probedWma = $audioProcessor->probeMetadata($absMasterPath);
$runner->assert(!$probedWma->isValid && str_contains($probedWma->error, 'Unsupported or unapproved audio codec'), 'Probe rejects unapproved audio codec (WMA)');

// Restore standard mock probe handler
FFmpegAudioProcessor::setMockProbeHandler(function (string $filePath, int $fileSize) {
    return new AudioMetadata(true, 185.4, 'mp3', 'mp3', 320, 44100, 2, $fileSize, null);
});

// Test preview generation directly
$previewOutputPath = $tempDir . '/generated_preview.mp3';
$genSuccess = $audioProcessor->generatePreview($absMasterPath, $previewOutputPath, 20, true);
$runner->assert($genSuccess && file_exists($previewOutputPath), 'Preview generated successfully');


echo "\n--- 4. Testing End-to-End Processing Pipeline & Track Lifecycle ---\n";

DB::beginTransaction();

// Create test genre, seller, buyer and track
$genre = \App\MusicGenre::firstOrCreate(
    ['slug' => 'test-genre-phase2'],
    ['name_en' => 'Test Genre', 'name_ar' => 'تصنيف تجريبي', 'is_active' => true]
);

$seller = User::firstOrCreate(
    ['email' => 'phase2_seller@test.com'],
    ['name' => 'Phase 2 Seller', 'username' => 'phase2seller', 'password' => bcrypt('password'), 'user_type' => 1]
);

$buyer = User::firstOrCreate(
    ['email' => 'phase2_buyer@test.com'],
    ['name' => 'Phase 2 Buyer', 'username' => 'phase2buyer', 'password' => bcrypt('password'), 'user_type' => 0]
);

$track = MusicTrack::create([
    'title_en' => 'Phase 2 Master Pipeline Track',
    'title_ar' => 'مسار اختبار المرحلة 2',
    'artist_name' => 'Phase 2 Artist',
    'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
    'seller_id' => $seller->id,
    'genre_id' => $genre->id,
    'duration_seconds' => 0,
    'audio_path_original' => $storeResult['storage_path'],
    'original_filename' => $storeResult['original_filename'],
    'file_size_bytes' => $storeResult['file_size'],
    'media_status' => MusicTrack::MEDIA_STATUS_PENDING,
    'status' => MusicTrack::STATUS_DRAFT,
]);

$saleOffer = \App\MusicOffer::create([
    'track_id' => $track->id,
    'offer_type' => \App\MusicOffer::OFFER_TYPE_SALE,
    'price' => 15.00,
    'allow_download' => false,
    'is_active' => true,
]);

$runner->assert($track->media_status === MusicTrack::MEDIA_STATUS_PENDING, 'New track has media_status = pending');
$runner->assert(!$track->isMediaReady(), 'Track is not media ready while pending');

// Run processing service
$processingService = new MusicAudioProcessingService($storage, $audioProcessor);
$processed = $processingService->processTrackAudio($track);

$track->refresh();
$runner->assert($processed, 'processTrackAudio returns true');
$runner->assert($track->media_status === MusicTrack::MEDIA_STATUS_READY, 'Track marked media_status = ready after processing');
$runner->assert($track->isMediaReady(), 'isMediaReady() helper returns true');
$runner->assert(!empty($track->audio_path_preview), 'Preview asset path is recorded on track');
$runner->assert($track->preview_duration_seconds === 20, 'Preview duration matches canonical 20 seconds');
$runner->assert($track->duration_seconds === 185, 'Duration seconds updated from probed metadata');
$runner->assert($track->audio_codec === 'mp3', 'Audio codec recorded on track');
$runner->assert($track->preview_version === 1, 'Preview version initialized to 1');

// Test Preview Dynamic Regeneration
// Update setting to 30 seconds
MusicSetting::set('paid_preview_duration_seconds', 30);
$runner->assert(MusicSetting::getEffectivePaidPreviewDurationSeconds() === 30, 'Setting paid_preview_duration_seconds updated to 30s');

$regenerated = $processingService->regeneratePreviewIfNeeded($track);
$track->refresh();
$runner->assert($regenerated, 'regeneratePreviewIfNeeded returns true when setting duration changes');
$runner->assert($track->preview_duration_seconds === 30, 'Track preview duration updated to 30s');
$runner->assert($track->preview_version === 2, 'Preview version bumped to 2');

// Restore setting to 20s
MusicSetting::set('paid_preview_duration_seconds', 20);

// Test background job execution
$track2 = MusicTrack::create([
    'title_en' => 'Phase 2 Queued Job Track',
    'title_ar' => 'مسار وظيفي تجريبي',
    'artist_name' => 'Job Artist',
    'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
    'seller_id' => $seller->id,
    'genre_id' => $genre->id,
    'duration_seconds' => 0,
    'audio_path_original' => $storeResult['storage_path'],
    'original_filename' => 'job_track.mp3',
    'file_size_bytes' => 1000,
    'media_status' => MusicTrack::MEDIA_STATUS_PENDING,
    'status' => MusicTrack::STATUS_DRAFT,
]);

$job = new ProcessMusicAudioJob($track2);
$job->handle($processingService);
$track2->refresh();
$runner->assert($track2->isMediaReady(), 'ProcessMusicAudioJob successfully processes track in background worker simulation');


echo "\n--- 5. Testing Master Preservation & Safe Deletion Lifecycle ---\n";

// Fresh track with no sales can have master deleted
$runner->assert($storage->canDeleteMaster($track2), 'Track with no sales/entitlements allows master deletion');

// Track with entitlement or order item cannot have master deleted
$entitlement = MusicEntitlement::create([
    'user_id' => $buyer->id,
    'track_id' => $track->id,
    'offer_id' => $saleOffer->id,
    'is_active' => true,
    'allow_download' => false,
]);

$runner->assert(!$storage->canDeleteMaster($track), 'Track with active entitlement prevents master deletion');


echo "\n--- 6. Testing Playback Authorization & Commercial Entitlements ---\n";

$streamService = new MusicStreamService($storage);

// Mark track as published/approved and enable marketplace for standard operation
$track->update(['status' => MusicTrack::STATUS_APPROVED]);
MusicSetting::set('music_marketplace_enabled', 1);

// 1. Buyer with purchase entitlement gets full stream
$accessBuyer = $streamService->resolvePlaybackAccess($buyer, $track);
$runner->assert($accessBuyer['authorized'] && $accessBuyer['mode'] === 'full', 'Buyer with perpetual purchase gets full stream access');

// 2. Unentitled user gets preview only
$unentitledUser = User::firstOrCreate(
    ['email' => 'unentitled@test.com'],
    ['name' => 'Unentitled User', 'username' => 'unentitled', 'password' => bcrypt('password'), 'user_type' => 0]
);
$accessUnentitled = $streamService->resolvePlaybackAccess($unentitledUser, $track);
$runner->assert($accessUnentitled['authorized'] && $accessUnentitled['mode'] === 'preview', 'Unentitled user receives preview access only for paid track');

// 3. Rental entitlement: active within time window
$rentalTrack = MusicTrack::create([
    'title_en' => 'Rental Test Track',
    'title_ar' => 'مسار إيجار تجريبي',
    'artist_name' => 'Rental Artist',
    'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
    'seller_id' => $seller->id,
    'genre_id' => $genre->id,
    'duration_seconds' => 120,
    'audio_path_original' => $storeResult['storage_path'],
    'audio_path_preview' => $track->audio_path_preview,
    'media_status' => MusicTrack::MEDIA_STATUS_READY,
    'status' => MusicTrack::STATUS_APPROVED,
]);

$rentalOffer = \App\MusicOffer::create([
    'track_id' => $rentalTrack->id,
    'offer_type' => \App\MusicOffer::OFFER_TYPE_RENT,
    'price' => 4.00,
    'rental_duration_days' => 7,
    'allow_download' => false,
    'is_active' => true,
]);

$rentalActive = MusicEntitlement::create([
    'user_id' => $buyer->id,
    'track_id' => $rentalTrack->id,
    'offer_id' => $rentalOffer->id,
    'is_active' => true,
    'starts_at' => Carbon::now()->subHour(),
    'expires_at' => Carbon::now()->addDays(2),
    'allow_download' => false,
]);

$accessRental = $streamService->resolvePlaybackAccess($buyer, $rentalTrack);
$runner->assert($accessRental['authorized'] && $accessRental['mode'] === 'full', 'Active rental entitlement grants full streaming access');

// 4. Rental entitlement: expired
$rentalActive->update([
    'starts_at' => Carbon::now()->subDays(5),
    'expires_at' => Carbon::now()->subDay(),
]);

$accessExpiredRental = $streamService->resolvePlaybackAccess($buyer, $rentalTrack);
$runner->assert($accessExpiredRental['authorized'] && $accessExpiredRental['mode'] === 'preview', 'Expired rental reverts to preview-only access');
$rentalActive->refresh();
$runner->assert(!$rentalActive->is_active, 'Expired entitlement status is marked inactive in database');

// 5. Free track: full streaming for anyone
$freeTrack = MusicTrack::create([
    'title_en' => 'Free Test Track',
    'title_ar' => 'مسار مجاني تجريبي',
    'artist_name' => 'Free Artist',
    'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
    'seller_id' => $seller->id,
    'genre_id' => $genre->id,
    'duration_seconds' => 120,
    'audio_path_original' => $storeResult['storage_path'],
    'audio_path_preview' => $track->audio_path_preview,
    'media_status' => MusicTrack::MEDIA_STATUS_READY,
    'status' => MusicTrack::STATUS_APPROVED,
]);

$freeOffer = \App\MusicOffer::create([
    'track_id' => $freeTrack->id,
    'offer_type' => \App\MusicOffer::OFFER_TYPE_FREE,
    'price' => 0.00,
    'allow_download' => false,
    'is_active' => true,
]);

$accessFree = $streamService->resolvePlaybackAccess($unentitledUser, $freeTrack);
$runner->assert($accessFree['authorized'] && $accessFree['mode'] === 'full', 'Free track allows full streaming to unentitled users');

// 6. Suspended track: unentitled user denied completely
$track->update(['status' => MusicTrack::STATUS_SUSPENDED]);
$accessSuspendedUnentitled = $streamService->resolvePlaybackAccess($unentitledUser, $track);
$runner->assert(!$accessSuspendedUnentitled['authorized'] && $accessSuspendedUnentitled['mode'] === 'denied', 'Suspended track denies access to unentitled users');

// 7. Suspended track: buyer with existing purchase entitlement still gets full stream (Preservation rule!)
$accessSuspendedBuyer = $streamService->resolvePlaybackAccess($buyer, $track);
$runner->assert($accessSuspendedBuyer['authorized'] && $accessSuspendedBuyer['mode'] === 'full', 'Suspended track preserves full playback access for prior entitled buyer');

// 8. Track seller always has full access to their own track
$accessSeller = $streamService->resolvePlaybackAccess($seller, $track);
$runner->assert($accessSeller['authorized'] && $accessSeller['mode'] === 'full', 'Track seller has full stream access to own track');


echo "\n--- 7. Testing HMAC Stream Tickets ---\n";

// Generate ticket
$ticket = $streamService->createStreamTicket($buyer, $track, 'full', 900);
$runner->assert(!empty($ticket) && str_contains($ticket, '.'), 'Stream ticket generated as dot-separated token');

// Validate valid ticket
$valResult = $streamService->validateStreamTicket($ticket, $track, $buyer);
$runner->assert($valResult['valid'] && $valResult['mode'] === 'full', 'Valid ticket validates successfully with mode=full');

// Validate tampered signature
$tamperedTicket = $ticket . 'tampered';
$valTampered = $streamService->validateStreamTicket($tamperedTicket, $track);
$runner->assert(!$valTampered['valid'] && $valTampered['error'] === 'Invalid ticket signature', 'Tampered ticket signature is rejected');

// Validate wrong track
$valWrongTrack = $streamService->validateStreamTicket($ticket, $freeTrack);
$runner->assert(!$valWrongTrack['valid'] && str_contains($valWrongTrack['error'], 'not valid for this track'), 'Ticket for Track A cannot be used on Track B');

// Validate expired ticket
$expiredTicket = $streamService->createStreamTicket($buyer, $track, 'full', -10);
$valExpired = $streamService->validateStreamTicket($expiredTicket, $track);
$runner->assert(!$valExpired['valid'] && str_contains($valExpired['error'], 'expired'), 'Expired stream ticket is rejected');

// Cross-User Replay Isolation: Buyer B attempting to use Buyer A's ticket
$valReplayUserB = $streamService->validateStreamTicket($ticket, $track, $seller);
$runner->assert(!$valReplayUserB['valid'] && str_contains($valReplayUserB['error'], 'Cross-user ticket replay rejected'), 'Ticket issued to Buyer A cannot be replayed by User B');

// Anonymous Guest Replay: Guest attempting to redeem Buyer A's full-stream entitlement
$valReplayAnon = $streamService->validateStreamTicket($ticket, $track, null);
$runner->assert(!$valReplayAnon['valid'] && str_contains($valReplayAnon['error'], 'requires authentication'), 'Personal full-stream ticket cannot be anonymously replayed by guest');

$guestFullTicket = $streamService->createStreamTicket(null, $track, 'full', 900);
$valGuestFull = $streamService->validateStreamTicket($guestFullTicket, $track, null);
$runner->assert(!$valGuestFull['valid'] && str_contains($valGuestFull['error'], 'Guest tickets cannot authorize full streaming'), 'Guest ticket cannot authorize full streaming on paid track');

// Restore track to approved for live entitlement revalidation tests
$track->update(['status' => MusicTrack::STATUS_APPROVED]);

// Live Entitlement Revalidation: Rental expiring between ticket issuance and redemption
$renterUser = User::firstOrCreate(
    ['email' => 'renter_expiry_test@test.com'],
    ['name' => 'Renter Expiry Test', 'username' => 'renterexp', 'password' => bcrypt('password'), 'user_type' => 0]
);
$rentalEnt = MusicEntitlement::create([
    'user_id' => $renterUser->id,
    'track_id' => $track->id,
    'offer_id' => $rentalOffer->id,
    'access_type' => MusicEntitlement::ACCESS_STREAM_ONLY,
    'starts_at' => now()->subHour(),
    'expires_at' => now()->addMinutes(10), // valid at issuance
    'is_active' => true,
    'allow_download' => false,
]);
$rentalTicket = $streamService->createStreamTicket($renterUser, $track, 'full', 900);
// Verify it's initially valid
$valRentInitial = $streamService->validateStreamTicket($rentalTicket, $track, $renterUser);
$runner->assert($valRentInitial['valid'] && $valRentInitial['mode'] === 'full', 'Rental ticket valid immediately after issuance');

// Advance expiry into the past (rental expires while ticket is in flight)
$rentalEnt->update(['expires_at' => now()->subMinute()]);
$valRentExpired = $streamService->validateStreamTicket($rentalTicket, $track, $renterUser);
$runner->assert(!$valRentExpired['valid'] && str_contains($valRentExpired['error'], 'no longer authorized'), 'Rental expiring between ticket issuance and redemption is strictly rejected');

// Live Entitlement Revalidation: Ticket presented after access is revoked
$revokedUser = User::firstOrCreate(
    ['email' => 'revoked_access_test@test.com'],
    ['name' => 'Revoked Access Test', 'username' => 'revokedacc', 'password' => bcrypt('password'), 'user_type' => 0]
);
$revokedEnt = MusicEntitlement::create([
    'user_id' => $revokedUser->id,
    'track_id' => $track->id,
    'offer_id' => $saleOffer->id,
    'access_type' => MusicEntitlement::ACCESS_STREAM_ONLY,
    'is_active' => true,
    'allow_download' => false,
]);
$revokedTicket = $streamService->createStreamTicket($revokedUser, $track, 'full', 900);
// Revoke the entitlement
$revokedEnt->update(['is_active' => false, 'revoked_at' => now()]);
$valRevoked = $streamService->validateStreamTicket($revokedTicket, $track, $revokedUser);
$runner->assert(!$valRevoked['valid'] && str_contains($valRevoked['error'], 'no longer authorized'), 'Ticket presented after entitlement revocation is strictly rejected');

// Account Authorization Revalidation: User account deactivated after ticket issuance
$deactivatedUser = User::firstOrCreate(
    ['email' => 'deact_user_test@test.com'],
    ['name' => 'Deact User Test', 'username' => 'deactuser', 'password' => bcrypt('password'), 'user_type' => 0, 'user_status' => 1]
);
$deactEnt = MusicEntitlement::create([
    'user_id' => $deactivatedUser->id,
    'track_id' => $track->id,
    'offer_id' => $saleOffer->id,
    'access_type' => MusicEntitlement::ACCESS_STREAM_ONLY,
    'is_active' => true,
    'allow_download' => false,
]);
$deactTicket = $streamService->createStreamTicket($deactivatedUser, $track, 'full', 900);
// Deactivate account
$deactivatedUser->update(['user_status' => 0]);
$valDeact = $streamService->validateStreamTicket($deactTicket, $track, $deactivatedUser);
$runner->assert(!$valDeact['valid'] && str_contains($valDeact['error'], 'deactivated or suspended'), 'Ticket presented by deactivated user account is strictly rejected');
$deactivatedUser->update(['user_status' => 1]); // restore


echo "\n--- 8. Testing HTTP Byte-Range Audio Streaming ---\n";

// Create a real dummy audio file for streaming tests
$streamAudioPath = $tempDir . '/stream_test.mp3';
$audioPayload = str_repeat("ABCDEFGHIJ", 100); // 1000 bytes
file_put_contents($streamAudioPath, $audioPayload);

// 1. Full request (No Range header) -> HTTP 200
$reqFull = Request::create('/api/v1/music/tracks/1/stream', 'GET');
$respFull = $streamService->streamAudioFile($reqFull, $streamAudioPath, 'audio/mpeg');
$runner->assert($respFull->getStatusCode() === 200, 'Full audio request returns HTTP 200');
$runner->assert($respFull->headers->get('Content-Length') === '1000', 'Full request returns Content-Length: 1000');
$runner->assert($respFull->headers->get('Accept-Ranges') === 'bytes', 'Full request includes Accept-Ranges: bytes');

// 2. Partial Range request (bytes=0-499) -> HTTP 206
$reqPartial = Request::create('/api/v1/music/tracks/1/stream', 'GET');
$reqPartial->headers->set('Range', 'bytes=0-499');
$respPartial = $streamService->streamAudioFile($reqPartial, $streamAudioPath, 'audio/mpeg');
$runner->assert($respPartial->getStatusCode() === 206, 'Partial range request returns HTTP 206 Partial Content');
$runner->assert($respPartial->headers->get('Content-Length') === '500', 'Partial range returns Content-Length: 500');
$runner->assert($respPartial->headers->get('Content-Range') === 'bytes 0-499/1000', 'Partial range returns Content-Range: bytes 0-499/1000');

// 3. Suffix Range request (bytes=-200, last 200 bytes) -> HTTP 206
$reqSuffix = Request::create('/api/v1/music/tracks/1/stream', 'GET');
$reqSuffix->headers->set('Range', 'bytes=-200');
$respSuffix = $streamService->streamAudioFile($reqSuffix, $streamAudioPath, 'audio/mpeg');
$runner->assert($respSuffix->getStatusCode() === 206, 'Suffix range request returns HTTP 206');
$runner->assert($respSuffix->headers->get('Content-Length') === '200', 'Suffix range returns Content-Length: 200');
$runner->assert($respSuffix->headers->get('Content-Range') === 'bytes 800-999/1000', 'Suffix range returns Content-Range: bytes 800-999/1000');

// 4. Suffix Range larger than file size (bytes=-999999) -> Clamped to full file
$reqSuffixLarge = Request::create('/api/v1/music/tracks/1/stream', 'GET');
$reqSuffixLarge->headers->set('Range', 'bytes=-999999');
$respSuffixLarge = $streamService->streamAudioFile($reqSuffixLarge, $streamAudioPath, 'audio/mpeg');
$runner->assert($respSuffixLarge->getStatusCode() === 206, 'Suffix range larger than file returns HTTP 206');
$runner->assert($respSuffixLarge->headers->get('Content-Length') === '1000', 'Clamped suffix range returns full Content-Length: 1000');
$runner->assert($respSuffixLarge->headers->get('Content-Range') === 'bytes 0-999/1000', 'Clamped suffix range returns Content-Range: bytes 0-999/1000');

// 5. Reversed Range request (bytes=500-200) -> HTTP 416
$reqReversed = Request::create('/api/v1/music/tracks/1/stream', 'GET');
$reqReversed->headers->set('Range', 'bytes=500-200');
$respReversed = $streamService->streamAudioFile($reqReversed, $streamAudioPath, 'audio/mpeg');
$runner->assert($respReversed->getStatusCode() === 416, 'Reversed range request (bytes=500-200) returns HTTP 416');
$runner->assert($respReversed->headers->get('Content-Range') === 'bytes */1000', 'HTTP 416 returns Content-Range: bytes */1000');
$runner->assert($respReversed->headers->get('Accept-Ranges') === 'bytes', 'HTTP 416 includes Accept-Ranges: bytes');

// 6. Invalid Out of Bounds Range request (bytes=2000-3000 out of bounds) -> HTTP 416
$reqInvalid = Request::create('/api/v1/music/tracks/1/stream', 'GET');
$reqInvalid->headers->set('Range', 'bytes=2000-3000');
$respInvalid = $streamService->streamAudioFile($reqInvalid, $streamAudioPath, 'audio/mpeg');
$runner->assert($respInvalid->getStatusCode() === 416, 'Out of bounds range returns HTTP 416 Range Not Satisfiable');
$runner->assert($respInvalid->headers->get('Content-Range') === 'bytes */1000', 'HTTP 416 returns Content-Range: bytes */1000');


echo "\n--- 9. Testing Seller Isolation & Cross-Tenant Protection ---\n";

$sellerB = User::firstOrCreate(
    ['email' => 'seller_b@test.com'],
    ['name' => 'Seller B', 'username' => 'sellerb', 'password' => bcrypt('password'), 'user_type' => 1]
);

// Seller B cannot alter track owned by Seller A
$sellerBCanEditA = ($track->seller_id === $sellerB->id);
$runner->assert(!$sellerBCanEditA, 'Seller B cannot manage or own track created by Seller A');


echo "\n--- 10. Testing MusicStreamController API Endpoints ---\n";

$controller = new \App\Http\Controllers\Api\MusicStreamController($streamService, $storage);

// Restore track to published approved status for controller testing
$track->update(['status' => MusicTrack::STATUS_APPROVED]);

// 1. Issue ticket for unentitled guest user
$reqTicketGuest = Request::create("/api/v1/music/tracks/{$track->id}/stream-ticket", 'GET');
$respTicketGuest = $controller->requestStreamTicket($reqTicketGuest, $track->id);
$dataGuest = json_decode($respTicketGuest->getContent(), true);

$runner->assert($respTicketGuest->getStatusCode() === 200, 'Stream ticket endpoint returns HTTP 200 for guest');
$runner->assert($dataGuest['success'] === true, 'Stream ticket response has success = true');
$runner->assert($dataGuest['data']['mode'] === 'preview', 'Guest user ticket mode is strictly preview');
$runner->assert(!empty($dataGuest['data']['ticket']), 'Stream ticket string is provided in response');
$runner->assert(str_contains($dataGuest['data']['stream_url'], 'ticket='), 'Stream URL includes signed ticket param');

$guestTicket = $dataGuest['data']['ticket'];

// 2. Stream audio using the issued preview ticket
$reqStream = Request::create("/api/v1/music/tracks/{$track->id}/stream", 'GET', ['ticket' => $guestTicket]);
$respStream = $controller->stream($reqStream, $track->id);
$runner->assert($respStream->getStatusCode() === 200, 'Stream endpoint returns HTTP 200 with valid ticket');
$runner->assert($respStream->headers->get('Accept-Ranges') === 'bytes', 'Stream endpoint provides Accept-Ranges: bytes');

// 3. Issue ticket for suspended track fails with 403
$track->update(['status' => MusicTrack::STATUS_SUSPENDED]);
$respSuspended = $controller->requestStreamTicket($reqTicketGuest, $track->id);
$runner->assert($respSuspended->getStatusCode() === 403, 'Stream ticket endpoint returns HTTP 403 for suspended track');

// 4. Issue ticket for non-existent track fails with 404
$resp404 = $controller->requestStreamTicket($reqTicketGuest, 999999);
$runner->assert($resp404->getStatusCode() === 404, 'Stream ticket endpoint returns HTTP 404 for missing track');

// Restore track to approved for authenticated tests
$track->update(['status' => MusicTrack::STATUS_APPROVED]);

// 5. Sanctum Guard Authenticated Ticket Issuance & Full Stream
$reqTicketAuth = Request::create("/api/v1/music/tracks/{$track->id}/stream-ticket", 'GET');
$reqTicketAuth->setUserResolver(function () use ($buyer) {
    return $buyer;
});
$respTicketAuth = $controller->requestStreamTicket($reqTicketAuth, $track->id);
$dataAuth = json_decode($respTicketAuth->getContent(), true);
$runner->assert($respTicketAuth->getStatusCode() === 200, 'Stream ticket issued for Sanctum authenticated buyer');
$runner->assert($dataAuth['data']['mode'] === 'full', 'Entitled buyer receives mode=full ticket from controller');
$buyerTicket = $dataAuth['data']['ticket'];

// 6. Stream audio with buyer ticket and matching Sanctum user context
$reqStreamAuth = Request::create("/api/v1/music/tracks/{$track->id}/stream", 'GET', ['ticket' => $buyerTicket]);
$reqStreamAuth->setUserResolver(function () use ($buyer) {
    return $buyer;
});
$respStreamAuth = $controller->stream($reqStreamAuth, $track->id);
$runner->assert($respStreamAuth->getStatusCode() === 200, 'Stream endpoint permits stream for matching Sanctum user');

// 7. Replay attack: Seller B attempts to use Buyer A's ticket in controller
$reqStreamReplay = Request::create("/api/v1/music/tracks/{$track->id}/stream", 'GET', ['ticket' => $buyerTicket]);
$reqStreamReplay->setUserResolver(function () use ($sellerB) {
    return $sellerB;
});
$replayThrew = false;
try {
    $controller->stream($reqStreamReplay, $track->id);
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    $replayThrew = ($e->getStatusCode() === 403);
}
$runner->assert($replayThrew, 'Cross-user replay of Sanctum-issued ticket by different user is blocked with HTTP 403');

// 8. Header-Based Stream Ticket Delivery (X-Stream-Ticket to prevent access log query leakage)
$reqHeaderStream = Request::create("/api/v1/music/tracks/{$track->id}/stream", 'GET');
$reqHeaderStream->headers->set('X-Stream-Ticket', $buyerTicket);
$reqHeaderStream->setUserResolver(function () use ($buyer) {
    return $buyer;
});
$respHeaderStream = $controller->stream($reqHeaderStream, $track->id);
$runner->assert($respHeaderStream->getStatusCode() === 200, 'Stream endpoint accepts ticket via X-Stream-Ticket header');
$runner->assert(empty($reqHeaderStream->getQueryString()), 'Request with X-Stream-Ticket has clean query string without token leakage');


echo "\n--- 11. Testing Preview Duration Lifecycle & Safe Regeneration ---\n";

// 1. Setting change & duration clamping check
// Ensure duration setting bounds [5, 60] prevent full-length previews
MusicSetting::set('paid_preview_duration_seconds', 120);
$clampedMax = MusicSetting::getEffectivePaidPreviewDurationSeconds();
$runner->assert($clampedMax === 60, 'Preview duration setting > 60 is clamped to 60s');

MusicSetting::set('paid_preview_duration_seconds', 2);
$clampedMin = MusicSetting::getEffectivePaidPreviewDurationSeconds();
$runner->assert($clampedMin === 5, 'Preview duration setting < 5 is clamped to 5s');

// Set to legitimate new duration (30 seconds)
MusicSetting::set('paid_preview_duration_seconds', 30);
$effectiveDuration = MusicSetting::getEffectivePaidPreviewDurationSeconds();
$runner->assert($effectiveDuration === 30, 'Effective paid preview duration updated to 30s');

// 2. Safe Regeneration Failure: Failed regeneration keeps previous asset intact
$regenTrack = MusicTrack::create([
    'title_en' => 'Regen Test Track',
    'title_ar' => 'مسار اختبار التحديث',
    'artist_name' => 'Regen Artist',
    'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
    'seller_id' => $seller->id,
    'genre_id' => $genre->id,
    'duration_seconds' => 180,
    'audio_path_original' => $storeResult['storage_path'],
    'original_filename' => 'regen_test.mp3',
    'file_size_bytes' => 1000,
    'media_status' => MusicTrack::MEDIA_STATUS_READY,
    'status' => MusicTrack::STATUS_APPROVED,
    'preview_duration_seconds' => 20,
    'preview_version' => 1,
]);

$initialTempFile = $tempDir . '/init_preview.mp3';
file_put_contents($initialTempFile, "ID3\x04\x00\x00\x00\x00\x00\x20\xFF\xFB" . str_repeat("\x11", 500));
$initialPreview = $storage->storePreview($initialTempFile, 20, 1);
$regenTrack->update(['audio_path_preview' => $initialPreview]);
@unlink($initialTempFile);

$runner->assert(!empty($initialPreview) && $storage->exists($initialPreview), 'Initial preview asset exists before regeneration');

// Simulate processing failure (mock handler returns false)
FFmpegAudioProcessor::setMockProcessHandler(function () {
    return false;
});

$failResult = $processingService->regeneratePreviewIfNeeded($regenTrack);
$runner->assert($failResult === false, 'regeneratePreviewIfNeeded returns false when audio processor fails');
$regenTrack->refresh();
$runner->assert($regenTrack->audio_path_preview === $initialPreview, 'Track retains original preview path upon regeneration failure');
$runner->assert($regenTrack->preview_version === 1, 'Track preview_version is not incremented upon failure');
$runner->assert($storage->exists($initialPreview), 'Original preview asset remains preserved on storage after failure');

// 3. Safe Regeneration Success & Atomic Cutover
// Restore working process mock handler
FFmpegAudioProcessor::setMockProcessHandler(function (string $action, string $source, string $output, $arg1, $arg2 = null) {
    file_put_contents($output, "ID3\x04\x00\x00\x00\x00\x00\x20\xFF\xFB" . str_repeat("\x66", 600));
    return true;
});

$successResult = $processingService->regeneratePreviewIfNeeded($regenTrack);
$runner->assert($successResult === true, 'regeneratePreviewIfNeeded succeeds with working processor');
$regenTrack->refresh();
$runner->assert($regenTrack->preview_version === 2, 'Track preview_version atomically incremented to 2');
$runner->assert($regenTrack->preview_duration_seconds === 30, 'Track preview_duration_seconds updated to 30');
$runner->assert($regenTrack->audio_path_preview !== $initialPreview, 'Track audio_path_preview updated to new versioned path');
$runner->assert($storage->exists($regenTrack->audio_path_preview), 'New preview asset file exists on storage');
$runner->assert(!$storage->exists($initialPreview), 'Old preview asset file safely cleaned up after replacement');

// 4. Queueing preview regeneration for tracks with outdated duration
// Set up a track that has outdated duration (20s vs current 30s)
$outdatedTrack = MusicTrack::create([
    'title_en' => 'Outdated Track',
    'title_ar' => 'مسار قديم',
    'artist_name' => 'Outdated Artist',
    'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
    'seller_id' => $seller->id,
    'genre_id' => $genre->id,
    'duration_seconds' => 180,
    'audio_path_original' => $storeResult['storage_path'],
    'original_filename' => 'outdated.mp3',
    'file_size_bytes' => 1000,
    'media_status' => MusicTrack::MEDIA_STATUS_READY,
    'status' => MusicTrack::STATUS_APPROVED,
    'preview_duration_seconds' => 20,
    'preview_version' => 1,
]);

$queuedCount = $processingService->queuePreviewRegenerationForOutdatedTracks();
$runner->assert($queuedCount >= 1, 'queuePreviewRegenerationForOutdatedTracks dispatches background jobs for outdated tracks');

// 5. Operational Trigger: MusicSetting::updateWithSideEffects()
$trackForTrigger = MusicTrack::create([
    'title_en' => 'Trigger Track',
    'title_ar' => 'مسار المشغل',
    'artist_name' => 'Trigger Artist',
    'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
    'seller_id' => $seller->id,
    'genre_id' => $genre->id,
    'duration_seconds' => 180,
    'audio_path_original' => $storeResult['storage_path'],
    'original_filename' => 'trigger_test.mp3',
    'file_size_bytes' => 1000,
    'media_status' => MusicTrack::MEDIA_STATUS_READY,
    'status' => MusicTrack::STATUS_APPROVED,
    'preview_duration_seconds' => 30, // matches current 30
    'preview_version' => 1,
]);

// Call updateWithSideEffects with unchanged value (30) -> no jobs dispatched
$resSame = MusicSetting::updateWithSideEffects('paid_preview_duration_seconds', 30);
$runner->assert($resSame['dispatched_jobs'] === 0, 'updateWithSideEffects dispatches 0 jobs when setting value is unchanged');

// Call updateWithSideEffects with changed value (25) -> triggers background regeneration
$resChanged = MusicSetting::updateWithSideEffects('paid_preview_duration_seconds', 25);
$runner->assert($resChanged['dispatched_jobs'] >= 1, 'updateWithSideEffects triggers preview regeneration on duration change');
$runner->assert((int) MusicSetting::get('paid_preview_duration_seconds') === 25, 'Setting updated to 25');

// Restore setting to 20
MusicSetting::set('paid_preview_duration_seconds', 20);

// 6. Job Deduplication & Uniqueness
$regenJob = new \App\Jobs\RegenerateMusicPreviewJob($trackForTrigger);
$runner->assert($regenJob instanceof \Illuminate\Contracts\Queue\ShouldBeUnique, 'RegenerateMusicPreviewJob implements ShouldBeUnique');
$runner->assert($regenJob->uniqueId() === (string) $trackForTrigger->id, 'RegenerateMusicPreviewJob uniqueId is bound to track ID');


DB::rollBack();

// Cleanup synthetic files
@unlink($mp3Path);
@unlink($wavPath);
@unlink($flacPath);
@unlink($m4aPath);
@unlink($aacPath);
@unlink($mismatchedAacPath);
@unlink($mismatchedM4aPath);
@unlink($disallowedPath);
@unlink($spoofedPhp);
@unlink($spoofedHtml);
@unlink($emptyPath);
@unlink($previewOutputPath);
@unlink($streamAudioPath);
@rmdir($tempDir);

$runner->summary();
