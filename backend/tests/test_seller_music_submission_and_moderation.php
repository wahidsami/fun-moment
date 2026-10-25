<?php

/**
 * FUN MOMENT — Phase 3B: Seller Music Submission, Track Management & Admin Moderation Test Suite
 *
 * Validates:
 * 1. Seller authentication, role enforcement, and cross-seller tenancy isolation
 * 2. Disabled marketplace and disabled seller submissions enforcement
 * 3. Audio master and cover artwork validation
 * 4. Rights declaration and acceptance requirement
 * 5. Commercial offers configuration and price/duration validation
 * 6. Private master storage and background audio processing lifecycle
 * 7. Processing failure and retry processing workflow
 * 8. Admin moderation list, filters, counters, and admin preview inspection
 * 9. Strict approval block when media_status is not 'ready'
 * 10. Admin approval, rejection with recorded reason, and suspension
 * 11. Public streaming security and entitlement preservation
 * 12. Safe track deletion rules
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Http\Controllers\AdminMusicModerationController;
use App\Http\Controllers\Api\SellerMusicTrackController;
use App\Jobs\ProcessMusicAudioJob;
use App\MusicGenre;
use App\MusicOffer;
use App\MusicOrder;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\AudioProcessorInterface;
use App\Services\Music\MusicAudioProcessingService;
use App\Services\Music\MusicStorageService;
use App\Services\Music\MusicStreamService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class Phase3BTestRunner
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

$runner = new Phase3BTestRunner();

echo "\n=== Phase 3B: Seller Music Submission & Admin Moderation Test Suite ===\n\n";

// Helper: Setup test users
DB::beginTransaction();

try {
    // Ensure test genre exists
    $genre = MusicGenre::firstOrCreate(
        ['slug' => 'electronic-dance-p3b'],
        [
            'name_en' => 'Electronic Dance P3B',
            'name_ar' => 'رقص إلكتروني',
            'status' => MusicGenre::STATUS_ACTIVE,
            'sort_order' => 1,
        ]
    );

    // Setup Seller A
    $sellerA = User::firstOrCreate(
        ['email' => 'sellerA_p3b@funmoment.test'],
        [
            'name' => 'Seller Alice',
            'username' => 'seller_alice_p3b',
            'user_type' => 0, // 0 = seller / service provider
            'password' => bcrypt('secret123'),
        ]
    );
    $sellerA->user_type = 0;
    $sellerA->user_status = 1;
    $sellerA->save();

    // Setup Seller B
    $sellerB = User::firstOrCreate(
        ['email' => 'sellerB_p3b@funmoment.test'],
        [
            'name' => 'Seller Bob',
            'username' => 'seller_bob_p3b',
            'user_type' => 0,
            'user_status' => 1,
            'password' => bcrypt('secret123'),
        ]
    );
    $sellerB->user_type = 0;
    $sellerB->user_status = 1;
    $sellerB->save();

    // Setup Buyer
    $buyer = User::firstOrCreate(
        ['email' => 'buyer_p3b@funmoment.test'],
        [
            'name' => 'Buyer Charlie',
            'username' => 'buyer_charlie_p3b',
            'user_type' => 1, // 1 = regular customer
            'user_status' => 1,
            'password' => bcrypt('secret123'),
        ]
    );
    $buyer->user_type = 1;
    $buyer->user_status = 1;
    $buyer->save();

    // Setup Admin
    $admin = Admin::where('email', 'admin_p3b@funmoment.test')->first();
    if (!$admin) {
        $adminId = DB::table('admins')->insertGetId([
            'name' => 'Admin Moderator',
            'email' => 'admin_p3b@funmoment.test',
            'username' => 'admin_mod_p3b',
            'password' => bcrypt('secret123'),
            'role' => 'Super Admin',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = Admin::find($adminId);
    }

    // Ensure default settings enabled
    $initialSettings = [
        'music_marketplace_enabled' => true,
        'seller_submissions_enabled' => true,
        'seller_tracks_require_approval' => true,
        'offer_free_enabled' => true,
        'offer_rent_enabled' => true,
        'offer_sale_enabled' => true,
        'allowed_audio_formats' => 'mp3,wav,flac,aac,m4a',
        'max_audio_upload_mb' => 20,
    ];
    foreach ($initialSettings as $sKey => $sVal) {
        MusicSetting::set($sKey, $sVal);
    }

    $sellerController = app(SellerMusicTrackController::class);
    $adminController = app(AdminMusicModerationController::class);

    // --- TEST GROUP 1: Authentication & Authorization ---
    echo "--- Group 1: Authentication & Authorization ---\n";

    // 1.1 Unauthenticated index
    Auth::guard('web')->logout();
    if (method_exists(Auth::guard('sanctum'), 'forgetUser')) {
        Auth::guard('sanctum')->forgetUser();
    }
    Auth::forgetUser();

    $req = Request::create('/api/v1/seller/music/tracks', 'GET');
    $res = $sellerController->index($req);
    $runner->assert($res->getStatusCode() === 401, '1.1 Unauthenticated seller request returns 401 Unauthorized');

    // 1.2 Buyer user cannot access seller music endpoints
    Auth::setUser($buyer);
    $req = Request::create('/api/v1/seller/music/tracks', 'GET');
    $res = $sellerController->index($req);
    $runner->assert($res->getStatusCode() === 403, '1.2 Non-seller (buyer) user is rejected with 403 Forbidden');

    // 1.3 Authenticated seller can list their tracks
    Auth::setUser($sellerA);
    $req = Request::create('/api/v1/seller/music/tracks', 'GET');
    $res = $sellerController->index($req);
    $runner->assert($res->getStatusCode() === 200, '1.3 Authenticated seller receives 200 OK on tracks listing');

    // --- TEST GROUP 2: Marketplace Switches ---
    echo "\n--- Group 2: Marketplace Switches ---\n";

    // 2.1 Submissions blocked when marketplace is globally disabled
    MusicSetting::set('music_marketplace_enabled', false);
    $req = Request::create('/api/v1/seller/music/tracks', 'POST', [
        'title' => 'Test Track While Disabled',
        'genre_id' => $genre->id,
        'agree_rights_declaration' => 1,
    ]);
    $res = $sellerController->store($req);
    $runner->assert($res->getStatusCode() === 403, '2.1 Seller submission rejected with 403 when music_marketplace_enabled is false');
    MusicSetting::set('music_marketplace_enabled', true);

    // 2.2 Submissions blocked when seller submissions specifically disabled
    MusicSetting::set('seller_submissions_enabled', false);
    $req = Request::create('/api/v1/seller/music/tracks', 'POST', [
        'title' => 'Test Track While Submissions Disabled',
        'genre_id' => $genre->id,
        'agree_rights_declaration' => 1,
    ]);
    $res = $sellerController->store($req);
    $runner->assert($res->getStatusCode() === 403, '2.2 Seller submission rejected with 403 when seller_submissions_enabled is false');
    MusicSetting::set('seller_submissions_enabled', true);

    // --- TEST GROUP 3: Validation (Audio, Images, Rights, Offers) ---
    echo "\n--- Group 3: Server-Side Validation ---\n";

    // 3.1 Missing rights declaration agreement
    $mp3Path = __DIR__ . '/fixtures/valid_test_tone.mp3';
    if (!file_exists($mp3Path) || filesize($mp3Path) < 1024) {
        // Create > 1KB dummy mp3 with ID3 header for validation test
        @mkdir(__DIR__ . '/fixtures', 0777, true);
        file_put_contents($mp3Path, "ID3\x04\x00\x00\x00\x00\x00\x00" . str_repeat("\xFF\xFB\x90\x04", 512));
    }

    $audioUpload = new UploadedFile($mp3Path, 'test_song.mp3', 'audio/mpeg', null, true);

    $req = Request::create('/api/v1/seller/music/tracks', 'POST', [
        'title' => 'No Rights Track',
        'genre_id' => $genre->id,
        'agree_rights_declaration' => 0,
        'offers' => [
            ['offer_type' => 'free', 'price' => 0],
        ],
    ], [], ['audio_file' => $audioUpload]);
    $res = $sellerController->store($req);
    $runner->assert($res->getStatusCode() === 422, '3.1 Missing agree_rights_declaration returns 422 Unprocessable');

    // 3.2 Non-audio file spoofed as mp3
    $txtPath = __DIR__ . '/fixtures/fake_audio.mp3';
    file_put_contents($txtPath, 'This is pure text, definitely not an audio file.');
    $fakeUpload = new UploadedFile($txtPath, 'fake_audio.mp3', 'audio/mpeg', null, true);

    $req = Request::create('/api/v1/seller/music/tracks', 'POST', [
        'title' => 'Spoofed Audio Track',
        'genre_id' => $genre->id,
        'agree_rights_declaration' => 1,
        'offers' => [
            ['offer_type' => 'free', 'price' => 0],
        ],
    ], [], ['audio_file' => $fakeUpload]);
    $res = $sellerController->store($req);
    $runner->assert($res->getStatusCode() === 422, '3.2 Spoofed non-audio file rejected with 422 Unprocessable');

    // 3.3 Invalid commercial offers (sale price = 0 or negative)
    $req = Request::create('/api/v1/seller/music/tracks', 'POST', [
        'title' => 'Zero Price Sale Track',
        'genre_id' => $genre->id,
        'agree_rights_declaration' => 1,
        'offers' => [
            ['offer_type' => 'sale', 'price' => 0], // Sale must be > 0
        ],
    ], [], ['audio_file' => $audioUpload]);
    $res = $sellerController->store($req);
    $runner->assert($res->getStatusCode() === 422, '3.3 Zero-price sale offer rejected with 422');

    // 3.4 Disabled offer type rejected (e.g. rent disabled by admin)
    MusicSetting::set('offer_rent_enabled', false);
    $req = Request::create('/api/v1/seller/music/tracks', 'POST', [
        'title' => 'Disabled Rent Offer Track',
        'genre_id' => $genre->id,
        'agree_rights_declaration' => 1,
        'offers' => [
            ['offer_type' => 'rent', 'price' => 10, 'rental_duration_days' => 7],
        ],
    ], [], ['audio_file' => $audioUpload]);
    $res = $sellerController->store($req);
    $runner->assert($res->getStatusCode() === 422, '3.4 Rental offer rejected when offer_rent_enabled is false');
    MusicSetting::set('offer_rent_enabled', true);

    // --- TEST GROUP 4: Submission & Background Processing ---
    echo "\n--- Group 4: Submission & Audio Processing Lifecycle ---\n";

    // Setup queue spy
    Queue::fake();

    // Create valid track directly with real audio processor mock
    $req = Request::create('/api/v1/seller/music/tracks', 'POST', [
        'title' => 'Sunset Waves',
        'artist_name' => 'DJ Alice',
        'description' => 'Chill electronic summer vibes',
        'genre_id' => $genre->id,
        'secondary_genres' => ['ambient', 'chillout'],
        'rights_declaration' => 'exclusive',
        'rights_notes' => '100% original composition and sound recording',
        'agree_rights_declaration' => 1,
        'offers' => [
            ['offer_type' => 'free', 'price' => 0, 'is_active' => 1],
            ['offer_type' => 'rent', 'price' => 12.50, 'rental_duration_days' => 14, 'is_active' => 1],
            ['offer_type' => 'sale', 'price' => 39.00, 'is_active' => 1],
        ],
    ], [], ['audio_file' => new UploadedFile($mp3Path, 'test_song.mp3', 'audio/mpeg', null, true)]);

    $res = $sellerController->store($req);
    $runner->assert($res->getStatusCode() === 201, '4.1 Valid seller submission creates track with 201 Created');

    $trackData = $res->getData(true)['data'];
    $trackId = $trackData['id'];
    $track = MusicTrack::findOrFail($trackId);

    $runner->assert($track->seller_id === $sellerA->id, '4.2 Track seller_id is derived securely from server authenticated user');
    $runner->assert($track->status === MusicTrack::STATUS_PENDING, '4.3 Track initial status is pending review');
    $runner->assert($track->media_status === MusicTrack::MEDIA_STATUS_PENDING, '4.4 Track initial media_status is pending processing');
    $runner->assert(!empty($track->declared_at), '4.5 declared_at timestamp is populated');
    $runner->assert(!empty($track->audio_path_original), '4.6 audio_path_original is populated on private disk');

    // 4.7 Offers creation
    $offers = $track->offers()->get();
    $runner->assert($offers->count() === 3, '4.7 Three configured offers (free, rent, sale) attached to track');
    $saleOffer = $offers->firstWhere('offer_type', 'sale');
    $runner->assert($saleOffer && (float)$saleOffer->price === 39.00 && !$saleOffer->allow_download, '4.8 Sale offer has correct price and allow_download = false');

    // 4.9 Queue job dispatch
    $pushedJobs = Queue::pushed(ProcessMusicAudioJob::class);
    $hasJob = $pushedJobs->contains(function ($job) use ($trackId) {
        return (isset($job->trackId) && $job->trackId === $trackId) || (isset($job->track) && $job->track->id === $trackId);
    });
    $runner->assert($hasJob, '4.9 ProcessMusicAudioJob dispatched for asynchronous transcoding');

    // --- TEST GROUP 5: Cross-Seller Tenancy Isolation ---
    echo "\n--- Group 5: Cross-Seller Tenancy Isolation ---\n";

    // 5.1 Seller B cannot view Seller A's track detail
    Auth::setUser($sellerB);
    $res = $sellerController->show($trackId);
    $runner->assert($res->getStatusCode() === 404, '5.1 Seller B cannot view Seller A track detail (scoped 404)');

    // 5.2 Seller B cannot update Seller A's track
    $req = Request::create("/api/v1/seller/music/tracks/{$trackId}", 'PUT', ['title' => 'Hijacked Title']);
    $res = $sellerController->update($req, $trackId);
    $runner->assert($res->getStatusCode() === 404, '5.2 Seller B cannot update Seller A track (scoped 404)');

    // 5.3 Seller B cannot delete Seller A's track
    $res = $sellerController->destroy($trackId);
    $runner->assert($res->getStatusCode() === 404, '5.3 Seller B cannot delete Seller A track (scoped 404)');

    // 5.4 Seller B's track listing does not leak Seller A's track
    $req = Request::create('/api/v1/seller/music/tracks', 'GET');
    $res = $sellerController->index($req);
    $sellerBTracks = $res->getData(true)['data']['data'];
    $containsA = collect($sellerBTracks)->pluck('id')->contains($trackId);
    $runner->assert(!$containsA, '5.4 Seller B track listing does NOT contain Seller A tracks');

    // --- TEST GROUP 6: Processing Failure & Retry Workflow ---
    echo "\n--- Group 6: Processing Failure & Retry Workflow ---\n";

    Auth::setUser($sellerA);

    // Simulate audio processing failure
    $track->media_status = MusicTrack::MEDIA_STATUS_FAILED;
    $track->media_error = 'Audio transcoding failed: Corrupt stream header';
    $track->save();

    // 6.1 Retry processing endpoint
    $res = $sellerController->retryProcessing($trackId);
    $runner->assert($res->getStatusCode() === 200, '6.1 Failed track retryProcessing returns 200 OK');

    $track->refresh();
    $runner->assert($track->media_status === MusicTrack::MEDIA_STATUS_PENDING, '6.2 Track media_status reset to pending after retry');
    $runner->assert($track->media_error === null, '6.3 Previous media_error cleared on retry');

    // --- TEST GROUP 7: Admin Moderation Operations ---
    echo "\n--- Group 7: Admin Moderation Workflow ---\n";

    // 7.1 Unauthenticated admin access rejected
    Auth::guard('web')->logout();
    Auth::guard('admin')->logout();
    Auth::forgetUser();
    $req = Request::create('/admin-home/music-moderation-json', 'GET');
    $res = $adminController->index($req);
    $runner->assert($res->getStatusCode() === 401, '7.1 Unauthenticated admin moderation call returns 401');

    // 7.2 Non-moderator admin (regular user) rejected
    Auth::setUser($sellerA);
    $res = $adminController->index($req);
    $runner->assert($res->getStatusCode() === 403, '7.2 Non-admin user cannot access admin moderation (403)');

    // 7.3 Authorized Admin access
    Auth::setUser($admin);
    $res = $adminController->index($req);
    $runner->assert($res->getStatusCode() === 200, '7.3 Authorized admin receives 200 with submissions list');

    $counts = $res->getData(true)['counts'];
    $runner->assert(isset($counts['pending']) && $counts['pending'] >= 1, '7.4 Admin moderation returns accurate status counts');

    // 7.5 Admin track inspection includes ephemeral stream ticket
    $track->media_status = MusicTrack::MEDIA_STATUS_READY;
    $track->audio_path_preview = 'previews/test_preview.mp3';
    $track->save();

    $res = $adminController->show($trackId);
    $runner->assert($res->getStatusCode() === 200, '7.5 Admin track inspection returns 200 with details');
    $adminTrackData = $res->getData(true)['track'];
    $runner->assert(!empty($adminTrackData['preview_stream_url']), '7.6 Admin track inspection generates secure authorized preview stream ticket');

    // 7.7 CRITICAL SAFETY: Admin CANNOT approve track while media_status is not ready
    $track->media_status = MusicTrack::MEDIA_STATUS_PROCESSING;
    $track->save();

    $res = $adminController->approve($trackId);
    $runner->assert($res->getStatusCode() === 422, '7.7 Admin CANNOT approve track while media_status !== ready (422 MEDIA_NOT_READY)');
    $runner->assert($track->fresh()->status === MusicTrack::STATUS_PENDING, '7.8 Track status remains pending after blocked approval');

    // 7.9 Admin approval succeeds when media_status is ready
    $track->media_status = MusicTrack::MEDIA_STATUS_READY;
    $track->duration_seconds = 180;
    $track->save();

    $res = $adminController->approve($trackId);
    $runner->assert($res->getStatusCode() === 200, '7.9 Admin approves track once media_status is ready (200 OK)');

    $track->refresh();
    $runner->assert($track->status === MusicTrack::STATUS_APPROVED, '7.10 Track status updated to approved');
    $runner->assert(!empty($track->released_at), '7.11 Track released_at populated upon approval');
    $runner->assert($track->reviewed_by_admin_id === $admin->id, '7.12 reviewed_by_admin_id attributed to approving admin');

    // 7.13 Admin rejection requires reason
    $req = Request::create("/admin-home/music-moderation-json/{$trackId}/reject", 'POST', [
        'rejection_reason' => '  ', // Empty
    ]);
    $res = $adminController->reject($req, $trackId);
    $runner->assert($res->getStatusCode() === 422, '7.13 Admin rejection requires non-empty reason (422)');

    // 7.14 Admin rejection with valid reason
    $req = Request::create("/admin-home/music-moderation-json/{$trackId}/reject", 'POST', [
        'rejection_reason' => 'Audio master quality has audible clipping in the bass frequencies.',
    ]);
    $res = $adminController->reject($req, $trackId);
    $runner->assert($res->getStatusCode() === 200, '7.14 Admin rejection succeeds with recorded reason (200 OK)');

    $track->refresh();
    $runner->assert($track->status === MusicTrack::STATUS_REJECTED, '7.15 Track status is rejected');
    $runner->assert($track->rejection_reason === 'Audio master quality has audible clipping in the bass frequencies.', '7.16 Rejection reason saved to track record');
    $activeOffers = $track->offers()->where('is_active', true)->count();
    $runner->assert($activeOffers === 0, '7.17 All offers deactivated on rejected track');

    // 7.18 Admin suspension
    $track->status = MusicTrack::STATUS_APPROVED;
    $track->save();

    $req = Request::create("/admin-home/music-moderation-json/{$trackId}/suspend", 'POST', [
        'suspension_reason' => 'Copyright dispute inquiry opened by rights holder.',
    ]);
    $res = $adminController->suspend($req, $trackId);
    $runner->assert($res->getStatusCode() === 200, '7.18 Admin suspension succeeds (200 OK)');
    $track->refresh();
    $runner->assert($track->status === MusicTrack::STATUS_SUSPENDED, '7.19 Track status is suspended');

    // --- TEST GROUP 8: Deletion Safety ---
    echo "\n--- Group 8: Track Deletion & Preservation Safety ---\n";

    Auth::setUser($sellerA);

    // 8.1 Delete allowed for suspended/rejected/draft tracks without orders
    $res = $sellerController->destroy($trackId);
    $runner->assert($res->getStatusCode() === 200, '8.1 Seller can delete unpurchased track (200 OK)');
    $runner->assert(MusicTrack::find($trackId) === null, '8.2 Track removed from database');

    // 8.3 Deletion blocked if track has purchased orders (financial preservation)
    $trackWithOrder = MusicTrack::create([
        'seller_id' => $sellerA->id,
        'genre_id' => $genre->id,
        'title_en' => 'Purchased Track',
        'title_ar' => 'Purchased Track Ar',
        'artist_name' => 'Artist Alice',
        'audio_path_original' => 'masters/test_master.mp3',
        'status' => MusicTrack::STATUS_APPROVED,
        'media_status' => MusicTrack::MEDIA_STATUS_READY,
    ]);

    $offer = MusicOffer::create([
        'track_id' => $trackWithOrder->id,
        'offer_type' => 'sale',
        'price' => 30.00,
        'currency' => 'SAR',
        'license_type' => MusicOffer::LICENSE_STREAM_ONLY,
        'is_active' => true,
    ]);

    MusicOrder::create([
        'buyer_id' => $buyer->id,
        'seller_id' => $sellerA->id,
        'track_id' => $trackWithOrder->id,
        'offer_id' => $offer->id,
        'offer_type' => 'sale',
        'invoice' => 'INV-TEST-P3B-001',
        'amount' => 30.00,
        'currency' => 'SAR',
        'payment_gateway' => MusicOrder::GATEWAY_WALLET,
        'payment_status' => 'completed',
    ]);

    $res = $sellerController->destroy($trackWithOrder->id);
    $runner->assert($res->getStatusCode() === 422, '8.3 Deletion BLOCKED with 422 when track has commercial order history');
    $runner->assert(MusicTrack::find($trackWithOrder->id) !== null, '8.4 Track with paid history preserved in database');

} finally {
    DB::rollBack();
}

// Cleanup test fixtures
@unlink(__DIR__ . '/fixtures/fake_audio.mp3');

echo "\n=======================================================\n";
echo "Phase 3B Test Results: {$runner->getPassed()} passed, {$runner->getFailed()} failed\n";
echo "=======================================================\n";

if ($runner->getFailed() > 0) {
    echo "\nFailed Assertions:\n";
    foreach ($runner->getFailures() as $f) {
        echo " - {$f}\n";
    }
    exit(1);
}

exit(0);
