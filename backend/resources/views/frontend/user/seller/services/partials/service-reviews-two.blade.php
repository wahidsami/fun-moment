@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{ __('Review') }}
@endsection
@section('content')
    <x-frontend.seller-buyer-preloader/>
    @include('frontend.user.seller.partials.sidebar-two')
    <div class="dashboard__right">
        @include('frontend.user.buyer.header.buyer-header')
        <div class="dashboard__body">
            <div class="dashboard__inner">
                <x-msg.error/>
                <x-msg.success/>
                <!-- search section start-->
                <div class="dashboard__inner__item dashboard_border padding-20 radius-10 bg-white">
                    <div class="dashboard__wallet">
                        <form action="{{ route('seller.service.review') }}" method="GET">
                            <div class="dashboard__headerGlobal__flex">
                                <div class="dashboard__headerGlobal__content">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                        <h4 class="dashboard_table__title">{{ __('Search Review Module') }}</h4> <i class="las la-angle-down search_by_all"></i>
                                    </button>
                                </div>
                                <div class="dashboard__headerGlobal__btn">
                                    <div class="btn-wrapper">
                                        <button href="#" class="dashboard_table__title__btn btn-bg-1 radius-5" type="submit">
                                            <i class="fa-solid fa-magnifying-glass"></i> {{ __('Search') }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <div id="collapseOne" class="accordion-collapse collapse
                                @if(request()->get('title'))  show
                                @elseif(request()->get('service_date')) show
                                @endif
                             " aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="single-settings">
                                                    <div class="single-dashboard-input">
                                                        <div class="row g-4 mt-3">

                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="title" class="info-title"> {{__('Service Title')}} </label>
                                                                    <input class="form--control" name="title" value="{{ request()->get('title') }}" type="text" placeholder="{{ __('Service Title') }}">
                                                                </div>
                                                            </div>

                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="service_date" class="info-title"> {{__('Created Date Range')}} </label>
                                                                    <input class="form--control flatpickr_input" value="{{ request()->get('service_date') }}" name="service_date" type="text" value="{{ request()->get('service_date') }}" placeholder="{{ __('Created Date Range') }}">
                                                                </div>
                                                            </div>

                                                        </div>
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
                <!--search section end-->

                <div class="dashboard__headerContents">
                    <div class="dashboard__headerContents__flex">
                        <div class="dashboard__headerContents__left">
                            <h4 class="dashboard__headerContents__title">{{ __('Service Reviews') }}</h4>
                        </div>
                    </div>
                </div>

                @if($services->count() > 0)
                    <div class="row g-4">
                        @foreach($services as $data)
                            <div class="col-xxl-6 col-lg-6">
                                <div class="dashboard__inner__item dashboard_border padding-20 radius-10 bg-white">
                                    <div class="dashboard_jobPost">
                                        <div class="dashboard_jobPost__flex">
                                            <div class="dashboard_jobPost__author">
                                                <div class="dashboard_jobPost__author__thumb">
                                                    @if(!empty(render_image_markup_by_attachment_id($data->image,'','thumb')))
                                                        {!! render_image_markup_by_attachment_id($data->image,'','thumb') !!}
                                                    @else
                                                       <img src="{{ asset('assets/frontend/img/no-image-one.jpg') }}" class="no_image_style_two" alt="No Image">
                                                    @endif
                                                </div>
                                                <div class="dashboard_jobPost__author__contents">
                                                    <h4 class="dashboard_jobPost__author__title"><a target="_blank" href="{{route('service.review.all',$data->id)}}">{{ $data->title }}</a></h4>
                                                    <div class="dashboard_jobPost__views mt-3">
                                                        <div class="dashboard_jobPost__views__item">
                                                            <span class="dashboard_jobPost__views__para"><i class="las la-star review_color_two"></i> {{ __('Rating') }}</span>
                                                            <span class="dashboard_jobPost__views__count">
                                                                {{ round(optional($data->reviews->where('type', 1))->avg('rating'),1) }}
                                                                 <b>({{ optional($data->reviews->where('type', 1))->count() }})</b>
                                                            </span>
                                                        </div>

                                                        <div class="dashboard_jobPost__views__item">
                                                            <span class="dashboard_jobPost__views__para"><i class="fa-regular fa-eye"></i> {{ __('Views') }}</span>
                                                                <strong class="replaceText text-success"> {{ $data->view }}</strong>
                                                        </div>

                                                        <div class="dashboard_jobPost__views__item">
                                                            <span class="dashboard_jobPost__views__para">{{ __('Reviews') }}</span>
                                                             <strong class="replaceText text-success"> {{ optional($data->reviews->where('type', 1))->count() }} </strong>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>

                                            <div class="dashboard_table__main__actions">
                                                <a href="{{route('service.review.all',$data->id)}}" title="{{ __('View') }}" class="icon"><i class="fa-regular fa-eye"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div class="blog-pagination margin-top-55">
                            <div class="custom-pagination mt-4 mt-lg-5">
                                {!! $services->links() !!}
                            </div>
                        </div>
                    </div>

                @else
                    <div class="chat_wrapper__details__inner__chat__contents">
                        <h2 class="chat_wrapper__details__inner__chat__contents__para">{{ __('No Review Found') }}</h2>
                    </div>
                @endif
            </div>
        </div>
@endsection
@section('scripts')
    <script src="{{asset('assets/backend/js/sweetalert2.js')}}"></script>
    <script>
        (function($){
            "use strict";
            $(document).ready(function(){
                // for date range
                $('.flatpickr_input').flatpickr({
                    altFormat: "invisible",
                    altInput: false,
                    mode: "range",
                });

            });
        })(jQuery);
    </script>
@endsection