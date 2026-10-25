<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MusicTrack extends Model
{
    use HasFactory;

    protected $table = 'music_tracks';

    public const OWNER_TYPE_SELLER = 'seller';
    public const OWNER_TYPE_ADMIN = 'admin';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';

    public const MEDIA_STATUS_PENDING = 'pending';
    public const MEDIA_STATUS_PROCESSING = 'processing';
    public const MEDIA_STATUS_READY = 'ready';
    public const MEDIA_STATUS_FAILED = 'failed';

    public const RIGHTS_ORIGINAL = 'original_composition';
    public const RIGHTS_REMIX = 'licensed_remix';
    public const RIGHTS_PUBLIC_DOMAIN = 'public_domain';

    protected $fillable = [
        'title_en',
        'title_ar',
        'artist_name',
        'original_filename',
        'owner_type',
        'seller_id',
        'submitted_by_user_id',
        'original_creator_name',
        'admin_id',
        'genre_id',
        'cover_image',
        'audio_path_original',
        'audio_path_preview',
        'audio_path_stream',
        'media_status',
        'media_error',
        'duration_seconds',
        'preview_duration_seconds',
        'preview_version',
        'file_size_bytes',
        'audio_format',
        'audio_mime_type',
        'audio_codec',
        'audio_bitrate_kbps',
        'audio_sample_rate',
        'audio_channels',
        'bpm',
        'track_key',
        'description_en',
        'description_ar',
        'rights_declaration_type',
        'rights_agreement_accepted',
        'rights_accepted_by_user_id',
        'rights_accepted_by_name',
        'rights_notes',
        'declared_at',
        'declared_ip',
        'status',
        'rejection_reason',
        'reviewed_by_admin_id',
        'reviewed_at',
        'is_featured',
        'featured_order',
        'play_count',
        'released_at',
    ];

    protected $casts = [
        'rights_agreement_accepted' => 'boolean',
        'is_featured' => 'boolean',
        'duration_seconds' => 'integer',
        'preview_duration_seconds' => 'integer',
        'preview_version' => 'integer',
        'file_size_bytes' => 'integer',
        'audio_bitrate_kbps' => 'integer',
        'audio_sample_rate' => 'integer',
        'audio_channels' => 'integer',
        'bpm' => 'integer',
        'featured_order' => 'integer',
        'play_count' => 'integer',
        'declared_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    /*--------------------------------------------------------------------------
     * Review A: Genre Relationships (Primary vs Secondary Consistency)
     *--------------------------------------------------------------------------*/

    /**
     * Authoritative primary genre (sole required relationship on track).
     */
    public function primaryGenre(): BelongsTo
    {
        return $this->belongsTo(MusicGenre::class, 'genre_id', 'id');
    }

    /**
     * Backwards-compatible alias for primaryGenre().
     */
    public function genre(): BelongsTo
    {
        return $this->primaryGenre();
    }

    /**
     * Optional secondary genres via pivot table, strictly excluding the primary genre_id.
     */
    public function secondaryGenres(): BelongsToMany
    {
        return $this->belongsToMany(MusicGenre::class, 'music_track_genres', 'track_id', 'genre_id')
            ->where('music_genres.id', '!=', $this->genre_id)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Return all unique genres (primary + secondary) associated with this track.
     */
    public function allGenres(): Collection
    {
        $primary = $this->primaryGenre;
        $secondary = $this->secondaryGenres;

        if (!$primary) {
            return $secondary;
        }

        return collect([$primary])->concat($secondary)->unique('id')->values();
    }

    /**
     * Synchronize primary and secondary genres ensuring no duplication or contradictory sources of truth.
     */
    public function syncGenres(int $primaryGenreId, array $secondaryGenreIds = []): void
    {
        $this->genre_id = $primaryGenreId;
        $this->save();

        // Secondary genres must strictly exclude the primary genre to prevent duplicate assignments
        $cleanSecondaryIds = array_values(array_unique(array_filter(
            $secondaryGenreIds,
            fn($id) => (int) $id !== (int) $primaryGenreId
        )));

        $syncPayload = [];
        foreach ($cleanSecondaryIds as $secId) {
            $syncPayload[$secId] = ['is_primary' => false];
        }

        $this->belongsToMany(MusicGenre::class, 'music_track_genres', 'track_id', 'genre_id')
            ->sync($syncPayload);
    }

    /*--------------------------------------------------------------------------
     * Review B: Ownership & Historical Attribution Relationships
     *--------------------------------------------------------------------------*/

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id', 'id');
    }

    public function rightsAcceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rights_accepted_by_user_id', 'id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id', 'id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(MusicOffer::class, 'track_id', 'id');
    }

    public function activeOffers(): HasMany
    {
        return $this->hasMany(MusicOffer::class, 'track_id', 'id')->where('is_active', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MusicOrder::class, 'track_id', 'id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(MusicEntitlement::class, 'track_id', 'id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(MusicFavorite::class, 'track_id', 'id');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->where(function ($q) {
                $q->where('media_status', self::MEDIA_STATUS_READY)
                  ->orWhere(function ($sub) {
                      $sub->whereNotNull('audio_path_original')
                          ->where('media_status', '!=', self::MEDIA_STATUS_FAILED);
                  });
            })
            ->whereNotNull('released_at')
            ->where('released_at', '<=', now());
    }

    public function scopeMediaReady($query)
    {
        return $query->where('media_status', self::MEDIA_STATUS_READY);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)->orderBy('featured_order', 'asc');
    }

    public function scopeBySeller($query, int $sellerId)
    {
        return $query->where('owner_type', self::OWNER_TYPE_SELLER)->where('seller_id', $sellerId);
    }

    public function scopeByAdmin($query)
    {
        return $query->where('owner_type', self::OWNER_TYPE_ADMIN);
    }

    /**
     * Query tracks matching a genre either as primary or secondary.
     */
    public function scopeByAnyGenre($query, int $genreId)
    {
        return $query->where(function ($q) use ($genreId) {
            $q->where('genre_id', $genreId)
              ->orWhereHas('secondaryGenres', function ($sub) use ($genreId) {
                  $sub->where('music_genres.id', $genreId);
              });
        });
    }

    // Helpers
    public function isOwnedBySeller(): bool
    {
        return $this->owner_type === self::OWNER_TYPE_SELLER && !empty($this->seller_id);
    }

    public function isOwnedByAdmin(): bool
    {
        return $this->owner_type === self::OWNER_TYPE_ADMIN;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function getLocalizedTitle(?string $lang = null): string
    {
        $lang = $lang ?: app()->getLocale();
        if ($lang === 'ar' && !empty($this->title_ar)) {
            return $this->title_ar;
        }
        return $this->title_en;
    }

    public function getTitleAttribute(): string
    {
        return $this->getLocalizedTitle();
    }

    public function hasActiveFreeOffer(): bool
    {
        return $this->activeOffers()->where('offer_type', MusicOffer::OFFER_TYPE_FREE)->exists();
    }

    public function hasActiveRentOffer(): bool
    {
        return $this->activeOffers()->where('offer_type', MusicOffer::OFFER_TYPE_RENT)->exists();
    }

    public function hasActiveSaleOffer(): bool
    {
        return $this->activeOffers()->where('offer_type', MusicOffer::OFFER_TYPE_SALE)->exists();
    }

    /**
     * Record immutable submission attribution snapshots.
     */
    public function recordSubmissionAttribution(User $user, ?string $clientIp = null): void
    {
        $this->submitted_by_user_id = $user->id;
        $this->original_creator_name = $user->name;
        $this->rights_accepted_by_user_id = $user->id;
        $this->rights_accepted_by_name = $user->name;
        $this->declared_at = now();
        $this->declared_ip = $clientIp ?: request()->ip();
        $this->save();
    }

    /**
     * Automatically suspend tracks and deactivate commercial offers when a seller
     * is deactivated or deleted, ensuring no unverified tracks remain published.
     * Preserves all completed orders, entitlements, and attribution history.
     */
    public static function suspendForDeactivatedSeller(int $sellerId, string $reason): int
    {
        $tracks = static::where('owner_type', self::OWNER_TYPE_SELLER)
            ->where('seller_id', $sellerId)
            ->get();

        $count = 0;
        foreach ($tracks as $track) {
            $track->update([
                'status' => self::STATUS_SUSPENDED,
                'rejection_reason' => $reason,
            ]);

            // Deactivate all commercial offers so track cannot be rented or bought
            $track->offers()->update(['is_active' => false]);
            $count++;
        }

        return $count;
    }

    public function isMediaReady(): bool
    {
        return $this->media_status === self::MEDIA_STATUS_READY;
    }

    public function isMediaProcessing(): bool
    {
        return $this->media_status === self::MEDIA_STATUS_PROCESSING;
    }

    public function isMediaFailed(): bool
    {
        return $this->media_status === self::MEDIA_STATUS_FAILED;
    }

    public function markMediaProcessing(): void
    {
        $this->update([
            'media_status' => self::MEDIA_STATUS_PROCESSING,
            'media_error' => null,
        ]);
    }

    public function markMediaReady(array $metadata = []): void
    {
        $payload = array_merge([
            'media_status' => self::MEDIA_STATUS_READY,
            'media_error' => null,
        ], $metadata);

        $this->update($payload);
    }

    public function markMediaFailed(string $error): void
    {
        $this->update([
            'media_status' => self::MEDIA_STATUS_FAILED,
            'media_error' => $error,
        ]);
    }

    /**
     * Determine if a given user can stream the full track.
     * Full streaming is allowed if:
     * 1. The track has an active FREE offer and is approved.
     * 2. The user holds an active entitlement (unexpired rental or perpetual sale).
     *
     * Note: If a seller is deactivated and their track is suspended from public offers,
     * historical paying entitlement holders preserve their playback access unless
     * the track was rejected/revoked for infringement.
     */
    public function canBeStreamedBy(?User $user): bool
    {
        if ($this->status === self::STATUS_REJECTED) {
            return false;
        }

        // Suspended or draft tracks cannot be streamed via free public access
        if ($this->isApproved() && $this->hasActiveFreeOffer()) {
            return true;
        }

        if (!$user) {
            return false;
        }

        return $this->entitlements()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
