<?php

namespace App\PageBuilder\Addons\Service;

use App\Category;
use App\ChildCategory;
use App\Country;
use App\PageBuilder\Fields\Switcher;
use App\ServiceArea;
use App\ServiceCity;
use App\Subcategory;
use App\PageBuilder\PageBuilderBase;
use App\PageBuilder\Fields\Number;
use App\PageBuilder\Fields\Select;
use App\PageBuilder\Fields\Slider;
use App\PageBuilder\Fields\Text;
use App\Service;
use App\User;
use App\Zone;
use Monolog\Handler\IFTTTHandler;
use Request;
use Str;
use URL;

class ServiceListOne extends PageBuilderBase
{
    public function preview_image()
    {
        return "service/service_list_google_map.jpg";
    }

    public function admin_render()
    {
        $output = $this->admin_form_before();
        $output .= $this->admin_form_start();
        $output .= $this->default_fields();
        $widget_saved_values = $this->get_settings();

        $output .= Select::get([
            "name" => "order_by",
            "label" => __("Order By"),
            "options" => [
                "id" => __("ID"),
                "created_at" => __("Date"),
            ],
            "value" => $widget_saved_values["order_by"] ?? null,
            "info" => __("set order by"),
        ]);
        $output .= Select::get([
            "name" => "order",
            "label" => __("Order"),
            "options" => [
                "asc" => __("Accessing"),
                "desc" => __("Decreasing"),
            ],
            "value" => $widget_saved_values["order"] ?? null,
            "info" => __("set order"),
        ]);
        $output .= Number::get([
            "name" => "items",
            "label" => __("Items"),
            "value" => $widget_saved_values["items"] ?? null,
            "info" => __("enter how many item you want to show in frontend"),
        ]);

        $output .= Select::get([
            "name" => "columns",
            "label" => __("Column"),
            "options" => [
                "col-lg-3" => __("04 Column"),
                "col-lg-4" => __("03 Column"),
                "col-lg-6" => __("02 Column"),
                "col-lg-12" => __("01 Column"),
            ],
            "value" => $widget_saved_values["columns"] ?? null,
            "info" => __("set column"),
        ]);

        $output .= Slider::get([
            "name" => "padding_top",
            "label" => __("Padding Top"),
            "value" => $widget_saved_values["padding_top"] ?? 110,
            "max" => 200,
        ]);
        $output .= Slider::get([
            "name" => "padding_bottom",
            "label" => __("Padding Bottom"),
            "value" => $widget_saved_values["padding_bottom"] ?? 110,
            "max" => 200,
        ]);

        // service filtering option on/off start
        $output .= Switcher::get([
            "name" => "location_on_off",
            "label" => __("Location"),
            "value" => $widget_saved_values["location_on_off"] ?? null,
            "info" => __("Location wise Service Filtering Hide/Show"),
        ]);

        $output .= Switcher::get([
            "name" => "price_range_on_off",
            "label" => __("Price range"),
            "value" => $widget_saved_values["price_range_on_off"] ?? null,
            "info" => __("Price range wise Service Filtering Hide/Show"),
        ]);

        $output .= Switcher::get([
            "name" => "country_on_off",
            "label" => __("Country"),
            "value" => $widget_saved_values["country_on_off"] ?? null,
            "info" => __("Country wise Service Filtering Hide/Show"),
        ]);

        $output .= Switcher::get([
            "name" => "city_on_off",
            "label" => __("City"),
            "value" => $widget_saved_values["city_on_off"] ?? null,
            "info" => __("City wise Service Filtering Hide/Show"),
        ]);

        $output .= Switcher::get([
            "name" => "area_on_off",
            "label" => __("Area"),
            "value" => $widget_saved_values["area_on_off"] ?? null,
            "info" => __("Area wise Service Filtering Hide/Show"),
        ]);

        $output .= Switcher::get([
            "name" => "service_search_by_text_on_off",
            "label" => __("Service search"),
            "value" =>
                $widget_saved_values["service_search_by_text_on_off"] ?? null,
            "info" => __("Service search Hide/Show"),
        ]);

        $output .= Switcher::get([
            "name" => "category_on_off",
            "label" => __("Category"),
            "value" => $widget_saved_values["category_on_off"] ?? null,
            "info" => __("Category wise Service Filtering Hide/Show"),
        ]);

        $output .= Switcher::get([
            "name" => "subcategory_on_off",
            "label" => __("SubCategory"),
            "value" => $widget_saved_values["subcategory_on_off"] ?? null,
            "info" => __("SubCategory wise Service Filtering Hide/Show"),
        ]);

        $output .= Switcher::get([
            "name" => "child_category_on_off",
            "label" => __("Child Category"),
            "value" => $widget_saved_values["child_category_on_off"] ?? null,
            "info" => __("Child Category wise Service Filtering Hide/Show"),
        ]);

        $output .= Switcher::get([
            "name" => "rating_on_off",
            "label" => __("Rating Star"),
            "value" => $widget_saved_values["rating_on_off"] ?? null,
            "info" => __("Rating Star wise Service Filtering Hide/Show"),
        ]);

        $output .= Switcher::get([
            "name" => "sort_by_on_off",
            "label" => __("Sort By Star"),
            "value" => $widget_saved_values["sort_by_on_off"] ?? null,
            "info" => __("Sort By Service Filtering Hide/Show"),
        ]);
        // service filtering option on/off end

        $output .= Text::get([
            "name" => "country",
            "label" => __("Country Title Text"),
            "value" => $widget_saved_values["country"] ?? null,
        ]);

        $output .= Text::get([
            "name" => "city",
            "label" => __("City Title Text"),
            "value" => $widget_saved_values["city"] ?? null,
        ]);
        $output .= Text::get([
            "name" => "area",
            "label" => __("Area Title Text"),
            "value" => $widget_saved_values["area"] ?? null,
        ]);

        $output .= Text::get([
            "name" => "service_search_by_text",
            "label" => __("Search Title Text"),
            "value" => $widget_saved_values["service_search_by_text"] ?? null,
        ]);

        $output .= Text::get([
            "name" => "category",
            "label" => __("Category Title Text"),
            "value" => $widget_saved_values["category"] ?? null,
        ]);

        $output .= Text::get([
            "name" => "subcategory",
            "label" => __("Subcategory Title Text"),
            "value" => $widget_saved_values["subcategory"] ?? null,
        ]);

        $output .= Text::get([
            "name" => "child_category",
            "label" => __("Child Category Title Text"),
            "value" => $widget_saved_values["child_category"] ?? null,
        ]);

        $output .= Text::get([
            "name" => "book_now",
            "label" => __("Book Now Text"),
            "value" => $widget_saved_values["book_now"] ?? null,
        ]);
        $output .= Text::get([
            "name" => "read_more",
            "label" => __("View Details Text"),
            "value" => $widget_saved_values["read_more"] ?? null,
        ]);

        $output .= $this->admin_form_submit_button();
        $output .= $this->admin_form_end();
        $output .= $this->admin_form_after();

        return $output;
    }

    public function frontend_render()
    {
        $settings = $this->get_settings();
        $order_by = $settings["order_by"] ?? "";
        $IDorDate = $settings["order"] ?? "";
        $items = $settings["items"] ?? "";
        $columns = $settings["columns"] ?? "";
        $padding_top = $settings["padding_top"] ?? "";
        $padding_bottom = $settings["padding_bottom"] ?? "";

        //Service Filtering Hide/Show
        $location_on_off = $settings["location_on_off"] ?? "";
        $price_range_on_off = $settings["price_range_on_off"] ?? "";
        $country_on_off = $settings["country_on_off"] ?? "";
        $city_on_off = $settings["city_on_off"] ?? "";
        $area_on_off = $settings["area_on_off"] ?? "";
        $service_search_by_text_on_off =  $settings["service_search_by_text_on_off"] ?? "";
        $category_on_off = $settings["category_on_off"] ?? "";
        $subcategory_on_off = $settings["subcategory_on_off"] ?? "";
        $child_category_on_off = $settings["child_category_on_off"] ?? "";
        $rating_star_on_off = $settings["rating_on_off"] ?? "";
        $sort_by_on_off = $settings["sort_by_on_off"] ?? "";

        $country_text = $settings["country"] ?? __("Select Country");
        $city_text = $settings["city"] ?? __("Select City");
        $area_text = $settings["area"] ?? __("Select Area");
        $search_placeholder = $settings["service_search_by_text"] ??  __("What are you looking for?");

        $category_text = $settings["category"] ?? __("Select Category");
        $subcategory_text = $settings["subcategory"] ?? __("Select Subcategory");
        $child_category_text = $settings["child_category"] ?? __("Select Child Category");
        $book_now_text = $settings["book_now"] ?? __("Book Now");
        $read_more_text = $settings["read_more"] ?? __("View Details");

        $text_search_value = request()->get("q") ?? request()->get("home_search");
        $service_quyery = Service::query()->where("status", 1);
        $service_quyery->with("reviews","seller");

        // google map autocomplete address, current location wise filter
        $remote_task_title = '';
        $all_button_filter_value = '';
        $in_person_filter_value = '';
        $autocomplete_address = request()->get('autocomplete_address');
        $location_city_name = request()->get('location_city_name');
        $online_check_service = request()->get('remotely_button_filter');

        if(!empty(get_static_option("google_map_settings"))) {
            if (!empty(request()->get('all_button_filter_value'))) {
                $service_quyery->where('status', 1);
                $all_button_filter_value = 'all_filter_jobs';
            } elseif (!empty(request()->get('in_person_filter_value'))) {
                $service_quyery->where('is_service_online', 0);
                $in_person_filter_value = 'in_person';
            } elseif (!empty(request()->get('remotely_button_filter'))) {
                $autocomplete_address = '';
                $location_city_name = '';
                $remote_task_title = __('Remote tasks only');
                $service_quyery->where('is_service_online', 1);
            }
        }


        // lat long wise filter
        $latitude = request()->get('latitude');
        $longitude = request()->get('longitude');

        $radius = 0;
        if(!empty(get_static_option("google_map_settings"))){
            if(empty(request()->get('remotely_button_filter'))){
                if(!empty(request()->get('latitude')) && !empty(request()->get('longitude'))){
                    // Calculate the radius in kilometers (adjust as needed)
                    $distance_radius_km_get = request()->get('distance_kilometers_value');
                    $distance_radius_km = (int) $distance_radius_km_get;
                    if($distance_radius_km == 0){
                        $radius = 50;
                    }else{
                        $radius = $distance_radius_km;
                    }

                    // Use a subquery to calculate the distance
                    $service_quyery->join('users', 'services.seller_id', '=', 'users.id') // Join the service table with the user table
                    ->selectRaw(
                        "services.*,
                            (6371 * acos(
                                cos(radians(?)) * cos(radians(users.latitude)) * cos(radians(users.longitude) - radians(?)) +
                                sin(radians(?)) * sin(radians(users.latitude))
                            )) AS distance",
                        [$latitude, $longitude, $latitude]
                    )
                        ->havingRaw('distance <= ?', [$radius])
                        ->orderBy('distance', 'asc');
                }
            }
        }


        // Filter by price range value
        $service_min_main_price_set = Service::select('id', 'price', 'online_service_price')->get();
        $max_price = max(
            intval($service_min_main_price_set->max('price')),
            intval($service_min_main_price_set->max('online_service_price'))
        );
        $max_price_start_value = $max_price ?? '10000';
        $min_price = '1';
        $max_price = $max_price ?? '10000';
        if (!empty(request()->get('price_range_value'))) {
            $priceRange = request()->get('price_range_value');
            list($minPrice, $maxPrice) = explode(',', $priceRange);
            $service_quyery->whereBetween('price', [$minPrice, $maxPrice]);
            $min_price = $minPrice;
            $max_price = $maxPrice;
        }

        // get country
        if (!empty(request()->get("country"))) {
            $service_country = Country::find(request()->get("country"));
            $service_country_ids = $service_country->cities->pluck("id")->toArray();
            $service_quyery->whereIn("service_city_id", $service_country_ids)->get();
        }

        // get city
        if (!empty(request()->get("city"))) {
            $service_quyery->where("service_city_id", request()->get("city"));
        }

        // get area
        if (!empty(request()->get("area"))) {
            $service_quyery->where("service_area_id", request()->get("area"))->get();
        }

        if (!empty(request()->get("q")) || !empty(request()->get("home_search"))) {
            $search_text = request()->get("home_search", request()->get("q"));
            $service_quyery->where("title", "LIKE", "%" . $search_text . "%")
                ->orWhere(
                    "description",
                    "LIKE",
                    "%" . $search_text . "%"
                );
        }

        if (!empty(request()->get("cat"))) {
            $service_quyery->where("category_id", request()->get("cat"));
        }

        if (!empty(request()->get("subcat"))) {
            $service_quyery->where("subcategory_id", request()->get("subcat"));
        }

        if (!empty(request()->get("child_cat"))) {
            $service_quyery->where(
                "child_category_id",
                request()->get("child_cat")
            );
        }

        if (!empty(request()->get("rating"))) {
            $rating = (int) request()->get("rating");
            $service_quyery->whereHas("reviews", function ($q) use ($rating) {
                $q->groupBy("reviews.id")
                    ->havingRaw("AVG(reviews.rating) >= ?", [$rating])
                    ->havingRaw("AVG(reviews.rating) < ?", [$rating + 1]);
            });
        }


        // no remove
        $rating_stars = [
            "1" => __("One Star"),
            "2" => __("Two Star"),
            "3" => __("Three Star"),
            "4" => __("Four Star"),
            "5" => __("Five Star"),
        ];

        if (!empty(request()->get("sortby"))) {
            if (request()->get("sortby") == "latest_service") {
                $service_quyery->orderBy("id", "Desc");
            }
            if (request()->get("sortby") == "lowest_price") {
                $service_quyery->orderBy("price", "Asc");
            }
            if (request()->get("sortby") == "highest_price") {
                $service_quyery->orderBy("price", "Desc");
            }
            if (request()->get("sortby") == "best_selling") {
                $service_quyery->orderBy("sold_count", "Desc");
            }
            if (request()->get("sortby") == "featured") {
                $service_quyery->where("featured", 1);
            }

            if (request()->get("sortby") == "popular") {
                $service_quyery->with('reviews')->orderBy('view','DESC');
            }

            if (request()->get("sortby") == "1") {
                $service_quyery->where("is_service_online", 1);
            }
        }


        // no remove
        $sortby_search = [
            "latest_service" => __("Latest Service"),
            "lowest_price" => __("Lowest Price"),
            "highest_price" => __("Highest Price"),
            "best_selling" => __("Best Selling Service"),
            "featured" => __("Featured Service"),
            "popular" => __("Popular Service"),
            "1" => __("Online Service"),
        ];


        //todo add filter for check seller has set his zone or not
        $all_services = $service_quyery->where('status', 1)
            ->where('is_service_on', 1)
            ->when(subscriptionModuleExistsAndEnable('Subscription'),function($q){
                // Check if the subscription module exists and is enabled
                $q->whereHas('seller_subscription');
            })
            ->OrderBy($order_by,$IDorDate)->paginate($items);

        $countries = Country::select("id", "country")
            ->where("status", 1)
            ->get();
        $categories = Category::select("id", "name")
            ->where("status", 1)
            ->get();

        $service_markup = "";
        $pagination = $all_services->links();
        $total_service = $all_services->total();
        //static text helpers
        $static_text = static_text();
        $no_service_found = __("No Service Found");


        // no remove
        $google_map_style_class = "";
        $map_showing_btn = "";
        if (!empty(get_static_option("google_map_settings"))) {
            $google_map_style_class = "service-map-style";
            $map_showing_btn = "d-none";
        }

        // no remove need for city
        if(request()->get("country")){
            $country_id = request()->get("country");
        }else{
            $find_country_id = ServiceCity::where('id', request()->get("city"))->first();
            $country_id = $find_country_id->country_id ?? 0;
        }

        $services_city = ServiceCity::select("id", "service_city", "status")->where("status", 1)->where("country_id", $country_id)->get();

        //no remove need for area
        $service_city_id = request()->get("city");
        $services_area = ServiceArea::select("id", "service_area", "status")->where("status", 1)->where("service_city_id", $service_city_id)->get();

        // todo: first check if country and city is disable get area list
        if (empty($country_on_off) && empty($city_on_off)){
            $services_area = ServiceArea::select("id", "service_area", "status")->where("status", 1)->get();
        }

        //no remove sub category
        $category_id = request()->get("cat");
        $sub_categories = Subcategory::select("id", "name")
            ->where("status", 1)
            ->where("category_id", $category_id)
            ->get();

        // no remove child category
        $sub_category_id = request()->get("subcat");
        $child_categories = ChildCategory::select("id", "name")
            ->where("status", 1)
            ->where("sub_category_id", $sub_category_id)
            ->get();

        $current_page_url = URL::current();


        // google map section start
        if (!empty(get_static_option("google_map_settings"))) {
            $services = $all_services;
            $map_services = $services->makeHidden(["created_at", "updated_at"]);

            // service return with image and price render
            $map_services_with_image_url = $map_services->map(function (
                $service
            ) {
                $imageUrl = render_background_image_markup_by_attachment_id(
                    $service->image
                );
                $service_main_price = custom_amount_with_currency_symbol(
                    $service->price
                );
                $service->image_url = $imageUrl;
                $service->service_main_price = $service_main_price;

                if ($service->is_service_online == 1) {
                    $service_service_location = __("Online Service");
                } else {
                    $service_service_location = sellerServiceLocation($service);
                }
                return $service;
            });

            $all_services_list_json = $map_services_with_image_url;
            $google_api_key = get_static_option("service_google_map_api_key");
            //route list
            $service_details_route = route("service.list.details", "");
            $service_book_route = route("service.list.book", "");

        }else{
            $all_services_list_json = '';
            $google_api_key = '';
            $service_details_route = '';
            $service_book_route = '';
            $latitude = '';
            $longitude = '';
        }

        // service list page url
        $url_search_services_list = get_static_option('select_home_page_search_service_page_url') ?? '/service-list';

        return $this->renderBlade('service.service-list-one',[
            'url_search_services_list' => $url_search_services_list,
            'padding_top' => $padding_top,
            'padding_bottom' => $padding_bottom,
            'columns' => $columns,
            'area_on_off' => $area_on_off,
            'city_on_off' => $city_on_off,
            'service_search_by_text_on_off' => $service_search_by_text_on_off,
            'category_on_off' => $category_on_off,
            'subcategory_on_off' => $subcategory_on_off,
            'child_category_on_off' => $child_category_on_off,
            'rating_star_on_off' => $rating_star_on_off,
            'sort_by_on_off' => $sort_by_on_off,
            'google_map_style_class' => $google_map_style_class,
            'map_showing_btn' => $map_showing_btn,
            'static_text' => $static_text,
            'book_now_text' => $book_now_text,
            'country_on_off' => $country_on_off,
            'country_text' => $country_text,
            'countries' => $countries,
            'categories' => $categories,
            'city_text' => $city_text,
            'area_text' => $area_text,
            'search_placeholder' => $search_placeholder,
            'text_search_value' => $text_search_value,
            'category_text' => $category_text,
            'subcategory_text' => $subcategory_text,
            'sub_categories' => $sub_categories,
            'child_category_text' => $child_category_text,
            'child_categories' => $child_categories,
            'rating_stars' => $rating_stars,
            'sortby_search' => $sortby_search,
            'services_city' => $services_city,
            'services_area' => $services_area,
            'current_page_url' => $current_page_url,
            // google map
            'all_services_list_json' => $all_services_list_json,
            'google_api_key' => $google_api_key,
            'service_details_route' => $service_details_route,
            'service_book_route' => $service_book_route,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'all_services' => $all_services,

            // for goolge map filter
            'remote_task_title' => $remote_task_title,
            'all_button_filter_value' => $all_button_filter_value,
            'in_person_filter_value' => $in_person_filter_value,
            'autocomplete_address' => $autocomplete_address,
            'location_city_name' => $location_city_name,
            'radius' => $radius,
            'online_check_service' => $online_check_service,
            'min_price' => $min_price,
            'max_price' => $max_price,
            'location_on_off' => $location_on_off,
            'price_range_on_off' => $price_range_on_off,
            'max_price_start_value' => $max_price_start_value
        ]);

    }

    public function addon_title()
    {
        return __("Service List: 1");
    }
}
