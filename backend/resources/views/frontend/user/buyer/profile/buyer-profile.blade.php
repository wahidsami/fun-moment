@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('Edit Buyer Profile')}}
@endsection
@section('style')
    <x-media.css/>
@endsection
@section('content')
    <x-frontend.seller-buyer-preloader/>
    @php $default_lang = get_default_language(); @endphp
            <!-- Dashboard area Starts -->
    @include('frontend.user.buyer.partials.sidebar-two')
    <div class="dashboard__right">
        <!-- buyer header -->
        @include('frontend.user.buyer.header.buyer-header')
        <div class="dashboard__body">
            <div class="dashboard__inner">
                <!-- buyer profile section start-->
                <div class="dashboard_accountProfile">
                    <x-error-message/>
                    <div class="dashboard__inner__item dashboard_border padding-20 radius-10 bg-white">
                        <div class="dashboard_accountProfile__item">
                            <div class="dashboard_accountProfile__flex">
                                <div class="dashboard_accountProfile__author">
                                    <div class="dashboard_accountProfile__author__flex">
                                        <div class="dashboard_accountProfile__author__thumb">
                                            <a href="javascript:void(0)">
                                                @if(!is_null(Auth::guard('web')->user()->image))
                                                    {!! render_image_markup_by_attachment_id(Auth::guard('web')->user()->image) !!}
                                                @else
                                                    <img src="{{ asset('assets/frontend/img/no-image.jpg') }}" alt="No Image">
                                                @endif
                                            </a>
                                        </div>
                                        <div class="dashboard_accountProfile__author__contents">
                                            <h4 class="dashboard_accountProfile__author__title"><a href="javascript:void(0)">{{ Auth::guard('web')->user()->name }}</a></h4>
                                            <p class="dashboard_accountProfile__author__para mt-1">{{ Auth::guard('web')->user()->email }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="dashboard_accountProfile__btn">
                                    <a href="#0" class="dashboard_table__title__btn btn-bg-1 radius-5 edit_buyer_profile"
                                       data-bs-toggle="modal"
                                       data-bs-target="#editProfile"
                                    ><i class="fa-regular fa-pen-to-square"></i> {{ __('Edit Profile') }}</a>
                                </div>
                            </div>
                            <div class="dashboard_accountProfile__inner profile_border_top">
                                <div class="dashboard_accountProfile__details">
                                    <div class="dashboard_accountProfile__details__item">
                                        <span class="dashboard_accountProfile__details__name">{{ __('Phone Number:') }}</span>
                                        <span class="dashboard_accountProfile__details__para">{{ Auth::guard('web')->user()->phone }}</span>
                                    </div>
                                    <div class="dashboard_accountProfile__details__item">
                                        <span class="dashboard_accountProfile__details__name">{{__('Country:')}} </span>
                                        <span class="dashboard_accountProfile__details__para">{{ optional(optional(Auth::guard('web')->user())->country)->country }}</span>
                                    </div>
                                    <div class="dashboard_accountProfile__details__item">
                                        <span class="dashboard_accountProfile__details__name">{{ __('City:') }}</span>
                                        <span class="dashboard_accountProfile__details__para">{{ optional(optional(Auth::guard('web')->user())->city)->service_city }}</span>
                                    </div>
                                    <div class="dashboard_accountProfile__details__item">
                                        <span class="dashboard_accountProfile__details__name">{{ __('Area:') }}</span>
                                        <span class="dashboard_accountProfile__details__para">{{ optional(optional(Auth::guard('web')->user())->area)->service_area }}</span>
                                    </div>
                                    <div class="dashboard_accountProfile__details__item">
                                        <span class="dashboard_accountProfile__details__name">{{__('Post Code:')}}</span>
                                        <span class="dashboard_accountProfile__details__para">{{ Auth::guard('web')->user()->post_code }}</span>
                                    </div>
                                    <div class="dashboard_accountProfile__details__item">
                                        <span class="dashboard_accountProfile__details__name">{{__('Address:')}}</span>
                                        <span class="dashboard_accountProfile__details__para">{{ Auth::guard('web')->user()->address }}</span>
                                    </div>
                                    @if(Auth::guard('web')->user()->about != NULL)
                                        <div class="dashboard_accountProfile__details__item">
                                            <span class="dashboard_accountProfile__details__name">{{__('About:')}}</span>
                                            <span class="dashboard_accountProfile__details__para">{{ Auth::guard('web')->user()->about }} </span>
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
    <!-- Dashboard area end -->

    <!-- Buyer Profile Edit Modal Start-->
    <div class="modal fade modal-lg" id="editProfile" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('Edit Profile') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="custom-form">
                        <form action="{{route('seller.profile.edit')}}" method="POST">
                            <input type="hidden" name="id" value="{{ Auth::guard('web')->user()->id }}">
                            @csrf
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="name" class="label_title__postition">{{ __('Your Name') }} <span class="text-danger">*</span> </label>
                                        <input type="text" class="form--control radius-10" name="name" value="{{ Auth::guard('web')->user()->name }}" placeholder="{{ __('Your Name') }}">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="email" class="label_title__postition">{{ __('Email') }} <span class="text-danger">*</span> </label>
                                        <input type="email" class="form--control radius-10" name="email" value="{{ Auth::guard('web')->user()->email }}" placeholder="{{ __('Your email') }}">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="phone" class="label_title__postition">{{ __('Phone') }} <span class="text-danger">*</span></label>
                                        <input type="tel" class="form--control radius-10" name="phone" value="{{ Auth::guard('web')->user()->phone }}" placeholder="{{ __('Your phone') }}">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="CurrentPassword" class="label_title__postition">{{ __('Select Country') }} <span class="text-danger">*</span> </label>
                                        <div class="single-input-select radius-10">
                                            <select name="country_id" id="country_id">
                                                @if(!empty($countries))
                                                    @foreach($countries as $country)
                                                        <option value="{{ $country->id }}" @if($country->id==Auth::guard('web')->user()->country_id) selected @endif>{{ $country->country }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="CurrentPassword" class="label_title__postition">{{ __('Select City') }} <span class="text-danger">*</span> </label>
                                        <div class="single-input-select radius-10">
                                            <select name="service_city" id="service_city">
                                                @if(!empty($cities))
                                                    @foreach($cities as $city)
                                                        <option value="{{ $city->id }}" @if($city->id==Auth::guard('web')->user()->service_city) selected @endif>{{ $city->service_city }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="CurrentPassword" class="label_title__postition">{{ __('Select Area') }} <span class="text-danger">*</span> </label>
                                        <div class="single-input-select radius-10">
                                            <select name="service_area" id="service_area">
                                                @if(!empty($areas))
                                                    @foreach($areas as $area)
                                                        <option value="{{ $area->id }}" @if($area->id==Auth::guard('web')->user()->service_area) selected @endif>{{ $area->service_area }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="newPassword" class="label_title__postition">{{ __('Post Code') }} <span class="text-danger">*</span> </label>
                                        <input type="text" class="form--control radius-10" name="post_code" value="{{ Auth::guard('web')->user()->post_code }}" placeholder="{{ __('Post Code') }}">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="newPassword" class="label_title__postition">{{ __('Your Address') }}</label>
                                        <input type="text" class="form--control radius-10" name="address" value="{{ Auth::guard('web')->user()->address }}" placeholder="{{ __('Your Address') }}">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="about" class="label_title__postition">{{ __('About') }}</label>
                                        <textarea class="form--control textarea--form" name="about" placeholder="{{__('Type Your About')}}"> {{ Auth::guard('web')->user()->about }} </textarea>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="media-upload-btn-wrapper">
                                        <div class="img-wrap">
                                            {!! render_image_markup_by_attachment_id(Auth::guard('web')->user()->image,'','thumb') !!}
                                        </div>
                                        <input type="hidden" id="image" name="image"
                                               value="{{Auth::guard('web')->user()->image}}">
                                        <button type="button" class="btn btn-info media_upload_form_btn"
                                                data-btntitle="{{__('Select Image')}}"
                                                data-modaltitle="{{__('Upload Image')}}" data-bs-toggle="modal"
                                                data-bs-target="#media_upload_modal">
                                            {{__('Upload Profile Image')}}
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">{{__('allowed image format: jpg,jpeg,png')}}</small>
                                </div>

                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">{{ __('Update Profile') }}</button>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <!-- Buyer Profile Edit Modal End-->
    <x-media.markup :type="'web'"/>
@endsection
@section('scripts')
    <x-media.js :type="'web'"/>
    <script src="{{asset('assets/backend/js/sweetalert2.js')}}"></script>
    <script>
        (function($){
            "use strict";
            $(document).ready(function(){

                $('#country_id').select2({
                    dropdownParent: $('#editProfile')
                });
                $('#service_city').select2({
                    dropdownParent: $('#editProfile')
                });
                $('#service_area').select2({
                    dropdownParent: $('#editProfile')
                });

                $(document).on('click', '.edit_buyer_profile', function(e) {
                    e.preventDefault();
                    $('#editProfile').modal('show');
                    // $('.nice-select').niceSelect('update');
                });


                // media upload modal open submit img after show old modal
                $(document).on('click', '.media_upload_modal_submit_btn', function(e) {
                    e.preventDefault();
                    $('#editProfile').modal('show');
                });

                // change country and get city
                $(document).on('change','#country_id' ,function() {
                    let country_id = $("#country_id").val();
                    $.ajax({
                        method: 'post',
                        url: "{{ route('user.country.city') }}",
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
                                $("#service_city").html(alloptions);
                                $("#service_city").parent().find(".current").html("__('Select City')");
                                $("#service_city").parent().find(".list").html(allList);
                                $(".service_area_wrapper").find(".current").html("{{__('Select Area')}}");
                                $(".service_area_wrapper .list").html("");

                                $('#service_city').select2({
                                    dropdownParent: $('#editProfile')
                                });
                            }
                        }
                    })
                })

                $('#service_city').select2({
                    placeholder: `{{__('search city')}}`,
                    ajax: {
                        type: 'get',
                        url: "{{route('user.country.city.ajax.search')}}",
                        dataType: 'json',
                        data: function (params) {
                            let country_id = $("#country_id").val();
                            return {
                                q: params.term, // search term
                                country_id: country_id,
                            };
                        },
                        delay: 250,
                        processResults: function (response) {
                            console.log(response.data);
                            return {
                                results:  $.map(response, function (item) {
                                    return {
                                        text: item.service_city,
                                        id: item.id
                                    }
                                })
                            };
                        },
                        cache: true
                    }
                });


                // select city and area
                $(document).on('change','#service_city', function() {
                    var city_id = $("#service_city").val();
                    $.ajax({
                        method: 'post',
                        url: "{{ route('user.city.area') }}",
                        data: {
                            city_id: city_id
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

                                $('#service_area').select2({
                                    dropdownParent: $('#editProfile')
                                });
                            }
                        }
                    })
                })

            });
        })(jQuery);
    </script>
@endsection
