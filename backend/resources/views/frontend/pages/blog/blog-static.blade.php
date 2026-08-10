@extends('frontend.frontend-page-master')

@section('page-meta-data')
    {!! render_site_title($page_post->meta_title ?? $page_post->title) !!}
    <meta name="title" content="{{ optional($page_post->meta_data)->meta_title }}">
    <meta name="description" content="{{ optional($page_post->meta_data)->meta_description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ optional($page_post->meta_data)->meta_title }}">
    <meta property="og:description" content="{{ optional($page_post->meta_data)->meta_description }}">
    {!! render_og_meta_image_by_attachment_id(optional($page_post->meta_data)->facebook_meta_image) !!}
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="{{ optional($page_post->meta_data)->meta_title }}">
    <meta property="twitter:description" content="{{ optional($page_post->meta_data)->meta_description }}">
    {!! render_twitter_meta_image_by_attachment_id(optional($page_post->meta_data)->twitter_meta_image) !!}
@endsection

@section('page-title')
    {{ optional(getPageDetailsFromSlug('blog_page'))->title }}
@endsection

@section('site-title')
    {{ optional(getPageDetailsFromSlug('blog_page'))->title }}
@endsection

@section('content')
    <section class="fm-hero py-5">
        <div class="container">
            <div class="hero-shell">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="hero-badge">
                            <i class="las la-newspaper"></i>
                            {{ __('News, updates, and booking guidance') }}
                        </span>
                        <h1 class="hero-title">{{ __('Stay close to the latest stories from FUN MOMENT') }}</h1>
                        <p class="hero-subtitle mb-0">
                            {{ __('Read live blog posts published through the same Laravel content system that powers the website.') }}
                        </p>
                    </div>
                    <div class="col-lg-4">
                        <div class="fm-glass-card p-4">
                            <div class="stat-card">
                                <div class="label">{{ __('Published posts') }}</div>
                                <div class="value">{{ $all_blogs->count() }}</div>
                            </div>
                            <div class="mt-3">
                                <a href="{{ route('homepage') }}" class="fm-btn w-100">{{ __('Back to home') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-fm-section pt-0">
        <div class="container">
            @if($all_blogs->count() > 0)
                <div class="fm-grid-3">
                    @foreach($all_blogs as $blog)
                        <article class="single-service">
                            <a href="{{ route('frontend.blog.single', $blog->slug) }}" class="service-thumb service-bg-thumb-format" {!! render_background_image_markup_by_attachment_id($blog->image) !!}></a>
                            <div class="services-contents">
                                <ul class="tags">
                                    <li>
                                        <a href="javascript:void(0)">
                                            <i class="las la-clock"></i>
                                            {{ optional($blog->created_at)->toFormattedDateString() }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('frontend.blog.category', optional($blog->category)->slug) }}">
                                            <i class="las la-tag"></i>{{ optional($blog->category)->name }}
                                        </a>
                                    </li>
                                </ul>
                                <h5 class="common-title">
                                    <a href="{{ route('frontend.blog.single', $blog->slug) }}">{{ $blog->title }}</a>
                                </h5>
                                <p class="common-para">{!! Str::words(strip_tags($blog->blog_content), 24) !!}</p>
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
            @else
                <div class="alert alert-info mb-0">{{ __('No blog posts are available yet.') }}</div>
            @endif
        </div>
    </section>
@endsection
