<?php

/**
 * FUN MOMENT — Customer Multi-Playlist Test Suite
 *
 * Verifies:
 * 1. Creating multiple playlists for one customer.
 * 2. Playlist isolation between different customer accounts.
 * 3. Adding tracks from different creators, albums, Originals, and remixes.
 * 4. Adding a track to several playlists.
 * 5. Duplicate prevention within one playlist.
 * 6. Reorder, rename, removal, and deletion behavior (deleting playlist does not delete tracks).
 * 7. Free, purchased, active-rental, expired-rental, purchase-required, and unavailable status mapping.
 * 8. Confirming playlist membership never bypasses stream-ticket authorization.
 * 9. Rental expiry being respected at playback time.
 * 10. Guest/unauthenticated requests not accessing private playlist operations.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Http\Controllers\Api\MusicPlaylistController;
use App\Http\Controllers\Api\MusicStreamController;
use App\MusicAlbum;
use App\MusicEntitlement;
use App\MusicGenre;
use App\MusicOffer;
use App\MusicPlaylist;
use App\MusicPlaylistTrack;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\MusicStorageService;
use App\Services\Music\MusicStreamService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerPlaylistTestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function assert(bool $condition, string $description): void
    {
        if ($condition) {
            echo "  [PASS] {$description}\n";
            $this->passed++;
        } else {
            echo "  [FAIL] {$description}\n";
            $this->failed++;
            $this->errors[] = $description;
        }
    }

    public function run(): void
    {
        echo "========================================================================\n";
        echo "FUN MOMENT — CUSTOMER MULTI-PLAYLIST TEST SUITE\n";
        echo "========================================================================\n\n";

        DB::beginTransaction();

        try {
            // Setup Settings
            MusicSetting::set('music_marketplace_enabled', 1, 'Enable for testing');
            MusicSetting::set('music_checkout_enabled', 0, 'Disabled checkout');
            MusicSetting::set('music_vat_enabled', 0, 'Disabled VAT');

            // Setup Base Entities
            $admin = Admin::first() ?: Admin::create([
                'name' => 'Staging Master Admin',
                'username' => 'staging_master_admin',
                'email' => 'admin_test_' . Str::random(6) . '@funmoments.unifinitylab.com',
                'password' => bcrypt('Secret123!'),
            ]);

            $genre = MusicGenre::where('status', MusicGenre::STATUS_ACTIVE)->first() ?: MusicGenre::create([
                'name_en' => 'Techno',
                'name_ar' => 'تكنو',
                'slug' => 'techno-test-' . Str::random(4),
                'sort_order' => 1,
                'status' => MusicGenre::STATUS_ACTIVE,
            ]);

            // Create Customer A and Customer B
            $customerA = User::create([
                'name' => 'Sarah Al-Customer',
                'username' => 'sarah_music_' . Str::random(4),
                'email' => 'sarah_' . Str::random(6) . '@example.com',
                'phone' => '+9665' . rand(10000000, 99999999),
                'password' => bcrypt('Password123!'),
                'user_type' => User::USER_TYPE_BUYER,
                'user_status' => 1,
            ]);

            $customerB = User::create([
                'name' => 'Fahad Al-Buyer',
                'username' => 'fahad_music_' . Str::random(4),
                'email' => 'fahad_' . Str::random(6) . '@example.com',
                'phone' => '+9665' . rand(10000000, 99999999),
                'password' => bcrypt('Password123!'),
                'user_type' => User::USER_TYPE_BUYER,
                'user_status' => 1,
            ]);

            $seller = User::create([
                'name' => 'DJ Riyadh Waves',
                'username' => 'dj_riyadh_' . Str::random(4),
                'email' => 'dj_riyadh_' . Str::random(6) . '@example.com',
                'phone' => '+9665' . rand(10000000, 99999999),
                'password' => bcrypt('Password123!'),
                'user_type' => User::USER_TYPE_SELLER,
                'user_status' => 1,
            ]);

            $album = MusicAlbum::create([
                'title_en' => 'Desert Horizons',
                'title_ar' => 'آفاق الصحراء',
                'artist_name' => 'FUN MOMENT Originals',
                'genre_id' => $genre->id,
                'status' => 'published',
                'owner_type' => 'admin',
                'admin_id' => $admin->id,
                'released_at' => now()->subDay(),
            ]);

            // Track 1: First-Party Original, Free offer, Album track
            $track1 = MusicTrack::create([
                'title_en' => 'Dunes Anthem',
                'title_ar' => 'نشيد الكثبان',
                'artist_name' => 'FUN MOMENT Originals',
                'owner_type' => 'admin',
                'admin_id' => $admin->id,
                'genre_id' => $genre->id,
                'album_id' => $album->id,
                'track_number' => 1,
                'status' => 'approved',
                'media_status' => 'ready',
                'audio_path_original' => 'music_private/masters/track1.mp3',
                'audio_path_preview' => 'music_private/previews/track1_prev.mp3',
                'audio_path_stream' => 'music_private/streams/track1_stream.mp3',
                'duration_seconds' => 210,
                'preview_duration_seconds' => 30,
                'released_at' => now()->subDays(2),
            ]);
            MusicOffer::create([
                'track_id' => $track1->id,
                'offer_type' => 'free',
                'price' => 0.00,
                'currency' => 'SAR',
                'is_active' => true,
            ]);

            // Track 2: Seller track, Paid (Sale & Rent), has Perpetual entitlement for Customer A
            $track2 = MusicTrack::create([
                'title_en' => 'Neon Nights',
                'title_ar' => 'ليالي النيون',
                'artist_name' => 'DJ Riyadh Waves',
                'owner_type' => 'seller',
                'seller_id' => $seller->id,
                'genre_id' => $genre->id,
                'status' => 'approved',
                'media_status' => 'ready',
                'audio_path_original' => 'music_private/masters/track2.mp3',
                'audio_path_preview' => 'music_private/previews/track2_prev.mp3',
                'audio_path_stream' => 'music_private/streams/track2_stream.mp3',
                'duration_seconds' => 195,
                'preview_duration_seconds' => 30,
                'released_at' => now()->subDays(3),
            ]);
            $offer2Sale = MusicOffer::create([
                'track_id' => $track2->id,
                'offer_type' => 'sale',
                'price' => 29.99,
                'currency' => 'SAR',
                'is_active' => true,
            ]);
            // Grant perpetual entitlement to Customer A
            MusicEntitlement::create([
                'user_id' => $customerA->id,
                'track_id' => $track2->id,
                'offer_id' => $offer2Sale->id,
                'access_type' => 'stream_only',
                'is_active' => true,
                'starts_at' => now()->subDays(1),
                'expires_at' => null, // Perpetual
            ]);

            // Track 3: Seller track with Active Rental for Customer A
            $track3 = MusicTrack::create([
                'title_en' => 'Pulse of Diriyah',
                'title_ar' => 'نبض الدرعية',
                'artist_name' => 'DJ Riyadh Waves',
                'owner_type' => 'seller',
                'seller_id' => $seller->id,
                'genre_id' => $genre->id,
                'status' => 'approved',
                'media_status' => 'ready',
                'audio_path_original' => 'music_private/masters/track3.mp3',
                'audio_path_preview' => 'music_private/previews/track3_prev.mp3',
                'audio_path_stream' => 'music_private/streams/track3_stream.mp3',
                'duration_seconds' => 180,
                'preview_duration_seconds' => 30,
                'released_at' => now()->subDays(4),
            ]);
            $offer3Rent = MusicOffer::create([
                'track_id' => $track3->id,
                'offer_type' => 'rent',
                'price' => 9.99,
                'currency' => 'SAR',
                'rental_duration_days' => 7,
                'is_active' => true,
            ]);
            // Active rental for Customer A (expires in 5 days)
            MusicEntitlement::create([
                'user_id' => $customerA->id,
                'track_id' => $track3->id,
                'offer_id' => $offer3Rent->id,
                'access_type' => 'stream_only',
                'is_active' => true,
                'starts_at' => now()->subDays(2),
                'expires_at' => now()->addDays(5),
            ]);

            // Track 4: Seller track with Expired Rental for Customer A
            $track4 = MusicTrack::create([
                'title_en' => 'Midnight Mirage',
                'title_ar' => 'سراب منتصف الليل',
                'artist_name' => 'DJ Riyadh Waves',
                'owner_type' => 'seller',
                'seller_id' => $seller->id,
                'genre_id' => $genre->id,
                'status' => 'approved',
                'media_status' => 'ready',
                'audio_path_original' => 'music_private/masters/track4.mp3',
                'audio_path_preview' => 'music_private/previews/track4_prev.mp3',
                'audio_path_stream' => 'music_private/streams/track4_stream.mp3',
                'duration_seconds' => 200,
                'preview_duration_seconds' => 30,
                'released_at' => now()->subDays(10),
            ]);
            $offer4Rent = MusicOffer::create([
                'track_id' => $track4->id,
                'offer_type' => 'rent',
                'price' => 8.00,
                'currency' => 'SAR',
                'rental_duration_days' => 3,
                'is_active' => true,
            ]);
            // Expired rental for Customer A (expired 2 days ago)
            MusicEntitlement::create([
                'user_id' => $customerA->id,
                'track_id' => $track4->id,
                'offer_id' => $offer4Rent->id,
                'access_type' => 'stream_only',
                'is_active' => false,
                'starts_at' => now()->subDays(5),
                'expires_at' => now()->subDays(2),
            ]);

            // Track 5: Paid track, neither bought nor rented by Customer A (Purchase Required)
            $track5 = MusicTrack::create([
                'title_en' => 'Red Sea Groove',
                'title_ar' => 'إيقاع البحر الأحمر',
                'artist_name' => 'DJ Riyadh Waves',
                'owner_type' => 'seller',
                'seller_id' => $seller->id,
                'genre_id' => $genre->id,
                'status' => 'approved',
                'media_status' => 'ready',
                'audio_path_original' => 'music_private/masters/track5.mp3',
                'audio_path_preview' => 'music_private/previews/track5_prev.mp3',
                'audio_path_stream' => 'music_private/streams/track5_stream.mp3',
                'duration_seconds' => 175,
                'preview_duration_seconds' => 30,
                'released_at' => now()->subDays(5),
            ]);
            MusicOffer::create([
                'track_id' => $track5->id,
                'offer_type' => 'sale',
                'price' => 19.99,
                'currency' => 'SAR',
                'is_active' => true,
            ]);

            // Track 6: Suspended / Unavailable track
            $track6 = MusicTrack::create([
                'title_en' => 'Unpublished Vault Track',
                'title_ar' => 'مسار معلق',
                'artist_name' => 'DJ Unknown',
                'owner_type' => 'seller',
                'seller_id' => $seller->id,
                'genre_id' => $genre->id,
                'status' => 'suspended',
                'media_status' => 'ready',
                'audio_path_original' => 'music_private/masters/track6.mp3',
                'duration_seconds' => 160,
                'released_at' => now()->subDays(2),
            ]);

            // Track 7: Licensed Remix of Track 1
            $track7 = MusicTrack::create([
                'title_en' => 'Dunes Anthem (Club Remix)',
                'title_ar' => 'نشيد الكثبان (ريمكس)',
                'artist_name' => 'DJ Riyadh Waves',
                'owner_type' => 'seller',
                'seller_id' => $seller->id,
                'original_track_id' => $track1->id,
                'genre_id' => $genre->id,
                'status' => 'approved',
                'media_status' => 'ready',
                'audio_path_original' => 'music_private/masters/track7.mp3',
                'audio_path_preview' => 'music_private/previews/track7_prev.mp3',
                'duration_seconds' => 220,
                'preview_duration_seconds' => 30,
                'released_at' => now()->subDay(),
            ]);
            MusicOffer::create([
                'track_id' => $track7->id,
                'offer_type' => 'free',
                'price' => 0.00,
                'currency' => 'SAR',
                'is_active' => true,
            ]);

            $controller = new MusicPlaylistController();

            // -------------------------------------------------------------
            // SECTION 1: Creating multiple playlists for one customer
            // -------------------------------------------------------------
            echo "--- Section 1: Creating Multiple Playlists for One Customer ---\n";

            Auth::guard('sanctum')->setUser($customerA);

            $req1 = Request::create('/api/v1/music/playlists', 'POST', [
                'name' => 'Riyadh Late Night Beats',
                'description' => 'Chill electronic beats for night drives',
            ]);
            $req1->setUserResolver(fn() => $customerA);
            $res1 = $controller->store($req1);
            $data1 = json_decode($res1->getContent(), true);

            $this->assert($res1->getStatusCode() === 201, 'Playlist 1 created with HTTP 201');
            $this->assert($data1['success'] === true, 'Response success is true');
            $this->assert($data1['data']['name'] === 'Riyadh Late Night Beats', 'Playlist name matches input');
            $playlistId1 = $data1['data']['id'];

            $req2 = Request::create('/api/v1/music/playlists', 'POST', [
                'name' => 'Desert Chill & Ambient',
                'description' => 'Relaxing desert sunset melodies',
            ]);
            $req2->setUserResolver(fn() => $customerA);
            $res2 = $controller->store($req2);
            $data2 = json_decode($res2->getContent(), true);

            $this->assert($res2->getStatusCode() === 201, 'Playlist 2 created with HTTP 201');
            $playlistId2 = $data2['data']['id'];

            // Customer A lists playlists
            $listReq = Request::create('/api/v1/music/playlists', 'GET');
            $listReq->setUserResolver(fn() => $customerA);
            $listRes = $controller->index($listReq);
            $listData = json_decode($listRes->getContent(), true);

            $this->assert($listRes->getStatusCode() === 200, 'Customer A lists playlists successfully (HTTP 200)');
            $this->assert(count($listData['data']) === 2, 'Customer A has exactly 2 playlists');

            // -------------------------------------------------------------
            // SECTION 2: Customer Isolation & Security
            // -------------------------------------------------------------
            echo "\n--- Section 2: Customer Isolation & Security ---\n";

            // Customer B attempts to list playlists
            $listReqB = Request::create('/api/v1/music/playlists', 'GET');
            $listReqB->setUserResolver(fn() => $customerB);
            $listResB = $controller->index($listReqB);
            $listDataB = json_decode($listResB->getContent(), true);

            $this->assert(count($listDataB['data']) === 0, 'Customer B starts with 0 playlists (isolated from Customer A)');

            // Customer B attempts to view Customer A's playlist by guessing ID
            $showReqB = Request::create("/api/v1/music/playlists/{$playlistId1}", 'GET');
            $showReqB->setUserResolver(fn() => $customerB);
            $showResB = $controller->show($showReqB, $playlistId1);

            $this->assert($showResB->getStatusCode() === 404, 'Customer B cannot view Customer A private playlist (HTTP 404)');

            // Customer B attempts to update Customer A's playlist
            $updateReqB = Request::create("/api/v1/music/playlists/{$playlistId1}", 'PUT', [
                'name' => 'Hacked Name',
            ]);
            $updateReqB->setUserResolver(fn() => $customerB);
            $updateResB = $controller->update($updateReqB, $playlistId1);

            $this->assert($updateResB->getStatusCode() === 404, 'Customer B cannot update Customer A playlist (HTTP 404)');

            // Customer B attempts to add track to Customer A's playlist
            $addReqB = Request::create("/api/v1/music/playlists/{$playlistId1}/tracks", 'POST', [
                'track_id' => $track1->id,
            ]);
            $addReqB->setUserResolver(fn() => $customerB);
            $addResB = $controller->addTrack($addReqB, $playlistId1);

            $this->assert($addResB->getStatusCode() === 404, 'Customer B cannot add tracks to Customer A playlist (HTTP 404)');

            // Customer B attempts to delete Customer A's playlist
            $delReqB = Request::create("/api/v1/music/playlists/{$playlistId1}", 'DELETE');
            $delReqB->setUserResolver(fn() => $customerB);
            $delResB = $controller->destroy($delReqB, $playlistId1);

            $this->assert($delResB->getStatusCode() === 404, 'Customer B cannot delete Customer A playlist (HTTP 404)');

            // Unauthenticated guest requests
            Auth::forgetGuards();
            $guestReq = Request::create('/api/v1/music/playlists', 'GET');
            $guestReq->setUserResolver(fn() => null);
            $guestRes = $controller->index($guestReq);

            $this->assert($guestRes->getStatusCode() === 401, 'Unauthenticated guest receives HTTP 401');

            // Restore Customer A for remaining tests
            Auth::guard('sanctum')->setUser($customerA);

            // -------------------------------------------------------------
            // SECTION 3: Adding Diverse Tracks (Originals, Remixes, Albums, Artists)
            // -------------------------------------------------------------
            echo "\n--- Section 3: Adding Diverse Tracks & Duplicate Prevention ---\n";

            // Add Track 1 (Original, Album)
            $addT1 = Request::create("/api/v1/music/playlists/{$playlistId1}/tracks", 'POST', ['track_id' => $track1->id]);
            $addT1->setUserResolver(fn() => $customerA);
            $resT1 = $controller->addTrack($addT1, $playlistId1);
            $this->assert($resT1->getStatusCode() === 201, 'Track 1 (Original / Album) added to Playlist 1');

            // Add Track 2 (Purchased)
            $addT2 = Request::create("/api/v1/music/playlists/{$playlistId1}/tracks", 'POST', ['track_id' => $track2->id]);
            $addT2->setUserResolver(fn() => $customerA);
            $resT2 = $controller->addTrack($addT2, $playlistId1);
            $this->assert($resT2->getStatusCode() === 201, 'Track 2 (Purchased) added to Playlist 1');

            // Add Track 3 (Rented)
            $addT3 = Request::create("/api/v1/music/playlists/{$playlistId1}/tracks", 'POST', ['track_id' => $track3->id]);
            $addT3->setUserResolver(fn() => $customerA);
            $resT3 = $controller->addTrack($addT3, $playlistId1);
            $this->assert($resT3->getStatusCode() === 201, 'Track 3 (Active Rental) added to Playlist 1');

            // Add Track 4 (Rental Expired)
            $addT4 = Request::create("/api/v1/music/playlists/{$playlistId1}/tracks", 'POST', ['track_id' => $track4->id]);
            $addT4->setUserResolver(fn() => $customerA);
            $resT4 = $controller->addTrack($addT4, $playlistId1);
            $this->assert($resT4->getStatusCode() === 201, 'Track 4 (Expired Rental) added to Playlist 1');

            // Add Track 5 (Purchase Required)
            $addT5 = Request::create("/api/v1/music/playlists/{$playlistId1}/tracks", 'POST', ['track_id' => $track5->id]);
            $addT5->setUserResolver(fn() => $customerA);
            $resT5 = $controller->addTrack($addT5, $playlistId1);
            $this->assert($resT5->getStatusCode() === 201, 'Track 5 (Purchase Required) added to Playlist 1');

            // Add Track 6 (Suspended / Unavailable)
            $addT6 = Request::create("/api/v1/music/playlists/{$playlistId1}/tracks", 'POST', ['track_id' => $track6->id]);
            $addT6->setUserResolver(fn() => $customerA);
            $resT6 = $controller->addTrack($addT6, $playlistId1);
            $this->assert($resT6->getStatusCode() === 201, 'Track 6 (Suspended) added to Playlist 1');

            // Add Track 7 (Remix)
            $addT7 = Request::create("/api/v1/music/playlists/{$playlistId1}/tracks", 'POST', ['track_id' => $track7->id]);
            $addT7->setUserResolver(fn() => $customerA);
            $resT7 = $controller->addTrack($addT7, $playlistId1);
            $this->assert($resT7->getStatusCode() === 201, 'Track 7 (Licensed Remix) added to Playlist 1');

            // -------------------------------------------------------------
            // SECTION 4: Multi-Playlist Track Inclusion & Duplicate Prevention
            // -------------------------------------------------------------
            echo "\n--- Section 4: Multi-Playlist Track Inclusion & Duplicate Prevention ---\n";

            // Add Track 1 to Playlist 2 as well (Same track in multiple playlists)
            $addT1P2 = Request::create("/api/v1/music/playlists/{$playlistId2}/tracks", 'POST', ['track_id' => $track1->id]);
            $addT1P2->setUserResolver(fn() => $customerA);
            $resT1P2 = $controller->addTrack($addT1P2, $playlistId2);
            $this->assert($resT1P2->getStatusCode() === 201, 'Track 1 successfully added to Playlist 2 (multi-playlist support)');

            // Try adding Track 1 again to Playlist 1 (Duplicate prevention)
            $dupReq = Request::create("/api/v1/music/playlists/{$playlistId1}/tracks", 'POST', ['track_id' => $track1->id]);
            $dupReq->setUserResolver(fn() => $customerA);
            $dupRes = $controller->addTrack($dupReq, $playlistId1);
            $dupData = json_decode($dupRes->getContent(), true);

            $this->assert($dupRes->getStatusCode() === 422, 'Duplicate track rejected with HTTP 422');
            $this->assert($dupData['error_code'] === 'DUPLICATE_TRACK', 'Error code is DUPLICATE_TRACK');

            // Test available-playlists-for-track endpoint
            $availReq = Request::create("/api/v1/music/playlists-for-track/{$track1->id}", 'GET');
            $availReq->setUserResolver(fn() => $customerA);
            $availRes = $controller->availableForTrack($availReq, $track1->id);
            $availData = json_decode($availRes->getContent(), true);

            $this->assert($availRes->getStatusCode() === 200, 'availableForTrack returns HTTP 200');
            $p1Entry = collect($availData['data'])->firstWhere('id', $playlistId1);
            $p2Entry = collect($availData['data'])->firstWhere('id', $playlistId2);
            $this->assert($p1Entry['contains_track'] === true, 'Playlist 1 correctly flagged as containing Track 1');
            $this->assert($p2Entry['contains_track'] === true, 'Playlist 2 correctly flagged as containing Track 1');

            // -------------------------------------------------------------
            // SECTION 5: Authoritative Access Status Resolution
            // -------------------------------------------------------------
            echo "\n--- Section 5: Authoritative Access Status Resolution ---\n";

            $viewReq = Request::create("/api/v1/music/playlists/{$playlistId1}", 'GET');
            $viewReq->setUserResolver(fn() => $customerA);
            $viewRes = $controller->show($viewReq, $playlistId1);
            $viewData = json_decode($viewRes->getContent(), true);

            $this->assert($viewRes->getStatusCode() === 200, 'Playlist 1 retrieved with HTTP 200');
            $items = collect($viewData['data']['tracks']);

            // 1. Free track status
            $item1 = $items->firstWhere('id', $track1->id);
            $this->assert($item1['access_status']['status'] === 'free', 'Track 1 has access status "free"');
            $this->assert($item1['access_status']['label_en'] === 'Free', 'Track 1 has English label "Free"');
            $this->assert($item1['access_status']['label_ar'] === 'مجاني', 'Track 1 has Arabic label "مجاني"');
            $this->assert($item1['access_status']['is_playable'] === true, 'Track 1 is_playable is true');
            $this->assert($item1['access_status']['can_stream_full'] === true, 'Track 1 can_stream_full is true');

            // 2. Purchased track status
            $item2 = $items->firstWhere('id', $track2->id);
            $this->assert($item2['access_status']['status'] === 'purchased', 'Track 2 has access status "purchased"');
            $this->assert($item2['access_status']['label_en'] === 'Purchased', 'Track 2 has English label "Purchased"');
            $this->assert($item2['access_status']['label_ar'] === 'تم الشراء', 'Track 2 has Arabic label "تم الشراء"');
            $this->assert($item2['access_status']['is_playable'] === true, 'Track 2 is_playable is true');

            // 3. Active rental status
            $item3 = $items->firstWhere('id', $track3->id);
            $this->assert($item3['access_status']['status'] === 'rented', 'Track 3 has access status "rented"');
            $this->assert($item3['access_status']['label_en'] === 'Rented', 'Track 3 has English label "Rented"');
            $this->assert($item3['access_status']['label_ar'] === 'مستأجر', 'Track 3 has Arabic label "مستأجر"');
            $this->assert($item3['access_status']['is_playable'] === true, 'Track 3 is_playable is true');
            $this->assert(!empty($item3['access_status']['expires_at']), 'Track 3 exposes rental expires_at');

            // 4. Expired rental status
            $item4 = $items->firstWhere('id', $track4->id);
            $this->assert($item4['access_status']['status'] === 'rental_expired', 'Track 4 has access status "rental_expired"');
            $this->assert($item4['access_status']['label_en'] === 'Rental Expired', 'Track 4 has English label "Rental Expired"');
            $this->assert($item4['access_status']['label_ar'] === 'انتهى الاستئجار', 'Track 4 has Arabic label "انتهى الاستئجار"');
            $this->assert($item4['access_status']['is_playable'] === false, 'Track 4 is_playable is FALSE for expired rental');

            // 5. Purchase required status
            $item5 = $items->firstWhere('id', $track5->id);
            $this->assert($item5['access_status']['status'] === 'purchase_required', 'Track 5 has access status "purchase_required"');
            $this->assert($item5['access_status']['label_en'] === 'Purchase Required', 'Track 5 has English label "Purchase Required"');
            $this->assert($item5['access_status']['label_ar'] === 'شراء مطلوب', 'Track 5 has Arabic label "شراء مطلوب"');
            $this->assert($item5['access_status']['is_playable'] === false, 'Track 5 is_playable is FALSE without purchase/rental');

            // 6. Suspended / Unavailable status
            $item6 = $items->firstWhere('id', $track6->id);
            $this->assert($item6['access_status']['status'] === 'unavailable', 'Track 6 has access status "unavailable"');
            $this->assert($item6['access_status']['label_en'] === 'Unavailable', 'Track 6 has English label "Unavailable"');
            $this->assert($item6['access_status']['label_ar'] === 'غير متاح', 'Track 6 has Arabic label "غير متاح"');
            $this->assert($item6['access_status']['is_playable'] === false, 'Track 6 is_playable is FALSE');

            // -------------------------------------------------------------
            // SECTION 6: Stream-Ticket Authorization Integrity
            // -------------------------------------------------------------
            echo "\n--- Section 6: Stream-Ticket Authorization Integrity ---\n";

            $storageService = new MusicStorageService();
            $streamService = new MusicStreamService($storageService);

            // Free track: receives full stream ticket
            $authT1 = $streamService->resolvePlaybackAccess($customerA, $track1);
            $this->assert($authT1['authorized'] === true && $authT1['mode'] === 'full', 'Stream service authorizes full stream for free track');

            // Purchased track: receives full stream ticket
            $authT2 = $streamService->resolvePlaybackAccess($customerA, $track2);
            $this->assert($authT2['authorized'] === true && $authT2['mode'] === 'full', 'Stream service authorizes full stream for purchased track');

            // Active rental: receives full stream ticket
            $authT3 = $streamService->resolvePlaybackAccess($customerA, $track3);
            $this->assert($authT3['authorized'] === true && $authT3['mode'] === 'full', 'Stream service authorizes full stream for active rental');

            // Expired rental: DENIED full stream; authorized for preview only
            $authT4 = $streamService->resolvePlaybackAccess($customerA, $track4);
            $this->assert($authT4['mode'] === 'preview', 'Stream service denies full stream for expired rental (preview only)');

            // Unpurchased paid track: DENIED full stream; authorized for preview only
            $authT5 = $streamService->resolvePlaybackAccess($customerA, $track5);
            $this->assert($authT5['mode'] === 'preview', 'Stream service denies full stream for unpurchased track (preview only)');

            // Suspended track: DENIED any playback
            $authT6 = $streamService->resolvePlaybackAccess($customerA, $track6);
            $this->assert($authT6['authorized'] === false && $authT6['mode'] === 'denied', 'Stream service strictly denies playback for suspended track');

            // -------------------------------------------------------------
            // SECTION 7: Reorder, Removal, and Deletion Lifecycle
            // -------------------------------------------------------------
            echo "\n--- Section 7: Reorder, Removal, and Deletion Lifecycle ---\n";

            // Reorder: reverse positions of Track 1 and Track 2
            $reorderReq = Request::create("/api/v1/music/playlists/{$playlistId1}/reorder", 'POST', [
                'track_ids' => [$track2->id, $track1->id, $track3->id, $track4->id, $track5->id, $track6->id, $track7->id],
            ]);
            $reorderReq->setUserResolver(fn() => $customerA);
            $reorderRes = $controller->reorder($reorderReq, $playlistId1);
            $this->assert($reorderRes->getStatusCode() === 200, 'Reorder returned HTTP 200');

            $pt1 = MusicPlaylistTrack::where('playlist_id', $playlistId1)->where('track_id', $track2->id)->first();
            $pt2 = MusicPlaylistTrack::where('playlist_id', $playlistId1)->where('track_id', $track1->id)->first();
            $this->assert($pt1->position === 0, 'Track 2 is now at position 0');
            $this->assert($pt2->position === 1, 'Track 1 is now at position 1');

            // Remove Track 3
            $remReq = Request::create("/api/v1/music/playlists/{$playlistId1}/tracks/{$track3->id}", 'DELETE');
            $remReq->setUserResolver(fn() => $customerA);
            $remRes = $controller->removeTrack($remReq, $playlistId1, $track3->id);
            $this->assert($remRes->getStatusCode() === 200, 'Track removal returned HTTP 200');
            $this->assert(!MusicPlaylistTrack::where('playlist_id', $playlistId1)->where('track_id', $track3->id)->exists(), 'Track 3 removed from playlist');
            $this->assert(MusicTrack::where('id', $track3->id)->exists(), 'Underlying MusicTrack 3 remains intact in database');

            // Rename playlist
            $renameReq = Request::create("/api/v1/music/playlists/{$playlistId1}", 'PUT', [
                'name' => 'Riyadh Midnight Electronic',
                'description' => 'Updated description for night drive vibes',
            ]);
            $renameReq->setUserResolver(fn() => $customerA);
            $renameRes = $controller->update($renameReq, $playlistId1);
            $renameData = json_decode($renameRes->getContent(), true);

            $this->assert($renameRes->getStatusCode() === 200, 'Rename returned HTTP 200');
            $this->assert($renameData['data']['name'] === 'Riyadh Midnight Electronic', 'Playlist renamed successfully');

            // Delete Playlist 2
            $delP2Req = Request::create("/api/v1/music/playlists/{$playlistId2}", 'DELETE');
            $delP2Req->setUserResolver(fn() => $customerA);
            $delP2Res = $controller->destroy($delP2Req, $playlistId2);

            $this->assert($delP2Res->getStatusCode() === 200, 'Playlist 2 deletion returned HTTP 200');
            $this->assert(!MusicPlaylist::where('id', $playlistId2)->exists(), 'Playlist 2 deleted from database');
            $this->assert(!MusicPlaylistTrack::where('playlist_id', $playlistId2)->exists(), 'Playlist 2 track associations cascade-deleted');
            $this->assert(MusicTrack::where('id', $track1->id)->exists(), 'Underlying MusicTrack 1 remains intact in database');

            echo "\n========================================================================\n";
            echo "SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
            echo "========================================================================\n";

        } catch (\Throwable $e) {
            echo "EXCEPTION: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            $this->failed++;
        } finally {
            DB::rollBack();
        }

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

$runner = new CustomerPlaylistTestRunner();
$runner->run();
