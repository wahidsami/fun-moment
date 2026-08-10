@extends('frontend.frontend-page-master')

@section('site-title')
    {{ __('Category') }}
@endsection

@section('inner-title')
    {{ __('All Category') }}
@endsection

@section('content')
    <section class="fm-hero py-5">
        <div class="container">
            <div class="hero-shell">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="hero-badge">
                            <i class="las la-th-large"></i>
                            {{ __('Service categories from the live marketplace') }}
                        </span>
                        <h1 class="hero-title">{{ __('Discover the right category before you book') }}</h1>
                        <p class="hero-subtitle mb-0">
                            {{ __('Every tile below comes from the real category table and points to live service listings.') }}
                        </p>
                    </div>
                    <div class="col-lg-4">
                        <div class="fm-glass-card p-4">
                            <div class="stat-card">
                                <div class="label">{{ __('Available categories') }}</div>
                                <div class="value">{{ $all_category->count() }}</div>
                            </div>
                            <div class="mt-3">
                                <a href="{{ route('service.list.category') }}" class="fm-btn w-100">{{ __('View services') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-fm-section pt-0">
        <div class="container">
            @if($all_category->count() > 0)
                <div class="fm-grid-4">
                    @foreach($all_category as $cat)
                        <a href="{{ route('service.list.category', $cat->slug) }}" class="single-category">
                            <div class="icon">
                                {!! render_image_markup_by_attachment_id($cat->image,'','','thumb') !!}
                            </div>
                            <div class="category-contents">
                                <h4 class="category-title">{{ $cat->name }}</h4>
                                <span class="category-para">{{ $cat->services->count() }}+ {{ __('Service') }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="alert alert-info mb-0">{{ __('No categories are available yet.') }}</div>
            @endif
        </div>
    </section>
@endsection
