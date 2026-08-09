@extends('backend.admin-master')
@section('site-title')
    {{__('Order Settings')}}
@endsection
@section('content')
    <div class="col-lg-12 col-ml-12 padding-bottom-30">
        <x-msg.success/>
        <x-msg.error/>
        <div class="row">
            <div class="col-6 mt-5">
                <div class="card">
                    <div class="card-body">
                        <h4 class="header-title mb-4">{{__("Order Success Settings")}}</h4>
                        <small class="text-danger mb-5">{{ __('You can change order success page text from here.') }}</small>
                        <form action="{{route('admin.order.success.settings.update')}}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <label for="success_title">{{__('Order Success Title')}}</label>
                                <input type="text" name="success_title"  class="form-control" value="{{get_static_option('success_title')}}" id="success_title">
                            </div>
                            <div class="form-group">
                                <label for="success_subtitle">{{__('Order Success Subtitle')}}</label>
                                <input type="text" name="success_subtitle"  class="form-control" value="{{get_static_option('success_subtitle')}}" id="success_subtitle">
                            </div>
                            <div class="form-group">
                                <label for="success_details_title">{{__('Order Success Details Title')}}</label>
                                <input type="text" name="success_details_title"  class="form-control" value="{{get_static_option('success_details_title')}}" id="success_details_title">
                            </div>
                            <div class="form-group">
                                <label for="button_title">{{__('Order Success Button Title')}}</label>
                                <input type="text" name="button_title"  class="form-control" value="{{get_static_option('button_title')}}" id="button_title">
                            </div>
                            <div class="form-group">
                                <label for="button_url">{{__('Order Success Button Url')}}</label>
                                <input type="text" name="button_url"  class="form-control" value="{{get_static_option('button_url')}}" id="button_url">
                            </div>

                            <div class="form-group">
                                <label for="order_date_time_change_permission"><strong>{{__('Order Booking Date & Time Change Permission')}}</strong></label>
                                <br>
                                <span>{{ __('Allow sellers to change the order date and time') }}</span>
                                <label class="switch yes">
                                    <input type="checkbox" name="order_date_time_change_permission"  @if(!empty(get_static_option('order_date_time_change_permission'))) checked @endif id="order_date_time_change_permission">
                                    <span class="slider-enable-disable"></span>
                                </label>
                            </div>

                            <button type="submit" id="update" class="btn btn-primary mt-4 pr-4 pl-4">{{__('Update Changes')}}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-6 mt-5">
                <div class="card">
                    <div class="card-body">
                        <h4 class="header-title mb-4">{{__("Order Invoice Settings")}}</h4>
                        <small class="text-danger mb-5">{{ __('You can change order invoice page text from here.') }}</small>
                        <form action="{{route('admin.order.invoice.settings.update')}}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="bill_to_title">{{__('Bill To Title')}}</label>
                                <input type="text" name="bill_to_title"  class="form-control" value="{{get_static_option('bill_to_title')}}" id="bill_to_title">
                            </div>
                            <div class="form-group">
                                <label for="ship_to_title">{{__('Ship To Title')}}</label>
                                <input type="text" name="ship_to_title"  class="form-control" value="{{get_static_option('ship_to_title')}}" id="ship_to_title">
                            </div>
                            <div class="form-group">
                                <label for="invoice_title">{{__('INVOICE Title')}}</label>
                                <input type="text" name="invoice_title"  class="form-control" value="{{get_static_option('invoice_title')}}" id="invoice_title">
                            </div>
                            <div class="form-group">
                                <label for="invoice_no_title">{{__('Invoice No Title')}}</label>
                                <input type="text" name="invoice_no_title"  class="form-control" value="{{get_static_option('invoice_no_title')}}" id="invoice_no_title">
                            </div>

                            <button type="submit" id="update" class="btn btn-primary mt-4 pr-4 pl-4">{{__('Update Changes')}}</button>
                        </form>
                    </div>
                </div>
                <br>
                <div class="card">
                    <div class="card-body">
                        <h4 class="header-title mb-4">{{__("User Details Settings")}}</h4>
                        <span class="text-danger mb-5">{{ __('You can set whether buyer info show in seller order page or not.') }}</span>
                        <form action="{{route('admin.order.user.settings.update')}}" method="POST">
                            @csrf
                            <div class="form-group">
                            <label class="switch yes mt-3">
                                <input type="checkbox" name="order_date_time_change_permission"  id="myCheckbox" onchange="checkCheckbox()" 
                                {{ old('myCheckbox', $showResult == 'Checkbox is checked.' ? 'checked' : '') }}>
                                <span class="slider-enable-disable"></span>
                            </label>
                            <input type="hidden" id="resultInput" name="result">

                        </div>   

                            <button type="submit" id="update" class="btn btn-primary mt-3 pr-3 pl-3">{{__('Update Changes')}}</button>
                            
                            
                        </form>
                    </div>
                </div>
            </div>


        </div>
    </div>
@endsection
@section('script')
    <script>
        function checkCheckbox() {
            const checkbox = document.getElementById('myCheckbox');
            const result = document.getElementById('resultInput');
            if (checkbox.checked) {
                
                resultInput.value = "Checkbox is checked.";
            } else {
               
                resultInput.value = "Checkbox is not checked.";
            }
        }
        (function($){
            "use strict";
            $(document).ready(function(){
                <x-icon-picker/>
                <x-btn.update/>
            });
        }(jQuery));
    </script>
@endsection
