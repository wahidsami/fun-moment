@extends('frontend.frontend-page-master')

@section('site-title')
    {{ __('Order Details') }}
@endsection

@section('content')
    <section class="fm-hero py-5">
        <div class="container">
            <div class="hero-shell">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="hero-badge">
                            <i class="las la-receipt"></i>
                            {{ __('Your live booking summary') }}
                        </span>
                        <h1 class="hero-title">{{ __('Order #') }}{{ $order_details->id }}</h1>
                        <p class="hero-subtitle mb-0">
                            {{ __('This page reflects the exact order stored in the Laravel database and shared with provider and admin views.') }}
                        </p>
                    </div>
                    <div class="col-lg-4">
                        <div class="fm-glass-card p-4">
                            <div class="stat-card">
                                <div class="label">{{ __('Payment gateway') }}</div>
                                <div class="value" style="font-size:20px">{{ $order_details->payment_gateway }}</div>
                            </div>
                            <div class="mt-3">
                                <div class="stat-card">
                                    <div class="label">{{ __('Order date') }}</div>
                                    <div class="value" style="font-size:20px">{{ $order_details->created_at->toFormattedDateString() }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-fm-section pt-0">
        <div class="container">
            <div class="fm-grid-2">
                <div class="fm-glass-card p-4 p-lg-5">
                    <h2 class="section-title title mb-4">{{ __('Booking information') }}</h2>
                    <ul class="profile-about">
                        <li>{{ __('Name:') }} <span>{{ $order_details->name }}</span></li>
                        <li>{{ __('Email:') }} <span>{{ $order_details->email }}</span></li>
                        <li>{{ __('Phone:') }} <span>{{ $order_details->phone }}</span></li>
                        <li>{{ __('City:') }} <span>{{ optional($order_details->city)->service_city }}</span></li>
                        <li>{{ __('Area:') }} <span>{{ optional($order_details->area)->service_city }}</span></li>
                        <li>{{ __('Post Code:') }} <span>{{ $order_details->post_code }}</span></li>
                        <li>{{ __('Address:') }} <span>{{ $order_details->address }}</span></li>
                    </ul>
                </div>

                <div class="fm-glass-card p-4 p-lg-5">
                    <h2 class="section-title title mb-4">{{ __('Order summary') }}</h2>
                    <div class="stat-card mb-3">
                        <div class="label">{{ __('Service') }}</div>
                        <div class="value" style="font-size:20px">{{ optional($order_details->service)->title }}</div>
                    </div>
                    <div class="stat-card mb-3">
                        <div class="label">{{ __('Status') }}</div>
                        <div class="value" style="font-size:20px">{{ ucfirst($order_details->payment_status ?? __('pending')) }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">{{ __('Payment gateway') }}</div>
                        <div class="value" style="font-size:20px">{{ $order_details->payment_gateway }}</div>
                    </div>
                </div>
            </div>

            <div class="fm-glass-card p-4 p-lg-5 mt-4">
                <h2 class="section-title title mb-4">{{ __('Pricing breakdown') }}</h2>
                <div class="fm-grid-4">
                    <div class="stat-card">
                        <div class="label">{{ __('Package fee') }}</div>
                        <div class="value" style="font-size:20px">{{ amount_with_currency_symbol($order_details->package_fee ?? 0) }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">{{ __('Extras') }}</div>
                        <div class="value" style="font-size:20px">{{ amount_with_currency_symbol($order_details->extra_service ?? 0) }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">{{ __('Tax') }}</div>
                        <div class="value" style="font-size:20px">{{ amount_with_currency_symbol($order_details->tax ?? 0) }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">{{ __('Total') }}</div>
                        <div class="value" style="font-size:20px">{{ amount_with_currency_symbol($order_details->total ?? 0) }}</div>
                    </div>
                </div>

                @if(!empty($order_includes) && $order_includes->count() > 0)
                    <div class="mt-4">
                        <h3 class="section-title title mb-3">{{ __('Included services') }}</h3>
                        <div class="fm-grid-2">
                            @foreach($order_includes as $include)
                                <div class="home-fm-panel p-3">
                                    <div class="d-flex justify-content-between gap-3">
                                        <strong>{{ $include->include_service_title ?? __('Included item') }}</strong>
                                        <span>{{ amount_with_currency_symbol($include->include_service_price ?? 0) }}</span>
                                    </div>
                                    @if(!empty($include->include_service_quantity))
                                        <div class="fm-muted mt-2">{{ __('Quantity:') }} {{ $include->include_service_quantity }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($order_additionals) && $order_additionals->count() > 0)
                    <div class="mt-4">
                        <h3 class="section-title title mb-3">{{ __('Extra services') }}</h3>
                        <div class="fm-grid-2">
                            @foreach($order_additionals as $additional)
                                <div class="home-fm-panel p-3">
                                    <div class="d-flex justify-content-between gap-3">
                                        <strong>{{ $additional->additional_service_title ?? __('Extra item') }}</strong>
                                        <span>{{ amount_with_currency_symbol($additional->additional_service_price ?? 0) }}</span>
                                    </div>
                                    @if(!empty($additional->additional_service_quantity))
                                        <div class="fm-muted mt-2">{{ __('Quantity:') }} {{ $additional->additional_service_quantity }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
