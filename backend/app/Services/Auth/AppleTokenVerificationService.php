<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AppleTokenVerificationService
{
    public const APPLE_ISSUER = 'https://appleid.apple.com';
    public const DEFAULT_BUNDLE_ID = 'com.sa.funmoments';
    public const JWKS_URL = 'https://appleid.apple.com/auth/keys';
    public const CACHE_KEY = 'apple_auth_jwks_v1';
    public const CACHE_TTL_SECONDS = 86400; // 24 hours

    /**
     * Optional custom key resolver for unit/integration testing.
     * @var (callable(): array)|null
     */
    protected static $customKeyResolver = null;

    /**
     * Set a custom key resolver (used for automated testing without outbound network calls).
     *
     * @param (callable(): array)|null $resolver
     */
    public static function setKeyResolver(?callable $resolver): void
    {
        self::$customKeyResolver = $resolver;
    }

    /**
     * Verify an Apple identity token (JWT) returned by Sign in with Apple.
     *
     * @param string|null $identityToken The JWT string returned by Apple
     * @param string|null $expectedSubject The expected Apple userIdentifier ('sub')
     * @return object|null Decoded JWT payload object on success, or null on verification failure
     */
    public function verifyToken(?string $identityToken, ?string $expectedSubject = null): ?object
    {
        if (empty($identityToken)) {
            Log::warning('[AppleTokenVerificationService] Identity token is empty.');
            return null;
        }

        try {
            $keys = $this->getApplePublicKeys();
            if (empty($keys)) {
                Log::error('[AppleTokenVerificationService] No public keys available to verify Apple token.');
                return null;
            }

            // Decode and verify cryptographic signature, expiration, and nbf
            $payload = JWT::decode($identityToken, $keys);

            // Verify issuer
            if (!isset($payload->iss) || $payload->iss !== self::APPLE_ISSUER) {
                Log::warning('[AppleTokenVerificationService] Invalid issuer in Apple token: ' . ($payload->iss ?? 'null'));
                return null;
            }

            // Verify audience against configured bundle ID / client ID
            $expectedAudience = config('services.apple.client_id', self::DEFAULT_BUNDLE_ID);
            if (!isset($payload->aud) || $payload->aud !== $expectedAudience) {
                Log::warning('[AppleTokenVerificationService] Invalid audience in Apple token. Expected ' . $expectedAudience . ', got ' . ($payload->aud ?? 'null'));
                return null;
            }

            // Verify subject matches expected user identifier if provided
            if (!empty($expectedSubject)) {
                if (!isset($payload->sub) || $payload->sub !== $expectedSubject) {
                    Log::warning('[AppleTokenVerificationService] Subject mismatch in Apple token. Expected ' . $expectedSubject . ', got ' . ($payload->sub ?? 'null'));
                    return null;
                }
            }

            return $payload;
        } catch (Throwable $e) {
            Log::warning('[AppleTokenVerificationService] Token verification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve parsed Apple public JWKs (cached for 24 hours).
     *
     * @return array
     */
    protected function getApplePublicKeys(): array
    {
        if (self::$customKeyResolver !== null) {
            return (self::$customKeyResolver)();
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            try {
                $response = Http::timeout(5)->get(self::JWKS_URL);
                if ($response->successful()) {
                    $jwks = $response->json();
                    if (is_array($jwks) && isset($jwks['keys'])) {
                        return JWK::parseKeySet($jwks);
                    }
                }
            } catch (Throwable $e) {
                Log::error('[AppleTokenVerificationService] Failed to fetch Apple JWKs: ' . $e->getMessage());
            }

            // Fallback: try raw curl/file_get_contents if Http facade had issues
            try {
                $raw = @file_get_contents(self::JWKS_URL);
                if ($raw) {
                    $jwks = json_decode($raw, true);
                    if (is_array($jwks) && isset($jwks['keys'])) {
                        return JWK::parseKeySet($jwks);
                    }
                }
            } catch (Throwable $e) {
                Log::error('[AppleTokenVerificationService] Fallback JWK fetch failed: ' . $e->getMessage());
            }

            return [];
        });
    }
}
