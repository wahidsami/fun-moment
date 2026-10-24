<?php

/**
 * FUN MOMENT — PHASE 1 AUTHENTICATION SECURITY & OTP LIFECYCLE REGRESSION TEST SUITE
 *
 * Verifies:
 * 1. Password reset requires server-validated reset_token (rejects missing, mismatched, expired, reused tokens).
 * 2. Password reset atomically consumes token and revokes active Sanctum sessions.
 * 3. /send-otp-in-mail never leaks plaintext OTP in response.
 * 4. Server-side OTP verification with correct code, wrong code, and attempt exhaustion (max 5).
 * 5. Resend throttling / cooldown (HTTP 429).
 * 6. Single-use OTP semantics (replay prevention).
 * 7. Insecure legacy /user/send-otp-in-mail/success rejects client-supplied email_verified without server-verified OTP.
 *
 * All tests execute within isolated DB transactions that rollback completely.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\UserController;
use App\Mail\BasicMail;
use App\Services\Auth\OtpVerificationService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

echo "==================================================================" . PHP_EOL;
echo "  FUN MOMENT — AUTHENTICATION SECURITY & OTP LIFECYCLE TESTS" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, &$passed, &$failed, string $details = '') {
    if ($condition) {
        echo "[PASS] " . $description . PHP_EOL;
        $passed++;
    } else {
        echo "[FAIL] " . $description . PHP_EOL;
        if (!empty($details)) {
            echo "       Details: " . $details . PHP_EOL;
        }
        $failed++;
    }
}

function extractLastSentOtp(string $recipientEmail): ?string {
    $mailables = Mail::sent(BasicMail::class);
    for ($i = count($mailables) - 1; $i >= 0; $i--) {
        $mail = $mailables[$i];
        if ($mail->hasTo($recipientEmail)) {
            $msg = $mail->data['message'] ?? '';
            if (preg_match('/\b\d{4}\b/', $msg, $m)) {
                return $m[0];
            }
        }
    }
    return null;
}

// Disable real email sending during tests
Mail::fake();

$controller = new UserController();
$otpService = new OtpVerificationService();

DB::beginTransaction();

try {
    // Setup isolated test users with lowercase emails
    $testPassword = 'InitialPassword123!';
    $emailA = strtolower('sec_test_a_' . Str::random(8) . '@funmoment.test');
    $emailB = strtolower('sec_test_b_' . Str::random(8) . '@funmoment.test');

    $userA = User::create([
        'name' => 'Security Test User A',
        'email' => $emailA,
        'username' => 'sec_test_a_' . Str::random(8),
        'password' => Hash::make($testPassword),
        'email_verified' => 0,
        'user_type' => 1,
    ]);

    $userB = User::create([
        'name' => 'Security Test User B',
        'email' => $emailB,
        'username' => 'sec_test_b_' . Str::random(8),
        'password' => Hash::make($testPassword),
        'email_verified' => 0,
        'user_type' => 1,
    ]);

    // Give User A an active Sanctum token
    $tokenObj = $userA->createToken('test-device-session');
    $initialTokenCount = $userA->tokens()->count();

    assertTest(
        "Prerequisite: User A created with active Sanctum session",
        $initialTokenCount > 0,
        $passed,
        $failed
    );

    // -------------------------------------------------------------
    // Test 1: POST /api/v1/reset-password WITHOUT reset_token
    // -------------------------------------------------------------
    $req1 = Request::create('/api/v1/reset-password', 'POST', [
        'email' => $userA->email,
        'password' => 'NewHackedPassword123!',
    ]);
    $res1 = $controller->resetPassword($req1);
    $res1Data = json_decode($res1->getContent(), true);

    $userAFresh = $userA->fresh();
    $passwordStillInitial = Hash::check($testPassword, $userAFresh->password);

    assertTest(
        "Scope A.1: Password reset without reset_token is rejected (HTTP 403)",
        $res1->getStatusCode() === 403 && $passwordStillInitial,
        $passed,
        $failed,
        "Status: " . $res1->getStatusCode() . ", Body: " . json_encode($res1Data)
    );

    // -------------------------------------------------------------
    // Test 2: POST /api/v1/reset-password with invalid/bogus reset_token
    // -------------------------------------------------------------
    $req2 = Request::create('/api/v1/reset-password', 'POST', [
        'email' => $userA->email,
        'password' => 'NewHackedPassword123!',
        'reset_token' => 'pwd_reset_bogus_token_1234567890',
    ]);
    $res2 = $controller->resetPassword($req2);
    $passwordStillInitial2 = Hash::check($testPassword, $userA->fresh()->password);

    assertTest(
        "Scope A.2: Password reset with invalid reset_token is rejected (HTTP 403)",
        $res2->getStatusCode() === 403 && $passwordStillInitial2,
        $passed,
        $failed,
        "Status: " . $res2->getStatusCode()
    );

    // -------------------------------------------------------------
    // Test 3: POST /api/v1/reset-password with token bound to DIFFERENT account
    // -------------------------------------------------------------
    $tokenForUserB = $otpService->createPasswordResetToken($userB->email);
    $req3 = Request::create('/api/v1/reset-password', 'POST', [
        'email' => $userA->email, // attempting to use B's token to reset A
        'password' => 'NewHackedPassword123!',
        'reset_token' => $tokenForUserB,
    ]);
    $res3 = $controller->resetPassword($req3);
    $passwordStillInitial3 = Hash::check($testPassword, $userA->fresh()->password);

    assertTest(
        "Scope A.3: Password reset with mismatched account token is rejected (HTTP 403)",
        $res3->getStatusCode() === 403 && $passwordStillInitial3,
        $passed,
        $failed,
        "Status: " . $res3->getStatusCode()
    );

    // -------------------------------------------------------------
    // Test 4: /send-otp-in-mail NEVER leaks OTP in response
    // -------------------------------------------------------------
    $reqOtp = Request::create('/api/v1/send-otp-in-mail', 'POST', [
        'email' => $userA->email,
        'purpose' => 'password_reset',
    ]);
    $resOtp = $controller->sendOTP($reqOtp);
    $resOtpData = json_decode($resOtp->getContent(), true);

    $hasLeakedOtp = isset($resOtpData['otp']) || isset($resOtpData['otp_code']) || isset($resOtpData['code']);

    assertTest(
        "Scope B.1: /send-otp-in-mail response contains NO plaintext OTP code",
        in_array($resOtp->getStatusCode(), [200, 201]) && !$hasLeakedOtp && isset($resOtpData['email']),
        $passed,
        $failed,
        "Status: " . $resOtp->getStatusCode() . ", Response payload: " . json_encode($resOtpData)
    );

    // -------------------------------------------------------------
    // Test 5: Resend throttling (HTTP 429)
    // -------------------------------------------------------------
    $reqThrottled = Request::create('/api/v1/send-otp-in-mail', 'POST', [
        'email' => $userA->email,
        'purpose' => 'password_reset',
    ]);
    $resThrottled = $controller->sendOTP($reqThrottled);

    assertTest(
        "Scope B.2: Rapid OTP resend is rate-limited (HTTP 429 cooldown)",
        $resThrottled->getStatusCode() === 429,
        $passed,
        $failed,
        "Status: " . $resThrottled->getStatusCode() . ", Content: " . $resThrottled->getContent()
    );

    // -------------------------------------------------------------
    // Test 6: Verify reset OTP with WRONG code and check attempt counter
    // -------------------------------------------------------------
    $reqWrongOtp = Request::create('/api/v1/verify-reset-otp', 'POST', [
        'email' => $userA->email,
        'otp' => '0000',
    ]);
    $resWrongOtp = $controller->verifyResetOtp($reqWrongOtp);
    $resWrongOtpData = json_decode($resWrongOtp->getContent(), true);

    assertTest(
        "Scope B.3: Wrong OTP verification is rejected (HTTP 422 with remaining attempts)",
        $resWrongOtp->getStatusCode() === 422 && strpos($resWrongOtpData['message'] ?? '', 'attempts remaining') !== false,
        $passed,
        $failed,
        "Status: " . $resWrongOtp->getStatusCode() . ", Msg: " . ($resWrongOtpData['message'] ?? '')
    );

    // Exhaust attempts (4 more wrong attempts)
    for ($i = 0; $i < 4; $i++) {
        $controller->verifyResetOtp($reqWrongOtp);
    }
    // 6th attempt should report maximum attempts exceeded / invalid
    $resExhausted = $controller->verifyResetOtp($reqWrongOtp);
    assertTest(
        "Scope B.4: Exceeded OTP attempts locks/invalidates verifier",
        in_array($resExhausted->getStatusCode(), [422, 429]) && (
            strpos($resExhausted->getContent(), 'invalidated') !== false ||
            strpos($resExhausted->getContent(), 'expired') !== false ||
            strpos($resExhausted->getContent(), 'incorrect attempts') !== false
        ),
        $passed,
        $failed,
        "Status: " . $resExhausted->getStatusCode() . ", Content: " . $resExhausted->getContent()
    );

    // -------------------------------------------------------------
    // Test 7: Successful OTP verification issues single-use reset_token
    // -------------------------------------------------------------
    // Clear cooldown and verifier for fresh generation
    Cache::forget("otp_cooldown_{$userA->email}_password_reset");
    Cache::forget("otp_{$userA->email}_password_reset");

    // Request fresh OTP via controller
    $resFreshOtp = $controller->sendOTP(Request::create('/api/v1/send-otp-in-mail', 'POST', [
        'email' => $userA->email,
        'purpose' => 'password_reset',
    ]));

    // Extract dispatched OTP code from Mail delivery
    $validOtpCode = extractLastSentOtp($userA->email);

    assertTest(
        "Scope B.5: OTP securely delivered via mail channel (4 digits extracted)",
        !empty($validOtpCode) && strlen($validOtpCode) === 4,
        $passed,
        $failed,
        "Extracted: " . var_export($validOtpCode, true)
    );

    $reqVerifySuccess = Request::create('/api/v1/verify-reset-otp', 'POST', [
        'email' => $userA->email,
        'otp' => $validOtpCode,
    ]);
    $resVerifySuccess = $controller->verifyResetOtp($reqVerifySuccess);
    $verifyData = json_decode($resVerifySuccess->getContent(), true);
    $issuedResetToken = $verifyData['reset_token'] ?? null;

    assertTest(
        "Scope B.6: Server-side OTP verification succeeds and returns reset_token",
        in_array($resVerifySuccess->getStatusCode(), [200, 201]) && !empty($issuedResetToken) && strpos($issuedResetToken, 'pwd_reset_') === 0,
        $passed,
        $failed,
        "Status: " . $resVerifySuccess->getStatusCode() . ", Response: " . json_encode($verifyData)
    );

    // -------------------------------------------------------------
    // Test 8: Single-use OTP semantics (replay prevention)
    // -------------------------------------------------------------
    $resReplayOtp = $controller->verifyResetOtp($reqVerifySuccess);
    assertTest(
        "Scope B.7: Replay of already-consumed OTP is rejected (HTTP 422)",
        $resReplayOtp->getStatusCode() === 422,
        $passed,
        $failed,
        "Status: " . $resReplayOtp->getStatusCode()
    );

    // -------------------------------------------------------------
    // Test 9: Authorized password reset consumes token and revokes Sanctum tokens
    // -------------------------------------------------------------
    $newValidPassword = 'NewlyChangedPassword987!';
    $reqAuthorizedReset = Request::create('/api/v1/reset-password', 'POST', [
        'email' => $userA->email,
        'password' => $newValidPassword,
        'reset_token' => $issuedResetToken,
    ]);
    $resAuthorizedReset = $controller->resetPassword($reqAuthorizedReset);
    $userAAfterReset = $userA->fresh();

    $passwordUpdated = Hash::check($newValidPassword, $userAAfterReset->password);
    $sessionsRevoked = $userAAfterReset->tokens()->count() === 0;

    assertTest(
        "Scope A.4: Authorized reset updates password and atomically revokes all Sanctum sessions",
        in_array($resAuthorizedReset->getStatusCode(), [200, 201]) && $passwordUpdated && $sessionsRevoked,
        $passed,
        $failed,
        "Status: " . $resAuthorizedReset->getStatusCode() . ", Updated: " . ($passwordUpdated ? 'YES' : 'NO') . ", Session tokens left: " . $userAAfterReset->tokens()->count()
    );

    // -------------------------------------------------------------
    // Test 10: Replay of consumed reset_token is rejected
    // -------------------------------------------------------------
    $resReplayToken = $controller->resetPassword($reqAuthorizedReset);
    assertTest(
        "Scope A.5: Single-use reset_token cannot be reused (HTTP 403)",
        $resReplayToken->getStatusCode() === 403,
        $passed,
        $failed,
        "Status: " . $resReplayToken->getStatusCode()
    );

    // -------------------------------------------------------------
    // Test 11: Server-side email verification (/api/v1/verify-email-otp)
    // -------------------------------------------------------------
    assertTest("User B starts with email_verified = 0", (int) $userB->email_verified === 0, $passed, $failed);

    $controller->sendOTP(Request::create('/api/v1/send-otp-in-mail', 'POST', [
        'email' => $userB->email,
        'purpose' => 'email_verify',
    ]));
    $userBOtp = extractLastSentOtp($userB->email);

    $reqVerifyEmail = Request::create('/api/v1/verify-email-otp', 'POST', [
        'email' => $userB->email,
        'otp' => $userBOtp,
    ]);
    $resVerifyEmail = $controller->verifyEmailOtp($reqVerifyEmail);
    $userBAfter = $userB->fresh();

    assertTest(
        "Scope B.8: /verify-email-otp validates OTP and sets email_verified = 1",
        in_array($resVerifyEmail->getStatusCode(), [200, 201]) && (int) $userBAfter->email_verified === 1,
        $passed,
        $failed,
        "Status: " . $resVerifyEmail->getStatusCode() . ", email_verified: " . $userBAfter->email_verified
    );

    // -------------------------------------------------------------
    // Test 12: Insecure legacy endpoint rejects client-supplied email_verified without OTP
    // -------------------------------------------------------------
    $emailC = strtolower('sec_test_c_' . Str::random(8) . '@funmoment.test');
    $userC = User::create([
        'name' => 'Security Test User C',
        'email' => $emailC,
        'username' => 'sec_test_c_' . Str::random(8),
        'password' => Hash::make($testPassword),
        'email_verified' => 0,
        'user_type' => 1,
    ]);

    // Authenticate as User C for the legacy endpoint
    auth('sanctum')->setUser($userC);

    $reqLegacyUnverified = Request::create('/api/v1/user/send-otp-in-mail/success', 'POST', [
        'user_id' => $userC->id,
        'email_verified' => 1, // client trying to self-verify without OTP
    ]);
    $resLegacyUnverified = $controller->sendOTPSuccess($reqLegacyUnverified);

    assertTest(
        "Scope B.9: Legacy /user/send-otp-in-mail/success rejects client flag without server OTP (HTTP 422)",
        $resLegacyUnverified->getStatusCode() === 422 && (int) $userC->fresh()->email_verified === 0,
        $passed,
        $failed,
        "Status: " . $resLegacyUnverified->getStatusCode() . ", email_verified: " . $userC->fresh()->email_verified
    );

} finally {
    DB::rollBack();
    echo "==================================================================" . PHP_EOL;
    echo "  DB Transaction Rolled Back — No persistent data left" . PHP_EOL;
    echo "==================================================================" . PHP_EOL;
}

echo PHP_EOL;
echo "Results: $passed Passed, $failed Failed" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
