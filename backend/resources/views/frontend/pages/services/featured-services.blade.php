@extends('frontend.frontend-page-master')
@section('page-meta-data')
    <title>{{ __('Featured Services') }}</title>
@endsection
@section('page-title')
{{ __('Featured Service') }}
@endsection
@section('inner-title')
{{ __('All Featured Service') }}
@endsection
@section('content')
    <!-- Category Service area starts -->
    <section class="category-services-area padding-top-70 padding-bottom-100">
        <div class="container">
            <div class="row">
                @if(!empty($all_featurd_service))
                    @foreach($all_featurd_service as $service)
                        <div class="col-lg-3 col-md-6 margin-top-30 all-services">
                            <div class="new_service__single">
                                <div class="new_service__single__thumb">
                                    <a href="{{ route('service.list.details',$service->slug) }}">
                                        {!! render_image_markup_by_attachment_id($service->image, '','','thumb'); !!}
                                    </a>
                                    <div class="award_icons">
                                        <a href="javascript:void(0)" class="award_icons__item">
                                            <i class="las la-award"></i>
                                        </a>
                                    </div>
                                </div>

                                <div class="new_service__single__contents">
                                     <span class="new_jobs__single__contents__location mb-2">
                                      <i class="fa-solid fa-location-dot"></i>
                                        {{ sellerServiceLocation($service) }}
                                    </span>

                                    <h5 class="new_service__single__contents__title"><a href="{{ route('service.list.details',$service->slug) }}">{{ $service->title }}</a></h5>
                                    <div class="new_service__single__price">
                                        <span class="new_service__single__price__starting"> {{ __('Starting at') }} </span>
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

                                        @if($service->reviews->where('type', 1)->count() >= 1)
                                            <div class="author_tag__review radius-5">
                                                <a href="javascript:void(0)" class="author_tag__review__para">
                                                <span class="reviews">
                                                    {!! ratting_star(round(optional($service->reviews->where('type', 1))->avg('rating'),1)) !!}
                                                    ({{ optional($service->reviews->where('type', 1))->count() }})
                                                </span>
                                                </a>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="btn-wrapper border_top">
                                        <a href="{{ route('service.list.book',$service->slug) }}" class="cmn-btn btn-outline-border w-100 radius-5"> {{ __('Book Now') }} </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @if($all_featurd_service->count() >= 9)
                        <div class="col-lg-12">
                            <div class="blog-pagination margin-top-55">
                                <div class="custom-pagination mt-4 mt-lg-5">
                                    {!! $all_featurd_service->links() !!}
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </section>
    <!-- Category Service area end -->

@endsection
