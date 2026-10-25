<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MusicSetting extends Model
{
    use HasFactory;

    protected $table = 'music_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public static function get(string $key, $default = null)
    {
        $setting = static::find($key);
        if ($setting) {
            return $setting->value;
        }

        // Canonical alias resolution for preview duration
        if ($key === 'default_preview_duration') {
            $canonical = static::find('paid_preview_duration_seconds');
            if ($canonical) {
                return $canonical->value;
            }
        } elseif ($key === 'paid_preview_duration_seconds') {
            $legacy = static::find('default_preview_duration');
            if ($legacy) {
                return $legacy->value;
            }
        }

        $defaults = self::getDefaults();
        return array_key_exists($key, $defaults) ? $defaults[$key] : $default;
    }

    public static function set(string $key, $value, ?string $description = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'description' => $description,
            ]
        );
    }

    public static function getDefaults(): array
    {
        return [
            'music_marketplace_enabled' => '0',
            'paid_preview_duration_seconds' => '20',
            'default_preview_duration' => '20',
            'max_audio_upload_mb' => '25',
            'platform_commission_rate' => '15.00',
            'allowed_audio_formats' => 'mp3,wav,aac,flac,m4a',
            'seller_submissions_enabled' => '1',
            'seller_tracks_require_approval' => '1',
            'offer_free_enabled' => '1',
            'offer_rent_enabled' => '1',
            'offer_sale_enabled' => '1',
            'default_rental_duration_days' => '7',
        ];
    }

    /**
     * Get the array of authoritative allowed audio format tokens (lowercase).
     * E.g. ['mp3', 'wav', 'aac', 'flac', 'm4a']
     */
    public static function getAllowedAudioFormats(): array
    {
        $raw = (string) self::get('allowed_audio_formats', 'mp3,wav,aac,flac,m4a');
        $formats = array_map('strtolower', array_map('trim', explode(',', $raw)));
        return array_values(array_unique(array_filter($formats)));
    }

    /**
     * Check whether a specific audio format token or extension is administratively permitted.
     * Note: 'aac' and 'm4a' are independent formats and are not cross-aliased.
     */
    public static function isAudioFormatAllowed(string $format): bool
    {
        $format = strtolower(trim($format));
        $allowed = self::getAllowedAudioFormats();

        return in_array($format, $allowed, true);
    }

    /**
     * Update a music setting and trigger any necessary backend operational side-effects
     * (e.g. queuing preview regeneration when paid_preview_duration_seconds changes).
     *
     * @param string $key Setting key
     * @param mixed $value New setting value
     * @param string|null $description Optional description
     * @return array [MusicSetting $setting, int $dispatched_jobs]
     */
    public static function updateWithSideEffects(string $key, $value, ?string $description = null): array
    {
        $oldValue = self::get($key);
        $setting = self::set($key, $value, $description);
        $dispatchedJobs = 0;

        if (
            ($key === 'paid_preview_duration_seconds' || $key === 'default_preview_duration')
            && (string) $oldValue !== (string) $value
        ) {
            try {
                $processingService = app(\App\Services\Music\MusicAudioProcessingService::class);
                $dispatchedJobs = $processingService->queuePreviewRegenerationForOutdatedTracks();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to trigger preview regeneration on setting update: " . $e->getMessage());
            }
        }

        return [
            'setting' => $setting,
            'dispatched_jobs' => $dispatchedJobs,
        ];
    }

    /**
     * Whether the music marketplace is globally visible and active.
     * Default false until an administrator explicitly enables it.
     */
    public static function isMarketplaceEnabled(): bool
    {
        return (bool) (int) self::get('music_marketplace_enabled', 0);
    }

    /**
     * Authoritative server-side paid-track preview duration in seconds.
     * Strictly clamped to safe server bounds (5 to 60 seconds, default 20).
     */
    public static function getPaidPreviewDurationSeconds(): int
    {
        $val = (int) (self::get('paid_preview_duration_seconds') ?: self::get('default_preview_duration', 20));
        return max(5, min(60, $val ?: 20));
    }

    /**
     * Alias for getPaidPreviewDurationSeconds().
     */
    public static function getEffectivePaidPreviewDurationSeconds(): int
    {
        return self::getPaidPreviewDurationSeconds();
    }

    /**
     * Whether sellers are permitted to submit new music tracks.
     */
    public static function areSellerSubmissionsEnabled(): bool
    {
        return (bool) (int) self::get('seller_submissions_enabled', 1);
    }

    /**
     * Whether a specific commercial offer type is active on the platform.
     */
    public static function isOfferTypeEnabled(string $offerType): bool
    {
        return (bool) (int) self::get("offer_{$offerType}_enabled", 1);
    }

    /**
     * Safe, public, read-only configuration payload for future mobile app consumption.
     */
    public static function toPublicConfig(): array
    {
        return [
            'marketplace_enabled' => self::isMarketplaceEnabled(),
            'paid_preview_duration_seconds' => self::getPaidPreviewDurationSeconds(),
            'allowed_audio_formats' => self::getAllowedAudioFormats(),
            'offers' => [
                'free' => self::isOfferTypeEnabled('free'),
                'rent' => self::isOfferTypeEnabled('rent'),
                'sale' => self::isOfferTypeEnabled('sale'),
            ],
            'default_rental_duration_days' => (int) self::get('default_rental_duration_days', 7),
        ];
    }

    /**
     * Authoritative validation rules for administrative settings updates.
     */
    public static function getValidationRules(): array
    {
        return [
            'music_marketplace_enabled' => 'required|in:0,1',
            'paid_preview_duration_seconds' => 'required|integer|min:5|max:60',
            'max_audio_upload_mb' => 'required|integer|min:5|max:100',
            'platform_commission_rate' => 'required|numeric|min:0|max:100',
            'allowed_audio_formats' => 'required|string',
            'seller_submissions_enabled' => 'required|in:0,1',
            'seller_tracks_require_approval' => 'required|in:0,1',
            'offer_free_enabled' => 'required|in:0,1',
            'offer_rent_enabled' => 'required|in:0,1',
            'offer_sale_enabled' => 'required|in:0,1',
            'default_rental_duration_days' => 'required|integer|min:1|max:365',
        ];
    }

    /**
     * Parse human-readable INI size values (e.g. 32M, 256M, 1G) into integer bytes.
     */
    public static function parseIniBytes(?string $value): int
    {
        if (empty($value)) {
            return 0;
        }

        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int) substr($value, 0, -1);

        switch ($unit) {
            case 'g':
                return $number * 1024 * 1024 * 1024;
            case 'm':
                return $number * 1024 * 1024;
            case 'k':
                return $number * 1024;
            default:
                return (int) $value;
        }
    }

    /**
     * Get the authoritative runtime PHP upload limit in bytes.
     */
    public static function getPhpUploadMaxBytes(): int
    {
        $uploadMax = self::parseIniBytes(ini_get('upload_max_filesize') ?: '32M');
        $postMax = self::parseIniBytes(ini_get('post_max_size') ?: '32M');

        // Post max size must accommodate the file plus multipart overhead (~2MB safe buffer)
        $effectivePostCeiling = max(0, $postMax - (2 * 1024 * 1024));

        return min($uploadMax, $effectivePostCeiling > 0 ? $effectivePostCeiling : $uploadMax);
    }

    /**
     * Get the effective maximum audio upload size in bytes,
     * ensuring application configuration never exceeds the PHP runtime ceiling.
     */
    public static function getEffectiveMaxUploadBytes(): int
    {
        $configuredMb = (int) self::get('max_audio_upload_mb', 25);
        $configuredBytes = $configuredMb * 1024 * 1024;
        $phpCeiling = self::getPhpUploadMaxBytes();

        return $phpCeiling > 0 ? min($configuredBytes, $phpCeiling) : $configuredBytes;
    }

    /**
     * Get the configured maximum audio upload size in bytes (before applying runtime PHP bounds).
     */
    public static function getConfiguredMaxUploadBytes(): int
    {
        $configuredMb = (int) self::get('max_audio_upload_mb', 25);
        return $configuredMb * 1024 * 1024;
    }

    /**
     * Get the effective maximum audio upload size in megabytes.
     */
    public static function getEffectiveMaxUploadMb(): int
    {
        return (int) floor(self::getEffectiveMaxUploadBytes() / (1024 * 1024));
    }
}
