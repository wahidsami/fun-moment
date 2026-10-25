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
     * Atomically toggle favorite state for a user and track.
     * Returns true if favorited, false if unfavorited.
     */
    public static function toggle(int $userId, int $trackId): bool
    {
        return DB::transaction(function () use ($userId, $trackId) {
            $existing = static::where('user_id', $userId)
                ->where('track_id', $trackId)
                ->first();

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
}
