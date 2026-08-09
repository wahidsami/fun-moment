@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{ __('Histories') }}
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
                @if($decline_histories->count() >= 1)
                    <div class="dashboard-right">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="dashboard-settings margin-top-40">
                                    <h2 class="dashboards-title">#{{$order_id}}-{{ __('Order Decline History') }}</h2>
                                    <br>
                                    <a class="btn btn-success" href="{{ route('buyer.orders') }}">{{__('Back To Orders')}}</a>
                                </div>
                                <x-msg.success/>
                                <x-msg.error/>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12 margin-top-40">
                                <div>
                                    <div class="table-responsive table-responsive--md">
                                        <table id="all_order_table" class="custom--table table-td-padding">
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
                                                       <strong>{{ __('Name:') }}</strong> {{ optional($history->seller)->name }} <br>
                                                       <strong>{{ __('Email:') }}</strong>{{ optional($history->seller)->email }} <br>
                                                       <strong>{{ __('Phone:') }}</strong>{{ optional($history->seller)->phone }} <br>
                                                    </td>
                                                    <td><strong>{{ __('Decline Reason:') }}</strong>{{ $history->decline_reason }}</td>
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

                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <h2 class="no_data_found">{{ __('No History Found') }}</h2>
                @endif
            </div>
        </div>
    </div>
@endsection