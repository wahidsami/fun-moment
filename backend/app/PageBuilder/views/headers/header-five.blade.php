<!-- Banner area Starts -->
<div class="new_banner_area new-section-bg padding-top-100 padding-bottom-100" data-padding-top="{{$padding_top}}" data-padding-bottom="{{$padding_bottom}}"
     style="background-color: {{$header_background_color}}">
    <div class="container">
        <div class="row g-5 align-items-center justify-content-between">
            <div class="col-xl-6 col-lg-7">
                <div class="new_banner__contents">
                    <h2 class="new_banner__contents__title">{{$title_start}} <span class="color-three"> {{$title_end}} </span> </h2>
                    <p class="new_banner__contents__para mt-4">{{$subtitle}}</p>
                    <div class="new_banner__search mt-4 mt-lg-5">

                        @if(!empty(get_static_option('google_map_settings')))
                        <!--google map -->
                        <form action="{{get_static_option('select_home_page_search_service_page_url') ?? '/service-list'}}" class="new_banner__search__form banner-search-location" method="get">
                            <div class="new_banner__search__input">
                                <div class="new_banner__search__location_left" id="myLocationGetAddress">
                                    <i class="fa-solid fa-location-crosshairs"></i>
                                </div>
                                <input class="form--control" name="change_address_new" id="change_address_new" type="hidden" value="">
                                <input class="form--control" name="autocomplete" id="autocomplete" type="text" placeholder="{{ get_static_option('google_map_search_placeholder_title') ?? __('Search location here') }}">
                            </div>
                            <button type="submit" class="new_banner__search__button setLocation_btn">{{ get_static_option('google_map_search_button_title') ?? __('Set Location') }}</button>
                        </form>
                        @else
                            <form action="{{get_static_option('select_home_page_search_service_page_url') ?? '/service-list'}}" method="get" class="new_banner__search__form mt-4">
                                <div class="new_banner__search__input">
                                    <input class="form--control" type="text" name="home_search" id="home_search" placeholder="{{ __('What are you looking for?') }}">
                                </div>
                                <button type="submit" class="new_banner__search__button">{{ __('Search Service') }}</button>
                            </form>
                            <span id="all_search_result"></span>
                        @endif
                    </div>

                    @if(!empty($satisfied_customer_show_hide))
                        <div class="new_banner__reviewer mt-4">
                            <div class="new_banner__reviewer__flex d-flex">
                                @foreach ($satisfied_customer_images['satisfied_customer_image_'] ?? [] as $key => $customer_image)
                                <div class="new_banner__reviewer__thumb">
                                    <a href="javascript:void(0)">
                                        {!! render_image_markup_by_attachment_id($customer_image) !!}
                                    </a>
                                </div>
                                @endforeach
                            </div>
                            <h4 class="new_banner__reviewer__title"><a href="javascript:void(0)">{{ $satisfied_customer_title }}</a></h4>
                        </div>
                    @endif

                    <div class="btn-wrapper btn_flex mt-4">
                        @if(!empty($button_one_show_hide))
                            <a href="{{ $button_one_link }}" class="cmn-btn btn-outline-2 radius-5">{{ $button_one_title }}</a>
                        @endif
                        @if(!empty($button_two_show_hide))
                            <a href="{{ $button_two_link }}" class="cmn-btn btn-bg-2 radius-5">{{ $button_two_title }}</a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-lg-5">
                <div class="new_banner__wrapper">
                    <div class="new_banner__thumb">
                        <div class="new_banner__thumb__flex">
                            <div class="new_banner__thumb__item">
                                <div class="new_banner__thumb__main">
                                    {!! $image_two !!}
                                </div>
                            </div>
                            <div class="new_banner__thumb__item">
                                <div class="new_banner__thumb__main">  {!! $image_one !!} </div>
                                @if(!empty($review_banner_show_hide))
                                    <div class="new_banner__thumb__contents d-flex">
                                        <div class="new_banner__thumb__contents__icon">
                                            <i class="{{$review_icon ?? 'fa-solid fa-thumbs-up'}}"></i>
                                        </div>
                                        <p class="new_banner__thumb__contents__para">{{$five_star_review_clients_count}}+ {{ $review_title ?? __('5 Star Reviews') }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Banner area end -->