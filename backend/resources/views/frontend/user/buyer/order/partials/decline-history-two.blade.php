@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{ __('Histories') }}
@endsection
@section('content')
    <x-frontend.seller-buyer-preloader/>

    @include('frontend.user.buyer.partials.sidebar-two')
    <div class="dashboard__right">
        @include('frontend.user.buyer.header.buyer-header')
        <div class="dashboard__body">
            <div class="dashboard__inner">
                <!-- Report section start-->
                <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                    <div class="dashboard_table__title__flex">
                        <h4 class="dashboards-title"> {{ __('Decline History') }} <br> {{ __('Order ID:') }} <span class="text-info"> {{ $order_id }} </span></h4>
                        <a class="btn btn-success" href="{{ route('buyer.orders') }}">{{__('Back To Orders')}}</a>

                    </div>
                    @if($decline_histories->count() >= 1)
                        <div class="dashboard_table__main custom--table mt-4">
                            <table>
                                <thead>
                                <tr>
                                    <th> {{ __('ID') }} </th>
                                    <th> {{ __('Seller Details') }} </th>
                                    <th>{{ __('Status') }} ({{ __('Decline Reason') }})</th>
                                </tr>
                                </thead>
                                <tbody>
                                    @foreach ($decline_histories as $history)
                                        <tr>
                                            <td data-label="{{__('History ID')}}"> {{ $history->id }} </td>
                                            <td data-label="{{__('Seller Name')}}">
                                                <strong>{{ __('Name: ') }}</strong> {{ optional($history->seller)->name }} <br>
                                                <strong>{{ __('Email: ') }}</strong>{{ optional($history->seller)->email }} <br>
                                                <strong>{{ __('Phone: ') }}</strong>{{ optional($history->seller)->phone }} <br>
                                            </td>
                                            <td><strong>{{ __('Decline Reason: ') }}</strong>{{ $history->decline_reason }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="blog-pagination margin-top-55">
                            <div class="custom-pagination mt-4 mt-lg-5">
                                {!! $decline_histories->links() !!}
                            </div>
                        </div>
                    @else
                        <h6 class="no_data_found mt-3">{{ __('No History Found') }}</h6>
                    @endif
                    <!-- reports section end -->
                </div>
            </div>
        </div>
@endsection
