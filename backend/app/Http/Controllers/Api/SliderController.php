<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Page;
use App\Service;
use App\Slider;
use Billplz\Request;

class SliderController extends Controller
{
    public function slider(){
        $slider = Slider::select('background_image','title','sub_title', 'service_id')->get();
        $image_url = [];

        foreach($slider as $sli){
            $bg = $sli->background_image;
            if (!empty($bg) && (is_int($bg) || (is_string($bg) && ctype_digit($bg)))) {
                $attach = get_attachment_image_by_id((int)$bg);
                if (!empty($attach) && is_array($attach) && !empty($attach['img_url'])) {
                    $image_url[] = $attach;
                    continue;
                }
            }

            // If non-numeric filename or unresolved ID, return clean object structure rather than empty array
            $image_url[] = [
                'image_id' => null,
                'path' => is_string($bg) ? $bg : null,
                'img_url' => null,
                'img_alt' => null,
            ];
        }

        if($slider->isNotEmpty()){
            return response()->success([
                'slider-details'=>$slider,
                'image_url'=>$image_url,
            ]);
        }

        return response()->error([
            'message'=> __('Slider Not Available'),
        ]);
    }

    public function terms_and_condition_page(){
        $terms_and_condition_page = Page::select('slug','page_content')->where('slug', get_static_option('select_terms_condition_page'))->first();
        return response()->success([
            'terms_and_condition'=> $terms_and_condition_page,
        ]);
    }

    public function privacy_policy_page(){
        $privacy_policy_page = Page::select('slug','page_content')->where('slug', 'privacy-policy')->first();
        return response()->success([
            'privacy_policy_page'=> $privacy_policy_page,
        ]);
    }
}
