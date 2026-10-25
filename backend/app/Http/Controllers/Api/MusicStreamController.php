<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MusicTrack;
use App\Services\Music\MusicStorageService;
use App\Services\Music\MusicStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MusicStreamController extends Controller
{
    protected MusicStreamService $streamService;
    protected MusicStorageService $storageService;

    public function __construct(
        MusicStreamService $streamService,
        MusicStorageService $storageService
    ) {
        $this->streamService = $streamService;
        $this->storageService = $storageService;
    }

    /**
     * Get the public runtime music marketplace configuration.
     *
     * Enables client mobile apps to dynamically fetch marketplace availability
     * and licensing parameters at runtime without requiring a mobile rebuild.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => \App\MusicSetting::toPublicConfig(),
        ]);
    }

    /**
     * Request an authorized, short-lived stream ticket for a music track.
     * Accessible by both authenticated users (full or preview stream based on entitlement)
     * and guest users (preview stream only).
     */
    public function requestStreamTicket(Request $request, int $id): JsonResponse
    {
        $track = MusicTrack::find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Music track not found',
            ], 404);
        }

        $user = $this->resolveRequestUser($request);
        $access = $this->streamService->resolvePlaybackAccess($user, $track);

        if (!$access['authorized']) {
            return response()->json([
                'success' => false,
                'message' => $access['reason'],
                'data' => [
                    'authorized' => false,
                    'mode' => 'denied',
                ],
            ], 403);
        }

        $ttlSeconds = 900; // 15 minutes
        $ticket = $this->streamService->createStreamTicket($user, $track, $access['mode'], $ttlSeconds);

        return response()->json([
            'success' => true,
            'message' => 'Stream ticket issued successfully',
            'data' => [
                'track_id' => $track->id,
                'title' => $track->title,
                'mode' => $access['mode'],
                'preview_duration_seconds' => $access['mode'] === 'preview' ? $track->preview_duration_seconds : null,
                'ticket' => $ticket,
                'stream_url' => route('api.v1.music.tracks.stream', [
                    'id' => $track->id,
                    'ticket' => $ticket,
                ]),
                'stream_endpoint' => route('api.v1.music.tracks.stream', ['id' => $track->id]),
                'stream_header' => 'X-Stream-Ticket',
                'expires_in' => $ttlSeconds,
            ],
        ]);
    }

    /**
     * Stream protected track audio with HTTP Byte-Range support (200, 206, 416).
     * Accepts authentication via stream ticket or active user session.
     */
    public function stream(Request $request, int $id): StreamedResponse
    {
        $track = MusicTrack::find($id);
        if (!$track) {
            abort(404, 'Track not found');
        }

        $mode = null;
        $ticket = $request->header('X-Stream-Ticket') ?? $request->query('ticket');
        $user = $this->resolveRequestUser($request);

        if ($ticket) {
            $verification = $this->streamService->validateStreamTicket($ticket, $track, $user);
            if (!$verification['valid']) {
                abort(403, $verification['error'] ?? 'Invalid or expired stream ticket');
            }
            $mode = $verification['mode'];
        } else {
            // Direct session/token check
            $access = $this->streamService->resolvePlaybackAccess($user, $track);
            if (!$access['authorized']) {
                abort(403, $access['reason']);
            }
            $mode = $access['mode'];
        }

        // Determine relative file path based on authorized playback mode
        if ($mode === 'preview') {
            $relativePath = $track->audio_path_preview;
            if (empty($relativePath) || !$this->storageService->exists($relativePath)) {
                abort(404, 'Preview asset not found or not yet generated');
            }
        } else {
            // Full stream: use standardized stream rendition if available, otherwise master
            $relativePath = $track->audio_path_stream ?: $track->audio_path_original;
            if (empty($relativePath) || !$this->storageService->exists($relativePath)) {
                abort(404, 'Playback asset not found on secure storage');
            }
        }

        $absolutePath = $this->storageService->getAbsolutePath($relativePath);

        return $this->streamService->streamAudioFile($request, $absolutePath, 'audio/mpeg');
    }

    /**
     * Resolve the currently authenticated user from the active request context,
     * prioritizing the Sanctum API guard used by mobile buyers and sellers.
     */
    protected function resolveRequestUser(Request $request): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return $request->user('sanctum')
            ?? auth('sanctum')->user()
            ?? $request->user('api')
            ?? auth('api')->user()
            ?? $request->user();
    }
}
