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
        $image_url=[];


        foreach($slider as $sli){
            $image_url[]= get_attachment_image_by_id($sli->background_image);
        }

        if($slider){
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
