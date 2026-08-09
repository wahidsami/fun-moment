@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{ __('Payout Request') }}
@endsection
@section('style')
    <link rel="stylesheet" href="{{asset('assets/backend/css/bootstrap-tagsinput.css')}}">
    <link rel="stylesheet" href="{{asset('assets/common/css/flatpickr.min.css')}}">
    <style>
        .close{ border: none;  }
        .dashboard-switch-single{
            font-size: 20px;
        }
        .swal_delete_button{
            color: #da0000 !important;
        }
        /*.dashboard_promo__row{*/
        /*    flex-wrap: unset !important;*/
        /*}*/
        .row_col_4 .dashboard_promo__col {
            width: calc(100% / 4 - 18px);
        }
        @media only screen and (max-width: 1599px) {
            .row_col_4 .dashboard_promo__col {
                width: calc(100% / 3 - 16px);
            }
        }
        @media only screen and (max-width: 1199px) {
            .row_col_4 .dashboard_promo__col {
                width: calc(100% / 2 - 12px);
            }
        }
        @media only screen and (max-width: 575px) {
            .row_col_4 .dashboard_promo__col {
                width: calc(100% / 1 - 0px);
            }
        }
        #seller_note{
            height: 140px;
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
                <!-- search section start-->
                <div class="dashboard__inner__item dashboard_border padding-20 radius-10 bg-white">
                    <div class="dashboard__wallet">
                        <form action="{{ route('seller.payout') }}" method="GET">
                            <div class="dashboard__headerGlobal__flex">
                                <div class="dashboard__headerGlobal__content">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                        <h4 class="dashboard_table__title">{{ __('Search Payout History Module') }}</h4> <i class="las la-angle-down search_by_all"></i>
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
                                 @if(request()->get('payout_history_id'))  show @elseif(request()->get('status')) show @elseif(request()->get('payout_request_date')) show @endif
                                " aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="single-settings">
                                                    <div class="single-dashboard-input">
                                                        <div class="row g-4 mt-3">
                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="payout_history_id" class="info-title"> {{__('Payout History ID')}} </label>
                                                                    <input class="form--control" name="payout_history_id" value="{{ request()->get('payout_history_id') }}" type="text" placeholder="{{ __('Payout History ID') }}">
                                                                </div>
                                                            </div>

                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="status" class="info-title"> {{__('Status')}} </label>
                                                                    <select name="status">
                                                                        <option value="">{{__('Select Order Status')}}</option>
                                                                        <option value="1" @if(request()->get('status') == 1) selected @endif>{{ __('Completed') }}</option>
                                                                        <option value="pending" @if(request()->get('status') == 'pending') selected @endif>{{  __('Pending') }}</option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="payout_request_date" class="info-title"> {{__('Created Date Range')}} </label>
                                                                    <input class="form--control flatpickr_input"  name="payout_request_date" type="text" value="{{ request()->get('payout_request_date') }}" placeholder="{{ __('Created Date Range') }}">
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
                <div class="col-xxl-12 col-lg-12">
                    <div class="dashboard_promo__row row_col_4">
                        <div class="dashboard_promo__col dashboard_promo__child">
                            <div class="dashboard_promo bg-white">
                                <div class="dashboard_promo__flex">
                                    <div class="dashboard_promo__contents">
                                        <span class="dashboard_promo__subtitle">{{ __('Order Pending') }}</span>
                                        <h4 class="dashboard_promo__title mt-2">{{ $pending_order }}</h4>
                                    </div>
                                    <div class="dashboard_promo__icon">
                                        <i class="las la-tasks"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="dashboard_promo__col dashboard_promo__child">
                            <div class="dashboard_promo bg-white">
                                <div class="dashboard_promo__flex">
                                    <div class="dashboard_promo__contents">
                                        <span class="dashboard_promo__subtitle">{{ __('Order Completed') }}</span>
                                        <h4 class="dashboard_promo__title mt-2">{{ $complete_order }}</h4>
                                    </div>
                                    <div class="dashboard_promo__icon">
                                        <i class="las la-handshake"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="dashboard_promo__col dashboard_promo__child">
                            <div class="dashboard_promo bg-white">
                                <div class="dashboard_promo__flex">
                                    <div class="dashboard_promo__contents">
                                        <span class="dashboard_promo__subtitle">{{ __('Total Withdraw') }}</span>
                                        <h4 class="dashboard_promo__title mt-2">{{ float_amount_with_currency_symbol($total_earnings) }} </h4>
                                    </div>
                                    <div class="dashboard_promo__icon">
                                        <i class="las la-dollar-sign"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard_promo__col dashboard_promo__child">
                            <div class="dashboard_promo bg-white">
                                <div class="dashboard_promo__flex">
                                    <a href="{{ route('buyer.orders') }}">
                                        <div class="dashboard_promo__contents">
                                            <span class="dashboard_promo__subtitle">{{ __('Remaining Balance') }}</span>
                                            <h4 class="dashboard_promo__title mt-2">{{ float_amount_with_currency_symbol($remaning_balance-$total_earnings) }}</h4>
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

                <!-- payout section start-->
                <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white mt-5">
                    <div class="dashboard_table__title__flex">
                        <div class="dashboard__headerContents__left">
                            <h4 class="dashboard_table__title"> {{__('Payout History')}} </h4>
                            <p class="text-danger">{{ __('You can create a request for withdraw your earnings.') }}</p>
                        </div>
                        <div class="btn-wrapper" data-bs-toggle="modal" data-bs-target="#openTicket">
                            <a href="javascript:void(0)"
                               class="dashboard_table__title__btn btn-bg-1 radius-5"
                               data-bs-toggle="modal"
                               data-bs-target="#payoutRequestModal"><i class="fa-solid fa-plus"></i> {{__('Request A Payment' )}}</a>
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div class="dashboard-settings">
                            <div class="mt-3"> <x-msg.error/></div>
                        </div>
                    </div>
                    @if($all_payout_request->count() >= 1)
                        <div class="dashboard_table__main custom--table mt-4">
                            <table>
                                <thead>
                                <tr>
                                    <th> {{ __('ID') }}</th>
                                    <th> {{ __('Payment Gateway') }} </th>
                                    <th> {{ __('Request Date') }} </th>
                                    <th> {{ __('Request Amount') }} </th>
                                    <th> {{ __('Request Status') }} </th>
                                    <th> {{ __('Downloads') }} </th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($all_payout_request as $pay_request)
                                    <tr>
                                        <td>{{ $pay_request->id }} </td>
                                        <td>{{ __($pay_request->payment_gateway) }}</td>
                                        <td>{{ $pay_request->created_at->diffForHumans() }} </td>
                                        <td> {{ float_amount_with_currency_symbol($pay_request->amount) }} </td>
                                        <td>
                                        <div class="dashboard_table__main__priority">
                                            @if ($pay_request->status == 0)  <span class="priorityBtn pending">{{ __('Pending') }}</span>@endif
                                            @if ($pay_request->status == 1)  <span class="priorityBtn completed">{{ __('Completed') }}</span>@endif
                                        </div>
                                        </td>
                                        <td>
                                            <div class="dashboard_table__main__actions">
                                                <a href="{{ route('seller.payout.request.details', $pay_request->id) }}" class="icon">
                                                    <i class="fa-regular fa-eye"></i>
                                                </a>
                                                <a href="{{ route('seller.payout.invoice.details',$pay_request->id) }}">
                                                    <i class="las la-file-pdf"></i> {{ __('Download PDF') }}
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
                                {!! $all_payout_request->links() !!}
                            </div>
                        </div>
                    @else
                        <div class="chat_wrapper__details__inner__chat__contents">
                            <h2 class="chat_wrapper__details__inner__chat__contents__para">{{ __('No Payout History Found') }}</h2>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    <!--Status Modal -->
    <div class="modal fade" id="payoutRequestModal" tabindex="-1" role="dialog" aria-labelledby="editModal"
         aria-hidden="true">
        <form action="{{ route('seller.create.payout.request') }}" method="post">
            @csrf
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="editModal">{{ __('Payout Request') }}</h4>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">

                        <div class="form-group">
                            <label for="amount" class="label_title">{{ __('Amount') }} <span class="text-danger">*</span> </label>
                            <input type="number" class="form-control" name="amount" id="amount" placeholder="{{ __('amount') }}">
                        </div>
                        <div class="form-group mt-2">
                            <label for="payment_gateway" class="label_title">{{ __('Payment Gateway') }}</label>
                            <select name="payment_gateway" id="payment_gateway" class="form-control nice-select">
                                <option value="">{{ __('Select Payment gateway') }}</option>
                                @php
                                    $all_gateways = ['paypal','manual_payment','mollie','paytm','stripe','razorpay','flutterwave','paystack','marcadopago','instamojo','cashfree','payfast','midtrans'];
                                @endphp
                                @foreach($all_gateways as $gateway)
                                    @if(!empty(get_static_option($gateway.'_gateway')))
                                        <option value="{{$gateway}}" @if(get_static_option('site_default_payment_gateway') == $gateway) selected @endif>{{ucwords(str_replace('_',' ',$gateway))}}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="payout-request-note d-block pt-4 label_title" for="amount">{{ __('Note (your payment account details)') }}</label>
                            @php $amount_settings = App\AmountSettings::first(); @endphp
                            <small class="text-danger margin-bottom-10 d-block">{{sprintf(__('You can make a request only if your remaining balance in a range set by the site admin. Like admin set minimum request amount %1$s and maximum request amount %2$s. than you can request a payment between %1$s to %2$s.'),$amount_settings->min_amount,$amount_settings->max_amount)}}</small>
                            <textarea class="form-control mt-3" name="seller_note" id="seller_note" cols="30" rows="7" placeholder="{{ __('note') }}"></textarea>
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
    <script src="{{asset('assets/common/js/flatpickr.js')}}"></script>
    <script>
        (function($) {
            "use strict";
            $(document).ready(function() {

                // search select2
                $('#payment_gateway').select2({
                    dropdownParent: $('#payoutRequestModal')
                });

                // date range
                $('.flatpickr_input').flatpickr({
                    altFormat: "invisible",
                    altInput: false,
                    mode: "range",
                });

                $(document).on('click', '.edit_status_modal', function(e) {
                    e.preventDefault();
                    let order_id = $(this).data('id');
                    let status = $(this).data('status');

                    $('#order_id').val(order_id);
                    $('#status').val(status);
                    $('.nice-select').niceSelect('update');
                });

            });
        })(jQuery);
    </script>
@endsection
