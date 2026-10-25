<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class MusicFavorite extends Model
{
    use HasFactory;

    protected $table = 'music_favorites';

    protected $fillable = [
        'user_id',
        'track_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(MusicTrack::class, 'track_id', 'id');
    }

    /**
     * Set or toggle favorite state idempotently for a user and track.
     * $action can be 'favorite', 'unfavorite', or 'toggle'.
     * Returns true if favorited, false if unfavorited.
     */
    public static function setFavorite(int $userId, int $trackId, string $action = 'toggle'): bool
    {
        return DB::transaction(function () use ($userId, $trackId, $action) {
            $existing = static::where('user_id', $userId)
                ->where('track_id', $trackId)
                ->first();

            if ($action === 'favorite') {
                if (!$existing) {
                    static::create([
                        'user_id' => $userId,
                        'track_id' => $trackId,
                    ]);
                }
                return true;
            }

            if ($action === 'unfavorite') {
                if ($existing) {
                    $existing->delete();
                }
                return false;
            }

            // Default 'toggle'
            if ($existing) {
                $existing->delete();
                return false;
            }

            static::create([
                'user_id' => $userId,
                'track_id' => $trackId,
            ]);
            return true;
        });
    }

    /**
     * Atomically toggle favorite state for a user and track.
     * Returns true if favorited, false if unfavorited.
     */
    public static function toggle(int $userId, int $trackId): bool
    {
        return static::setFavorite($userId, $trackId, 'toggle');
    }

    /**
     * Check if a track is favorited by a user.
     */
    public static function isFavorite(int $userId, int $trackId): bool
    {
        return static::where('user_id', $userId)->where('track_id', $trackId)->exists();
    }
}
