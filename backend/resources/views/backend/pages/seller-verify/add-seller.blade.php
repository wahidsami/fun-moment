@extends('backend.admin-master')
@section('site-title')
    {{__('Add New Seller')}}
@endsection
@section('style')
    <x-media.css/>
    @if(empty(get_static_option('disable_user_otp_verify')))
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css">
    @endif
    <style>
        /* Define CSS styles for the validation message */
        #output {
            font-weight: bold;
            padding: 5px;
        }

        .valid-number {
            color: green;
        }

        .invalid-number {
            color: red;
        }

        /* Default width for the phone number input */
        .phone_number_responsive {
            width: 490px;
        }


        @media (min-width: 320px) and (max-width: 990px) {
            .phone_number_responsive {
                width: 100%;
            }
        }


    </style>
@endsection
@section('content')
    <div class="col-lg-12 col-ml-12 padding-bottom-30">
        <div class="row">
            <div class="col-lg-12">
                <div class="margin-top-40"></div>
                <x-msg.success/>
                <x-msg.error/>
            </div>
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="header-wrap d-flex justify-content-between">
                            <div class="left-content">
                                <h4 class="header-title">{{__('Add New Seller')}}   </h4>
                            </div>
                        </div>

                        <form action="{{route('admin.frontend.seller.create')}}" method="post" enctype="multipart/form-data">
                            @csrf


                            <div class="single-dashboard-input">

                                <div class="single-info-input margin-top-30">
                                    <label for="name" class="info-title"> {{__('Full Name')}} <strong class="text-danger">*</strong> </label>
                                    <input class="form-control" name="name" id="name"  value="{{ old('name') }}" type="text" placeholder="{{__('Full Name')}}">
                                </div>

                                <div class="single-info-input margin-top-30">
                                    <label for="username" class="info-title"> {{__('User Name')}}  <strong class="text-danger">*</strong></label>
                                    <input class="form-control" name="username" id="username" value="{{ old('username') }}" type="text" placeholder="{{__('username')}}">
                                </div>

                                <div class="single-info-input margin-top-30">
                                    <label for="email" class="info-title"> {{__('Email Address')}} <strong class="text-danger">*</strong> </label>
                                    <input class="form-control" name="email" id="email" value="{{ old('email') }}" type="email" placeholder="{{__('Email Address')}}">
                                </div>

                            </div>

                            <div class="single-dashboard-input">

                                <div class="single-info-input margin-top-30">
                                    <label for="phone" class="info-title"> {{__('Phone Number')}} <strong class="text-danger">*</strong> </label>
                                    <input class="form-control phone_number_responsive" name="phone" id="phone" type="tel" placeholder="{{__('Phone Number')}}">
                                    <input  name="valid_phone_number" id="valid_phone_number" type="hidden">
                                    @if(empty(get_static_option('disable_user_otp_verify')))
                                        <br>
                                        <span id="output">{{ __('Please enter a valid number below') }}</span>
                                    @endif
                                </div>


                                <div class="single-info-input margin-top-30">
                                    <label for="password" class="info-title"> {{__('Password')}} <strong class="text-danger">*</strong> </label>
                                    <input class="form-control" name="password" id="password" value="{{ old('password') }}" type="password" placeholder="{{__('Password')}}">
                                </div>
                                <div class="single-info-input margin-top-30">
                                    <label for="confirm" class="info-title"> {{__('Password')}} <strong class="text-danger">*</strong></label>
                                    <input class="form-control" name="confirm" id="confirm" value="{{ old('confirm _password') }}" type="password" placeholder="{{__('Retype Password')}}">
                                </div>
                            </div>

                            <div class="single-dashboard-input">

                                <div class="single-info-input margin-top-30">
                                    <label for="country_id">{{__('Service Country')}}</label>
                                    <select name="country_id" id="country_id" class="form-control" >
                                        <option value="">{{ __('Select Country') }}</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}">{{ $country->country }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="single-info-input margin-top-30">
                                    <label for="city">{{__('Service City')}}</label>
                                    <select name="service_city_id" id="service_city_id" class="form-control" >
                                        <option value="">{{ __('Select City') }}</option>
                                    </select>
                                </div>

                                <div class="single-info-input margin-top-30">
                                    <label for="service_area_id">{{__('Service Area')}}</label>
                                    <select name="service_area_id" id="service_area_id" class="form-control" >
                                        <option value="">{{ __('Select Area') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="single-dashboard-input">
                                <div class="single-info-input margin-top-30">
                                    <div class="form-group ">
                                        <span>{{ __('Upload Seller Profile Image') }}</span>
                                        <div class="media-upload-btn-wrapper">
                                            <div class="img-wrap"></div>
                                            <input type="hidden" name="image">
                                            <button type="button" class="btn btn-info media_upload_form_btn"
                                                    data-btntitle="{{__('Select Seller Profile Image')}}"
                                                    data-modaltitle="{{__('Upload Image')}}" data-toggle="modal"
                                                    data-target="#media_upload_modal">
                                                {{__('Upload Profile Image')}}
                                            </button>
                                            <small>{{ __('image format: jpg,jpeg,png,webp')}}</small> <br>
                                            <small>{{ __('recommended size 150x150') }}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="btn-wrapper d-flex justify-content-end">
                                <input type="submit" class="btn btn-success btn-bg-1 submit_btn_check" value="{{__('Submit')}} ">
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-media.markup/>
@endsection
@section('script')
    <x-media.js />
    @if(empty(get_static_option('disable_user_otp_verify')))
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>
    <script>
        @php
            $country_code = \App\Country::where('status', 1)->pluck('country_code')->toArray();
        @endphp
        const restractCountrys = @json($country_code);

        const input = document.querySelector("#phone");
        const output = document.querySelector("#output");
        const iti = window.intlTelInput(input, {
            nationalMode: true,
            onlyCountries: restractCountrys,
            utilsScript: `https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.min.js`
        });

        const handleChange = () => {
            let text;
            let cssClass;
            if (input.value) {
                if (iti.isValidNumber()) {
                    $('#valid_phone_number').val(iti.getNumber());
                    text = "{{__('The number is perfect.')}}";
                    cssClass = "{{__('valid-number success')}}";
                    $('.submit_btn_check').prop('disabled', false);
                } else {
                    text = "{{__('The number is not valid.')}}";
                    cssClass = "{{__('invalid-number danger')}}";
                    $('.submit_btn_check').prop('disabled', true);
                }
            } else {
                text = "{{__('Please enter a valid number below')}}";
                cssClass = "";
            }
            output.textContent = text;
            output.className = cssClass;
        };
        // Listen to "input" to handle changes immediately
        input.addEventListener('input', handleChange);
    </script>
    @endif



    <script>
        (function ($) {
            "use strict";
            $(document).ready(function () {

                // change country and get city
                $(document).on('change','#country_id' ,function() {
                    let country_id = $("#country_id").val();
                    $.ajax({
                        method: 'post',
                        url: "{{ route('admin.user.country.city') }}",
                        data: {
                            country_id: country_id
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
                                $("#service_city_id").html(alloptions);
                                $(".service_area_wrapper .list").html("");
                            }
                        }
                    })
                });

                $(document).on('change','#service_city_id' ,function() {
                    let service_city_id = $("#service_city_id").val();
                    $.ajax({
                        method: 'post',
                        url: "{{ route('admin.user.city.area') }}",
                        data: {
                            city_id: service_city_id
                        },
                        success: function(res) {
                            if (res.status == 'success') {
                                var alloptions = "<option value=''>{{__('Select Area')}}</option>";
                                var allList = "<li class='option' data-value=''>{{__('Select Area')}}</li>";
                                var allArea = res.areas;
                                $.each(allArea, function(index, value) {
                                    alloptions += "<option value='" + value.id +
                                        "'>" + value.service_area + "</option>";
                                    allList += "<li class='option' data-value='" + value.id +
                                        "'>" + value.service_area + "</li>";
                                });
                                $("#service_area_id").html(alloptions);
                                $(".service_area_wrapper .list").html("");
                            }
                        }
                    })
                });

            });
        })(jQuery)
    </script>
@endsection

