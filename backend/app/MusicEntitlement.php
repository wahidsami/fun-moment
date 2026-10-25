<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicEntitlement extends Model
{
    use HasFactory;

    protected $table = 'music_entitlements';

    public const ACCESS_STREAM_ONLY = 'stream_only';
    public const LICENSE_STREAM_ONLY = 'in_app_streaming_only';

    protected $fillable = [
        'user_id',
        'track_id',
        'offer_id',
        'order_id',
        'access_type',
        'license_type',
        'allow_download',
        'commercial_use',
        'remix_rights',
        'starts_at',
        'expires_at',
        'is_active',
        'revoked_at',
        'revocation_reason',
    ];

    protected $casts = [
        'allow_download' => 'boolean',
        'commercial_use' => 'boolean',
        'remix_rights' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();

        static::saving(function (MusicEntitlement $entitlement) {
            // Enforce explicit licensing restrictions:
            // Permanent in-app listening only. No download, no commercial, no remix.
            $entitlement->access_type = self::ACCESS_STREAM_ONLY;
            $entitlement->license_type = self::LICENSE_STREAM_ONLY;
            $entitlement->allow_download = false;
            $entitlement->commercial_use = false;
            $entitlement->remix_rights = false;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(MusicTrack::class, 'track_id', 'id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(MusicOffer::class, 'offer_id', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MusicOrder::class, 'order_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false; // Perpetual purchase
        }
        return $this->expires_at->isPast();
    }

    public function isPerpetual(): bool
    {
        return $this->expires_at === null;
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired() && $this->revoked_at === null;
    }

    public function revoke(string $reason): void
    {
        $this->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);
    }
}
