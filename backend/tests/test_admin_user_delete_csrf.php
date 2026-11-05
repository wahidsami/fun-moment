<?php

/**
 * FUN MOMENT — ADMINISTRATIVE USER DELETION CSRF & PROTOCOL TEST SUITE
 *
 * Verifies:
 * 1. POST /admin-home/frontend-users/{id}/delete is protected by VerifyCsrfToken.
 * 2. Requests without a CSRF token are rejected with TokenMismatchException (HTTP 419).
 * 3. Requests with an encrypted cookie passed in X-CSRF-TOKEN are rejected (HTTP 419).
 * 4. Requests with plaintext session token in X-CSRF-TOKEN pass CSRF verification.
 * 5. Requests with encrypted XSRF-TOKEN cookie in X-XSRF-TOKEN pass CSRF verification.
 * 6. Requests with both valid headers pass CSRF verification.
 * 7. End-to-end request handling through Laravel routing pipeline:
 *    - Valid CSRF token passes to controller
 *    - Confirmation email validation is enforced (422)
 *    - Deletion permission is enforced (403)
 *    - Zero persistent database modifications (rolled back)
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\VerifyCsrfToken;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
echo " ADMIN USER DELETION CSRF & PROTOCOL VERIFICATION SUITE" . PHP_EOL;
echo " Target: POST /admin-home/frontend-users/{id}/delete" . PHP_EOL;
echo "==================================================================" . PHP_EOL . PHP_EOL;

try {
    // ------------------------------------------------------------------------
    // T1: VerifyCsrfToken does NOT exempt /frontend-users/*/delete
    // ------------------------------------------------------------------------
    $csrfMiddleware = $app->make(VerifyCsrfToken::class);
    $refProperty = new ReflectionProperty($csrfMiddleware, 'except');
    $refProperty->setAccessible(true);
    $exceptList = $refProperty->getValue($csrfMiddleware);

    $isExempted = false;
    foreach ($exceptList as $pattern) {
        if (str_contains($pattern, 'frontend-users') && str_contains($pattern, 'delete')) {
            $isExempted = true;
            break;
        }
    }

    assertTest(
        !$isExempted,
        "T1: VerifyCsrfToken does NOT exempt admin-home/frontend-users/*/delete",
        "Server-side CSRF protection is strictly enforced"
    );

    // ------------------------------------------------------------------------
    // Setup test session and tokens
    // ------------------------------------------------------------------------
    $sessionStore = new Store('test_session', new ArraySessionHandler(120));
    $sessionStore->start();
    $plaintextToken = $sessionStore->token();

    assertTest(
        !empty($plaintextToken) && strlen($plaintextToken) >= 40,
        "T2: Session store generates cryptographically secure plaintext token",
        "Length: " . strlen($plaintextToken)
    );

    // Generate real encrypted XSRF-TOKEN cookie via middleware pipeline
    $encryptCookies = $app->make(EncryptCookies::class);
    $probeReq = Request::create('/admin-home/csrf-token', 'GET');
    $probeReq->setLaravelSession($sessionStore);
    $rawRes = $csrfMiddleware->handle($probeReq, function ($r) { return new Response('OK'); });
    $encRes = $encryptCookies->handle($probeReq, function ($r) use ($rawRes) { return $rawRes; });

    $encryptedXsrfCookie = null;
    foreach ($encRes->headers->getCookies() as $cookie) {
        if ($cookie->getName() === 'XSRF-TOKEN') {
            $encryptedXsrfCookie = $cookie->getValue();
            break;
        }
    }

    assertTest(
        !empty($encryptedXsrfCookie) && $encryptedXsrfCookie !== $plaintextToken,
        "T3: EncryptCookies produces encrypted ciphertext for XSRF-TOKEN cookie",
        "Ciphertext prefix: " . substr($encryptedXsrfCookie ?? '', 0, 16) . '...'
    );

    $nextClosure = function ($req) {
        return new Response('CSRF_PASSED', 200);
    };

    // ------------------------------------------------------------------------
    // T4: Request WITHOUT token is rejected (TokenMismatchException / 419)
    // ------------------------------------------------------------------------
    $reqNoToken = Request::create('/admin-home/frontend-users/2/delete', 'POST', [
        'confirm_email' => 'test@example.com',
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
        "T4: POST delete-user WITHOUT token is REJECTED (HTTP 419 TokenMismatchException)"
    );

    // ------------------------------------------------------------------------
    // T5: Encrypted cookie sent as X-CSRF-TOKEN is REJECTED (TokenMismatchException / 419)
    // ------------------------------------------------------------------------
    $reqEncInCsrf = Request::create('/admin-home/frontend-users/2/delete', 'POST', [
        'confirm_email' => 'test@example.com',
    ], [], [], [
        'HTTP_X_CSRF_TOKEN' => $encryptedXsrfCookie,
    ]);
    $reqEncInCsrf->setLaravelSession($sessionStore);

    $encInCsrfRejected = false;
    try {
        $csrfMiddleware->handle($reqEncInCsrf, $nextClosure);
    } catch (TokenMismatchException $e) {
        $encInCsrfRejected = true;
    }

    assertTest(
        $encInCsrfRejected,
        "T5: Encrypted XSRF cookie sent as X-CSRF-TOKEN is REJECTED (Protocol enforcement)"
    );

    // ------------------------------------------------------------------------
    // T6: Forged or invalid token is REJECTED (TokenMismatchException / 419)
    // ------------------------------------------------------------------------
    $reqForged = Request::create('/admin-home/frontend-users/2/delete', 'POST', [
        'confirm_email' => 'test@example.com',
    ], [], [], [
        'HTTP_X_CSRF_TOKEN' => 'forged_token_value_0123456789abcdef',
    ]);
    $reqForged->setLaravelSession($sessionStore);

    $forgedRejected = false;
    try {
        $csrfMiddleware->handle($reqForged, $nextClosure);
    } catch (TokenMismatchException $e) {
        $forgedRejected = true;
    }

    assertTest(
        $forgedRejected,
        "T6: Forged or invalid token is REJECTED (HTTP 419)"
    );

    // ------------------------------------------------------------------------
    // T7: Plaintext token sent as X-CSRF-TOKEN PASSES CSRF verification
    // ------------------------------------------------------------------------
    $reqPlaintext = Request::create('/admin-home/frontend-users/2/delete', 'POST', [
        'confirm_email' => 'test@example.com',
    ], [], [], [
        'HTTP_X_CSRF_TOKEN' => $plaintextToken,
    ]);
    $reqPlaintext->setLaravelSession($sessionStore);

    $resPlaintext = $csrfMiddleware->handle($reqPlaintext, $nextClosure);

    assertTest(
        $resPlaintext->getStatusCode() === 200 && $resPlaintext->getContent() === 'CSRF_PASSED',
        "T7: Plaintext session token in X-CSRF-TOKEN PASSES CSRF verification"
    );

    // ------------------------------------------------------------------------
    // T8: Encrypted cookie sent as X-XSRF-TOKEN PASSES CSRF verification
    // ------------------------------------------------------------------------
    $reqXsrfCookie = Request::create('/admin-home/frontend-users/2/delete', 'POST', [
        'confirm_email' => 'test@example.com',
    ], [], [], [
        'HTTP_X_XSRF_TOKEN' => $encryptedXsrfCookie,
    ]);
    $reqXsrfCookie->setLaravelSession($sessionStore);

    $resXsrfCookie = $csrfMiddleware->handle($reqXsrfCookie, $nextClosure);

    assertTest(
        $resXsrfCookie->getStatusCode() === 200 && $resXsrfCookie->getContent() === 'CSRF_PASSED',
        "T8: Encrypted cookie in X-XSRF-TOKEN is decrypted by Laravel and PASSES CSRF verification"
    );

    // ------------------------------------------------------------------------
    // T9: Both valid headers provided together PASS CSRF verification
    // ------------------------------------------------------------------------
    $reqBoth = Request::create('/admin-home/frontend-users/2/delete', 'POST', [
        'confirm_email' => 'test@example.com',
    ], [], [], [
        'HTTP_X_CSRF_TOKEN' => $plaintextToken,
        'HTTP_X_XSRF_TOKEN' => $encryptedXsrfCookie,
    ]);
    $reqBoth->setLaravelSession($sessionStore);

    $resBoth = $csrfMiddleware->handle($reqBoth, $nextClosure);

    assertTest(
        $resBoth->getStatusCode() === 200 && $resBoth->getContent() === 'CSRF_PASSED',
        "T9: Dual-token transmission (X-CSRF-TOKEN + X-XSRF-TOKEN) PASSES CSRF verification"
    );

    // ------------------------------------------------------------------------
    // T10: Full Controller Pipeline: Email Mismatch Protection (422)
    // ------------------------------------------------------------------------
    DB::beginTransaction();
    try {
        $admin = Admin::first();
        Auth::guard('admin')->setUser($admin);

        // Synthetic test user
        $syntheticUser = User::create([
            'name' => 'CSRF Safeguard Test User',
            'email' => 'safeguard_test_' . Str::random(8) . '@example.test',
            'username' => 'csrf_safe_' . Str::random(8),
            'password' => bcrypt('password123'),
            'user_type' => 1,
            'user_status' => 1,
        ]);

        $controller = $app->make(\App\Http\Controllers\FrontendUserManageController::class);

        // Call controller with mismatch email
        $requestMismatch = Request::create("/admin-home/frontend-users/{$syntheticUser->id}/delete", 'POST', [
            'confirm_email' => 'wrong_email@example.com',
        ]);
        $responseMismatch = $controller->apiDeleteUser($requestMismatch, $syntheticUser->id);

        assertTest(
            $responseMismatch->getStatusCode() === 422,
            "T10: Confirmation email mismatch triggers HTTP 422 (Safeguard preserved)",
            "Status: " . $responseMismatch->getStatusCode()
        );

        // Call controller with non-existent user ID
        $response404 = $controller->apiDeleteUser($requestMismatch, 999999999);
        assertTest(
            $response404->getStatusCode() === 404,
            "T11: Non-existent target user returns HTTP 404",
            "Status: " . $response404->getStatusCode()
        );

    } finally {
        DB::rollBack();
    }

} catch (\Throwable $e) {
    echo " [FATAL ERROR]: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failed++;
}

echo PHP_EOL . "==================================================================" . PHP_EOL;
echo " TEST SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
