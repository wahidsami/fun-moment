@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('Service Coupons')}}
@endsection
@section('style')
    <link rel="stylesheet" href="{{asset('assets/common/css/flatpickr.min.css')}}">
    <style>
        .close{ border: none;  }
        .dashboard-switch-single{
            font-size: 20px;
        }
        .swal_delete_button{
            color: #da0000 !important;
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
                        <form action="{{ route('seller.service.coupon') }}" method="GET">
                            <div class="dashboard__headerGlobal__flex">
                                <div class="dashboard__headerGlobal__content">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                        <h4 class="dashboard_table__title">{{ __('Search Coupons Module') }}</h4> <i class="las la-angle-down search_by_all"></i>
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
                                 @if(request()->get('coupon_code'))  show
                                 @elseif(request()->get('status')) show
                                 @elseif(request()->get('discount_type')) show
                                 @elseif(request()->get('coupon_date')) show
                                 @endif
                                "aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="single-settings">
                                                    <div class="single-dashboard-input">
                                                        <div class="row g-4 mt-3">
                                                            <div class="col-lg-3 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="coupon_code" class="info-title"> {{__('Coupon Code')}} </label>
                                                                    <input class="form--control" name="coupon_code" value="{{ request()->get('coupon_code') }}" type="text" placeholder="{{ __('Coupon Code') }}">
                                                                </div>
                                                            </div>

                                                            <div class="col-lg-3 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="status" class="info-title"> {{__('Discount Type')}} </label>
                                                                    <select name="discount_type">
                                                                        <option value="">{{__('Select Type')}}</option>
                                                                        <option value="percentage" @if(request()->get('discount_type') == 'percentage') selected @endif>{{ __('Percentage') }}</option>
                                                                        <option value="amount" @if(request()->get('discount_type') == 'amount') selected @endif>{{  __('Amount') }}</option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <div class="col-lg-3 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="status" class="info-title"> {{__('Status')}} </label>
                                                                    <select name="status">
                                                                        <option value="">{{__('Select Order Status')}}</option>
                                                                        <option value="1" @if(request()->get('status') == 1) selected @endif>{{ __('Active') }}</option>
                                                                        <option value="pending" @if(request()->get('status') == 'pending') selected @endif>{{  __('Inactive') }}</option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <div class="col-lg-3 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="coupon_date" class="info-title"> {{__('Created Date Range')}} </label>
                                                                    <input class="form--control flatpickr_input"  name="coupon_date" type="text" value="{{ request()->get('coupon_date') }}" placeholder="{{ __('Created Date Range') }}">
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
                <!-- todolist table section start-->
                <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                    <h2 class="dashboard_table__title"> {{__('All Coupons')}} </h2>
                    <div class="notice-board">
                        <p class="text-danger">{{ __('Coupon will applicable only for your services and coupon amount will be reduce from your earnings.') }}</p>
                    </div>
                    <div class="dashboard_table__title__flex">
                        <h4 class="dashboard_table__title">  </h4>
                        <div class="btn-wrapper mt-3" data-bs-toggle="modal" data-bs-target="#openTicket">
                            <a href="javascript:void(0)"
                               class="dashboard_table__title__btn btn-bg-1 radius-5"
                               data-bs-toggle="modal"
                               data-bs-target="#addCouponModal"><i class="fa-solid fa-plus"></i> {{__('Add Coupon' )}}</a>
                        </div>
                    </div>
                    <div class="mt-5"> <x-msg.error/> </div>
                    @if($coupons->count() >= 1)
                        <div class="dashboard_table__main custom--table mt-4">
                            <table>
                                <thead>
                                <tr>
                                    <th>{{ __('No') }}</th>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Discount') }}</th>
                                    <th>{{ __('Discount Type') }}</th>
                                    <th>{{ __('Expire Date') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($coupons as $key=>$data)
                                    <tr>
                                        <td>{{ $key+1 }}</td>
                                        <td>{{ $data->code }}</td>
                                        <td>{{ $data->discount }}</td>
                                        <td>{{ __($data->discount_type) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($data->expire_date)->format('d/m/Y') }}</td>
                                        <td>
                                            @if($data->status==1)
                                                <span class="text-success">{{ __('Active') }}</span>
                                            @else
                                                <span class="text-danger">{{ __('Inactive') }}</span>
                                            @endif
                                            <x-seller-coupon-status :url="route('seller.service.coupon.status',$data->id)"/>
                                        </td>
                                        <td>
                                            <div class="dashboard-switch-single">
                                                <a href="#0" class="edit_coupon_modal"
                                                   data-bs-toggle="modal"
                                                   data-bs-target="#editCouponModal"
                                                   data-id="{{ $data->id }}"
                                                   data-code="{{ $data->code }}"
                                                   data-discount="{{ $data->discount }}"
                                                   data-discount_type="{{ __($data->discount_type) }}"
                                                   data-expire_date="{{ $data->expire_date }}"
                                                >
                                                    <span style="font-size:16px;" class="dash-icon color-1"> <i class="las la-edit"></i> </span>
                                                </a>
                                                <x-seller-delete-popup :url="route('seller.service.coupon.delete',$data->id)"/>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="blog-pagination margin-top-55">
                            <div class="custom-pagination mt-lg-5">
                                {!! $coupons->links() !!}
                            </div>
                        </div>
                    @else
                        <div class="chat_wrapper__details__inner__chat__contents">
                            <h2 class="chat_wrapper__details__inner__chat__contents__para">{{ __('No Coupon Found') }}</h2>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addCouponModal" tabindex="-1" role="dialog" aria-labelledby="couponModal" aria-hidden="true">
        <form action="{{ route('seller.service.coupon.add') }}" method="post">
            @csrf
            <div class="modal-dialog" role="document">
                <div class="modal-content">

                    <div class="modal-header d-block">
                        <h4 class="modal-title" id="couponModal">{{ __('Add New Coupon') }}</h4>
                        <small class="text-info">{{ __('Be careful while create a coupon. if the service price less than the admin charge after apply coupon than admin charge will cut from your main balance.') }}</small>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="code" class="label_title">{{ __('Coupon Code') }} <span class="text-danger">*</span> </label>
                            <input type="text" name="code" id="code" class="form-control" placeholder="{{ __('Coupon Code') }}">
                        </div>
                        <div class="form-group mt-3">
                            <label for="discount" class="label_title">{{ __('Coupon Discount') }} <span class="text-danger">*</span> </label>
                            <input type="number" name="discount" id="discount" class="form-control" placeholder="{{ __('Discount') }}">
                        </div>
                        <div class="form-group mt-3">
                            <label for="discount_type" class="label_title">{{ __('Coupon Type') }} <span class="text-danger">*</span> </label>
                            <select name="discount_type" id="discount_type" class="form-control mb-3">
                                <option value="">{{ __('Select Type') }}</option>
                                <option value="percentage">{{ __('Percentage') }}</option>
                                <option value="amount">{{ __('Amount') }}</option>
                            </select>
                        </div>
                        <div class="form-group mt-2">
                            <label for="expire_date" class="label_title">{{ __('Expire Date') }} <span class="text-danger">*</span> </label>
                            <input type="date" name="expire_date" id="expire_date" class="form-control" placeholder="{{ __('Expire Date') }}">
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
    <!-- Edit Modal -->
    <div class="modal fade" id="editCouponModal" tabindex="-1" role="dialog" aria-labelledby="editCouponModal" aria-hidden="true">
        <form action="{{ route('seller.service.coupon.update') }}" method="post">
            <input type="hidden" id="up_id" name="up_id" >
            @csrf
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="editCouponModal">{{ __('Edit Coupon') }}</h4>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">

                        <div class="form-group mt-3">
                            <label for="up_code" class="label_title">{{ __('Coupon Code') }} <span class="text-danger">*</span> </label>
                            <input type="text" name="up_code" id="up_code" class="form-control" placeholder="{{ __('Coupon Code') }}">
                        </div>
                        <div class="form-group mt-3">
                            <label for="up_discount" class="label_title">{{ __('Coupon Discount') }} <span class="text-danger">*</span> </label>
                            <input type="number" name="up_discount" id="up_discount" class="form-control" placeholder="{{ __('Discount') }}">
                        </div>
                        <div class="form-group mt-3">
                            <label for="up_discount_type" class="label_title">{{ __('Coupon Type') }} <span class="text-danger">*</span> </label>
                            <select name="up_discount_type" id="up_discount_type" class="form-control nice-select mb-3">
                                <option value="">{{ __('Select Type') }}</option>
                                <option value="percentage">{{ __('Percentage') }}</option>
                                <option value="amount">{{ __('Amount') }}</option>
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label for="up_expire_date" class="label_title">{{ __('Expire Date') }} <span class="text-danger">*</span> </label>
                            <input type="date" name="up_expire_date" id="up_expire_date" class="form-control" placeholder="{{ __('Expire Date') }}">
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
    <script src="{{asset('assets/backend/js/sweetalert2.js')}}"></script>
    <script src="{{asset('assets/backend/js/dropzone.js')}}"></script>
    <script src="{{asset('assets/common/js/flatpickr.js')}}"></script>
    <script>
        (function($){
            "use strict";
            $(document).ready(function(){

                // search select2
                $('#discount_type').select2({
                    dropdownParent: $('#addCouponModal')
                });

                $('#up_discount_type').select2({
                    dropdownParent: $('#editCouponModal')
                });

                // date range
                $('.flatpickr_input').flatpickr({
                    altFormat: "invisible",
                    altInput: false,
                    mode: "range",
                });

                $(document).on('click','.edit_coupon_modal',function(e){
                    e.preventDefault();
                    let coupon_id = $(this).data('id');
                    let code = $(this).data('code');
                    let discount = $(this).data('discount');
                    let discount_type = $(this).data('discount_type');
                    let expire_date = $(this).data('expire_date');

                    $('#up_id').val(coupon_id);
                    $('#up_code').val(code);
                    $('#up_discount').val(discount);
                    $('#up_discount_type').val(discount_type);
                    $('#up_expire_date').val(expire_date);
                    $('.nice-select').niceSelect('update');
                });

                $(document).on('click','.swal_status_button',function(e){
                    e.preventDefault();
                    Swal.fire({
                        title: '{{__("Are you sure to change status?")}}',
                        text: '{{__("You will change it anytime!")}}',
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

                $("#expire_date").flatpickr({
                    dateFormat: "Y-m-d",
                });
                $("#up_expire_date").flatpickr({
                    dateFormat: "Y-m-d",
                });

            });

        })(jQuery);
    </script>
@endsection