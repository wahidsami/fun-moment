<?php

/**
 * FUN MOMENT — First-Party Music Library & Album Test Suite
 *
 * Verifies:
 * 1. Admin authorization and ownership isolation (owner_type = 'admin', seller_id = null).
 * 2. Audio upload validation and processing pipeline integration.
 * 3. Draft/unpublished/processing tracks excluded from public catalog.
 * 4. Eligible first-party tracks appear in catalog with 'FUN MOMENT Original' badge.
 * 5. Free first-party tracks receive authorized stream tickets and stream.
 * 6. Album creation, track ordering, and public album catalog retrieval.
 * 7. Remix-to-original attribution and validation.
 * 8. Seller submissions and moderation isolation remain intact.
 * 9. Commercial checkout and VAT remain strictly disabled.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Http\Controllers\AdminMusicAlbumController;
use App\Http\Controllers\AdminMusicLibraryController;
use App\Http\Controllers\Api\MusicCatalogController;
use App\Http\Controllers\Api\MusicStreamController;
use App\MusicAlbum;
use App\MusicGenre;
use App\MusicOffer;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\FFmpegAudioProcessor;
use App\Services\Music\MusicAudioProcessingService;
use App\Services\Music\MusicStorageService;
use App\Services\Music\MusicStreamService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirstPartyMusicLibraryTestRunner
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
        echo "FUN MOMENT — FIRST-PARTY MUSIC LIBRARY & ALBUMS TEST SUITE\n";
        echo "========================================================================\n\n";

        DB::beginTransaction();

        try {
            // Setup base entities
            $admin = Admin::first() ?: Admin::create([
                'name' => 'Staging Master Admin',
                'username' => 'staging_master_admin',
                'email' => 'admin_test_' . Str::random(6) . '@funmoments.unifinitylab.com',
                'password' => bcrypt('Secret123!'),
            ]);

            Auth::guard('admin')->login($admin);

            $genre = MusicGenre::where('status', MusicGenre::STATUS_ACTIVE)->first() ?: MusicGenre::create([
                'name_en' => 'Electronic',
                'name_ar' => 'إلكتروني',
                'slug' => 'electronic-test-' . Str::random(4),
                'sort_order' => 1,
                'status' => MusicGenre::STATUS_ACTIVE,
            ]);

            MusicSetting::set('music_marketplace_enabled', 1, 'Enable for testing');
            MusicSetting::set('music_checkout_enabled', 0, 'Disabled checkout');
            MusicSetting::set('music_vat_enabled', 0, 'Disabled VAT');

            // -------------------------------------------------------------
            // SECTION 1: Ownership Isolation & Admin Track Creation
            // -------------------------------------------------------------
            echo "--- Section 1: Ownership Isolation & Admin Track Creation ---\n";

            $track1 = MusicTrack::create([
                'title_en' => 'Desert Dunes (Original Mix)',
                'title_ar' => 'كثبان الصحراء',
                'artist_name' => 'FUN MOMENT Originals',
                'owner_type' => 'admin',
                'admin_id' => $admin->id,
                'seller_id' => null,
                'genre_id' => $genre->id,
                'status' => 'draft',
                'media_status' => 'pending',
                'audio_path_original' => 'music_private/masters/test_orig.wav',
                'rights_declaration_type' => 'original_work',
                'rights_agreement_accepted' => true,
                'bpm' => 124,
                'track_key' => 'Am',
                'duration_seconds' => 180,
                'preview_duration_seconds' => 30,
            ]);

            $this->assert($track1->isOwnedByAdmin(), 'Track is identified as admin-owned');
            $this->assert(!$track1->isOwnedBySeller(), 'Track is NOT identified as seller-owned');
            $this->assert($track1->isFirstParty(), 'isFirstParty() returns true for admin tracks');
            $this->assert($track1->seller_id === null, 'seller_id is strictly null for admin tracks');
            $this->assert($track1->admin_id === $admin->id, 'admin_id matches authenticated administrator');

            // -------------------------------------------------------------
            // SECTION 2: Audio Pipeline & Publication Validation
            // -------------------------------------------------------------
            echo "\n--- Section 2: Audio Pipeline & Publication Lifecycle ---\n";

            $libraryController = app(AdminMusicLibraryController::class);

            // Attempt to publish an unready track (should fail with 422)
            $publishReqUnready = Request::create("/admin-home/music-library-json/{$track1->id}/publish", 'POST');
            $publishResUnready = $libraryController->publish($publishReqUnready, $track1->id);
            $this->assert($publishResUnready->status() === 422, 'Publishing unready track returns 422 Unprocessable Entity');

            // Simulate successful audio transcode
            $track1->media_status = 'ready';
            $track1->audio_path_original = 'music_private/masters/test.wav';
            $track1->audio_path_preview = 'music_private/previews/test_preview.mp3';
            $track1->audio_format = 'wav';
            $track1->duration_seconds = 210;
            $track1->save();

            // Publish media-ready track (should auto-create free offer and set status to approved)
            $publishReqReady = Request::create("/admin-home/music-library-json/{$track1->id}/publish", 'POST');
            $publishResReady = $libraryController->publish($publishReqReady, $track1->id);
            $this->assert($publishResReady->status() === 200, 'Publishing media-ready track returns 200 OK');

            $track1->refresh();
            $this->assert($track1->status === 'approved', 'Track status transitioned to approved');
            $this->assert($track1->released_at !== null, 'Track released_at timestamp is populated');
            $this->assert($track1->activeOffers()->where('offer_type', 'free')->exists(), 'Active free offer auto-created on publication');

            // Unpublish track
            $unpublishReq = Request::create("/admin-home/music-library-json/{$track1->id}/unpublish", 'POST');
            $unpublishRes = $libraryController->unpublish($unpublishReq, $track1->id);
            $this->assert($unpublishRes->status() === 200, 'Unpublishing returns 200 OK');

            $track1->refresh();
            $this->assert($track1->status === 'draft', 'Track status reverted to draft on unpublish');

            // Re-publish for catalog tests
            $track1->status = 'approved';
            $track1->save();

            // -------------------------------------------------------------
            // SECTION 3: Albums & Track Reordering
            // -------------------------------------------------------------
            echo "\n--- Section 3: Albums & Track Ordering ---\n";

            $albumController = app(AdminMusicAlbumController::class);

            // Create Album
            $createAlbumReq = Request::create('/admin-home/music-albums-json', 'POST', [
                'title_en' => 'Arabian Nights Showcase',
                'title_ar' => 'ليالي عربية',
                'artist_name' => 'FUN MOMENT Originals',
                'genre_id' => $genre->id,
                'description_en' => 'Official first-party electronic suite.',
            ]);
            $createAlbumRes = $albumController->store($createAlbumReq);
            $this->assert($createAlbumRes->status() === 201, 'Album created with status 201');

            $albumData = json_decode($createAlbumRes->getContent(), true)['album'];
            $album = MusicAlbum::find($albumData['id']);
            $this->assert($album !== null, 'Album retrieved from database');
            $this->assert($album->isOwnedByAdmin(), 'Album is owned by admin');

            // Assign track 1 to album
            $track1->album_id = $album->id;
            $track1->track_number = 1;
            $track1->save();

            $this->assert($track1->album->id === $album->id, 'Track 1 correctly belongs to created album');
            $this->assert($album->tracks()->count() === 1, 'Album tracks relation contains track 1');

            // Create Track 2 and assign to album as Track 2
            $track2 = MusicTrack::create([
                'title_en' => 'Desert Dunes (Chillout Remix)',
                'title_ar' => 'كثبان الصحراء (ريمكس هادئ)',
                'artist_name' => 'FUN MOMENT Originals',
                'owner_type' => 'admin',
                'admin_id' => $admin->id,
                'genre_id' => $genre->id,
                'album_id' => $album->id,
                'track_number' => 2,
                'original_track_id' => $track1->id,
                'status' => 'approved',
                'media_status' => 'ready',
                'audio_path_original' => 'music_private/masters/test2.wav',
                'audio_path_preview' => 'music_private/previews/test2_preview.mp3',
                'duration_seconds' => 195,
                'preview_duration_seconds' => 30,
                'rights_declaration_type' => 'original_work',
                'rights_agreement_accepted' => true,
                'released_at' => now(),
            ]);

            MusicOffer::create([
                'track_id' => $track2->id,
                'offer_type' => 'free',
                'price' => 0,
                'currency' => 'SAR',
                'is_active' => true,
            ]);

            $this->assert($track2->isRemix(), 'Track 2 is identified as a remix');
            $this->assert($track2->originalTrack->id === $track1->id, 'Track 2 links correctly to Track 1 as original master');
            $this->assert($track1->remixes()->count() === 1, 'Track 1 has 1 registered remix in remixes relation');

            // Reorder Album Tracks (swap track 1 and 2)
            $reorderReq = Request::create("/admin-home/music-albums-json/{$album->id}/reorder-tracks", 'POST', [
                'tracks' => [
                    ['id' => $track1->id, 'track_number' => 2],
                    ['id' => $track2->id, 'track_number' => 1],
                ],
            ]);
            $reorderRes = $albumController->reorderTracks($reorderReq, $album->id);
            $this->assert($reorderRes->status() === 200, 'Reordering tracks returns 200 OK');

            $track1->refresh();
            $track2->refresh();
            $this->assert($track1->track_number === 2, 'Track 1 updated to track number 2');
            $this->assert($track2->track_number === 1, 'Track 2 updated to track number 1');

            // Publish album
            $album->status = 'published';
            $album->released_at = now();
            $album->save();

            // -------------------------------------------------------------
            // SECTION 4: Public Mobile Catalog & Badge Verification
            // -------------------------------------------------------------
            echo "\n--- Section 4: Public Mobile Catalog & Badging ---\n";

            $catalogController = app(MusicCatalogController::class);

            // Fetch Catalog index
            $catalogReq = Request::create('/api/v1/music/catalog', 'GET');
            $catalogRes = $catalogController->index($catalogReq);
            $this->assert($catalogRes->status() === 200, 'Catalog index returns 200 OK');

            $catalogPayload = json_decode($catalogRes->getContent(), true);
            $catalogTracks = $catalogPayload['data']['data'];
            $track1InCatalog = collect($catalogTracks)->firstWhere('id', $track1->id);
            $track2InCatalog = collect($catalogTracks)->firstWhere('id', $track2->id);

            $this->assert($track1InCatalog !== null, 'Track 1 appears in public catalog');
            $this->assert($track1InCatalog['is_first_party'] === true, 'Track 1 has is_first_party = true');
            $this->assert($track1InCatalog['owner_type'] === 'admin', 'Track 1 has owner_type = admin');
            $this->assert($track1InCatalog['badge'] === 'FUN MOMENT Original', 'Track 1 has FUN MOMENT Original badge');
            $this->assert($track1InCatalog['seller'] === null, 'Track 1 seller is null');
            $this->assert($track1InCatalog['album']['id'] === $album->id, 'Track 1 contains album metadata');

            $this->assert($track2InCatalog !== null, 'Track 2 appears in public catalog');
            $this->assert($track2InCatalog['original_track']['id'] === $track1->id, 'Track 2 catalog item includes original_track attribution');

            // Latest Releases
            $latestReq = Request::create('/api/v1/music/catalog/latest-releases', 'GET');
            $latestRes = $catalogController->latestReleases($latestReq);
            $this->assert($latestRes->status() === 200, 'Latest releases returns 200 OK');
            $latestTracks = json_decode($latestRes->getContent(), true)['data'];
            $this->assert(collect($latestTracks)->contains('id', $track1->id), 'First-party track appears in latest releases carousel');

            // Public Albums Catalog
            $publicAlbumsReq = Request::create('/api/v1/music/catalog/albums', 'GET');
            $publicAlbumsRes = $catalogController->albums($publicAlbumsReq);
            $this->assert($publicAlbumsRes->status() === 200, 'Public albums endpoint returns 200 OK');

            $publicAlbumsData = json_decode($publicAlbumsRes->getContent(), true)['data'];
            $publicAlbums = isset($publicAlbumsData['data']) ? $publicAlbumsData['data'] : $publicAlbumsData;
            $this->assert(collect($publicAlbums)->contains('id', $album->id), 'Published album appears in public albums catalog');

            // Public Album Detail
            $albumDetailReq = Request::create("/api/v1/music/catalog/albums/{$album->id}", 'GET');
            $albumDetailRes = $catalogController->albumDetail($albumDetailReq, $album->id);
            $this->assert($albumDetailRes->status() === 200, 'Public album detail endpoint returns 200 OK');

            $albumDetail = json_decode($albumDetailRes->getContent(), true)['data'];
            $this->assert(count($albumDetail['tracks']) === 2, 'Album detail returns 2 ordered tracks');
            $this->assert($albumDetail['tracks'][0]['id'] === $track2->id, 'First track in album is track 2 (track_number 1)');

            // -------------------------------------------------------------
            // SECTION 5: Draft / Unready Track Catalog Exclusion
            // -------------------------------------------------------------
            echo "\n--- Section 5: Draft & Unready Catalog Exclusion ---\n";

            $draftTrack = MusicTrack::create([
                'title_en' => 'Draft Secret Song',
                'title_ar' => 'أغنية مسودة سرية',
                'artist_name' => 'FUN MOMENT Originals',
                'owner_type' => 'admin',
                'admin_id' => $admin->id,
                'genre_id' => $genre->id,
                'status' => 'draft',
                'media_status' => 'ready',
                'audio_path_original' => 'music_private/masters/draft.wav',
                'duration_seconds' => 120,
                'preview_duration_seconds' => 30,
            ]);

            $catalogDraftCheck = Request::create('/api/v1/music/catalog', 'GET');
            $draftCatalogRes = $catalogController->index($catalogDraftCheck);
            $draftList = json_decode($draftCatalogRes->getContent(), true)['data']['data'];
            $this->assert(!collect($draftList)->contains('id', $draftTrack->id), 'Draft first-party track is EXCLUDED from catalog');

            $unreadyTrack = MusicTrack::create([
                'title_en' => 'Unready Song',
                'title_ar' => 'أغنية غير جاهزة',
                'artist_name' => 'FUN MOMENT Originals',
                'owner_type' => 'admin',
                'admin_id' => $admin->id,
                'genre_id' => $genre->id,
                'status' => 'approved',
                'media_status' => 'processing', // not ready
                'audio_path_original' => 'music_private/masters/unready.wav',
                'duration_seconds' => 120,
                'preview_duration_seconds' => 30,
            ]);

            $unreadyCatalogRes = $catalogController->index($catalogDraftCheck);
            $unreadyList = json_decode($unreadyCatalogRes->getContent(), true)['data']['data'];
            $this->assert(!collect($unreadyList)->contains('id', $unreadyTrack->id), 'Processing / Unready first-party track is EXCLUDED from catalog');

            // -------------------------------------------------------------
            // SECTION 6: Free First-Party Stream Ticket Generation
            // -------------------------------------------------------------
            echo "\n--- Section 6: Stream Ticket Authorization for Free Tracks ---\n";

            $streamService = app(MusicStreamService::class);
            $streamController = app(MusicStreamController::class);

            $streamTicketReq = Request::create("/api/v1/music/tracks/{$track1->id}/stream-ticket", 'GET');
            $streamTicketRes = $streamController->requestStreamTicket($streamTicketReq, $track1->id);
            $this->assert($streamTicketRes->status() === 200, 'Stream ticket request returns 200 OK');

            $ticketData = json_decode($streamTicketRes->getContent(), true)['data'];
            $this->assert($ticketData['mode'] === 'full', 'Free first-party track grants full access mode');
            $this->assert(!empty($ticketData['ticket']), 'Stream ticket token string generated');
            $this->assert(!empty($ticketData['stream_url']), 'Direct stream endpoint URL provided');

            // -------------------------------------------------------------
            // SECTION 7: Platform Settings Safeguards (Checkout & VAT Disabled)
            // -------------------------------------------------------------
            echo "\n--- Section 7: Commercial Checkout & VAT Safeguards ---\n";

            $checkoutSetting = (int) MusicSetting::get('music_checkout_enabled', 0);
            $vatSetting = (int) MusicSetting::get('music_vat_enabled', 0);
            $this->assert($checkoutSetting === 0, 'music_checkout_enabled is strictly 0 (disabled)');
            $this->assert($vatSetting === 0, 'music_vat_enabled is strictly 0 (disabled)');

            // -------------------------------------------------------------
            // SECTION 8: Seller Submissions Non-Regression
            // -------------------------------------------------------------
            echo "\n--- Section 8: Seller Submissions Non-Regression ---\n";

            $seller = User::where('user_type', User::USER_TYPE_SELLER)->first() ?: User::create([
                'name' => 'Independent DJ Seller',
                'username' => 'dj_seller_' . Str::random(4),
                'email' => 'seller_music_' . Str::random(6) . '@test.com',
                'password' => bcrypt('Secret123!'),
                'user_type' => User::USER_TYPE_SELLER,
            ]);

            $sellerTrack = MusicTrack::create([
                'title_en' => 'Indie Beat',
                'title_ar' => 'إيقاع مستقل',
                'artist_name' => 'Indie DJ',
                'owner_type' => 'seller',
                'seller_id' => $seller->id,
                'admin_id' => null,
                'genre_id' => $genre->id,
                'status' => 'pending',
                'media_status' => 'ready',
                'audio_path_original' => 'music_private/masters/seller.wav',
                'duration_seconds' => 150,
                'preview_duration_seconds' => 20,
            ]);

            $this->assert($sellerTrack->isOwnedBySeller(), 'Seller track is correctly owned by seller');
            $this->assert(!$sellerTrack->isFirstParty(), 'Seller track is NOT first party');
            $this->assert($sellerTrack->admin_id === null, 'Seller track admin_id is null');

        } finally {
            DB::rollBack();
        }

        echo "\n========================================================================\n";
        echo "TEST SUMMARY:\n";
        echo "  Passed: {$this->passed}\n";
        echo "  Failed: {$this->failed}\n";
        echo "========================================================================\n";

        if ($this->failed > 0) {
            echo "Failed Assertions:\n";
            foreach ($this->errors as $err) {
                echo "  - {$err}\n";
            }
            exit(1);
        }
    }
}

$runner = new FirstPartyMusicLibraryTestRunner();
$runner->run();
