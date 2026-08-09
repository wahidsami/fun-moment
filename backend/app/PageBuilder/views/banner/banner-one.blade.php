<!-- App area start -->
<section class="new_app_area new-section-bg">
    <div class="container">
        <div class="row gy-5 align-items-end">
            <div class="col-lg-6">
                <div class="new_app__contents">
                    <h2 class="new_app__contents__title">{{ $title }}</h2>
                    @if(!empty($content_list_show_hide))
                        <ul class="new_join__list list_none mt-4">
                            @foreach ($repeater_data['benifits_'] as $key => $benifits)
                                <li class="list">{{ $benifits }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="btn-wrapper btn_flex mt-4 mt-lg-5">
                        <a href="{{ $app_button_link_one }}" class="app_btn">
                            {!! $app_image_one !!}
                        </a>
                        <a href="{{ $app_button_link_two }}" class="app_btn">
                            {!! $app_image_two!!}
                        </a>
                    </div>

                </div>
            </div>
            <div class="col-lg-6">
                <div class="new_app_wrapper">
                    <div class="new_app__thumb">
                        <div class="new_app__thumb__item">
                            {!! $bg_image_one !!}
                        </div>
                        <div class="new_app__thumb__item">
                            {!! $bg_image_two !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>