<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Page;
use App\Service;
use App\Slider;
use Illuminate\Http\Request;

class SliderController extends Controller
{
    /**
     * Public mobile API for active promotional carousel slides.
     */
    public function slider(?Request $request = null)
    {
        $request = $request ?? request();
        $lang = $request ? $request->header('X-Language', $request->query('lang', app()->getLocale())) : app()->getLocale();
        $isArabic = str_starts_with(strtolower((string) $lang), 'ar');

        $sliders = Slider::active()->get();
        $sliderDetails = [];
        $imageUrls = [];

        foreach ($sliders as $slider) {
            $attachmentMeta = $slider->getAttachmentMeta();
            $resolvedImgUrl = $slider->resolved_image_url;

            // Determine localized title and subtitle
            $titleEn = $slider->title;
            $titleAr = !empty($slider->title_ar) ? $slider->title_ar : $slider->title;
            $subTitleEn = $slider->sub_title;
            $subTitleAr = !empty($slider->sub_title_ar) ? $slider->sub_title_ar : $slider->sub_title;

            $activeTitle = $isArabic ? $titleAr : $titleEn;
            $activeSubTitle = $isArabic ? $subTitleAr : $subTitleEn;

            $btnText = $isArabic
                ? (!empty($slider->btn_text_ar) ? $slider->btn_text_ar : $slider->btn_text_en)
                : (!empty($slider->btn_text_en) ? $slider->btn_text_en : $slider->btn_text_ar);

            // Sanitize action type and target
            $rawActionType = $slider->action_type ?? ($slider->service_id ? 'service' : 'none');
            $rawActionTarget = $slider->action_target ?? ($slider->service_id ? (string) $slider->service_id : null);
            $actionType = 'none';
            $actionTarget = null;

            if ($rawActionType === 'service' && !empty($rawActionTarget) && is_numeric($rawActionTarget)) {
                $actionType = 'service';
                $actionTarget = (string) (int) $rawActionTarget;
            } elseif ($rawActionType === 'category' && !empty($rawActionTarget) && is_numeric($rawActionTarget)) {
                $actionType = 'category';
                $actionTarget = (string) (int) $rawActionTarget;
            } elseif ($rawActionType === 'section' && !empty($rawActionTarget)) {
                $targetKey = trim((string) $rawActionTarget);
                $validKeys = array_column(Slider::SUPPORTED_APP_SECTIONS, 'key');
                if (in_array($targetKey, $validKeys, true)) {
                    $actionType = 'section';
                    $actionTarget = $targetKey;
                }
            } elseif ($rawActionType === 'url' && !empty($rawActionTarget)) {
                $trimmedUrl = trim((string) $rawActionTarget);
                if (filter_var($trimmedUrl, FILTER_VALIDATE_URL) && (str_starts_with($trimmedUrl, 'http://') || str_starts_with($trimmedUrl, 'https://'))) {
                    $actionType = 'url';
                    $actionTarget = $trimmedUrl;
                }
            }

            $serviceId = $actionType === 'service' ? (int) $actionTarget : ($slider->service_id ? (int) $slider->service_id : null);

            $sliderDetails[] = [
                'id' => $slider->id,
                'background_image' => (string) $slider->background_image,
                'title' => $activeTitle,
                'sub_title' => $activeSubTitle,
                'title_en' => $titleEn,
                'title_ar' => $titleAr,
                'sub_title_en' => $subTitleEn,
                'sub_title_ar' => $subTitleAr,
                'btn_text' => $btnText,
                'btn_text_en' => $slider->btn_text_en,
                'btn_text_ar' => $slider->btn_text_ar,
                'service_id' => $serviceId,
                'action_type' => $actionType,
                'action_target' => $actionTarget,
                'order' => $slider->order ?? 0,
                'img_url' => $resolvedImgUrl,
                'image_variants' => $attachmentMeta['image_variants'] ?? null,
            ];

            $imageUrls[] = [
                'image_id' => $attachmentMeta['image_id'],
                'path' => $attachmentMeta['path'],
                'img_url' => $resolvedImgUrl,
                'img_alt' => $activeTitle,
                'image_variants' => $attachmentMeta['image_variants'] ?? null,
            ];
        }

        if (!empty($sliderDetails)) {
            return response()->success([
                'slider-details' => $sliderDetails,
                'image_url' => $imageUrls,
            ]);
        }

        return response()->success([
            'slider-details' => [],
            'image_url' => [],
            'message' => __('Slider Not Available'),
        ]);
    }

    public function terms_and_condition_page()
    {
        $terms_and_condition_page = Page::select('slug', 'page_content')
            ->where('slug', get_static_option('select_terms_condition_page'))
            ->first();

        return response()->success([
            'terms_and_condition' => $terms_and_condition_page,
        ]);
    }

    public function privacy_policy_page()
    {
        $privacy_policy_page = Page::select('slug', 'page_content')
            ->where('slug', 'privacy-policy')
            ->first();

        return response()->success([
            'privacy_policy_page' => $privacy_policy_page,
        ]);
    }
}
