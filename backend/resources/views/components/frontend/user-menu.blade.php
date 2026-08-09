@if(Auth::guard('web')->check())


<div class="info-bar-item d-lg-none">
    @if(auth('web')->check() && Auth()->guard('web')->user()->unreadNotifications()->count() > 0)
        @if(Auth::guard('web')->check() && Auth::guard('web')->user()->user_type==0)
            <div class="notification-icon icon">
                @if(Auth::guard('web')->check())
                    <i class="las la-bell"></i>
                    <span class="notification-number">
                {{ Auth()->user()->unreadNotifications()->where('data->order_message' , 'You have a new order')->count() }}
            </span>
                @endif

                <div class="notification-list-item mt-2">
                    <h5 class="notification-title">{{ __('Notifications') }}</h5>
                    <div class="list">
                        @if(Auth::guard('web')->check() && Auth::guard('web')->user()->unreadNotifications()->where('data->order_message' , 'You have a new order')->count() >=1)
                            <span>
                        @foreach(Auth::user()->unreadNotifications->take(5) as $notification)
                                    @if(isset($notification->data['order_id']))
                                        <a class="list-order" href="{{ route('seller.order.details',$notification->data['order_id']) }}">
                                    <span class="order-icon"> <i class="las la-check-circle"></i> </span>
                                    {{ $notification->data['order_message'] }} #{{ $notification->data['order_id'] }}
                                </a>
                                    @endif
                                @endforeach
                    </span>
                            <a class="p-2 text-center d-block" href="{{ route('seller.notification.all') }}">{{ __('View All Notification') }}</a>
                        @else
                            <p class="text-center padding-3">{{ __('No New Notification') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>

<div class="login-account">
    <div class="info-bar-item-two">
        <div class="author-thumb">
            @if(!empty(Auth::guard('web')->user()->image))
                {!! render_image_markup_by_attachment_id(Auth::guard('web')->user()->image) !!}
            @else
                <img src="{{ asset('assets/frontend/img/static/user_profile.png') }}" alt="No Image">
            @endif

        </div>
        <a class="accounts loggedin" href="javascript:void(0)">
            <span class="title"> {{Auth::guard('web')->user()->name}} </span>
        </a>
        <ul class="account-list-item mt-2">
            <li class="list">
                @if(Auth::guard('web')->user()->user_type==0)
                <a href="{{ route('seller.dashboard')}}"> {{ __('Dashboard') }} </a>
                @else
                <a href="{{ route('buyer.dashboard')}}"> {{ __('Dashboard') }} </a>
                @endif
            </li>
            <li class="list"> <a href="{{ route('seller.logout')}}"> {{ __('Logout') }} </a> </li>
        </ul>
    </div>
</div>

@else
    <div class="login-account">
        <a class="accounts" href="javascript:void(0)"> <span class="account">{{ __('Account') }}</span> <i class="las la-user"></i> </a>
        <ul class="account-list-item mt-2">
            <li class="list"> <a href="{{ route('user.register') }}"> {{ __('Sign Up') }} </a> </li>
            <li class="list"> <a href="{{ route('user.login') }}">{{ __('Sign In') }} </a> </li>
        </ul>
    </div>
@endif


