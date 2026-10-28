<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasFactory;

    protected $table = 'sliders';

    public const ACTION_NONE = 'none';
    public const ACTION_SERVICE = 'service';
    public const ACTION_CATEGORY = 'category';
    public const ACTION_SECTION = 'section';
    public const ACTION_URL = 'url';

    public const SUPPORTED_ACTION_TYPES = [
        self::ACTION_NONE,
        self::ACTION_SERVICE,
        self::ACTION_CATEGORY,
        self::ACTION_SECTION,
        self::ACTION_URL,
    ];

    public const SUPPORTED_APP_SECTIONS = [
        [
            'key' => 'music_marketplace',
            'label_en' => 'Music Marketplace',
            'label_ar' => 'سوق الموسيقى',
            'description_en' => 'Open Music Marketplace track & beat catalog',
            'description_ar' => 'فتح كتالوج المقاطع الموسيقية وسوق الموسيقى',
            'icon' => 'music',
        ],
        [
            'key' => 'all_categories',
            'label_en' => 'All Categories',
            'label_ar' => 'جميع التصنيفات',
            'description_en' => 'Browse all marketplace service categories',
            'description_ar' => 'استعراض كافة تصنيفات الخدمات في المنصة',
            'icon' => 'grid',
        ],
        [
            'key' => 'bookings',
            'label_en' => 'My Bookings & Orders',
            'label_ar' => 'حجوزاتي وطلباتي',
            'description_en' => 'Buyer bookings, active orders & service status',
            'description_ar' => 'حجوزات المشتري والطلبات الحالية وحالة الخدمات',
            'icon' => 'calendar',
        ],
        [
            'key' => 'discover',
            'label_en' => 'Discover Services',
            'label_ar' => 'استكشف الخدمات',
            'description_en' => 'Search & explore marketplace services',
            'description_ar' => 'البحث واستكشاف خدمات المنصة',
            'icon' => 'search',
        ],
        [
            'key' => 'saved',
            'label_en' => 'Saved Favorites',
            'label_ar' => 'المفضلة والمحفوظات',
            'description_en' => 'View saved services and bookmarks',
            'description_ar' => 'عرض الخدمات المحفوظة والمفضلة',
            'icon' => 'heart',
        ],
        [
            'key' => 'wallet',
            'label_en' => 'My Wallet',
            'label_ar' => 'محفظتي',
            'description_en' => 'Buyer wallet balance & transactions',
            'description_ar' => 'رصيد محفظة المشتري وسجل العمليات',
            'icon' => 'credit-card',
        ],
        [
            'key' => 'top_services',
            'label_en' => 'Top Rated Services',
            'label_ar' => 'الخدمات الأعلى تقييماً',
            'description_en' => 'Browse top-reviewed & trending services',
            'description_ar' => 'استعراض الخدمات الأكثر تقييماً والأكثر طلباً',
            'icon' => 'star',
        ],
    ];

    protected $fillable = [
        'background_image',
        'title',
        'title_ar',
        'sub_title',
        'sub_title_ar',
        'btn_text_en',
        'btn_text_ar',
        'service_id',
        'action_type',
        'action_target',
        'order',
        'status',
    ];

    protected $casts = [
        'service_id' => 'integer',
        'order' => 'integer',
        'status' => 'integer',
    ];

    protected $appends = [
        'resolved_image_url',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    /**
     * Scope for active slides ordered by display order.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1)->orderBy('order', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Resolve public image URL from background_image.
     */
    public function getResolvedImageUrlAttribute(): ?string
    {
        $bg = $this->background_image;
        if (empty($bg)) {
            return null;
        }

        // Numeric MediaUpload ID
        if (is_numeric($bg) || (is_string($bg) && ctype_digit($bg))) {
            $attachment = get_attachment_image_by_id((int) $bg);
            if (!empty($attachment) && is_array($attachment) && !empty($attachment['img_url'])) {
                return $attachment['img_url'];
            }
        }

        // Absolute URL
        if (is_string($bg) && (str_starts_with($bg, 'http://') || str_starts_with($bg, 'https://'))) {
            return $bg;
        }

        // Relative path or local asset
        if (is_string($bg) && !empty($bg)) {
            if (str_starts_with($bg, '/')) {
                return asset(ltrim($bg, '/'));
            }
            if (str_starts_with($bg, 'assets/')) {
                return asset($bg);
            }
            return asset('assets/uploads/media-uploader/' . $bg);
        }

        return null;
    }

    /**
     * Resolve media attachment metadata if numeric ID is stored.
     */
    public function getAttachmentMeta(): array
    {
        $bg = $this->background_image;
        $resolvedUrl = $this->resolved_image_url;

        if (is_numeric($bg) || (is_string($bg) && ctype_digit($bg))) {
            $attach = get_attachment_image_by_id((int) $bg);
            if (!empty($attach) && is_array($attach) && !empty($attach['img_url'])) {
                return [
                    'image_id' => (int) $bg,
                    'path' => $attach['path'] ?? null,
                    'img_url' => $attach['img_url'],
                    'img_alt' => $attach['img_alt'] ?? null,
                ];
            }
        }

        return [
            'image_id' => (is_numeric($bg) || (is_string($bg) && ctype_digit($bg))) ? (int) $bg : null,
            'path' => is_string($bg) ? $bg : null,
            'img_url' => $resolvedUrl,
            'img_alt' => $this->title,
        ];
    }
}
