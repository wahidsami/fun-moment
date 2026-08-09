<!-- Testimonial area start -->
<section class="new_testimonial_area padding-top-50 padding-bottom-100" data-padding-top="{{$padding_top}}" data-padding-bottom="{{$padding_bottom}}" style="background-color:{{$section_bg}}">
    <div class="container">
        <div class="new_sectionTitle">
            <h2 class="title">{{ $section_title }}</h2>
        </div>
        <div class="row mt-5">
            <div class="col-lg-12">
                <div class="global-slick-init new_testimonail_slider dot-style-one slider-inner-margin" data-appendArrows=".new_testimonial__appendNav" data-centerPadding="0px" data-centerMode="true" data-arrows="true" data-infinite="true" data-dots="false" data-slidesToShow="3" data-swipeToSlide="true" data-autoplaySpeed="2500" data-prevArrow='<div class="prev-icon"><i class="fa-solid fa-arrow-left"></i></div>'
                     data-nextArrow='<div class="next-icon"><i class="fa-solid fa-arrow-right"></i></div>' data-responsive='[{"breakpoint": 1400,"settings": {"slidesToShow": 3}},{"breakpoint": 1200,"settings": {"slidesToShow": 3}},{"breakpoint": 992,"settings": {"slidesToShow": 2}},{"breakpoint": 768,"settings": {"slidesToShow": 1}},{"breakpoint": 576, "settings": {"slidesToShow": 1} }]'>

                    @foreach($all_reviews_buyer as $buyer)
                    <div class="slick_slider_item">
                        <div class="new_testimonial text-center radius-10">
                            <div class="new_testimonial__review">
                                @if($buyer->rating == 1)
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                @elseif($buyer->rating == 2)
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                @elseif($buyer->rating == 3)
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                @elseif($buyer->rating == 4)
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                @elseif($buyer->rating == 5)
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                    <span class="new_testimonial__review__star"><i class="fa-solid fa-star"></i></span>
                                @endif
                            </div>
                            <div class="new_testimonial__contents mt-2">
                                <p class="new_testimonial__para">{{ $buyer->message }}</p>
                            </div>
                            <div class="new_testimonial__author mt-4">
                                <div class="new_testimonial__author__thumb">
                                    @if(!empty($buyer->buyer->image))
                                        {!! render_image_markup_by_attachment_id(optional($buyer)->buyer->image, '','') !!}
                                    @else
                                        <img src="{{ asset('assets/frontend/img/user-no-image.png') }}">
                                    @endif
                                </div>
                                <div class="new_testimonial__author__contents mt-3">
                                    <h4 class="new_testimonial__author__title">{{ $buyer->name }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="new_testimonial__appendNav mt-4"></div>
            </div>
        </div>
    </div>
</section>