@extends('frontend.frontend-page-master')

@section('site-title')
    {{ $category_name->name }}
@endsection

@section('page-title')
    {{ $category_name->name }}
@endsection

@section('inner-title')
    {{ __('Category:') }} {{ $category_name->name }}
@endsection

@section('content')
    <section class="fm-hero py-5">
        <div class="container">
            <div class="hero-shell">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="hero-badge">
                            <i class="las la-tag"></i>
                            {{ __('Blog category archive') }}
                        </span>
                        <h1 class="hero-title">{{ $category_name->name }}</h1>
                        <p class="hero-subtitle mb-0">{{ __('Browse every post published under this live category.') }}</p>
                    </div>
                    <div class="col-lg-4">
                        <div class="fm-glass-card p-4">
                            <div class="stat-card">
                                <div class="label">{{ __('Posts in category') }}</div>
                                <div class="value">{{ $all_blogs->count() }}</div>
                            </div>
                            <div class="mt-3">
                                <a href="{{ route('frontend.blog.single', $all_blogs->first()->slug ?? '#') }}" class="fm-btn w-100">{{ __('Open first post') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-fm-section pt-0">
        <div class="container">
            <div class="fm-grid-3">
                @foreach($all_blogs as $blog)
                    <article class="single-blog no-margin wow fadeInUp" data-wow-delay=".2s">
                        <a href="{{ route('frontend.blog.single', $blog->slug) }}" class="blog-thumb service-bg-thumb-format" {!! render_background_image_markup_by_attachment_id($blog->image) !!}></a>
                        <div class="blog-contents">
                            <ul class="tags">
                                <li>
                                    <a href="javascript:void(0)"> <i class="las la-clock"></i> {{ optional($blog->created_at)->toFormattedDateString() }} </a>
                                </li>
                                <li>
                                    <a href="{{ route('frontend.blog.category', optional($blog->category)->slug) }}"> <i class="las la-tag"></i>{{ optional($blog->category)->name }} </a>
                                </li>
                            </ul>
                            <h5 class="common-title"> <a href="{{ route('frontend.blog.single', $blog->slug) }}">{{ $blog->title }} </a> </h5>
                            <p class="common-para">{!! Str::words(strip_tags($blog->blog_content), 20)  !!} </p>
                        </div>
                    </article>
                @endforeach
            </div>
            @if($all_blogs->count() >= 6)
                <div class="col-lg-12">
                    <div class="blog-pagination margin-top-55">
                        <div class="custom-pagination mt-4 mt-lg-5">
                            {!! $all_blogs->links() !!}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
