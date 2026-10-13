<?php

/**
 * FUN MOMENT — TERMS & CONDITIONS STORAGE FOUNDATION TEST SUITE
 *
 * Runs strictly against an isolated in-memory SQLite database.
 * Does NOT touch or modify any PostgreSQL, MySQL, or persistent database.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Admin;
use App\LegalTerm;
use App\LegalTermVersion;
use App\User;
use App\UserTermsAcceptance;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1. Configure isolated in-memory SQLite connection
Config::set('database.connections.sqlite_test_isolated', [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
]);
Config::set('database.default', 'sqlite_test_isolated');
DB::purge('sqlite_test_isolated');
DB::reconnect('sqlite_test_isolated');

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] $testName" . ($detail ? " ($detail)" : '') . PHP_EOL;
    } else {
        $failed++;
        echo " [FAIL] $testName" . ($detail ? " - $detail" : '') . PHP_EOL;
    }
}

echo "==========================================================" . PHP_EOL;
echo " PHASE 2A: LEGAL TERMS & ACCEPTANCE FOUNDATION TEST SUITE" . PHP_EOL;
echo " Connection: SQLite (:memory:) — Zero Persistent Footprint" . PHP_EOL;
echo "==========================================================" . PHP_EOL . PHP_EOL;

try {
    // ------------------------------------------------------------------------
    // SETUP: Create minimal users and admins tables for referential integrity
    // ------------------------------------------------------------------------
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('username')->unique();
        $table->string('password');
        $table->integer('user_type')->default(1);
        $table->timestamps();
    });

    Schema::create('admins', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('username')->unique();
        $table->string('email');
        $table->string('password');
        $table->string('role')->default('super_admin');
        $table->timestamps();
    });

    // ------------------------------------------------------------------------
    // RUN MIGRATIONS UP
    // ------------------------------------------------------------------------
    $m1 = require __DIR__ . '/../database/migrations/2026_10_14_000001_create_legal_terms_table.php';
    $m2 = require __DIR__ . '/../database/migrations/2026_10_14_000002_create_legal_term_versions_table.php';
    $m3 = require __DIR__ . '/../database/migrations/2026_10_14_000003_create_user_terms_acceptances_table.php';

    $m1->up();
    $m2->up();
    $m3->up();

    // ------------------------------------------------------------------------
    // T1: Initial master registry records count
    // ------------------------------------------------------------------------
    $termsCount = LegalTerm::count();
    assertTest(
        $termsCount === 4,
        "T1: Master document registry seeded with 4 records",
        "Found $termsCount records"
    );

    // ------------------------------------------------------------------------
    // T2: Four authoritative combinations with exact display titles
    // ------------------------------------------------------------------------
    $buyerAr = LegalTerm::findByRoleAndLanguage('buyer', 'ar');
    $buyerEn = LegalTerm::findByRoleAndLanguage('buyer', 'en');
    $sellerAr = LegalTerm::findByRoleAndLanguage('seller', 'ar');
    $sellerEn = LegalTerm::findByRoleAndLanguage('seller', 'en');

    $titlesMatch = $buyerAr && $buyerAr->title === 'شروط وأحكام المشتري'
        && $buyerEn && $buyerEn->title === 'Buyer Terms & Conditions'
        && $sellerAr && $sellerAr->title === 'شروط وأحكام مقدم الخدمة'
        && $sellerEn && $sellerEn->title === 'Seller Terms & Conditions';

    assertTest(
        $titlesMatch,
        "T2: Four documents have exact required display titles",
        "buyer/ar, buyer/en, seller/ar, seller/en verified"
    );

    // ------------------------------------------------------------------------
    // T3: Draft content is empty/null, no invented legal clauses
    // ------------------------------------------------------------------------
    $draftsNull = is_null($buyerAr->draft_content)
        && is_null($buyerEn->draft_content)
        && is_null($sellerAr->draft_content)
        && is_null($sellerEn->draft_content);

    assertTest(
        $draftsNull,
        "T3: Draft content is strictly null (no placeholder or invented clauses)",
        "All 4 drafts are null"
    );

    // ------------------------------------------------------------------------
    // T4: Published version references are null and hasPublishedVersion() is false
    // ------------------------------------------------------------------------
    $noPublished = !$buyerAr->hasPublishedVersion()
        && !$buyerEn->hasPublishedVersion()
        && !$sellerAr->hasPublishedVersion()
        && !$sellerEn->hasPublishedVersion()
        && is_null($buyerAr->current_version_id);

    assertTest(
        $noPublished,
        "T4: No active published version exists initially",
        "hasPublishedVersion() is false for all documents"
    );

    // ------------------------------------------------------------------------
    // T5: Zero initial versions and zero initial acceptances
    // ------------------------------------------------------------------------
    $versionsCount = LegalTermVersion::count();
    $acceptancesCount = UserTermsAcceptance::count();

    assertTest(
        $versionsCount === 0 && $acceptancesCount === 0,
        "T5: Clean baseline with zero versions and zero acceptances",
        "versions=$versionsCount, acceptances=$acceptancesCount"
    );

    // ------------------------------------------------------------------------
    // T6: Duplicate (role, language) rejected by unique constraint
    // ------------------------------------------------------------------------
    $duplicateBlocked = false;
    try {
        DB::table('legal_terms')->insert([
            'role' => 'buyer',
            'language' => 'en',
            'title' => 'Duplicate Buyer Terms',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Throwable $e) {
        $duplicateBlocked = true;
    }

    assertTest(
        $duplicateBlocked,
        "T6: Duplicate (role, language) master document rejected by unique constraint"
    );

    // ------------------------------------------------------------------------
    // T7: Buyer and seller documents are distinct and independent
    // ------------------------------------------------------------------------
    assertTest(
        $buyerAr->isBuyer() && !$buyerAr->isSeller() && $sellerAr->isSeller() && !$sellerAr->isBuyer(),
        "T7: Buyer and seller documents remain independent",
        "Role checks verified"
    );

    // ------------------------------------------------------------------------
    // T8: Arabic and English documents are distinct and independent
    // ------------------------------------------------------------------------
    assertTest(
        $buyerAr->isArabic() && !$buyerAr->isEnglish() && $buyerEn->isEnglish() && !$buyerEn->isArabic(),
        "T8: Arabic and English documents remain independent",
        "Language checks verified"
    );

    // ------------------------------------------------------------------------
    // T9: Multiple immutable versions with sequential numbers and content hash
    // ------------------------------------------------------------------------
    $admin = Admin::create([
        'name' => 'Test Admin',
        'username' => 'test_admin',
        'email' => 'admin@test.com',
        'password' => 'secret123',
    ]);

    $contentV1 = "FUN MOMENT Buyer Terms v1 canonical content.";
    $hashV1 = LegalTermVersion::hashContent($contentV1);

    $v1 = LegalTermVersion::create([
        'legal_term_id' => $buyerEn->id,
        'version_number' => 1,
        'title' => 'Buyer Terms & Conditions v1',
        'content' => $contentV1,
        'content_hash' => $hashV1,
        'published_by' => $admin->id,
        'published_at' => now(),
    ]);

    $contentV2 = "FUN MOMENT Buyer Terms v2 updated canonical content.";
    $hashV2 = LegalTermVersion::hashContent($contentV2);

    $v2 = LegalTermVersion::create([
        'legal_term_id' => $buyerEn->id,
        'version_number' => 2,
        'title' => 'Buyer Terms & Conditions v2',
        'content' => $contentV2,
        'content_hash' => $hashV2,
        'published_by' => $admin->id,
        'published_at' => now(),
    ]);

    $versions = $buyerEn->versions()->get();

    assertTest(
        $versions->count() === 2 && $versions->first()->version_number === 2 && $versions->last()->version_number === 1,
        "T9: Multiple immutable versions created in sequence for master document",
        "Total versions: {$versions->count()}, ordered descending by version_number"
    );

    // ------------------------------------------------------------------------
    // T10: Content hash verification matches canonical SHA-256
    // ------------------------------------------------------------------------
    $hashMatches = $v1->verifyHash() && $v2->verifyHash();
    $tamperedV1 = clone $v1;
    $tamperedV1->content = "Tampered content";
    $tamperDetected = !$tamperedV1->verifyHash();

    assertTest(
        $hashMatches && $tamperDetected,
        "T10: Content SHA-256 hash verified and tampering detected",
        "v1 hash=" . substr($hashV1, 0, 12) . "..., tamper detected=true"
    );

    // ------------------------------------------------------------------------
    // T11: Duplicate version_number for same document is rejected
    // ------------------------------------------------------------------------
    $duplicateVersionBlocked = false;
    try {
        LegalTermVersion::create([
            'legal_term_id' => $buyerEn->id,
            'version_number' => 1,
            'title' => 'Conflict version',
            'content' => 'Conflict content',
            'content_hash' => LegalTermVersion::hashContent('Conflict content'),
            'published_at' => now(),
        ]);
    } catch (\Throwable $e) {
        $duplicateVersionBlocked = true;
    }

    assertTest(
        $duplicateVersionBlocked,
        "T11: Duplicate version_number within same document rejected by unique constraint"
    );

    // ------------------------------------------------------------------------
    // T12: Immutability: Editing master draft does NOT alter published versions
    // ------------------------------------------------------------------------
    $buyerEn->update([
        'draft_title' => 'New Draft in Progress',
        'draft_content' => 'Work-in-progress draft text that has not been approved.',
    ]);

    $freshV1 = LegalTermVersion::find($v1->id);
    $freshV2 = LegalTermVersion::find($v2->id);

    $immutabilityPreserved = $freshV1->content === $contentV1
        && $freshV1->content_hash === $hashV1
        && $freshV2->content === $contentV2
        && $freshV2->content_hash === $hashV2;

    assertTest(
        $immutabilityPreserved,
        "T12: Draft edits do NOT mutate existing published versions (immutability preserved)",
        "v1 and v2 contents remain identical to publication snapshot"
    );

    // ------------------------------------------------------------------------
    // T13: Publishing linkage: current_version_id resolves activeVersion & isCurrent()
    // ------------------------------------------------------------------------
    $buyerEn->update(['current_version_id' => $v2->id]);
    $freshBuyerEn = LegalTerm::find($buyerEn->id);

    $activeResolves = $freshBuyerEn->hasPublishedVersion()
        && $freshBuyerEn->activeVersion->id === $v2->id
        && $v2->fresh()->isCurrent()
        && !$v1->fresh()->isCurrent();

    assertTest(
        $activeResolves,
        "T13: current_version_id accurately resolves activeVersion and isCurrent() state",
        "v2 is current, v1 is archived"
    );

    // ------------------------------------------------------------------------
    // T14: User acceptance record persistence
    // ------------------------------------------------------------------------
    $user = User::create([
        'name' => 'Acceptance Test User',
        'email' => 'acceptance_user@test.com',
        'username' => 'accept_user_1',
        'password' => 'secret123',
        'user_type' => 1,
    ]);

    $acceptanceTimestamp = now();
    $acceptance = UserTermsAcceptance::create([
        'user_id' => $user->id,
        'role' => LegalTerm::ROLE_BUYER,
        'legal_term_id' => $freshBuyerEn->id,
        'legal_term_version_id' => $v2->id,
        'version_number' => $v2->version_number,
        'content_hash' => $v2->content_hash,
        'language' => LegalTerm::LANG_EN,
        'accepted_at' => $acceptanceTimestamp,
    ]);

    $acceptancePersisted = $acceptance->exists
        && $acceptance->user_id === $user->id
        && $acceptance->version_number === 2
        && $acceptance->content_hash === $hashV2
        && $acceptance->role === 'buyer'
        && $acceptance->language === 'en';

    assertTest(
        $acceptancePersisted,
        "T14: User acceptance record persists durable audit evidence",
        "Linked to user={$user->id}, version={$v2->version_number}, hash=" . substr($hashV2, 0, 10) . "..."
    );

    // ------------------------------------------------------------------------
    // T15: Acceptance hash verification against version
    // ------------------------------------------------------------------------
    $hashValid = $acceptance->verifyHashAgainstVersion();
    $tamperedAcceptance = clone $acceptance;
    $tamperedAcceptance->content_hash = 'tampered_hash_string';
    $tamperDetected = !$tamperedAcceptance->verifyHashAgainstVersion();

    assertTest(
        $hashValid && $tamperDetected,
        "T15: Acceptance content hash verifies against immutable version and detects mismatch"
    );

    // ------------------------------------------------------------------------
    // T16: Clean rollback in reverse order
    // ------------------------------------------------------------------------
    $rollbackSuccess = false;
    try {
        $m3->down(); // user_terms_acceptances
        $m2->down(); // legal_term_versions + drops foreign key from legal_terms
        $m1->down(); // legal_terms

        $tablesDropped = !Schema::hasTable('user_terms_acceptances')
            && !Schema::hasTable('legal_term_versions')
            && !Schema::hasTable('legal_terms');

        $rollbackSuccess = $tablesDropped;
    } catch (\Throwable $e) {
        echo " Rollback error: " . $e->getMessage() . PHP_EOL;
    }

    assertTest(
        $rollbackSuccess,
        "T16: Migration rollback (down) executes cleanly in reverse order without errors",
        "All 3 tables dropped cleanly"
    );

} catch (\Throwable $e) {
    echo " [FATAL ERROR]: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failed++;
}

echo PHP_EOL . "==========================================================" . PHP_EOL;
echo " TEST SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
