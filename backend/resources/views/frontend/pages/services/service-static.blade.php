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
    {{ optional(getPageDetailsFromSlug('service_list_page'))->title }}
@endsection

@section('site-title')
    {{ optional(getPageDetailsFromSlug('service_list_page'))->title }}
@endsection

@section('content')
    <section class="fm-hero py-5">
        <div class="container">
            <div class="hero-shell">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="hero-badge">
                            <i class="las la-layer-group"></i>
                            {{ __('Live service catalog') }}
                        </span>
                        <h1 class="hero-title">{{ __('Browse real FUN MOMENT services and book the one that fits your occasion.') }}</h1>
                        <p class="hero-subtitle mb-0">
                            {{ __('This catalog is powered by the same live service records used across the website, mobile app, and admin panel.') }}
                        </p>
                    </div>
                    <div class="col-lg-5">
                        <div class="fm-glass-card p-4">
                            <div class="fm-grid-2">
                                <div class="stat-card">
                                    <div class="label">{{ __('Available now') }}</div>
                                    <div class="value">{{ $all_services->count() }}</div>
                                </div>
                                <div class="stat-card">
                                    <div class="label">{{ __('Page') }}</div>
                                    <div class="value">{{ method_exists($all_services, 'currentPage') ? $all_services->currentPage() : 1 }}</div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('all.category.subcategory') }}" class="fm-btn w-100">{{ __('Browse categories') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-fm-section pt-0">
        <div class="container">
            @if(!empty($all_services))
                <div class="fm-grid-3">
                    @foreach($all_services as $service)
                        <article class="single-service">
                            <a href="{{ route('service.list.details', $service->slug) }}" class="service-thumb service-bg-thumb-format" {!! render_background_image_markup_by_attachment_id($service->image) !!}>
                                @if($service->featured == 1)
                                    <div class="award-icons">
                                        <i class="las la-award"></i>
                                    </div>
                                @endif
                            </a>
                            <div class="services-contents">
                                <ul class="author-tag">
                                    <li class="tag-list">
                                        <a href="{{ route('about.seller.profile', optional($service->seller)->username) }}">
                                            <div class="authors">
                                                <div class="thumb">
                                                    {!! render_image_markup_by_attachment_id(optional($service->seller)->image) !!}
                                                    <span class="notification-dot"></span>
                                                </div>
                                                <span class="author-title">{{ optional($service->seller)->name }}</span>
                                            </div>
                                        </a>
                                    </li>
                                    @if($service->reviews->where('type', 1)->count() >= 1)
                                        <li class="tag-list">
                                            <a href="javascript:void(0)">
                                                <span class="icon">{{ __('Rating:') }}</span>
                                                <span class="reviews">
                                                    {{ round(optional($service->reviews->where('type', 1))->avg('rating'), 1) }}
                                                    ({{ optional($service->reviews->where('type', 1))->count() }})
                                                </span>
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                                <h5 class="common-title">
                                    <a href="{{ route('service.list.details', $service->slug) }}">{{ Str::limit($service->title, 48) }}</a>
                                </h5>
                                <p class="common-para">{{ Str::limit(strip_tags($service->description), 110) }}</p>
                                <div class="service-price">
                                    <span class="starting">{{ __('Starting at') }}</span>
                                    <span class="prices">{{ amount_with_currency_symbol($service->price) }}</span>
                                </div>
                                <div class="btn-wrapper d-flex flex-wrap">
                                    <a href="{{ route('service.list.book', $service->slug) }}" class="cmn-btn btn-small btn-bg-1">{{ __('Book Now') }}</a>
                                    <a href="{{ route('service.list.details', $service->slug) }}" class="cmn-btn btn-small btn-outline-1 ml-auto">{{ __('View Details') }}</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if($all_services->count() >= 6)
                    <div class="col-lg-12">
                        <div class="blog-pagination margin-top-55">
                            <div class="custom-pagination mt-4 mt-lg-5">
                                {!! $all_services->links() !!}
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <div class="alert alert-info mb-0">{{ __('No live services are available yet.') }}</div>
            @endif
        </div>
    </section>
@endsection
