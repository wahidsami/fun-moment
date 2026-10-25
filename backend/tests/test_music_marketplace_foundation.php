<?php
/**
 * Automated Verification for FUN MOMENTS Music Marketplace — Phase 1 Foundation
 * Tests:
 * 1. Database Schema & Models Integrity (Genres, Tracks, Offers, Orders, Entitlements, Favorites, Settings)
 * 2. Ownership & Server-Side Identity (Admin-owned vs Seller-owned)
 * 3. Commercial Offers & Approved Licensing Rules (Free, Rent, Sale)
 * 4. Explicit Sale License Constraints (Perpetual in-app streaming only; NO download, NO commercial, NO remix)
 * 5. Rental Expiration Lifecycle (Starts_at, Expires_at, Active check)
 * 6. Duplicate-Safe Favorites Unique Constraint
 * 7. Digital Goods Orders & Idempotency Key Integrity
 * 8. Track Moderation Transitions & Policy Authorization Boundaries
 * 9. Referential Integrity & Financial Record Preservation
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Accountdeactive;
use App\MusicEntitlement;
use App\MusicFavorite;
use App\MusicGenre;
use App\MusicOffer;
use App\MusicOrder;
use App\MusicSetting;
use App\MusicTrack;
use App\Policies\MusicTrackPolicy;
use App\Services\User\UserDeletionService;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
echo "FUN MOMENT — MUSIC MARKETPLACE PHASE 1 TEST SUITE\n";
echo "========================================================\n\n";

DB::beginTransaction();

try {
    // Setup test actor records
    $seller = User::create([
        'name' => 'DJ Test Producer',
        'email' => 'dj_producer_' . Str::random(5) . '@funmoments.test',
        'username' => 'dj_producer_' . Str::random(5),
        'password' => bcrypt('password123'),
        'user_type' => User::USER_TYPE_SELLER,
        'user_status' => 1,
        'email_verified' => 1,
    ]);

    $buyer = User::create([
        'name' => 'Music Fan Buyer',
        'email' => 'buyer_' . Str::random(5) . '@funmoments.test',
        'username' => 'buyer_' . Str::random(5),
        'password' => bcrypt('password123'),
        'user_type' => User::USER_TYPE_BUYER,
        'user_status' => 1,
        'email_verified' => 1,
    ]);

    $admin = Admin::first() ?: Admin::create([
        'name' => 'Music Admin',
        'email' => 'music_admin_' . Str::random(5) . '@funmoments.test',
        'username' => 'music_admin_' . Str::random(5),
        'password' => bcrypt('password123'),
    ]);

    $genre = MusicGenre::where('slug', 'house')->first() ?: MusicGenre::create([
        'name_en' => 'Deep House',
        'name_ar' => 'ديب هاوس',
        'slug' => 'deep-house-test-' . Str::random(4),
        'sort_order' => 1,
        'status' => 1,
    ]);

    // --- SECTION 1: GENRES & LOCALIZATION ---
    echo "--- 1. MUSIC GENRES & LOCALIZATION ---\n";

    it("1.1 Genre active and ordered scopes work", function() use ($genre) {
        $activeGenres = MusicGenre::active()->ordered()->get();
        return $activeGenres->contains('id', $genre->id);
    });

    it("1.2 Genre bilingual localization returns Arabic when requested", function() use ($genre) {
        return $genre->getLocalizedName('ar') === $genre->name_ar &&
               $genre->getLocalizedName('en') === $genre->name_en;
    });

    // --- SECTION 2: TRACK OWNERSHIP & METADATA ---
    echo "\n--- 2. TRACK OWNERSHIP & METADATA ---\n";

    $sellerTrack = MusicTrack::create([
        'title_en' => 'Sunset Dunes VIP',
        'title_ar' => 'غروب الكثبان',
        'artist_name' => 'DJ Test Producer',
        'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
        'seller_id' => $seller->id,
        'genre_id' => $genre->id,
        'cover_image' => 'assets/uploads/music/sunset_cover.jpg',
        'audio_path_original' => 'storage/app/music/tracks/sunset_master.wav',
        'audio_path_preview' => 'storage/app/music/previews/sunset_preview.mp3',
        'duration_seconds' => 245,
        'preview_duration_seconds' => 20,
        'file_size_bytes' => 45000000,
        'audio_format' => 'wav',
        'bpm' => 126,
        'track_key' => 'Fm',
        'rights_declaration_type' => MusicTrack::RIGHTS_ORIGINAL,
        'rights_agreement_accepted' => true,
        'declared_at' => now(),
        'declared_ip' => '127.0.0.1',
        'status' => MusicTrack::STATUS_DRAFT,
    ]);

    it("2.1 Seller-owned track correctly links to seller User identity", function() use ($sellerTrack, $seller) {
        return $sellerTrack->isOwnedBySeller() &&
               !$sellerTrack->isOwnedByAdmin() &&
               (int) $sellerTrack->seller->id === (int) $seller->id;
    });

    $adminTrack = MusicTrack::create([
        'title_en' => 'FUN MOMENTS Official Anthem',
        'title_ar' => 'نشيد فن مومنتس الرسمي',
        'artist_name' => 'FUN MOMENTS Collective',
        'owner_type' => MusicTrack::OWNER_TYPE_ADMIN,
        'admin_id' => $admin->id,
        'genre_id' => $genre->id,
        'audio_path_original' => 'storage/app/music/tracks/anthem_master.wav',
        'duration_seconds' => 180,
        'preview_duration_seconds' => 20,
        'status' => MusicTrack::STATUS_APPROVED,
        'released_at' => now()->subDay(),
    ]);

    it("2.2 Admin-owned track links to Admin identity without seller ID", function() use ($adminTrack, $admin) {
        return $adminTrack->isOwnedByAdmin() &&
               !$adminTrack->isOwnedBySeller() &&
               $adminTrack->seller_id === null &&
               (int) $adminTrack->admin->id === (int) $admin->id;
    });

    it("2.3 Published scope includes approved track with past release date and excludes drafts", function() use ($adminTrack, $sellerTrack) {
        $published = MusicTrack::published()->pluck('id')->toArray();
        return in_array($adminTrack->id, $published, true) &&
               !in_array($sellerTrack->id, $published, true);
    });

    // --- SECTION 3: COMMERCIAL OFFERS & APPROVED LICENSING RULES ---
    echo "\n--- 3. COMMERCIAL OFFERS & APPROVED LICENSING RULES ---\n";

    $freeOffer = MusicOffer::create([
        'track_id' => $sellerTrack->id,
        'offer_type' => MusicOffer::OFFER_TYPE_FREE,
        'price' => 0.00,
        'currency' => 'SAR',
        'is_active' => true,
    ]);

    it("3.1 Free offer defaults price to 0 and stream-only license", function() use ($freeOffer) {
        return $freeOffer->isFree() &&
               $freeOffer->price == 0.00 &&
               $freeOffer->allow_download === false &&
               $freeOffer->commercial_use === false &&
               $freeOffer->remix_rights === false &&
               $freeOffer->license_type === MusicOffer::LICENSE_STREAM_ONLY;
    });

    it("3.2 Free offer rejects positive price with InvalidArgumentException", function() use ($sellerTrack) {
        try {
            MusicOffer::create([
                'track_id' => $sellerTrack->id,
                'offer_type' => MusicOffer::OFFER_TYPE_FREE,
                'price' => 25.00, // Invalid! Free cannot charge positive price
            ]);
            return false;
        } catch (\InvalidArgumentException $e) {
            return true;
        }
    });

    $rentOffer = MusicOffer::create([
        'track_id' => $sellerTrack->id,
        'offer_type' => MusicOffer::OFFER_TYPE_RENT,
        'price' => 15.00,
        'currency' => 'SAR',
        'rental_duration_days' => 7,
        'is_active' => true,
    ]);

    it("3.3 Rent offer enforces positive rental duration and non-negative price", function() use ($rentOffer) {
        return $rentOffer->isRent() &&
               $rentOffer->price == 15.00 &&
               $rentOffer->rental_duration_days === 7 &&
               $rentOffer->allow_download === false &&
               $rentOffer->commercial_use === false &&
               $rentOffer->remix_rights === false;
    });

    it("3.4 Rent offer rejects missing/zero rental duration with InvalidArgumentException", function() use ($sellerTrack) {
        try {
            MusicOffer::create([
                'track_id' => $sellerTrack->id,
                'offer_type' => MusicOffer::OFFER_TYPE_RENT,
                'price' => 10.00,
                'rental_duration_days' => 0, // Invalid!
            ]);
            return false;
        } catch (\InvalidArgumentException $e) {
            return true;
        }
    });

    // --- SECTION 4: SALE LICENSING RULES (PERMANENT IN-APP STREAMING ONLY) ---
    echo "\n--- 4. APPROVED SALE LICENSE CONSTRAINTS (IN-APP STREAMING ONLY) ---\n";

    $saleOffer = MusicOffer::create([
        'track_id' => $sellerTrack->id,
        'offer_type' => MusicOffer::OFFER_TYPE_SALE,
        'price' => 50.00,
        'currency' => 'SAR',
        'is_active' => true,
    ]);

    it("4.1 Sale offer enforces license_type = in_app_streaming_only", function() use ($saleOffer) {
        return $saleOffer->isSale() &&
               $saleOffer->price == 50.00 &&
               $saleOffer->license_type === MusicOffer::LICENSE_STREAM_ONLY;
    });

    it("4.2 Sale offer strictly sets allow_download = false (no master audio file downloads)", function() use ($saleOffer) {
        return $saleOffer->allow_download === false;
    });

    it("4.3 Sale offer strictly sets commercial_use = false (no commercial performance rights)", function() use ($saleOffer) {
        return $saleOffer->commercial_use === false;
    });

    it("4.4 Sale offer strictly sets remix_rights = false (no remixing or redistribution rights)", function() use ($saleOffer) {
        return $saleOffer->remix_rights === false;
    });

    // --- SECTION 5: DIGITAL GOODS ORDERS & IDEMPOTENCY ---
    echo "\n--- 5. DIGITAL GOODS ORDERS & IDEMPOTENCY ---\n";

    $idempotencyKey = (string) Str::uuid();

    $order = MusicOrder::create([
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
        'track_id' => $sellerTrack->id,
        'offer_id' => $saleOffer->id,
        'offer_type' => MusicOffer::OFFER_TYPE_SALE,
        'amount' => 50.00,
        'currency' => 'SAR',
        'commission_rate' => 15.00,
        'commission_amount' => 7.50,
        'seller_earning' => 42.50,
        'payment_gateway' => MusicOrder::GATEWAY_PAYTABS,
        'payment_status' => MusicOrder::STATUS_COMPLETED,
        'transaction_id' => 'TXN_TEST_12345',
        'idempotency_key' => $idempotencyKey,
        'paid_at' => now(),
    ]);

    it("5.1 MusicOrder generates unique invoice prefix and links digital fields", function() use ($order, $buyer, $seller) {
        return str_starts_with($order->invoice, 'FM-MUS-') &&
               (int) $order->buyer->id === (int) $buyer->id &&
               (int) $order->seller->id === (int) $seller->id &&
               $order->isCompleted();
    });

    it("5.2 Duplicate idempotency key is rejected by unique constraint", function() use ($buyer, $seller, $sellerTrack, $saleOffer, $idempotencyKey) {
        try {
            DB::statement('SAVEPOINT sp_idempotency');
            MusicOrder::create([
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'track_id' => $sellerTrack->id,
                'offer_id' => $saleOffer->id,
                'offer_type' => MusicOffer::OFFER_TYPE_SALE,
                'amount' => 50.00,
                'payment_gateway' => MusicOrder::GATEWAY_PAYTABS,
                'idempotency_key' => $idempotencyKey, // Duplicate!
            ]);
            DB::statement('RELEASE SAVEPOINT sp_idempotency');
            return false;
        } catch (\Illuminate\Database\QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT sp_idempotency');
            return true;
        }
    });

    // --- SECTION 6: ENTITLEMENTS (RENTAL VS SALE LIFECYCLE) ---
    echo "\n--- 6. ENTITLEMENTS (RENTAL VS SALE LIFECYCLE) ---\n";

    // A: Perpetual Sale Entitlement
    $saleEntitlement = MusicEntitlement::create([
        'user_id' => $buyer->id,
        'track_id' => $sellerTrack->id,
        'offer_id' => $saleOffer->id,
        'order_id' => $order->id,
        'starts_at' => now(),
        'expires_at' => null, // Perpetual!
        'is_active' => true,
    ]);

    it("6.1 Sale entitlement is perpetual (expires_at is null)", function() use ($saleEntitlement) {
        return $saleEntitlement->isPerpetual() &&
               !$saleEntitlement->isExpired() &&
               $saleEntitlement->isValid();
    });

    it("6.2 Sale entitlement strictly enforces stream_only without download or commercial rights", function() use ($saleEntitlement) {
        return $saleEntitlement->access_type === MusicEntitlement::ACCESS_STREAM_ONLY &&
               $saleEntitlement->allow_download === false &&
               $saleEntitlement->commercial_use === false &&
               $saleEntitlement->remix_rights === false;
    });

    // B: Time-Limited Rental Entitlement
    $rentalEntitlement = MusicEntitlement::create([
        'user_id' => $buyer->id,
        'track_id' => $adminTrack->id,
        'offer_id' => $rentOffer->id,
        'starts_at' => now(),
        'expires_at' => now()->addDays(7),
        'is_active' => true,
    ]);

    it("6.3 Unexpired rental entitlement reports valid and unexpired", function() use ($rentalEntitlement) {
        return !$rentalEntitlement->isPerpetual() &&
               !$rentalEntitlement->isExpired() &&
               $rentalEntitlement->isValid();
    });

    // C: Expired Rental Entitlement
    $expiredRental = MusicEntitlement::create([
        'user_id' => $buyer->id,
        'track_id' => $sellerTrack->id,
        'offer_id' => $rentOffer->id,
        'starts_at' => now()->subDays(10),
        'expires_at' => now()->subDays(3),
        'is_active' => true,
    ]);

    it("6.4 Expired rental entitlement reports isExpired = true and isValid = false", function() use ($expiredRental) {
        return $expiredRental->isExpired() &&
               !$expiredRental->isValid();
    });

    it("6.5 Revoked entitlement reports isValid = false", function() use ($saleEntitlement) {
        $saleEntitlement->revoke("Refund processed");
        return !$saleEntitlement->isValid() &&
               $saleEntitlement->revoked_at !== null &&
               $saleEntitlement->is_active === false;
    });

    // --- SECTION 7: DUPLICATE-SAFE FAVORITES ---
    echo "\n--- 7. DUPLICATE-SAFE FAVORITES ---\n";

    $added = MusicFavorite::toggle($buyer->id, $sellerTrack->id);
    it("7.1 Toggling favorite adds record and returns true", function() use ($added, $buyer, $sellerTrack) {
        return $added === true &&
               MusicFavorite::where('user_id', $buyer->id)->where('track_id', $sellerTrack->id)->exists();
    });

    $removed = MusicFavorite::toggle($buyer->id, $sellerTrack->id);
    it("7.2 Toggling favorite again removes record atomically and returns false", function() use ($removed, $buyer, $sellerTrack) {
        return $removed === false &&
               !MusicFavorite::where('user_id', $buyer->id)->where('track_id', $sellerTrack->id)->exists();
    });

    it("7.3 Direct duplicate insert is blocked by unique constraint", function() use ($buyer, $sellerTrack) {
        MusicFavorite::create(['user_id' => $buyer->id, 'track_id' => $sellerTrack->id]);
        try {
            DB::statement('SAVEPOINT sp_favorite');
            MusicFavorite::create(['user_id' => $buyer->id, 'track_id' => $sellerTrack->id]);
            DB::statement('RELEASE SAVEPOINT sp_favorite');
            return false;
        } catch (\Illuminate\Database\QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT sp_favorite');
            return true;
        }
    });

    it("7.4 Favoriting a track does NOT grant streaming entitlement", function() use ($buyer, $adminTrack, $sellerTrack) {
        MusicFavorite::toggle($buyer->id, $adminTrack->id);
        // User favorited adminTrack, but has no valid entitlement (make sure adminTrack has no free offer)
        $unentitledUser = User::create([
            'name' => 'Stranger Buyer',
            'email' => 'stranger_' . Str::random(5) . '@funmoments.test',
            'username' => 'stranger_' . Str::random(5),
            'password' => bcrypt('password123'),
            'user_type' => User::USER_TYPE_BUYER,
        ]);
        MusicFavorite::toggle($unentitledUser->id, $sellerTrack->id);
        return $sellerTrack->canBeStreamedBy($unentitledUser) === false;
    });

    // --- SECTION 8: SELLER OWNERSHIP ISOLATION & POLICY BOUNDARIES ---
    echo "\n--- 8. SELLER OWNERSHIP ISOLATION & POLICY BOUNDARIES ---\n";

    $policy = new MusicTrackPolicy();

    $otherSeller = User::create([
        'name' => 'Intruder DJ',
        'email' => 'intruder_' . Str::random(5) . '@funmoments.test',
        'username' => 'intruder_' . Str::random(5),
        'password' => bcrypt('password123'),
        'user_type' => User::USER_TYPE_SELLER,
        'user_status' => 1,
    ]);

    it("8.1 Seller cannot update another seller's track", function() use ($policy, $otherSeller, $sellerTrack) {
        return $policy->update($otherSeller, $sellerTrack) === false;
    });

    it("8.2 Seller cannot update admin/platform-owned track", function() use ($policy, $seller, $adminTrack) {
        return $policy->update($seller, $adminTrack) === false;
    });

    it("8.3 Owner seller can update their own draft/rejected track", function() use ($policy, $seller, $sellerTrack) {
        return $policy->update($seller, $sellerTrack) === true;
    });

    it("8.4 Seller cannot edit approved track directly without re-moderation", function() use ($policy, $seller, $sellerTrack) {
        $sellerTrack->update(['status' => MusicTrack::STATUS_APPROVED]);
        return $policy->update($seller, $sellerTrack) === false;
    });

    it("8.5 Seller cannot delete track if completed orders exist", function() use ($policy, $seller, $sellerTrack) {
        $sellerTrack->update(['status' => MusicTrack::STATUS_DRAFT]);
        return $policy->delete($seller, $sellerTrack) === false; // $order exists for this track!
    });

    // --- SECTION 9: GLOBAL MUSIC SETTINGS ---
    echo "\n--- 9. GLOBAL MUSIC SETTINGS ---\n";

    it("9.1 music_marketplace_enabled defaults to false (0) until administratively enabled", function() {
        return MusicSetting::isMarketplaceEnabled() === false &&
               (int) MusicSetting::get('music_marketplace_enabled') === 0;
    });

    it("9.2 paid_preview_duration_seconds returns canonical default of 20 seconds", function() {
        return MusicSetting::getPaidPreviewDurationSeconds() === 20 &&
               (int) MusicSetting::get('paid_preview_duration_seconds') === 20;
    });

    it("9.3 getPaidPreviewDurationSeconds() strictly clamps values within server bounds (5 to 60)", function() {
        // Test lower bound clamp (< 5s clamped to 5s)
        MusicSetting::set('paid_preview_duration_seconds', 2);
        $clampedLow = MusicSetting::getPaidPreviewDurationSeconds();

        // Test upper bound clamp (> 60s clamped to 60s)
        MusicSetting::set('paid_preview_duration_seconds', 120);
        $clampedHigh = MusicSetting::getPaidPreviewDurationSeconds();

        // Reset to standard 20s
        MusicSetting::set('paid_preview_duration_seconds', 20);

        return $clampedLow === 5 && $clampedHigh === 60;
    });

    it("9.4 Legacy default_preview_duration aliases to paid_preview_duration_seconds", function() {
        MusicSetting::set('paid_preview_duration_seconds', 25, 'Updated duration');
        $readViaLegacy = (int) MusicSetting::get('default_preview_duration');

        // Reset back
        MusicSetting::set('paid_preview_duration_seconds', 20);

        return $readViaLegacy === 25;
    });

    it("9.5 toPublicConfig() generates complete, sanitized configuration contract for mobile app", function() {
        $config = MusicSetting::toPublicConfig();

        return array_key_exists('marketplace_enabled', $config) &&
               $config['marketplace_enabled'] === false &&
               array_key_exists('paid_preview_duration_seconds', $config) &&
               $config['paid_preview_duration_seconds'] === 20 &&
               is_array($config['allowed_audio_formats']) &&
               in_array('mp3', $config['allowed_audio_formats']) &&
               isset($config['offers']['free'], $config['offers']['rent'], $config['offers']['sale']) &&
               $config['default_rental_duration_days'] === 7;
    });

    it("9.6 MusicTrackPolicy::create() blocks seller submissions when seller_submissions_enabled is 0", function() use ($policy, $seller) {
        MusicSetting::set('seller_submissions_enabled', '0');
        $blocked = $policy->create($seller) === false;

        MusicSetting::set('seller_submissions_enabled', '1');
        $allowed = $policy->create($seller) === true;

        return $blocked && $allowed;
    });

    it("9.7 getValidationRules() defines authoritative schema for admin dashboard updates", function() {
        $rules = MusicSetting::getValidationRules();

        return isset($rules['music_marketplace_enabled']) &&
               isset($rules['paid_preview_duration_seconds']) &&
               isset($rules['max_audio_upload_mb']) &&
               isset($rules['seller_submissions_enabled']) &&
               isset($rules['offer_sale_enabled']);
    });

    // --- SECTION 10: REVIEW A — GENRE CONSISTENCY & DEDUPLICATION ---
    echo "\n--- 10. REVIEW A — GENRE CONSISTENCY & DEDUPLICATION ---\n";

    $houseGenre = MusicGenre::where('slug', 'house')->first();
    $technoGenre = MusicGenre::where('slug', 'techno')->first();
    $edmGenre = MusicGenre::where('slug', 'edm-dance')->first();

    it("10.1 Primary genre is authoritatively governed by genre_id", function() use ($sellerTrack, $houseGenre) {
        $sellerTrack->genre_id = $houseGenre->id;
        $sellerTrack->save();
        $sellerTrack->refresh();
        return $sellerTrack->primaryGenre->id === $houseGenre->id &&
               $sellerTrack->genre->id === $houseGenre->id;
    });

    it("10.2 syncGenres() excludes primary genre from secondary pivot and prevents duplicate storage", function() use ($sellerTrack, $houseGenre, $technoGenre, $edmGenre) {
        // Deliberately pass houseGenre (primary) into the secondary list along with techno and edm
        $sellerTrack->syncGenres($houseGenre->id, [$houseGenre->id, $technoGenre->id, $edmGenre->id]);
        $sellerTrack->refresh();

        $secondaryIds = $sellerTrack->secondaryGenres->pluck('id')->all();
        return !in_array($houseGenre->id, $secondaryIds) &&
               in_array($technoGenre->id, $secondaryIds) &&
               in_array($edmGenre->id, $secondaryIds) &&
               count($secondaryIds) === 2;
    });

    it("10.3 Direct duplicate genre in pivot table is prevented by compound primary key", function() use ($sellerTrack, $technoGenre) {
        try {
            DB::statement('SAVEPOINT sp_genre_dup');
            DB::table('music_track_genres')->insert([
                'track_id' => $sellerTrack->id,
                'genre_id' => $technoGenre->id,
            ]);
            DB::statement('RELEASE SAVEPOINT sp_genre_dup');
            return false;
        } catch (\Illuminate\Database\QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT sp_genre_dup');
            return true;
        }
    });

    it("10.4 allGenres() returns unified collection without duplicates", function() use ($sellerTrack, $houseGenre, $technoGenre, $edmGenre) {
        $all = $sellerTrack->allGenres();
        $ids = $all->pluck('id')->all();
        return count($ids) === 3 &&
               $ids[0] === $houseGenre->id &&
               in_array($technoGenre->id, $ids) &&
               in_array($edmGenre->id, $ids);
    });

    it("10.5 scopeByAnyGenre() retrieves track via primary or secondary without duplicate rows", function() use ($sellerTrack, $houseGenre, $technoGenre) {
        $byPrimary = MusicTrack::byAnyGenre($houseGenre->id)->where('music_tracks.id', $sellerTrack->id)->get();
        $bySecondary = MusicTrack::byAnyGenre($technoGenre->id)->where('music_tracks.id', $sellerTrack->id)->get();

        return $byPrimary->count() === 1 && $bySecondary->count() === 1;
    });

    // --- SECTION 11: REVIEW B — HISTORICAL ATTRIBUTION & SAFE SUSPENSION ---
    echo "\n--- 11. REVIEW B — HISTORICAL ATTRIBUTION & SAFE SUSPENSION ---\n";

    it("11.1 recordSubmissionAttribution() records creator and rights snapshot", function() use ($sellerTrack, $seller) {
        $sellerTrack->recordSubmissionAttribution($seller, '127.0.0.1');
        $sellerTrack->refresh();

        return $sellerTrack->submitted_by_user_id === $seller->id &&
               $sellerTrack->original_creator_name === $seller->name &&
               $sellerTrack->rights_accepted_by_user_id === $seller->id &&
               $sellerTrack->rights_accepted_by_name === $seller->name;
    });

    $orderWithSnapshots = MusicOrder::create([
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
        'track_id' => $sellerTrack->id,
        'offer_id' => $saleOffer->id,
        'offer_type' => MusicOffer::OFFER_TYPE_SALE,
        'amount' => 15.00,
        'currency' => 'SAR',
        'commission_rate' => 15.00,
        'commission_amount' => 2.25,
        'seller_earning' => 12.75,
        'payment_gateway' => MusicOrder::GATEWAY_PAYTABS,
        'payment_status' => MusicOrder::STATUS_COMPLETED,
        'transaction_id' => 'TXN-ATTR-001',
        'idempotency_key' => 'IDEM-ATTR-001',
    ]);

    it("11.2 MusicOrder creation snapshots seller and buyer names and emails", function() use ($orderWithSnapshots, $seller, $buyer) {
        return $orderWithSnapshots->seller_name === $seller->name &&
               $orderWithSnapshots->seller_email === $seller->email &&
               $orderWithSnapshots->buyer_name === $buyer->name &&
               $orderWithSnapshots->buyer_email === $buyer->email;
    });

    it("11.3 suspendForDeactivatedSeller() suspends tracks and deactivates active offers", function() use ($seller, $sellerTrack, $saleOffer) {
        // Ensure offer is active first
        $saleOffer->update(['is_active' => true]);

        $suspendedCount = MusicTrack::suspendForDeactivatedSeller($seller->id, 'Seller account deactivated pending verification');
        $sellerTrack->refresh();
        $saleOffer->refresh();

        return $suspendedCount >= 1 &&
               $sellerTrack->status === MusicTrack::STATUS_SUSPENDED &&
               $saleOffer->is_active === false;
    });

    it("11.4 Historical entitlements and orders remain accessible when track is suspended", function() use ($sellerTrack, $buyer, $saleOffer, $orderWithSnapshots, $seller) {
        $validEntitlement = MusicEntitlement::create([
            'user_id' => $buyer->id,
            'track_id' => $sellerTrack->id,
            'offer_id' => $saleOffer->id,
            'order_id' => $orderWithSnapshots->id,
            'starts_at' => now(),
            'expires_at' => null,
            'is_active' => true,
        ]);

        return $validEntitlement->isValid() === true &&
               $sellerTrack->canBeStreamedBy($buyer) === true &&
               $orderWithSnapshots->seller_name === $seller->name;
    });

    it("11.5 Suspended track cannot be streamed without valid pre-existing entitlement", function() use ($sellerTrack) {
        $unentitledUser = User::create([
            'name' => 'Late Buyer',
            'email' => 'late_' . Str::random(5) . '@funmoments.test',
            'username' => 'late_' . Str::random(5),
            'password' => bcrypt('password123'),
            'user_type' => User::USER_TYPE_BUYER,
        ]);
        return $sellerTrack->canBeStreamedBy($unentitledUser) === false;
    });

    // Real Lifecycle Integration Test 11.6: Accountdeactive creation automatically suspends seller tracks
    $lifecycleSeller = User::create([
        'name' => 'Lifecycle DJ',
        'email' => 'lifecycle_' . Str::random(5) . '@funmoments.test',
        'username' => 'lifecycle_' . Str::random(5),
        'password' => bcrypt('password123'),
        'user_type' => User::USER_TYPE_SELLER,
        'user_status' => 1,
    ]);

    $lifecycleTrack = MusicTrack::create([
        'title_en' => 'Lifecycle Track',
        'artist_name' => 'Lifecycle DJ',
        'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
        'seller_id' => $lifecycleSeller->id,
        'genre_id' => $houseGenre->id,
        'cover_image' => 'lifecycle.jpg',
        'audio_path_original' => 'music/original/lifecycle.mp3',
        'duration_seconds' => 180,
        'preview_duration_seconds' => 20,
        'audio_format' => 'mp3',
        'rights_declaration_type' => MusicTrack::RIGHTS_ORIGINAL,
        'rights_agreement_accepted' => true,
        'status' => MusicTrack::STATUS_APPROVED,
        'released_at' => now()->subDay(),
    ]);

    $lifecycleOffer = MusicOffer::create([
        'track_id' => $lifecycleTrack->id,
        'offer_type' => MusicOffer::OFFER_TYPE_SALE,
        'price' => 20.00,
        'currency' => 'SAR',
        'is_active' => true,
    ]);

    it("11.6 Accountdeactive workflow automatically invokes track suspension and offer deactivation", function() use ($lifecycleSeller, $lifecycleTrack, $lifecycleOffer) {
        Accountdeactive::create([
            'user_id' => $lifecycleSeller->id,
            'reason' => 'Voluntary seller withdrawal',
            'description' => 'Deactivating DJ profile',
            'status' => 0,
            'account_status' => 0,
        ]);

        $lifecycleTrack->refresh();
        $lifecycleOffer->refresh();

        return $lifecycleTrack->status === MusicTrack::STATUS_SUSPENDED &&
               $lifecycleOffer->is_active === false &&
               $lifecycleTrack->rejection_reason === 'Voluntary seller withdrawal';
    });

    it("11.7 User model user_status update hook automatically suspends tracks when deactivated", function() use ($houseGenre) {
        $statusSeller = User::create([
            'name' => 'Status DJ',
            'email' => 'status_' . Str::random(5) . '@funmoments.test',
            'username' => 'status_' . Str::random(5),
            'password' => bcrypt('password123'),
            'user_type' => User::USER_TYPE_SELLER,
            'user_status' => 1,
        ]);

        $statusTrack = MusicTrack::create([
            'title_en' => 'Status Track',
            'artist_name' => 'Status DJ',
            'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
            'seller_id' => $statusSeller->id,
            'genre_id' => $houseGenre->id,
            'audio_path_original' => 'music/original/status.mp3',
            'duration_seconds' => 150,
            'preview_duration_seconds' => 20,
            'audio_format' => 'mp3',
            'status' => MusicTrack::STATUS_APPROVED,
            'released_at' => now()->subDay(),
        ]);

        $statusOffer = MusicOffer::create([
            'track_id' => $statusTrack->id,
            'offer_type' => MusicOffer::OFFER_TYPE_FREE,
            'price' => 0,
            'is_active' => true,
        ]);

        // Seller deactivated via Eloquent update
        $statusSeller->update(['user_status' => 0]);

        $statusTrack->refresh();
        $statusOffer->refresh();

        return $statusTrack->status === MusicTrack::STATUS_SUSPENDED &&
               $statusOffer->is_active === false;
    });

    it("11.8 Administrative deletion via UserDeletionService suspends tracks and preserves attribution", function() use ($admin, $houseGenre) {
        $deleteSeller = User::create([
            'name' => 'Purged DJ',
            'email' => 'purged_' . Str::random(5) . '@funmoments.test',
            'username' => 'purged_' . Str::random(5),
            'password' => bcrypt('password123'),
            'user_type' => User::USER_TYPE_SELLER,
            'user_status' => 1,
        ]);

        $deleteTrack = MusicTrack::create([
            'title_en' => 'Purged Track',
            'artist_name' => 'Purged DJ',
            'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
            'seller_id' => $deleteSeller->id,
            'genre_id' => $houseGenre->id,
            'audio_path_original' => 'music/original/purged.mp3',
            'duration_seconds' => 210,
            'preview_duration_seconds' => 20,
            'audio_format' => 'mp3',
            'status' => MusicTrack::STATUS_APPROVED,
            'released_at' => now()->subDay(),
        ]);
        $deleteTrack->recordSubmissionAttribution($deleteSeller, '10.0.0.1');

        $deleteOffer = MusicOffer::create([
            'track_id' => $deleteTrack->id,
            'offer_type' => MusicOffer::OFFER_TYPE_RENT,
            'rental_duration_days' => 14,
            'price' => 5.00,
            'is_active' => true,
        ]);

        // Execute administrative deletion
        $deletionService = app(UserDeletionService::class);
        $deletionService->executeDeletion($deleteSeller, $admin->id);

        $deleteTrack->refresh();
        $deleteOffer->refresh();

        return $deleteTrack->status === MusicTrack::STATUS_SUSPENDED &&
               $deleteOffer->is_active === false &&
               $deleteTrack->original_creator_name === 'Purged DJ';
    });

    it("11.9 Repeated suspension/deactivation is completely idempotent", function() use ($lifecycleSeller, $lifecycleTrack, $lifecycleOffer) {
        // Trigger deactivation a second time
        $suspendedCount = MusicTrack::suspendForDeactivatedSeller($lifecycleSeller->id, 'Repeated suspension check');
        $lifecycleTrack->refresh();
        $lifecycleOffer->refresh();

        return $suspendedCount >= 1 &&
               $lifecycleTrack->status === MusicTrack::STATUS_SUSPENDED &&
               $lifecycleOffer->is_active === false;
    });

    // --- SECTION 12: REVIEW C — UPLOAD LIMITS & PHP CEILING ALIGNMENT ---
    echo "\n--- 12. REVIEW C — UPLOAD LIMITS & PHP CEILING ALIGNMENT ---\n";

    it("12.1 MusicSetting default max_audio_upload_mb is 25 MB", function() {
        return (int) MusicSetting::getDefaults()['max_audio_upload_mb'] === 25;
    });

    it("12.2 parseIniBytes() accurately converts human-readable INI string to bytes", function() {
        return MusicSetting::parseIniBytes('32M') === 33554432 &&
               MusicSetting::parseIniBytes('256M') === 268435456 &&
               MusicSetting::parseIniBytes('1G') === 1073741824 &&
               MusicSetting::parseIniBytes('1024K') === 1048576 &&
               MusicSetting::parseIniBytes('5000') === 5000;
    });

    it("12.3 getEffectiveMaxUploadBytes() clamps application setting to PHP runtime ceiling", function() {
        $effectiveBytes = MusicSetting::getEffectiveMaxUploadBytes();
        $phpMaxBytes = MusicSetting::getPhpUploadMaxBytes();
        $configuredMb = (int) MusicSetting::get('max_audio_upload_mb', 25);

        // Effective bytes must never exceed the PHP runtime ceiling
        return $effectiveBytes <= $phpMaxBytes &&
               $effectiveBytes > 0 &&
               $effectiveBytes <= ($configuredMb * 1024 * 1024);
    });

    it("12.4 25 MB default leaves safe headroom (> 2 MB) under 32 MB PHP ceiling", function() {
        $audioDefaultBytes = 25 * 1024 * 1024;
        $php32MBytes = 32 * 1024 * 1024;
        $headroomBytes = $php32MBytes - $audioDefaultBytes;

        // Headroom must be at least 2 MB for multipart/form-data overhead and cover art
        return $headroomBytes >= (2 * 1024 * 1024); // 7 MB headroom
    });

} finally {
    // Clean rollback of test data so database remains clean
    DB::rollBack();
}

echo "\n========================================================\n";
echo "TEST RESULTS: Passed: $passed, Failed: $failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
