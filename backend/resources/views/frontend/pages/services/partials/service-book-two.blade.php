@extends('frontend.frontend-page-master')
@section('page-meta-data')
    <title>{{ $service_details_for_book->title }}</title>
@endsection
@section('page-title')
    <?php
    $page_info = request()->url();
    $str = explode("/",request()->url());
    $page_info = $str[count($str)-2];
    ?>
    {{ __(ucwords(str_replace("-", " ", $page_info))) }}
@endsection
@section('inner-title')
    {{ $service_details_for_book->title}}
@endsection

@section('style')
    <style>
        .input-with-icon {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            color: #999;
        }

        .wallet-payment-gateway-wrapper label{
            padding: 10px;
            font-weight: bold;
        }

        .wallet-payment-gateway-wrapper input{
            transform: scale(1.3);
        }
        .show-schedule {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .paymentGateway_add__item {
            width: calc(100% / 5 - 16px);
            overflow: hidden;
        }
        @media screen and (max-width: 1199px) and (min-width: 992px) {
            .paymentGateway_add__item {
                width: calc(100% / 3 - 13.33px);
            }
        }
        @media only screen and (max-width: 991px) {
            .paymentGateway_add__item {
                width: calc(100% / 4 - 15px);
            }
        }
        @media only screen and (max-width: 767px) {
            .paymentGateway_add__item {
                width: calc(100% / 3 - 13.33px);
            }
        }
        @media only screen and (max-width: 425px) {
            .paymentGateway_add__item {
                width: calc(100% / 2 - 10px);
            }
        }
        .custom_radio__inline__two .custom_radio__single{
            width: calc(56% - 8px);
        }

        .coupon_amount_for_apply_code {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }


    </style>
@endsection

@section('content')
    @php
        $service_country =  optional(optional($service_details_for_book->serviceCity)->countryy)->id;
        $country_tax =  App\Tax::select('id','tax')->where('country_id',$service_country)->first();
    @endphp

    <!-- Service Details area start -->
    <div class="new_service_details_area padding-top-100 padding-bottom-100">
        <div class="container">

            <div class="new_stepForm">
                <form action="{{ route('service.create.order') }}" id="msform" class="msform ms-order-form" method="post" name="msOrderForm" enctype="multipart/form-data" novalidate>
                    @csrf
                    <div class="row g-4 mt-1">
                        <!-- Hidden data for request -->
                        <input type="hidden" name="service_id" value="{{ $service_details_for_book->id }}">
                        <input type="hidden" name="seller_id" value="{{ optional($service_details_for_book->seller)->id }}">

                        @if($service_details_for_book->is_service_online == 1)
                            <input type="hidden" name="is_service_online_" value="{{ $service_details_for_book->is_service_online }}">
                            <input type="hidden" name="online_service_package_fee" value="{{ $service_details_for_book->price }}">
                        @endif
                        <input type="hidden" name="date">
                        <input type="hidden" name="schedule">
                        <input type="hidden" id="payment_form_services" name="services[]">
                        <input type="hidden" id="payment_form_additionals" name="additionals[]">

                        <div class="col-xl-9 col-lg-8">

                            <!--for coupon code and other -->
                            <input type="hidden" id="service_id" value="{{ $service_details_for_book->id }}">
                            <input type="hidden" id="seller_id" value="{{ $service_details_for_book->seller_id }}">

                            <div class="new_serviceDetails radius-10">
                                <div class="new_serviceDetails__flex">
                                    <div class="new_serviceDetails__author">
                                        <div class="new_serviceDetails__author__flex">
                                            <div class="new_serviceDetails__author__thumb"
                                            @if(empty(render_image_markup_by_attachment_id($service_details_for_book->image))) style="height: 82px; width: 92px"  @endif>
                                                <a href="javascript:void(0)">
                                                    @if(empty(render_image_markup_by_attachment_id($service_details_for_book->image)))
                                                        <img src="{{ asset('assets/frontend/img/no-image-one.jpg', 'thumb') }}" alt="no-image" />
                                                    @else
                                                      {!! render_image_markup_by_attachment_id($service_details_for_book->image,'','thumb') !!}
                                                    @endif
                                                </a>
                                            </div>
                                            <div class="new_serviceDetails__author__contents">
                                                <h4 class="new_serviceDetails__author__title">
                                                    <a href="{{ route('service.list.details',$service_details_for_book->slug) }}">{{ $service_details_for_book->title }}</a>
                                                </h4>
                                                <div class="d-flex justify-content-start">
                                                    <!--service seller info -->

                                                    <p class="new_serviceDetails__author__para">
                                                        @if(!empty(optional(optional($service_details_for_book)->seller)->username))
                                                            <a href="{{ route('about.seller.profile', optional(optional($service_details_for_book)->seller)->username) }}">
                                                                {{ optional($service_details_for_book)->seller->name }}</a>
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Error message show -->
                            <div>
                                <x-msg.error_for_service_book/> <x-session-msg/>
                            </div>

                            <div class="new_stepForm_list step_list list_none mt-5">
                                @if($service_details_for_book->is_service_online != 1)
                                <!--Location -->
                                <div class="new_stepForm_list__item active full_address_get_next_page edit_location">
                                    <div class="new_stepForm_list__item__flex">
                                        <a class="new_stepForm_list__item__click" href="javascript:void(0)">
                                            <span class="new_stepForm_list__item__click__icon"><i class="fa-solid fa-location-dot"></i></span>
                                            <div class="new_stepForm_list__item__click__contents">
                                                <h6 class="new_stepForm_list__item__click__title">{{ __('Location') }}</h6>
                                                <span class="new_stepForm_list__item__click__para">
                                                    @if(empty(get_static_option('google_map_settings')))
                                                        <strong>{{ __('Your Location:') }}</strong>
                                                            @if(Auth::guard('web')->check())
                                                                {{ optional(Auth::guard('web')->user()->country)->country }},
                                                                {{ optional(Auth::guard('web')->user()->city)->service_city }}
                                                           @endif
                                                        @endif
                                                </span>
                                            </div>
                                        </a>
                                        <div class="new_stepForm_list__item__btn click_edit_address">
                                            <a href="javascript:void(0)" class="new_stepForm_list__item__btn__edit radius-5">{{ __('Edit') }}</a>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Service info-->
                                <div class="new_stepForm_list__item  edit_service_info @if($service_details_for_book->is_service_online == 1) active @endif">
                                    <div class="new_stepForm_list__item__flex">
                                        <a class="new_stepForm_list__item__click" href="javascript:void(0)">
                                            <span class="new_stepForm_list__item__click__icon"><i class="fa-regular fa-envelope"></i></span>
                                            <div class="new_stepForm_list__item__click__contents">
                                                <h6 class="new_stepForm_list__item__click__title">{{ __('Service') }}</h6>
                                            </div>
                                        </a>
                                        <div class="new_stepForm_list__item__btn click_edit_service_info">
                                            <a href="javascript:void(0)" class="new_stepForm_list__item__btn__edit radius-5">{{ __('Edit') }}</a>
                                        </div>
                                    </div>
                                </div>


                               <!--Booking Info -->
                                <div class="new_stepForm_list__item  edit_booking_info">
                                    <div class="new_stepForm_list__item__flex">
                                        <a class="new_stepForm_list__item__click" href="javascript:void(0)">
                                            <span class="new_stepForm_list__item__click__icon"><i class="fa-regular fa-envelope"></i></span>
                                            <div class="new_stepForm_list__item__click__contents">
                                                <h6 class="new_stepForm_list__item__click__title">{{ get_static_option('service_booking_information_title') ?? __('Booking Information') }}</h6>
                                            </div>
                                        </a>
                                        <div class="new_stepForm_list__item__btn click_edit_info click_edit_booking_info">
                                            <a href="javascript:void(0)" class="new_stepForm_list__item__btn__edit radius-5">{{ __('Edit') }}</a>
                                        </div>
                                    </div>
                                </div>

                               @if($service_details_for_book->is_service_online != 1)
                                <!--Date & Time-->
                                <div class="confirm-overview-left new_stepForm_list__item edit_date_time_info">
                                    <div class="new_stepForm_list__item__flex">
                                        <a class="new_stepForm_list__item__click" href="javascript:void(0)">
                                            <span class="new_stepForm_list__item__click__icon"><i class="fa-regular fa-calendar-days"></i></span>
                                            <div class="new_stepForm_list__item__click__contents">
                                                <h6 class="new_stepForm_list__item__click__title">{{ __('Date & Time') }} </h6>

                                                <span class="new_stepForm_list__item__click__para">
                                                  <span class="details available_date"> </span>
                                                  <span class="details available_schedule"> </span>
                                                </span>
                                            </div>
                                        </a>
                                        <div class="new_stepForm_list__item__btn click_edit_schedule click_edit_date_time">
                                            <a href="javascript:void(0)" class="new_stepForm_list__item__btn__edit radius-5">{{ __('Edit') }}</a>
                                        </div>
                                    </div>
                                </div>
                               @endif

                                 <!--payment Confirmation -->
                                <div class="new_stepForm_list__item all_check_for_order edit_payment_option">
                                    <div class="new_stepForm_list__item__flex">
                                        <a class="new_stepForm_list__item__click" href="javascript:void(0)">
                                            <span class="new_stepForm_list__item__click__icon"><i class="fa-solid fa-circle-check"></i></span>
                                            <div class="new_stepForm_list__item__click__contents">
                                                <h6 class="new_stepForm_list__item__click__title">{{ __('Confirmation') }} </h6>
                                            </div>
                                        </a>
                                        <div class="new_stepForm_list__item__btn click_edit_schedule">
                                            <a href="javascript:void(0)" class="new_stepForm_list__item__btn__edit radius-5">{{ __('Edit') }}</a>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            @if($service_details_for_book->is_service_online != 1)
                            <!-- Location  -->
                            <fieldset class="padding-top-50 confirm-location">
                                <div class="row">

                             @if(empty(get_static_option('google_map_settings')))
                                    <div class="col-lg-4 col-sm-6">
                                        <div class="single-input">
                                            <label class="label-title">{{ __('Service Country') }}</label>
                                            <div class="single-input-select radius-5">
                                                <select name="choose_service_country" id="choose_service_country"  class="select2_activation">
                                                    @if(!empty($country))
                                                        <option value="{{ $country->id }}">{{ $country->country }}</option>
                                                    @endif
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        <div class="single-input">
                                            <label class="label-title">{{ __('Service City') }}</label>
                                            <div class="single-input-select radius-5">
                                                <select name="choose_service_city" id="choose_service_city" class="select2_activation get_service_city">
                                                    @if($service_details_for_book->is_service_all_cities === 1)
                                                        @php $cities = App\ServiceCity::select('id','service_city')->where('country_id',$service_country)->where('status',1)->get(); @endphp
                                                        @foreach($cities as $city)
                                                            <option value="{{ $city->id }}">{{ $city->service_city }}</option>
                                                        @endforeach
                                                    @else
                                                        @if(!empty($city))
                                                            <option value="{{ $city->id }}">{{ $city->service_city }}</option>
                                                        @endif
                                                    @endif
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        <div class="single-input">
                                            <label class="label-title">{{ __('Choose Area') }}</label>
                                            <div class="single-input-select radius-5">
                                                <select name="choose_service_area" id="choose_service_area" class="select2_activation get_service_area">
                                                    <option value="">{{ __('Select Area') }}</option>
                                                    @foreach($areas as $area)
                                                        <option value="{{ $area->id }}">{{ $area->service_area }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                @endif


                                 @if(!empty(get_static_option('google_map_settings')))
                                    <div class="col-lg-12 col-sm-12">
                                        <div class="custom-form">
                                            <div class="single-input">
                                                <label class="label-title">{{ __('Service Location') }}</label>
                                                <input type="text" class="form--control radius-5
                                                @if($service_details_for_book->is_service_online == 0) service_wise_seller_country @endif"
                                                       placeholder="Enter Your Location" name="user_address" id="user_address" value="" data-user-country="">
                                            </div>
                                        </div>
                                    </div>
                                 @endif
                                </div>

                                <!--Auth User Check  -->
                                <div class="btn-wrapper btn_flex justify-content-end mt-4">
                                    @if(get_static_option('order_create_settings') == 'anyone')
                                    <input type="button" name="next" class="next stepForm_btn radius-5" value="Next"/>
                                    @else
                                        @if(Auth::guard('web')->check())
                                            <input type="button" name="next" class="next stepForm_btn radius-5" value="Next"/>
                                        @else
                                            <div class="btn-wrapper">
                                                <a class="action-button text-white" href="{{route('user.login')}}?return={{request()->path()}}">{{ __('Sign In') }}</a>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </fieldset>
                            @endif

                            <!-- Service Info -->
                            <fieldset class="padding-top-50 edit_style_service_info">
                                <div class="custom-form">
                                    <div class="row g-4">
                                        <div class="col-sm-12">

                                            <div class="new_packageBook__details">
                                                <div class="new_packageBook__details__item">

                                                    <!-- Heading start -->
                                                        <div class="new_packageBook__header">
                                                            <div class="new_packageBook__header__left">
                                                                <h4 class="new_packageBook__details__title">{{ get_static_option('service_main_attribute_title') ?? __('What\'s Included') }}</h4>
                                                            </div>
                                                        </div>
                                                    <!-- Heading send -->
                                                    @if($service_details_for_book->is_service_online == 1)
                                                        <ul class="new_packageBook__list list_none mt-4">
                                                            @foreach ($service_includes as $include)
                                                              <li class="list_show new_packageBook__addFeature__title">{{ $include->include_service_title }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <!--Service customize start -->
                                                        <div class="row g-4 mt-1 service_include_edit_show_hide">
                                                            @foreach ($service_includes as $include)
                                                                <div class="col-lg-6 single-include include_service_id_{{ $include->id }}">
                                                                    <div class="new_packageBook__addFeature radius-10">
                                                                        <div class="new_packageBook__addFeature__flex">
                                                                            <div class="new_packageBook__addFeature__contents">
                                                                                <ul class="new_packageBook__list list_none mt-4">
                                                                                    <li class="list_show new_packageBook__addFeature__title">{{ $include->include_service_title }}</li>
                                                                                </ul>

                                                                                @if($service_details_for_book->is_service_online !=1)
                                                                                    <p class="new_packageBook__addFeature__price mt-2"
                                                                                          id="include_service_unit_price_{{ $include->id }}">
                                                                                        {{ amount_with_currency_symbol($include->include_service_price) }}
                                                                                    </p>
                                                                                @endif
                                                                            </div>

                                                                            @if($service_details_for_book->is_service_online !=1)
                                                                                <div class="btn-wrapper">
                                                                                    <div class="package_quantity">
                                                                                    <span class="substract package_quantity__icon include_service_qty_decrement">
                                                                                        <i class="fa-solid fa-minus"></i></span>
                                                                                        <input type="number" min="1"
                                                                                               class="quantity-input package_quantity__input inc_dec_include_service"
                                                                                               data-id="{{ $include->id }}"
                                                                                               data-price="{{ $include->include_service_price }}"
                                                                                               value="{{ $include->include_service_quantity }}"  oninput="validateNumberInput(this)">
                                                                                        <span class="plus package_quantity__icon inc_dec_include_service"><i class="fa-solid fa-plus"></i></span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="btn-wrapper">
                                                                                    <div class="package_quantity remove-service-list"
                                                                                         data-id="{{ $include->id }}">
                                                                                        <a class="remove text-danger" href="javascript:void(0)">{{ __('Remove') }}</a>
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        <!--Service customize end -->
                                                    @endif
                                                </div>

                                                <!--Service Additional start -->
                                                <div class="new_packageBook__details__item extra-services">
                                                    <h4 class="new_packageBook__details__title">{{ get_static_option('service_additional_attribute_title') ?? __('Upgrade your order with extras') }}</h4>
                                                    <div class="new_packageBook__details__inner">
                                                        <div class="row g-4 mt-1">
                                                            @foreach ($service_additionals as $additional)
                                                            <div class="col-lg-6">
                                                                <div class="new_packageBook__addFeature radius-10">
                                                                    <div class="new_packageBook__addFeature__flex">
                                                                        <div class="new_packageBook__addFeature__contents">
                                                                            <div class="checkbox-inlines">
                                                                                <input class="check-input" type="checkbox" id="{{ $additional->id }}" value="{{ $additional->id }}">
                                                                                <label class="new_packageBook__addFeature__title" for="{{ $additional->id }}"> {{ $additional->additional_service_title }} </label>
                                                                            </div>
                                                                            <p class="new_packageBook__addFeature__price price-value mt-2">
                                                                                {{ amount_with_currency_symbol($additional->additional_service_price) }}
                                                                            </p>
                                                                        </div>
                                                                        <div class="btn-wrapper">
                                                                            <div class="package_quantity">
                                                                                <span class="values d-none" price="{{ $additional->id }}"> {{ $additional->additional_service_price }}</span>
                                                                                <span class="substract package_quantity__icon additional_service_qty_decrement"><i class="fa-solid fa-minus"></i></span>
                                                                                <input  type="number"
                                                                                        min="1"
                                                                                        class="quantity-input package_quantity__input inc_dec_additional_service"
                                                                                        id="additional_service_quantity_{{ $additional->id }}"
                                                                                        data-id="{{ $additional->id }}"
                                                                                        data-price="{{ $additional->additional_service_price }}"
                                                                                        value="{{ $additional->additional_service_quantity }}" oninput="validateNumberInput(this)">

                                                                                <span class="plus package_quantity__icon inc_dec_additional_service"><i class="fa-solid fa-plus"></i></span>
                                                                            </div>
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                            </div>
                                                            @endforeach

                                                        </div>
                                                    </div>
                                                </div>
                                                <!--Service Additional end -->

                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <div class="btn-wrapper btn_flex justify-content-end mt-4">
                                    @if($service_details_for_book->is_service_online == 1)
                                        @if(Auth::guard('web')->check())
                                            <input type="button" name="stepPrevious" class="stepPrevious stepForm_btn__previous radius-5
                                            @if($service_details_for_book->is_service_online == 1) d-none @endif" value="Previous"/>
                                            <input type="button" name="next" class="next stepForm_btn radius-5" value="Next"/>
                                        @else
                                            <div class="btn-wrapper">
                                                <a class="action-button text-white" href="{{route('user.login')}}?return={{request()->path()}}">{{ __('Sign In') }}</a>
                                            </div>
                                        @endif
                                    @else
                                        <input type="button" name="stepPrevious" class="stepPrevious stepForm_btn__previous radius-5
                                            @if($service_details_for_book->is_service_online == 1) d-none @endif" value="Previous"/>
                                        <input type="button" name="next" class="next stepForm_btn radius-5" value="Next"/>
                                    @endif
                                </div>




                                <!-- service faq and benefits  -->
                                @if($service_benifits->count() >1)
                                    <div class="overview-single padding-top-60">
                                        <h4 class="title">{{ get_static_option('service_benifits_title') ?? __('Benefits of the Package:') }}</h4>
                                        <ul class="new_packageBook__list list_none mt-4">
                                            @foreach ($service_benifits as $benifit)
                                                <li class="list_show">{{ $benifit->benifits }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if($service_details_for_book->is_service_online == 1)
                                    @if($service_faqs && count($service_faqs) > 0)
                                        <div class="faq-area" data-padding-top="70" data-padding-bottom="100">
                                            <div class="container">
                                                <div class="row">
                                                    <div class="col-lg-12 margin-top-30">
                                                        <div class="faq-contents">
                                                            @foreach ($service_faqs as $faq)
                                                                @if(empty($faq->title )) @continue  @endif
                                                                <div class="faq-item">
                                                                    <div class="faq-title">
                                                                        {{ $faq->title }}
                                                                    </div>
                                                                    <div class="faq-panel">
                                                                        <p class="faq-para">{{ $faq->description }}</p>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </fieldset>


                            <!-- Booking Info -->
                            <fieldset class="confirm-information padding-top-50 edit_style_booking_info">
                                <div class="custom-form">
                                    <div class="row g-4">
                                        <div class="col-sm-6">
                                            <div class="single-input">
                                                <label class="label-title"> {{ __('Your Name') }} <span class="text-danger">*</span> </label>
                                                <input class="form--control radius-5" type="text" name="name" id="name" placeholder="{{ __('Enter Full Name') }}"
                                                       @if(Auth::guard('web')->check()) value="{{ Auth::user()->name }}" @else value="" @endif>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="single-input">
                                                <label class="label-title">{{ __('Your Email') }} <span class="text-danger">*</span> </label>
                                                <input type="text" class="form--control radius-5" name="email" id="email" placeholder="{{ __('Type Your Email') }}"
                                                       @if(Auth::guard('web')->check()) value="{{ Auth::user()->email }}" @else value="" @endif>
                                            </div>
                                        </div>

                                        <div class="@if(empty(get_static_option('google_map_settings'))) col-sm-6 @else col-sm-12 @endif">
                                            <div class="single-input">
                                                <label class="label-title">{{ __('Phone Number') }} <span class="text-danger">*</span> </label>
                                                <input type="number" class="form--control radius-5" name="phone" id="phone" placeholder="{{ __('Type Your Number') }}"
                                                       @if(Auth::guard('web')->check()) value="{{ Auth::user()->phone }}" @else value="" @endif>
                                            </div>
                                        </div>

                                        @if(empty(get_static_option('google_map_settings')))
                                        <div class="col-sm-6">
                                            <div class="single-input">
                                                <label class="label-title">{{ __('Post Code') }} <span class="text-danger">*</span> </label>
                                                <input type="text" class="form--control radius-5" name="post_code" id="post_code" placeholder="{{ __('Type Post Code') }}"
                                                       @if(Auth::guard('web')->check()) value="{{ Auth::user()->post_code }}" @else value="" @endif>
                                            </div>
                                        </div>
                                        @endif


                                        <div class="col-sm-12">
                                            <div class="single-input">
                                                <label class="label-title">{{ __('Your Address') }}</label>
                                                <div class="input-with-icon">
                                                    <input type="text" class="form--control radius-5" name="address"
                                                           @if($service_details_for_book->is_service_online == 1) id="user_address" @else id="address"  @endif
                                                           placeholder="{{ __('Type Your Address') }}"
                                                           @if(Auth::guard('web')->check()) value="{{ Auth::user()->address }}"
                                                           @else value="" @endif>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-12">
                                            <div class="single-input">
                                                <label class="label-title">{{ __('Order Note') }}</label>
                                                <textarea cols="30" rows="3" class="form--control radius-5" name="order_note" id="order_note" placeholder="{{ __('Type Order Note') }}"></textarea>
                                                <span>{{__('Max: 190 Character')}}</span>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="btn-wrapper btn_flex justify-content-end mt-4">
                                    @if($service_details_for_book->is_service_online == 1)
                                        @if(Auth::guard('web')->check())
                                            <input type="button" name="stepPrevious" class="stepPrevious stepForm_btn__previous radius-5" value="Previous"/>
                                            <input type="button" name="next" class="next stepForm_btn radius-5" value="Next"/>
                                        @else
                                            <div class="btn-wrapper">
                                                <a class="action-button text-white" href="{{route('user.login')}}?return={{request()->path()}}">{{ __('Sign In') }}</a>
                                            </div>
                                        @endif
                                    @else
                                        <input type="button" name="stepPrevious" class="stepPrevious stepForm_btn__previous radius-5" value="Previous"/>
                                        <input type="button" name="next" class="next stepForm_btn radius-5" value="Next"/>
                                    @endif
                                </div>
                            </fieldset>

                            @if($service_details_for_book->is_service_online != 1)
                            <!-- Schedule -->
                            <fieldset class="confirm-date-time padding-top-50 edit_style_schedule">
                                <div class="row g-4 date-overview">
                                    <div class="col-xxl-4 col-xl-5 col-md-6">
                                        <h4 class="date-time-title"> {{ get_static_option('service_available_date_title') ?? __('Available Date') }} </h4>
                                        <div class="overview-location">
                                            <input type="hidden" class="flatpickr_calendar d-none" id="service_available_dates" name="service_available_dates">
                                            <ul class="date-time-list margin-top-20 show-date">
                                                <span class="seller-id-for-schedule" style="display:none">{{ $service_details_for_book->seller_id }}</span>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-xxl-8 col-xl-7 col-md-6">
                                        <div class="schedule_radioInput mt-4">
                                            <div class="custom_radio custom_radio__inline">
                                                <h4 class="date-time-title"> {{ get_static_option('service_available_schudule_title') ?? __('Available Schedule') }} </h4>
                                                <div class="show-schedule"> </div>
                                                <div class="schedule_loader"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="btn-wrapper btn_flex justify-content-end mt-4">
                                    <input type="button" name="stepPrevious" class="stepPrevious stepForm_btn__previous radius-5" value="Previous"/>
                                    <input type="button" name="next" class="next stepForm_btn radius-5" value="Next"/>
                                </div>
                            </fieldset>
                            @endif

                            <!-- payment -->
                            <fieldset class="padding-top-50 edit_style_payment_option">
                                <div class="row g-4">

                                    <!-- all payment gateway start -->
                                    <div class="col-md-12">

                                        <!-- Wallet payment start -->
                                        <div class="user-wallet-payment-info">
                                            @if(moduleExists('Wallet'))
                                                {!! \App\Helpers\PaymentGatewayRenderHelper::renderWalletForm() !!}
                                            @endif
                                        </div>

                                        <!--all payment gateway  -->
                                        <div class="schedule_radioInput mt-3">
                                            <div class="custom_radio">
                                                {!! \App\Helpers\PaymentGatewayRenderHelper::renderPaymentGatewayForForm($service_details_for_book->is_service_online != 1) !!}
                                            </div>
                                        </div>

                                        <!--agree button -->
                                        <div class="schedule_radioInput mt-3" style="float: right">
                                            <div class="checkbox-inlines bottom-checkbox terms-and-conditions">
                                                <input class="check-input" type="checkbox" id="check3">
                                                <label class="checkbox-label" for="check3">{{ __('I agree with') }}
                                                    <a href="{{ url('/'.get_static_option('select_terms_condition_page')) }}" target="_blank">{{ __('terms and conditions') }} <span class="text-danger">*</span></a>
                                                </label>
                                            </div>
                                        </div>

                                    </div>
                                    <!-- all payment gateway end -->

                                </div>
                                <div class="btn-wrapper btn_flex justify-content-end mt-4">
                                    @if($service_details_for_book->is_service_online == 1)
                                        @if(Auth::guard('web')->check())
                                            <input type="button" name="stepPrevious" class="stepPrevious stepForm_btn__previous radius-5" value="Previous"/>
                                            <input type="submit" class="stepForm_btn radius-5" value="{{ get_static_option('service_order_confirm_title') ?? __('Pay & Confirm Your Order') }}">
                                        @else
                                            <a class="cmn-btn btn-appoinment btn-bg-1" href="{{route('user.login')}}?return={{request()->path()}}">{{ __('Sign In') }}</a>
                                            <small class="text-danger">{{__('Must login to create order for online services')}}</small>
                                        @endif
                                    @else
                                        <input type="button" name="stepPrevious" class="stepPrevious stepForm_btn__previous radius-5" value="Previous"/>
                                        <input type="submit" class="stepForm_btn radius-5" value="{{ get_static_option('service_order_confirm_title') ?? __('Pay & Confirm Your Order') }}">
                                    @endif
                                </div>
                            </fieldset>
                        </div>



                        <!--Booking Summary section -->
                        <div class="col-xl-3 col-lg-4">
                            <div class="new_serviceDetails__side">
                                <div class="new_serviceDetails__side__item">
                                    <div class="new_serviceBooking__summary">
                                        <h4 class="new_serviceBooking__summary__title"> {{ get_static_option('service_booking_title') ?? __('Booking Summery') }} </h4>
                                        <div class="new_serviceBooking__summary__contents">
                                            <div class="new_serviceBooking__summary__contents__inner">

                                              <div class="mt-4">
                                                  <h4 class="new_serviceBooking__summary__sub_title border_top">
                                                        @if($service_details_for_book->is_service_online != 1)
                                                            {{ get_static_option('service_appoinment_package_title') ?? __('Appointment Package Service') }}
                                                        @else
                                                            <ul class='onlilne-special-list'>
                                                                <li><i class="las la-clock"></i> {{ __('Delivery Days').': '.$service_details_for_book->delivery_days }}</li>
                                                                <li class="margin-bottom-30"><i class="las la-redo-alt"></i> {{ __('Revisions').': '.$service_details_for_book->revision }}</li>
                                                            </ul>
                                                        @endif
                                                  </h4>
                                              </div>
                                                <!--Service additional -->
                                                <ul class="summery_list border_top list_none @if($service_details_for_book->is_service_online == 1) d-none @endif">
                                                    @foreach ($service_includes as $include)
                                                        <li class="list include_service_id_{{ $include->id }} include_service_list">
                                                            <input type="hidden" class="includeServiceID" value="{{ $include->id }}">
                                                            <span class="item__title">{{ $include->include_service_title }}</span>
                                                            @if($service_details_for_book->is_service_online !=1)
                                                            <span class="item_count include_service_quantity service_quantity_count" id="include_service_quantity_3_{{ $include->id }}">
                                                                {{ $include->include_service_quantity }}
                                                            </span>
                                                            <span class="value_count room-count">{{ amount_with_currency_symbol($include->include_service_price) }}</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>

                                                <!--Package fee -->
                                                <ul class="new_serviceBooking__summary__list list_none border_top">
                                                    <li class="new_serviceBooking__summary__list__item">
                                                        <span class="item__title">{{ get_static_option('service_package_fee_title') ?? __('Package Fee') }}</span>
                                                        <span class="value_count package-fee">{{ amount_with_currency_symbol($service_details_for_book->price) }}</span>
                                                    </li>
                                                </ul>
                                                <h4 class="new_serviceBooking__summary__sub_title border_top">{{ get_static_option('service_extra_title') ?? __('Extra Service') }}</h4>
                                                <input type="hidden" name="package_fee_input_hiddend_field_for_js_calculation" value="{{$service_details_for_book->price}}">

                                                <!--additional service for display data-->
                                                <ul class="summery_list list_none extra-service-list">

                                                </ul>

                                                <!--additional service for backend request data-->
                                                <ul class="summery_list extra-service-list-2 d-none">

                                                </ul>

                                                <!--extra service count-->
                                                <ul class="new_serviceBooking__summary__list list_none border_top">
                                                    <li class="new_serviceBooking__summary__list__item">
                                                        <span class="item__title"> {{ get_static_option('service_extra_title') ?? __('Extra Service') }}</span>
                                                        <span class="value-count extra-service-fee">{{amount_with_currency_symbol(0)}}</span>
                                                    </li>
                                                </ul>

                                                <!--sub-total count -->
                                                <ul class="new_serviceBooking__summary__list list_none border_top">
                                                    <li class="new_serviceBooking__summary__list__item">
                                                        <span class="item__title"> {{ get_static_option('service_subtotal_title') ?? __('Subtotal') }}</span>
                                                        <span class="value-count service-subtotal">{{amount_with_currency_symbol(0)}}</span>
                                                    </li>
                                                </ul>

                                                <!--Tax Count -->
                                                <ul class="new_serviceBooking__summary__list list_none border_top">
                                                    <li class="new_serviceBooking__summary__list__item">
                                                        <span class="item__title"> {{ __('Tax(+)') }}
                                                             <span class="service-tax">{{ optional($country_tax)->tax ?? 0}}</span> %
                                                        </span>
                                                        <span class="value-count tax-amount">{{amount_with_currency_symbol(0)}}</span>
                                                    </li>
                                                </ul>

                                                <!--service Sub Total value -->
                                                <input type="hidden" name="service_subtotal_input_hidden_field_for_js_calculation" value="">

                                                <!--Total count -->
                                                <ul class="new_serviceBooking__summary__list list_none border_top">
                                                    <li class="new_serviceBooking__summary__list__item">
                                                        <span class="item__title"><strong>{{ get_static_option('service_total_amount_title') ?? __('Total') }}</strong></span>
                                                        <span class="value-count total-amount total_amount_for_coupon" id="total_amount_for_coupon">{{amount_with_currency_symbol(0)}}</span>
                                                    </li>
                                                </ul>


                                                <ul class="new_serviceBooking__summary__list list_none mt-3">
                                                    <li class="new_serviceBooking__summary__list__item">
                                                        <span class="coupon_amount_for_apply_code"> </span>
                                                    </li>
                                                </ul>


                                                <ul class="new_serviceBooking__summary__list list_none border_top coupon_input_field">
                                                    <li class="result-list">
                                                        <input type="text" name="coupon_code" class="form-control coupon_code" placeholder="{{__('Enter Coupon Code')}}">
                                                        <button class="apply-coupon">{{ __('Apply') }}</button>
                                                    </li>
                                                </ul>

                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Service Details area end -->
@endsection
@include('frontend.pages.services.service-book-js')
