@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('All Reviews')}}
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

                <div class="dashboard__headerContents">
                    <div class="dashboard__headerContents__flex">
                        <div class="dashboard__headerContents__left">
                            <h4 class="dashboard__headerContents__title">{{ __('Service Reviews') }}</h4>
                        </div>
                    </div>
                </div>

                <div class="row mb-5">
                    <div class="col-md-6">
                        <div class="btn-wrapper margin-top-50 text-right">
                            <a href="{{route('seller.service.review')}}" class="btn btn-info"> {{__('Go Back' )}}</a>
                        </div>
                    </div>
                </div>


                <div class="row">
                    @if($service_reviews->count() > 0)
                        @foreach($service_reviews as $review)
                            <div class="col-sm-12 col-lg-4 mt-3">
                                <div class="dashboard-reviews">
                                    <div class="about-review-tab">
                                        <div class="about-seller-flex-content flex-start padding-top-40">
                                            <div class="about-seller-thumb">
                                                {!! render_image_markup_by_attachment_id(optional($review->buyer)->image,'','thumb') !!}
                                            </div>
                                            <div class="about-seller-content mt-2">
                                                <h5 class="title text-dark">
                                                    <a href="javascript:void(0)"> {{ $review->name }} </a>
                                                </h5>
                                                <div class="about-seller-list text-dark">
                                                    <span class="icon">
                                                        {{ __('Rating:') }}
                                                        {{ $review->rating }}
                                                        <i class="las la-star review_color_two"></i>
                                                    </span>
                                                </div>
                                                <p class="about-review-para">{{ $review->message }}</p>
                                                <span class="review-date">{{ $review->created_at->diffForHumans() }} </span>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        @endforeach
                            <div class="blog-pagination margin-top-55">
                                <div class="custom-pagination mt-4 mt-lg-5">
                                    {!! $service_reviews->links() !!}
                                </div>
                            </div>
                    @else
                        <div class="chat_wrapper__details__inner__chat__contents">
                            <h2 class="chat_wrapper__details__inner__chat__contents__para">{{ __('No Service Reviews Found') }}</h2>
                        </div>
                    @endif
                </div>
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