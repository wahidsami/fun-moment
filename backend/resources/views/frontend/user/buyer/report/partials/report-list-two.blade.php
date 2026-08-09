@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('Reports')}}
@endsection
@section('content')
    <x-frontend.seller-buyer-preloader/>
    @include('frontend.user.buyer.partials.sidebar-two')
    <div class="dashboard__right">
        @include('frontend.user.buyer.header.buyer-header')
        <div class="dashboard__body">

            <!-- search section start-->
            <div class="dashboard__inner__item dashboard_border padding-20 radius-10 bg-white">
                <div class="dashboard__wallet">
                    <form action="{{ route('buyer.order.report.list') }}" method="GET">
                    <div class="dashboard__headerGlobal__flex">
                        <div class="dashboard__headerGlobal__content">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                <h4 class="dashboard_table__title">{{ __('Search Report Module') }}</h4> <i class="las la-angle-down search_by_all"></i>
                            </button>
                        </div>
                        <div class="dashboard__headerGlobal__btn">
                            <div class="btn-wrapper">
                                <button href="#" class="dashboard_table__title__btn btn-bg-1 radius-5" type="submit">
                                    <i class="fa-solid fa-magnifying-glass"></i> {{ __('Search') }}</button>
                            </div>
                        </div>
                    </div>
                        <div class="accordion-item">
                            <div id="collapseOne" class="accordion-collapse collapse
                             @if(request()->get('order_id'))  show @elseif(request()->get('report_id')) show @elseif(request()->get('report_date')) show @endif
                             " aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="single-settings">
                                                <div class="single-dashboard-input">
                                                    <div class="row g-4 mt-3">
                                                        <div class="col-lg-4 col-sm-6">
                                                            <div class="single-info-input">
                                                                <label for="title" class="info-title"> {{__('Order ID')}} </label>
                                                                <input class="form--control" name="order_id" value="{{ request()->get('order_id') }}" type="text" placeholder="{{ __('Order ID') }}">
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-4 col-sm-6">
                                                            <div class="single-info-input">
                                                                <label for="title" class="info-title"> {{__('Report ID')}} </label>
                                                                <input class="form--control" name="report_id" value="{{ request()->get('report_id') }}" type="text" placeholder="{{ __('Report ID') }}">
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-4 col-sm-6">
                                                            <div class="single-info-input">
                                                                <label for="dead_line" class="info-title"> {{__('Created Date Range')}} </label>
                                                                <input class="form--control flatpickr_input" value="{{ request()->get('report_date') }}" name="report_date" type="date">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <!--search section end-->

            <div class="dashboard__inner mt-3">
                <!-- Report section start-->
                <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                    <div class="dashboard_table__title__flex">
                        <h4 class="dashboard_table__title">{{ __('All Reports') }}</h4>
                    </div>

                    @if($reports->count() >= 1)
                        <div class="dashboard_table__main custom--table mt-4">
                            <table>
                                <thead>
                                <tr>
                                    <th> {{ __('Order ID') }} </th>
                                    <th> {{ __('Report ID') }} </th>
                                    <th> {{ __('Report Details') }} </th>
                                    <th> {{ __('Seller Details') }} </th>
                                    <th> {{ __('Conversation') }} </th>
                                </tr>
                                </thead>
                                <tbody>

                                @foreach ($reports as $report)
                                    <tr>
                                        <td>
                                            <div class="dashboard_table__main__orderId">{{ $report->order_id }}</div>
                                        </td>
                                        <td>
                                            <div class="dashboard_table__main__orderId">{{ $report->id }}</div>
                                        </td>
                                        <td>
                                            <div class="dashboard_table__main__priority">
                                                <p><strong>{{ __('Report From:') }}</strong> {{ ucfirst($report->report_from) }}</p>
                                                <p><strong>{{ __('Report To:') }}</strong> {{ ucfirst($report->report_to) }}</p>
                                                <p><strong>{{ __('Report Date:') }}</strong> {{date('d-m-Y', strtotime($report->created_at))}}</p>
                                                <p><strong>{{ __('Description:') }}</strong>
                                                    <span class="btn btn-info report_description"
                                                          data-bs-toggle="modal"
                                                          data-bs-target="#reportModal"
                                                          data-report="{{ $report->report }}">
                                                        <i class="fa-regular fa-eye"></i></span></p>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="dashboard_table__main__status dropdown__status">
                                                <p><strong>{{ __('Name:') }}</strong> {{ optional($report->seller)->name }}</p>
                                                <p><strong>{{ __('Email:') }}</strong> {{ optional($report->seller)->email }}</p>
                                                <p><strong>{{ __('Phone:') }}</strong> {{ optional($report->seller)->phone }}</p>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="dashboard_table__main__actions">
                                                <a href="{{ route('buyer.order.report.chat.admin',$report->id) }}" class="btn btn-info">
                                                    {{ __('Chat To Admin') }} <i class="fa-regular fa-commenting mx-2"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach

                                </tbody>
                            </table>
                        </div>
                        <div class="blog-pagination margin-top-55">
                            <div class="custom-pagination mt-4 mt-lg-5">
                                {!! $reports->links() !!}
                            </div>
                        </div>
                    @else
                        <div class="chat_wrapper__details__inner__chat__contents mt-3">
                            <p class="no_data_found_for_buyer_seller_panel">
                                {{ __('No Reports found')}}
                            </p>
                        </div>
                    @endif
                    <!-- reports section end -->
                </div>
            </div>
        </div>
    </div>

        <!-- Report Details Modal -->
        <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            @csrf
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">{{ __('Report Details') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="custom-form">
                                <div class="row g-4">
                                    <div class="col-12">
                                        <div class="single-input">
                                            <p id="report_description"></p>
                                        </div>
                                    </div>
                                </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endsection
@section('scripts')
    <script>
        (function($){
            "use strict";
            $(document).ready(function(){

                $('.flatpickr_input').flatpickr({
                    altFormat: "invisible",
                    altInput: false,
                    mode: "range",
                });

                $(document).on('click','.report_description',function(e){
                    let report_description = $(this).data('report');
                    $('#report_description').text(report_description);
                });

            });
        })(jQuery);
    </script>
@endsection