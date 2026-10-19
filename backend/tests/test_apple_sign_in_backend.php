<?php

/**
 * FUN MOMENT — APPLE SIGN-IN BACKEND REGRESSION TEST SUITE
 *
 * Verifies Apple identity token verification, returning user login (email: null),
 * new registration terms enforcement, and safe account linking.
 * Runs completely inside DB transactions with rollback to guarantee no dirty data.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\UserController;
use App\LegalTerm;
use App\LegalTermVersion;
use App\Services\Auth\AppleTokenVerificationService;
use App\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "==================================================================" . PHP_EOL;
echo "  FUN MOMENT — APPLE SIGN-IN BACKEND REGRESSION SUITE" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

$privateKey = <<<'EOD'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC/s5OgJzSJfB4Q
4P+IZ2amiU0St8Ce2GBsHQzrvmoFAqmuDl46+WixPnvmIuFYhSlydxuV7ckc4JPy
QeW+d5hRNbvrDTAUWwF8KI1AG/ddbCB2TdajN+zVvQAwobAyZeI4C1oF2PN/sdFp
zcIo4e2tG7GLNgAvSCt3Hyqw8+Ix49NrVKvIpcPnqIOl3FAUiZsshLhuo8WjWvHx
Jes0xYYfat/mM1IrEA5qlY/so87thYlv3CpoGhwcz3yPfboXFAgDogi7bgzjTFCK
/8489SBCSxzi9rk2g2Mpu/AmMnA4vyOehNgcoYgzF6+5zEIayOLNhrz5jlhGFF8N
qGMEA5tLAgMBAAECggEARe+YitPiucmbGmNLNhMrAtSSVqLM1eZwfuC2RwbnnL5U
8tfxDqjQxeDayKGu/mFONqE3YiImva88qiPaUftFiVMGJIFiKfrtdsNAjMPnIaEB
0scg59jOQUe7WwKRykEbH4EcrYdxhgN0N5pGHC4YTJAO3I+tz5IPsVygcX/35GTs
cQmhEwXfo1URXmYL7w4v65VG8kzpMTMZxVG95FgU8m+rd78APvKNAwfmMiYH0HTP
vTxDh6K3oz8v/0d4juYVBph1JW1ER6JqPcQPtfwkOve1PkohKUQ6DpHoImtjxQCD
+Xauvo/PU6llzS5SCAR5S7cOyGZkWfqQkOMtLbEMCQKBgQDpQVHVgdzR13ln+GlC
u6jkQkTzddVZmkxzjsC3JEsrJ4YBb7134DFLoqN61odv9uqv6YQ5btcd1mH3YP5h
HHdBPO1zOlPgiaFQJUBGvR17kj8NQxV2uVvpLIiQ/vPVw98eVpJM0VJC8R7sUmB/
+q/rxHMa2rntp9DC2xyN2tiJfQKBgQDSZPbrK4vVBd2IXKtbq3NvuZJezAezWvQO
yesbLgUC5UG78CS2kBhFIAOLIiovCEldhgA4HSObesRMzYnEedcJFvj8GkEpWnEx
wYnOS5pqt6Zh8Seff6fSL94qm/gzeEmjKO6iPLsrOSmgVN0q2XpKaTEomUlwV7b/
GzfE61eSZwKBgQC+Uj/VeCqXRbuX7AbnHnvGE5RzGjhE7jNnLplsVb7HrW4YCfaM
s29FiaTee+DyqIQBmQX8XsZDYEN7N7cXwcREhNQyaus5kNMAtARoM7K0uzWbOptu
zfuCnIWp2X1XdIS/GmAxMbJvUJKsvOJkSkX0l8xboxCGK6GY/HOmA2UfeQKBgAng
zasK91zAEr+lQjJioV/cgZ/LyozEN3M+TbiP95JEO0rcooyjxU/xidkNeJeOtwsa
EIaR76sMH41PlSlRYyQ9HOGRRf/wDCJ+6L5z1EMwbjJpBSg68SEwOCkd+5BHFIYB
lPqvCUalP2wUr/GkFlbKC5HRaJ5Kr//YQk0Pk6P/AoGBAMJAcuV/1vq1cwu1LM8S
KCcFqNBK7tChJhxbJvXxK2xHHfKsYvTASgBBqcTUQiwv6M4tf1+bGQ3EZOL9ZvaK
OPtqlnr6xLg2Vc5j62Gc5S4FIqxNcT0krFr3xqHsLbbLpkxQ46SQJq97ODKYH0ZP
ttFt6Hl5oVftXzc9XQFWQZ/j
-----END PRIVATE KEY-----
EOD;

$publicKey = <<<'EOD'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAv7OToCc0iXweEOD/iGdm
polNErfAnthgbB0M675qBQKprg5eOvlosT575iLhWIUpcncble3JHOCT8kHlvneY
UTW76w0wFFsBfCiNQBv3XWwgdk3Wozfs1b0AMKGwMmXiOAtaBdjzf7HRac3CKOHt
rRuxizYAL0grdx8qsPPiMePTa1SryKXD56iDpdxQFImbLIS4bqPFo1rx8SXrNMWG
H2rf5jNSKxAOapWP7KPO7YWJb9wqaBocHM98j326FxQIA6IIu24M40xQiv/OPPUg
Qksc4va5NoNjKbvwJjJwOL8jnoTYHKGIMxevucxCGsjizYa8+Y5YRhRfDahjBAOb
SwIDAQAB
-----END PUBLIC KEY-----
EOD;

$testKid = 'test-apple-key-1';
$customKeys = [
    $testKid => new \Firebase\JWT\Key($publicKey, 'RS256'),
];

AppleTokenVerificationService::setKeyResolver(function () use ($customKeys) {
    return $customKeys;
});

function generateTestAppleToken(string $sub, ?string $email = null, bool $verified = true, ?string $aud = 'com.sa.funmoments', ?string $iss = 'https://appleid.apple.com'): string {
    global $privateKey, $testKid;
    $payload = [
        'iss' => $iss,
        'aud' => $aud,
        'exp' => time() + 3600,
        'iat' => time(),
        'sub' => $sub,
    ];
    if ($email !== null) {
        $payload['email'] = $email;
        $payload['email_verified'] = $verified ? 'true' : 'false';
    }
    return JWT::encode($payload, $privateKey, 'RS256', $testKid);
}

$passed = 0;
$failed = 0;

function assertCondition(bool $condition, string $testName, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] $testName" . ($detail ? " ($detail)" : '') . PHP_EOL;
    } else {
        $failed++;
        echo " [FAIL] $testName" . ($detail ? " - $detail" : '') . PHP_EOL;
    }
}

DB::beginTransaction();

try {
    $controller = new UserController();

    // 1. Setup a test returning Apple user
    $returningAppleId = 'apple.sub.' . Str::random(10);
    $returningUser = User::create([
        'name' => 'Existing Apple Customer',
        'email' => 'apple_existing_' . Str::random(6) . '@privaterelay.appleid.com',
        'username' => 'ap_exist_' . Str::random(5),
        'password' => Hash::make(Str::random(16)),
        'user_type' => 1,
        'terms_condition' => 1,
        'email_verified' => 1,
        'apple_id' => $returningAppleId,
    ]);

    // TEST 1: Returning Apple user with credential.email = null succeeds
    $returningToken = generateTestAppleToken($returningAppleId);
    $req1 = Request::create('/api/v1/social/login', 'POST', [
        'isApple' => 1,
        'id' => $returningAppleId,
        'identity_token' => $returningToken,
        'email' => null, // Explicitly null from Apple SDK!
    ]);
    $res1 = $controller->socialLogin($req1);
    $data1 = json_decode($res1->getContent(), true);

    assertCondition(
        in_array($res1->getStatusCode(), [200, 201], true) && !empty($data1['token']) && (int) ($data1['users']['id'] ?? 0) === (int) $returningUser->id,
        'TEST 1: Returning Apple user with email=null authenticates via verified apple_id',
        'Token issued, user ID: ' . ($data1['users']['id'] ?? 'null')
    );

    // TEST 2: Missing Apple identity token returns 422
    $req2 = Request::create('/api/v1/social/login', 'POST', [
        'isApple' => 1,
        'id' => $returningAppleId,
        // missing identity_token
    ]);
    $res2 = $controller->socialLogin($req2);
    assertCondition(
        $res2->getStatusCode() === 422,
        'TEST 2: Missing Apple identity token returns HTTP 422 validation error'
    );

    // TEST 3: Invalid / Tampered Apple identity token returns 401
    $tamperedToken = generateTestAppleToken($returningAppleId, null, true, 'wrong.bundle.id');
    $req3 = Request::create('/api/v1/social/login', 'POST', [
        'isApple' => 1,
        'id' => $returningAppleId,
        'identity_token' => $tamperedToken,
    ]);
    $res3 = $controller->socialLogin($req3);
    assertCondition(
        $res3->getStatusCode() === 401,
        'TEST 3: Untrusted/tampered Apple identity token (wrong audience) returns HTTP 401'
    );

    // TEST 4: Identity token subject mismatch with client id returns 401
    $mismatchToken = generateTestAppleToken('different.apple.sub');
    $req4 = Request::create('/api/v1/social/login', 'POST', [
        'isApple' => 1,
        'id' => $returningAppleId,
        'identity_token' => $mismatchToken,
    ]);
    $res4 = $controller->socialLogin($req4);
    assertCondition(
        $res4->getStatusCode() === 401,
        'TEST 4: Subject mismatch between client id and token sub returns HTTP 401'
    );

    // TEST 5: First-time Apple user without terms acceptance returns 422
    $newAppleSub = 'apple.new.' . Str::random(10);
    $newAppleEmail = 'apple_new_' . Str::random(6) . '@privaterelay.appleid.com';
    $newToken = generateTestAppleToken($newAppleSub, $newAppleEmail);

    $req5 = Request::create('/api/v1/social/login', 'POST', [
        'isApple' => 1,
        'id' => $newAppleSub,
        'identity_token' => $newToken,
        'email' => $newAppleEmail,
        // no terms_version_id
    ]);
    $res5 = null;
    try {
        $res5 = $controller->socialLogin($req5);
    } catch (\Illuminate\Validation\ValidationException $e) {
        $res5 = response()->json(['errors' => $e->errors()], 422);
    }
    assertCondition(
        $res5 && $res5->getStatusCode() === 422,
        'TEST 5: First-time Apple registration without terms returns HTTP 422'
    );

    // TEST 6: First-time Apple user with published terms creates user defaulting to buyer (user_type=1)
    $publishedTerm = LegalTerm::where('role', LegalTerm::ROLE_BUYER)->where('language', LegalTerm::LANG_EN)->first();
    if (!$publishedTerm) {
        $publishedTerm = LegalTerm::create([
            'role' => LegalTerm::ROLE_BUYER,
            'language' => LegalTerm::LANG_EN,
            'title' => 'Buyer Terms',
        ]);
    }
    $publishedVersion = null;
    if ($publishedTerm->current_version_id) {
        $publishedVersion = LegalTermVersion::find($publishedTerm->current_version_id);
    }
    if (!$publishedVersion) {
        $nextVersionNum = (int) (LegalTermVersion::where('legal_term_id', $publishedTerm->id)->max('version_number') ?? 0) + 1;
        $publishedVersion = LegalTermVersion::create([
            'legal_term_id' => $publishedTerm->id,
            'version_number' => $nextVersionNum,
            'title' => 'Buyer Terms v' . $nextVersionNum,
            'content' => 'Sample content',
            'content_hash' => hash('sha256', 'Sample content'),
            'published_at' => now(),
        ]);
        $publishedTerm->current_version_id = $publishedVersion->id;
        $publishedTerm->save();
    }

    $req6 = Request::create('/api/v1/social/login', 'POST', [
        'isApple' => 1,
        'id' => $newAppleSub,
        'identity_token' => $newToken,
        'email' => $newAppleEmail,
        'displayName' => 'Fresh Apple Buyer',
        'terms_version_id' => $publishedVersion->id,
        'terms_language' => 'en',
    ]);
    $res6 = $controller->socialLogin($req6);
    $data6 = json_decode($res6->getContent(), true);

    assertCondition(
        in_array($res6->getStatusCode(), [200, 201], true)
        && !empty($data6['token'])
        && (int) ($data6['users']['user_type'] ?? 0) === 1
        && ($data6['users']['apple_id'] ?? '') === $newAppleSub,
        'TEST 6: First-time Apple registration with accepted terms creates user_type=1 Buyer',
        'New user ID: ' . ($data6['users']['id'] ?? 'null')
    );

    // TEST 7: Safe account linking: matches verified email in Apple token
    $existingPassEmail = 'password_user_' . Str::random(6) . '@example.com';
    $existingPassUser = User::create([
        'name' => 'Existing Password User',
        'email' => $existingPassEmail,
        'username' => 'pw_user_' . Str::random(5),
        'password' => Hash::make('password123'),
        'user_type' => 1,
        'email_verified' => 1,
    ]);

    $linkAppleSub = 'apple.link.' . Str::random(10);
    $linkToken = generateTestAppleToken($linkAppleSub, $existingPassEmail, true);
    $req7 = Request::create('/api/v1/social/login', 'POST', [
        'isApple' => 1,
        'id' => $linkAppleSub,
        'identity_token' => $linkToken,
        'email' => $existingPassEmail,
    ]);
    $res7 = $controller->socialLogin($req7);
    $data7 = json_decode($res7->getContent(), true);

    $existingPassUser->refresh();
    assertCondition(
        in_array($res7->getStatusCode(), [200, 201], true)
        && $existingPassUser->apple_id === $linkAppleSub
        && (int) ($data7['users']['id'] ?? 0) === (int) $existingPassUser->id,
        'TEST 7: Cryptographically verified Apple email safely links existing account',
        'apple_id linked: ' . $existingPassUser->apple_id
    );

    // TEST 8: Unsafe account linking rejection: candidate email differs from token verified email
    $unsafeAppleSub = 'apple.attacker.' . Str::random(10);
    $unsafeToken = generateTestAppleToken($unsafeAppleSub, 'attacker@example.com', true);
    $req8 = Request::create('/api/v1/social/login', 'POST', [
        'isApple' => 1,
        'id' => $unsafeAppleSub,
        'identity_token' => $unsafeToken,
        'email' => $returningUser->email, // Attacker tries to claim returningUser's email!
    ]);
    $res8 = $controller->socialLogin($req8);
    assertCondition(
        $res8->getStatusCode() === 403,
        'TEST 8: Unsafe account linking with mismatched email rejected with HTTP 403 Forbidden'
    );

    // TEST 9: Non-Apple provider (Google) still requires email
    $req9 = Request::create('/api/v1/social/login', 'POST', [
        'isGoogle' => 1,
        'id' => 'google.user.123',
        // missing email
    ]);
    $res9 = null;
    try {
        $res9 = $controller->socialLogin($req9);
    } catch (\Illuminate\Validation\ValidationException $e) {
        $res9 = response()->json(['errors' => $e->errors()], 422);
    }
    assertCondition(
        $res9 && $res9->getStatusCode() === 422,
        'TEST 9: Google login still strictly requires email (no regression)'
    );

} finally {
    // Strictly rollback everything so no test users remain in the DB
    DB::rollBack();
    AppleTokenVerificationService::setKeyResolver(null);
}

echo "==================================================================" . PHP_EOL;
echo "  RESULTS: $passed passed, $failed failed." . PHP_EOL;
echo "==================================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
