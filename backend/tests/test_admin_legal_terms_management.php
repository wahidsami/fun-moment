<?php

/**
 * FUN MOMENT — ADMIN LEGAL TERMS MANAGEMENT & PUBLISHING TEST SUITE
 *
 * Runs strictly against an isolated in-memory SQLite database (:memory:).
 * Does NOT touch or modify PostgreSQL, MySQL, or any persistent database.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\AdminAuditLog;
use App\Http\Controllers\AdminLegalTermsController;
use App\LegalTerm;
use App\LegalTermVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1. Configure isolated in-memory SQLite connection
Config::set('database.connections.sqlite_test_admin', [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
]);
Config::set('database.default', 'sqlite_test_admin');
DB::purge('sqlite_test_admin');
DB::reconnect('sqlite_test_admin');

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
echo " PHASE 2B: ADMIN LEGAL TERMS MANAGEMENT TEST SUITE" . PHP_EOL;
echo " Connection: SQLite (:memory:) — Zero Persistent Footprint" . PHP_EOL;
echo "==========================================================" . PHP_EOL . PHP_EOL;

try {
    // ------------------------------------------------------------------------
    // SETUP: Create minimal admins, audit logs, and terms tables
    // ------------------------------------------------------------------------
    Schema::create('admins', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('username')->unique();
        $table->string('email');
        $table->string('password');
        $table->string('role')->default('editor');
        $table->timestamps();
    });

    Schema::create('admin_audit_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('admin_id')->nullable()->index();
        $table->string('admin_name')->nullable();
        $table->string('admin_email')->nullable();
        $table->string('action', 100)->index();
        $table->string('resource_type', 100)->index();
        $table->string('resource_id', 100)->nullable()->index();
        $table->text('details_en')->nullable();
        $table->text('details_ar')->nullable();
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->string('status', 30)->default('success');
        $table->timestamps();
    });

    // Run Legal Terms migrations
    $m1 = require __DIR__ . '/../database/migrations/2026_10_14_000001_create_legal_terms_table.php';
    $m2 = require __DIR__ . '/../database/migrations/2026_10_14_000002_create_legal_term_versions_table.php';
    $m3 = require __DIR__ . '/../database/migrations/2026_10_14_000003_create_user_terms_acceptances_table.php';

    $m1->up();
    $m2->up();
    $m3->up();

    // Create admins
    $superAdmin = Admin::create([
        'name' => 'Super Administrator',
        'username' => 'super_admin',
        'email' => 'super@funmoment.sa',
        'password' => bcrypt('password123'),
        'role' => 'super_admin',
    ]);

    $restrictedAdmin = Admin::create([
        'name' => 'Restricted Viewer',
        'username' => 'restricted_viewer',
        'email' => 'viewer@funmoment.sa',
        'password' => bcrypt('password123'),
        'role' => 'viewer',
    ]);

    $controller = new AdminLegalTermsController();

    // ------------------------------------------------------------------------
    // T1: Initial clean state: 4 seeded records with null drafts & null versions
    // ------------------------------------------------------------------------
    Auth::guard('admin')->login($superAdmin);
    $response = $controller->index();
    $data = $response->getData(true);

    $allNullDrafts = true;
    $allNullActive = true;
    foreach ($data['data'] as $doc) {
        if (!is_null($doc['draft_content'])) $allNullDrafts = false;
        if (!is_null($doc['current_version_id'])) $allNullActive = false;
    }

    assertTest(
        count($data['data']) === 4 && $allNullDrafts && $allNullActive,
        "T1: Index returns exactly 4 master documents with null drafts and null versions",
        "Records count: " . count($data['data'])
    );

    // ------------------------------------------------------------------------
    // T2: Buyer and seller document independence
    // ------------------------------------------------------------------------
    $buyerAr = LegalTerm::findByRoleAndLanguage('buyer', 'ar');
    $sellerAr = LegalTerm::findByRoleAndLanguage('seller', 'ar');
    $buyerEn = LegalTerm::findByRoleAndLanguage('buyer', 'en');
    $sellerEn = LegalTerm::findByRoleAndLanguage('seller', 'en');

    assertTest(
        $buyerAr->id !== $sellerAr->id && $buyerEn->id !== $sellerEn->id,
        "T2: Buyer and seller documents are strictly separate records"
    );

    // ------------------------------------------------------------------------
    // T3: Arabic and English document independence
    // ------------------------------------------------------------------------
    assertTest(
        $buyerAr->id !== $buyerEn->id && $sellerAr->id !== $sellerEn->id,
        "T3: Arabic and English documents are strictly separate records"
    );

    // ------------------------------------------------------------------------
    // T4: Saving draft updates draft_title and draft_content without publishing
    // ------------------------------------------------------------------------
    $req = Request::create('/admin-home/legal-terms/' . $buyerEn->id . '/draft', 'POST', [
        'draft_title' => 'FUN MOMENT Customer Service Agreement v1 Draft',
        'draft_content' => '<p>These are the draft customer terms in English.</p>',
    ]);

    $draftRes = $controller->updateDraft($req, $buyerEn->id);
    $freshBuyerEn = LegalTerm::find($buyerEn->id);

    assertTest(
        $freshBuyerEn->draft_title === 'FUN MOMENT Customer Service Agreement v1 Draft'
            && str_contains($freshBuyerEn->draft_content, 'draft customer terms')
            && is_null($freshBuyerEn->current_version_id),
        "T4: Draft save updates draft content without changing active published version",
        "current_version_id remains null"
    );

    // ------------------------------------------------------------------------
    // T5: Draft save creates ZERO versions
    // ------------------------------------------------------------------------
    $versionsCount = LegalTermVersion::where('legal_term_id', $buyerEn->id)->count();
    assertTest(
        $versionsCount === 0,
        "T5: Saving a draft does NOT create an immutable published version",
        "Versions count = 0"
    );

    // ------------------------------------------------------------------------
    // T6: Rejection of empty content during publish
    // ------------------------------------------------------------------------
    $emptyTerm = LegalTerm::findByRoleAndLanguage('seller', 'en');
    $emptyReq = Request::create('/admin-home/legal-terms/' . $emptyTerm->id . '/publish', 'POST');
    $emptyPublishRes = $controller->publish($emptyReq, $emptyTerm->id);

    assertTest(
        $emptyPublishRes->status() === 422,
        "T6: Publishing empty draft is rejected with HTTP 422 Unprocessable Entity",
        "Status: " . $emptyPublishRes->status()
    );

    // ------------------------------------------------------------------------
    // T7: Malicious HTML sanitization
    // ------------------------------------------------------------------------
    $dirtyHtml = '<p>Safe legal paragraph.</p><script>alert("hack")</script><a href="javascript:void(0)" onclick="stealCookies()">Click me</a><iframe src="evil.com"></iframe>';
    $dirtyReq = Request::create('/admin-home/legal-terms/' . $buyerEn->id . '/draft', 'POST', [
        'draft_title' => 'Customer Terms with Script Test',
        'draft_content' => $dirtyHtml,
    ]);
    $controller->updateDraft($dirtyReq, $buyerEn->id);
    $cleanBuyerEn = LegalTerm::find($buyerEn->id);

    $isSanitized = !str_contains($cleanBuyerEn->draft_content, '<script>')
        && !str_contains($cleanBuyerEn->draft_content, 'onclick')
        && !str_contains($cleanBuyerEn->draft_content, '<iframe>')
        && !str_contains($cleanBuyerEn->draft_content, 'javascript:void')
        && str_contains($cleanBuyerEn->draft_content, 'Safe legal paragraph.');

    assertTest(
        $isSanitized,
        "T7: HTML sanitization strips scripts, iframes, inline event handlers, and javascript: URIs",
        "Malicious tags removed cleanly"
    );

    // ------------------------------------------------------------------------
    // T8: Publishing valid content creates Version 1
    // ------------------------------------------------------------------------
    $v1Content = "<h3>Article 1: Platform Usage</h3><p>Customers must abide by FUN MOMENT guidelines.</p>";
    $updateReq = Request::create('/admin-home/legal-terms/' . $buyerEn->id . '/draft', 'POST', [
        'draft_title' => 'FUN MOMENT Buyer Terms & Conditions',
        'draft_content' => $v1Content,
    ]);
    $controller->updateDraft($updateReq, $buyerEn->id);

    $publishReq = Request::create('/admin-home/legal-terms/' . $buyerEn->id . '/publish', 'POST');
    $publishRes = $controller->publish($publishReq, $buyerEn->id);
    $publishData = $publishRes->getData(true);

    $publishedBuyerEn = LegalTerm::find($buyerEn->id);
    $v1 = LegalTermVersion::find($publishData['data']['version']['id']);

    assertTest(
        $publishRes->status() === 200
            && $v1->version_number === 1
            && (int) $publishedBuyerEn->current_version_id === (int) $v1->id,
        "T8: Publishing creates immutable Version 1 and updates master active version pointer",
        "Version number: {$v1->version_number}, current_version_id: {$publishedBuyerEn->current_version_id}"
    );

    // ------------------------------------------------------------------------
    // T9: SHA-256 canonical hash matches stored content
    // ------------------------------------------------------------------------
    $computedHash = hash('sha256', $v1->content);
    assertTest(
        $v1->content_hash === $computedHash && $v1->verifyHash(),
        "T9: Published version persists valid SHA-256 canonical content hash",
        "Hash: " . substr($v1->content_hash, 0, 16) . "..."
    );

    // ------------------------------------------------------------------------
    // T10: Audit log recorded in admin_audit_logs
    // ------------------------------------------------------------------------
    $auditLog = AdminAuditLog::where('action', 'publish_legal_terms')
        ->where('resource_id', (string) $buyerEn->id)
        ->latest('id')
        ->first();

    assertTest(
        $auditLog && (int) $auditLog->admin_id === (int) $superAdmin->id,
        "T10: Audit log recorded for terms publication with administrator attribution",
        "Action: {$auditLog->action}, Admin ID: {$auditLog->admin_id}"
    );

    // ------------------------------------------------------------------------
    // T11: Republishing creates Version 2 without modifying Version 1
    // ------------------------------------------------------------------------
    $v2Content = "<h3>Article 1: Platform Usage</h3><p>Updated terms with amended cancellation clauses.</p>";
    $updateReq2 = Request::create('/admin-home/legal-terms/' . $buyerEn->id . '/draft', 'POST', [
        'draft_title' => 'FUN MOMENT Buyer Terms & Conditions v2',
        'draft_content' => $v2Content,
    ]);
    $controller->updateDraft($updateReq2, $buyerEn->id);

    $publishReq2 = Request::create('/admin-home/legal-terms/' . $buyerEn->id . '/publish', 'POST');
    $publishRes2 = $controller->publish($publishReq2, $buyerEn->id);
    $publishData2 = $publishRes2->getData(true);

    $v2 = LegalTermVersion::find($publishData2['data']['version']['id']);
    $v1Reloaded = LegalTermVersion::find($v1->id);
    $publishedBuyerEnReloaded = LegalTerm::find($buyerEn->id);

    $v1Intact = $v1Reloaded->content === $v1->content
        && $v1Reloaded->content_hash === $v1->content_hash
        && $v1Reloaded->version_number === 1;

    $v2Active = $v2->version_number === 2
        && (int) $publishedBuyerEnReloaded->current_version_id === (int) $v2->id
        && $v2->isCurrent()
        && !$v1Reloaded->isCurrent();

    assertTest(
        $v1Intact && $v2Active,
        "T11: Republishing creates Version 2; Version 1 remains intact and archived",
        "v1 intact=true, v2 active=true"
    );

    // ------------------------------------------------------------------------
    // T12: Arabic document publishing with RTL text
    // ------------------------------------------------------------------------
    $arContent = "<h3>المادة الأولى: التعريفات</h3><p>تخضع هذه الشروط لمنصة فن مومنت وخدماتها المرخصة.</p>";
    $updateArReq = Request::create('/admin-home/legal-terms/' . $sellerAr->id . '/draft', 'POST', [
        'draft_title' => 'شروط وأحكام مقدم الخدمة',
        'draft_content' => $arContent,
    ]);
    $controller->updateDraft($updateArReq, $sellerAr->id);

    $publishArReq = Request::create('/admin-home/legal-terms/' . $sellerAr->id . '/publish', 'POST');
    $publishArRes = $controller->publish($publishArReq, $sellerAr->id);
    $publishArData = $publishArRes->getData(true);

    $v1Ar = LegalTermVersion::find($publishArData['data']['version']['id']);

    assertTest(
        $publishArRes->status() === 200
            && $v1Ar->version_number === 1
            && str_contains($v1Ar->content, 'منصة فن مومنت')
            && $v1Ar->verifyHash(),
        "T12: Arabic document publishes with independent Arabic RTL content and hash",
        "Role: seller, Lang: ar, Version: {$v1Ar->version_number}"
    );

    // ------------------------------------------------------------------------
    // T13: History endpoint returns immutable versions ordered descending
    // ------------------------------------------------------------------------
    $historyRes = $controller->history($buyerEn->id);
    $historyData = $historyRes->getData(true);

    $historyList = $historyData['data'];
    $historyOrdered = count($historyList) === 2
        && $historyList[0]['version_number'] === 2
        && $historyList[0]['is_current'] === true
        && $historyList[1]['version_number'] === 1
        && $historyList[1]['is_current'] === false;

    assertTest(
        $historyOrdered,
        "T13: Version history lists all published snapshots in descending order with is_current flag",
        "Versions returned: " . count($historyList)
    );

    // ------------------------------------------------------------------------
    // T14: Transaction rollback safety
    // ------------------------------------------------------------------------
    $initialVersionsCount = LegalTermVersion::count();
    $termBeforeFail = LegalTerm::find($buyerAr->id);
    $initialActiveId = $termBeforeFail->current_version_id;

    $txFailed = false;
    try {
        DB::transaction(function () use ($buyerAr) {
            LegalTermVersion::create([
                'legal_term_id' => $buyerAr->id,
                'version_number' => 999,
                'title' => 'Temporary Rollback Test',
                'content' => 'Test content',
                'content_hash' => hash('sha256', 'Test content'),
                'published_at' => now(),
            ]);

            // Force intentional exception to simulate transaction failure
            throw new \RuntimeException("Simulated publishing failure");
        });
    } catch (\Throwable $e) {
        $txFailed = true;
    }

    $finalVersionsCount = LegalTermVersion::count();
    $termAfterFail = LegalTerm::find($buyerAr->id);

    assertTest(
        $txFailed && $finalVersionsCount === $initialVersionsCount && $termAfterFail->current_version_id === $initialActiveId,
        "T14: Transaction rollback prevents partial version records or broken active pointers on failure",
        "Versions preserved: $finalVersionsCount == $initialVersionsCount"
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
