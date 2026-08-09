@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('Seller Dashboard')}}
@endsection
@section('style')
    <style>
        .dashboard__notification__item:not(:last-child) {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 16px;
            margin-bottom: 16px;
        }
    </style>
@endsection
@section('content')
    <x-frontend.seller-buyer-preloader/>
    @php $default_lang = get_default_language(); @endphp
    <!-- Dashboard area Starts -->
    @include('frontend.user.seller.partials.sidebar-two')
    <div class="dashboard__right">
        <!-- buyer header -->
        @include('frontend.user.buyer.header.buyer-header')
    <div class="dashboard__body">
        <div class="dashboard__inner">
            <div class="row g-4">
                @if(moduleExists('Subscription') && $commissionGlobal->system_type == 'subscription' )
                    @if(empty(auth('web')->user()->subscribedSeller))
                        <div class="col-lg-12 mt-1">
                                <div class="alert alert-warning d-flex justify-content-between">
                                    <strong style="font-size: 16px">{{__('you must have to subscribe any of our package in order to start selling your services.')}}</strong>
                                    <a href="{{getSlugFromReadingSetting('price_plan_page') ? url('/'.getSlugFromReadingSetting('price_plan_page')) : url('/price-plan')}}" target="_self" class="btn btn-secondary">{{__('view packages')}}</a>
                                </div>
                        </div>
                    @else
                        {{-- first check this seller subscribed then expire or subscribed expire info message show --}}
                        @if(!empty(Auth::guard('web')->user()->subscribedSeller))
                            @if(Carbon\Carbon::parse(auth('web')->user()->subscribedSeller->expire_date) <= \Carbon\Carbon::today())
                                <div class="alert alert-warning d-flex justify-content-between">
                                    <strong>{{__('your package has been expired, please renew it')}}</strong>
                                    <a href="{{getSlugFromReadingSetting('price_plan_page') ? url('/'.getSlugFromReadingSetting('price_plan_page')) : url('/price-plan')}}" target="_self" class="btn btn-secondary">{{__('view packages')}}</a>
                                </div>
                              @else
                                <div class="col-lg-12 mt-1">
                                    <div class="alert alert-info d-flex justify-content-between">
                                        <p>{{__('Your Subscribed Package:')}} <strong class="text-success">
                                                {{auth('web')->user()?->subscribedSeller?->subscription?->title}}</strong> {{__('Expire Date:')}}
                                            <strong class="text-danger">{{auth('web')->user()?->subscribedSeller->expire_date->format('d M Y')}}</strong>
                                        </p>
                                    </div>
                                </div>
                             @endif
                        @endif
                    @endif
                @endif
                <div class="col-xxl-6 col-lg-12">
                    <div class="dashboard_promo__row row_col_2">
                        <div class="dashboard_promo__col dashboard_promo__child">
                            <div class="dashboard_promo bg-white">
                                <div class="dashboard_promo__flex">
                                    <a href="{{ route('seller.orders') }}">
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
                                    <a href="{{ route('seller.orders') }}">
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
                                    <a href="{{ route('seller.orders') }}">
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
                                    <a href="{{ route('seller.orders') }}">
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

                        <div class="dashboard_promo__col dashboard_promo__child">
                            <div class="dashboard_promo bg-white">
                                <div class="dashboard_promo__flex">
                                    <a href="{{ route('seller.payout') }}">
                                    <div class="dashboard_promo__contents">
                                        <span class="dashboard_promo__subtitle">{{ __('Total Withdraw') }}</span>
                                        <h4 class="dashboard_promo__title mt-2">{{ float_amount_with_currency_symbol($total_earnings) }}</h4>
                                    </div>
                                    </a>
                                    <div class="dashboard_promo__icon">
                                        <i class="fa-solid fa-dollar"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard_promo__col dashboard_promo__child">
                            <div class="dashboard_promo bg-white">
                                <div class="dashboard_promo__flex">
                                    <a href="{{ route('seller.payout') }}">
                                    <div class="dashboard_promo__contents">
                                        <span class="dashboard_promo__subtitle">{{ __('Remaining Balance') }}</span>
                                        <h4 class="dashboard_promo__title mt-2"> {{ float_amount_with_currency_symbol($remaning_balance - $total_earnings)  }}
                                        </h4>
                                    </div>
                                    </a>
                                    <div class="dashboard_promo__icon">
                                        <i class="las la-file-invoice-dollar"></i>
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
                            <a href="{{ route('seller.clear.notifications') }}" class="dashboard__notification__clearBtn">{{ __('Clear all') }}</a>
                        </div>
                        <div class="dashboard__notification__inner profile_border_top">
                            <!--Buyer All Notifications start-->
                            {{-- todo: first check auth user and check buyer all unread-message list --}}
                            @if(Auth::guard('web')->check() && Auth::guard('web')->user()->user_type==0)
                                @if(Auth::guard('web')->check() && Auth::guard('web')->user()->unreadNotifications->count() >=1)
                                    @foreach(Auth::guard('web')->user()->unreadNotifications->take(5) as $notification)
                                    <div class="dashboard__notification__item">
                                        <div class="dashboard__notification__item__author">
                                            <div class="dashboard__header__notification__wrap__list__icon">
                                                <i class="las la-bell"></i>
                                            </div>
                                            <div class="dashboard__notification__item__author__contents">
                                                <p class="dashboard__notification__item__author__details">
                                                    <!--seller ticket notification -->
                                                    @if(isset($notification->data['seller_last_ticket_id']))
                                                    <a href="{{ route('seller.support.ticket.view',$notification->data['seller_last_ticket_id']) }}">
                                                    {{ $notification->data['order_ticcket_message']  }} #{{ $notification->data['seller_last_ticket_id'] }}
                                                    </a>
                                                    @endif
                                                    <!--seller order notification -->
                                                    @if(isset($notification->data['order_id']))
                                                        <a class="list-order" href="{{ route('seller.order.details',$notification->data['order_id']) }}">
                                                            {{ $notification->data['order_message'] }} #{{ $notification->data['order_id'] }}
                                                        </a>
                                                    @endif
                                                </p>
                                                <span class="dashboard__notification__item__time">{{ date('Y/m/d h:i A', strtotime($notification->created_at)) }}</span>
                                            </div>
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
                    <div class="col-xxl-12">
                        <div class="dashboard_recentOrder dashboard_border  bg-white padding-20 radius-10">
                            <h4 class="dashboard_recentOrder__title">{{ __('Recent Order') }}</h4>
                            <div class="dashboard_recentOrder__inner">
                                @if($last_five_order->count() >= 1)
                                    <div class="row g-4 mt-1">
                                        @foreach($last_five_order as $order)
                                        <div class="col-xxl-6">
                                          <div class="dashboard_recentOrder__item recentOrder_parent radius-10">
                                            <div class="dashboard_recentOrder__item__flex">
                                                <div class="dashboard_recentOrder__item__service">
                                                    <div class="dashboard_recentOrder__item__service__thumb">
                                                        <a href="{{ route('seller.order.details', $order->id) }}">
                                                            {!! render_image_markup_by_attachment_id(optional($order->service)->image, '', 'thumb') !!}
                                                        </a>
                                                    </div>
                                                    <div class="dashboard_recentOrder__item__service__contents">
                                                        <a href="{{ route('seller.order.details', $order->id) }}" class="dashboard_recentOrder__item__service__id">
                                                            <span>{{ __('Order ID:') }}</span> {{ $order->id }}</a>
                                                        <h4 class="dashboard_recentOrder__item__service__title mt-1">
                                                            @if(!empty($order->job_post_id))
                                                                <a href="{{ route('job.post.details', optional($order->job)->slug) }}"> {{ optional($order->job)->title }}</a></h4>
                                                             @else
                                                                @if(!is_null(optional($order->service)->slug))
                                                                <a href="{{ route('service.list.details', optional($order->service)->slug) }}"> {{ optional($order->service)->title }}</a></h4>
                                                                @endif
                                                            @endif
                                                        <p class="dashboard_recentOrder__item__service__buyer mt-2">
                                                            {{ __('Order Date:') }} <strong>{{ Carbon\Carbon::parse($order->created_at)->format('d/m/y') }}{{ __(',') }}</strong>
                                                            {{ __('Buyer:') }}   <a href="{{ route('about.buyer.profile',optional($order->buyer)->username) }}"> {{ optional($order->buyer)->name }} </a>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="dashboard_recentOrder__item__icon">
                                                    <div class="dashboard_table__main__actions">
                                                        <a href="{{ route('seller.order.details', $order->id) }}" class="icon"><i class="fa-regular fa-eye"></i></a>
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
                                        </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-center padding-3" style="color:#111;">{{ __('No New Order') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-6">
                        <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                            <h4 class="dashboard_table__title">{{ __('This Month Summery ') }}</h4>
                            <div class="dashboard_promo__row row_col_2 mt-2">
                                <div class="dashboard_promo__col dashboard_promo__child">
                                    <div class="dashboard_promo bg-white">
                                        <div class="dashboard_promo__flex">
                                            <div class="dashboard_promo__contents">
                                                <span class="dashboard_promo__subtitle">{{ __('Order') }}</span>
                                                <h4 class="dashboard_promo__title mt-2">{{ $this_month_order_count }}</h4>
                                            </div>
                                            <div class="dashboard_promo__icon">
                                                <i class="fa-solid fa-tasks"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="dashboard_promo__col dashboard_promo__child">
                                    <div class="dashboard_promo bg-white">
                                        <div class="dashboard_promo__flex">
                                            <div class="dashboard_promo__contents">
                                                <span class="dashboard_promo__subtitle">{{ __('Earning') }}</span>
                                                <h4 class="dashboard_promo__title mt-2">{{ float_amount_with_currency_symbol($this_month_earnings) }}</h4>
                                            </div>
                                            <div class="dashboard_promo__icon">
                                                <i class="fa-solid fa-dollar"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="dashboard_promo__col dashboard_promo__child">
                                    <div class="dashboard_promo bg-white">
                                        <div class="dashboard_promo__flex">
                                            <div class="dashboard_promo__contents">
                                                <span class="dashboard_promo__subtitle">{{ __('Balance') }}</span>
                                                <h4 class="dashboard_promo__title mt-2">{{ float_amount_with_currency_symbol($this_month_balance_without_tax_and_admin_commission) }}</h4>
                                            </div>
                                            <div class="dashboard_promo__icon">
                                                <i class="las la-file-invoice-dollar"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dashboard_promo__col dashboard_promo__child">
                                    <div class="dashboard_promo bg-white">
                                        <div class="dashboard_promo__flex">
                                            <div class="dashboard_promo__contents">
                                                <span class="dashboard_promo__subtitle">{{ __('Total Buyer') }}</span>
                                                <h4 class="dashboard_promo__title mt-2">{{ $buyer_count }}</h4>
                                            </div>
                                            <div class="dashboard_promo__icon">
                                                <i class="fa-solid fa-user"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-6">
                        <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                            <h4 class="dashboard_table__title">{{ __('To Do List') }}</h4>
                            <div class="text-end">
                                <a href="{{ route('seller.todolist') }}" class="dashboard__notification__clearBtn text-end">{{ __('See All') }}</a>
                            </div>

                            @if($to_do_list->count() >= 1)
                                <div class="dashboard_table__main custom--table mt-2">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th> {{ __('ID') }}</th>
                                            <th> {{ __('Description') }}</th>
                                            <th> {{ __('Action') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($to_do_list as $todo)
                                            <tr>
                                                <td>
                                                    <div class="dashboard_table__main__ticket">
                                                        <a href="{{ route('seller.support.ticket.view', $todo->id) }}">
                                                            <span class="dashboard_table__main__ticket__id mt-2"> {{ $todo->id }}</span>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td><div class="dashboard_table__main__ticket">{{ $todo->description }}</div></td>
                                                <td>
                                                    <div class="dashboard_table__main__actions">
                                                        <x-seller-coupon-status :url="route('seller.todolist.status',$todo->id)"/>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-center padding-3" style="color:#111;">{{ __('No New To Do List') }}</p>
                            @endif

                        </div>
                    </div>
                    <div class="col-xl-6">
                        <!-- chat js -->
                        <div class="dashboard-middle-flex">
                            <div class="single-flex-middle margin-top-40">
                                <div class="line-charts-wrapper">
                                    <div class="line-top-contents">
                                        <h4 class="dashboard_table__title">{{ __('Total Order Overview') }}</h4>
                                    </div>
                                    <div class="line-charts">
                                        <canvas id="line-chart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <!-- chat js -->
                        <div class="single-flex-middle margin-top-40">
                            <div class="line-charts-wrapper">
                                <div class="line-top-contents">
                                    <h4 class="dashboard_table__title">{{ __('Weekly Work Summary') }} </h4>
                                </div>
                                <div class="group-bar-charts">
                                    <canvas id="bar-chart-grouped"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
 </div><!-- Dashboard area end -->
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


                /* Line Charts */
                new Chart(document.getElementById("line-chart"), {
                    type: 'line',
                    data: {
                        labels: [@foreach($month_list as $list) "{{ $list }}", @endforeach],
                        datasets: [{
                            data: [@foreach($monthly_order_list as $list) "{{ $list }}", @endforeach],
                            label: "{{__('Order')}}",
                            borderColor: "#1DBF73",
                            borderWidth: 3,
                            fill: false,
                            pointBorderWidth: 2,
                            pointBackgroundColor: '#fff',
                            pointRadius: 5,
                            pointHoverRadius: 5,
                            pointHoverBackgroundColor: "#1DBF73",
                        }]
                    },

                });


                /* Group Bar Charts */
                new Chart(document.getElementById("bar-chart-grouped"), {
                    type: 'bar',
                    data: {
                        labels: [@foreach($days_list as $list) "{{ $list }}", @endforeach],
                        datasets: [
                            {
                                label: "{{__('Pending')}}",
                                backgroundColor: "#2F98DC",
                                data: [@foreach($pending_order_list as $list) "{{ $list }}", @endforeach],
                                barThickness: 10,
                                hoverBackgroundColor: '#fff',
                                hoverBorderColor: '#2F98DC',
                                borderColor: '#fff',
                                borderWidth: 2,
                            },
                            {
                                label: "{{__('Active')}}",
                                backgroundColor: "#FFB307",
                                data: [@foreach($active_order_list as $list) "{{ $list }}", @endforeach],
                                barThickness: 10,
                                hoverBackgroundColor: '#fff',
                                hoverBorderColor: '#FFB307',
                                borderColor: '#fff',
                                borderWidth: 2,
                            },
                            {
                                label: "{{__('Complete')}}",
                                backgroundColor: "#6560FF",
                                data: [@foreach($complete_order_list as $list) "{{ $list }}", @endforeach],
                                barThickness: 10,
                                hoverBackgroundColor: '#fff',
                                hoverBorderColor: '#6560FF',
                                borderColor: '#fff',
                                borderWidth: 2,
                            }
                        ],
                    },
                });


            });
        })(jQuery);
    </script>
@endsection