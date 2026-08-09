<?php


namespace App\PageBuilder\Addons\Header;

use App\Country;
use App\PageBuilder\Fields\ColorPicker;
use App\PageBuilder\Fields\IconPicker;
use App\PageBuilder\Fields\Image;
use App\PageBuilder\Fields\Repeater;
use App\PageBuilder\Fields\Slider;
use App\PageBuilder\Fields\Switcher;
use App\PageBuilder\Fields\Text;
use App\PageBuilder\Helpers\RepeaterField;
use App\PageBuilder\Traits\LanguageFallbackForPageBuilder;
use App\Category;
use App\Review;
use App\ServiceArea;
use App\ServiceCity;
use App\User;

class HeaderStyleOne extends \App\PageBuilder\PageBuilderBase
{
    use LanguageFallbackForPageBuilder;

    public function preview_image()
    {
        return 'home_five/header_5.jpg';
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
        $output .= Text::get([
            'name' => 'subtitle',
            'label' => __('Subtitle'),
            'value' => $widget_saved_values['subtitle'] ?? null,
        ]);

        $output .= Text::get([
            'name' => 'satisfied_customer_title',
            'label' => __('Satisfied Customer Title'),
            'value' => $widget_saved_values['satisfied_customer_title'] ?? null,
        ]);

        $output .= Text::get([
            'name' => 'service_type',
            'label' => __('Review Title'),
            'value' => $widget_saved_values['service_type'] ?? null,
        ]);
        $output .= IconPicker::get([
            'name' => 'service_icon',
            'label' => __('Review Icon'),
            'value' => $widget_saved_values['service_icon'] ?? null,
        ]);

        $output .= ColorPicker::get([
            'name' => 'header_background_color',
            'label' => __('Background Color'),
            'value' => $widget_saved_values['header_background_color'] ?? null,
        ]);


        $output .= Image::get([
            'name' => 'banner_image',
            'label' => __('Banner Image One'),
            'value' => $widget_saved_values['banner_image'] ?? null,
            'dimensions' => '280x408'
        ]);

        $output .= Image::get([
            'name' => 'image',
            'label' => __('Banner Image Two'),
            'value' => $widget_saved_values['image'] ?? null,
            'dimensions' => '280x408'
        ]);

        $output .= Repeater::get([
            'settings' => $widget_saved_values,
            'id' => 'satisfied_customer_01',
            'fields' => [
                [
                    'type' => RepeaterField::IMAGE,
                    'name' => 'satisfied_customer_image',
                    'label' => __('Satisfied Customer Image (maximus add five images)')
                ],
            ]
        ]);


        $output .= Text::get([
            'name' => 'button_one_title',
            'label' => __('Button One Title'),
            'value' => $widget_saved_values['button_one_title'] ?? null,
        ]);

        $output .= Text::get([
            'name' => 'button_one_link',
            'label' => __('Button One Link'),
            'value' => $widget_saved_values['button_one_link'] ?? null,
        ]);

        $output .= Text::get([
            'name' => 'button_two_title',
            'label' => __('Button Two Title'),
            'value' => $widget_saved_values['button_two_title'] ?? null,
        ]);

        $output .= Text::get([
            'name' => 'button_two_link',
            'label' => __('Button Two Link'),
            'value' => $widget_saved_values['button_two_link'] ?? null,
        ]);


        $output .= Switcher::get([
            'name' => 'button_one_show_hide',
            'label' => __('Button One'),
            'value' => $widget_saved_values['button_one_show_hide'] ?? null,
            'info' => __('Button One Hide/Show')
        ]);

        $output .= Switcher::get([
            'name' => 'button_two_show_hide',
            'label' => __('Button Two'),
            'value' => $widget_saved_values['button_two_show_hide'] ?? null,
            'info' => __('Button Two Hide/Show')
        ]);

        // satisfied customer show hide
        $output .= Switcher::get([
            'name' => 'satisfied_customer_show_hide',
            'label' => __('Satisfied Customer'),
            'value' => $widget_saved_values['satisfied_customer_show_hide'] ?? null,
            'info' => __('Satisfied Customer Hide/Show')
        ]);


        // REVIEW BANNER
        $output .= Switcher::get([
            'name' => 'review_banner_area',
            'label' => __('Review area'),
            'value' => $widget_saved_values['review_banner_area'] ?? null,
            'info' => __('Review area Hide/Show')
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

        $title = $settings['title'] ?? '';
        $subtitle = $settings['subtitle'] ?? '';
        $satisfied_customer_title = $settings['satisfied_customer_title'] ?? '';
        $header_background_color = $settings['header_background_color'] ?? '';
        $review_icon = $settings['service_icon'] ?? '';
        $review_title = $settings['service_type'] ?? '';
        $review_banner_show_hide =$settings['review_banner_area'] ?? '';
        $satisfied_customer_show_hide =$settings['satisfied_customer_show_hide'] ?? '';
        $five_star_review_clients_count = Review::where('rating', 5)->where('status','1')->count();
        $banner_image_one = render_image_markup_by_attachment_id($settings['image']) ?? '';
        $banner_image_two = render_image_markup_by_attachment_id($settings['banner_image']) ?? '';
        $satisfied_customer_images = $settings['satisfied_customer_01'] ?? 0;

        // button section
        $button_one_title = $settings['button_one_title'] ?? '';
        $button_two_title = $settings['button_two_title'] ?? '';
        $button_one_link = $settings['button_one_link'] ?? '';
        $button_two_link = $settings['button_two_link'] ?? '';
        $button_one_show_hide = $settings['button_one_show_hide'] ?? '';
        $button_two_show_hide = $settings['button_two_show_hide'] ?? '';

        // padding
       $padding_top = $settings['padding_top'] ?? '100';
       $padding_bottom = $settings['padding_bottom'] ?? '100';

        $explode = explode(" ",$title);
        $title_end = end($explode);
        $last_space_position = strrpos($title, ' ');
        $title_start = substr($title, 0, $last_space_position);
        $popular = __('Popular:');

return $this->renderBlade('headers.header-five',[
     'title_start' => $title_start,
     'header_background_color' => $header_background_color,
     'title_end' => $title_end,
     'subtitle' => $subtitle,
    'popular' => $popular,
    'image_one' => $banner_image_one,
    'image_two' => $banner_image_two,
    'review_banner_show_hide' => $review_banner_show_hide,
    'five_star_review_clients_count' => $five_star_review_clients_count,
    'review_title' => $review_title,
    'review_icon' => $review_icon,
    'satisfied_customer_show_hide' => $satisfied_customer_show_hide,
    'satisfied_customer_title' => $satisfied_customer_title,
    'satisfied_customer_images' => $satisfied_customer_images,
    'button_one_title' => $button_one_title,
    'button_two_title' => $button_two_title,
    'button_one_link' => $button_one_link,
    'button_two_link' => $button_two_link,
    'button_one_show_hide' => $button_one_show_hide,
    'button_two_show_hide' => $button_two_show_hide,
    'padding_top' => $padding_top,
    'padding_bottom' => $padding_bottom
]);

}
    public function addon_title()
    {
        return __('Header: 01');
    }
}