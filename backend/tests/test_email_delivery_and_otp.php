<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Mail\BasicMail;
use App\Mail\Transport\ResendTransport;
use App\Services\Auth\OtpVerificationService;
use App\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\SentMessage;

$passed = 0;
$failed = 0;

function assertCheck($condition, $description) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $description" . PHP_EOL;
        $passed++;
    } else {
        echo "  [FAIL] $description" . PHP_EOL;
        $failed++;
    }
}

echo "==================================================================" . PHP_EOL;
echo " WORKSTREAM A: EMAIL DELIVERY & OTP TEST SUITE" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST GROUP 1: BasicMail From Address & Name Resolution (Config-Safe)
// -------------------------------------------------------------------------
echo PHP_EOL . "--- TEST GROUP 1: BasicMail Resolution ---" . PHP_EOL;

config(['mail.from.address' => 'sender@funmoments.sa']);
config(['mail.from.name' => 'Fun Moments Platform']);

$mailable = new BasicMail([
    'subject' => 'Verification Code',
    'message' => 'Your code is 1234',
]);
$mailable->build();

assertCheck($mailable->hasFrom('sender@funmoments.sa', 'Fun Moments Platform'), 'BasicMail resolves from address and name from Laravel config');
assertCheck($mailable->subject === 'Verification Code', 'BasicMail correctly sets email subject');

// Test fallback when config is default 'hello@example.com'
config(['mail.from.address' => 'hello@example.com']);
config(['mail.from.name' => 'Example']);
$mailableFallback = new BasicMail([
    'subject' => 'Test Subject',
    'message' => 'Test Message',
]);
$mailableFallback->build();
assertCheck($mailableFallback->hasFrom('no-reply@funmoments.sa'), 'BasicMail falls back to no-reply@funmoments.sa when config is hello@example.com');

// -------------------------------------------------------------------------
// TEST GROUP 2: ResendTransport Safety, Payload, and Error Diagnostics
// -------------------------------------------------------------------------
echo PHP_EOL . "--- TEST GROUP 2: ResendTransport ---" . PHP_EOL;

// 2.1: Empty API key throws TransportException fail-closed
$emptyKeyThrown = false;
try {
    $transport = new ResendTransport('');
    $email = (new Email())
        ->from('test@funmoments.sa')
        ->to('buyer@example.com')
        ->subject('Test')
        ->text('Test Body');
    $transport->send($email);
} catch (TransportException $e) {
    $emptyKeyThrown = true;
    assertCheck(str_contains($e->getMessage(), 'RESEND_API_KEY in environment'), 'ResendTransport throws TransportException when API key is empty');
}
assertCheck($emptyKeyThrown, 'Empty Resend API key fails closed immediately');

// 2.2: Mocked Guzzle 200 Success Response
$mock = new MockHandler([
    new Response(200, [], json_encode(['id' => 'resend_email_12345'])),
]);
$handlerStack = HandlerStack::create($mock);
$client = new Client(['handler' => $handlerStack]);

$transport = new ResendTransport('re_test_key_valid', $client);
$email = (new Email())
    ->from('Fun Moments <info@funmoments.sa>')
    ->to('buyer@example.com')
    ->subject('Your OTP Code')
    ->html('<p>Code: 4567</p>')
    ->text('Code: 4567');

$sent = false;
try {
    $transport->send($email);
    $sent = true;
} catch (\Throwable $e) {
    echo "  Send error: " . $e->getMessage() . PHP_EOL;
}
assertCheck($sent, 'ResendTransport successfully sends payload to Resend API endpoint');

// 2.3: Mocked Guzzle 403 Domain Unverified Response with JSON Error Detail
$mockError = new MockHandler([
    new RequestException(
        'Client error: 403 Forbidden',
        new Request('POST', 'https://api.resend.com/emails'),
        new Response(403, ['Content-Type' => 'application/json'], json_encode([
            'statusCode' => 403,
            'name' => 'validation_error',
            'message' => 'The domain funmoments.sa is not verified. Please verify it or use onboarding@resend.dev',
        ]))
    ),
]);
$handlerStackError = HandlerStack::create($mockError);
$clientError = new Client(['handler' => $handlerStackError]);
$transportError = new ResendTransport('re_test_key_unverified', $clientError);

$errorDiagnosticLogged = false;
try {
    $transportError->send($email);
} catch (TransportException $e) {
    $errorDiagnosticLogged = str_contains($e->getMessage(), '403') && str_contains($e->getMessage(), 'domain funmoments.sa is not verified');
    assertCheck($errorDiagnosticLogged, 'ResendTransport captures exact Resend JSON error details in exception');
}

// -------------------------------------------------------------------------
// TEST GROUP 3: OtpVerificationService Complete Lifecycle
// -------------------------------------------------------------------------
echo PHP_EOL . "--- TEST GROUP 3: OtpVerificationService Lifecycle ---" . PHP_EOL;

DB::beginTransaction();

try {
    $testEmail = 'otp_test_user_' . uniqid() . '@example.com';
    $user = User::create([
        'name' => 'OTP Test User',
        'email' => $testEmail,
        'username' => 'otp_user_' . uniqid(),
        'phone' => '+96650' . rand(1000000, 9999999),
        'password' => Hash::make('Password123!'),
        'service_city' => 2,
        'service_area' => 2,
        'country_id' => 2,
        'user_type' => 1,
        'user_status' => 1,
        'email_verified' => 0,
        'terms_condition' => 1,
    ]);

    $otpService = app(OtpVerificationService::class);

    // 3.1: OTP Generation & Dispatch with Mail::fake()
    Mail::fake();
    $result = $otpService->generateAndSendOtp($testEmail);

    assertCheck($result['success'] === true, 'generateAndSendOtp returns success=true');
    assertCheck($result['status_code'] === 200, 'generateAndSendOtp returns HTTP 200');
    assertCheck(!isset($result['otp']), 'generateAndSendOtp does NOT leak plaintext OTP in response');

    $sentMailables = Mail::sent(BasicMail::class, function ($mail) use ($testEmail) {
        return $mail->hasTo($testEmail);
    });
    assertCheck(count($sentMailables) > 0, 'BasicMail was dispatched via Mail facade to recipient');

    // 3.2: 60-Second Cooldown Enforcement
    $retryResult = $otpService->generateAndSendOtp($testEmail);
    assertCheck($retryResult['success'] === false, 'Immediate OTP resend request is rejected');
    assertCheck($retryResult['status_code'] === 429, 'Immediate OTP resend returns HTTP 429');
    assertCheck(isset($retryResult['cooldown_remaining']) && $retryResult['cooldown_remaining'] > 0, 'Response includes cooldown_remaining seconds');

    // 3.3: Database and Cache verification
    $user->refresh();
    assertCheck(!empty($user->otp_code), 'User record has hashed otp_code saved');
    assertCheck(!empty($user->otp_expire_at), 'User record has otp_expire_at timestamp');

    // 3.4: Extract code from dispatched mail and verify success
    $messageBody = $sentMailables[0]->data['message'] ?? '';
    preg_match('/\b\d{4}\b/', $messageBody, $matches);
    $exactCode = $matches[0] ?? null;
    assertCheck(!empty($exactCode), 'Dispatched email contained the generated 4-digit OTP code');

    // 3.5: Invalid OTP Verification
    $wrongCode = ($exactCode === '1111') ? '2222' : '1111';
    $verifyFail = $otpService->verifyOtp($testEmail, $wrongCode);
    assertCheck($verifyFail['success'] === false, 'Invalid OTP code is rejected');
    assertCheck($verifyFail['status_code'] === 422, 'Invalid OTP code returns HTTP 422');
    assertCheck($user->fresh()->email_verified == 0, 'User email_verified remains 0 after invalid attempt');

    // 3.6: Successful OTP Verification with correct code
    $verifySuccess = $otpService->verifyOtp($testEmail, $exactCode);
    assertCheck($verifySuccess['success'] === true, 'Correct OTP code is accepted with success=true');
    assertCheck($verifySuccess['status_code'] === 200, 'Correct OTP code returns HTTP 200');
    assertCheck($user->fresh()->email_verified == 1, 'User email_verified is updated to 1 upon successful verification');
    assertCheck(empty($user->fresh()->otp_code), 'Hashed OTP code is cleared from user record after verification');

    // 3.7: Single-Use Guarantee (cannot reuse consumed OTP)
    $reuseResult = $otpService->verifyOtp($testEmail, $exactCode);
    assertCheck($reuseResult['success'] === false, 'Reusing a consumed OTP code is rejected');
    assertCheck($reuseResult['status_code'] === 422, 'Reused OTP code returns HTTP 422 expired/does not exist');

    // 3.8: Delivery Failure Cooldown Reset
    // Simulate mail transport failure to verify that cooldown is cleared
    Cache::forget("otp_cooldown_{$testEmail}_email_verify");
    Cache::forget("otp_cooldown_{$testEmail}_email_verify_ts");

    // Force Mail facade to throw
    $failingEmail = 'fail_mail_' . uniqid() . '@example.com';
    Mail::shouldReceive('to->send')->andThrow(new \Exception('Connection refused: 127.0.0.1:1025'));

    $failResult = $otpService->generateAndSendOtp($failingEmail);
    assertCheck($failResult['success'] === false, 'Dispatch failure returns success=false');
    assertCheck($failResult['status_code'] === 500, 'Dispatch failure returns HTTP 500');

    // Check that cooldown was cleared so client is not throttled
    $cooldownKey = "otp_cooldown_{$failingEmail}_email_verify";
    assertCheck(!Cache::has($cooldownKey), 'Cooldown is cleared on delivery failure to allow immediate retry');

} finally {
    DB::rollBack();
}

echo PHP_EOL . "==================================================================" . PHP_EOL;
echo " TEST SUMMARY: $passed PASSED, $failed FAILED" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
