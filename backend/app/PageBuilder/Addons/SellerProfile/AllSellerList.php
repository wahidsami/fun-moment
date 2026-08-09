<?php


namespace App\PageBuilder\Addons\SellerProfile;

use App\Country;
use App\Order;
use App\PageBuilder\Fields\ColorPicker;
use App\PageBuilder\Fields\Number;
use App\PageBuilder\Fields\Select;
use App\PageBuilder\Fields\Slider;
use App\PageBuilder\Fields\Text;
use App\PageBuilder\Fields\Textarea;
use App\PageBuilder\Traits\LanguageFallbackForPageBuilder;
use App\Review;
use App\User;
use phpDocumentor\Reflection\Types\Context;

class AllSellerList extends \App\PageBuilder\PageBuilderBase
{
    use LanguageFallbackForPageBuilder;

    public function preview_image()
    {
        return 'seller/all_seller_list.jpg';
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
            'info' => __('enter title')
        ]);

        $output .= Textarea::get([
            'name' => 'description',
            'label' => __('Description'),
            'value' => $widget_saved_values['description'] ?? null,
            'info' => __('enter description')
        ]);
        
        $output .= Select::get([
            'name' => 'order_by',
            'label' => __('Order By'),
            'options' => [
                'id' => __('ID'),
                'created_at' => __('Date'),
            ],
            'value' => $widget_saved_values['order_by'] ?? null,
            'info' => __('set order by')
        ]);
        $output .= Select::get([
            'name' => 'order',
            'label' => __('Order'),
            'options' => [
                'asc' => __('Accessing'),
                'desc' => __('Decreasing'),
            ],
            'value' => $widget_saved_values['order'] ?? null,
            'info' => __('set order')
        ]);

        $output .= Select::get([
            'name' => 'review_or_monthly_order_seller',
            'label' => __('Review wise or monthly Order wise seller show'),
            'options' => [
                'all_seller_list' => __('All Sellers'),
                'seller_review' => __('Review wise'),
                'monthly_order' => __('Monthly Order wise'),
            ],
            'value' => $widget_saved_values['review_or_monthly_order_seller'] ?? null,
            'info' => __('set review wise or monthly order wise seller show')
        ]);


        $output .= Number::get([
            'name' => 'items',
            'label' => __('Items'),
            'value' => $widget_saved_values['items'] ?? null,
            'info' => __('enter how many item you want to show in frontend'),
        ]);

        $output .= Slider::get([
            'name' => 'padding_top',
            'label' => __('Padding Top'),
            'value' => $widget_saved_values['padding_top'] ?? 100,
            'max' => 500,
        ]);
        $output .= Slider::get([
            'name' => 'padding_bottom',
            'label' => __('Padding Bottom'),
            'value' => $widget_saved_values['padding_bottom'] ?? 100,
            'max' => 500,
        ]);
        $output .= ColorPicker::get([
            'name' => 'section_bg',
            'label' => __('Background Color'),
            'value' => $widget_saved_values['section_bg'] ?? null,
            'info' => __('select color you want to show in frontend'),
        ]);

        $output .= $this->admin_form_submit_button();
        $output .= $this->admin_form_end();
        $output .= $this->admin_form_after();

        return $output;
    }

    public function frontend_render() : string
    {
        $settings = $this->get_settings();
        $order_by =$settings['order_by'] ?? '';
        $IDorDate =$settings['order'] ?? '';
        $items =$settings['items'] ?? 0;
        $padding_top = $settings['padding_top'] ?? '';
        $padding_bottom = $settings['padding_bottom'] ?? '';
        $section_bg = $settings['section_bg'] ?? '';
        $section_title = $settings['title'] ?? '';
        $description = $settings['description'] ?? '';
        $review_or_order_wise_seller_show = $settings['review_or_monthly_order_seller'] ?? '';


        if ($review_or_order_wise_seller_show == 'all_seller_list') {
            $topSellerIds = User::select('id', 'user_type', 'user_status')->where('user_type', 0)->where('user_status', 1)->pluck('id')->toArray();
        }elseif ($review_or_order_wise_seller_show == 'seller_review'){
             $get_seller_reviews = Review::select('id', 'seller_id','rating')->where('rating', '>', 1)->get();
             $topSellerIds = $get_seller_reviews->unique('seller_id')->pluck('seller_id')->toArray();
        }else{
            $currentMonthStartDate = now()->startOfMonth(); // Get the first day of the current month
            $currentMonthEndDate = now()->endOfMonth();    // Get the last day of the current month
            $topSellers = Order::where('status', 2)->whereBetween('created_at', [$currentMonthStartDate, $currentMonthEndDate])->get();
            $topSellerIds = $topSellers->pluck('seller_id')->toArray();
        }


        $countries = Country::select('id', 'country')->where('status', 1)->get();

        $seller_quyery = User::query();
        // filtering start
        if (!empty(request()->get("q"))) {
            $seller_quyery
                ->where("name", "LIKE", "%" . request()->get("q") . "%")
                ->orWhere(
                    "about",
                    "LIKE",
                    "%" . request()->get("q") . "%"
                );
        }
        if (!empty(request()->get("country_id"))) {
            $seller_country_id = Country::find(request()->get("country_id"));
            $seller_quyery->where("country_id", $seller_country_id->id)->get();
        }
        // filtering end

        $seller_lists = $seller_quyery->whereIn('id', $topSellerIds)->where(['user_type'=>0,'user_status' => 1])->orderBy($order_by,$IDorDate)->paginate($items);

        return $this->renderBlade('seller.all-sellers',[
            'padding_top' => $padding_top,
            'padding_bottom' => $padding_bottom,
            'section_bg' => $section_bg,
            'section_title' => $section_title,
            'description' => $description,
            'seller_lists' => $seller_lists,
            'review_or_order_wise_seller_show' => $review_or_order_wise_seller_show,
            'countries' => $countries
        ]);
    }
    public function addon_title()
    {
        return __('All Seller List');
    }
}