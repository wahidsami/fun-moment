@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{ __('Pending Orders') }}
@endsection
@section('style')
    <style>
        .btn-wrapper .cmn-btn.btn-bg-3 {
            background: var(--main-color-three);
            color: #fff;
        }
        .btn-wrapper .cmn-btn {
            color: var(--paragraph-color);
            font-size: 15px;
            font-weight: 500;
            font-family: var(--body-font);
            display: inline-block;
            border-radius: 5px;
            text-transform: capitalize;
            text-align: center;
            cursor: pointer;
            line-height: 24px;
            padding: 10px 17px;
            -webkit-transition: all .3s ease-in;
            transition: all .3s ease-in;
        }
        .dashboard_jobPost__views__para{
            font-size: 14px;
        }
        .close{
            border: none;
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
                <x-msg.error/>
                <x-msg.success/>
                <!-- search section start-->
                <div class="dashboard__inner__item dashboard_border padding-20 radius-10 bg-white">
                    <div class="dashboard__wallet">
                        <form action="{{ route('seller.pending.orders') }}" method="GET">
                            <div class="dashboard__headerGlobal__flex">
                                <div class="dashboard__headerGlobal__content">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                        <h4 class="dashboard_table__title">{{ __('Search Order Pending Module') }}</h4> <i class="las la-angle-down search_by_all"></i>
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
                                @if(request()->get('order_id'))  show
                                @elseif(request()->get('order_date')) show
                                @endif
                             " aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="single-settings">
                                                    <div class="single-dashboard-input">
                                                        <div class="row g-4 mt-3">
                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="order_id" class="info-title"> {{__('Order ID')}} </label>
                                                                    <input class="form--control" name="order_id" value="{{ request()->get('order_id') }}" type="text" placeholder="{{ __('Order ID') }}">
                                                                </div>
                                                            </div>

                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="order_date" class="info-title"> {{__('Created Date Range')}} </label>
                                                                    <input class="form--control flatpickr_input" value="{{ request()->get('order_date') }}" name="order_date" type="text" value="{{ request()->get('order_date') }}" placeholder="{{ __('Created Date Range') }}">
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
                <div class="dashboard__headerContents">
                    <div class="dashboard__headerContents__flex">
                        <div class="dashboard__headerContents__left">
                            <h4 class="dashboard__headerContents__title"> {{ __('Order Request') }}({{ $pending_orders->total() }}) </h4>
                        </div>
                    </div>
                </div>
                @if($pending_orders->count() > 0)
                    <div class="row g-4">
                        @foreach($pending_orders as $order)
                            <div class="col-xxl-6 col-lg-12 col-md-12">
                                <div class="dashboard__inner__item dashboard_border padding-20 radius-10 bg-white">
                                    <div class="dashboard_jobPost">
                                        <div class="dashboard_jobPost__flex">
                                            <div class="dashboard_jobPost__author">
                                                <div class="dashboard_jobPost__author__thumb mt-4">
                                                    <a href="{{ route('seller.order.details', $order->id) }}">
                                                        {!! render_image_markup_by_attachment_id(optional($order->service)->image, '', 'thumb' ) !!}
                                                    </a>
                                                </div>
                                                <div class="dashboard_jobPost__author__contents">
                                                    <a target="_blank" href="{{ route('seller.order.details', $order->id) }}">
                                                        <h4 class="dashboard_jobPost__author__title">
                                                            {{ optional($order->service)->title }}
                                                        </h4>
                                                        <strong class="label_title mt-2"> {{ __('Order').' #'.$order->id }} </strong>
                                                    </a>

                                                    <div class="dashboard_jobPost__views mt-3">
                                                        <div class="dashboard_jobPost__views__item">
                                                            @if($order->status==0)
                                                                <div class="btn-wrapper pending">
                                                                    <a href="javascript:void(0)" class="dashboard_table__title__btn btn-bg-1 radius-5">{{ __('New Request') }}</a>
                                                                </div>
                                                            @endif
                                                            @if($order->status==1)
                                                                <div class="btn-wrapper completed">
                                                                    <a href="javascript:void(0)" class="dashboard_table__title__btn btn-bg-1 radius-5">{{ __('Active Orders') }}</a>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <div class="dashboard_jobPost__views__item">
                                                            <strong class="replaceText text-success">  {{ float_amount_with_currency_symbol($order->total) }} </strong>
                                                        </div>

                                                        <div class="dashboard_jobPost__views__item">
                                                            <span class="dashboard_jobPost__views__para">{{ __('Date & Schedule:') }}
                                                                <strong class="replaceText text-success">
                                                                @if($order->date === 'No Date Created' && $order->schedule === 'No Schedule Created')
                                                                        <span>{{ __('No Date & Schedule Created') }}</span>
                                                                    @else
                                                                        @if($order->date === 'No Date Created')
                                                                            <span>{{ __('No Date Created') }}</span>
                                                                        @else
                                                                            {{ Carbon\Carbon::parse($order->date)->format('d/m/y') }}
                                                                        @endif

                                                                        @if($order->schedule === 'No Schedule Created')
                                                                            <span>{{ __('No Schedule Created') }}</span>
                                                                        @else
                                                                            <span class="orders">{{ __($order->schedule) }}</span>
                                                                        @endif
                                                                    @endif
                                                            </strong>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="dashboard_jobPost__views mt-3">

                                                        @if($order->payment_gateway === 'cash_on_delivery' && $order->payment_status === 'pending')
                                                            <div class="dashboard_jobPost__views__item">
                                                                <div class="btn-wrapper">
                                                                    <a href="javascript:void(0)"
                                                                       class="cmn-btn btn-bg-3 edit_payment_status_modal"
                                                                       data-bs-toggle="modal"
                                                                       data-bs-target="#editPaymentStatusModal"
                                                                       data-id="{{ $order->id }}">{{ __('Payment Status') }}
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <div class="dashboard_jobPost__views__item">
                                                            <div class="dashboard_table__main__actions">
                                                                <a href="{{ route('seller.order.details', $order->id) }}" title="{{ __('View Details') }}" class="icon"><i class="fa-regular fa-eye"></i></a>
                                                            </div>
                                                            <div class="dashboard_table__main__actions">
                                                                <a href="{{ route('seller.order.invoice.details',$order->id) }}" target="_blank" title="{{ __('Print Pdf') }}" class="icon"><i class="las la-print"></i></a>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div class="blog-pagination margin-top-55">
                            <div class="custom-pagination mt-4 mt-lg-5">
                                {!! $pending_orders->links() !!}
                            </div>
                        </div>
                    </div>
                @else
                    <div class="chat_wrapper__details__inner__chat__contents">
                        <h2 class="chat_wrapper__details__inner__chat__contents__para">{{ __('No Pending Order Found') }}</h2>
                    </div>
                @endif
            </div>
        </div>



        <div class="modal fade" id="editPaymentStatusModal" tabindex="-1" role="dialog" aria-labelledby="editModal"
             aria-hidden="true">
            <form action="{{ route('seller.order.payment.status') }}" method="post">
                <input type="hidden"  name="order_id">
                @csrf
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" id="editModal">{{ __('Change Payment Status') }}</h4>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="up_day_id" class="label_title">{{ __('Select Status') }}</label>
                                <select name="status" id="status" class="form-control nice-select">
                                    <option value="">{{ __('Select Status') }}</option>
                                    <option value="complete">{{ __('Completed') }}</option>
                                </select>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @endsection
        @section('scripts')
            <script src="{{ asset('assets/backend/js/sweetalert2.js') }}"></script>
            <script>
                (function($) {
                    "use strict";
                    $(document).ready(function() {

                        // date range
                        $('.flatpickr_input').flatpickr({
                            altFormat: "invisible",
                            altInput: false,
                            mode: "range",
                        });

                        $(document).on('click', '.edit_status_modal', function(e) {
                            e.preventDefault();
                            let order_id = $(this).data('id');
                            $('#order_id').val(order_id);
                            $('.nice-select').niceSelect('update');
                        });
                        $(document).on('click', '.edit_payment_status_modal', function(e) {
                            e.preventDefault();
                            let modalContainer = $('#editPaymentStatusModal');
                            let order_id = $(this).data('id');
                            modalContainer.find('input[name="order_id"]').val(order_id);
                            $('.nice-select').niceSelect('update');
                        });
                        $(document).on('click', '.swal_delete_button', function(e) {
                            e.preventDefault();
                            Swal.fire({
                                title: '{{ __('Are you sure?') }}',
                                text: '{{ __('You would not be able to revert this item!') }}',
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

                    });
                })(jQuery);
            </script>
@endsection