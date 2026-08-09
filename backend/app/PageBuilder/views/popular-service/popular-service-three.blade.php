<!-- Service area starts -->
<section class="new_services_area padding-top-50 padding-bottom-50" data-padding-top="{{$padding_top}}" data-padding-bottom="{{$padding_bottom}}" style="background-color:{{$section_bg}}">
    <div class="container">
        <div class="new_sectionTitle text-left title_flex">
            <h2 class="title">{{ $section_title }}</h2>
            <form action="{{ get_static_option('select_home_page_search_service_page_url') ?? url('/service-list') }}" method="get">
                <button class="new_exploreBtn bg-transparent border-0">{{ $explore_text }} <i class="fa-solid fa-angle-right"></i></button>
                <input type="hidden" name="sortby" value="popular"/>
            </form>
        </div>
        <div class="row g-4 mt-4">

            @foreach($services as $service)
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="new_service__single">
                    <div class="new_service__single__thumb">
                        <a href="{{ route('service.list.details',$service->slug) }}">
                            {!! render_image_markup_by_attachment_id($service->image, '','','thumb'); !!}
                        </a>
                    </div>
                    <div class="new_service__single__contents">
                        <span class="new_jobs__single__contents__location mb-2">
                              <i class="fa-solid fa-location-dot"></i>
                                {{ sellerServiceLocation($service) }}
                            </span>

                        <h5 class="new_service__single__contents__title"><a href="{{ route('service.list.details',$service->slug) }}">{{ $service->title }}</a></h5>
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
                                @if($rating >= 1)
                                    <a href="javascript:void(0)" class="author_tag__review__para"> {!! ratting_star($rating) !!} {{ $total_count }}</a>
                                @endif
                            </div>
                        </div>

                        <div class="btn-wrapper border_top">
                            <a href="{{ route('service.list.book',$service->slug) }}" class="cmn-btn btn-outline-border w-100 radius-5"
                               style="background:{{$btn_color}}; color:{{$button_text_color}}">{{ $book_appoinment }} </a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

        </div>
    </div>
</section>
<!-- Service area end -->