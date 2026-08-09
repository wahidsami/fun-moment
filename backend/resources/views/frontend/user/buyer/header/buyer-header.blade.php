@php
    $notice = \App\AdminNotice::where('status', 1)
    ->where('expire_date', '>', now())
    ->latest()
    ->where('notice_for', auth()->guard('web')->user()->user_type === 1 ? 2 : 3)->first();
@endphp
@if($notice)
    <div class="col-lg-12 m-2">
        <div class="alert
         @if($notice->notice_type === 1) alert-danger
         @elseif($notice->notice_type === 2) alert-warning
         @elseif($notice->notice_type === 3) alert-success
         @elseif($notice->notice_type === 4) alert-info
         @endif d-flex justify-content-between notice_for_frontend">
            <p> <strong class="text-dark">{{ $notice->title }}</strong>
                <strong>{{ $notice->description }} </strong>
            </p>
        </div>
    </div>
@endif

<div class="dashboard__header single_border_bottom">
    <div class="row g-4 justify-content-between">
        <div class="col-sm-6">
            <div class="dashboard__header__left">
                <h4 class="dashboard__header__title">{{ \Illuminate\Support\Facades\Auth::guard('web')->user()->name }} </h4>
                <p class="dashboard__header__para mt-2">{{ __('Manage your accounts activity from here') }}</p>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="dashboard__header__right">
                <div class="dashboard__header__right__flex">
                    <div class="dashboard__header__right__item">

                        <div class="dashboard__header__notification">
                            <a href="javascript:void(0)" class="dashboard__header__notification__icon"> <i class="fa-solid fa-bell"></i> </a>
                            <span class="dashboard__header__notification__number">{{ Auth::user()->unreadNotifications->count() }}</span>
                            <div class="dashboard__header__notification__wrap">
                                <h6 class="dashboard__header__notification__wrap__title">{{ __('Notifications') }}</h6>
                                <ul class="dashboard__header__notification__wrap__list">
                                    <!--Buyer All Notifications start-->
                                    {{-- todo: first check auth user and check buyer all unread-message list --}}
                                    @if(Auth::guard('web')->check() && Auth::guard('web')->user()->user_type==1)
                                        @if(Auth::guard('web')->check() && Auth::guard('web')->user()->unreadNotifications->count() >=1)
                                            @foreach(Auth::guard('web')->user()->unreadNotifications->take(10) as $notification)
                                                @if(isset($notification->data['last_ticket_id']))
                                                <li class="dashboard__header__notification__wrap__list__item">
                                                    <div class="dashboard__header__notification__wrap__list__flex">
                                                        <a class="dashboard__header__notification__wrap__list__contents__title"
                                                           href="{{ route('buyer.support.ticket.view',$notification->data['last_ticket_id'] ?? 'x') }}">
                                                        <div class="dashboard__header__notification__wrap__list__icon">
                                                            <i class="las la-bell"></i>
                                                        </div>
                                                        <div class="dashboard__header__notification__wrap__list__contents">
                                                                {{ $notification->data['order_ticcket_message'] ?? ''  }} #{{ $notification->data['last_ticket_id'] ?? '' }}
                                                            <span class="dashboard__header__notification__wrap__list__contents__sub"> {{ date('Y/m/d h:i A', strtotime($notification->created_at)) }}</span>
                                                        </div>
                                                        </a>
                                                    </div>
                                                </li>
                                                @endif

                                               @if(isset($notification->data['order_id']))
                                                <li class="dashboard__header__notification__wrap__list__item">
                                                    <div class="dashboard__header__notification__wrap__list__flex">
                                                        <a class="dashboard__header__notification__wrap__list__contents__title"
                                                           href="{{ route('buyer.order.details',$notification->data['order_id'] ?? 'x') }}">
                                                        <div class="dashboard__header__notification__wrap__list__icon">
                                                            <i class="las la-bell"></i>
                                                        </div>
                                                        <div class="dashboard__header__notification__wrap__list__contents">
                                                                {{ $notification->data['order_message'] ?? ''  }} #{{ $notification->data['order_id'] ?? '' }}
                                                            <span class="dashboard__header__notification__wrap__list__contents__sub"> {{ date('Y/m/d h:i A', strtotime($notification->created_at)) }}</span>
                                                        </div>
                                                        </a>
                                                    </div>
                                                </li>
                                                @endif

                                            @endforeach
                                        @else
                                            <p class="text-center padding-3 mt-2" style="color:#111;">{{ __('No New Notification') }}</p>
                                        @endif
                                        <div class="text-center mt-3">
                                            <a href="{{ route('buyer.notification.all') }}" class="dashboard__notification__clearBtn">{{ __('View all') }}</a>
                                        </div>
                                    @endif
                                    <!--Buyer All Notifications end-->

                                    <!--Seller All Notifications start-->
                                    {{-- todo: first check auth user and check buyer all unread-message list --}}
                                    @if(Auth::guard('web')->check() && Auth::guard('web')->user()->user_type==0)
                                        @if(Auth::guard('web')->check() && Auth::guard('web')->user()->unreadNotifications->count() >=1)
                                            @foreach(Auth::guard('web')->user()->unreadNotifications->take(10) as $notification)

                                                <!--seller order support ticket notification -->
                                                @if(isset($notification->data['seller_last_ticket_id']))
                                                    <li class="dashboard__header__notification__wrap__list__item">
                                                        <div class="dashboard__header__notification__wrap__list__flex">
                                                            <a class="dashboard__header__notification__wrap__list__contents__title"
                                                               href="{{ route('seller.support.ticket.view',$notification->data['seller_last_ticket_id']) }}">
                                                            <div class="dashboard__header__notification__wrap__list__icon">
                                                                <i class="las la-bell"></i>
                                                            </div>
                                                            <div class="dashboard__header__notification__wrap__list__contents">
                                                                    {{ $notification->data['order_ticcket_message']  }} #{{ $notification->data['seller_last_ticket_id'] }}
                                                                <span class="dashboard__header__notification__wrap__list__contents__sub"> {{ date('Y/m/d h:i A', strtotime($notification->created_at)) }}</span>
                                                            </div>
                                                            </a>
                                                         </div>
                                                    </li>
                                                @endif

                                            <!--seller order notification -->
                                                @if(isset($notification->data['order_id']))
                                                    <li class="dashboard__header__notification__wrap__list__item">
                                                        <div class="dashboard__header__notification__wrap__list__flex">
                                                            <a class="dashboard__header__notification__wrap__list__contents__title"
                                                               href="{{ route('seller.order.details',$notification->data['order_id']) }}">
                                                            <div class="dashboard__header__notification__wrap__list__icon"> <i class="las la-bell"></i> </div>
                                                            <div class="dashboard__header__notification__wrap__list__contents">
                                                                    {{ $notification->data['order_message'] }} #{{ $notification->data['order_id'] }}
                                                                <span class="dashboard__header__notification__wrap__list__contents__sub"> {{ date('Y/m/d h:i A', strtotime($notification->created_at)) }}</span>
                                                            </div>
                                                            </a>
                                                        </div>
                                                    </li>
                                                @endif
                                            @endforeach
                                        @else
                                            <p class="text-center padding-3 mt-2" style="color:#111;">{{ __('No New Notification') }}</p>
                                        @endif
                                        <div class="text-center mt-3">
                                            <a href="{{ route('seller.notification.all') }}" class="dashboard__notification__clearBtn">{{ __('View all') }}</a>
                                        </div>
                                    @endif
                                    <!--Seller All Notifications end-->
                                </ul>
                            </div>
                        </div>

                    </div>
                    <div class="dashboard__header__right__item">
                        <div class="dashboard__header__author">
                            <a href="javascript:void(0)" class="dashboard__header__author__flex flex-btn">
                                <div class="dashboard__header__author__thumb">
                                    {!! render_image_markup_by_attachment_id(Auth::guard('web')->user()->image) !!}
                                </div>
                            </a>
                            <div class="dashboard__header__author__wrapper">
                                <div class="dashboard__header__author__wrapper__list">
                                    @if(Auth::guard('web')->check() && Auth::guard('web')->user()->user_type==1)
                                        <a href="{{ route('buyer.profile')}}" class="dashboard__header__author__wrapper__list__item">{{ __('Profile') }}</a>
                                        <a href="{{ route('buyer.account.settings')}}" class="dashboard__header__author__wrapper__list__item">{{__('Settings')}}</a>
                                    @endif
                                    @if(Auth::guard('web')->check() && Auth::guard('web')->user()->user_type==0)
                                        <a href="{{ route('seller.profile')}}" class="dashboard__header__author__wrapper__list__item">{{ __('Profile') }}</a>
                                        <a href="{{ route('seller.account.settings')}}" class="dashboard__header__author__wrapper__list__item">{{__('Settings')}}</a>
                                    @endif
                                    <a href="{{ route('seller.logout')}}" class="dashboard__header__author__wrapper__list__item">{{__('Logout')}}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>