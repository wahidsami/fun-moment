<?php


namespace App\PageBuilder\Addons\BecomeSeller;

use App\PageBuilder\Fields\ColorPicker;
use App\PageBuilder\Fields\Slider;
use App\PageBuilder\Fields\Switcher;
use App\PageBuilder\Fields\Text;
use App\PageBuilder\Fields\Textarea;
use App\PageBuilder\Traits\LanguageFallbackForPageBuilder;
use App\PageBuilder\Fields\Repeater;
use App\PageBuilder\Helpers\RepeaterField;
use App\PageBuilder\Fields\Image;

class BecomeSellerThree extends \App\PageBuilder\PageBuilderBase
{
    use LanguageFallbackForPageBuilder;

    public function preview_image()
    {
        return 'home_three/become_seller_3.jpg';
    }

    public function admin_render()
    {
        $output = $this->admin_form_before();
        $output .= $this->admin_form_start();
        $output .= $this->default_fields();
        $widget_saved_values = $this->get_settings();


        $output .= Text::get([
            'name' => 'title',
            'label' => __('Title'),
            'value' => $widget_saved_values['title'] ?? null,
        ]);
        $output .= ColorPicker::get([
            'name' => 'title_text_color',
            'label' => __('Title Text Color'),
            'value' => $widget_saved_values['title_text_color'] ?? null,
            'info' => __('select color you want to show in frontend'),
        ]);

        $output .= ColorPicker::get([
            'name' => 'section_bg',
            'label' => __('Background Color'),
            'value' => $widget_saved_values['section_bg'] ?? null,
            'info' => __('select color you want to show in frontend'),
        ]);
        $output .= ColorPicker::get([
            'name' => 'btn_color',
            'label' => __('Button Color'),
            'value' => $widget_saved_values['btn_color'] ?? null,
            'info' => __('select color you want to show in frontend'),
        ]);
        $output .= Text::get([
            'name' => 'btn_text',
            'label' => __('Button Text'),
            'value' => $widget_saved_values['btn_text'] ?? null,
        ]);
        $output .= Text::get([
            'name' => 'btn_link',
            'label' => __('Button Link'),
            'value' => $widget_saved_values['btn_link'] ?? null,
        ]);
        $output .= Image::get([
            'name' => 'seller_image',
            'label' => __('Upload Image'),
            'value' => $widget_saved_values['seller_image'] ?? null,
        ]);

        
        $output .= Repeater::get([
            'settings' => $widget_saved_values,
            'id' => 'contact_page_contact_info_01',
            'fields' => [
                [
                    'type' => RepeaterField::TEXT,
                    'name' => 'benifits',
                    'label' => __('Benifits')
                ],

            ]
        ]);

        $output .= Switcher::get([
            'name' => 'content_list_show_hide',
            'label' => __('Benefits show/hide'),
            'value' => $widget_saved_values['content_list_show_hide'] ?? null,
        ]);

        $output .= Slider::get([
            'name' => 'padding_top',
            'label' => __('Padding Top'),
            'value' => $widget_saved_values['padding_top'] ?? 260,
            'max' => 500,
        ]);
        $output .= Slider::get([
            'name' => 'padding_bottom',
            'label' => __('Padding Bottom'),
            'value' => $widget_saved_values['padding_bottom'] ?? 190,
            'max' => 500,
        ]);

        $output .= $this->admin_form_submit_button();
        $output .= $this->admin_form_end();
        $output .= $this->admin_form_after();

        return $output;
    }
    

    public function frontend_render() : string
    {
        
        $settings = $this->get_settings();
        $title =$settings['title'] ?? '';
        $title_text_color =$settings['title_text_color'] ?? '';
        $content_list_show_hide = $settings['content_list_show_hide'] ??  '';
        $padding_top = $settings['padding_top'] ?? '';
        $padding_bottom = $settings['padding_bottom'] ?? '';
        $section_bg = $settings['section_bg'] ?? '';
        $btn_color = $settings['btn_color'] ?? '';
        $btn_text = $settings['btn_text'] ?? '';
        $btn_link = $settings['btn_link'] ?? '';
        if($btn_link==''){
            $btn_link = route('user.register',['type' => 'seller']);
        }
        $seller_image = render_image_markup_by_attachment_id($settings['seller_image']); 
        $repeater_data = $settings['contact_page_contact_info_01'];


        return $this->renderBlade('become-seller.become-seller-three',[
            'padding_top' => $padding_top,
            'padding_bottom' => $padding_bottom,
            'section_bg' => $section_bg,
            'btn_link' => $btn_link,
            'btn_text' => $btn_text,
            'repeater_data' => $repeater_data,
            'title' => $title,
            'title_text_color' => $title_text_color,
            'seller_image' => $seller_image,
            'content_list_show_hide' => $content_list_show_hide,
            'btn_color' => $btn_color,
        ]);
}

    public function addon_title()
    {
        return __('Start As Seller 3');
    }
}