@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('All Tickets')}}
@endsection
@section('style')
    <link rel="stylesheet" href="{{asset('assets/backend/css/bootstrap-tagsinput.css')}}">
    <link rel="stylesheet" href="{{asset('assets/common/css/flatpickr.min.css')}}">
@endsection
@section('content')
    <x-frontend.seller-buyer-preloader/>
    @include('frontend.user.buyer.partials.sidebar-two')
    <div class="dashboard__right">
        @include('frontend.user.buyer.header.buyer-header')
        <div class="dashboard__body">
            <div class="dashboard__inner">

                <!-- search section start-->
                <div class="dashboard__inner__item dashboard_border padding-20 radius-10 bg-white">
                    <div class="dashboard__wallet">
                        <form action="{{ route('buyer.support.ticket') }}" method="GET">
                            <div class="dashboard__headerGlobal__flex">
                                <div class="dashboard__headerGlobal__content">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                        <h4 class="dashboard_table__title">{{ __('Search Ticket Module') }}</h4> <i class="las la-angle-down search_by_all"></i>
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
                                 @if(request()->get('order_id'))  show @elseif(request()->get('ticket_id')) show @elseif(request()->get('ticket_date')) show @endif
                                " aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="single-settings">
                                                    <div class="single-dashboard-input">
                                                        <div class="row g-4 mt-3">
                                                            <div class="col-lg-2 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="ticket_id" class="info-title"> {{__('Ticket ID')}} </label>
                                                                    <input class="form--control" name="ticket_id" value="{{ request()->get('ticket_id') }}" type="text" placeholder="{{ __('Ticket ID') }}">
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-2 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="order_id" class="info-title"> {{__('Order ID')}} </label>
                                                                    <input class="form--control" name="order_id" value="{{ request()->get('order_id') }}" type="text" placeholder="{{ __('Order ID') }}">
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-5 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="title" class="info-title"> {{__('Title')}} </label>
                                                                    <input class="form--control" name="title" value="{{ request()->get('title') }}" type="text" placeholder="{{ __('Title') }}">
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-3 col-sm-6">
                                                                <div class="single-info-input">
                                                                    <label for="dead_line" class="info-title"> {{__('Created Date Range')}} </label>
                                                                    <input class="form--control flatpickr_input"  name="ticket_date" type="date" placeholder="{{ __('Created Date Range') }}">
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

                <!-- Tickets table section start-->
                <div class="dashboard_table__wrapper dashboard_border  padding-20 radius-10 bg-white">
                    <div class="dashboard_table__title__flex">
                        <h4 class="dashboard_table__title">{{ __('All Tickets') }}</h4>
                        <div class="btn-wrapper" data-bs-toggle="modal" data-bs-target="#openTicket">
                            <a href="javascript:void(0)"
                               class="dashboard_table__title__btn btn-bg-1 radius-5"
                               data-bs-toggle="modal"
                               data-bs-target="#ticketModal"><i class="fa-solid fa-plus"></i> {{__('Create Ticket For A Order' )}}</a>
                        </div>
                    </div>

                    @if($tickets->count() >= 1)
                    <div class="dashboard_table__main custom--table mt-4">
                        <table>
                            <thead>
                            <tr>
                                <th>{{ __('Ticket Name/ID') }}</th>
                                <th>{{ __('Order ID') }}</th>
                                <th>{{ __('Priority') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                            </thead>
                            <tbody>

                            @foreach($tickets as $key=>$data)
                            <tr>
                                <td>
                                    <div class="dashboard_table__main__ticket">
                                        <h5 class="dashboard_table__main__ticket__title">{{ $data->title }}</h5>
                                        <span class="dashboard_table__main__ticket__id mt-2"> {{ __('Ticket ID:') }} {{ $data->id }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="dashboard_table__main__orderId">{{ $data->order_id }}</div>
                                </td>
                                <td>
                                    <div class="dashboard_table__main__priority">
                                        @if($data->priority=='low') <a href="javascript:void(0)" class="priorityBtn pending">{{ __(ucfirst($data->priority)) }}</a>@endif
                                        @if($data->priority=='high') <a href="javascript:void(0)" class="priorityBtn high">{{ __(ucfirst($data->priority)) }}</a>@endif
                                        @if($data->priority=='medium') <a href="javascript:void(0)" class="priorityBtn medium">{{ __(ucfirst($data->priority)) }}</a>@endif
                                        @if($data->priority=='urgent') <a href="javascript:void(0)" class="priorityBtn high">{{ __(ucfirst($data->priority)) }}</a>@endif
                                    </div>
                                </td>
                                <td>
                                    <div class="dashboard_table__main__status dropdown__status">
                                        @if($data->status === 'open')
                                            <a href="{{ route('buyer.support.ticket.view', $data->id) }}" class="dashboard_table__main__status__select dropdown__status__main Open"> {{ __('open') }}</a>
                                            <x-buyer-ticket-status-two :url="route('buyer.support.ticket.status.change',$data->id)"/>
                                        @else
                                            <a href="javascript:void(0)" class="dashboard_table__main__status__select dropdown__status__main Close">
                                                {{ __(ucfirst($data->status)) }}</a>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="dashboard_table__main__actions">
                                        <a href="{{ route('buyer.support.ticket.view', $data->id) }}" class="icon"><i class="fa-regular fa-eye"></i></a>
                                        <x-ticket-delete-popup :url="route('buyer.support.ticket.delete',$data->id)"/>
                                        <a href="javascript:void(0)"
                                           class="icon edit edit_priority_modal"
                                           data-bs-toggle="modal"
                                           data-bs-target="#editPriorityModal"
                                           data-id="{{ $data->id }}"
                                           data-priority="{{ $data->priority }}"
                                        > <i class="fa-regular fa-pen-to-square"></i></a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach

                            </tbody>
                        </table>
                    </div>

                        <div class="blog-pagination margin-top-55">
                            <div class="custom-pagination mt-4 mt-lg-5">
                                {!! $tickets->links() !!}
                            </div>
                        </div>

                    @else
                        <div class="chat_wrapper__details__inner__chat__contents mt-3">
                            <p class="no_data_found_for_buyer_seller_panel">
                                {{ __('No Tickets found')}}
                            </p>
                        </div>
                    @endif
                <!-- Tickets table section end -->
            </div>
        </div>
      </div>
    </div>

        <!-- priority Ticket Modal -->
        <div class="modal fade" id="editPriorityModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">{{ __('Change Priority') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="custom-form">
                            <form action="{{ route('buyer.support.ticket.priority.change') }}" method="post">
                                <input type="hidden" id="ticket_id" name="ticket_id">
                                @csrf
                                <div class="row g-4">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="up_day_id">{{ __('Select Status') }}</label>
                                            <select name="priority" id="priority" class="form-control nice-select">
                                                <option value="low">{{__('Low')}}</option>
                                                <option value="medium">{{__('Medium')}}</option>
                                                <option value="high">{{__('High')}}</option>
                                                <option value="urgent">{{__('Urgent')}}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">{{ __('Save changes') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Ticket Modal -->
    <div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('Open a Ticket') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="custom-form">
                        <form action="{{ route('buyer.support.ticket.new') }}" method="post">
                            @csrf

                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="ticketTitle" class="label_title">{{ __('Ticket title') }}</label>
                                        <input type="text" name="title" value="{{ @old('title') }}" id="ticketTitle" class="form--control radius-10" placeholder="{{ __('e.g. I got into a problem') }}">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="ticketTitle" class="label_title">{{ __('Subject') }}</label>
                                        <input type="text" name="subject" value="{{ @old('subject') }}" id="ticketTitle" class="form--control radius-10"
                                               placeholder="{{ __('Subject') }}">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="ticketTitle" class="label_title">{{__('Select Order')}}</label>
                                        <div class="single-input-select radius-10">
                                            <select class="select2_activation" name="order_id">
                                                <option value="">{{__('Select Order')}}</option>
                                                @foreach($orders as $order)
                                                    <option value="{{ $order->id }}">{{ __('Order ID#') }} {{ $order->id }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="ticketTitle" class="label_title">{{ __('Priority type') }}</label>
                                        <div class="single-input-select radius-10">
                                            <select class="select2_activation" name="priority" id="priority">
                                                <option value="low">{{__('Low')}}</option>
                                                <option value="medium">{{__('Medium')}}</option>
                                                <option value="high">{{__('High')}}</option>
                                                <option value="urgent">{{__('Urgent')}}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="ticketTitle" class="label_title">{{ __('Description') }}</label>
                                        <textarea name="description" placeholder="{{__('Type Description')}}" cols="10" rows="2" class="form--control radius-10"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">{{ __('Save changes') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

@endsection
@section('scripts')
    <x-media.js :type="'web'"/>
    <script src="{{asset('assets/backend/js/sweetalert2.js')}}"></script>
            <script src="{{asset('assets/backend/js/bootstrap-tagsinput.js')}}"></script>
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

                // for toggle dropdown menu
                $('.dropdown__status__list > .dropdown__status__list__option').click(function () {
                    window.location = $(this).attr('href');
                });


                // for modal search
                $('.select2_activation').select2({
                    dropdownParent: $('#ticketModal')
                });

                $(document).on('click', '.edit_priority_modal', function(e) {
                    e.preventDefault();
                    let ticket_id = $(this).data('id');
                    let priority = $(this).data('priority');

                    $('#ticket_id').val(ticket_id);
                    $('#priority').val(priority);
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

                $(document).on('click','.swal_status_button',function(e){
                    e.preventDefault();
                    Swal.fire({
                        title: '{{__("Are you sure to close status?")}}',
                        text: '{{__("You will not able to open it!")}}',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: "{{__('Yes, change it!')}}"
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