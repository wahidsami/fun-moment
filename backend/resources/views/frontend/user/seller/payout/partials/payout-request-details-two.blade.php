@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{ __('Payout Request Details') }}
@endsection
@section('style')
    <style>
        img {
            max-width: 39%;
        }
    </style>
@endsection
@section('content')
    <x-frontend.seller-buyer-preloader/>
    @include('frontend.user.seller.partials.sidebar-two')
    <div class="dashboard__right">
        @include('frontend.user.buyer.header.buyer-header')
        <div class="dashboard__body">
            <div class="dashboard__inner">
                <!-- Report section start-->
                <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                    @if(!empty($request_details))
                        <div class="row">

                            <div class="col-md-4">
                                <div class="single-flex-middle">
                                    <div class="single-flex-middle-inner">
                                        <div class="line-charts-wrapper margin-top-40">

                                            <div class="line-top-contents mt-4">
                                                <h4 class="earning-title">{{ __('Payout Request Details') }}</h4>
                                            </div>
                                            <div class="single-checbox mt-3">
                                                <div class="checkbox-inlines">
                                                    <label><strong>{{ __('ID:') }} #</strong>{{ $request_details->id }}</label>
                                                </div>
                                                <div class="checkbox-inlines">
                                                    <label><strong>{{ __('Amount:') }} </strong>{{ float_amount_with_currency_symbol($request_details->amount) }}</label>
                                                </div>
                                                <div class="checkbox-inlines">
                                                    <label><strong>{{ __('Payment Gateway:') }} </strong>{{ __($request_details->payment_gateway) }}</label>
                                                </div>
                                                <div class="checkbox-inlines">
                                                    <label><strong>{{ __('Request Date:') }} </strong>{{ $request_details->created_at->toFormattedDateString() }}</label>
                                                </div>
                                                <div class="checkbox-inlines">
                                                    <label><strong>{{ __('Seller Note:') }} </strong>{{ $request_details->seller_note }}</label>
                                                </div>
                                                <div class="checkbox-inlines">
                                                    <label><strong>{{ __('Admin Note:') }} </strong>{{ $request_details->admin_note }}</label>
                                                </div>
                                            </div>

                                            <div class="line-top-contents mt-4">
                                                <h4 class="earning-title">{{ __('Payout Request Status') }}</h4>
                                            </div>
                                            <div class="single-checbox mt-3">
                                                <div class="checkbox-inlines">
                                                    <label><strong>{{ __('Status:') }} </strong>
                                                        @if ($request_details->status == 0) <span class="text-danger">{{ __('Pending') }}</span>@endif
                                                        @if ($request_details->status == 1) <span class="text-success">{{ __('Completed') }}</span>@endif
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-8">

                                <div class="single-flex-middle">
                                    <div class="single-flex-middle-inner">
                                        <div class="line-charts-wrapper margin-top-40">
                                            <div class="line-top-contents">
                                                <h4 class="earning-title">{{ __('Payout Receipt') }}</h4>
                                                <hr>
                                            </div>
                                            @if(!empty($request_details->payment_receipt))
                                                {!! render_image_markup_by_attachment_id($request_details->payment_receipt) !!}
                                            @endif
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </div>
                    @endif

                </div>
            </div>
        </div>
 @endsection