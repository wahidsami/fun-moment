@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('Buyer Dashboard')}}
@endsection
@section('content')
    <x-frontend.seller-buyer-preloader/>
    @php $default_lang = get_default_language(); @endphp
     <!-- Dashboard area Starts -->
    @include('frontend.user.buyer.partials.sidebar-two')
    <div class="dashboard__right">
        <!-- buyer header -->
        @include('frontend.user.buyer.header.buyer-header')
        <div class="dashboard__body">
            <div class="dashboard__inner">
                <div class="row g-4">
                    <div class="col-xxl-6 col-lg-12">
                        <div class="dashboard_promo__row row_col_2">
                            <div class="dashboard_promo__col dashboard_promo__child">
                                <div class="dashboard_promo bg-white">
                                    <div class="dashboard_promo__flex">
                                        <a href="{{ route('buyer.orders') }}">
                                            <div class="dashboard_promo__contents">
                                                <span class="dashboard_promo__subtitle">{{ __('Order In Progress') }}</span>
                                                <h4 class="dashboard_promo__title mt-2">{{ $active_order }}</h4>
                                            </div>
                                        </a>
                                        <div class="dashboard_promo__icon">
                                            <i class="fa-solid fa-hourglass-end"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="dashboard_promo__col dashboard_promo__child">
                                <div class="dashboard_promo bg-white">
                                    <div class="dashboard_promo__flex">
                                        <a href="{{ route('buyer.orders') }}">
                                            <div class="dashboard_promo__contents">
                                                <span class="dashboard_promo__subtitle">{{ __('Order Pending') }}</span>
                                                <h4 class="dashboard_promo__title mt-2">{{ $pending_order }}</h4>
                                            </div>
                                        </a>
                                        <div class="dashboard_promo__icon">
                                            <i class="fa-solid fa-list-ul"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="dashboard_promo__col dashboard_promo__child">
                                <div class="dashboard_promo bg-white">
                                    <div class="dashboard_promo__flex">
                                        <a href="{{ route('buyer.orders') }}">
                                            <div class="dashboard_promo__contents">
                                                <span class="dashboard_promo__subtitle">{{ __('Order Completed') }}</span>
                                                <h4 class="dashboard_promo__title mt-2">{{ $complete_order }}</h4>
                                            </div>
                                        </a>
                                        <div class="dashboard_promo__icon">
                                            <i class="fa-regular fa-square-check"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="dashboard_promo__col dashboard_promo__child">
                                <div class="dashboard_promo bg-white">
                                    <div class="dashboard_promo__flex">
                                        <a href="{{ route('buyer.orders') }}">
                                            <div class="dashboard_promo__contents">
                                                <span class="dashboard_promo__subtitle">{{ __('Total Order') }}</span>
                                                <h4 class="dashboard_promo__title mt-2">{{ $total_order }}</h4>
                                            </div>
                                        </a>
                                        <div class="dashboard_promo__icon">
                                            <i class="fa-solid fa-clipboard-list"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Notifications Section start -->
                    <div class="col-xxl-6 col-lg-12">
                        <div class="dashboard__notification bg-white padding-20 radius-10">
                            <div class="dashboard__notification__flex">
                                <h4 class="dashboard__notification__title">{{ __('Notifications') }}</h4>
                                <a href="{{ route('buyer.clear.notifications') }}" class="dashboard__notification__clearBtn">{{ __('Clear all') }}</a>
                            </div>
                            <div class="dashboard__notification__inner profile_border_top">
                                <!--Buyer All Notifications start-->
                                {{-- todo: first check auth user and check buyer all unread-message list --}}
                                @if(Auth::guard('web')->check() && Auth::guard('web')->user()->user_type==1)
                                    @if(Auth::guard('web')->check() && Auth::guard('web')->user()->unreadNotifications->count() >=1)
                                        @foreach(Auth::guard('web')->user()->unreadNotifications->take(10) as $notification)
                                            <div class="dashboard__notification__item">
                                                <div class="dashboard__notification__item__author">
                                                    <div class="dashboard__header__notification__wrap__list__icon">
                                                        <i class="las la-bell"></i>
                                                    </div>
                                                    @if(isset($notification->data['last_ticket_id']))
                                                        <div class="dashboard__notification__item__author__contents">
                                                            <p class="dashboard__notification__item__author__details">
                                                                @php $ticket_id_find = \App\SupportTicket::find($notification->data['last_ticket_id']) @endphp
                                                                <a href="@if(!empty($ticket_id_find)) {{ route('buyer.support.ticket.view',$notification->data['last_ticket_id']) }} @endif ">
                                                                    {{ $notification->data['order_ticcket_message']  }} #{{ $notification->data['last_ticket_id'] }}
                                                                </a>
                                                            </p>
                                                            <span class="dashboard__notification__item__time">{{ date('d/m/Y h:i A', strtotime($notification->created_at)) }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <p class="text-center padding-3" style="color:#111;">{{ __('No New Notification') }}</p>
                                    @endif
                                @endif
                                <!--Buyer All Notifications end-->
                            </div>
                        </div>
                    </div>
                    <!-- Notifications Section end -->
                </div>
                <div class="row g-4 mt-1">
                    <div class="col-xxl-5">
                        <div class="dashboard_recentOrder dashboard_border  bg-white padding-20 radius-10">
                            <h4 class="dashboard_recentOrder__title">{{ __('Recent Order') }}</h4>
                            <div class="dashboard_recentOrder__inner">
                                @if($last_6_order_dash_two->count() >= 1)
                                    @foreach($last_6_order_dash_two as $order)
                                        <div class="dashboard_recentOrder__item recentOrder_parent radius-10 mt-3">
                                            <div class="dashboard_recentOrder__item__flex">
                                                <div class="dashboard_recentOrder__item__service">
                                                    @if(!empty($order->job_post_id))
                                                        <a href="{{ route('buyer.order.details', $order->id) }}" >
                                                            <div class="dashboard_recentOrder__item__service__thumb" style="height: 120px; width: 120px">
                                                                {!! render_image_markup_by_attachment_id(optional($order->job)->image, '', 'thumb') !!}
                                                            </div>
                                                        </a>
                                                    @else
                                                        <a href="{{ route('buyer.order.details', $order->id) }}" >
                                                            <div class="dashboard_recentOrder__item__service__thumb" style="height: 120px; width: 120px">
                                                                {!! render_image_markup_by_attachment_id(optional($order->service)->image, '', 'thumb') !!}
                                                            </div>
                                                        </a>
                                                    @endif

                                                    <div class="dashboard_recentOrder__item__service__contents">
                                                        <a href="{{ route('buyer.order.details', $order->id) }}" class="dashboard_recentOrder__item__service__id"><span>{{ __('Order ID:') }}</span> {{ $order->id }}</a>
                                                        <h4 class="dashboard_recentOrder__item__service__title mt-1">
                                                            @if(!empty($order->job_post_id))
                                                                <a href="{{ route('job.post.details', optional($order->job)->slug) }}"> {{ optional($order->job)->title }}</a></h4>
                                                        @else
                                                            @if(!is_null(optional($order->service)->slug))
                                                                <a href="{{ route('service.list.details', optional($order->service)->slug ?? '') }}"> {{ optional($order->service)->title }}</a></h4>
                                                            @endif
                                                        @endif
                                                        <p class="dashboard_recentOrder__item__service__buyer mt-2">
                                                            {{ __('Order Date:') }} <strong>{{ Carbon\Carbon::parse($order->created_at)->format('d/m/y') }}{{ __(',') }}</strong>
                                                            {{ __('Seller:') }}   <a href="{{ route('about.seller.profile',optional($order->seller)->username) }}"> {{ optional($order->seller)->name }} </a>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="dashboard_recentOrder__item__icon">
                                                    <div class="dashboard_table__main__actions">
                                                        <a href="{{ route('buyer.order.details', $order->id) }}" class="icon"><i class="fa-regular fa-eye"></i></a>
                                                    </div>
                                                    <span class="dashboard_recentOrder__item__icon__single toggle_recentOrder"><i class="fa-solid fa-angle-down"></i></span>
                                                </div>
                                            </div>
                                            <div class="dashboard_recentOrder__item__details recentOrder_children">
                                                <div class="dashboard_recentOrder__item__details__item">
                                                    <p class="dashboard_recentOrder__item__details__item__left">{{ __('Booking Date & Time:') }}</p>
                                                    @if($order->date === 'No Date Created')
                                                        <p>{{ __('No Date Created') }}</p>
                                                    @else
                                                        <p class="dashboard_recentOrder__item__details__item__right">{{ Carbon\Carbon::parse($order->date)->format('d/m/y') }} <span>{{ $order->schedule }}</span></p>
                                                    @endif
                                                </div>

                                                <div class="dashboard_recentOrder__item__details__item">
                                                    <p class="dashboard_recentOrder__item__details__item__left">{{ __('Order type:')}}</p>
                                                    <p class="dashboard_recentOrder__item__details__item__right">
                                                        @php $online = __('Online'); $offline = __('Offline')  @endphp
                                                        @if($order->is_order_online == 1) {{ $online }} @else {{ $offline }} @endif
                                                    </p>
                                                </div>
                                                <div class="dashboard_recentOrder__item__details__item">
                                                    <p class="dashboard_recentOrder__item__details__item__left">{{ __('Order amount:')}}</p>
                                                    <p class="dashboard_recentOrder__item__details__item__right">{{ amount_with_currency_symbol($order->total) }}</p>
                                                </div>
                                                <div class="dashboard_recentOrder__item__details__item">
                                                    <p class="dashboard_recentOrder__item__details__item__left">{{ __('Order status:')}}</p>
                                                    @if ($order->status == 0)<div class="dashboard_table__main__priority"><a href="javascript:void(0)" class="priorityBtn pending">{{ __('Pending') }}</a> </div> @endif
                                                    @if ($order->status == 1)<div class="dashboard_table__main__priority"><a href="javascript:void(0)" class="priorityBtn active">{{ __('Active') }}</a> </div> @endif
                                                    @if ($order->status == 2)<div class="dashboard_table__main__priority"><a href="javascript:void(0)" class="priorityBtn completed">{{ __('Completed') }}</a> </div> @endif
                                                    @if ($order->status == 3)<div class="dashboard_table__main__priority"><a href="javascript:void(0)" class="priorityBtn delivered">{{ __('Delivered') }}</a> </div> @endif
                                                    @if ($order->status == 4)<div class="dashboard_table__main__priority"><a href="javascript:void(0)" class="priorityBtn cancel">{{ __('Cancel') }}</a> </div> @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-center padding-3" style="color:#111;">{{ __('No New Order') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-7">
                        <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                            <h4 class="dashboard_table__title">{{ __('Recent Ticket') }}</h4>
                            @if($last_10_tickets->count() >= 1)
                                <div class="dashboard_table__main custom--table mt-4">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th> {{ __('Ticket Name/ID') }}</th>
                                            <th> {{ __('Order ID') }}</th>
                                            <th> {{ __('Priority') }}</th>
                                            <th> {{ __('Status') }}</th>
                                            <th> {{ __('Action') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($last_10_tickets as $ticket)
                                            <tr>
                                                <td>
                                                    <div class="dashboard_table__main__ticket">
                                                        <a href="{{ route('buyer.support.ticket.view', $ticket->id) }}">
                                                            <h5 class="dashboard_table__main__ticket__title">{{ $ticket->title }}</h5>
                                                            <span class="dashboard_table__main__ticket__id mt-2">{{ __('Ticket ID:') }} {{ $ticket->id }}</span>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="dashboard_table__main__orderId">{{ $ticket->order_id }}</div>
                                                </td>
                                                <td>
                                                    <div class="dashboard_table__main__priority">
                                                        @if ($ticket->priority == 'high')  <a href="javascript:void(0)" class="priorityBtn high">{{ __('High') }} </a>@endif
                                                        @if ($ticket->priority == 'low')  <a href="javascript:void(0)" class="priorityBtn low">{{ __('Low') }} </a>@endif
                                                        @if ($ticket->priority == 'medium')  <a href="javascript:void(0)" class="priorityBtn medium">{{ __('Medium') }} </a>@endif
                                                        @if ($ticket->priority == 'urgent')  <a href="javascript:void(0)" class="priorityBtn urgent">{{ __('Urgent') }} </a>@endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="dashboard_table__main__status dropdown__status">
                                                        @if($ticket->status === 'open')
                                                            <a href="javascript:void(0)"  class="dashboard_table__main__status__select dropdown__status__main Open">
                                                                {{ __(ucfirst($ticket->status)) }}</a>
                                                        @else
                                                            <a href="javascript:void(0)" class="dashboard_table__main__status__select dropdown__status__main Close">
                                                                {{ __(ucfirst($ticket->status)) }}</a>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="dashboard_table__main__actions">
                                                        <a href="{{ route('buyer.support.ticket.view', $ticket->id) }}" class="icon"><i class="fa-regular fa-eye"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-center padding-3" style="color:#111;">{{ __('No New Ticket') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- Dashboard area end -->
    <!-- Dashboard area end -->
@endsection
@section('scripts')
    <script src="{{asset('assets/backend/js/sweetalert2.js')}}"></script>
    <script>
        (function($){
            "use strict";

            $(document).ready(function(){

                $(document).on('click','.swal_delete_button',function(e){
                    e.preventDefault();
                    Swal.fire({
                        title: '{{__("Are you sure?")}}',
                        text: '{{__("You would not be able to revert this item!")}}',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: "{{__('Yes, delete it!')}}"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $(this).next().find('.swal_form_submit_btn').trigger('click');
                        }
                    });
                });

                $(document).on('click','.swal_status_button',function(e){
                    e.preventDefault();
                    Swal.fire({
                        title: '{{__("Are you sure to close status?")}}',
                        text: '{{__("You will not able to open it!")}}',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: "{{__('Yes, change it!')}}"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $(this).next().find('.swal_form_submit_btn').trigger('click');
                        }
                    });
                });

            });

        })(jQuery);
    </script>
@endsection
