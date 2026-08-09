@extends('backend.admin-master')
@section('site-title')
    {{__('Service Country')}}
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
            <div class="col-lg-12 mt-5">
                <div class="card">
                    <div class="card-body">
                        <div class="header-wrap d-flex justify-content-between">
                            <div class="left-content">
                                <h4 class="header-title">{{__('Service Country')}}  </h4>
                                @can('country-delete')
                                  <x-bulk-action/>
                                @endcan
                            </div>
                            @can('country-create')
                            <div class="right-content">
                                <a href="{{ route('admin.country.settings')}}" class="btn btn-info">{{__('Set Default Country')}}</a>
                                <a href="{{ route('admin.country.add')}}" class="btn btn-primary mx-2">{{__('Add New Country')}}</a>
                            </div>
                             @endcan
                        </div>
                        <div class="table-wrap table-responsive">
                            <table class="table table-default">
                                <thead>
                                <th class="no-sort">
                                    <div class="mark-all-checkbox">
                                        <input type="checkbox" class="all-checkbox">
                                    </div>
                                </th>
                                <th>{{__('ID')}}</th>
                                <th>{{__('Country')}}</th>
                                <th>{{__('Country Code')}}</th>
                                <th>{{__('Latitude')}}</th>
                                <th>{{__('Longitude')}}</th>
                                <th>{{__('Flag')}}</th>
                                <th>{{__('Status')}}</th>
                                <th>{{__('Create Date')}}</th>
                                <th>{{__('Action')}}</th>
                                </thead>
                                <tbody>
                                    @foreach($countries as $data)
                                        <tr>
                                            <td>
                                                <x-bulk-delete-checkbox :id="$data->id"/>
                                            </td>
                                            <td>{{$data->id}}</td>
                                            <td>{{$data->country}}</td>
                                            <td>{{$data->country_code}}</td>
                                            <td>{{$data->latitude}}</td>
                                            <td>{{$data->longitude}}</td>

                                            <td>
                                                @if(!empty($data->flag_url))
                                                    <img class="avatar user-thumb" src="{{ $data->flag_url }}" alt="" height="100px" width="100px">
                                                @else
                                                    <img class="avatar user-thumb" src="{{ url('/assets/uploads/no-image.png') }}" alt="" height="100px" width="100px">
                                                @endif

                                            </td>
                                            <td>
                                                @can('country-status')
                                                    @if($data->status==1)
                                                    <span class="btn btn-success btn-sm">{{__('Active')}}</span>
                                                    @else 
                                                    <span class="btn btn-danger">{{__('Inactive')}}</span> 
                                                    @endif
                                                    <span><x-status-change :url="route('admin.country.status',$data->id)"/></span>
                                                @endcan
                                            </td>
                                            <td>{{date('d-m-Y', strtotime($data->created_at))}}</td>
                                            <td>
                                                @can('country-delete')
                                                  <x-delete-popover :url="route('admin.country.delete',$data->id)"/>
                                                @endcan
                                                @can('country-edit')
                                                <a href="#"
                                                    data-toggle="modal"
                                                    data-target="#country_edit_modal"
                                                    class="btn btn-primary btn-xs mb-3 mr-1 country_edit_btn"
                                                    data-id="{{$data->id}}"
                                                    data-country="{{$data->country}}"  
                                                    data-country_code="{{$data->country_code}}"
                                                    data-flag_url="{{$data->flag_url}}"
                                                    data-latitude="{{$data->latitude}}"
                                                    data-longitude="{{$data->longitude}}"
                                                    >
                                                    <i class="ti-pencil"></i>
                                                </a>
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


    <div class="modal fade" id="country_edit_modal" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{__('Edit Country')}}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>×</span></button>
                </div>
                <form action="{{route('admin.country.edit')}}" method="post">
                    <div class="modal-body">
                        @csrf
                        <input type="hidden" name="up_id" id="up_id">

                        <div class="form-group">
                            <label for="up_country">{{__('Service Country')}}</label>
                            <input type="text" class="form-control" name="up_country" id="up_country" placeholder="{{__('Service Country')}}">
                        </div>
                        <div class="form-group">
                            <label for="country_code">{{__('Country code')}}</label>
                            <input type="text" class="form-control" name="country_code" id="country_code" placeholder="{{__('Country code')}}">
                        </div>
                        <div class="form-group">
                            <label for="flag_url">{{__('Country flag url')}}</label>
                            <input type="text" class="form-control" name="flag_url" id="flag_url" placeholder="{{__('Country flag url')}}">
                            <span> {{ __('Example:') }} https://flagcdn.com/w320/name</span>
                        </div>
                        <div class="form-group">
                            <label for="latitude">{{__('Country latitude')}}</label>
                            <input type="text" class="form-control" name="latitude" id="latitude" placeholder="{{__('Country latitude')}}">
                        </div>

                        <div class="form-group">
                            <label for="longitude">{{__('Country longitude')}}</label>
                            <input type="text" class="form-control" name="longitude" id="longitude" placeholder="{{__('Country longitude')}}">
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{__('Close')}}</button>
                        <button id="update" type="submit" class="btn btn-primary">{{__('Save Changes')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
 <x-datatable.js/>
    <script type="text/javascript">
        (function(){
            "use strict";
            $(document).ready(function(){
                <x-bulk-action-js :url="route('admin.country.bulk.action')"/>

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

                $(document).on('click', '.country_edit_btn', function () {
                    var el = $(this);
                    var id = el.data('id');
                    var country = el.data('country');
                    var country_code = el.data('country_code');
                    var flag_url = el.data('flag_url');
                    var latitude = el.data('latitude');
                    var longitude = el.data('longitude');
                    var form = $('#country_edit_modal');
                    form.find('#up_id').val(id);
                    form.find('#up_country').val(country);
                    form.find('#country_code').val(country_code);
                    form.find('#flag_url').val(flag_url);
                    form.find('#latitude').val(latitude);
                    form.find('#longitude').val(longitude);
                });

              });
        })(jQuery);
    </script>
@endsection
