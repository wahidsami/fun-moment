@extends('backend.admin-master')

@section('site-title')
    {{__('All Seller Service Coupons')}}
@endsection
@section('style')
    <x-datatable.css/>
    <link rel="stylesheet" href="{{asset('assets/common/css/flatpickr.min.css')}}">
@endsection
@section('content')
    <div class="col-lg-12 col-ml-12 padding-bottom-30">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="header-wrap d-flex justify-content-between">
                            <div class="left-content">
                                <h4 class="header-title">{{__('All Seller Service Coupons')}}  </h4>
                            </div>
                        </div>
                        <div class="table-wrap table-responsive">
                            <table class="table table-default">
                                <thead>
                                <th>{{__('ID')}}</th>
                                <th>{{__('Code')}}</th>
                                <th>{{__('Discount')}}</th>
                                <th>{{__('Discount Type')}}</th>
                                <th>{{__('Expire Date')}}</th>
                                <th>{{__('Status')}}</th>
                                <th>{{__('Action')}}</th>
                                </thead>
                                <tbody>
                                @foreach($seller_service_coupons as $data)
                                    <tr>
                                        <td>{{$data->id}}</td>
                                        <td>{{$data->code}}</td>
                                        <td>{{$data->discount}}</td>
                                        <td>{{$data->discount_type}}</td>
                                        <td>{{$data->expire_date}}</td>
                                        <td>
                                            @if($data->status==0)
                                                <span class="text-warning">{{ __('Inactive') }}</span>
                                            @else
                                                <span class="text-info">{{ __('Active') }}</span>
                                            @endif
                                            <x-status-change :url="route('admin.service.seller.coupon.status',$data->id)"/>
                                        </td>
                                        <td>
                                            @can('slider-delete')
                                                <x-delete-popover :url="route('admin.service.seller.coupon.delete',$data->id)"/>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
@section('script')
    <x-datatable.js/>
    <script src="{{asset('assets/common/js/flatpickr.js')}}"></script>
    <script>
        (function ($) {
            "use strict";
            $(document).ready(function () {
                <x-bulk-action-js :url="route('admin.slider.bulk.action')"/>
                $(document).on('click','.swal_status_change',function(e){
                    e.preventDefault();
                    Swal.fire({
                        title: '{{__("Are you sure to change status?")}}',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, change it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $(this).next().find('.swal_form_submit_btn').trigger('click');
                        }
                    });
                });
                $("#expire_date").flatpickr({
                    dateFormat: "Y-m-d",
                });

                $(document).on('click','.admin_coupon_edit_btn',function(){
                    let id = $(this).data('id');
                    let code = $(this).data('code');
                    let discount = $(this).data('discount');
                    let type = $(this).data('discount_type');
                    let expire_date = $(this).data('expire_date');
                    let form = $('#admin_coupon_edit_modal');
                    form.find('#up_id').val(id);
                    form.find('#up_code').val(code);
                    form.find('#up_discount').val(discount);
                    form.find('#up_discount_type').val(type);
                    form.find('#up_expire_date').val(expire_date);
                });
                $("#up_expire_date").flatpickr({
                    dateFormat: "Y-m-d",
                });
            });
        })(jQuery)
    </script>
@endsection

