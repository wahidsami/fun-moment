@extends('backend.admin-master')
@section('site-title')
    {{__('Seller/Buyer Panel Settings')}}
@endsection

@section('style')
    <x-datatable.css/>
@endsection

@section('content')
    <div class="col-lg-12 col-ml-12 padding-bottom-30">
        <div class="row">
            <div class="col-lg-12">
                <div class="margin-top-40"></div>
                <x-msg.success/>
                <x-msg.error/>
            </div>
            <div class="col-lg-6 mt-5">
                <div class="card">
                    <div class="card-body">
                        <div class="header-wrap d-flex justify-content-between">
                            <div class="left-content">
                                <h4 class="header-title">{{__('Seller Dashboard Weekly Work Summary Start Day Add')}} </h4>
                            </div>
                        </div>

                        <form action="{{ route('admin.dashboard.variant') }}" method="post">
                            @csrf
                            <div class="form-group">
                                <label for="start_week_from">{{__('Select Start Day')}}</label>
                                @php  $days_array = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'); @endphp
                                <select name="start_week_from" class="form-control">
                                    @foreach($days_array as $key => $day_name)

                                        <option value="{{ $key }}" @if(get_static_option('start_week_from') == $key) selected @endif>{{ $day_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button id="update" type="submit" class="btn btn-primary mt-4 pr-4 pl-4">{{__('Update')}}</button>
                        </form>
                    </div>
                </div>
            </div>



        </div>
    </div>
@endsection

@section('script')
    <script>
        (function($){
            "use strict";

            $(document).ready(function () {
                <x-btn.update/>

                let imgSelect = $('.img-select');
                let id = $('#dashboard_variant_buyer').val();
                imgSelect.removeClass('selected');
                $('img[data-dashboard_id="'+id+'"]').parent().parent().addClass('selected');

                // buyer dashboard
                $(document).on('click','.img-select img',function (e) {
                    e.preventDefault();
                    imgSelect.removeClass('selected');
                    $(this).parent().parent().addClass('selected').siblings();
                    $('#dashboard_variant_buyer').val($(this).data('dashboard_id'));
                });

            });

        })(jQuery);
    </script>
@endsection