@extends('frontend.frontend-master')

@section('page-meta-data')
    <title>{{ __('User Login') }}</title>
@endsection

@if(empty(get_static_option('disable_user_otp_verify')))
    @section('style')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/css/intlTelInput.css">
        <style>
            .intl-tel-input,
            .iti{
                width: 100%;
            }
        </style>
    @endsection
@endif

@section('content')
<div class="signup-area padding-top-70 padding-bottom-100">
    <div class="container">
        <div class="signup-wrapper">
            <div class="signup-contents">
                <h3 class="signup-title"> {{ get_static_option('login_form_title') ?? __('Login to your account') }}</h3>

                @if(Session::has('msg'))
                <p class="alert alert-{{Session::get('type') ?? 'success'}}">{{ Session::get('msg') }}</p>
                @endif
                <div class="error-message"></div>

                <form class="signup-forms" action="{{ route('user.login.set.phone.number') }}" method="post">
                    @csrf

                    <div class="single-signup margin-top-30 loginWithOtpInput">
                        <label class="signup-label"> {{__('Your Phone Number*')}} </label>

                        <input type="hidden" name="country_code" id="country_code">
                        <input class="form--control" type="tel" name="phone" id="phone"  placeholder="{{__('Type Phone Number')}}">

                    </div>
                        <div class="d-none">
                            <span id="error-msg" class="hide"></span>
                            <p id="result" class="d-none"></p>
                        </div>

                    <div class="text-success mt-2">
                        <a href="{{ route('user.login') }}"> <strong id="loginWithNameEmail">{{ __('Login with Username or Email') }}</strong> </a>
                    </div>

                        <button type="submit">{{ __('Login Now') }}</button>
                    <span class="bottom-register"> {{ __('Do not have Account?')}} <a class="resgister-link" href="{{ route('user.register')}}"> {{__('Register')}} </a> </span>
                </form>
                
                @if(preg_match('/(bytesed)/',url('/')))
                <div class="adminlogin-info table-responsive margin-top-30">
                    <table class="table-border table">
                        <th>{{__('Username')}}</th>
                        <th>{{__('Password')}}</th>
                        <th>{{__('Action')}}</th>
                        <tbody>
                            <tr>
                                <td id="seller_username">test_seller</td>
                                <td id="seller_password">12345678</td>
                                <td><button type="button" class="autoLogin" id="sellerLogin">{{__('Seller Login')}}</button></td>
                            </tr>
                            <tr>
                                <td id="buyer_username">test_buyer</td>
                                <td id="buyer_password">12345678</td>
                                <td><button type="button" class="autoLogin" id="buyerLogin">{{__('Buyer Login')}}</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @endif

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
    </div>
</div>


@endsection
@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/intlTelInput.min.js"></script>
    <script>
        var input = document.querySelector("#phone");
        window.intlTelInput(input, {
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/utils.js",
        });

    </script>
    <script>
        "use strict";
        $(document).ready(function () {


            // get country code
            $(document).on('click change', '#phone', function () {
                var country_code_get_value = $('.iti__selected-dial-code').text();
                 $('#country_code').val(country_code_get_value);
            });


            // OTP JS start
            var input = document.querySelector("#phone"),
                errorMap = ["Invalid number", "Invalid country code", "Too short", "Too long", "Invalid number"],
                result = document.querySelector("#result");

            const restrictedCountries = {!! $restricted_countries !!};
            const allowedCountryCodes =  {!! $restricted_countries !!}.map(countryCode => countryCode.toLowerCase());

            window.addEventListener("load", function () {
                var errorMsg = document.querySelector("#error-msg");
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
                });


                input.addEventListener('keyup', formatIntlTelInput);
                input.addEventListener('change', formatIntlTelInput);

                //Place Autocomplete Restricted to Multiple Countries
                const listItemElements = $('.iti__country');
                listItemElements.each(function() {
                    const countryDataCode = $(this).attr('data-country-code').toLowerCase();
                    if (countryDataCode && !allowedCountryCodes.includes(countryDataCode)) {
                        $(this).hide();
                    }
                });

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

            function isPhoneNumberKey(evt)
            {
                var charCode = (evt.which) ? evt.which : evt.keyCode
                if (charCode != 43 && charCode > 31 && (charCode < 48 || charCode > 57))
                    return false;
                return true;
            }
           
        });
    </script>
@endsection
