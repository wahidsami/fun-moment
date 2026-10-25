<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MusicPlaybackEvent;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\MusicStreamService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MusicAnalyticsController extends Controller
{
    protected MusicStreamService $streamService;

    public function __construct(MusicStreamService $streamService)
    {
        $this->streamService = $streamService;
    }

    /**
     * Resolve authenticated user.
     */
    protected function resolveUser(Request $request)
    {
        return $request->user() ?? Auth::guard('sanctum')->user() ?? Auth::user();
    }

    /**
     * Ingest a playback telemetry event with server-side session authentication.
     *
     * Telemetry events are client-reported metrics used for product analytics and bounded play counting.
     * Client telemetry NEVER proves complete listening with physical certainty, and NEVER grants playback access,
     * entitlements, order creation, seller commission, or payment settlement.
     */
    public function recordPlaybackEvent(Request $request): JsonResponse
    {
        if (!MusicSetting::isMarketplaceEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'MARKETPLACE_DISABLED',
                'message' => 'Music marketplace is currently disabled.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'track_id' => 'required|integer|exists:music_tracks,id',
            'playback_session_id' => 'required|string|max:64',
            'idempotency_key' => 'required|string|max:128',
            'event_type' => 'required|string|in:' . implode(',', MusicPlaybackEvent::VALID_EVENTS),
            'playback_mode' => 'required|string|in:' . implode(',', MusicPlaybackEvent::VALID_MODES),
            'duration_seconds' => 'nullable|numeric|min:0|max:86400',
            'position_seconds' => 'nullable|numeric|min:0|max:86400',
            'guest_session_id' => 'nullable|string|max:64',
            'client_timestamp' => 'nullable|date',
            'session_token' => 'nullable|string',
            'stream_ticket' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid telemetry event payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $trackId = (int) $request->input('track_id');
        $track = MusicTrack::find($trackId);

        // Security check: Events are only accepted for approved, media-ready, non-suspended tracks
        if (!$track || !$track->isApproved() || !$track->isMediaReady() || $track->isSuspended()) {
            return response()->json([
                'success' => false,
                'message' => 'Track is not eligible for playback telemetry.',
            ], 404);
        }

        // 1. Session Token Resolution & Verification
        // Telemetry must be backed by a server-recognized playback ticket/session token
        $sessionToken = $request->header('X-Stream-Ticket')
            ?: $request->header('X-Session-Token')
            ?: $request->input('session_token')
            ?: $request->input('stream_ticket');

        if (!$sessionToken) {
            return response()->json([
                'success' => false,
                'error_code' => 'MISSING_SESSION_TOKEN',
                'message' => 'A valid server-issued playback session token is required to record telemetry.',
            ], 403);
        }

        $user = $this->resolveUser($request);
        $sessionId = (string) $request->input('playback_session_id');

        $sessionValidation = $this->streamService->validatePlaybackSessionToken(
            $sessionToken,
            $track,
            $user,
            $sessionId
        );

        if (!$sessionValidation['valid']) {
            $errorCode = 'INVALID_PLAYBACK_SESSION';
            if (isset($sessionValidation['error']) && str_contains(strtolower($sessionValidation['error']), 'cross-user')) {
                $errorCode = 'SESSION_USER_MISMATCH';
            }
            return response()->json([
                'success' => false,
                'error_code' => $errorCode,
                'message' => $sessionValidation['error'] ?? 'Invalid or expired playback session.',
            ], 403);
        }

        // 2. Playback Mode Authorization Validation
        // A preview ticket holder cannot submit full playback completion telemetry
        $authorizedMode = $sessionValidation['mode'];
        $submittedMode = $request->input('playback_mode');
        $eventType = $request->input('event_type');

        if ($authorizedMode === MusicPlaybackEvent::MODE_PREVIEW) {
            if ($submittedMode === MusicPlaybackEvent::MODE_FULL || $eventType === MusicPlaybackEvent::EVENT_FULL_TRACK_COMPLETED) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'SESSION_MODE_MISMATCH',
                    'message' => 'Telemetry playback mode exceeds server-authorized session mode (authorized: preview).',
                ], 403);
            }
        }

        // 3. User Identity Boundary Validation
        $tokenUserId = $sessionValidation['user_id'];
        if ($tokenUserId !== null) {
            if (!$user || (int) $user->id !== $tokenUserId) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'SESSION_USER_MISMATCH',
                    'message' => 'Playback session belongs to a different authenticated user.',
                ], 403);
            }
        } else {
            // Guest session: cannot claim an authenticated user identity
            if ($user) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'SESSION_USER_MISMATCH',
                    'message' => 'Authenticated user cannot claim a guest playback session.',
                ], 403);
            }
        }

        // 4. Client Timestamp Validation
        if ($request->filled('client_timestamp')) {
            $clientTime = Carbon::parse($request->input('client_timestamp'));
            // Allow up to 60 seconds tolerance for slight client clock skew
            if ($clientTime->isAfter(now()->addSeconds(60))) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_CLIENT_TIMESTAMP',
                    'message' => 'Client timestamp cannot be in the future.',
                ], 422);
            }
        }

        // 5. Position Bounds Validation
        $position = (float) $request->input('position_seconds', 0);
        $maxAllowedPosition = ($authorizedMode === MusicPlaybackEvent::MODE_PREVIEW)
            ? (float) ($track->preview_duration_seconds ?: MusicSetting::get('paid_preview_duration_seconds', 20)) + 5.0
            : (float) ($track->duration_seconds > 0 ? $track->duration_seconds + 5.0 : 86400);

        if ($position < 0 || $position > $maxAllowedPosition) {
            return response()->json([
                'success' => false,
                'error_code' => 'POSITION_OUT_OF_BOUNDS',
                'message' => 'Reported position exceeds track playback duration bounds.',
            ], 422);
        }

        // Prepare clean telemetry payload strictly discarding any financial or entitlement claims
        $payload = [
            'track_id' => $trackId,
            'user_id' => $user ? $user->id : null,
            'guest_session_id' => $request->input('guest_session_id'),
            'playback_session_id' => $sessionId,
            'idempotency_key' => $request->input('idempotency_key'),
            'event_type' => $eventType,
            'playback_mode' => $submittedMode,
            'duration_seconds' => (float) $request->input('duration_seconds', 0),
            'position_seconds' => $position,
            'client_timestamp' => $request->input('client_timestamp') ?: now(),
            'metadata' => $request->input('metadata'),
        ];

        [$event, $isCounted, $isDuplicate] = MusicPlaybackEvent::recordEvent($payload, $request->ip());

        return response()->json([
            'success' => true,
            'event_id' => $event->id,
            'is_duplicate' => $isDuplicate,
            'data' => [
                'event_id' => $event->id,
                'event_type' => $event->event_type,
                'playback_mode' => $event->playback_mode,
                'counted_play' => $isCounted,
                'is_duplicate' => $isDuplicate,
            ],
            'counted_play' => $isCounted,
            'message' => $isDuplicate ? 'Duplicate telemetry event received.' : 'Playback event recorded successfully.',
        ], 200);
    }
}
