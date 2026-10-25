<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MusicPlaybackEvent extends Model
{
    use HasFactory;

    protected $table = 'music_playback_events';

    public const EVENT_PLAYBACK_STARTED = 'playback_started';
    public const EVENT_LISTENING_PROGRESS = 'listening_progress';
    public const EVENT_PREVIEW_COMPLETED = 'preview_completed';
    public const EVENT_FULL_TRACK_COMPLETED = 'full_track_completed';
    public const EVENT_PLAYBACK_FAILED = 'playback_failed';

    public const VALID_EVENTS = [
        self::EVENT_PLAYBACK_STARTED,
        self::EVENT_LISTENING_PROGRESS,
        self::EVENT_PREVIEW_COMPLETED,
        self::EVENT_FULL_TRACK_COMPLETED,
        self::EVENT_PLAYBACK_FAILED,
    ];

    public const MODE_PREVIEW = 'preview';
    public const MODE_FULL = 'full';

    public const VALID_MODES = [
        self::MODE_PREVIEW,
        self::MODE_FULL,
    ];

    protected $fillable = [
        'track_id',
        'user_id',
        'guest_session_id',
        'playback_session_id',
        'idempotency_key',
        'event_type',
        'playback_mode',
        'duration_seconds',
        'position_seconds',
        'counted_play',
        'client_timestamp',
        'metadata',
    ];

    protected $casts = [
        'duration_seconds' => 'float',
        'position_seconds' => 'float',
        'counted_play' => 'boolean',
        'client_timestamp' => 'datetime',
        'metadata' => 'array',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(MusicTrack::class, 'track_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeCountedPlays($query)
    {
        return $query->where('counted_play', true);
    }

    public function scopeForTrack($query, int $trackId)
    {
        return $query->where('track_id', $trackId);
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Ingest a playback telemetry event idempotently.
     * Evaluates qualified listening criteria to safely count unique plays.
     *
     * Note: Telemetry events are client-reported metrics used for product analytics and bounded play counting.
     * Client telemetry NEVER proves complete listening with physical certainty, and NEVER grants playback access,
     * entitlements, order creation, seller commission, or payment settlement.
     *
     * @param array $payload Validated telemetry payload
     * @param string|null $clientIp Client IP address for popularity anti-spam bounds
     * @return array [MusicPlaybackEvent $event, bool $isNewPlayCounted, bool $isDuplicate]
     */
    public static function recordEvent(array $payload, ?string $clientIp = null): array
    {
        $idempotencyKey = $payload['idempotency_key'];

        // 1. Idempotency Check: Return existing record on retry
        $existing = static::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return [$existing, false, true];
        }

        return DB::transaction(function () use ($payload, $clientIp) {
            $trackId = (int) $payload['track_id'];
            $sessionId = (string) $payload['playback_session_id'];
            $eventType = (string) $payload['event_type'];
            $duration = (float) ($payload['duration_seconds'] ?? 0);
            $position = (float) ($payload['position_seconds'] ?? 0);

            // 2. Check if this session has ALREADY been counted towards a play
            $alreadyCounted = static::where('playback_session_id', $sessionId)
                ->where('counted_play', true)
                ->exists();

            $isQualifiedPlay = false;

            if (!$alreadyCounted && $eventType !== self::EVENT_PLAYBACK_FAILED) {
                // A counted play requires meaningful engagement:
                // - Preview completed
                // - Full track completed
                // - Listening duration reached at least 15 seconds or 50% of track length
                if (
                    $eventType === self::EVENT_PREVIEW_COMPLETED ||
                    $eventType === self::EVENT_FULL_TRACK_COMPLETED ||
                    ($eventType === self::EVENT_LISTENING_PROGRESS && ($position >= 15.0 || ($duration > 0 && ($position / $duration) >= 0.5)))
                ) {
                    $isQualifiedPlay = true;
                }
            }

            // 3. Popularity Counter Anti-Spam Bounds:
            // Prevent bot scripts from rapidly inflating play_count by obtaining multiple tickets.
            // Caps qualified plays to at most 10 counted plays per track per client IP within 1 hour.
            if ($isQualifiedPlay) {
                $ip = $clientIp ?: (request() ? request()->ip() : 'unknown');
                $cacheKey = "music_play_count:track_{$trackId}:ip_" . md5($ip ?: 'unknown');
                $recentPlays = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0);

                if ($recentPlays >= 10) {
                    // Suppress play_count increment; still record telemetry event
                    $isQualifiedPlay = false;
                } else {
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $recentPlays + 1, 3600);
                }
            }

            $event = static::create([
                'track_id' => $trackId,
                'user_id' => $payload['user_id'] ?? null,
                'guest_session_id' => $payload['guest_session_id'] ?? null,
                'playback_session_id' => $sessionId,
                'idempotency_key' => $payload['idempotency_key'],
                'event_type' => $eventType,
                'playback_mode' => $payload['playback_mode'] ?? self::MODE_PREVIEW,
                'duration_seconds' => $duration,
                'position_seconds' => $position,
                'counted_play' => $isQualifiedPlay,
                'client_timestamp' => $payload['client_timestamp'] ?? now(),
                'metadata' => $payload['metadata'] ?? null,
            ]);

            if ($isQualifiedPlay) {
                MusicTrack::where('id', $trackId)->increment('play_count');
            }

            return [$event, $isQualifiedPlay, false];
        });
    }
}
