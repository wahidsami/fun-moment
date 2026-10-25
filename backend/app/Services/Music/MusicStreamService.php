<?php

namespace App\Services\Music;

use App\MusicEntitlement;
use App\MusicTrack;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MusicStreamService
{
    protected MusicStorageService $storageService;

    public function __construct(MusicStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Resolve the user's playback authorization for a given track.
     *
     * @return array{
     *     authorized: bool,
     *     mode: string, // 'full' | 'preview' | 'denied'
     *     entitlement: ?MusicEntitlement,
     *     reason: string
     * }
     */
    public function resolvePlaybackAccess(?\Illuminate\Contracts\Auth\Authenticatable $user, MusicTrack $track): array
    {
        // 1. Verify media is processed and ready
        if ($track->media_status !== MusicTrack::MEDIA_STATUS_READY) {
            // Only allow seller owner or admin to review pending/processing tracks
            if ($this->isOwnerOrAdmin($user, $track)) {
                return [
                    'authorized' => true,
                    'mode' => 'full',
                    'entitlement' => null,
                    'reason' => 'Owner / Admin preview of processing track',
                ];
            }

            return [
                'authorized' => false,
                'mode' => 'denied',
                'entitlement' => null,
                'reason' => 'Track media is not ready for playback',
            ];
        }

        // 2. Check for active seller/admin bypass
        if ($this->isOwnerOrAdmin($user, $track)) {
            return [
                'authorized' => true,
                'mode' => 'full',
                'entitlement' => null,
                'reason' => 'Authorized as track owner or administrator',
            ];
        }

        // 3. Check for existing active entitlement for authenticated user (Rental or Perpetual Sale)
        if ($user) {
            // Block deactivated or suspended user accounts from streaming
            if (isset($user->user_status) && (int) $user->user_status === 0) {
                return [
                    'authorized' => false,
                    'mode' => 'denied',
                    'entitlement' => null,
                    'reason' => 'User account is deactivated or suspended',
                ];
            }

            $entitlement = MusicEntitlement::query()
                ->where('user_id', $user->id)
                ->where('track_id', $track->id)
                ->where('is_active', true)
                ->whereNull('revoked_at')
                ->first();

            if ($entitlement) {
                // Rental vs Perpetual Sale (expires_at is null for perpetual sale)
                if ($entitlement->expires_at !== null) {
                    // Time-limited rental
                    if ($entitlement->expires_at->isPast()) {
                        // Mark entitlement inactive as expired
                        $entitlement->update(['is_active' => false]);
                    } elseif ($entitlement->starts_at && $entitlement->starts_at->isFuture()) {
                        return [
                            'authorized' => false,
                            'mode' => 'denied',
                            'entitlement' => $entitlement,
                            'reason' => 'Rental period has not started yet',
                        ];
                    } else {
                        // Active valid rental
                        return [
                            'authorized' => true,
                            'mode' => 'full',
                            'entitlement' => $entitlement,
                            'reason' => 'Valid active rental entitlement',
                        ];
                    }
                } else {
                    // Perpetual stream-only entitlement (Sale)
                    return [
                        'authorized' => true,
                        'mode' => 'full',
                        'entitlement' => $entitlement,
                        'reason' => 'Valid perpetual streaming entitlement (Sale)',
                    ];
                }
            }
        }

        // 4. Server-Side Marketplace Availability Enforcement:
        // When the marketplace is disabled, new public access (free streaming and previews) is blocked.
        // Existing paid entitlements (prior buyers and active renters) and owner/admin access are strictly preserved.
        if (!\App\MusicSetting::isMarketplaceEnabled()) {
            return [
                'authorized' => false,
                'mode' => 'denied',
                'entitlement' => null,
                'reason' => 'Music marketplace is currently disabled',
            ];
        }

        // 5. If track is suspended or not approved, unentitled users cannot stream it (even preview)
        if ($track->isSuspended() || !$track->isApproved()) {
            return [
                'authorized' => false,
                'mode' => 'denied',
                'entitlement' => null,
                'reason' => 'Track is suspended or not approved and user has no prior entitlement',
            ];
        }

        // 5. Check if track has an active Free offer
        if ($track->hasActiveFreeOffer()) {
            return [
                'authorized' => true,
                'mode' => 'full',
                'entitlement' => null,
                'reason' => 'Track is published under an active free streaming offer',
            ];
        }

        // 6. Paid track: unentitled user is permitted preview asset only
        if (!empty($track->audio_path_preview)) {
            return [
                'authorized' => true,
                'mode' => 'preview',
                'entitlement' => null,
                'reason' => 'Unentitled user authorized for paid preview asset only',
            ];
        }

        return [
            'authorized' => false,
            'mode' => 'denied',
            'entitlement' => null,
            'reason' => 'No playback access or preview available for this track',
        ];
    }

    /**
     * Check if user is the track owner or platform administrator.
     */
    public function isOwnerOrAdmin(?\Illuminate\Contracts\Auth\Authenticatable $user, MusicTrack $track): bool
    {
        if (!$user) {
            return false;
        }

        if ($user instanceof \App\Admin) {
            return true;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if ($track->isOwnedBySeller() && (int) $user->getAuthIdentifier() === (int) $track->seller_id) {
            return true;
        }

        if ($track->isOwnedByAdmin() && !empty($track->admin_id) && (int) $user->getAuthIdentifier() === (int) $track->admin_id) {
            return true;
        }

        return false;
    }

    /**
     * Create a tamper-proof short-lived HMAC signed stream ticket.
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable|null $user Authenticated user or null for guest
     * @param MusicTrack $track Target track
     * @param string $mode 'full' or 'preview'
     * @param int $ttlSeconds Ticket lifetime (default 900s = 15 minutes)
     * @return string
     */
    public function createStreamTicket(
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        MusicTrack $track,
        string $mode,
        int $ttlSeconds = 900,
        ?string $sessionId = null
    ): string {
        $sessionId = $sessionId ?: (string) Str::uuid();
        $payload = [
            'trk' => $track->id,
            'usr' => $user ? (int) $user->getAuthIdentifier() : null,
            'mod' => $mode,
            'sid' => $sessionId,
            'exp' => time() + $ttlSeconds,
            'jti' => Str::random(16),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $encodedPayload = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encodedPayload, config('app.key'));

        return $encodedPayload . '.' . $signature;
    }

    /**
     * Validate a stream ticket.
     *
     * @return array{
     *     valid: bool,
     *     mode: ?string,
     *     user_id: ?int,
     *     error: ?string
     * }
     */
    public function validateStreamTicket(
        string $ticket,
        MusicTrack $track,
        ?\Illuminate\Contracts\Auth\Authenticatable $currentUser = null
    ): array {
        $parts = explode('.', $ticket);
        if (count($parts) !== 2) {
            return ['valid' => false, 'mode' => null, 'user_id' => null, 'error' => 'Malformed ticket structure'];
        }

        [$encodedPayload, $signature] = $parts;

        $expectedSignature = hash_hmac('sha256', $encodedPayload, config('app.key'));
        if (!hash_equals($expectedSignature, $signature)) {
            return ['valid' => false, 'mode' => null, 'user_id' => null, 'error' => 'Invalid ticket signature'];
        }

        $json = base64_decode(strtr($encodedPayload, '-_', '+/'));
        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            return ['valid' => false, 'mode' => null, 'user_id' => null, 'error' => 'Invalid ticket payload'];
        }

        if (($payload['trk'] ?? null) !== $track->id) {
            return ['valid' => false, 'mode' => null, 'user_id' => null, 'error' => 'Ticket is not valid for this track'];
        }

        if (($payload['exp'] ?? 0) < time()) {
            return ['valid' => false, 'mode' => null, 'user_id' => null, 'error' => 'Stream ticket has expired'];
        }

        $mode = $payload['mod'] ?? 'preview';
        if (!in_array($mode, ['full', 'preview'], true)) {
            return ['valid' => false, 'mode' => null, 'user_id' => null, 'error' => 'Invalid stream mode in ticket'];
        }

        $ticketUserId = isset($payload['usr']) && $payload['usr'] !== null ? (int) $payload['usr'] : null;
        $currentUserId = $currentUser ? (int) $currentUser->getAuthIdentifier() : null;

        // 1. Cross-User Replay Isolation:
        // A ticket issued to User A cannot be used by User B.
        if ($ticketUserId !== null) {
            if ($currentUserId !== null && $ticketUserId !== $currentUserId) {
                return [
                    'valid' => false,
                    'mode' => null,
                    'user_id' => null,
                    'error' => 'Cross-user ticket replay rejected: ticket is bound to a different authenticated user',
                ];
            }

            // An entitlement ticket for full stream cannot be anonymously shared or replayed by an unauthenticated guest
            if ($mode === 'full' && $currentUserId === null && !$track->hasActiveFreeOffer()) {
                return [
                    'valid' => false,
                    'mode' => null,
                    'user_id' => null,
                    'error' => 'Full-stream entitlement ticket requires authentication with the account to which it was issued',
                ];
            }
        }

        // 2. Guest Ticket Isolation:
        // Guest tickets are strictly limited to preview playback, unless the track is published free
        if ($ticketUserId === null && $mode === 'full' && !$track->hasActiveFreeOffer()) {
            return [
                'valid' => false,
                'mode' => null,
                'user_id' => null,
                'error' => 'Guest tickets cannot authorize full streaming on commercial tracks',
            ];
        }

        // 3. Track Suspension & Lifecycle Validation:
        if ($track->isSuspended()) {
            if ($mode === 'preview') {
                return [
                    'valid' => false,
                    'mode' => null,
                    'user_id' => null,
                    'error' => 'Track is suspended; preview streaming is unavailable',
                ];
            }

            // For full stream, verify user has a pre-existing valid entitlement
            if (!$ticketUserId || !\App\MusicEntitlement::query()->where('user_id', $ticketUserId)->where('track_id', $track->id)->where('is_active', true)->exists()) {
                return [
                    'valid' => false,
                    'mode' => null,
                    'user_id' => null,
                    'error' => 'Track is suspended and ticket holder holds no active entitlement',
                ];
            }
        }

        // 4. Live Entitlement & Authorization Revalidation:
        // A valid cryptographic signature alone must NEVER override expired, revoked, or changed access.
        $userForAuth = $currentUser;
        if (!$userForAuth && $ticketUserId) {
            $userForAuth = \App\User::find($ticketUserId);
        }

        $currentAccess = $this->resolvePlaybackAccess($userForAuth, $track);

        if (!$currentAccess['authorized']) {
            return [
                'valid' => false,
                'mode' => null,
                'user_id' => null,
                'error' => 'Current playback access denied: ' . $currentAccess['reason'],
            ];
        }

        if ($mode === 'full' && $currentAccess['mode'] !== 'full') {
            return [
                'valid' => false,
                'mode' => null,
                'user_id' => null,
                'error' => 'Full streaming access is no longer authorized (entitlement expired, revoked, or changed)',
            ];
        }

        return [
            'valid' => true,
            'mode' => $mode,
            'user_id' => $ticketUserId,
            'session_id' => $payload['sid'] ?? null,
            'error' => null,
        ];
    }

    /**
     * Validate a playback session token for telemetry ingestion.
     *
     * @param string $token HMAC signed stream ticket or session token
     * @param MusicTrack $track Expected track
     * @param \Illuminate\Contracts\Auth\Authenticatable|null $currentUser Current authenticated user or null
     * @param string|null $expectedSessionId Expected session UUID if provided
     * @return array{
     *     valid: bool,
     *     mode: ?string,
     *     user_id: ?int,
     *     session_id: ?string,
     *     error: ?string
     * }
     */
    public function validatePlaybackSessionToken(
        string $token,
        MusicTrack $track,
        ?\Illuminate\Contracts\Auth\Authenticatable $currentUser = null,
        ?string $expectedSessionId = null
    ): array {
        $validation = $this->validateStreamTicket($token, $track, $currentUser);
        if (!$validation['valid']) {
            return $validation;
        }

        if ($expectedSessionId !== null && !empty($validation['session_id'])) {
            if ($validation['session_id'] !== $expectedSessionId) {
                return [
                    'valid' => false,
                    'mode' => null,
                    'user_id' => null,
                    'session_id' => null,
                    'error' => 'Playback session ID mismatch with session token',
                ];
            }
        }

        return $validation;
    }

    /**
     * Stream a private audio file with robust HTTP Byte-Range support (200, 206, 416).
     */
    public function streamAudioFile(Request $request, string $absoluteFilePath, string $mimeType = 'audio/mpeg'): StreamedResponse
    {
        if (!file_exists($absoluteFilePath) || !is_readable($absoluteFilePath)) {
            abort(404, 'Audio resource not found on storage server');
        }

        $fileSize = (int) filesize($absoluteFilePath);
        if ($fileSize <= 0) {
            abort(404, 'Audio resource is empty or unavailable');
        }

        $rangeHeader = $request->header('Range');

        // Check if Range header is requested
        if ($rangeHeader && preg_match('/bytes=\s*(\d*)\s*-\s*(\d*)/i', $rangeHeader, $matches)) {
            $startStr = $matches[1];
            $endStr = $matches[2];

            if ($startStr === '' && $endStr === '') {
                // Malformed range
                return response()->stream(function () {}, 416, [
                    'Content-Range' => "bytes */{$fileSize}",
                    'Accept-Ranges' => 'bytes',
                    'Content-Type' => 'text/plain',
                ]);
            }

            if ($startStr === '') {
                // Suffix byte range: bytes=-500 (last 500 bytes)
                $suffixLength = (int) $endStr;
                if ($suffixLength >= $fileSize) {
                    $start = 0;
                    $end = $fileSize - 1;
                } else {
                    $start = max(0, $fileSize - $suffixLength);
                    $end = $fileSize - 1;
                }
            } elseif ($endStr === '') {
                // Prefix byte range: bytes=500- (from 500 to EOF)
                $start = (int) $startStr;
                $end = $fileSize - 1;
            } else {
                $start = (int) $startStr;
                $end = (int) $endStr;
            }

            // Boundary validation: start must be <= end and start < fileSize
            if ($start > $end || $start >= $fileSize) {
                return response()->stream(function () {}, 416, [
                    'Content-Range' => "bytes */{$fileSize}",
                    'Accept-Ranges' => 'bytes',
                    'Content-Type' => 'text/plain',
                ]);
            }

            // Clamp end to file size - 1
            if ($end >= $fileSize) {
                $end = $fileSize - 1;
            }

            $length = $end - $start + 1;

            $headers = [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $length,
                'Content-Range' => sprintf('bytes %d-%d/%d', $start, $end, $fileSize),
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'no-cache, private',
            ];

            return response()->stream(function () use ($absoluteFilePath, $start, $length) {
                $stream = fopen($absoluteFilePath, 'rb');
                if ($stream === false) {
                    return;
                }

                fseek($stream, $start);
                $bytesRemaining = $length;
                $chunkSize = 64 * 1024; // 64 KB chunk

                while ($bytesRemaining > 0 && !feof($stream)) {
                    $readSize = min($chunkSize, $bytesRemaining);
                    $data = fread($stream, $readSize);
                    if ($data === false) {
                        break;
                    }
                    echo $data;
                    flush();
                    $bytesRemaining -= strlen($data);
                }

                fclose($stream);
            }, 206, $headers);
        }

        // Standard full file response (HTTP 200)
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-cache, private',
        ];

        return response()->stream(function () use ($absoluteFilePath) {
            $stream = fopen($absoluteFilePath, 'rb');
            if ($stream === false) {
                return;
            }

            $chunkSize = 64 * 1024;
            while (!feof($stream)) {
                $data = fread($stream, $chunkSize);
                if ($data === false) {
                    break;
                }
                echo $data;
                flush();
            }

            fclose($stream);
        }, 200, $headers);
    }
}
