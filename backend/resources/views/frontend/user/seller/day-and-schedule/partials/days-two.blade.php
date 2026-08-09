@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('Days')}}
@endsection
@section('style')
    <link rel="stylesheet" href="{{asset('assets/common/css/flatpickr.min.css')}}">
    <style>
        .close{ border: none;  }
        .dashboard-switch-single{ font-size: 20px; }
        .swal_delete_button{ color: #da0000 !important; }
        .days_new_style .select2-container {
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
                    <h2 class="dashboard_table__title"> {{__('All Days')}} </h2>
                    <div class="dashboard_table__title__flex">
                        <form class="total_service_day" action="{{ route('seller.update.totalday') }}" method="post">
                            @csrf
                            <div class="days_new_style">
                                <div class="form_service_day">
                                    <label class="total_day_label label_title mt-2"> {{ __('Select Service Day') }} </label>
                                    <select name="total_day">
                                        @if(empty($total_day))
                                            @for($i=1; $i<=30; $i++){
                                            <option value="{{ $i }}">{{ $i }} {{ __('Day') }}</option>
                                            }
                                            @endfor
                                        @else
                                            @for($i=1; $i<=30; $i++){
                                            <option value="{{ $i }}" @if($total_day->total_day==$i) selected @endif>{{ $i }} {{ __('Day') }}</option>
                                            }
                                            @endfor
                                        @endif
                                    </select>
                                </div>
                                <div class="btn-wrapper mx-4 mt-5">
                                    <button type="submit" class="dashboard_table__title__btn btn-bg-1 radius-5" style="border: none">{{__('Update' )}}</button>
                                </div>
                            </div>
                        </form>

                        <div class="btn-wrapper">
                            <a href="javascript:void(0)"
                               class="dashboard_table__title__btn btn-bg-1 radius-5"
                               data-bs-toggle="modal"
                               data-bs-target="#addDayModal"><i class="fa-solid fa-plus"></i> {{__('Add Day' )}}</a>
                        </div>
                    </div>

                    <div class="notice-board">
                        <p class="text-danger">{{ __('selected days will show while booking an order') }}</p>
                    </div>

                    <div class="mt-5"> <x-msg.error/> </div>
                    @if($days->count() >= 1)
                        <div class="dashboard_table__main custom--table mt-4">
                            <table>
                                <thead>
                                <tr>
                                    <th>{{ __('No') }}</th>
                                    <th>{{ __('Day') }}</th>
                                    <th>{{ __('Schedule') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($days as $data)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ __($data->day) }}</td>
                                        <td>
                                            @if(isset($data['schedules']) && $data->schedules->count())
                                                @foreach($data['schedules'] as $schedule)
                                                    <span class="btn btn-sm btn-success day_wise_service_schedule">{{ $schedule->schedule }}</span>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td>
                                            <div class="dashboard-switch-single">
                                                <x-seller-delete-popup :url="route('seller.day.delete',$data->id)"/>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="chat_wrapper__details__inner__chat__contents">
                            <h2 class="chat_wrapper__details__inner__chat__contents__para">{{ __('No Days Found') }}</h2>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addDayModal" tabindex="-1" role="dialog" aria-labelledby="dayModal" aria-hidden="true">
        <form action="{{ route('seller.add.day') }}" method="post">
            @csrf
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="dayModal">{{ __('Add New Day') }}</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="day" class="label_title">{{ __('Day') }}</label>
                            <select name="day" id="day" class="form-control select2_activation">
                                <option value="">{{ __('Select Day') }}</option>
                                <option value="Sat">{{ __('Saturday') }}</option>
                                <option value="Sun">{{ __('Sunday') }}</option>
                                <option value="Mon">{{ __('Monday') }}</option>
                                <option value="Tue">{{ __('Tuesday') }}</option>
                                <option value="Wed">{{ __('Wednesday') }}</option>
                                <option value="Thu">{{ __('Thursday') }}</option>
                                <option value="Fri">{{ __('Friday') }}</option>
                            </select>
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

                // for modal search
                $('.select2_activation').select2({
                    dropdownParent: $('#addDayModal')
                });

                $(document).on('click','.edit_date_modal',function(e){
                    e.preventDefault();
                    let date_id = $(this).data('id');
                    let date = $(this).data('date');
                    $('#up_id').val(date_id)
                    $('#up_date').val(date)
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