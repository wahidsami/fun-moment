<!-- Choose area starts -->
<section class="new_choose_area padding-top-50 padding-bottom-50" data-padding-top="{{$padding_top}}" data-padding-bottom="{{$padding_bottom}}" style="background-color:{{$section_bg}}">
    <div class="container">
        <div class="new_sectionTitle">
            <h2 class="title">{{ $section_title }}</h2>
            <p class="section-para">{{ $subtitle }}</p>
            <div class="explore-btn mt-4">
                <div class="btn-wrapper">
                    <a href="{{ $btn_link }}" class="cmn-btn btn-bg-1 radius-5">{{ $btn_text }}</a>
                </div>
            </div>
        </div>
        <div class="row g-4 mt-4">
            @foreach($repeater_data['title_'] as $key => $title)
            <div class="col-xl-4 col-md-6">
                <div class="new_choose__single radius-10">
                    <div class="new_choose__single__flex">
                        <div class="new_choose__single__icon">
                            <a href="javascript:void(0)" class="market_place_image_size">
                               {!! render_image_markup_by_attachment_id($repeater_data['image_'][$key]) !!}
                            </a>
                        </div>
                        <div class="new_choose__single__contents">
                            <h5 class="new_choose__single__title"> {{ $title }} </h5>
                            <p class="new_choose__single__para">{{ $repeater_data['description_'][$key] }}</p>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
<!-- Choose area end -->