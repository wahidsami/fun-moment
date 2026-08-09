@extends('frontend.frontend-master')
@section('page-meta-data')
    <title>{{ __('User Register') }}</title>
@endsection

@if(empty(get_static_option('disable_user_otp_verify')))
@section('style')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/css/intlTelInput.css">
    <style>
        .intl-tel-input,
        .iti{
            width: 100%;
        }


        .signup-wrapper {
            padding: 1px 100px;
            -webkit-box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            box-shadow: 0 0 0px rgba(0, 0, 0, 0.1);
            max-width: 650px;
            margin: 0 auto;
        }

    </style>
@endsection
@endif

@section('content')
@php 
$reg_type = request()->get('type') ?? 'buyer';
@endphp
    <!-- Banner Inner area Starts -->
    <div class="banner-inner-area section-bg-2 padding-top-70 padding-bottom-70">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="banner-inner-contents text-center">
                        <h2 class="banner-inner-title"> {{ get_static_option('register_page_title') ?? __('Register') }}</h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Banner Inner area end -->
    <!-- Register Step Form area starts -->
    <section class="registration-step-area padding-top-100 padding-bottom-100">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="registration-seller-btn">
                        @if(get_static_option('seller_register_on_off') === 'off' && get_static_option('buyer_register_on_off') === 'off')
                            <div class="alert alert-danger" role="alert">
                                {{ get_static_option('register_notice') ?? __('Please be patient!!. Register system is currently disabled. We will come back very soon.') }}
                            </div>
                        @else
                            <ul class="registration-tabs tabs">
                                @if(get_static_option('seller_register_on_off') === 'on')
                                    <li data-tab="tab_one" class="is_user_seller @if($reg_type === 'seller') active @endif">
                                        <div class="single-tabs-registration">
                                            <div class="icon">
                                                <i class="las la-briefcase"></i>
                                            </div>
                                            <div class="contents">
                                                <h4 class="title" id="seller"> {{ get_static_option('register_seller_title') ?? __('Seller') }}</h4>
                                            </div>
                                        </div>
                                    </li>
                                @endif
                                @if(get_static_option('buyer_register_on_off') === 'on')
                                    <li data-tab="tab_two" class="@if($reg_type === 'buyer') active @endif is_user_buyer">
                                        <div class="single-tabs-registration">
                                            <div class="icon">
                                                <i class="las la-user-alt"></i>
                                            </div>
                                            <div class="contents">
                                                <h4 class="title" id="buyer"> {{ get_static_option('register_buyer_title') ?? __('Buyer') }}</h4>
                                            </div>
                                        </div>
                                    </li>
                                @endif
                            </ul>
                        @endif
                    </div>
                    
                    <div class="tab-content active" id="tab_one">
                        @if(!empty(get_static_option('site_google_captcha_enable')))
                        <script src='https://www.google.com/recaptcha/api.js'></script>
                        @endif

                        <div class="registration-step-form margin-top-55">
                            <form id="msform-one" class="msform user-register-form" method="post"
                                action="{{ route('user.register') }}">
                                @csrf
                                <ul class="registration-list step-list-two">
                                    <li class="list active">
                                        <a class="list-click" href="javascript:void(0)"> 1 </a>
                                    </li>
                                    <li class="list">
                                        <a class="list-click" href="javascript:void(0)"> 2 </a>
                                    </li>
                                    <li class="list">
                                        <a class="list-click" href="javascript:void(0)"> 3 </a>
                                    </li>
                                </ul>
                                <div class="text-center mt-5" id="error-message"></div>
                                <!-- Information -->
                                <fieldset class="fieldset-info user-information">
                                    {{-- validation error show  --}}
                                    <div class="mt-5"> <x-msg.error/> </div>

                                    <div class="information-all margin-top-55">
                                        <div class="info-forms">
                                            <div class="single-forms">
                                                <div class="single-content margin-top-30">
                                                    <label class="forms-label"> {{ __('Full Name') }} <span class="text-danger">*</span> </label>
                                                    <input class="form--control" type="text" name="name" id="name" value="{{old('name')}}" placeholder="{{__('Full Name')}}">
                                                </div>
                                                <div class="single-content margin-top-30">
                                                    <label class="forms-label">{{ __('User Name') }} <span class="text-danger">*</span></label>
                                                    <input class="form--control" type="text" name="username" value="{{old('username')}}" id="username" placeholder="{{__('User Name')}}">
                                                </div>
                                            </div>
                                            <div class="single-forms">
                                                <div class="single-content margin-top-30">
                                                    <label class="forms-label"> {{ __('Email Address') }} <span class="text-danger">*</span> </label>
                                                    <input class="form--control" type="text" name="email" id="email" value="{{old('email')}}"
                                                        placeholder="{{__('Type Email')}}">
                                                </div>

                                                <div class="single-content margin-top-30">
                                                    <input type="hidden" id="country-code" name="country_code">
                                                    <label class="forms-label"> {{ __('Phone Number') }}  <span class="text-danger">*</span> </label>
                                                    <input class="form--control" type="tel" name="phone" id="phone" value="{{old('phone')}}"
                                                        placeholder="{{__('Type Number')}}">

                                                    @if(empty(get_static_option('disable_user_otp_verify')))
                                                        <div class="d-none">
                                                          <span id="error-msg" class="hide"></span>
                                                         <p id="result" class="d-none"></p>
                                                      </div>
                                                    @endif

                                                </div>
                                            </div>


                                            <div class="single-forms">
                                                <div class="single-content margin-top-30">
                                                    <label class="forms-label"> {{ __('Password') }} <span class="text-danger">*</span> </label>
                                                    <input class="form--control" type="password" name="password"
                                                        id="password" placeholder="{{__('Type Password')}}">
                                                </div>
                                                <div class="single-content margin-top-30">
                                                    <label class="forms-label"> {{ __('Confirm Password') }} <span class="text-danger">*</span> </label>
                                                    <input class="form--control" type="password"
                                                        name="password_confirmation" id="password_confirmation"
                                                        placeholder="{{__('Retype Password')}}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @if(get_static_option('seller_register_on_off') === 'off' && get_static_option('buyer_register_on_off') === 'off')
                                        <input type="button" name="next" class="next action-button" value="{{__('Next')}}" disabled />
                                    @else
                                        <input type="button" name="next" class="next action-button" value="{{__('Next')}}" />
                                    @endif
                                </fieldset>
                                <!-- Service -->
                                <fieldset class="fieldset-service service-area">
                                    <div class="information-all margin-top-55">
                                        <h3 class="register-title"> {{ __('Service Area') }} </h3>
                                        <div class="info-service">
                                            <div class="single-info-service margin-top-30 country-wrapper">
                                                <div class="single-content">
                                                    <label class="forms-label"> {{ __('Service Country') }} <span class="text-danger">*</span> </label>
                                                    <select name="country" id="country">
                                                        <option value="">{{ __('Select Country') }}</option>
                                                        @foreach ($countries as $country)
                                                            <option value="{{ $country->id }}" @if($countries->count() === 1) selected @endif>{{ $country->country }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="single-info-service margin-top-30 service_city_wrapper">
                                                <div class="single-content">
                                                    <label class="forms-label"> {{ __('Service City') }} <span class="text-danger">*</span> </label>
                                                    <select name="service_city" id="service_city" class="get_service_city">
                                                        <option value="">{{ __('Select City') }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="single-info-service margin-top-30 service_area_wrapper">
                                                <div class="single-content">
                                                    <label class="forms-label"> {{ __('Service Area') }} <span class="text-danger seller_area_hide_show_req">*</span> </label>
                                                    <select name="service_area" id="service_area" class="get_service_area">
                                                        <option value="">{{ __('Select Area') }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="button" name="next" class="next action-button" value="{{__('Next')}}" />
                                    <input type="button" name="previous" class="previous action-button-previous"
                                        value="{{__('Previous')}}" />
                                </fieldset>
                                <!-- Terms & Condition -->
                                <fieldset class="fieldset-condition terms-conditions">
                                    <div class="information-all margin-top-55">
                                        <h3 class="register-title"> {{ __('Terms and Conditions') }} </h3>
                                        <div class="condition-info">
                                            <div class="single-condition margin-top-30">
                                                <div class="condition-content">
                                                    <div class="checkbox-inlines">
                                                        <input class="check-input" type="checkbox"
                                                            name="terms_conditions" id="terms_conditions">
                                                        <label class="checkbox-label" for="terms_conditions">
                                                            {{ __('I agree with the') }}
                                                            <a href="{{ url('/'.get_static_option('select_terms_condition_page')) }}" target="_blank">
                                                                {{ __('terms and conditions.') }}
                                                            </a>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                               
                                  
                                @if(!empty(get_static_option('site_google_captcha_enable')))
                                   
                                    <div class="col-md-12 my-3">
                                        <div class="g-recaptcha" data-sitekey="{{ get_static_option('recaptcha_2_site_key')}}"></div>
                                        @if ($errors->has('g-recaptcha-response'))
                                            <span class="text-danger">{{ $errors->first('g-recaptcha-response') }}</span>
                                        @endif
                                    </div>

                                @endif
                                    <input type="hidden" name="get_user_type" id="get_user_type" value="{{$reg_type === 'buyer' ? 1 : 0}}">
                                    <input type="submit" name="submit" class="action-button" value="{{__('Submit')}}" />
                                    <input type="button" name="previous" class="previous action-button-previous"
                                        value="{{__('Previous')}}" />
                                </fieldset>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty(get_static_option('register_page_social_login_show_hide')))
            <div class="signup-wrapper mt-2">
                <div class="signup-contents">
                    <div class="social-login-wrapper">
                        @if(get_static_option('enable_google_login') || get_static_option('enable_facebook_login'))
                            <div class="bar-wrap">
                                <span class="bar"></span>
                                <p class="or">{{ __('or') }}</p>
                                <span class="bar"></span>
                            </div>
                        @endif
                        <div class="sin-in-with">
                            @if(get_static_option('enable_google_login'))
                                <a href="{{ route('login.google.redirect') }}" class="sign-in-btn">
                                    <img src="{{ asset('assets/frontend/img/static/google.png') }}" alt="icon">
                                    {{ __('Sign in with Google') }}
                                </a>
                            @endif
                            @if(get_static_option('enable_facebook_login'))
                                <a href="{{ route('login.facebook.redirect') }}" class="sign-in-btn">
                                    <img src="{{ asset('assets/frontend/img/static/facebook.png') }}" alt="icon">
                                    {{ __('Sign in with Facebook') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </section>
    <!-- Register Step Form area end -->
@endsection
@section('scripts')
    @if(empty(get_static_option('disable_user_otp_verify')))
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/intlTelInput.min.js"></script>
    <script>
        var input = document.querySelector("#phone");
        window.intlTelInput(input, {
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/utils.js",
        });
    </script>
    @endif

    <script type="text/javascript">
        (function() {
            "use strict";
            $(document).ready(function() {

                // if otp is active for site check
                @if(empty(get_static_option('disable_user_otp_verify')))
                var input = document.querySelector("#phone"),
                    hiddenInput = document.querySelector("#country-code"),
                    errorMap = ["Invalid number", "Invalid country code", "Too short", "Too long", "Invalid number"],
                    result = document.querySelector("#result");
                    window.addEventListener("load", function () {
                        var errorMsg = document.querySelector("#error-msg");
                        const restrictedCountries = {!! $restricted_countries !!};
                        const allowedCountryCodes =  {!! $restricted_countries !!}.map(countryCode => countryCode.toLowerCase());

                        var iti = window.intlTelInput(input, {
                            hiddenInput: "full_number",
                            nationalMode: false,
                            formatOnDisplay: true,
                            separateDialCode: true,
                            autoHideDialCode: true,
                            autoPlaceholder: "aggressive" ,
                            initialCountry: restrictedCountries[0],
                            placeholderNumberType: "MOBILE",
                            preferredCountries: restrictedCountries[0],
                            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.1.1/js/utils.js",
                            allowDropdown: true,
                            searchCountryFlag: true
                        });


                    //Place Autocomplete Restricted to Multiple Countries
                    const listItemElements = $('.iti__country');
                    listItemElements.each(function() {
                        const countryDataCode = $(this).attr('data-country-code').toLowerCase();
                        if (countryDataCode && !allowedCountryCodes.includes(countryDataCode)) {
                            $(this).hide();
                        }
                    });


                    input.addEventListener('keyup', formatIntlTelInput);
                    input.addEventListener('change', formatIntlTelInput);

                    function formatIntlTelInput() {
                        if (typeof intlTelInputUtils !== 'undefined') {
                            var currentText = iti.getNumber(intlTelInputUtils.numberFormat.E164);
                            if (typeof currentText === 'string') {
                                iti.setNumber(currentText);
                            }
                        }
                    }

                    input.addEventListener('keyup', function () {
                        reset();
                        if (input.value.trim()) {
                            if (iti.isValidNumber()) {
                                $(input).addClass('form-control is-valid');
                                hiddenInput.value = iti.getSelectedCountryData().dialCode;
                            } else {
                                $(input).addClass('form-control is-invalid');
                                var errorCode = iti.getValidationError();
                                errorMsg.innerHTML = errorMap[errorCode];
                                $(errorMsg).show();
                            }
                        }
                    });
                    input.addEventListener('change', reset);
                    input.addEventListener('keyup', reset);

                    var reset = function () {
                        $(input).removeClass('form-control is-invalid');
                        errorMsg.innerHTML = "";
                        $(errorMsg).hide();
                    };
                });
                     //-only-phone-number-input code (with +)
                    function isPhoneNumberKey(evt)
                    {
                        var charCode = (evt.which) ? evt.which : evt.keyCode
                        if (charCode != 43 && charCode > 31 && (charCode < 48 || charCode > 57))
                            return false;
                        return true;
                    }
                @endif

               var user_type = "{{$reg_type === 'buyer' ? 1 : 0}}";
                $('#get_user_type').val(user_type);
                $(document).on('click', '.is_user_buyer', function() {
                    $('#get_user_type').val(user_type);

                    // check seller service area filed
                    if(user_type == 1){
                        $('.seller_area_hide_show_req').text('*');
                    }

                })
                $(document).on('click', '.is_user_seller', function() {
                    var user_type = 0;
                    $('#get_user_type').val(user_type);

                    // check seller service area filed
                    @if(empty(get_static_option('seller_service_area_required')))
                        if(user_type == 0){
                            $('.seller_area_hide_show_req').text('');
                        }else {
                         $('.seller_area_hide_show_req').text('*');
                       }
                    @endif
                })

           $('.user-information .next').on('click', function() {
                    var name = $('#name').val();
                    var user_name = $('#user_name').val();
                    var email = $('#email').val();
                    var phone = $('#phone').val();
                    var password = $('#password').val();
                    var password_confirmation = $('#password_confirmation').val();

                    // validate user information
                    if (name == '' || user_name == '' || email == '' || phone == '' || password == '' ||
                        password_confirmation == '') {
                        //error msg
                        Command: toastr["warning"]("{{__('Please fill all fields!')}}", "{{__('Warning')}}")
                        toastr.options = {
                            "closeButton": true,
                            "debug": false,
                            "newestOnTop": false,
                            "progressBar": true,
                            "positionClass": "toast-top-right",
                            "preventDuplicates": false,
                            "onclick": null,
                            "showDuration": "300",
                            "hideDuration": "1000",
                            "timeOut": "5000",
                            "extendedTimeOut": "1000",
                            "showEasing": "swing",
                            "hideEasing": "linear",
                            "showMethod": "fadeIn",
                            "hideMethod": "fadeOut"
                        }
                        return false;
                    }
                    else if (password != password_confirmation) {
                        //error msg
                        Command: toastr["warning"]("{{__('Password and confirm password not match.!')}}","{{__('Warning')}}")
                        toastr.options = {
                            "closeButton": true,
                            "debug": false,
                            "newestOnTop": false,
                            "progressBar": true,
                            "positionClass": "toast-top-right",
                            "preventDuplicates": false,
                            "onclick": null,
                            "showDuration": "300",
                            "hideDuration": "1000",
                            "timeOut": "5000",
                            "extendedTimeOut": "1000",
                            "showEasing": "swing",
                            "hideEasing": "linear",
                            "showMethod": "fadeIn",
                            "hideMethod": "fadeOut"
                        }
                        return false;
                    }
                    else {
                        var current_fs, next_fs, previous_fs;
                        var opacity;
                        var current = 1;
                        var steps = $("fieldset").length;
                        current_fs = $(this).parent();
                        next_fs = $(this).parent().next();

                        //Add Class Active
                        $(".step-list li, .step-list-two li").eq($("fieldset").index(next_fs)).addClass(
                            "active");
                        next_fs.show();
                        current_fs.animate({
                            opacity: 0
                        }, {
                            step: function(now) {
                                opacity = 1 - now;
                                current_fs.css({
                                    'display': 'none',
                                    'position': 'relative'
                                });
                                next_fs.css({
                                    'opacity': opacity
                                });
                            },
                            duration: 500
                        });
                    }
                })
           // change country and get city
            $(document).on('change','#country' ,function() {
                let country_id = $("#country").val();
                $.ajax({
                    method: 'post',
                    url: "{{ route('user.country.city') }}",
                    data: {
                        country_id: country_id,
                        _token: "{{csrf_token()}}"
                    },
                    success: function(res) {
                        if (res.status == 'success') {
                            var alloptions = "<option value=''>{{__('Select City')}}</option>";
                            var allList = "<li class='option' data-value=''>{{__('Select City')}}</li>";
                            var allCity = res.cities;
                            $.each(allCity, function(index, value) {
                                alloptions += "<option value='" + value.id +
                                    "'>" + value.service_city + "</option>";
                                allList += "<li class='option' data-value='" + value.id +
                                    "'>" + value.service_city + "</li>";
                            });
                            $("#service_city").html(alloptions);
                            $("#service_city").parent().find(".current").html("{{__('Select City')}}");
                            $("#service_city").parent().find(".list").html(allList);
                            $(".service_area_wrapper").find(".current").html("{{__('Select Area')}}");
                            $(".service_area_wrapper .list").html("");
                        }
                    }
                });
            });

            // if default county set
            @if($countries->count() === 1)
                $(document).ready(function() {
                  $('#country').trigger("change");
                });
            @endif

           // select city and area
            $(document).on('change','#service_city', function() {
                var city_id = $("#service_city").val();
                $.ajax({
                    method: 'post',
                    url: "{{ route('user.city.area') }}",
                    data: {
                        city_id: city_id,
                        _token: "{{csrf_token()}}"
                    },
                    success: function(res) {
                        if (res.status == 'success') {
                            var alloptions = "<option value=''>{{__('Select Area')}}</option>";
                            var allList = "<li data-value='' class='option'>{{__('Select Area')}}</li>";
                            var allArea = res.areas;
                            $.each(allArea, function(index, value) {
                                alloptions += "<option value='" + value.id +
                                    "'>" + value.service_area + "</option>";
                                allList += "<li class='option' data-value='" + value.id +
                                    "'>" + value.service_area + "</li>";
                            });

                            $("#service_area").html(alloptions);
                            $(".service_area_wrapper ul.list").html(allList);
                            $(".service_area_wrapper").find(".current").html("{{__('Select Area')}}");
                        }
                    }
                })
            })
            //confirm service area
            $('.service-area .next').on('click', function() {
                    var service_city = $('#service_city').val();
                    var service_area = $('#service_area').val();
                    var country = $('#country').val();


                    $('.get-all-iformation #get_service_city').text(service_city);
                    $('.get-all-iformation #get_service_area').text(service_area);
                    $('.get-all-iformation #get_country').text(country);

                    // check seller service area filed is required or null
                     var  check_service_area_seller_type = null;
                    @if(empty(get_static_option('seller_service_area_required')))
                       var check_service_area_seller_type = $('#get_user_type').val();
                    @endif

                    if(check_service_area_seller_type == 0){
                        if (service_city == '' ||  country == '') {

                            //error msg
                            Command: toastr["warning"]("{{__('Please fill all fields!')}}", "{{__('Warning')}}")
                            toastr.options = {
                                "closeButton": true,
                                "debug": false,
                                "newestOnTop": false,
                                "progressBar": true,
                                "positionClass": "toast-top-right",
                                "preventDuplicates": false,
                                "onclick": null,
                                "showDuration": "300",
                                "hideDuration": "1000",
                                "timeOut": "5000",
                                "extendedTimeOut": "1000",
                                "showEasing": "swing",
                                "hideEasing": "linear",
                                "showMethod": "fadeIn",
                                "hideMethod": "fadeOut"
                            }
                            return false;
                        }
                        else {
                            var current_fs, next_fs, previous_fs;
                            var opacity;
                            var current = 1;
                            var steps = $("fieldset").length;
                            current_fs = $(this).parent();
                            next_fs = $(this).parent().next();

                            $(".step-list li, .step-list-two li").eq($("fieldset").index(next_fs)).addClass(
                                "active");

                            next_fs.show();
                            current_fs.animate({
                                opacity: 0
                            }, {
                                step: function(now) {
                                    opacity = 1 - now;
                                    current_fs.css({
                                        'display': 'none',
                                        'position': 'relative'
                                    });
                                    next_fs.css({
                                        'opacity': opacity
                                    });
                                },
                                duration: 500
                            });
                        }
                    }else {
                        if (service_city == '' || service_area == '' ||  country == '') {

                            //error msg
                            Command: toastr["warning"]("{{__('Please fill all fields!')}}", "{{__('Warning')}}")
                            toastr.options = {
                                "closeButton": true,
                                "debug": false,
                                "newestOnTop": false,
                                "progressBar": true,
                                "positionClass": "toast-top-right",
                                "preventDuplicates": false,
                                "onclick": null,
                                "showDuration": "300",
                                "hideDuration": "1000",
                                "timeOut": "5000",
                                "extendedTimeOut": "1000",
                                "showEasing": "swing",
                                "hideEasing": "linear",
                                "showMethod": "fadeIn",
                                "hideMethod": "fadeOut"
                            }
                            return false;
                        }
                        else {
                            var current_fs, next_fs, previous_fs;
                            var opacity;
                            var current = 1;
                            var steps = $("fieldset").length;
                            current_fs = $(this).parent();
                            next_fs = $(this).parent().next();

                            $(".step-list li, .step-list-two li").eq($("fieldset").index(next_fs)).addClass(
                                "active");

                            next_fs.show();
                            current_fs.animate({
                                opacity: 0
                            }, {
                                step: function(now) {
                                    opacity = 1 - now;
                                    current_fs.css({
                                        'display': 'none',
                                        'position': 'relative'
                                    });
                                    next_fs.css({
                                        'opacity': opacity
                                    });
                                },
                                duration: 500
                            });
                        }
                    }

                })
            $(document).on('submit', '.user-register-form', function(e) {
                    if (!$('.terms-conditions .check-input').is(":checked")) {
                        //error msg
                        Command: toastr["warning"]("{{__('Please agree with terms and conditions.!')}}","{{__('Warning')}}")
                        toastr.options = {
                            "closeButton": true,
                            "debug": false,
                            "newestOnTop": false,
                            "progressBar": true,
                            "positionClass": "toast-top-right",
                            "preventDuplicates": false,
                            "onclick": null,
                            "showDuration": "300",
                            "hideDuration": "1000",
                            "timeOut": "5000",
                            "extendedTimeOut": "1000",
                            "showEasing": "swing",
                            "hideEasing": "linear",
                            "showMethod": "fadeIn",
                            "hideMethod": "fadeOut"
                        }
                        return false;
                    }
                });

            });
        })(jQuery);
    </script>
@endsection
