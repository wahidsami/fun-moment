<!-- Join area starts -->
<section class="new_join_area new-section-bg padding-top-100 padding-bottom-100" data-padding-top="{{$padding_top}}" data-padding-bottom="{{$padding_bottom}}" style="background-color:{{$section_bg}}">
    <div class="container">
        <div class="row flex-column-reverse flex-lg-row align-items-center">
            <div class="col-lg-6">
                <div class="new_join__contents">
                    <h2 class="new_join__title" style="color:{{$title_text_color}}">{{ $title }}</h2>
                    @if(!empty($content_list_show_hide))
                        <ul class="new_join__list list_none mt-4">
                            @foreach ($repeater_data['benifits_'] as $key => $benifits)
                                <li class="list">{{ $benifits }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="btn-wrapper mt-4 mt-lg-5">
                        <a href="{{ $btn_link }}" class="cmn-btn btn-bg-1 radius-5" style="background:{{$btn_color}}">{{ $btn_text }}</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 margin-top-30">
                <div class="new_join__thumb wow slideInRight" data-wow-delay=".2s">
                    <div class="new_join__thumb__main">
                        {!! $seller_image !!}
                    </div>
                    <div class="new_join__shapes">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Join area end -->