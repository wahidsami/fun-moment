<?php

/**
 * FUN MOMENT — SECURE ADMIN CSRF & ROUTE PROTECTION TEST SUITE
 *
 * Verifies that:
 * 1. Admin legal-terms mutation routes are NOT exempted from VerifyCsrfToken.
 * 2. POST requests with missing or invalid CSRF tokens are rejected with TokenMismatchException (HTTP 419).
 * 3. POST requests with a valid X-CSRF-TOKEN header pass verification.
 * 4. Dedicated /admin-home/csrf-token and /admin-home/auth-check endpoints provide the active session CSRF token.
 * 5. Admin authentication and manage_cms permission middlewares remain strictly enforced.
 *
 * Runs using in-memory array session driver — zero database connection required.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLegalTermsController;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route as RouteFacade;

Config::set('session.driver', 'array');

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

echo "==================================================================" . PHP_EOL;
echo " PHASE 2E: SECURE ADMIN CSRF & MIDDLEWARE VERIFICATION" . PHP_EOL;
echo " Session Driver: array (In-Memory) — Zero Persistent Footprint" . PHP_EOL;
echo "==================================================================" . PHP_EOL . PHP_EOL;

try {
    // ------------------------------------------------------------------------
    // T1: VerifyCsrfToken does NOT contain broad exemption for legal-terms
    // ------------------------------------------------------------------------
    $csrfMiddleware = $app->make(VerifyCsrfToken::class);
    $refProperty = new ReflectionProperty($csrfMiddleware, 'except');
    $refProperty->setAccessible(true);
    $exceptList = $refProperty->getValue($csrfMiddleware);

    $isExempted = false;
    foreach ($exceptList as $exceptPattern) {
        if (str_contains($exceptPattern, 'legal-terms')) {
            $isExempted = true;
            break;
        }
    }

    assertTest(
        !$isExempted,
        "T1: VerifyCsrfToken does NOT exempt admin-home/legal-terms routes",
        "Except patterns checked: " . count($exceptList)
    );

    // ------------------------------------------------------------------------
    // Setup test session and tokens
    // ------------------------------------------------------------------------
    $sessionStore = new Store('test_session', new ArraySessionHandler(120));
    $sessionStore->start();
    $activeCsrfToken = $sessionStore->token();

    assertTest(
        !empty($activeCsrfToken) && strlen($activeCsrfToken) >= 40,
        "T2: Session store generates cryptographically secure CSRF token",
        "Token length: " . strlen($activeCsrfToken)
    );

    $nextClosure = function ($req) {
        return new Response('OK', 200);
    };

    // ------------------------------------------------------------------------
    // T3: POST /admin-home/legal-terms/1/draft WITHOUT CSRF token is REJECTED
    // ------------------------------------------------------------------------
    $reqNoToken = Request::create('/admin-home/legal-terms/1/draft', 'POST', [
        'draft_title' => 'Updated Draft Title',
        'draft_content' => '<p>New legal terms body</p>',
    ]);
    $reqNoToken->setLaravelSession($sessionStore);

    $noTokenRejected = false;
    try {
        $csrfMiddleware->handle($reqNoToken, $nextClosure);
    } catch (TokenMismatchException $e) {
        $noTokenRejected = true;
    }

    assertTest(
        $noTokenRejected,
        "T3: POST request without CSRF token is REJECTED (TokenMismatchException / HTTP 419)"
    );

    // ------------------------------------------------------------------------
    // T4: POST /admin-home/legal-terms/1/draft WITH INVALID CSRF token is REJECTED
    // ------------------------------------------------------------------------
    $reqInvalidToken = Request::create('/admin-home/legal-terms/1/draft', 'POST', [
        'draft_title' => 'Updated Draft Title',
        'draft_content' => '<p>New legal terms body</p>',
    ], [], [], [
        'HTTP_X_CSRF_TOKEN' => 'forged_or_invalid_csrf_token_1234567890abcdef',
    ]);
    $reqInvalidToken->setLaravelSession($sessionStore);

    $invalidTokenRejected = false;
    try {
        $csrfMiddleware->handle($reqInvalidToken, $nextClosure);
    } catch (TokenMismatchException $e) {
        $invalidTokenRejected = true;
    }

    assertTest(
        $invalidTokenRejected,
        "T4: POST request with forged/invalid CSRF token is REJECTED (HTTP 419)"
    );

    // ------------------------------------------------------------------------
    // T5: POST /admin-home/legal-terms/1/draft WITH VALID X-CSRF-TOKEN PASSES
    // ------------------------------------------------------------------------
    $reqValidHeader = Request::create('/admin-home/legal-terms/1/draft', 'POST', [
        'draft_title' => 'Updated Draft Title',
        'draft_content' => '<p>New legal terms body</p>',
    ], [], [], [
        'HTTP_X_CSRF_TOKEN' => $activeCsrfToken,
    ]);
    $reqValidHeader->setLaravelSession($sessionStore);

    $validHeaderResponse = $csrfMiddleware->handle($reqValidHeader, $nextClosure);

    assertTest(
        $validHeaderResponse->getStatusCode() === 200,
        "T5: POST request with valid X-CSRF-TOKEN header PASSES CSRF verification",
        "Status: " . $validHeaderResponse->getStatusCode()
    );

    // ------------------------------------------------------------------------
    // T6: POST /admin-home/legal-terms/1/publish WITH VALID X-CSRF-TOKEN PASSES
    // ------------------------------------------------------------------------
    $reqValidPublish = Request::create('/admin-home/legal-terms/1/publish', 'POST', [], [], [], [
        'HTTP_X_CSRF_TOKEN' => $activeCsrfToken,
    ]);
    $reqValidPublish->setLaravelSession($sessionStore);

    $validPublishResponse = $csrfMiddleware->handle($reqValidPublish, $nextClosure);

    assertTest(
        $validPublishResponse->getStatusCode() === 200,
        "T6: POST publish request with valid X-CSRF-TOKEN header PASSES CSRF verification"
    );

    // ------------------------------------------------------------------------
    // T7: GET /admin-home/csrf-token route exists and returns session CSRF token
    // ------------------------------------------------------------------------
    $csrfRoute = RouteFacade::getRoutes()->getByName('admin.csrf.token');

    assertTest(
        $csrfRoute !== null,
        "T7: Route 'admin.csrf.token' is registered in Laravel routing table",
        "URI: " . ($csrfRoute ? $csrfRoute->uri() : 'none')
    );

    // Test AdminDashboardController@csrfToken method execution
    $app->make('session')->start();
    $dashboardController = new AdminDashboardController();
    $csrfJsonResponse = $dashboardController->csrfToken();
    $csrfData = $csrfJsonResponse->getData(true);

    assertTest(
        $csrfJsonResponse->getStatusCode() === 200
        && isset($csrfData['success']) && $csrfData['success'] === true
        && !empty($csrfData['csrf_token']),
        "T8: AdminDashboardController@csrfToken returns valid CSRF token payload",
        "Token: " . substr($csrfData['csrf_token'] ?? '', 0, 16) . '...'
    );

    // ------------------------------------------------------------------------
    // T9: AdminDashboardController@authCheck includes csrf_token
    // ------------------------------------------------------------------------
    $authCheckResponse = $dashboardController->authCheck();
    $authCheckData = $authCheckResponse->getData(true);

    assertTest(
        $authCheckResponse->getStatusCode() === 200
        && !empty($authCheckData['csrf_token'])
        && !empty($authCheckData['data']['csrf_token']),
        "T9: AdminDashboardController@authCheck embeds csrf_token in payload for instant bootstrap",
        "Root token present: " . (!empty($authCheckData['csrf_token']) ? 'yes' : 'no')
    );

    // ------------------------------------------------------------------------
    // T10: Verify route middleware stack for legal-terms mutations
    // ------------------------------------------------------------------------
    $draftRoute = RouteFacade::getRoutes()->getByName('admin.legal.terms.draft');
    $publishRoute = RouteFacade::getRoutes()->getByName('admin.legal.terms.publish');

    $draftMiddleware = $draftRoute ? $draftRoute->gatherMiddleware() : [];
    $publishMiddleware = $publishRoute ? $publishRoute->gatherMiddleware() : [];

    assertTest(
        in_array('web', $draftMiddleware) && in_array('auth:admin', $draftMiddleware)
        && in_array('web', $publishMiddleware) && in_array('auth:admin', $publishMiddleware),
        "T10: Legal terms mutation routes enforce 'web' (CSRF) and 'auth:admin' (authentication)",
        "Middlewares: " . implode(', ', $draftMiddleware)
    );

    // ------------------------------------------------------------------------
    // T11: Verify AdminLegalTermsController permission middleware
    // ------------------------------------------------------------------------
    $termsController = new AdminLegalTermsController();
    $controllerMiddleware = $termsController->getMiddleware();

    $hasAuthAdmin = false;
    $hasManageCms = false;
    foreach ($controllerMiddleware as $m) {
        if ($m['middleware'] === 'auth:admin') $hasAuthAdmin = true;
        if ($m['middleware'] === 'permission:manage_cms') $hasManageCms = true;
    }

    assertTest(
        $hasAuthAdmin && $hasManageCms,
        "T11: AdminLegalTermsController enforces 'auth:admin' and 'permission:manage_cms'",
        "Controller middlewares verified"
    );

} catch (\Throwable $e) {
    echo " [FATAL ERROR]: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failed++;
}

echo PHP_EOL . "==================================================================" . PHP_EOL;
echo " TEST SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
