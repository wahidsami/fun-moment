<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MusicGenre extends Model
{
    use HasFactory;

    protected $table = 'music_genres';

    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 0;

    protected $fillable = [
        'name_en',
        'name_ar',
        'slug',
        'icon',
        'description_en',
        'description_ar',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'status' => 'integer',
    ];

    public function tracks(): HasMany
    {
        return $this->hasMany(MusicTrack::class, 'genre_id', 'id');
    }

    public function secondaryTracks(): BelongsToMany
    {
        return $this->belongsToMany(MusicTrack::class, 'music_track_genres', 'genre_id', 'track_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    public function getLocalizedName(?string $lang = null): string
    {
        $lang = $lang ?: app()->getLocale();
        if ($lang === 'ar' && !empty($this->name_ar)) {
            return $this->name_ar;
        }
        return $this->name_en;
    }
}
