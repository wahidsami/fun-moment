@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('Add New Ticket')}}
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
                    <div class="dashboard_table__title__flex">
                        <h4 class="dashboards-title">  {{__('Add New Ticket')}} </h4>
                    </div>

                    <div class="row">
                        <div class="col-lg-6 margin-top-50">
                            <div class="single-settings">
                                <div class="mt-5"> <x-msg.error/> </div>
                                <h4 class="mb-3">{{ __('Ticket For:') }} {{ optional($order->service)->title }}</h4>
                                <h4 class="mb-5">{{ __('Order ID:') }} #{{ $order->id }}</h4>
                                <form action="{{route('seller.support.ticket.new')}}" method="post" enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="buyer_id" value="{{ $order->buyer_id }}">
                                    <input type="hidden" name="service_id" value="{{ $order->service_id }}">
                                    <input type="hidden" name="order_id" value="{{ $order->id }}">

                                    <div class="single-dashboard-input">
                                        <div class="single-info-input margin-top-30">
                                            <label for="title" class="info-title"> {{__('Title*')}} </label>
                                            <input class="form--control" name="title" id="title" value="{{ @old('title') }}" type="text" placeholder="{{__('Add Title')}}">
                                        </div>
                                    </div>
                                    <div class="single-dashboard-input">
                                        <div class="single-info-input margin-top-30">
                                            <label for="subject" class="info-title"> {{__('Subject*')}} </label>
                                            <input class="form--control" name="subject" id="subject" value="{{ @old('subject') }}" type="text" placeholder="{{__('Add Subject')}}">
                                        </div>
                                    </div>
                                    <div class="single-dashboard-input">
                                        <div class="single-info-input margin-top-30">
                                            <label for="priority" class="info-title"> {{__('Priority*')}} </label>
                                            <select name="priority" id="priority">
                                                <option value="low">{{__('Low')}}</option>
                                                <option value="medium">{{__('Medium')}}</option>
                                                <option value="high">{{__('High')}}</option>
                                                <option value="urgent">{{__('Urgent')}}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="single-dashboard-input">
                                        <div class="single-info-input margin-top-30">
                                            <label for="description" class="info-title"> {{__('Description*')}} </label>
                                            <textarea class="form--control textarea--form" name="description" placeholder="{{__('Type Description')}}" style="height: 114px;"></textarea>
                                        </div>
                                    </div>
                                    <div class="btn-wrapper margin-top-30">
                                        <button type="submit" class="dashboard_table__title__btn btn-bg-1 radius-5" style="border: none"> {{__('Submit Ticket')}} </button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
@endsection