@php
    $navClasses = request()->is('/') ? 'navbar navbar-area white navbar-dark bg-dark nav-absolute navbar-expand-lg navbar-border' : 'navbar navbar-area white navbar-dark bg-dark navbar-expand-lg navbar-border';
@endphp
<header class="header-style-01">
    <nav class="{{ $navClasses }} {{ $page_post->page_class ?? '' }}">
        <div class="container container-two nav-container">
            <div class="responsive-mobile-menu">
                <div class="logo-wrapper">
                    <a href="{{ route('homepage') }}" class="logo">
                        {!! render_image_markup_by_attachment_id(get_static_option('site_white_logo')) !!}
                    </a>
                </div>

                <div class="onlymobile-device-account-navbar">
                    <div class="onlymobile-device-account-navbar-flex">
                        <x-frontend.user-menu/>
                    </div>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#bizcoxx_main_menu_navbar_one" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <div class="collapse navbar-collapse" id="bizcoxx_main_menu_navbar_one">
                <ul class="navbar-nav">
                    {!! render_frontend_menu($primary_menu) !!}
                </ul>
            </div>

            <div class="nav-right-content">
                <div class="navbar-right-inner">
                    <div class="info-bar-item">
                        @if(auth('web')->check() && Auth()->guard('web')->user()->unreadNotifications()->count() > 0)
                            @if(Auth::guard('web')->check() && Auth::guard('web')->user()->user_type==0)
                                <div class="notification-icon icon">
                                    @if(Auth::guard('web')->check())
                                        <span class="text-white"> <i class="las la-bell"></i> </span>
                                        <span class="notification-number">
                                        {{ Auth()->user()->unreadNotifications()->count() }}
                                    </span>
                                    @endif

                                    <div class="notification-list-item mt-2">
                                        <h5 class="notification-title">{{ __('Notifications') }}</h5>
                                        <div class="list">
                                            @if(Auth::guard('web')->check() && Auth::guard('web')->user()->unreadNotifications()->count() >=1)
                                                <span>

                                      <!-- seller ticket Notifications-->
                                        @foreach(Auth::guard('web')->user()->unreadNotifications->take(10) as $notification)
                                                        @if(isset($notification->data['seller_last_ticket_id']))
                                                            <a class="list-order" href="{{ route('seller.support.ticket.view',$notification->data['seller_last_ticket_id']) }}">
                                                            <span class="order-icon"> <i class="las la-check-circle"></i> </span>
                                                            {{ $notification->data['order_ticcket_message']  }} #{{ $notification->data['seller_last_ticket_id'] }}
                                                        </a>
                                            @endif
                                        @endforeach

                                               <!-- seller order Notifications-->
                                            @foreach(Auth::guard('web')->user()->unreadNotifications()->take(5) as $notification)
                                                        @if(isset($notification->data['order_id']))
                                                            <a class="list-order" href="{{ route('seller.order.details',$notification->data['order_id']) }}">
                                                        <span class="order-icon"> <i class="las la-check-circle"></i> </span>
                                                        {{ $notification->data['order_message']  }} #{{ $notification->data['order_id'] }}
                                                    </a>
                                                        @endif
                                                    @endforeach
                                        </span>

                                                <a class="p-2 text-center d-block" href="{{ route('seller.notification.all') }}">{{ __('View All Notification') }}</a>
                                            @else
                                                <p class="text-center text-white padding-3">{{ __('No New Notification') }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                    <x-frontend.user-menu/>
                </div>
            </div>
        </div>
    </nav>
</header>