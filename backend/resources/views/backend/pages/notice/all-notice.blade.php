@extends('backend.admin-master')
@section('site-title')
    {{__('All Notices')}}
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
                                <h4 class="header-title">{{__('All Notices')}}  </h4>
                            </div>
                                <div class="header-title d-flex">
                                    <div class="btn-wrapper-inner">
                                        <a href="{{ route('admin.add.notice.page') }}" class="btn btn-primary">{{__('Add Notice')}}</a>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="table-wrap table-responsive">
                            <table class="table table-default">
                                <thead>
                                <th>{{__('ID')}}</th>
                                <th>{{__('Title')}}</th>
                                <th>{{__('Description')}}</th>
                                <th>{{__('Notice Type')}}</th>
                                <th>{{__('Notice For')}}</th>
                                <th>{{__('Expire Date')}}</th>
                                <th>{{__('Status')}}</th>
                                <th>{{__('Action')}}</th>
                                </thead>
                                <tbody>
                                @foreach($notices as $data)
                                    <tr>
                                        <td>{{$data->id}}</td>
                                        <td>{{$data->title}}</td>
                                        <td>{!! $data->description !!}</td>
                                        <td>
                                            @if($data->notice_type === 1)
                                             <span class="text-danger">{{ __('Error') }}</span>
                                            @elseif($data->notice_type === 2)
                                                <span class="text-warning">{{ __('Warning') }}</span>
                                            @elseif($data->notice_type === 3)
                                                <span class="text-success">{{ __('Success') }}</span>
                                            @elseif($data->notice_type === 4)
                                                <span class="text-info">{{ __('Info') }}</span>
                                            @endif
                                        </td>

                                        <td>
                                            @if($data->notice_for === 1)
                                             {{ __('Frontend') }}
                                            @elseif($data->notice_for === 2)
                                               {{ __('Buyer Dashboard') }}
                                            @elseif($data->notice_for === 3)
                                             {{ __('Seller Dashboard') }}
                                            @endif
                                        </td>

                                        <td>
                                            @if(!empty($data->expire_date))
                                                {{ date('d-m-Y', strtotime($data->expire_date)) }}
                                            @else
                                                {{ __('Date not available') }}
                                            @endif
                                        </td>

                                        <td width="200px">
                                            @can('category-status')
                                                @if($data->status==1)
                                                    <span class="btn btn-success btn-sm">{{__('Active')}}</span>
                                                @else
                                                    <span class="btn btn-danger">{{__('Inactive')}}</span>
                                                @endif
                                                <span class="my-2"><x-status-change :url="route('admin.notice.status',$data->id)"/></span>
                                            @endcan
                                        </td>

                                        <td width="200px">
                                            @can('category-delete')
                                                <x-delete-popover :url="route('admin.delete.notice',$data->id)"/>
                                            @endcan
                                            @can('category-edit')
                                                <x-edit-icon :url="route('admin.notice.edit',$data->id)"/>
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
    <script type="text/javascript">
        (function(){
            "use strict";
            $(document).ready(function(){

                $(document).on('click','.swal_status_change',function(e){
                    e.preventDefault();
                    Swal.fire({
                        title: '{{__("Are you sure to change status complete? Once you done you can not revert this !!")}}',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: "{{ __('Yes, change it!') }}"
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
