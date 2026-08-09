@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('Schedules')}}
@endsection
@section('style')
    <link rel="stylesheet" href="{{asset('assets/common/css/flatpickr.min.css')}}">
    <style>
        .close{ border: none;  }
        .dashboard-switch-single{ font-size: 20px; }
        .swal_delete_button{ color: #da0000 !important; }
        .select2-selection.select2-selection--single {
            /*background-color: #eaecf0;*/
        }
        .allow_multiple_schedule .select2-container {
            z-index: 0;
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
                <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="dashboard-settings margin-top-40">
                                <h4 class="dashboards-title"> {{__('All Schedules')}} </h4>
                                <div class="notice-board">
                                    <p class="text-danger">{{ __('schedules will show while a customer booking your order') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5"> <x-msg.error/> </div>

                    <div class="dashboard_table__title__flex mt-3">
                        <div class="">
                            <form action="{{ route('seller.allow.multiple.schedule') }}" method="post">
                                @csrf
                                <div class="allow_multiple_schedule">
                                    @php
                                        $allow_or_not = App\Schedule::select('allow_multiple_schedule')->first();
                                    @endphp
                                    <label class="total_day_label label_title"> {{ __('Allow Multiple Order to Same Schedule ') }} </label>
                                    <select name="allow_multiple_schedule">
                                        <option value="{{ __('yes') }}" @if($allow_or_not?->allow_multiple_schedule=='yes') selected @endif> {{ __('Yes') }}</option>
                                        <option value="{{ __('no') }}" @if($allow_or_not?->allow_multiple_schedule=='no') selected @endif> {{ __('No') }}</option>
                                    </select>
                                    <p class="text-warning mt-3">{{ __('If you select yes than buyer will place multiple order at the same schedule') }}</p>
                                </div>
                                <div class="btn-wrapper mt-3">
                                    <button type="submit" class="dashboard_table__title__btn btn-bg-1 radius-5" style="border: none">{{__('Submit')}}</button>
                                </div>
                            </form>
                        </div>

                        <div class="btn-wrapper">
                            <a href="javascript:void(0)"
                               class="dashboard_table__title__btn btn-bg-1 radius-5"
                               data-bs-toggle="modal"
                               data-bs-target="#addScheduleModal">{{__('Add Schedule' )}}</a>
                        </div>
                    </div>

                    <div class="dashboard-service-single-item border-1 margin-top-40 mt-4">
                        <div class="rows dash-single-inner">
                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>{{ __('No') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Schedule') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($schedules as $key => $data)
                                    <tr>
                                        <td>{{ $key+1 }}</td>
                                        <td>{{ __(optional($data->days)->day) }}</td>
                                        <td>{{ $data->schedule }}</td>
                                        <td>
                                            <div class="dashboard-switch-single">
                                                <a href="#0" class="edit_schedule_modal"
                                                   data-bs-toggle="modal"
                                                   data-bs-target="#editScheduleModal"
                                                   data-id="{{ $data->id }}"
                                                   data-dayid="{{ $data->day_id }}"
                                                   data-schedule="{{ $data->schedule }}"
                                                > <span class="dash-icon dash-edit-icon color-1"> <i class="las la-edit"></i> </span>
                                                </a>
                                                <x-seller-delete-popup :url="route('seller.schedule.delete',$data->id)"/>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="blog-pagination margin-top-55">
                        <div class="custom-pagination mt-4 mt-lg-5">
                            {!! $schedules->links() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModal" aria-hidden="true">
        <form action="{{ route('seller.add.schedule') }}" method="post">
            @csrf
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ScheduleModal">{{ __('Add New Schedule') }}</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group margin-bottom-60">
                            <label for="day_id" class="label_title">{{ __('Select Day') }} <span class="text-danger">*</span> </label>
                            <select name="day_id" id="day_id" class="form-control">
                                <option value="">{{ __('Select Day') }}</option>
                                @foreach($days as $day)
                                    <option value="{{ $day->id }}">{{ __($day->day) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <br>
                        <div class="form-group mt-3">
                            <div class="form-check">
                                <input class="form-check-input" name="schedule_for_all_days" type="checkbox"  id="flexCheckDefault">
                                <label class="form-check-label label_title" for="flexCheckDefault">
                                    {{__('Add this schedule time for all days.')}}
                                </label>
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <label for="schedule" class="label_title">{{ __('Schedule Time') }} <span class="text-danger">*</span> </label>
                            <input type="text" name="schedule" id="schedule" class="form-control" placeholder="{{__('Schedule Time')}}">
                            <span class="info mt-2">{{__('eg: 10:00Am - 11:00PM, this schedule time will show in frontend, when anyone want to book your service, they will select this schedule time made by you')}}</span>
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
    <div class="modal fade" id="editScheduleModal" tabindex="-1" role="dialog" aria-labelledby="editModal" aria-hidden="true">
        <form action="{{ route('seller.edit.schedule') }}" method="post">
            <input type="hidden" id="up_id" name="up_id" >
            @csrf
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModal">{{ __('Edit Schedule') }}</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">

                        <div class="form-group">
                            <label for="up_day_id" class="label_title">{{ __('Select Day') }} <span class="text-danger">*</span> </label>
                            <select name="up_day_id" id="up_day_id" class="form-control nice-select">
                                <option value="">{{ __('Select Day') }}</option>
                                @foreach($days as $day)
                                    <option value="{{ $day->id }}">{{ $day->day }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="up_schedule" class="label_title">{{ __('Schedule') }} <span class="text-danger">*</span> </label>
                            <input type="text" name="up_schedule" id="up_schedule" class="form-control">
                            <span class="info mt-2">{{__('eg: 10:00Am - 11:00PM, this schedule time will show in frontend, when anyone want to book your service, they will select this schedule time made by you')}}</span>
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

                // search select2
                $('#day_id').select2({
                    dropdownParent: $('#addScheduleModal')
                });
                  $('#up_day_id').select2({
                    dropdownParent: $('#editScheduleModal')
                });

                // for modal search
                $('.select2_activation').select2({
                    dropdownParent: $('#addScheduleModal__', '#addScheduleModal__')
                });

                $(document).on('click','.edit_schedule_modal',function(e){
                    e.preventDefault();
                    let schedule_id = $(this).data('id');
                    let day_id = $(this).data('dayid');
                    let schedule = $(this).data('schedule');

                    console.log(schedule_id + day_id);

                    $('#up_id').val(schedule_id);
                    $('#up_day_id').val(day_id);
                    $('#up_schedule').val(schedule);
                    $('.nice-select').niceSelect('update');
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

            });

        })(jQuery);
    </script>
@endsection