@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/14.6.3/nouislider.min.css">
    <style>
        /*loader css start */
        .all_location_new_btn.btn-primary {
            background-color: var(--main-color-one);
            border-color: var(--main-color-one);
        }
        .loader-container {
            position: relative;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #map-container {
            display: none; /* Initially hide the map container */
        }
        /*loader css end */

        #ratingCollapse {
            display: grid;
            gap: 4px;
        }
        .common-title {
            font-size: 16px;
            line-height: 21px;
            font-weight: 700;
        }
        .service_filter_with_reset{
            display: flex;
            gap: 56PX;
        }

        /* for google map visible content not empty marker show */
        .single-service.service-map-style.no-margin.wow {
            visibility: visible !important;
        }

        /* Add your own custom styling here */
        .wrapper {
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        .m-b-50 {
            margin-bottom: 50px;
        }

        .m-b-20 {
            margin-bottom: 20px;
        }
        .m-t-50 {
            margin-top: 50px;
        }

        .p-l-r{
            padding: 0 30px;
        }

        .tooltipdiv {
            display: block;
            position: absolute;
            bottom: 35px;
            left: 50%;
            transform: translateX(-50%);
            border: 1px solid #D9D9D9;
            border-radius: 3px;
            background: #fff;
            color: #000;
            padding: 5px;
            text-align: center;
            white-space: nowrap;
        }

        .noUi-value{
            margin-top: 10px;
        }


        /* Filter online offline service button bg color change start */
        .address-input-background-color {
            background-color: rgb(230, 231, 238) !important;
        }
        .filter_button_active{
            background-color: rgb(6, 18, 87);
        }

       /*google map wise filter button */
        .submit-btn {
            border: 2px solid var(--main-color-one);
            background-color: var(--main-color-one);
            color: var(--white);
            padding: 3px 20px;
            -webkit-transition: 300ms;
            transition: 300ms;
            border-radius: 5px;
        }

        .gm-style-iw.gm-style-iw-c{
            padding-right: 0px!important;
            padding-bottom: 0px!important;
            max-width: 191px!important;
            max-height: 208px!important;
            min-width: 0px!important;
        }

        /* google map section css start*/
        @if (!empty(get_static_option("google_map_settings")))
             .new_service__single__contents__title {
            font-size: 13px;
            font-weight: 600;
            line-height: 1.3;
            color: var(--new-heading-color);
            -webkit-transition: all 0.3s;
            transition: all 0.3s;

            /* Limit to 2 lines of text */
            max-height: 2.6em; /* 2 lines * line-height */
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2; /* Number of lines to show */
            -webkit-box-orient: vertical;
        }

        .new_jobs__single__contents__location {
            display: -webkit-box;
            display: -ms-flexbox;
            display: flex;
            -webkit-box-align: center;
            -ms-flex-align: center;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            color: var(--main-color-one);

            /* Limit to 2 lines of text */
            max-height: 34px; /* 2 lines * line-height (17px) */
            overflow: hidden;
            text-overflow: ellipsis;
            -webkit-line-clamp: 2; /* Number of lines to show */
            -webkit-box-orient: vertical;
        }

        .new_service__single__price__title {
            font-size: 18px;
        }


        .new_service__single__thumb {
            height: 133px;
          }

        .new_service__single__price {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 5px;
        }

        .author_tag.border_top {
            margin: 0;
            padding: 0;
        }
        .btn-wrapper.border_top {
            margin: 0;
            padding: 0px;
            padding-top: 5px;
            margin-top: 5px;
        }

        .btn-wrapper .cmn-btn {
            padding: 6px 35px;
        }

        .new_service__single__thumb img.no-image {
            height: 147px;
        }
        @endif

      .new_serviceDetails__side__author {
            border-radius: 5px;
            padding: 10px;
            margin: 10px;
            padding-bottom: 0;
            background-color: #f7f7f7;
        }

        .form--control, .form-control {
          background-color: #FFFFFF;
        }

        /* new ======================*/
        .price-input{
            width: 100%;
            display: flex;
            margin: 30px 0 35px;
        }
        .price-input .field{
            display: flex;
            width: 100%;
            height: 45px;
            align-items: center;
            flex-direction: column-reverse;
            margin: 5px;
        }
        .field input{
            width: 100%;
            height: 100%;
            outline: none;
            font-size: 19px;
            margin-left: 3px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #e3e0e0;
            -moz-appearance: textfield;
        }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
        }
        .price-input .separator{
            width: 130px;
            display: flex;
            font-size: 19px;
            align-items: center;
            justify-content: center;
            align-items: flex-start;
        }

        .price_range_setup{
            background: #FFFFFF;
        }
        .price_range_setup .progress {
            background: #ddd;
            height: 10px;
            overflow: unset;
        }
        .slider-kilometer .slider-range {
            height: 8px;
            background: #ddd;
        }
        .noUi-handle:after,
        .noUi-handle:before {
            display: none;
        }
        .noUi-touch-area {
            height: 100%;
            width: 100%;
            background: var(--main-color-one);
            border-radius: 50%;
        }
        .noUi-pips-horizontal {
            padding: 10px 0;
            height: 80px;
            top: 100%;
            left: 0;
            width: 100%;
            visibility: hidden;
            opacity: 0;
        }
        .noUi-connect {
            background: gray;
        }
        .noUi-horizontal .noUi-handle {
            width: 20px;
            height: 20px;
            right: -10px;
            top: -6px;
            border-radius: 50%;
        }
        .range-input{
            position: relative;
        }
        .range-input input{
            position: absolute;
            width: 100%;
            height: 5px;
            top: -5px;
            background: none;
            pointer-events: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        input[type="range"]::-webkit-slider-thumb{
            height: 17px;
            width: 17px;
            border-radius: 50%;
            background: #17A2B8;
            pointer-events: auto;
            -webkit-appearance: none;
            box-shadow: 0 0 6px rgba(0,0,0,0.05);
        }
        input[type="range"]::-moz-range-thumb{
            height: 17px;
            width: 17px;
            border: none;
            border-radius: 50%;
            background: #17A2B8;
            pointer-events: auto;
            -moz-appearance: none;
            box-shadow: 0 0 6px rgba(0,0,0,0.05);
        }

        .min_price_range{
            display: flex;
            align-items: center;
        }
        .max_price_range{
            display: flex;
            align-items: center;
        }

        .site_currency_symbol{
          font-size: 16px;
        }

    </style>
@endsection
<!-- Service area starts -->
<section class="new_services_area padding-top-100 padding-bottom-100">
    <div class="container">
        <form method="get" action="{{$current_page_url}}" id="search_service_list_form">
            <div class="row">
                <!--Service Filtering Section Start -->
                <div class="col-xl-3">
                    <div class="new_serviceDetails__side">
                        <div class="new_serviceDetails__side__item">

                            <div class="service_filter_with_reset mb-3">
                                <h5 class="common-title">{{ __('Service Filter') }} </h5>
                               <a href="{{ url($url_search_services_list) }}">
                                <strong class="text-danger">{{ __('Reset Filter') }} </strong>
                               </a>
                            </div>

                            <!--Search any title filter start -->
                            @if(!empty($service_search_by_text_on_off))
                                <div class="new_serviceDetails__side__author">
                                    <div class="new_serviceDetails__side__author__contents">
                                        <h6 class="new_packageBook__addFeature__title">
                                            <a href="#textCollapse" data-bs-toggle="collapse" aria-expanded="true">
                                                {{ __('Search By text') }} <i class="las la-angle-down"></i> </a>
                                        </h6>
                                        <div class="collapse show" id="textCollapse">
                                            <div class="single-category-service">
                                                <div class="single-select">
                                                    <input type="text" class="search-input form-control" id="search_by_query"
                                                           placeholder="{{$search_placeholder}}" name="q" value="{{$text_search_value}}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <!--Search any title filter end -->

                            <!--Distance google map filter -->
                            @if(!empty($location_on_off))
                             @if (!empty(get_static_option("google_map_settings")))
                                <div class="new_serviceDetails__side__author">
                                    <div class="new_serviceDetails__side__author__contents">
                                        <h6 class="new_packageBook__addFeature__title">
                                            <a href="#distanceCollapse" data-bs-toggle="collapse" aria-expanded="true">  {{ __('Search By Location') }}
                                                <i class="las la-angle-down"></i> </a>
                                        </h6>

                                        <div class="collapse show" id="distanceCollapse">
                                            <!-- In person, remotely, all-->
                                            <div class="job_status_wise_section_start mt-2">
                                                <input type="hidden" name="remotely_button_filter" id="remotely_button_filter_value" value="{{$remote_task_title}}">
                                                <input type="hidden" name="all_button_filter_value" id="all_button_filter_value" value="{{$all_button_filter_value}}">
                                                <input type="hidden" name="in_person_filter_value" id="in_person_filter_value" value="{{$in_person_filter_value}}">
                                                <button type="button" class="all_location_new_btn @if(!empty($in_person_filter_value)) btn btn-primary btn-sm @else btn btn-secondary btn-sm  @endif in_person_button_filter">{{ __('Offline') }} </button>
                                                <button type="button" class="all_location_new_btn @if(!empty($remote_task_title)) btn btn-primary btn-sm @else btn btn-secondary btn-sm @endif remotely_button_filter" >{{ __('Online') }}</button>
                                                <button type="button" class="all_location_new_btn @if(!empty($all_button_filter_value)) btn btn-primary btn-sm @elseif(empty($remote_task_title) && empty($in_person_filter_value)) btn btn-primary btn-sm @else btn btn-secondary btn-sm @endif
                                               all_button_filter">{{ __('All') }} </button>
                                            </div>

                                            <!-- autocomplete address -->
                                            <div class="suburb_section_start mt-2 mb-3">
                                                <input type="hidden" name="autocomplete_address" id="autocomplete_address">
                                                <input type="hidden" name="location_city_name" id="location_city_name">
                                                <input type="hidden" name="latitude" id="latitude">
                                                <input type="hidden" name="longitude" id="longitude">
                                                <label>{{ __('Location') }}</label>
                                                <input class="search-input form-control w-100 border-1 bg-white autocomplete_disable" name="autocomplete" id="autocomplete" placeholder="{{ __('Enter a Location') }}" type="text">
                                            </div>

                                            <!-- Distance range-->
                                            <div id="distance-slider"></div>
                                            <div class="slider-container slider-kilometer m-2">
                                                <input type="hidden" name="distance_kilometers_value" id="distance_kilometers_value">
                                                <strong class="mb-2">{{__('Distance')}}</strong>
                                                <div id="slider" class="slider-range mt-2"></div>
                                                <div id="slider-value" class="slider-range-value mt-2"></div>
                                                <span class="km_title_text" style="display: flex; margin-left: 23px; margin-top: -21px;">{{ __('km') }}</span>
                                            </div>

                                            <!-- cancel and apply button start -->
                                            <div class="cancel_apply_section_start text-end mb-2">
                                                <button type="button" class="submit-btn btn-sm" id="distance_wise_filter_apply">{{ __('Filter') }}</button>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                              @endif
                            @endif
                            <!--google map Distance filter end -->

                            <!--price range filter -->
                            @if(!empty($price_range_on_off))
                                <div class="new_serviceDetails__side__author">
                                    <div class="new_serviceDetails__side__author__contents">
                                        <h6 class="new_packageBook__addFeature__title">
                                            <a href="#distanceCollapseAnyPrice" data-bs-toggle="collapse" aria-expanded="false" class="toggle-collapse">
                                                {{ __('Search By Price') }} <i class="las la-angle-down"></i>
                                            </a>
                                        </h6>

                                        <!--hasib -->
                                        <div class="collapse show" id="distanceCollapseAnyPrice">
                                            <input type="hidden" name="price_range_value" id="price_range_value">
                                            <div class="price-input">
                                                <div class="field">
                                                    <span>{{ __('Min') }}  </span>
                                                      <div class="min_price_range">
                                                          <span class="site_currency_symbol">{{ site_currency_symbol() }}</span>
                                                          <input type="number" class="input-min">
                                                      </div>
                                                </div>
                                                <div class="separator">-</div>
                                                <div class="field">
                                                    <span>{{__('Max')}} </span>
                                                    <div class="max_price_range">
                                                        <span class="site_currency_symbol">{{ site_currency_symbol() }}</span>
                                                        <input type="number" class="input-max">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="price_range_setup">
                                                <div class="progress"></div>
                                            </div>
                                            <!-- cancel and apply button start -->
                                            <div class="cancel_apply_section_start text-end mt-3 mb-2">
                                                <button type="button" class="submit-btn btn-sm" id="price_wise_filter_apply">{{ __('Filter') }}</button>
                                            </div>
                                            <!-- End of cancel and apply button -->
                                        </div>

                                    </div>
                                </div>
                            @endif
                            <!--price range filter end -->

                            <!--Country filter start -->
                            @if(empty(get_static_option("google_map_settings")))
                                @if(!empty($country_on_off))
                                <div class="new_serviceDetails__side__author">
                                    <div class="new_serviceDetails__side__author__contents">
                                        <h6 class="new_packageBook__addFeature__title">
                                            <a href="#countryCollapse" data-bs-toggle="collapse" aria-expanded="true">  {{ __('Search By Country') }} <i class="las la-angle-down"></i> </a>
                                        </h6>
                                        <div class="collapse show" id="countryCollapse">
                                            <div class="">
                                                <div class="single-category-service">
                                                    <div class="single-select">
                                                        <select id="search_by_country" name="country">
                                                            <option value="">{{$country_text}}</option>
                                                            @foreach ($countries as $cont)
                                                              <option @if(!empty(request()->get("country")) && request()->get("country") == $cont->id ) selected @endif  value="{{$cont->id}}">{{$cont->country}}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endif
                            <!--Country filter end -->


                            <!--City filter start -->
                            @if(empty(get_static_option("google_map_settings")))
                            @if(!empty($city_on_off))
                            <div class="new_serviceDetails__side__author">
                                <div class="new_serviceDetails__side__author__contents">
                                    <h6 class="new_packageBook__addFeature__title">
                                        <a href="#cityCollapse" data-bs-toggle="collapse" aria-expanded="true">  {{ __('Search By City') }} <i class="las la-angle-down"></i> </a>
                                    </h6>
                                    <div class="collapse show" id="cityCollapse">
                                        @php  $fetch_cities = '';  @endphp
                                        @if ($country_on_off !== "on")
                                           @php
                                               $get_service_city_id = $all_services->pluck('service_city_id');
                                                $all_cities = \App\ServiceCity::whereIn("id", $get_service_city_id)->where("status", 1)->get();
                                                foreach ($all_cities as $cities) {
                                                    $fetch_cities .=  "<option selected value=" .  $cities->id .   ">" . $cities->service_city .  "</option>";
                                                }
                                           @endphp
                                        @endif

                                        <div class="single-category-service">
                                            <div class="single-select">
                                                <select id="search_by_city" name="city">
                                                    <option value=""> {{$city_text}}</option>
                                                    @foreach ($services_city as $service_city) {
                                                      <option @if(!empty(request()->get("city")) && request()->get("city") == $service_city->id) selected @endif
                                                      value="{{$service_city->id}}">{{$service_city->service_city}}</option>
                                                    @endforeach
                                                    {{ $fetch_cities }}
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            @endif
                            <!--Country filter end -->

                            <!--Area filter start -->
                            @if(empty(get_static_option("google_map_settings")))
                                @if(!empty($area_on_off))
                                    <div class="new_serviceDetails__side__author">
                                        <div class="new_serviceDetails__side__author__contents">
                                            <h6 class="new_packageBook__addFeature__title">
                                                <a href="#areaCollapse" data-bs-toggle="collapse" aria-expanded="true">  {{ __('Search By Area') }} <i class="las la-angle-down"></i> </a>
                                            </h6>
                                            <div class="collapse show" id="areaCollapse">
                                                <div class="single-category-service">
                                                    <div class="single-select">
                                                        <select id="search_by_area" name="area">
                                                            <option value=""> {{$city_text}}</option>

                                                            @foreach ($services_area as $service_area) {
                                                              <option @if(!empty(request()->get("area")) && request()->get("area") == $service_area->id) selected @endif
                                                              value="{{$service_area->id}}">{{$service_area->service_area}}</option>
                                                            @endforeach
                                                                {{ $fetch_cities ?? 0 }}
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif
                            <!--Area filter end -->


                            <!--Category filter start -->
                            @if(!empty($category_on_off))
                                <div class="new_serviceDetails__side__author">
                                    <div class="new_serviceDetails__side__author__contents">
                                        <h6 class="new_packageBook__addFeature__title">
                                            <a href="#categoryCollapse" data-bs-toggle="collapse" aria-expanded="true">  {{ __('Search By Category') }} <i class="las la-angle-down"></i> </a>
                                        </h6>
                                        <div class="collapse show" id="categoryCollapse">
                                            <div class="single-category-service">
                                                <div class="single-select">
                                                    <select id="search_by_category" name="cat">
                                                        <option value="">{{$category_text}}</option>
                                                        @foreach($categories as $cat)
                                                            <option @if(!empty(request()->get("cat")) && request()->get("cat") == $cat->id) selected @endif value="{{$cat->id}}">{{$cat->name}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <!--Category filter end -->


                            <!--Sub Category filter start -->
                            @if(!empty($subcategory_on_off))
                                <div class="new_serviceDetails__side__author">
                                    <div class="new_serviceDetails__side__author__contents">
                                        <h6 class="new_packageBook__addFeature__title">
                                            <a href="#cubCategoryCollapse" data-bs-toggle="collapse" aria-expanded="true">  {{ __('Search By Sub-Category') }} <i class="las la-angle-down"></i> </a>
                                        </h6>
                                        <div class="collapse show" id="cubCategoryCollapse">
                                            <div class="single-category-service">
                                                <div class="single-select">
                                                    <select id="search_by_subcategory" name="subcat">
                                                        <option value="">{{$subcategory_text}}</option>
                                                        @foreach($sub_categories as $sub_cat)
                                                            <option @if(!empty(request()->get("subcat")) && request()->get("subcat") == $sub_cat->id) selected @endif value="{{$sub_cat->id}}">{{$sub_cat->name}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <!--Sub Category filter end -->

                            <!--Child Category filter start -->
                            @if(!empty($child_category_on_off))
                                <div class="new_serviceDetails__side__author">
                                    <div class="new_serviceDetails__side__author__contents">
                                        <h6 class="new_packageBook__addFeature__title">
                                            <a href="#childCategoryCollapse" data-bs-toggle="collapse" aria-expanded="true">  {{ __('Search By Child Category') }} <i class="las la-angle-down"></i> </a>
                                        </h6>
                                        <div class="collapse show" id="childCategoryCollapse">
                                            <div class="single-category-service">
                                                <div class="single-select">
                                                    <select id="search_by_child_category" name="child_cat">
                                                        <option value="">{{$child_category_text}}</option>
                                                        @foreach($child_categories as $child_cat)
                                                            <option @if(!empty(request()->get("child_cat")) &&  request()->get("child_cat") == $child_cat->id) selected @endif value="{{$child_cat->id}}">{{$child_cat->name}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <!--Child Category filter end -->


                            <!--Rating star filter start -->
                            @if(!empty($subcategory_on_off))
                                <div class="new_serviceDetails__side__author">
                                    <div class="new_serviceDetails__side__author__contents">
                                        <h6 class="new_packageBook__addFeature__title">
                                            <a href="#starRatingCollapse" data-bs-toggle="collapse" aria-expanded="true">  {{ __('Search By Rating') }} <i class="las la-angle-down"></i> </a>
                                        </h6>
                                        <div class="collapse show" id="starRatingCollapse">
                                            <div class="single-category-service">
                                                <div class="single-select">
                                                    <select id="search_by_rating" name="rating">
                                                        <option value="">{{ __("Select Rating Star") }}</option>
                                                        @foreach($rating_stars as $value => $text)
                                                            <option @if(!empty(request()->get("rating")) && request()->get("rating") == $value) selected @endif value="{{$value}}">{{$text}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <!--Rating star filter end -->

                            <!-- Sort-by filter start -->
                            @if(!empty($sort_by_on_off))
                                <div class="new_serviceDetails__side__author">
                                    <div class="new_serviceDetails__side__author__contents">
                                        <h6 class="new_packageBook__addFeature__title">
                                            <a href="#sortbyCollapse" data-bs-toggle="collapse" aria-expanded="false">  {{ __('Search By Sort-by') }} <i class="las la-angle-down"></i> </a>
                                        </h6>
                                        <div class="collapse show" id="sortbyCollapse">
                                            <div class="single-category-service">
                                                <div class="single-select">
                                                    <select id="search_by_sorting" name="sortby">
                                                        <option value="">{{ __("Sort By") }}</option>
                                                        @foreach($sortby_search as $value => $text)
                                                            <option @if(!empty(request()->get("sortby")) && request()->get("sortby") == $value) selected @endif value="{{$value}}">{{$text}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <!-- Sort-by star filter end -->

                        </div>
                    </div>
                </div>
                <!--Service Filtering Section end -->


                <!--All Service List Section Start -->
                @if (!empty(get_static_option("google_map_settings")))
                    <!--google map section -->
                    <div class="col-xl-9">
                        <div class="row">
                            <div class="col-lg-12">
                                <!-- loader -->
                                <div class="loader-container">
                                    <div class="loader"></div>
                                </div>

                                <!--google map section start -->
                                <div class="service-locationMap" id="map-container">
                                    <div class="fullwidth-sidebar-container">
                                        <div class="sidebar top-sidebar">
                                            <div id="map-canvas" style="height: 400px; width: 100%; position: relative; overflow: hidden;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                     <div class="row mt-4">
                    @if ($all_services->total() > 0)
                        @foreach ($all_services as $service)
                                    <div class="{{$columns ?? 'col-lg-4'}} mt-3">
                                        <div class="new_service__single {{ $google_map_style_class }}">
                                            <div class="new_service__single__thumb">
                                                <a href="{{route("service.list.details", $service->slug)}}">
                                                    {!! render_image_markup_by_attachment_id($service->image, '','','thumb'); !!}
                                                </a>
                                                @if ($service->featured == 1)
                                                    <div class="award_icons">
                                                        <a href="javascript:void(0)" class="award_icons__item">
                                                            <i class="las la-award"></i>
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="new_service__single__contents">

                                             <span class="new_jobs__single__contents__location mb-2">
                                              <i class="fa-solid fa-location-dot"></i>
                                                 {{ sellerServiceLocation($service) }}
                                             </span>

                                                <h5 class="new_service__single__contents__title">
                                                    <a href="{{ route('service.list.details',$service->slug) }}">{{ $service->title }}</a></h5>
                                                <div class="new_service__single__price">
                                                    <span class="new_service__single__price__starting"> {{ $static_text['start_at'] ?? __('Starting at') }} </span>
                                                    <h5 class="new_service__single__price__title mt-1"> {{ amount_with_currency_symbol($service->price) }} </h5>
                                                </div>
                                                <div class="author_tag border_top">
                                                    <a href="{{ route('about.seller.profile',optional($service->seller)->username) }}" class="single_authors">
                                                        <div class="single_authors__thumb">
                                                            {!! render_image_markup_by_attachment_id(optional($service->seller)->image,'','','thumb') !!}
                                                            <span class="notification-dot"></span>
                                                        </div>
                                                        <span class="single_authors__title"> {{ optional($service->seller)->name }} </span>
                                                    </a>
                                                </div>
                                                <div class="btn-wrapper border_top">
                                                    <a href="{{ route("service.list.book", $service->slug) }}" class="cmn-btn btn-outline-border w-100 radius-5"> {{ $book_now_text }} </a>
                                                </div>
                                            </div>
                                        </div>

                                  </div>
                        @endforeach
                             <div class="col-lg-12">
                                <div class="blog-pagination margin-top-55">
                                    <div class="custom-pagination mt-4 mt-lg-5">
                                        {{$all_services->links()}}
                                    </div>
                                </div>
                             </div>

                      </div>
                     </div>
                    @else
                        <!--google map section start -->
                    <div class="row">
                        <div class="col-xl-9">
                            <div class="justify-content-end mt-5">
                              <h5 class="common-title text-danger">{{ __('no service found') }}</h5>
                            </div>
                        </div>
                    </div>
                    @endif


                @else
                    <!--not google map  -->
                    <div class="col-xl-9">
                        <div class="row g-4">
                    @if ($all_services->total() > 0)
                        @foreach ($all_services as $service)
                                    <div class="{{ $columns ?? 'col-lg-4' }}">
                                        <div class="new_service__single {{ $google_map_style_class }}">
                                            <div class="new_service__single__thumb">
                                                <a href="{{route("service.list.details", $service->slug)}}">
                                                    {!! render_image_markup_by_attachment_id($service->image, '','','thumb'); !!}
                                                </a>

                                                @if ($service->featured == 1)
                                                    <div class="award_icons">
                                                        <a href="javascript:void(0)" class="award_icons__item">
                                                            <i class="las la-award"></i>
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="new_service__single__contents">

                                             <span class="new_jobs__single__contents__location mb-2">
                                              <i class="fa-solid fa-location-dot"></i>
                                                 {{ sellerServiceLocation($service) }}
                                             </span>

                                                <h5 class="new_service__single__contents__title">
                                                    <a href="{{ route('service.list.details',$service->slug) }}">{{ $service->title }}</a></h5>
                                                <div class="new_service__single__price">
                                                    <span class="new_service__single__price__starting"> {{ $static_text['start_at'] ?? __('Starting at') }} </span>
                                                    <h5 class="new_service__single__price__title mt-1"> {{ amount_with_currency_symbol($service->price) }} </h5>
                                                </div>
                                                <div class="author_tag border_top">
                                                    <a href="{{ route('about.seller.profile',optional($service->seller)->username) }}" class="single_authors">
                                                        <div class="single_authors__thumb">
                                                            {!! render_image_markup_by_attachment_id(optional($service->seller)->image,'','','thumb') !!}
                                                            <span class="notification-dot"></span>
                                                        </div>
                                                        <span class="single_authors__title"> {{ optional($service->seller)->name }} </span>
                                                    </a>
                                                    <div class="author_tag__review radius-5">
                                                        @php
                                                            $total_review = optional($service->reviews->where('type', 1));
                                                            $total_count = $total_review ->count();
                                                            $rating = round($total_review->avg('rating'),1);
                                                       @endphp

                                                        <a href="javascript:void(0)" class="author_tag__review__para"> {!! ratting_star($rating) !!} {{ $total_count }} </a>
                                                    </div>
                                                </div>
                                                <div class="btn-wrapper border_top">
                                                    <a href="{{ route("service.list.book", $service->slug) }}" class="cmn-btn btn-outline-border w-100 radius-5"> {{ $book_now_text }} </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                        @endforeach
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="blog-pagination margin-top-55">
                                            <div class="custom-pagination mt-4 mt-lg-5">
                                                {{$all_services->links()}}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </div>
                    @else
                        <div class="row">
                            <div class="col-xl-9 mt-5">
                                <h5 class="common-title text-danger">{{ __('no service found') }}</h5>
                            </div>
                        </div>
                    @endif
                @endif
                <!--All Service List Section end -->
            </div>
        </form>
    </div>
</section>

<!-- Service area end -->
@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/14.6.3/nouislider.min.js"></script>
@if (!empty(get_static_option("google_map_settings")))
    <script src="https://maps.googleapis.com/maps/api/js?key={{$google_api_key}}&libraries=places">
    <script defer src="//cdn.jsdelivr.net/npm/markerclustererplus/dist/markerclusterer.min.js"> </script>
    <script>
        // Wait for the page to fully load
        window.addEventListener('load', function() {
            var loaderContainer = document.querySelector('.loader-container');
            var mapContainer = document.getElementById('map-container');
            loaderContainer.style.display = 'none';
            mapContainer.style.display = 'block';
        });

        // goolge map html markup show section
        let book_now_title = @json($book_now_text);
        function generateContent(place){
            var content = `<div class=\"single-service service-map-style no-margin wow\">
                      <a href=\"{{$service_details_route}}/`+place.slug+`\" class=\"service-thumb service-bg-thumb-format\" `+place.image_url+`>
                  </a>
                  <div class=\"services-contents\">
                      <h5 class=\"common-title map-view-service-title mt-2\"> <a href=\"{{$service_details_route}}/`+place.slug+`\" title=\"View: `+place.title+`\"> `+place.title+` </a> </h5>
                      <div class=\"service-price\">
                          <span class=\"starting\"> Starting at </span>
                          <span class=\"prices\">`+place.service_main_price+`</span>
                      </div>
                      <div class=\"btn-wrapper d-flex flex-wrap\">
                          <a href=\"{{$service_book_route}}/`+place.slug+`\" class=\"cmn-btn btn-small btn-bg-1\"> `+book_now_title+` </a>
                      </div>
                  </div>
              </div>`;

            return content;
        }

        var map;
        var markers = [];
        var infowindow = new google.maps.InfoWindow();
        var places = @json($all_services_list_json);

        // first check lat, long if lat long not empty map initialize play
        var latitude;
        var longitude;
        @if(!empty($latitude) && !empty($longitude))
            latitude = '{{$latitude}}'
            longitude = '{{$longitude}}'
        @else
            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    latitude = position.coords.latitude;
                    longitude = position.coords.longitude;
                    // local storage
                    localStorage.setItem('latitude', latitude);
                    localStorage.setItem('longitude', longitude);
                }, function (error) {
                    console.error('Error getting location:', error);
                    // Set default values in case of an error
                    latitude = 0;
                    longitude = 0;
                });
            }
                latitude = localStorage.getItem('latitude');
                longitude = localStorage.getItem('longitude');
        @endif



        var centerLatLng = new google.maps.LatLng(latitude, longitude);
        function initialize() {
            var mapOptions = {
                zoom: 12,
                // minZoom: 2,
                // maxZoom: 20,
                zoomControl: true,
                zoomControlOptions: {
                    style: google.maps.ZoomControlStyle.DEFAULT
                },
                center: centerLatLng,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                scrollwheel: true,
                panControl: true,
                mapTypeControl: true,
                scaleControl: true,
                overviewMapControl: true,
                rotateControl: true,
            };
            map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);
            // all markers show this map
            addMarkers();
            initializeRangeSlider();
        }



        google.maps.event.addDomListener(window, 'load', initialize);

        // empty check for online services
        @if(empty($online_check_service))
            function addMarkers() {
                var min = 0.999999;
                var max = 1.000001;

                for (var place in places) {
                    place = places[place];

                    if (place.seller !== null && place.seller.latitude && place.seller.longitude) {
                        var marker_icon_new = 'https://maps.google.com/mapfiles/ms/icons/red-dot.png';
                        var image = new google.maps.MarkerImage(marker_icon_new, null, null, null, new google.maps.Size(40, 52));

                        var marker = new google.maps.Marker({
                            position: new google.maps.LatLng(
                                place.seller.latitude * (Math.random() * (max - min) + min),
                                place.seller.longitude * (Math.random() * (max - min) + min)
                            ),
                            map: map,
                            title: place.title,
                            icon: image,
                        });

                        markers.push(marker);
                        google.maps.event.addListener(marker, 'click', (function (marker, place) {
                            return function () {
                                map.setZoom(20);
                                infowindow.setContent(generateContent(place));
                                infowindow.open(map, marker);
                            };
                        })(marker, place));
                    }
                }
            }
        @endif

        @if(!empty($location_on_off))
            function initializeRangeSlider() {
                var slider = document.getElementById('slider');
                var sliderValue = document.getElementById('slider-value');

                noUiSlider.create(slider, {
                    start: {{ !empty($radius) ? $radius : 50 }},
                    range: {
                        'min': 1,
                        'max': 150
                    }
                });

                slider.noUiSlider.on('update', function (values) {
                    var newValue = Math.round(values[0]);
                    sliderValue.innerHTML = newValue;
                });
            }
        @else
          function initializeRangeSlider(){ return '' };
        @endif
    </script>

    <script>
        (function($){
            "use strict";
            $(document).ready(function(){

                // Function to handle filter button clicks
                function handleFilterButtonClick(button) {
                    $('.job_status_wise_section_start button').removeClass('btn-primary').addClass('btn-secondary');
                    $(button).removeClass('btn-secondary').addClass('btn-primary');
                }

                var check_distance_range_slider = false;
                $(".in_person_button_filter").click(function() {
                    $('#all_button_filter_value').val('');
                    $('#remotely_button_filter_value').val('');
                    $('#in_person_filter_value').val('in_person');
                    handleFilterButtonClick(this);
                    $('.autocomplete_disable').prop('disabled', false);
                    $('.autocomplete_disable').removeClass('address-input-background-color');

                    if (check_distance_range_slider === true) {
                        if(typeof slider === 'undefined' || !$('.slider-range').hasClass('noUi-target') ) {
                            initializeRangeSlider();
                        }
                        $('#slider').show();
                        $('.slider-range-value').show();
                        $('.km_title_text').show();
                    }
                });


                // Remote tasks wise filter jobs start
                var remotely_filter_check = $('#remotely_button_filter_value').val();
                if (remotely_filter_check !== '') {
                    $('#all_button_filter_value').val('');
                    $('#in_person_filter_value').val('');
                    $('#remotely_button_filter_value').val('remotely');
                    $('.autocomplete_disable').prop('disabled', true);
                    $('.autocomplete_disable').addClass('address-input-background-color');

                    initializeRangeSlider();
                    // Check if the slider object exists before trying to destroy it
                    if (typeof slider !== 'undefined') {
                        slider.noUiSlider.destroy();
                        $('#slider').hide();
                        $('.slider-range-value').hide();
                        $('.km_title_text').hide();
                        check_distance_range_slider = true;
                    }

                }

                //  remotely  jobs filter
                $(".remotely_button_filter").click(function() {

                    // empty lat, long value
                    $('#latitude').val('');
                    $('#longitude').val('');

                    $('#all_button_filter_value').val('');
                    $('#in_person_filter_value').val('');
                    $('#remotely_button_filter_value').val('remotely');
                    handleFilterButtonClick(this);
                    $('.autocomplete_disable').prop('disabled', true);
                    $('.autocomplete_disable').addClass('address-input-background-color');
                    // Disable the distance slider
                    // initializeRangeSlider();
                    slider.noUiSlider.destroy();
                    $('.slider-range-value').hide();
                    $('.km_title_text').hide();
                    check_distance_range_slider = true;
                });
                // Remote tasks wise filter jobs end



                // google map all jobs filter
                $(".all_button_filter").click(function() {
                    handleFilterButtonClick(this);
                    $('#remotely_button_filter_value').val('');
                    $('#in_person_filter_value').val('');
                    $('#all_button_filter_value').val('all_filter_jobs');

                    $('.autocomplete_disable').prop('disabled', false);
                    $('.autocomplete_disable').removeClass('address-input-background-color');

                    if (check_distance_range_slider === true) {
                        if(typeof slider === 'undefined' || !$('.slider-range').hasClass('noUi-target') ) {
                            initializeRangeSlider();
                        }
                        $('#slider').show();
                        $('.slider-range-value').show();
                        $('.km_title_text').show();
                    }


                });


                //========google map autocomplete address start
                // Initialize Google Places autocomplete
                var input = document.getElementById('autocomplete');
                @php
                    $countryCodes = \App\Country::where('status', 1)->pluck('country_code')->toArray();
                    $countryCodesStr = implode(',', $countryCodes);
                @endphp
                var countryCodesStr = "{{ $countryCodesStr }}";
                var countryCodesArray = countryCodesStr.split(',');
                var autocompleteOptions = {
                    types: ['(regions)'],
                    componentRestrictions: { country: countryCodesArray }
                };

                // Initialize the autocomplete with the options
                var autocomplete = new google.maps.places.Autocomplete(
                    document.getElementById('autocomplete'),
                    autocompleteOptions
                );

                // Get current location name and lat/long
                navigator.geolocation.getCurrentPosition(function(position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;

                    // Reverse geocode to get location name
                    var geocoder = new google.maps.Geocoder();
                    var latlng = new google.maps.LatLng(lat, lng);

                    geocoder.geocode({ 'location': latlng }, function(results, status) {
                        if (status === google.maps.GeocoderStatus.OK) {
                            if (results[0]) {
                                // Extract city and division
                                var addressComponents = results[0].address_components;
                                var city = '';
                                var division = '';

                                for (var i = 0; i < addressComponents.length; i++) {
                                    var component = addressComponents[i];
                                    if (component.types.includes('locality')) {
                                        city = component.long_name;
                                    } else if (component.types.includes('administrative_area_level_1')) {
                                        division = component.long_name;
                                    }
                                }

                                // Format as "City, Division"
                                var formattedLocation = city + ', ' + division;

                                @if(!empty($location_city_name))
                                   var city_name_formatted_location = `{{$location_city_name}}`;
                                @else
                                   var city_name_formatted_location = city;
                                @endif


                                // set address in input box current location
                                @if(!empty($autocomplete_address))
                                    input.value = `{{$autocomplete_address}}`;
                                @else
                                    input.value = formattedLocation;
                                @endif

                                if(formattedLocation){
                                    $('#location_city_name').val(city);

                                    $('#latitude').val(lat);
                                    $('#longitude').val(lng);


                                    // Set the filter title by combining the distance and formatted location by Hasib
                                    var distance_set_default = `{{ $distance_radius_km_get ?? 50 }}`;
                                    var in_person_filter_value_get = `{{$in_person_filter_value}}`;

                                    if(in_person_filter_value_get === ''){
                                        $('.distance_wise_filter_title').text(`${distance_set_default}km ${city_name_formatted_location} & remotely`);
                                    }else {
                                        $('.distance_wise_filter_title').text(`${distance_set_default}km ${city_name_formatted_location}`);
                                    }
                                }


                            } else {
                                console.error('No results found');
                            }
                        } else {
                            console.error('Geocoder failed due to: ' + status);
                        }
                    });
                });

                // Autocomplete address get
                autocomplete.addListener('place_changed', function() {
                    var place = autocomplete.getPlace();
                    if (!place.geometry) {
                        return;
                    }
                    var suburb = place.name;
                    var lat = place.geometry.location.lat();
                    var lng = place.geometry.location.lng();


                    var city_name = '';
                    for (var i = 0; i < place.address_components.length; i++) {
                        var component = place.address_components[i];
                        if (component.types.includes('locality')) {
                            city_name = component.long_name;
                            break;
                        }
                    }

                    // set lat long value
                    if(suburb){
                        $('#location_city_name').val(city_name);
                        $('#latitude').val(lat);
                        $('#longitude').val(lng);
                    }
                });
                //========== google map autocomplete address end

                // google map distance, current location, autocomplete address wise filter jobs
                $("#distance_wise_filter_apply").click(function() {
                    let get_lan_value = $('#latitude').val();
                    let get_long_value = $('#longitude').val();
                    let distance_km_value = $('#slider-value').text();

                    $('#distance_kilometers_value').val(distance_km_value);
                    // get autocomplete address old value get
                    let get_autocomplete_value = $('#autocomplete').val();
                    $('#autocomplete_address').val(get_autocomplete_value);

                    // get price and set value
                    let left_value = $('.input-min').val();
                    let right_value = $('.input-max').val();
                    $('#price_range_value').val(left_value + ',' + right_value);

                    $('#search_service_list_form').trigger('submit');
                });

            });
        })(jQuery);
    </script>
@endif

<script>
    (function($){
        "use strict";
        $(document).ready(function(){
            $(document).on('click', '#price_wise_filter_apply', function (){
                let left_value = $('.input-min').val();
                let right_value = $('.input-max').val();
                $('#price_range_value').val(left_value + ',' + right_value);

                // google map km set
                    let distance_km_value = $('#slider-value').text();
                    $('#distance_kilometers_value').val(distance_km_value);
                    let get_autocomplete_value = $('#autocomplete').val();
                    $('#autocomplete_address').val(get_autocomplete_value);

                $('#search_service_list_form').trigger('submit');
            });
        });
    })(jQuery);
</script>

@if(!empty($price_range_on_off))
<script>
    const rangeInput = document.querySelectorAll(".range-input input"),
        priceInput = document.querySelectorAll(".price-input input"),
        range = document.querySelector(".price_range_setup .progress");
    let priceGap = 10;

    var slider_price_div = document.querySelector('.price_range_setup .progress');
    var maxPriceValue = {{ $max_price_start_value ?? 10000}};
    noUiSlider.create(slider_price_div, {
        start: [@if(!empty($min_price)) {{$min_price}} @else 1 @endif, @if(!empty($max_price)) {{$max_price}} @else 10000 @endif],
        connect: true,
        range: {
            'min': 1,
            'max': maxPriceValue
        },
        pips: {
            mode: 'steps',
            stepped: true,
            density: 4
        }
    });

    slider_price_div.noUiSlider.on('update', function (values) {
        $(".input-min").val(Math.round(values[0]));
        $(".input-max").val(Math.round(values[1]));
    });

    // INPUT
    priceInput.forEach(input => {
        input.addEventListener("input", e => {
            let minPrice = parseInt(priceInput[0].value),
                maxPrice = parseInt(priceInput[1].value);

            if ((maxPrice - minPrice) >= priceGap && maxPrice <= slider_price_div.noUiSlider.options.range.max) {
                if (e.target.className === "input-min") {
                    slider_price_div.noUiSlider.set([minPrice, null]);
                } else {
                    slider_price_div.noUiSlider.set([null, maxPrice]);
                }
            }
        });
    });
</script>
@endif
@endsection