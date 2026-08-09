@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('Show Service Attributes')}}
@endsection
@section('style')
   <style>
       .theme-two-color{
           font-size: 20px;
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
                <div class="dashboard__headerContents">
                    <div class="dashboard__headerContents__flex">
                        <div class="dashboard__headerContents__left">
                            <h4 class="dashboard_table__title"> {{ __('Show Service Attributes') }} </h4>
                        </div>
                    </div>
                </div>

                <div class="dashboard__inner__item dashboard_border padding-20 radius-10 bg-white">
                    <div class="show_service_all_attr">
                        <div class="row">
                            <div class="col-lg-4">
                                <h5>{{ __('Service Title:') }} <strong>{{ $service->title }}</strong></h5>
                                <div class="dashboard_recentOrder__item__service__thumb mt-3" style="height: 150px; width: 200px">
                                    @if(!empty(render_image_markup_by_attachment_id($service->image, '')))
                                        {!! render_image_markup_by_attachment_id($service->image, '') !!}
                                    @else
                                        <img src="{{ asset('assets/uploads/no-image.png') }}" class="no_image_style_two" alt="No Image">
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-service-single-item border-1 margin-top-40 mt-4">

                        <h5 class="mb-3">{{ __('Include Service Attributes') }}</h5>
                        <div class="rows dash-single-inner">
                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>{{ __('No') }}</th>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Price') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($include_service as $key=>$inc_service)
                                    <tr>
                                        <td>{{ $key+1 }}</td>
                                        <td>{{ $inc_service->include_service_title }}</td>
                                        <td>{{ float_amount_with_currency_symbol($inc_service->include_service_price) }}</td>
                                        <td>{{ $inc_service->include_service_quantity }}</td>
                                        <td>
                                            <x-seller-delete-popup :url="route('seller.services.includeservice.delete',$inc_service->id)"/>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($additional_service->count() >= 1)
                            <h5 class="mt-3 mb-3">{{ __('Additional Service Attributes') }}</h5>
                            <div class="rows dash-single-inner">
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th>{{ __('No') }}</th>
                                        <th>{{ __('Title') }}</th>
                                        <th>{{ __('Price') }}</th>
                                        <th>{{ __('Quantity') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($additional_service as $key=>$addi_service)
                                        <tr>
                                            <td>{{ $key+1 }}</td>
                                            <td>{{ $addi_service->additional_service_title }}</td>
                                            <td>{{ float_amount_with_currency_symbol($addi_service->additional_service_price) }}</td>
                                            <td>{{ $addi_service->additional_service_quantity }}</td>
                                            <td>
                                                <x-seller-delete-popup :url="route('seller.services.additionalservice.delete',$addi_service->id)"/>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if($service_benifit->count() >= 1)
                            <h5 class="mt-3 mb-3">{{ __('Service Benefit') }}</h5>
                            <div class="rows dash-single-inner">
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th>{{ __('No') }}</th>
                                        <th>{{ __('Benefit') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($service_benifit as $key=>$ser_benifit)
                                        <tr>
                                            <td>{{ $key+1 }}</td>
                                            <td>{{ $ser_benifit->benifits }}</td>
                                            <td>
                                                <x-seller-delete-popup :url="route('seller.services.benifit.delete',$ser_benifit->id)"/>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if($service_faqs->count() >= 1)
                            <h5 class="mt-3 mb-3">{{ __('Service Faqs') }}</h5>
                            <div class="rows dash-single-inner">
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th>{{ __('No') }}</th>
                                        <th>{{ __('Faq Title') }}</th>
                                        <th>{{ __('Faq Description') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($service_faqs as $key=>$service_faq)
                                        <tr>
                                            <td>{{ $key+1 }}</td>
                                            <td>{{ $service_faq->title }}</td>
                                            <td>{{ $service_faq->description }}</td>
                                            <td>
                                                <x-seller-delete-popup :url="route('seller.services.faq.delete',$service_faq->id)"/>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
@endsection
@section('scripts')
    <script src="{{asset('assets/backend/js/sweetalert2.js')}}"></script>
    <script>
        (function($){
            "use strict";
            $(document).ready(function(){

                $(document).on('click','.edit_todo_modal',function(e){
                    e.preventDefault();
                    let todo_id = $(this).data('id');
                    let title = $(this).data('title');
                    let description = $(this).data('description');

                    $('#up_id').val(todo_id);
                    $('#up_title').val(title);
                    $('#up_description').val(description);
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
                        confirmButtonText: 'Yes, delete it!'
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