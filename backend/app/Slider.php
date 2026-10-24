<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasFactory;

    protected $table = 'sliders';

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
