@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('To Do List')}}
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
                        <form action="{{ route('seller.todolist') }}" method="GET">
                            <div class="dashboard__headerGlobal__flex">
                                <div class="dashboard__headerGlobal__content">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                        <h4 class="dashboard_table__title">{{ __('Search Todo List Module') }}</h4> <i class="las la-angle-down search_by_all"></i>
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
                                 @if(request()->get('title'))  show @elseif(request()->get('status')) show @elseif(request()->get('todolist_date')) show @endif
                                " aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="single-settings">
                                                    <div class="single-dashboard-input">
                                                        <div class="row g-4 mt-3">
                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="title" class="info-title"> {{__('Title')}} </label>
                                                                    <input class="form--control" name="title" value="{{ request()->get('title') }}" type="text" placeholder="{{ __('Title') }}">
                                                                </div>
                                                            </div>

                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="status" class="info-title"> {{__('Status')}} </label>
                                                                    <select name="status">
                                                                        <option value="">{{__('Select Order Status')}}</option>
                                                                        <option value="1" @if(request()->get('status') == 1) selected @endif>{{ __('Completed') }}</option>
                                                                        <option value="in_completed" @if(request()->get('status') == 'in_completed') selected @endif>{{  __('In Completed') }}</option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <div class="col-lg-4 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="todolist_date" class="info-title"> {{__('Created Date Range')}} </label>
                                                                    <input class="form--control flatpickr_input"  name="todolist_date" type="text" value="{{ request()->get('todolist_date') }}" placeholder="{{ __('Created Date Range') }}">
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
                    <div class="dashboard_table__title__flex">
                        <h4 class="dashboard_table__title"> {{__('To Do List')}} </h4>
                        <div class="btn-wrapper" data-bs-toggle="modal" data-bs-target="#openTicket">
                            <a href="javascript:void(0)"
                               class="dashboard_table__title__btn btn-bg-1 radius-5"
                               data-bs-toggle="modal"
                               data-bs-target="#addTodoModal"><i class="fa-solid fa-plus"></i> {{__('Add Todo List' )}}</a>
                        </div>
                    </div>
                    <div class="mt-5"> <x-msg.error/> </div>
                @if($to_do_list->count() >= 1)
                        <div class="dashboard_table__main custom--table mt-4">
                            <table>
                                <thead>
                                <tr>
                                    <th>{{ __('No') }}</th>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($to_do_list as $key=>$data)
                                    <tr>
                                        <td>{{ $key+1 }}</td>
                                        <td>{{ $data->title }}</td>
                                        <td>
                                            @if($data->status==1)
                                                <span class="text-success">{{ __('Completed') }}</span>
                                            @else
                                                <span class="text-danger">{{ __('In Completed') }}</span>
                                            @endif
                                            <x-seller-coupon-status :url="route('seller.todolist.status',$data->id)"/>
                                        </td>
                                        <td width="50%">{{ $data->description }}</td>
                                        <td>
                                            <div class="dashboard-switch-single">
                                                <a href="#0" class="edit_todo_modal"
                                                   data-bs-toggle="modal"
                                                   data-bs-target="#editTodoModal"
                                                   data-id="{{ $data->id }}"
                                                   data-title="{{ $data->title }}"
                                                   data-description="{{ $data->description }}"
                                                > <i class="fa-regular fa-pen-to-square"></i> </a>
                                                <x-seller-delete-popup :url="route('seller.todolist.delete',$data->id)"/>
                                            </div>
                                        </td>
                                    </tr>
                                  @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="blog-pagination margin-top-55">
                            <div class="custom-pagination mt-4 mt-lg-5">
                                {!! $to_do_list->links() !!}
                            </div>
                        </div>
                    @else
                        <div class="chat_wrapper__details__inner__chat__contents">
                            <h2 class="chat_wrapper__details__inner__chat__contents__para">{{ __('No To do list Found') }}</h2>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>



    <!-- Add Modal -->
    <div class="modal fade" id="addTodoModal" tabindex="-1" role="dialog" aria-labelledby="couponModal" aria-hidden="true">
        <form action="{{ route('seller.todolist.add') }}" method="post">
            @csrf
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="couponModal">{{ __('Add New Todo List') }}</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">

                        <div class="form-group mt-3">
                            <label for="title" class="label_title">{{ __('Title') }} <span class="text-danger">*</span> </label>
                            <input type="text" name="title" id="title" class="form-control" placeholder="{{ __('Title') }}">
                        </div>
                        <div class="form-group mt-3">
                            <label for="description" class="label_title">{{ __('Description') }} <span class="text-danger">*</span> </label>
                            <textarea name="description" id="description" class="form-control textarea-input" cols="20" rows="7" style="height: 140px" placeholder="{{ __('Description') }}"></textarea>
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
    <div class="modal fade" id="editTodoModal" tabindex="-1" role="dialog" aria-labelledby="editTodoModal" aria-hidden="true">
        <form action="{{ route('seller.todolist.update') }}" method="post">
            <input type="hidden" id="up_id" name="up_id" >
            @csrf
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTodoModal">{{ __('Edit Todo') }}</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">

                        <div class="form-group mt-3">
                            <label for="up_title" class="label_title">{{ __('Title') }} <span class="text-danger">*</span> </label>
                            <input type="text" name="up_title" id="up_title" class="form-control" placeholder="{{ __('Title') }}">
                        </div>
                        <div class="form-group mt-3">
                            <label for="up_description" class="label_title">{{ __('Description') }} <span class="text-danger">*</span> </label>
                            <textarea name="up_description" id="up_description" class="form-control textarea-input" cols="20" rows="7" style="height: 119px"></textarea>
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
    <script src="{{asset('assets/common/js/flatpickr.js')}}"></script>
    <script>
        (function($){
            "use strict";
            $(document).ready(function(){

                // date range
                $('.flatpickr_input').flatpickr({
                    altFormat: "invisible",
                    altInput: false,
                    mode: "range",
                });

                $(document).on('click','.edit_todo_modal',function(e){
                    e.preventDefault();
                    let todo_id = $(this).data('id');
                    let title = $(this).data('title');
                    let description = $(this).data('description');

                    $('#up_id').val(todo_id);
                    $('#up_title').val(title);
                    $('#up_description').val(description);
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
                        confirmButtonText: 'Yes, change it!'
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