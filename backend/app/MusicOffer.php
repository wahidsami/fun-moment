<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class MusicOffer extends Model
{
    use HasFactory;

    protected $table = 'music_offers';

    public const OFFER_TYPE_FREE = 'free';
    public const OFFER_TYPE_RENT = 'rent';
    public const OFFER_TYPE_SALE = 'sale';

    public const LICENSE_STREAM_ONLY = 'in_app_streaming_only';

    protected $fillable = [
        'track_id',
        'offer_type',
        'price',
        'currency',
        'rental_duration_days',
        'license_type',
        'allow_download',
        'commercial_use',
        'remix_rights',
        'is_active',
    ];

    protected $casts = [
        'price' => 'float',
        'rental_duration_days' => 'integer',
        'allow_download' => 'boolean',
        'commercial_use' => 'boolean',
        'remix_rights' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();

        static::saving(function (MusicOffer $offer) {
            $offer->enforceLicensingRules();
        });
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(MusicTrack::class, 'track_id', 'id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MusicOrder::class, 'offer_id', 'id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(MusicEntitlement::class, 'offer_id', 'id');
    }

    public function isFree(): bool
    {
        return $this->offer_type === self::OFFER_TYPE_FREE;
    }

    public function isRent(): bool
    {
        return $this->offer_type === self::OFFER_TYPE_RENT;
    }

    public function isSale(): bool
    {
        return $this->offer_type === self::OFFER_TYPE_SALE;
    }

    /**
     * Enforce strict business and licensing constraints.
     * Approved rule:
     * - Free: price must be 0.
     * - Rent: valid price and positive rental duration days required.
     * - Sale: permanent in-app listening ONLY. allow_download, commercial_use, remix_rights MUST be false.
     */
    public function enforceLicensingRules(): void
    {
        if (!in_array($this->offer_type, [self::OFFER_TYPE_FREE, self::OFFER_TYPE_RENT, self::OFFER_TYPE_SALE], true)) {
            throw new InvalidArgumentException("Invalid music offer type: {$this->offer_type}");
        }

        if ($this->price < 0) {
            throw new InvalidArgumentException("Music offer price cannot be negative");
        }

        if ($this->isFree()) {
            if ($this->price > 0) {
                throw new InvalidArgumentException("Free music offers cannot charge a positive price");
            }
            $this->price = 0.00;
            $this->rental_duration_days = null;
            $this->allow_download = false;
            $this->commercial_use = false;
            $this->remix_rights = false;
            $this->license_type = self::LICENSE_STREAM_ONLY;
        }

        if ($this->isRent()) {
            if (empty($this->rental_duration_days) || $this->rental_duration_days <= 0) {
                throw new InvalidArgumentException("Rent offers require a positive rental duration in days");
            }
            $this->allow_download = false;
            $this->commercial_use = false;
            $this->remix_rights = false;
            $this->license_type = self::LICENSE_STREAM_ONLY;
        }

        if ($this->isSale()) {
            // Approved product decision: Sale is strictly permanent in-app listening only.
            // No download of raw master file, no commercial rights, no remix rights.
            $this->rental_duration_days = null;
            $this->allow_download = false;
            $this->commercial_use = false;
            $this->remix_rights = false;
            $this->license_type = self::LICENSE_STREAM_ONLY;
        }
    }
}
