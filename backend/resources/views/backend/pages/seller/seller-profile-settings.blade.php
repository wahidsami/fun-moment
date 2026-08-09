@extends('backend.admin-master')
@section('site-title')
    {{__('Seller Profile Settings')}}
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
                                <h4 class="header-title">{{__('Seller Profile Settings')}} </h4>
                            </div>
                        </div>
                        <div class="table-wrap table-responsive">
                            <form action="{{ route('admin.seller.profile.settings.update') }}" method="post">
                                @csrf
                                <div class="form-group">
                                    <label for="seller_tax_number_show_hide_profile"><strong>{{__('Show/hide Tax Number on Seller Profile')}}</strong></label>
                                    <label class="switch yes">
                                        <input type="checkbox" name="seller_tax_number_show_hide_profile"  @if(!empty(get_static_option('seller_tax_number_show_hide_profile'))) checked @endif>
                                        <span class="slider-enable-disable"></span>
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label for="seller_service_schedule_show_hide_in_profile"><strong>{{__('Show/hide Available Schedule on Seller Profile')}}</strong></label>
                                    <label class="switch yes">
                                        <input type="checkbox" name="seller_service_schedule_show_hide_in_profile"  @if(!empty(get_static_option('seller_service_schedule_show_hide_in_profile'))) checked @endif>
                                        <span class="slider-enable-disable"></span>
                                    </label>
                                </div>

                                <div class="form-group mt-4">
                                    <input type="submit" value="Update" class="btn btn-primary">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection