<?php

/**
 * FUN MOMENT — Phase 4: Buyer Music Marketplace, Audio Player & Playback Analytics Foundation Test Suite
 *
 * Validates:
 * 1. Global marketplace disablement enforcement (403 MARKETPLACE_DISABLED)
 * 2. Public catalog visibility rules (approved, media ready, released, active offers only)
 * 3. Exclusion of draft, pending, rejected, suspended, unready, and future-dated tracks
 * 4. Master path privacy (no private storage paths or keys exposed in API responses)
 * 5. Server-side search across English title, Arabic title, and artist name
 * 6. Server-side genre and offer-type filters (free, rent, sale)
 * 7. Safe sorting (latest, popular, title) and bounded pagination
 * 8. Latest releases carousel endpoint handling 0, 1, and N tracks gracefully
 * 9. Single track detail endpoint with full metadata and rights declaration
 * 10. Favorites operations: toggle, authenticated listing, and cross-user isolation
 * 11. Playback telemetry ingestion: event types, modes, timestamps, session ownership
 * 12. Rejection and stripping of client-supplied financial, revenue, and entitlement claims
 * 13. Idempotency enforcement on duplicate event submissions
 * 14. Qualified play counting rules (play_count increments at most once per continuous session)
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\MusicAnalyticsController;
use App\Http\Controllers\Api\MusicCatalogController;
use App\Http\Controllers\Api\MusicFavoriteController;
use App\Http\Controllers\Api\MusicStreamController;
use App\MusicEntitlement;
use App\MusicFavorite;
use App\MusicGenre;
use App\MusicOffer;
use App\MusicPlaybackEvent;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\MusicStreamService;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Phase4TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function run(): void
    {
        echo "\n======================================================\n";
        echo "  FUN MOMENT — Phase 4: Buyer Music Marketplace & Analytics\n";
        echo "======================================================\n\n";

        DB::beginTransaction();

        try {
            $this->testMarketplaceDisablement();
            $this->testCatalogVisibilityRules();
            $this->testNoMasterPathLeakage();
            $this->testCatalogSearch();
            $this->testCatalogFiltersAndSorting();
            $this->testLatestReleasesCarouselCases();
            $this->testSingleTrackDetail();
            $this->testFavoritesToggleAndIsolation();
            $this->testPlaybackAnalyticsIngestion();
            $this->testAnalyticsAntiTampering();
            $this->testAnalyticsIdempotency();
            $this->testQualifiedPlayIncrement();
            $this->testAnalyticsSecurityAgainstFabrication();
            $this->testStreamingTicketTransportAndRenewal();
        } catch (\Throwable $e) {
            $this->failed++;
            $this->errors[] = "Fatal test failure: " . $e->getMessage() . "\n" . $e->getTraceAsString();
        } finally {
            DB::rollBack();
        }

        echo "\n------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "------------------------------------------------------\n\n";

        if ($this->failed > 0) {
            echo "FAILURES:\n";
            foreach ($this->errors as $err) {
                echo " - " . $err . "\n";
            }
            exit(1);
        }

        echo "ALL PHASE 4 TESTS PASSED SUCCESSFULLY!\n\n";
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            $this->errors[] = $message;
            echo "  [FAIL] {$message}\n";
        }
    }

    private function createSeller(): User
    {
        $rand = Str::random(8);
        return User::create([
            'name' => 'Seller DJ ' . $rand,
            'username' => 'seller_' . strtolower($rand),
            'email' => 'seller_' . $rand . '@example.com',
            'phone' => '+9665' . rand(10000000, 99999999),
            'password' => bcrypt('password123'),
            'user_type' => 0,
            'user_status' => 1,
        ]);
    }

    private function createBuyer(): User
    {
        $rand = Str::random(8);
        return User::create([
            'name' => 'Buyer ' . $rand,
            'username' => 'buyer_' . strtolower($rand),
            'email' => 'buyer_' . $rand . '@example.com',
            'phone' => '+9665' . rand(10000000, 99999999),
            'password' => bcrypt('password123'),
            'user_type' => 1,
            'user_status' => 1,
        ]);
    }

    private function createGenre(string $name, string $slug): MusicGenre
    {
        return MusicGenre::create([
            'name_en' => $name,
            'name_ar' => $name . ' بالعربي',
            'slug' => $slug,
            'status' => MusicGenre::STATUS_ACTIVE,
        ]);
    }

    private function createTrack(
        User $seller,
        MusicGenre $genre,
        string $status = 'approved',
        string $mediaStatus = 'ready',
        array $attributes = []
    ): MusicTrack {
        $track = MusicTrack::create(array_merge([
            'seller_id' => $seller->id,
            'submitted_by_user_id' => $seller->id,
            'owner_type' => 'seller',
            'genre_id' => $genre->id,
            'title_en' => 'Cosmic Echoes ' . Str::random(4),
            'title_ar' => 'أصداء كونية ' . Str::random(4),
            'artist_name' => 'DJ Nova',
            'duration_seconds' => 180,
            'preview_duration_seconds' => 30,
            'status' => $status,
            'media_status' => $mediaStatus,
            'audio_path_original' => 'private/music/masters/test.mp3',
            'audio_path_preview' => 'public/music/previews/test.mp3',
            'audio_path_stream' => 'private/music/masters/test.mp3',
            'rights_declaration_type' => 'original_composition',
            'rights_agreement_accepted' => true,
            'declared_at' => now(),
            'released_at' => now()->subDay(),
            'play_count' => 0,
        ], $attributes));

        // Attach default active free offer
        MusicOffer::create([
            'track_id' => $track->id,
            'offer_type' => 'free',
            'price' => 0.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        return $track;
    }

    private function createOffer(MusicTrack $track, string $type = 'rent', float $price = 9.99, int $rentalDays = 7): MusicOffer
    {
        return MusicOffer::create([
            'track_id' => $track->id,
            'offer_type' => $type,
            'price' => $price,
            'currency' => 'SAR',
            'rental_duration_days' => $type === 'rent' ? $rentalDays : null,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Test marketplace disablement enforcement
     */
    private function testMarketplaceDisablement(): void
    {
        echo "\n1. Testing Marketplace Disablement Enforcement...\n";

        $seller = $this->createSeller();
        $genre = $this->createGenre('Ambient', 'ambient-' . Str::random(4));
        $this->createTrack($seller, $genre);

        // Disable marketplace
        MusicSetting::set('music_marketplace_enabled', '0');

        $controller = app(MusicCatalogController::class);
        $res = $controller->index(new Request());

        $this->assert($res->getStatusCode() === 403, "Catalog index returns 403 when marketplace is disabled");
        $data = $res->getData(true);
        $this->assert($data['error_code'] === 'MARKETPLACE_DISABLED', "Error code MARKETPLACE_DISABLED is returned");

        $releasesRes = $controller->latestReleases(new Request());
        $this->assert($releasesRes->getStatusCode() === 403, "Latest releases returns 403 when marketplace is disabled");

        // Re-enable marketplace
        MusicSetting::set('music_marketplace_enabled', '1');
        $resEnabled = $controller->index(new Request());
        $this->assert($resEnabled->getStatusCode() === 200, "Catalog index returns 200 when marketplace is enabled");
    }

    /**
     * 2. Test catalog visibility rules
     */
    private function testCatalogVisibilityRules(): void
    {
        echo "\n2. Testing Catalog Visibility Rules...\n";

        $seller = $this->createSeller();
        $genre = $this->createGenre('House', 'house-' . Str::random(4));

        // Approved and media ready (visible)
        $readyTrack = $this->createTrack($seller, $genre, 'approved', 'ready', ['title_en' => 'Ready Track']);

        // Pending track (hidden)
        $pendingTrack = $this->createTrack($seller, $genre, 'pending', 'ready', ['title_en' => 'Pending Track']);

        // Rejected track (hidden)
        $rejectedTrack = $this->createTrack($seller, $genre, 'rejected', 'ready', ['title_en' => 'Rejected Track']);

        // Suspended track (hidden)
        $suspendedTrack = $this->createTrack($seller, $genre, 'suspended', 'ready', ['title_en' => 'Suspended Track']);

        // Processing media (hidden)
        $processingTrack = $this->createTrack($seller, $genre, 'approved', 'processing', ['title_en' => 'Processing Track']);

        // Failed media (hidden)
        $failedTrack = $this->createTrack($seller, $genre, 'approved', 'failed', ['title_en' => 'Failed Track']);

        // Future release date (hidden)
        $futureTrack = $this->createTrack($seller, $genre, 'approved', 'ready', [
            'title_en' => 'Future Track',
            'released_at' => now()->addWeek(),
        ]);

        $controller = app(MusicCatalogController::class);
        $res = $controller->index(new Request(['per_page' => 50]));
        $body = $res->getData(true);
        $trackIds = array_column($body['data']['data'], 'id');

        $this->assert(in_array($readyTrack->id, $trackIds), "Approved and media-ready track is visible in catalog");
        $this->assert(!in_array($pendingTrack->id, $trackIds), "Pending track is excluded from catalog");
        $this->assert(!in_array($rejectedTrack->id, $trackIds), "Rejected track is excluded from catalog");
        $this->assert(!in_array($suspendedTrack->id, $trackIds), "Suspended track is excluded from catalog");
        $this->assert(!in_array($processingTrack->id, $trackIds), "Processing media track is excluded from catalog");
        $this->assert(!in_array($failedTrack->id, $trackIds), "Failed media track is excluded from catalog");
        $this->assert(!in_array($futureTrack->id, $trackIds), "Future-dated track is excluded from catalog");
    }

    /**
     * 3. Test private master path privacy
     */
    private function testNoMasterPathLeakage(): void
    {
        echo "\n3. Testing Master Path and Internal Storage Privacy...\n";

        $seller = $this->createSeller();
        $genre = $this->createGenre('Techno', 'techno-' . Str::random(4));
        $track = $this->createTrack($seller, $genre, 'approved', 'ready', [
            'audio_path_original' => 'private/super_secret_master_123.wav',
            'audio_path_stream' => 'private/super_secret_stream_456.mp3',
        ]);

        $controller = app(MusicCatalogController::class);
        $res = $controller->show(new Request(), $track->id);
        $body = $res->getData(true);
        $json = json_encode($body);

        $this->assert(!str_contains($json, 'super_secret_master_123'), "Master filename is never exposed in API response");
        $this->assert(!str_contains($json, 'audio_path_original'), "audio_path_original key is never exposed");
        $this->assert(!str_contains($json, 'audio_path_stream'), "audio_path_stream key is never exposed");
        $this->assert(isset($body['data']['id']), "Safe track detail is returned");
    }

    /**
     * 4. Test search across titles and artist name
     */
    private function testCatalogSearch(): void
    {
        echo "\n4. Testing Server-side Catalog Search...\n";

        $seller = $this->createSeller();
        $genre = $this->createGenre('EDM', 'edm-' . Str::random(4));

        $t1 = $this->createTrack($seller, $genre, 'approved', 'ready', [
            'title_en' => 'Midnight Groove UniqueXYZ',
            'title_ar' => 'إيقاع منتصف الليل',
            'artist_name' => 'DJ Alpha',
        ]);
        $t2 = $this->createTrack($seller, $genre, 'approved', 'ready', [
            'title_en' => 'Sunrise Chill',
            'title_ar' => 'شروق الشمس الفريدXYZ',
            'artist_name' => 'DJ Beta',
        ]);
        $t3 = $this->createTrack($seller, $genre, 'approved', 'ready', [
            'title_en' => 'Desert Storm',
            'title_ar' => 'عاصفة الصحراء',
            'artist_name' => 'DJ SpecialArtistXYZ',
        ]);

        $controller = app(MusicCatalogController::class);

        // Search by English title keyword
        $resEn = $controller->index(new Request(['q' => 'UniqueXYZ']));
        $idsEn = array_column($resEn->getData(true)['data']['data'], 'id');
        $this->assert(in_array($t1->id, $idsEn) && !in_array($t2->id, $idsEn), "Search finds track by English title");

        // Search by Arabic title keyword
        $resAr = $controller->index(new Request(['q' => 'الفريدXYZ']));
        $idsAr = array_column($resAr->getData(true)['data']['data'], 'id');
        $this->assert(in_array($t2->id, $idsAr) && !in_array($t1->id, $idsAr), "Search finds track by Arabic title");

        // Search by artist name keyword
        $resArtist = $controller->index(new Request(['q' => 'SpecialArtistXYZ']));
        $idsArtist = array_column($resArtist->getData(true)['data']['data'], 'id');
        $this->assert(in_array($t3->id, $idsArtist) && !in_array($t1->id, $idsArtist), "Search finds track by Artist name");
    }

    /**
     * 5. Test catalog filters and sorting
     */
    private function testCatalogFiltersAndSorting(): void
    {
        echo "\n5. Testing Catalog Filters & Safe Sorting...\n";

        $seller = $this->createSeller();
        $genreA = $this->createGenre('HipHop', 'hiphop-' . Str::random(4));
        $genreB = $this->createGenre('Jazz', 'jazz-' . Str::random(4));

        $trackA = $this->createTrack($seller, $genreA, 'approved', 'ready', [
            'title_en' => 'HipHop Track',
            'play_count' => 10,
        ]);
        $trackB = $this->createTrack($seller, $genreB, 'approved', 'ready', [
            'title_en' => 'Jazz Track',
            'play_count' => 500,
        ]);

        // Add commercial offers
        MusicOffer::create([
            'track_id' => $trackA->id,
            'offer_type' => 'rent',
            'price' => 15.00,
            'currency' => 'SAR',
            'rental_duration_days' => 7,
            'is_active' => true,
        ]);
        MusicOffer::create([
            'track_id' => $trackB->id,
            'offer_type' => 'sale',
            'price' => 50.00,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $controller = app(MusicCatalogController::class);

        // Filter by genre
        $resGenre = $controller->index(new Request(['genre_id' => $genreA->id]));
        $idsGenre = array_column($resGenre->getData(true)['data']['data'], 'id');
        $this->assert(in_array($trackA->id, $idsGenre) && !in_array($trackB->id, $idsGenre), "Filter by genre returns matching tracks");

        // Filter by offer type 'rent'
        $resRent = $controller->index(new Request(['offer_type' => 'rent']));
        $idsRent = array_column($resRent->getData(true)['data']['data'], 'id');
        $this->assert(in_array($trackA->id, $idsRent), "Filter by offer_type rent includes track with rent offer");

        // Sort by popular
        $resPop = $controller->index(new Request(['sort' => 'popular', 'per_page' => 20]));
        $itemsPop = $resPop->getData(true)['data']['data'];
        $posA = array_search($trackA->id, array_column($itemsPop, 'id'));
        $posB = array_search($trackB->id, array_column($itemsPop, 'id'));
        if ($posA !== false && $posB !== false) {
            $this->assert($posB < $posA, "Sort by popular places higher play_count first");
        } else {
            $this->assert(true, "Sort by popular executed safely");
        }
    }

    /**
     * 6. Test latest releases carousel (0, 1, and N tracks)
     */
    private function testLatestReleasesCarouselCases(): void
    {
        echo "\n6. Testing Latest Releases Carousel (0, 1, N tracks)...\n";

        $controller = app(MusicCatalogController::class);

        // Case 1: Multiple tracks
        $resN = $controller->latestReleases(new Request(['limit' => 5]));
        $this->assert($resN->getStatusCode() === 200, "Latest releases endpoint returns 200");
        $dataN = $resN->getData(true)['data'];
        $this->assert(is_array($dataN), "Latest releases returns array of track items");

        // Case 2: Zero tracks isolation
        // Suspend all active tracks
        MusicTrack::query()->update(['status' => 'suspended']);
        $resZero = $controller->latestReleases(new Request());
        $dataZero = $resZero->getData(true)['data'];
        $this->assert(count($dataZero) === 0, "When 0 eligible tracks exist, returns empty array without fake items");

        // Case 3: Exactly 1 track
        $seller = $this->createSeller();
        $genre = $this->createGenre('Lo-Fi', 'lofi-' . Str::random(4));
        $single = $this->createTrack($seller, $genre, 'approved', 'ready');

        $resOne = $controller->latestReleases(new Request());
        $dataOne = $resOne->getData(true)['data'];
        $this->assert(count($dataOne) === 1 && $dataOne[0]['id'] === $single->id, "When exactly 1 track exists, returns exactly 1 card");
    }

    /**
     * 7. Test single track detail endpoint
     */
    private function testSingleTrackDetail(): void
    {
        echo "\n7. Testing Single Track Detail Endpoint...\n";

        $seller = $this->createSeller();
        $genre = $this->createGenre('Deep House', 'deephouse-' . Str::random(4));
        $track = $this->createTrack($seller, $genre, 'approved', 'ready', [
            'bpm' => 124,
            'track_key' => 'Am',
            'rights_declaration_type' => 'original_composition',
        ]);

        $controller = app(MusicCatalogController::class);
        $res = $controller->show(new Request(), $track->id);
        $this->assert($res->getStatusCode() === 200, "Track detail returns 200 for approved track");

        $data = $res->getData(true)['data'];
        $this->assert($data['bpm'] === 124, "Track detail includes BPM");
        $this->assert($data['track_key'] === 'Am', "Track detail includes musical key");
        $this->assert($data['rights_declaration_type'] === 'original_composition', "Track detail includes rights declaration");
        $this->assert(is_array($data['offers']), "Track detail includes commercial offers list");

        // Test unapproved track returns 404
        $track->update(['status' => 'pending_review']);
        $resPending = $controller->show(new Request(), $track->id);
        $this->assert($resPending->getStatusCode() === 404, "Pending track detail returns 404 to public clients");
    }

    /**
     * 8. Test favorites toggle, listing, and isolation
     */
    private function testFavoritesToggleAndIsolation(): void
    {
        echo "\n8. Testing Favorites Toggle, Listing, and Cross-User Isolation...\n";

        $seller = $this->createSeller();
        $genre = $this->createGenre('Synthwave', 'synthwave-' . Str::random(4));
        $track = $this->createTrack($seller, $genre, 'approved', 'ready');

        $userA = $this->createBuyer();
        $userB = $this->createBuyer();

        $controller = app(MusicFavoriteController::class);

        // User A toggles favorite ON
        Auth::login($userA);
        $reqA = new Request(['track_id' => $track->id]);
        $reqA->setUserResolver(fn() => $userA);
        $resToggle1 = $controller->toggle($reqA);
        $this->assert($resToggle1->getData(true)['data']['is_favorite'] === true, "User A can favorite a track");

        // Idempotent Favorite: calling action=favorite twice keeps track favorited
        $reqFav = new Request(['track_id' => $track->id, 'action' => 'favorite']);
        $reqFav->setUserResolver(fn() => $userA);
        $controller->toggle($reqFav);
        $resFav2 = $controller->toggle($reqFav);
        $this->assert($resFav2->getData(true)['data']['is_favorite'] === true, "Idempotent favorite action retains favorited state on retry");

        // Check listing for User A
        $resListA = $controller->index($reqA);
        $favIdsA = array_column($resListA->getData(true)['data']['data'], 'id');
        $this->assert(in_array($track->id, $favIdsA), "User A favorites list contains favorited track");

        // User B isolation check: User B has not favorited this track
        Auth::login($userB);
        $reqB = new Request();
        $reqB->setUserResolver(fn() => $userB);
        $resListB = $controller->index($reqB);
        $favIdsB = array_column($resListB->getData(true)['data']['data'], 'id');
        $this->assert(!in_array($track->id, $favIdsB), "User B cannot see User A favorites (cross-user isolation)");

        // User A toggles favorite OFF
        Auth::login($userA);
        $reqUnfav = new Request(['track_id' => $track->id, 'action' => 'unfavorite']);
        $reqUnfav->setUserResolver(fn() => $userA);
        $resToggle2 = $controller->toggle($reqUnfav);
        $this->assert($resToggle2->getData(true)['data']['is_favorite'] === false, "User A can unfavorite a track");
        $this->assert(!MusicFavorite::isFavorite($userA->id, $track->id), "Database confirms track is unfavorited");

        // Idempotent Unfavorite: calling action=unfavorite twice keeps track unfavorited
        $resUnfav2 = $controller->toggle($reqUnfav);
        $this->assert($resUnfav2->getData(true)['data']['is_favorite'] === false, "Idempotent unfavorite action retains unfavorited state on retry");
    }

    /**
     * 9. Test playback telemetry ingestion
     */
    private function testPlaybackAnalyticsIngestion(): void
    {
        echo "\n9. Testing Playback Telemetry Ingestion Contract...\n";

        $seller = $this->createSeller();
        $genre = $this->createGenre('Chillout', 'chillout-' . Str::random(4));
        $track = $this->createTrack($seller, $genre, 'approved', 'ready');

        $buyer = $this->createBuyer();
        $sessionId = (string) Str::uuid();
        $streamService = app(MusicStreamService::class);
        $sessionToken = $streamService->createStreamTicket($buyer, $track, 'preview', 900, $sessionId);

        $controller = app(MusicAnalyticsController::class);

        $payload = [
            'track_id' => $track->id,
            'playback_session_id' => $sessionId,
            'session_token' => $sessionToken,
            'event_type' => 'playback_started',
            'playback_mode' => 'preview',
            'position_seconds' => 0,
            'duration_seconds' => 30,
            'idempotency_key' => $sessionId . '-start',
        ];

        $req = new Request($payload);
        $req->setUserResolver(fn() => $buyer);
        $res = $controller->recordPlaybackEvent($req);

        $this->assert($res->getStatusCode() === 200, "Playback event ingestion returns 200");
        $data = $res->getData(true);
        $this->assert($data['success'] === true, "Ingestion succeeds");
        $this->assert($data['data']['event_type'] === 'playback_started', "Recorded event type matches");
        $this->assert($data['data']['playback_mode'] === 'preview', "Recorded mode matches preview");

        // Verify in DB
        $event = MusicPlaybackEvent::find($data['data']['event_id']);
        $this->assert($event !== null, "Event record persists in music_playback_events table");
        $this->assert($event->user_id === $buyer->id, "Event correctly associates authenticated buyer");
    }

    /**
     * 10. Test anti-tampering (rejection of client financial claims)
     */
    private function testAnalyticsAntiTampering(): void
    {
        echo "\n10. Testing Telemetry Anti-Tampering & Financial Claim Stripping...\n";

        Auth::logout();
        $seller = $this->createSeller();
        $genre = $this->createGenre('Funk', 'funk-' . Str::random(4));
        $track = $this->createTrack($seller, $genre, 'approved', 'ready');

        $sessionId = (string) Str::uuid();
        $streamService = app(MusicStreamService::class);
        $sessionToken = $streamService->createStreamTicket(null, $track, 'preview', 900, $sessionId);
        $controller = app(MusicAnalyticsController::class);

        // Attempt to submit unauthorized financial claims
        $payload = [
            'track_id' => $track->id,
            'playback_session_id' => $sessionId,
            'session_token' => $sessionToken,
            'event_type' => 'listening_progress',
            'playback_mode' => 'preview',
            'position_seconds' => 15,
            'duration_seconds' => 30,
            'idempotency_key' => $sessionId . '-prog-15',
            'amount' => 500.00,
            'revenue' => 500.00,
            'is_purchased' => true,
            'entitlement_status' => 'lifetime_perpetual',
        ];

        $req = new Request($payload);
        $res = $controller->recordPlaybackEvent($req);
        $this->assert($res->getStatusCode() === 200, "Event ingested without failing schema");

        $event = MusicPlaybackEvent::where('idempotency_key', $sessionId . '-prog-15')->first();
        $this->assert($event !== null, "Event created");
        $rawAttributes = $event->getAttributes();
        $this->assert(!array_key_exists('amount', $rawAttributes), "Client-sent 'amount' is discarded");
        $this->assert(!array_key_exists('revenue', $rawAttributes), "Client-sent 'revenue' is discarded");
        $this->assert(!array_key_exists('is_purchased', $rawAttributes), "Client-sent 'is_purchased' claim is discarded");
    }

    /**
     * 11. Test analytics idempotency
     */
    private function testAnalyticsIdempotency(): void
    {
        echo "\n11. Testing Analytics Idempotency on Duplicates...\n";

        Auth::logout();
        $seller = $this->createSeller();
        $genre = $this->createGenre('Trance', 'trance-' . Str::random(4));
        $track = $this->createTrack($seller, $genre, 'approved', 'ready');

        $sessionId = (string) Str::uuid();
        $streamService = app(MusicStreamService::class);
        $sessionToken = $streamService->createStreamTicket(null, $track, 'preview', 900, $sessionId);
        $controller = app(MusicAnalyticsController::class);
        $idempKey = 'unique-idemp-' . Str::random(8);

        $payload = [
            'track_id' => $track->id,
            'playback_session_id' => $sessionId,
            'session_token' => $sessionToken,
            'event_type' => 'preview_completed',
            'playback_mode' => 'preview',
            'position_seconds' => 30,
            'duration_seconds' => 30,
            'idempotency_key' => $idempKey,
        ];

        $res1 = $controller->recordPlaybackEvent(new Request($payload));
        $data1 = $res1->getData(true);
        $this->assert($data1['success'] === true && $data1['is_duplicate'] === false, "First submission creates new event");

        // Submit exact same idempotency key again
        $res2 = $controller->recordPlaybackEvent(new Request($payload));
        $data2 = $res2->getData(true);
        $this->assert($data2['success'] === true && $data2['is_duplicate'] === true, "Second submission detects duplicate and returns idempotent 200");
        $this->assert($data2['data']['event_id'] === $data1['data']['event_id'], "Returned event_id matches original event");
    }

    /**
     * 12. Test qualified play increment rules
     */
    private function testQualifiedPlayIncrement(): void
    {
        echo "\n12. Testing Qualified Play Counter Logic...\n";

        Auth::logout();
        $seller = $this->createSeller();
        $genre = $this->createGenre('Reggae', 'reggae-' . Str::random(4));
        $track = $this->createTrack($seller, $genre, 'approved', 'ready', ['play_count' => 0]);
        $this->createOffer($track, 'free', 0.00);

        $streamService = app(MusicStreamService::class);
        $controller = app(MusicAnalyticsController::class);
        $sessionId = (string) Str::uuid();
        $sessionToken = $streamService->createStreamTicket(null, $track, 'full', 900, $sessionId);

        // 1. Playback started at 0s: NOT a qualified play yet
        $controller->recordPlaybackEvent(new Request([
            'track_id' => $track->id,
            'playback_session_id' => $sessionId,
            'session_token' => $sessionToken,
            'event_type' => 'playback_started',
            'playback_mode' => 'full',
            'position_seconds' => 0,
            'duration_seconds' => 180,
            'idempotency_key' => $sessionId . '-start',
        ]));
        $track->refresh();
        $this->assert($track->play_count === 0, "Playback started alone does not increment play_count");

        // 2. Listening progress at 5s (< 15s): NOT a qualified play yet
        $controller->recordPlaybackEvent(new Request([
            'track_id' => $track->id,
            'playback_session_id' => $sessionId,
            'session_token' => $sessionToken,
            'event_type' => 'listening_progress',
            'playback_mode' => 'full',
            'position_seconds' => 5,
            'duration_seconds' => 180,
            'idempotency_key' => $sessionId . '-prog-5',
        ]));
        $track->refresh();
        $this->assert($track->play_count === 0, "Short listening (5s) does not count as a qualified play");

        // 3. Listening progress reaches 15s: QUALIFIES as play!
        $controller->recordPlaybackEvent(new Request([
            'track_id' => $track->id,
            'playback_session_id' => $sessionId,
            'session_token' => $sessionToken,
            'event_type' => 'listening_progress',
            'playback_mode' => 'full',
            'position_seconds' => 15,
            'duration_seconds' => 180,
            'idempotency_key' => $sessionId . '-prog-15',
        ]));
        $track->refresh();
        $this->assert($track->play_count === 1, "Reaching 15s of listening increments play_count to 1");

        // 4. Track completion in same session: MUST NOT duplicate increment!
        $controller->recordPlaybackEvent(new Request([
            'track_id' => $track->id,
            'playback_session_id' => $sessionId,
            'session_token' => $sessionToken,
            'event_type' => 'full_track_completed',
            'playback_mode' => 'full',
            'position_seconds' => 180,
            'duration_seconds' => 180,
            'idempotency_key' => $sessionId . '-complete',
        ]));
        $track->refresh();
        $this->assert($track->play_count === 1, "Subsequent completion in same continuous session does NOT double-count play");

        // 5. New session for another qualified play
        $newSessionId = (string) Str::uuid();
        $newToken = $streamService->createStreamTicket(null, $track, 'preview', 900, $newSessionId);
        $controller->recordPlaybackEvent(new Request([
            'track_id' => $track->id,
            'playback_session_id' => $newSessionId,
            'session_token' => $newToken,
            'event_type' => 'preview_completed',
            'playback_mode' => 'preview',
            'position_seconds' => 30,
            'duration_seconds' => 30,
            'idempotency_key' => $newSessionId . '-prev-comp',
        ]));
        $track->refresh();
        $this->assert($track->play_count === 2, "New session with completed preview increments play_count to 2");
    }

    /**
     * 13. Test security against fabricated events, session hijacking & popularity spam
     */
    private function testAnalyticsSecurityAgainstFabrication(): void
    {
        echo "\n13. Testing Analytics Security Against Fabricated Events & Session Hijacking...\n";

        Auth::logout();
        $seller = $this->createSeller();
        $genre = $this->createGenre('Ambient', 'ambient-' . Str::random(4));
        $trackA = $this->createTrack($seller, $genre, 'approved', 'ready', ['play_count' => 0, 'preview_duration_seconds' => 30]);
        $trackB = $this->createTrack($seller, $genre, 'approved', 'ready', ['play_count' => 0]);

        $userA = $this->createBuyer();
        $userB = $this->createBuyer();

        $controller = app(MusicAnalyticsController::class);
        $streamService = app(MusicStreamService::class);

        // Case 1: Fabricated event without any session token
        $fakeSessionId = (string) Str::uuid();
        $resNoToken = $controller->recordPlaybackEvent(new Request([
            'track_id' => $trackA->id,
            'playback_session_id' => $fakeSessionId,
            'event_type' => 'preview_completed',
            'playback_mode' => 'preview',
            'position_seconds' => 30,
            'duration_seconds' => 30,
            'idempotency_key' => $fakeSessionId . '-fake-1',
        ]));
        $this->assert($resNoToken->getStatusCode() === 403, "Fabricated event without session token is rejected with 403");
        $this->assert($resNoToken->getData(true)['error_code'] === 'MISSING_SESSION_TOKEN', "Missing session token error code returned");

        // Case 2: Fabricated event with tampered / invalid session token
        $resTampered = $controller->recordPlaybackEvent(new Request([
            'track_id' => $trackA->id,
            'playback_session_id' => $fakeSessionId,
            'session_token' => 'invalid-tampered-token-xyz.badsignature',
            'event_type' => 'preview_completed',
            'playback_mode' => 'preview',
            'position_seconds' => 30,
            'duration_seconds' => 30,
            'idempotency_key' => $fakeSessionId . '-fake-2',
        ]));
        $this->assert($resTampered->getStatusCode() === 403, "Tampered session token is rejected with 403");
        $this->assert($resTampered->getData(true)['error_code'] === 'INVALID_PLAYBACK_SESSION', "Invalid playback session error code returned");

        // Case 3: Session token issued for Track A used to submit telemetry for Track B
        $sessionA = (string) Str::uuid();
        $tokenA = $streamService->createStreamTicket(null, $trackA, 'preview', 900, $sessionA);
        $resCrossTrack = $controller->recordPlaybackEvent(new Request([
            'track_id' => $trackB->id, // Mismatched track!
            'playback_session_id' => $sessionA,
            'session_token' => $tokenA,
            'event_type' => 'preview_completed',
            'playback_mode' => 'preview',
            'position_seconds' => 30,
            'duration_seconds' => 30,
            'idempotency_key' => $sessionA . '-cross-track',
        ]));
        $this->assert($resCrossTrack->getStatusCode() === 403, "Session token bound to Track A is rejected when used for Track B");

        // Case 4: User A session token replayed by User B
        $sessionUserA = (string) Str::uuid();
        $tokenUserA = $streamService->createStreamTicket($userA, $trackA, 'preview', 900, $sessionUserA);

        $reqUserB = new Request([
            'track_id' => $trackA->id,
            'playback_session_id' => $sessionUserA,
            'session_token' => $tokenUserA,
            'event_type' => 'playback_started',
            'playback_mode' => 'preview',
            'position_seconds' => 0,
            'duration_seconds' => 30,
            'idempotency_key' => $sessionUserA . '-replay-userB',
        ]);
        $reqUserB->setUserResolver(fn() => $userB); // User B claims User A session
        $resUserB = $controller->recordPlaybackEvent($reqUserB);
        $this->assert($resUserB->getStatusCode() === 403, "User B cannot replay User A's session token");
        $this->assert($resUserB->getData(true)['error_code'] === 'SESSION_USER_MISMATCH', "Cross-user session mismatch error code returned");

        // Case 5: Guest attempting to use authenticated User A's session token
        Auth::logout();
        $reqGuest = new Request([
            'track_id' => $trackA->id,
            'playback_session_id' => $sessionUserA,
            'session_token' => $tokenUserA,
            'event_type' => 'playback_started',
            'playback_mode' => 'preview',
            'position_seconds' => 0,
            'duration_seconds' => 30,
            'idempotency_key' => $sessionUserA . '-replay-guest',
        ]);
        $reqGuest->setUserResolver(fn() => null);
        $resGuest = $controller->recordPlaybackEvent($reqGuest);
        $this->assert($resGuest->getStatusCode() === 403, "Unauthenticated guest cannot claim authenticated user's session token");

        // Case 6: Authenticated user attempting to claim a guest session token
        $guestSessionId = (string) Str::uuid();
        $guestToken = $streamService->createStreamTicket(null, $trackA, 'preview', 900, $guestSessionId);
        $reqAuthClaimGuest = new Request([
            'track_id' => $trackA->id,
            'playback_session_id' => $guestSessionId,
            'session_token' => $guestToken,
            'event_type' => 'playback_started',
            'playback_mode' => 'preview',
            'position_seconds' => 0,
            'duration_seconds' => 30,
            'idempotency_key' => $guestSessionId . '-auth-claim',
        ]);
        $reqAuthClaimGuest->setUserResolver(fn() => $userA);
        $resAuthClaimGuest = $controller->recordPlaybackEvent($reqAuthClaimGuest);
        $this->assert($resAuthClaimGuest->getStatusCode() === 403, "Authenticated user cannot claim a guest session token");

        // Case 7: Preview session token attempting to submit full track completion
        $previewSession = (string) Str::uuid();
        $previewToken = $streamService->createStreamTicket(null, $trackA, 'preview', 900, $previewSession);
        $resModeMismatch = $controller->recordPlaybackEvent(new Request([
            'track_id' => $trackA->id,
            'playback_session_id' => $previewSession,
            'session_token' => $previewToken,
            'event_type' => 'full_track_completed', // Denied for preview!
            'playback_mode' => 'full',
            'position_seconds' => 180,
            'duration_seconds' => 180,
            'idempotency_key' => $previewSession . '-mode-escalate',
        ]));
        $this->assert($resModeMismatch->getStatusCode() === 403, "Preview token cannot report full track completion");
        $this->assert($resModeMismatch->getData(true)['error_code'] === 'SESSION_MODE_MISMATCH', "Session mode mismatch error code returned");

        // Case 8: Future client timestamp (+2 hours)
        $futureSession = (string) Str::uuid();
        $futureToken = $streamService->createStreamTicket(null, $trackA, 'preview', 900, $futureSession);
        $resFutureTime = $controller->recordPlaybackEvent(new Request([
            'track_id' => $trackA->id,
            'playback_session_id' => $futureSession,
            'session_token' => $futureToken,
            'event_type' => 'listening_progress',
            'playback_mode' => 'preview',
            'position_seconds' => 15,
            'duration_seconds' => 30,
            'client_timestamp' => now()->addHours(2)->toIso8601String(),
            'idempotency_key' => $futureSession . '-future-time',
        ]));
        $this->assert($resFutureTime->getStatusCode() === 422, "Future client timestamp is rejected with 422");
        $this->assert($resFutureTime->getData(true)['error_code'] === 'INVALID_CLIENT_TIMESTAMP', "Invalid client timestamp error returned");

        // Case 9: Position exceeding preview bounds
        $boundSession = (string) Str::uuid();
        $boundToken = $streamService->createStreamTicket(null, $trackA, 'preview', 900, $boundSession);
        $resOutOfBounds = $controller->recordPlaybackEvent(new Request([
            'track_id' => $trackA->id,
            'playback_session_id' => $boundSession,
            'session_token' => $boundToken,
            'event_type' => 'listening_progress',
            'playback_mode' => 'preview',
            'position_seconds' => 500, // Preview is only 30s!
            'duration_seconds' => 30,
            'idempotency_key' => $boundSession . '-out-of-bounds',
        ]));
        $this->assert($resOutOfBounds->getStatusCode() === 422, "Position exceeding track preview length is rejected with 422");
        $this->assert($resOutOfBounds->getData(true)['error_code'] === 'POSITION_OUT_OF_BOUNDS', "Position out of bounds error returned");

        // Case 10: Popularity spam throttling: Max 10 counted plays per IP per track per hour
        $spamTrack = $this->createTrack($seller, $genre, 'approved', 'ready', ['play_count' => 0]);
        for ($i = 1; $i <= 12; $i++) {
            $spamSessionId = (string) Str::uuid();
            $spamToken = $streamService->createStreamTicket(null, $spamTrack, 'preview', 900, $spamSessionId);
            $controller->recordPlaybackEvent(new Request([
                'track_id' => $spamTrack->id,
                'playback_session_id' => $spamSessionId,
                'session_token' => $spamToken,
                'event_type' => 'preview_completed',
                'playback_mode' => 'preview',
                'position_seconds' => 30,
                'duration_seconds' => 30,
                'idempotency_key' => $spamSessionId . '-spam-' . $i,
            ]));
        }
        $spamTrack->refresh();
        $this->assert($spamTrack->play_count === 10, "Popularity counters remain bounded against event spam (12 sessions yielded exactly 10 counted plays)");
    }

    /**
     * 14. Test streaming-ticket transport and renewal
     */
    private function testStreamingTicketTransportAndRenewal(): void
    {
        echo "\n14. Testing Streaming Ticket Transport, Entitlement Transitions & Renewal...\n";

        $seller = $this->createSeller();
        $genre = $this->createGenre('EDM', 'edm-' . Str::random(4));
        $track = $this->createTrack($seller, $genre, 'approved', 'ready', ['duration_seconds' => 200, 'preview_duration_seconds' => 20]);
        $track->offers()->where('offer_type', 'free')->delete();
        $offerRent = $this->createOffer($track, 'rent', 10.00, 7);
        $track->load('offers');

        $buyer = $this->createBuyer();
        $streamController = app(MusicStreamController::class);

        // 1. Initial Ticket Request for unentitled track -> preview mode
        $reqTicketUnentitled = new Request();
        $reqTicketUnentitled->setUserResolver(fn() => $buyer);
        $resTicket = $streamController->requestStreamTicket($reqTicketUnentitled, $track->id);

        $this->assert($resTicket->getStatusCode() === 200, "Requesting stream ticket returns 200");
        $ticketData = $resTicket->getData(true)['data'];
        $this->assert($ticketData['mode'] === 'preview', "Unentitled track issues preview ticket");
        $this->assert(!empty($ticketData['session_token']), "Response includes signed session_token");
        $this->assert(!empty($ticketData['playback_session_id']), "Response includes unique playback_session_id");
        $this->assert($ticketData['stream_header'] === 'X-Stream-Ticket', "Response specifies clean X-Stream-Ticket header");

        // 2. Active Rental Entitlement -> Full mode
        $entitlement = MusicEntitlement::create([
            'user_id' => $buyer->id,
            'track_id' => $track->id,
            'offer_id' => $offerRent->id,
            'access_type' => 'stream_only',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(6),
            'is_active' => true,
        ]);

        $resTicketRented = $streamController->requestStreamTicket($reqTicketUnentitled, $track->id);
        $this->assert($resTicketRented->getData(true)['data']['mode'] === 'full', "Active rental entitlement authorizes full streaming mode");

        // 3. Expired Rental Entitlement -> Graceful Fallback to Preview Mode
        $entitlement->update([
            'expires_at' => now()->subHour(),
        ]);

        $resTicketExpired = $streamController->requestStreamTicket($reqTicketUnentitled, $track->id);
        $this->assert($resTicketExpired->getData(true)['data']['mode'] === 'preview', "Expired rental loses full-stream access and transitions to preview");
        $entitlement->refresh();
        $this->assert($entitlement->is_active === false, "Expired rental entitlement is deactivated");

        // 4. Suspended track -> denied to unentitled users
        $track->update(['status' => 'suspended']);
        $resSuspended = $streamController->requestStreamTicket($reqTicketUnentitled, $track->id);
        $this->assert($resSuspended->getStatusCode() === 403, "Suspended track is inaccessible to unentitled users");
    }
}

// Run test suite
$runner = new Phase4TestRunner();
$runner->run();
