@extends('backend.admin-master')
@section('site-title')
    {{__('Service Zone Add')}}
@endsection
@section('content')
    <div class="col-lg-12 col-ml-12 padding-bottom-30">
        <div class="row">
            <div class="col-4 mt-5">
                <x-msg.success/>
                <x-msg.error/>
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin.country.coordinates.create') }}"  method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="country_id">{{ __('Select Country')}}</label>
                                <select type="text" class="form-control" name="country_id" id="country_id">
                                    <option value="" disabled selected>{{ __('Select Country') }}</option>
                                    @foreach($all_country as $country)
                                        <option value="{{$country->id}}">{{$country->country}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3 submit_btn"> {{ __('Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-8 mt-5">
                <div class="card">
                    <div class="card-body">
                        <h1 class="title">{{ __('All Zones') }}</h1>
                        <div class="table-wrap table-responsive">
                            <table class="table table-default">
                                <thead>
                                <th>{{ __('No') }}</th>
                                <th>{{ __('Zone Name') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Action') }}</th>
                                </thead>
                                <tbody>
                                @foreach($all_zone as $key=>$zone)
                                    <tr>
                                        <td>{{ $key+1 }}</td>
                                        <td>{{ $zone->country }}</td>
                                        <td>
                                            @if($zone->zone_status == 1)
                                                <span class="btn btn-success btn-sm">{{__('Active')}}</span>
                                            @else
                                                <span class="btn btn-danger">{{__('Inactive')}}</span>
                                            @endif
                                            <span><x-status-change  :url="route('admin.country.coordinates.status',$zone->id)"/></span>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <x-delete-popover :url="route('admin.country.coordinates.delete', $zone->id)"/>
                                            </div>
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
    @php  $location_data = \Location::get(); @endphp
    <script src="{{asset('assets/backend/js/sweetalert2.js')}}"></script>
    <script src="{{asset('assets/common/js/flatpickr.js')}}"></script>
    <!-- google api key  -->
    <script  src="https://maps.googleapis.com/maps/api/js?key={{get_static_option('service_google_map_api_key')}}&libraries=drawing,places&v=3.45.8"></script>

    <script>
        (function($){
            "use strict";
            $(document).ready(function(){

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

            });

        })(jQuery);
    </script>
@endsection




