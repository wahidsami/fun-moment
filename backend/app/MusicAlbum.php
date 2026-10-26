<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusicAlbum extends Model
{
    use HasFactory;

    protected $table = 'music_albums';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const OWNER_TYPE_ADMIN = 'admin';
    public const OWNER_TYPE_SELLER = 'seller';

    protected $fillable = [
        'title_en',
        'title_ar',
        'artist_name',
        'cover_image',
        'description_en',
        'description_ar',
        'genre_id',
        'status',
        'owner_type',
        'admin_id',
        'seller_id',
        'is_featured',
        'released_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'released_at' => 'datetime',
    ];

    protected $appends = [
        'cover_image_url',
    ];

    public function genre(): BelongsTo
    {
        return $this->belongsTo(MusicGenre::class, 'genre_id', 'id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(MusicTrack::class, 'album_id', 'id')->orderBy('track_number', 'asc');
    }

    public function publishedTracks(): HasMany
    {
        return $this->hasMany(MusicTrack::class, 'album_id', 'id')
            ->published()
            ->mediaReady()
            ->orderBy('track_number', 'asc');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('released_at')
            ->where('released_at', '<=', now());
    }

    public function scopeByAdmin($query)
    {
        return $query->where('owner_type', self::OWNER_TYPE_ADMIN);
    }

    public function scopeBySeller($query, int $sellerId)
    {
        return $query->where('owner_type', self::OWNER_TYPE_SELLER)->where('seller_id', $sellerId);
    }

    // Helpers
    public function isOwnedByAdmin(): bool
    {
        return $this->owner_type === self::OWNER_TYPE_ADMIN;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
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

    public function getLocalizedDescription(?string $lang = null): ?string
    {
        $lang = $lang ?: app()->getLocale();
        if ($lang === 'ar' && !empty($this->description_ar)) {
            return $this->description_ar;
        }
        return $this->description_en;
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (empty($this->cover_image)) {
            return null;
        }
        if (filter_var($this->cover_image, FILTER_VALIDATE_URL)) {
            return $this->cover_image;
        }
        return asset('storage/' . ltrim($this->cover_image, '/'));
    }

    public function getTotalDurationSecondsAttribute(): int
    {
        return (int) $this->tracks()->sum('duration_seconds');
    }

    public function getTrackCountAttribute(): int
    {
        return (int) $this->tracks()->count();
    }
}
