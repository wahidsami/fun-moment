<!-- Blog area start -->
<section class="new_blog_area padding-top-100 padding-bottom-100"
         data-padding-top="{{$padding_top}}" data-padding-bottom="{{$padding_bottom}}" style="background-color:{{$section_bg}}">
    <div class="container">
        <div class="new_sectionTitle text-left title_flex">
            <h2 class="title">{{ $section_title }}</h2>
            <a href="{{ $explore_link }}" class="new_exploreBtn">{{ $explore_title }}
                <i class="fa-solid fa-angle-right"></i></a>
        </div>
        <div class="row g-4 mt-4">

            @foreach ($blogs as $blog)
             <div class="col-xxl-3 col-lg-4 col-md-6">
                <div class="new_blog__single radius-10">
                    <div class="new_blog__single__thumb">
                        <a href="{{ route('frontend.blog.single',$blog->slug) }}">
                            {!! render_image_markup_by_attachment_id($blog->image,'','','thumb') !!}
                        </a>
                    </div>
                    <div class="new_blog__single__contents">
                        <div class="new_tags">
                            <a class="new_tags__item" href="javascript:void(0)"> <i class="las la-clock"></i> {{ optional($blog->created_at)->diffForHumans() }} </a>
                            <a class="new_tags__item" href="{{ route('frontend.blog.category',optional($blog->category)->slug) }}"> <i class="las la-tag"></i> {{ optional($blog->category)->name }} </a>
                        </div>
                        <h5 class="new_blog__single__title mt-3"> <a href="{{ route('frontend.blog.single',$blog->slug) }}"> {{ $blog->title }}</a>
                        </h5>
                        <p class="new_blog__single__para mt-3"> {!! purify_html_raw(Str::words($blog->blog_content,15)) !!} </p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>