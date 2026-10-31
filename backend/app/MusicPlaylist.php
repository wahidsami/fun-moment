<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusicPlaylist extends Model
{
    use HasFactory;

    protected $table = 'music_playlists';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'cover_image',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    protected $appends = [
        'cover_image_url',
        'cover_image_variants',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function playlistTracks(): HasMany
    {
        return $this->hasMany(MusicPlaylistTrack::class, 'playlist_id', 'id')->orderBy('position', 'asc');
    }

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(MusicTrack::class, 'music_playlist_tracks', 'playlist_id', 'track_id')
            ->withPivot(['position', 'created_at'])
            ->withTimestamps()
            ->orderBy('music_playlist_tracks.position', 'asc');
    }

    /**
     * Resolve cover image URL (custom cover, or fallback to first track's cover image).
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if (!empty($this->cover_image)) {
            return str_starts_with($this->cover_image, 'http')
                ? $this->cover_image
                : url('storage/' . ltrim($this->cover_image, '/'));
        }

        // Fallback to first track's cover image if loaded or available
        $firstTrack = $this->tracks->first();
        if ($firstTrack && !empty($firstTrack->cover_image)) {
            return str_starts_with($firstTrack->cover_image, 'http')
                ? $firstTrack->cover_image
                : url('storage/' . ltrim($firstTrack->cover_image, '/'));
        }

        return null;
    }

    /**
     * Get responsive WebP variants for playlist cover image.
     */
    public function getCoverImageVariantsAttribute(): ?array
    {
        $imagePath = $this->cover_image;
        if (empty($imagePath)) {
            $firstTrack = $this->tracks->first();
            $imagePath = $firstTrack ? $firstTrack->cover_image : null;
        }

        if (empty($imagePath)) {
            return null;
        }

        return app(\App\Services\Media\ImageOptimizationService::class)->getVariantUrls(
            $imagePath,
            \App\Services\Media\ImageOptimizationService::PRESET_MUSIC_ARTWORK,
            'public'
        );
    }

    /**
     * Total duration in seconds of all tracks in the playlist.
     */
    public function getTotalDurationSeconds(): int
    {
        return (int) $this->tracks->sum('duration_seconds');
    }

    /**
     * Check if the given user owns this playlist.
     */
    public function isOwnedBy(?User $user): bool
    {
        return $user && (int) $user->id === (int) $this->user_id;
    }
}
