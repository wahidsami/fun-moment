<?php

namespace App\Http\Controllers;

use App\MusicSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminMusicSettingController extends Controller
{
    /**
     * Supported canonical audio formats across the platform.
     */
    const SUPPORTED_FORMATS = ['mp3', 'wav', 'aac', 'flac', 'm4a'];

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Get the authoritative administrative music marketplace settings and runtime metrics.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->canManage($request)) {
            return $this->forbidden();
        }

        $allowedFormats = MusicSetting::getAllowedAudioFormats();

        $data = [
            'music_marketplace_enabled' => MusicSetting::isMarketplaceEnabled(),
            'paid_preview_duration_seconds' => MusicSetting::getPaidPreviewDurationSeconds(),
            'max_audio_upload_mb' => (int) MusicSetting::get('max_audio_upload_mb', 25),
            'platform_commission_rate' => (float) MusicSetting::get('platform_commission_rate', 15.00),
            'allowed_audio_formats' => $allowedFormats,
            'allowed_audio_formats_raw' => implode(',', $allowedFormats),
            'seller_submissions_enabled' => MusicSetting::areSellerSubmissionsEnabled(),
            'seller_tracks_require_approval' => (bool) (int) MusicSetting::get('seller_tracks_require_approval', 1),
            'offer_free_enabled' => MusicSetting::isOfferTypeEnabled('free'),
            'offer_rent_enabled' => MusicSetting::isOfferTypeEnabled('rent'),
            'offer_sale_enabled' => MusicSetting::isOfferTypeEnabled('sale'),
            'default_rental_duration_days' => (int) MusicSetting::get('default_rental_duration_days', 7),
            'music_vat_enabled' => MusicSetting::isVatEnabled(),
            'music_vat_rate' => MusicSetting::getVatRate(),
            'music_checkout_enabled' => MusicSetting::isCheckoutEnabled(),
            'music_price_display_mode' => MusicSetting::getPriceDisplayMode(),
            'music_tax_policy_status' => MusicSetting::getTaxPolicyStatus(),
        ];

        $runtime = [
            'php_upload_max_filesize' => ini_get('upload_max_filesize') ?: '32M',
            'php_post_max_size' => ini_get('post_max_size') ?: '32M',
            'php_max_upload_mb' => MusicSetting::getServerMaxUploadCeilingMb(),
            'effective_max_upload_mb' => MusicSetting::getServerMaxUploadCeilingMb(),
            'configured_max_upload_bytes' => MusicSetting::getConfiguredMaxUploadBytes(),
            'effective_max_upload_bytes' => MusicSetting::getEffectiveMaxUploadBytes(),
            'server_clamped_preview_min' => 5,
            'server_clamped_preview_max' => 60,
            'supported_system_formats' => self::SUPPORTED_FORMATS,
        ];

        return response()->json([
            'success' => true,
            'message_en' => 'Music marketplace settings loaded successfully.',
            'message_ar' => 'تم تحميل إعدادات سوق الموسيقى بنجاح.',
            'data' => $data,
            'runtime' => $runtime,
            'meta' => [
                'can_manage' => true,
            ],
        ]);
    }

    /**
     * Update music marketplace settings with validation and operational side-effects.
     */
    public function update(Request $request): JsonResponse
    {
        if (!$this->canManage($request)) {
            return $this->forbidden();
        }

        // Normalize allowed_audio_formats if provided as an array
        if ($request->has('allowed_audio_formats') && is_array($request->input('allowed_audio_formats'))) {
            $formats = array_map('strtolower', array_map('trim', $request->input('allowed_audio_formats')));
            $request->merge(['allowed_audio_formats' => implode(',', array_filter($formats))]);
        }

        // Merge current defaults for commercial fields if not explicitly provided (backward compatibility)
        $commercialDefaults = [
            'music_vat_enabled' => (string) MusicSetting::get('music_vat_enabled', '0'),
            'music_vat_rate' => (string) MusicSetting::get('music_vat_rate', '15.00'),
            'music_checkout_enabled' => (string) MusicSetting::get('music_checkout_enabled', '0'),
            'music_price_display_mode' => (string) MusicSetting::get('music_price_display_mode', 'exclusive'),
            'music_tax_policy_status' => (string) MusicSetting::get('music_tax_policy_status', 'unapproved'),
        ];
        foreach ($commercialDefaults as $cKey => $cDef) {
            if (!$request->has($cKey)) {
                $request->merge([$cKey => $cDef]);
            }
        }

        // Normalize boolean flags from boolean or string
        $booleanKeys = [
            'music_marketplace_enabled',
            'seller_submissions_enabled',
            'seller_tracks_require_approval',
            'offer_free_enabled',
            'offer_rent_enabled',
            'offer_sale_enabled',
            'music_vat_enabled',
            'music_checkout_enabled',
        ];

        foreach ($booleanKeys as $bKey) {
            if ($request->has($bKey)) {
                $val = $request->input($bKey);
                if (is_bool($val)) {
                    $request->merge([$bKey => $val ? '1' : '0']);
                } elseif (in_array(strtolower((string) $val), ['true', 'on', 'yes', '1'], true)) {
                    $request->merge([$bKey => '1']);
                } elseif (in_array(strtolower((string) $val), ['false', 'off', 'no', '0'], true)) {
                    $request->merge([$bKey => '0']);
                }
            }
        }

        $maxCeilingMb = MusicSetting::getServerMaxUploadCeilingMb();
        $messages = [
            'max_audio_upload_mb.max' => "The maximum audio upload size cannot exceed the server PHP runtime ceiling of {$maxCeilingMb} MB.",
        ];

        $validated = $request->validate(MusicSetting::getValidationRules(), $messages);

        // Validate formats string contains only approved system formats
        $formatTokens = array_map('strtolower', array_map('trim', explode(',', $validated['allowed_audio_formats'])));
        $formatTokens = array_values(array_unique(array_filter($formatTokens)));

        if (empty($formatTokens)) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_FORMATS',
                'message_en' => 'At least one valid audio format must be allowed.',
                'message_ar' => 'يجب السماح بصيغة صوتية واحدة صالحة على الأقل.',
                'errors' => [
                    'allowed_audio_formats' => ['At least one valid audio format must be allowed.'],
                ],
            ], 422);
        }

        foreach ($formatTokens as $token) {
            if (!in_array($token, self::SUPPORTED_FORMATS, true)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'UNSUPPORTED_FORMAT',
                    'message_en' => sprintf("Audio format '%s' is not supported by the streaming processor.", $token),
                    'message_ar' => sprintf("الصيغة الصوتية '%s' غير مدعومة من محرك المعالجة.", $token),
                    'errors' => [
                        'allowed_audio_formats' => [sprintf("Audio format '%s' is not supported.", $token)],
                    ],
                ], 422);
            }
        }

        // Canonical format storage
        $validated['allowed_audio_formats'] = implode(',', $formatTokens);

        $dispatchedJobsSummary = [];
        $totalDispatchedJobs = 0;
        $changedSettings = [];

        foreach ($validated as $key => $value) {
            $oldVal = MusicSetting::get($key);
            if ((string) $oldVal !== (string) $value) {
                $changedSettings[$key] = [
                    'old' => $oldVal,
                    'new' => (string) $value,
                ];
            }

            $result = MusicSetting::updateWithSideEffects($key, (string) $value);
            if (!empty($result['dispatched_jobs']) && $result['dispatched_jobs'] > 0) {
                $dispatchedJobsSummary[$key] = $result['dispatched_jobs'];
                $totalDispatchedJobs += $result['dispatched_jobs'];
            }
        }

        // Record AdminAuditLog if financial or operational settings changed
        if (!empty($changedSettings)) {
            \App\AdminAuditLog::record([
                'action' => 'update_music_settings',
                'resource_type' => 'music_settings',
                'resource_id' => null,
                'details_en' => 'Updated music marketplace settings (' . implode(', ', array_keys($changedSettings)) . ')',
                'details_ar' => 'تم تحديث إعدادات سوق الموسيقى (' . implode(', ', array_keys($changedSettings)) . ')',
                'old_values' => array_map(fn($v) => $v['old'], $changedSettings),
                'new_values' => array_map(fn($v) => $v['new'], $changedSettings),
            ]);
        }

        $allowedFormats = MusicSetting::getAllowedAudioFormats();

        $data = [
            'music_marketplace_enabled' => MusicSetting::isMarketplaceEnabled(),
            'paid_preview_duration_seconds' => MusicSetting::getPaidPreviewDurationSeconds(),
            'max_audio_upload_mb' => (int) MusicSetting::get('max_audio_upload_mb', 25),
            'platform_commission_rate' => (float) MusicSetting::get('platform_commission_rate', 15.00),
            'allowed_audio_formats' => $allowedFormats,
            'allowed_audio_formats_raw' => implode(',', $allowedFormats),
            'seller_submissions_enabled' => MusicSetting::areSellerSubmissionsEnabled(),
            'seller_tracks_require_approval' => (bool) (int) MusicSetting::get('seller_tracks_require_approval', 1),
            'offer_free_enabled' => MusicSetting::isOfferTypeEnabled('free'),
            'offer_rent_enabled' => MusicSetting::isOfferTypeEnabled('rent'),
            'offer_sale_enabled' => MusicSetting::isOfferTypeEnabled('sale'),
            'default_rental_duration_days' => (int) MusicSetting::get('default_rental_duration_days', 7),
            'music_vat_enabled' => MusicSetting::isVatEnabled(),
            'music_vat_rate' => MusicSetting::getVatRate(),
            'music_checkout_enabled' => MusicSetting::isCheckoutEnabled(),
            'music_price_display_mode' => MusicSetting::getPriceDisplayMode(),
            'music_tax_policy_status' => MusicSetting::getTaxPolicyStatus(),
        ];

        $runtime = [
            'php_upload_max_filesize' => ini_get('upload_max_filesize') ?: '32M',
            'php_post_max_size' => ini_get('post_max_size') ?: '32M',
            'php_max_upload_mb' => MusicSetting::getServerMaxUploadCeilingMb(),
            'effective_max_upload_mb' => MusicSetting::getServerMaxUploadCeilingMb(),
            'configured_max_upload_bytes' => MusicSetting::getConfiguredMaxUploadBytes(),
            'effective_max_upload_bytes' => MusicSetting::getEffectiveMaxUploadBytes(),
            'server_clamped_preview_min' => 5,
            'server_clamped_preview_max' => 60,
            'supported_system_formats' => self::SUPPORTED_FORMATS,
        ];

        return response()->json([
            'success' => true,
            'message_en' => 'Music marketplace settings updated successfully.',
            'message_ar' => 'تم تحديث إعدادات سوق الموسيقى بنجاح.',
            'dispatched_regeneration_jobs' => $dispatchedJobsSummary,
            'total_dispatched_jobs' => $totalDispatchedJobs,
            'data' => $data,
            'runtime' => $runtime,
        ]);
    }

    /**
     * Verify whether the authenticated admin is authorized to view/manage platform settings.
     */
    protected function canManage(Request $request): bool
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return false;
        }

        // Spatie role check
        if (method_exists($admin, 'hasRole')) {
            if ($admin->hasRole('Super Admin') || $admin->hasRole('super_admin')) {
                return true;
            }
        }

        // Legacy role attribute check
        $roleStr = strtolower(trim((string) ($admin->role ?? '')));
        if (in_array($roleStr, ['super admin', 'super_admin', 'admin'], true)) {
            return true;
        }

        // Spatie permission check
        if (method_exists($admin, 'can') && $admin->can('manage_settings')) {
            return true;
        }

        return false;
    }

    /**
     * Forbidden response for unauthorized administrative operations.
     */
    protected function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'FORBIDDEN',
            'message_en' => 'Unauthorized. Administrative privileges required to manage music settings.',
            'message_ar' => 'غير مصرح لك. يلزم صلاحيات المشرف لإدارة إعدادات الموسيقى.',
        ], 403);
    }
}
