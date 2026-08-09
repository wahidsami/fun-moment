@extends('frontend.user.seller.seller-master')
@section('site-title')
    {{__('All Notifications')}}
@endsection
@section('content')
    <x-frontend.seller-buyer-preloader/>
    <!-- Dashboard area Starts -->
    <div class="body-overlay"></div>
    <div class="dashboard-area dashboard-padding">
        <div class="container-fluid">
            <div class="dashboard-contents-wrapper">
                <div class="dashboard-icon">
                    <div class="sidebar-icon">
                        <i class="las la-bars"></i>
                    </div>
                </div>
                @include('frontend.user.buyer.partials.sidebar')
                <div class="dashboard-right">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="dashboard-settings margin-top-40">
                                <h2 class="dashboards-title"> {{__('All Notifications')}}</h2>
                            </div>
                        </div>
                    </div>
                    @if(Auth::guard('web')->user()->notifications()->count() >= 1)
                        <div class="dashboard-service-single-item border-1 margin-top-40">
                            <div class="rows dash-single-inner">
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th>{{ __('No') }}</th>
                                        <th>{{ __('Title') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(Auth::guard('web')->user()->notifications()->paginate(20) as $key=>$notification)
                                        @if(isset($notification->data['last_ticket_id']))
                                            <tr>
                                                <td>{{ $key+1 }}</td>
                                                <td>
                                                    <a class="list-order" href="{{ route('buyer.support.ticket.view',$notification->data['last_ticket_id']) }}">
                                                        <span class="order-icon"> <i class="las la-check-circle"></i> </span>
                                                        {{ __($notification->data['order_ticcket_message']) }} #{{ $notification->data['last_ticket_id'] }}
                                                    </a>
                                                </td>
                                                <td>
                                                        <a href="{{ route('buyer.support.ticket.view',$notification->data['last_ticket_id']) }}" class="icon"><i class="las la-eye"></i></a>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach

                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="blog-pagination margin-top-55">
                            <div class="custom-pagination mt-4 mt-lg-5">
                                {{Auth::guard('web')->user()->notifications()->paginate(20)->links()}}
                            </div>
                        </div>
                    @else
                        <h2 class="no_data_found">{{ __('No Notification Found') }}</h2>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection