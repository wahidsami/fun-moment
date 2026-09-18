@extends('frontend.frontend-master')

@section('page-meta-data')
    {!! render_site_title(get_static_option('site_title') ?? __('FUN MOMENT')) !!}
    {!! render_site_meta() !!}
@endsection

@section('content')
    @php
        $countries = \App\Country::where('status', 1)->orderBy('country')->get();
        $featuredCategories = \App\Category::where('status', 1)
            ->withCount('services')
            ->orderByDesc('services_count')
            ->take(8)
            ->get();
        $featuredServices = \App\Service::with(['seller', 'reviews', 'serviceCity'])
            ->where('status', 1)
            ->where('is_service_on', 1)
            ->latest()
            ->take(8)
            ->get();
        $featuredSellers = \App\User::where('user_type', 0)
            ->where('user_status', 1)
            ->latest()
            ->take(4)
            ->get();
        $latestBlogs = \App\Blog::where('status', 'publish')->latest()->take(3)->get();
        $pageBuilderContent = \App\PageBuilder\PageBuilderSetup::render_frontend_pagebuilder_content_by_location('homepage');

        $servicesCount = \App\Service::where('status', 1)->where('is_service_on', 1)->count();
        $sellersCount = \App\User::where('user_type', 0)->where('user_status', 1)->count();
        $categoriesCount = \App\Category::where('status', 1)->count();
        $ordersCount = \App\Order::count();
    @endphp

    <section class="fm-hero">
        <div class="container">
            <div class="hero-shell">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="hero-badge">
                            <i class="las la-bolt"></i>
                            {{ __('Live marketplace, real bookings, no demo content') }}
                        </span>
                        <h1 class="hero-title">
                            {{ __('Plan the moments, book the service, and keep every order in one place.') }}
                        </h1>
                        <p class="hero-subtitle">
                            {{ __('FUN MOMENT connects customers, providers, and admin operations through a single Laravel-powered web experience with real service listings, booking flow, and account access.') }}
                        </p>
                        <div class="hero-actions">
                            <a href="{{ route('all.category.subcategory') }}" class="fm-btn">
                                {{ __('Browse Categories') }}
                            </a>
                            <a href="{{ route('service.list.category') }}" class="btn-outline-1">
                                {{ __('Explore Services') }}
                            </a>
                            <a href="{{ route('user.register', ['type' => 'seller']) }}" class="btn-outline-1">
                                {{ __('Become a Provider') }}
                            </a>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="label">{{ __('Active services') }}</div>
                                <div class="value">{{ number_format($servicesCount) }}</div>
                            </div>
                            <div class="stat-card">
                                <div class="label">{{ __('Providers') }}</div>
                                <div class="value">{{ number_format($sellersCount) }}</div>
                            </div>
                            <div class="stat-card">
                                <div class="label">{{ __('Categories') }}</div>
                                <div class="value">{{ number_format($categoriesCount) }}</div>
                            </div>
                            <div class="stat-card">
                                <div class="label">{{ __('Bookings') }}</div>
                                <div class="value">{{ number_format($ordersCount) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="hero-visual p-4">
                            <div class="hero-visual__chip mb-4">
                                <i class="las la-search"></i>
                                {{ __('Search live services') }}
                            </div>
                            <div class="fm-glass-card p-4">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <img src="{{ asset('logo.png') }}" alt="{{ get_static_option('site_title') ?? __('FUN MOMENT') }}" style="width:72px;height:72px;object-fit:contain;">
                                    <div>
                                        <h3 class="mb-1">{{ __('Book with confidence') }}</h3>
                                        <p class="mb-0 fm-muted">{{ __('Use the live search below to find categories, providers, and services.') }}</p>
                                    </div>
                                </div>
                                <div class="fm-grid-2">
                                    <div class="stat-card">
                                        <div class="label">{{ __('Featured services') }}</div>
                                        <div class="value">{{ number_format($featuredServices->count()) }}</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="label">{{ __('Latest blogs') }}</div>
                                        <div class="value">{{ number_format($latestBlogs->count()) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fm-search-panel mt-4">
                    <form action="{{ route('frontend.home.search.single') }}" method="get">
                        <div class="home_search_box">
                            <div>
                                <label class="forms-label mb-2">{{ __('Search') }}</label>
                                <input type="search" id="home_search" name="home_search" class="form--control" placeholder="{{ __('Search services, providers, or experiences') }}">
                            </div>
                            <div>
                                <label class="forms-label mb-2">{{ __('Country') }}</label>
                                <select id="service_country_id" name="country_id" class="form--control">
                                    <option value="">{{ __('Select Country') }}</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->country }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="forms-label mb-2">{{ __('City') }}</label>
                                <select id="service_city_id" name="service_city_id" class="form--control">
                                    <option value="">{{ __('Select City') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="forms-label mb-2">{{ __('Area') }}</label>
                                <select id="service_area_id" name="service_area_id" class="form--control">
                                    <option value="">{{ __('Select Area') }}</option>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="fm-btn w-100">
                                    {{ __('Find Now') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="home-fm-section">
        <div class="container">
            <div class="section-heading d-flex flex-wrap justify-content-between align-items-end gap-3">
                <div>
                    <span class="fm-section-tag">{{ __('Categories') }}</span>
                    <h2 class="section-title title">{{ __('Choose the right experience category') }}</h2>
                    <p class="section-para mb-0">{{ __('Everything below comes from the live database and keeps the marketplace grounded in real content.') }}</p>
                </div>
                <a href="{{ route('all.category.subcategory') }}" class="btn-outline-1">{{ __('View all categories') }}</a>
            </div>

            @if($featuredCategories->count() > 0)
                <div class="fm-grid-4">
                    @foreach($featuredCategories as $category)
                        <a href="{{ route('service.list.category', $category->slug) }}" class="single-category">
                            <div class="icon">
                                @if(!empty($category->image))
                                    {!! render_image_markup_by_attachment_id($category->image) !!}
                                @else
                                    <div class="hero-visual__chip">{{ strtoupper(substr($category->name, 0, 2)) }}</div>
                                @endif
                            </div>
                            <div class="category-contents">
                                <h4 class="category-title">{{ $category->name }}</h4>
                                <span class="category-para">{{ number_format($category->services_count) }} {{ __('services') }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="alert alert-info mb-0">{{ __('No categories are available yet.') }}</div>
            @endif
        </div>
    </section>

    <section class="home-fm-section">
        <div class="container">
            <div class="section-heading d-flex flex-wrap justify-content-between align-items-end gap-3">
                <div>
                    <span class="fm-section-tag">{{ __('Featured Services') }}</span>
                    <h2 class="section-title title">{{ __('Real services ready for booking') }}</h2>
                    <p class="section-para mb-0">{{ __('Each card reflects the current services table, so the homepage never invents listings that do not exist.') }}</p>
                </div>
                <a href="{{ route('service.list.category') }}" class="btn-outline-1">{{ __('Open services catalog') }}</a>
            </div>

            @if($featuredServices->count() > 0)
                <div class="fm-grid-4">
                    @foreach($featuredServices as $service)
                        <article class="single-service">
                            <a href="{{ route('service.list.details', $service->slug) }}" class="service-thumb service-bg-thumb-format" {!! render_background_image_markup_by_attachment_id($service->image) !!}>
                                @if($service->featured == 1)
                                    <div class="award-icons"><i class="las la-award"></i></div>
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
                                    <li class="tag-list">
                                        <a href="javascript:void(0)">
                                            <span class="icon"><i class="las la-star"></i></span>
                                            <span class="reviews">{{ round(optional($service->reviews)->avg('rating'), 1) ?: '0.0' }}</span>
                                        </a>
                                    </li>
                                </ul>
                                <h5 class="common-title">
                                    <a href="{{ route('service.list.details', $service->slug) }}">{{ Str::limit($service->title, 42) }}</a>
                                </h5>
                                <p class="common-para">{{ Str::limit(strip_tags($service->description), 95) }}</p>
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
            @else
                <div class="alert alert-warning mb-0">{{ __('No live services are available yet.') }}</div>
            @endif
        </div>
    </section>

    <section class="home-fm-section">
        <div class="container">
            <div class="section-heading">
                <span class="fm-section-tag">{{ __('How it works') }}</span>
                <h2 class="section-title title">{{ __('Book in three simple steps') }}</h2>
            </div>
            <div class="fm-grid-3">
                <div class="home-fm-panel p-4">
                    <h4 class="mb-3">{{ __('1. Search') }}</h4>
                    <p class="mb-0 fm-muted">{{ __('Search real services by category, city, area, provider, or keyword.') }}</p>
                </div>
                <div class="home-fm-panel p-4">
                    <h4 class="mb-3">{{ __('2. Review') }}</h4>
                    <p class="mb-0 fm-muted">{{ __('Compare provider profiles, live ratings, availability, and service details before booking.') }}</p>
                </div>
                <div class="home-fm-panel p-4">
                    <h4 class="mb-3">{{ __('3. Book securely') }}</h4>
                    <p class="mb-0 fm-muted">{{ __('Use the supported payment flow and keep the order in the same backend used by admin and mobile.') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="home-fm-section">
        <div class="container">
            <div class="section-heading d-flex flex-wrap justify-content-between align-items-end gap-3">
                <div>
                    <span class="fm-section-tag">{{ __('Providers') }}</span>
                    <h2 class="section-title title">{{ __('Featured providers from the live platform') }}</h2>
                </div>
                <a href="{{ route('all.sellers') }}" class="btn-outline-1">{{ __('View all providers') }}</a>
            </div>

            @if($featuredSellers->count() > 0)
                <div class="fm-grid-4">
                    @foreach($featuredSellers as $seller)
                        @php
                            $sellerServicesCount = \App\Service::where('seller_id', $seller->id)->where('status', 1)->where('is_service_on', 1)->count();
                            $sellerRating = \App\Review::where('seller_id', $seller->id)->avg('rating');
                        @endphp
                        <a href="{{ route('about.seller.profile', $seller->username) }}" class="profile-author-contents">
                            <div class="thumb mb-3">
                                {!! render_image_markup_by_attachment_id($seller->image) !!}
                            </div>
                            <h4 class="title mb-2">{{ $seller->name }}</h4>
                            <p class="fm-muted mb-2">{{ $sellerServicesCount }} {{ __('active services') }}</p>
                            <p class="mb-0">{{ __('Rating:') }} {{ number_format((float) $sellerRating, 1) ?: '0.0' }}</p>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="alert alert-info mb-0">{{ __('No providers are available yet.') }}</div>
            @endif
        </div>
    </section>

    <section class="home-fm-section">
        <div class="container">
            <div class="section-heading d-flex flex-wrap justify-content-between align-items-end gap-3">
                <div>
                    <span class="fm-section-tag">{{ __('Latest stories') }}</span>
                    <h2 class="section-title title">{{ __('News and guidance from the marketplace') }}</h2>
                </div>
                @if(Route::has('frontend.blog.category'))
                    <a href="{{ route('frontend.blog.category') }}" class="btn-outline-1">{{ __('Open blog') }}</a>
                @endif
            </div>

            @if($latestBlogs->count() > 0)
                <div class="fm-grid-3">
                    @foreach($latestBlogs as $blog)
                        <article class="single-service">
                            <a href="{{ route('frontend.blog.single', $blog->slug) }}" class="service-thumb service-bg-thumb-format" {!! render_blog_background_image_markup($blog->image) !!}></a>
                            <div class="services-contents">
                                <h5 class="common-title">
                                    <a href="{{ route('frontend.blog.single', $blog->slug) }}">{{ Str::limit($blog->title, 58) }}</a>
                                </h5>
                                <p class="common-para">{{ Str::limit(strip_tags($blog->description), 120) }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="alert alert-info mb-0">{{ __('No blog posts are published yet.') }}</div>
            @endif
        </div>
    </section>

    @if(!empty($page_details))
        <section class="home-fm-section">
            <div class="container">
                <div class="fm-glass-card p-4 p-lg-5">
                    @include('frontend.partials.pages-portion.dynamic-page-builder-part', ['page_post' => $page_details])
                </div>
            </div>
        </section>
    @elseif(!empty(trim($pageBuilderContent)))
        <section class="home-fm-section">
            <div class="container">
                <div class="fm-glass-card p-4 p-lg-5">
                    {!! $pageBuilderContent !!}
                </div>
            </div>
        </section>
    @endif
@endsection
