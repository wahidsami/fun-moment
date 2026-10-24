@extends('backend.admin-master')
@section('style')
    <x-summernote.css/>
    <link rel="stylesheet" href="{{asset('assets/backend/css/dropzone.css')}}">
    <x-media.css/>
    <x-datatable.css/>
    <style>
        #edit_user_info_modal .modal-body {
            max-height: calc(100vh -  150px);
            overflow-y: auto;
        }
    </style>
@endsection

@section('site-title')
    {{__('All Users')}}
@endsection

@section('content')
    <div class="col-lg-12 col-ml-12 padding-bottom-30">
        <div class="row">
            <div class="col-12 mt-5">
                <div class="card">
                    <div class="card-body">
                        <div class="col-12 mt-5">
                            <div class="card">
                                <div class="card-body">
                                  <x-msg.success/>
                                  <x-msg.error/>
                                    <h4 class="header-title">{{__('All Users')}}</h4>
                                    @can('user-delete')
                                       <x-bulk-action/>
                                    @endcan
                                    <div class="data-tables datatable-primary table-wrap">
                                        <table class="text-center">
                                            <thead class="text-capitalize">
                                            <tr>
                                                <th class="no-sort">
                                                    <div class="mark-all-checkbox">
                                                        <input type="checkbox" class="all-checkbox">
                                                    </div>
                                                </th>
                                                <th>{{__('ID')}}</th>
                                                <th>{{__('Name')}}</th>
                                                <th>{{__('User Status')}}</th>
                                                <th>{{__('User Verified')}}</th>
                                                <th>{{__('Email Verify')}}</th>
                                                <th>{{__('Phone')}}</th>
                                                <th>{{__('Action')}}</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Primary table end -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('newsletter-send-mail')
    <div class="modal fade" id="send_mail_modal" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{__('Send Mail To User')}}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>×</span></button>
                </div>
                <form action="{{ route('admin.frontend.user.email.send.single') }}" id="send_mail_modal_form"  method="post">
                    <div class="modal-body">
                        @csrf
                        <div class="form-group">
                            <label for="email">{{__('Email')}}</label>
                            <input type="text" readonly class="form-control"  id="email" name="email" placeholder="{{__('Email')}}">
                        </div>
                        <div class="form-group">
                            <label for="edit_icon">{{__('Subject')}}</label>
                            <input type="text" class="form-control"  id="subject" name="subject" placeholder="{{__('Subject')}}">
                        </div>
                        <div class="form-group">
                            <label for="message">{{__('Message')}}</label>
                            <input type="hidden" name="message" >
                            <div class="summernote"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{__('Close')}}</button>
                        <button id="submit" type="submit" class="btn btn-primary">{{__('Send Mail')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan

   <div class="modal fade" id="change_password_modal" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{__('Change User Password')}}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>×</span></button>
                    </div>
                    <form action="{{ route('admin.frontend.user.password') }}" id="change_password_modal_form"  method="post">
                        <div class="modal-body">
                            @csrf
                            <div class="form-group">
                                <label for="email">{{__('Email')}}</label>
                                <input type="text" readonly class="form-control"  id="user_new_password_email" name="user_new_password_email" placeholder="{{__('Email')}}">
                            </div>
                            <div class="form-group">
                                <label for="edit_icon">{{__('New Password')}}</label>
                                <input type="text" class="form-control"  id="user_new_password" name="user_new_password" placeholder="{{__('New Password')}}">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{__('Close')}}</button>
                            <button id="submit" type="submit" class="btn btn-primary">{{__('Save & Send Mail')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <div class="modal fade" id="edit_user_info_modal" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{__('Edit User Info')}}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>×</span></button>
                </div>
                <form action="{{ route('admin.user.info.update') }}" id="user_info_modal_form"  method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        @csrf
                        <div class="form-group">
                            <label for="edit_email">{{__('Email')}}</label>
                            <input type="text" readonly class="form-control"  id="edit_email" name="edit_email" placeholder="{{__('Email')}}">
                        </div>
                        <div class="form-group">
                            <label for="edit_username">{{__('Username')}}</label>
                            <input type="text" readonly class="form-control"  id="edit_username" name="edit_username" placeholder="{{__('username')}}">
                        </div>
                        <div class="form-group">
                            <label for="edit_name">{{__('Full Name')}}</label>
                            <input type="text" class="form-control"  id="edit_name" name="edit_name" placeholder="{{__('name')}}">
                        </div>
                        <div class="form-group">
                            <label for="edit_phone">{{__('Phone')}}</label>
                            <input type="text" class="form-control"  id="edit_phone" name="edit_phone" placeholder="{{__('phone')}}">
                            <small>{{ __('Please enter country code') }}</small>
                        </div>
                        <div class="form-group country-wrapper">
                            <label class="forms-label"> {{ __('Service Country*') }} </label>
                            <select name="edit_country" id="edit_country" class="form-control">
                                <option value="">{{ __('Select Country') }}</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->country }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group service_city_wrapper">
                            <label class="forms-label"> {{ __('Service City*') }} </label>
                            <select name="edit_city" id="edit_city" class="get_service_city form-control">
                                <option value="">{{ __('Select City') }}</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}">{{ $city->service_city }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group service_area_wrapper">
                            <label class="forms-label"> {{ __('Service Area*') }} </label>
                            <select name="edit_area" id="edit_area" class="get_service_area form-control">
                                <option value="">{{ __('Select Area') }}</option>
                                @foreach ($areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->service_area }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edit_username">{{__('Address')}}</label>
                            <textarea name="edit_address" id="edit_address" rows="5" class="form-control"></textarea>
                        </div>


                        <div class="form-group tax_number_option">
                            <label for="tax_number">{{__('Tax Number')}}</label>
                            <input name="tax_number" id="tax_number" class="form-control" placeholder="{{ __('Tax Number') }}">
                        </div>


                        <div class="form-group">
                            <div class="media-upload-btn-wrapper">
                                <div class="img-wrap"></div>
                                <input type="hidden" id="edit_image" name="edit_image">
                                <button type="button" class="btn btn-info media_upload_form_btn"
                                        data-btntitle="{{__('Select Image')}}"
                                        data-modaltitle="{{__('Upload Image')}}" data-toggle="modal"
                                        data-target="#media_upload_modal">
                                    {{__('Upload Profile Image')}}
                                </button>
                            </div>
                            <small class="form-text text-muted">{{__('allowed image format: jpg,jpeg,png')}}</small>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{__('Close')}}</button>
                        <button id="submit" type="submit" class="btn btn-primary">{{__('Save')}}</button>
                    </div>
                </form>
            </div>
        </div>
    <!-- User Deletion Impact Preview Modal -->
    <div class="modal fade" id="delete_user_impact_modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-danger">
                        <i class="ti-trash mr-1"></i> {{__('Review & Delete User Account')}}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="delete_user_confirmed_form" method="POST" action="">
                    @csrf
                    <div class="modal-body">
                        <!-- Loading State -->
                        <div id="delete_modal_loading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">{{__('Loading impact analysis...')}}</span>
                            </div>
                            <p class="mt-2 text-muted">{{__('Calculating account dependencies and deletion impact...')}}</p>
                        </div>

                        <!-- Content State -->
                        <div id="delete_modal_content" style="display:none;">
                            <!-- Account Identity Card -->
                            <div class="card mb-3 border-secondary">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0 font-weight-bold" id="preview_user_name_badge"></h6>
                                        <span class="badge" id="preview_user_role_badge"></span>
                                    </div>
                                    <div class="row text-sm">
                                        <div class="col-md-6">
                                            <strong>{{__('User ID:')}}</strong> <span id="preview_user_id"></span><br>
                                            <strong>{{__('Username:')}}</strong> <span id="preview_user_username"></span><br>
                                            <strong>{{__('Email:')}}</strong> <span id="preview_user_email" class="text-primary font-weight-bold d-inline-block" dir="ltr"></span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>{{__('Status:')}}</strong> <span id="preview_user_status"></span><br>
                                            <strong>{{__('Email Release:')}}</strong> <span class="badge badge-success">{{__('Immediate')}}</span><br>
                                            <strong>{{__('Deletion Mode:')}}</strong> <span id="preview_deletion_mode_badge"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Blocking Alert Box (when deletion is not permitted) -->
                            <div id="delete_modal_blockers_box" class="alert alert-danger" style="display:none;">
                                <h6 class="alert-heading font-weight-bold mb-1">
                                    <i class="ti-close mr-1"></i> {{__('Deletion Blocked: Unresolved Obligations')}}
                                </h6>
                                <p class="mb-1 text-sm">{{__('This account cannot be deleted until the following obligations are settled:')}}</p>
                                <ul id="delete_modal_blockers_list" class="mb-0 pl-3 pr-3 text-sm"></ul>
                            </div>

                            <!-- Metrics Summary Cards -->
                            <div class="row mb-3">
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="p-2 border rounded text-center bg-light">
                                        <div class="text-xs text-muted text-uppercase">{{__('Services')}}</div>
                                        <div class="h5 mb-0 font-weight-bold text-dark" id="preview_count_services">0</div>
                                        <small class="text-muted">{{__('Unpublished')}}</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="p-2 border rounded text-center bg-light">
                                        <div class="text-xs text-muted text-uppercase">{{__('Active Orders')}}</div>
                                        <div class="h5 mb-0 font-weight-bold text-danger" id="preview_count_active_orders">0</div>
                                        <small class="text-muted">{{__('Blocker if > 0')}}</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="p-2 border rounded text-center bg-light">
                                        <div class="text-xs text-muted text-uppercase">{{__('Wallet Balance')}}</div>
                                        <div class="h5 mb-0 font-weight-bold text-warning" id="preview_count_balance">0.00</div>
                                        <small class="text-muted">{{__('SAR')}}</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="p-2 border rounded text-center bg-light">
                                        <div class="text-xs text-muted text-uppercase">{{__('Retained History')}}</div>
                                        <div class="h5 mb-0 font-weight-bold text-info" id="preview_count_retained">0</div>
                                        <small class="text-muted">{{__('Ledgers/Invoices')}}</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Detailed Impact Tabs / Sections -->
                            <div class="card mb-3">
                                <div class="card-header bg-white py-2">
                                    <ul class="nav nav-pills card-header-pills text-sm" id="impact_tabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active py-1 px-2" id="tab-deleted-link" data-toggle="pill" href="#tab-deleted">{{__('Permanently Deleted')}}</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link py-1 px-2" id="tab-retained-link" data-toggle="pill" href="#tab-retained">{{__('Retained Integrity')}}</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link py-1 px-2" id="tab-anonymized-link" data-toggle="pill" href="#tab-anonymized">{{__('Anonymized Fields')}}</a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body p-3">
                                    <div class="tab-content" id="impact_tab_content">
                                        <div class="tab-pane fade show active text-sm" id="tab-deleted">
                                            <ul id="preview_list_deleted" class="mb-0 pl-3 pr-3 text-muted"></ul>
                                        </div>
                                        <div class="tab-pane fade text-sm" id="tab-retained">
                                            <ul id="preview_list_retained" class="mb-0 pl-3 pr-3 text-muted"></ul>
                                        </div>
                                        <div class="tab-pane fade text-sm" id="tab-anonymized">
                                            <ul id="preview_list_anonymized" class="mb-0 pl-3 pr-3 text-muted"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Target Confirmation Input (Only when can_delete is true) -->
                            <div id="delete_modal_confirm_wrapper">
                                <div class="form-group mb-2">
                                    <label for="confirm_delete_email_input" class="font-weight-bold text-sm">
                                        {{__('To prevent accidental deletion, type the account email address to confirm:')}}
                                        <span class="text-danger ml-1 mr-1 d-inline-block font-weight-bold" id="delete_target_expected_email" dir="ltr"></span>
                                    </label>
                                    <input type="text" class="form-control" id="confirm_delete_email_input" name="confirm_email" autocomplete="off" dir="ltr" placeholder="{{__('Type email here to unlock deletion')}}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{__('Cancel')}}</button>
                        <button type="submit" id="confirm_delete_submit_btn" class="btn btn-danger btn-sm" disabled>
                            <i class="ti-trash mr-1"></i> {{__('Confirm & Execute Deletion')}}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<x-media.markup/>
@endsection
@section('script')
    <x-datatable.js nojs="true"/>
    <script src="{{asset('assets/backend/js/summernote-bs4.js')}}"></script>
    <script>
        (function($){
        "use strict";
        $(document).ready(function() {

            $('.table-wrap > table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{route("admin.all.frontend.user")}}",
                columns: [
                    {data: 'checkbox', name: '', orderable: false, searchable: false},
                    {data: 'id', name: 'id'},
                    {data: 'name', name: 'name', orderable: true, searchable: true},
                    {data: 'user_status', name: '', orderable: false},
                    {data: 'user_verify', name: ''},
                    {data: 'email_verify', name: '',orderable: false},
                    {data: 'phone', name: '',orderable: false},
                    {data: 'action', name: '', orderable: false, searchable: false},
                ]
            });

            
            $(document).on('click','.swal_status_change',function(e){
                e.preventDefault();
                    Swal.fire({
                    title: '{{__("Are you sure to change status?")}}',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: "{{__('Yes, change it!')}}",
                    cancelButtonText: "{{__('cancel')}}"
                    }).then((result) => {
                    if (result.isConfirmed) {
                        $(this).next().find('.swal_form_submit_btn').trigger('click');
                    }
                });
            });

            $(document).on('click','.edit_user_info_btn',function(){
                let el = $(this);
                let email = el.data('email');
                let username = el.data('username');
                let name = el.data('name');
                let phone = el.data('phone');
                let country = el.data('country');
                let city = el.data('city');
                let area = el.data('area');
                let address = el.data('address');
                let tax_number = el.data('tax_number');
                let user_type_check = el.data('user_type');

                let form = $('#user_info_modal_form');
                form.find('#edit_email').val(email);
                form.find('#edit_username').val(username);
                form.find('#edit_name').val(name);
                form.find('#edit_phone').val(phone);
                form.find('#edit_country').val(country);
                form.find('#edit_city').val(city);
                form.find('#edit_area').val(area);
                form.find('#edit_address').val(address);

                // if seller tax not null show
                if(user_type_check === 1){
                    $(".tax_number_option").hide();
                    form.find('#tax_number').val('');
                }
                if(user_type_check === 0){
                    $(".tax_number_option").show();
                    form.find('#tax_number').val(tax_number);
                }


                var image = el.data('image');
                var imageid = el.data('imageid');

                if (imageid != '') {
                    form.find('.media-upload-btn-wrapper .img-wrap').html('<div class="attachment-preview"><div class="thumbnail"><div class="centered">' +
                        '<img class="avatar user-thumb" src="' + image + '" > </div></div></div>');
                    form.find('.media-upload-btn-wrapper input').val(imageid);
                    form.find('.media-upload-btn-wrapper .media_upload_form_btn').text('Change Image');
                }
            });

            // change country and get city
            $('#edit_country').on('change', function() {
                var country_id = $(this).val();
                $.ajax({
                    method: 'post',
                    url: "{{ route('admin.user.country.city') }}",
                    data: {
                        country_id: country_id
                    },
                     success: function(res) {
                        if (res.status == 'success') {
                            var alloptions = "<option value=''>{{__('Select City')}}</option>";
                            var allCity = res.cities;
                            $.each(allCity, function(index, value) {
                                alloptions += "<option value='" + value.id +
                                    "'>" + value.service_city + "</option>";
                            });
                            $(".get_service_city").html(alloptions);
                        }
                    }
                })
            })

            // select city and area
             $('#edit_city').on('change', function() {
                 var city_id = $(this).val();
                 $.ajax({
                     method: 'post',
                     url: "{{ route('admin.user.city.area') }}",
                     data: {
                         city_id: city_id
                     },
                     success: function(res) {
                         if (res.status == 'success') {
                             var alloptions = "<option value=''>{{__('Select Area')}}</option>";
                             var allArea = res.areas;
                             $.each(allArea, function(index, value) {
                                 alloptions += "<option value='" + value.id +
                                     "'>" + value.service_area + "</option>";
                             });
                             $(".get_service_area").html(alloptions);
                         }
                     }
                 })
             })

            $(document).on('click','.change_password_modal_btn',function(){
                let el = $(this);
                let email = el.data('email');
                let form = $('#change_password_modal');
                form.find('#user_new_password_email').val(email);
            });

            $(document).on('click','#bulk_delete_btn',function (e) {
                e.preventDefault();
                var bulkOption = $('#bulk_option').val();
                var allCheckbox =  $('.bulk-checkbox:checked');
                var allIds = [];
                allCheckbox.each(function(index,value){
                    allIds.push($(this).val());
                });
                if(allIds != '' && bulkOption == 'delete'){
                    $(this).text('{{__('Deleting...')}}');
                    $.ajax({
                        'type' : "POST",
                        'url' : "{{route('admin.all.frontend.user.bulk.action')}}",
                        'data' : {
                            _token: "{{csrf_token()}}",
                            ids: allIds
                        },
                        success:function (data) {
                            if (data.blocked && data.blocked.length > 0) {
                                let blockedMsg = data.message + "\n\n" + data.blocked.map(function(b) {
                                    return b.name + ' (' + b.email + '): ' + b.reasons.join(', ');
                                }).join("\n");
                                Swal.fire({
                                    title: "{{__('Bulk Action Notice')}}",
                                    text: blockedMsg,
                                    icon: "warning"
                                }).then(function() { location.reload(); });
                            } else {
                                location.reload();
                            }
                        },
                        error: function(err) {
                            Swal.fire({
                                title: "{{__('Error')}}",
                                text: err.responseJSON ? err.responseJSON.message : "{{__('Bulk operation failed')}}",
                                icon: "error"
                            });
                        }
                    });
                }

            });

            var currentDeleteExpectedEmail = '';

            $(document).on('click', '.review_delete_user_btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                var userId = btn.data('id');

                var modal = $('#delete_user_impact_modal');
                var form = $('#delete_user_confirmed_form');
                form.attr('action', "{{ url('/admin-home/frontend/delete-user') }}/" + userId);

                $('#delete_modal_loading').show();
                $('#delete_modal_content').hide();
                $('#confirm_delete_email_input').val('');
                $('#confirm_delete_submit_btn').prop('disabled', true);
                modal.modal('show');

                $.ajax({
                    url: "{{ url('/admin-home/frontend/delete-preview') }}/" + userId + "?lang={{ app()->getLocale() }}",
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        if (res.status !== 'success' || !res.preview) {
                            Swal.fire("{{__('Error')}}", "{{__('Failed to calculate deletion impact.')}}", 'error');
                            modal.modal('hide');
                            return;
                        }

                        var p = res.preview;
                        var acc = p.account;
                        currentDeleteExpectedEmail = (acc.email || '').trim().toLowerCase();

                        $('#preview_user_name_badge').text(acc.name);
                        $('#preview_user_id').text('#' + acc.id);
                        $('#preview_user_username').text(acc.username);
                        $('#preview_user_email').text(acc.email);
                        $('#delete_target_expected_email').text(acc.email);

                        var statusText = (p.localized && p.localized.status) ? p.localized.status : (acc.status === 'active' ? "{{__('Active')}}" : "{{__('Inactive')}}");
                        $('#preview_user_status').html(acc.status === 'active' ? '<span class="badge badge-success">' + statusText + '</span>' : '<span class="badge badge-secondary">' + statusText + '</span>');

                        var roleBadgeClass = acc.role === 'seller' ? 'badge-primary' : 'badge-info';
                        var roleLabel = (p.localized && p.localized.role) ? p.localized.role : (acc.role === 'seller' ? "{{__('Seller')}}" : "{{__('Buyer')}}");
                        var sellerTypeLabel = acc.seller_type === 'company' ? "{{__('Company')}}" : (acc.seller_type === 'individual' ? "{{__('Individual')}}" : acc.seller_type);
                        $('#preview_user_role_badge').attr('class', 'badge ' + roleBadgeClass).text(roleLabel + (acc.seller_type !== 'n/a' ? ' (' + sellerTypeLabel + ')' : ''));

                        var modeText = (p.localized && p.localized.deletion_mode) ? p.localized.deletion_mode : (p.deletion_mode === 'purge' ? "{{__('Clean Purge')}}" : "{{__('Anonymize & Retain Ledgers')}}");
                        if (p.deletion_mode === 'purge') {
                            $('#preview_deletion_mode_badge').html('<span class="badge badge-success"><i class="ti-check"></i> ' + modeText + '</span>');
                        } else {
                            $('#preview_deletion_mode_badge').html('<span class="badge badge-warning"><i class="ti-shield"></i> ' + modeText + '</span>');
                        }

                        $('#preview_count_services').text(p.counts.services_count || 0);
                        $('#preview_count_active_orders').text(p.counts.active_orders_count || 0);
                        $('#preview_count_balance').text((p.counts.wallet_balance || 0).toFixed(2));
                        $('#preview_count_retained').text((p.counts.retained_orders_count || 0) + (p.counts.wallet_history_count || 0));

                        // Blockers
                        if (!p.can_delete && p.blocking_reasons && p.blocking_reasons.length > 0) {
                            var bList = $('#delete_modal_blockers_list');
                            bList.empty();
                            $.each(p.blocking_reasons, function(i, reason) {
                                bList.append('<li>' + reason + '</li>');
                            });
                            $('#delete_modal_blockers_box').show();
                            $('#delete_modal_confirm_wrapper').hide();
                            $('#confirm_delete_submit_btn').prop('disabled', true).text("{{__('Deletion Blocked')}}");
                        } else {
                            $('#delete_modal_blockers_box').hide();
                            $('#delete_modal_confirm_wrapper').show();
                            $('#confirm_delete_submit_btn').prop('disabled', true).html('<i class="ti-trash mr-1"></i> {{__("Confirm & Execute Deletion")}}');
                        }

                        // Lists
                        var populateList = function(listId, items) {
                            var el = $(listId).empty();
                            if (!items || items.length === 0) {
                                el.append('<li class="text-muted font-italic">{{__("None")}}</li>');
                            } else {
                                $.each(items, function(i, item) {
                                    el.append('<li>' + item + '</li>');
                                });
                            }
                        };

                        populateList('#preview_list_deleted', p.breakdown.permanently_deleted);
                        populateList('#preview_list_retained', p.breakdown.retained_records);
                        populateList('#preview_list_anonymized', p.breakdown.anonymized_fields);

                        $('#delete_modal_loading').hide();
                        $('#delete_modal_content').show();
                    },
                    error: function(err) {
                        $('#delete_modal_loading').hide();
                        Swal.fire("{{__('Error')}}", "{{__('Failed to fetch impact preview.')}}", 'error');
                        modal.modal('hide');
                    }
                });
            });

            // Unlock submit button only when typed email matches target email
            $(document).on('input keyup paste', '#confirm_delete_email_input', function() {
                var typed = $(this).val().trim().toLowerCase();
                if (currentDeleteExpectedEmail && typed === currentDeleteExpectedEmail) {
                    $('#confirm_delete_submit_btn').prop('disabled', false).removeClass('btn-secondary').addClass('btn-danger');
                } else {
                    $('#confirm_delete_submit_btn').prop('disabled', true);
                }
            });

            $(document).on('click','.send_mail_modal_btn',function(){
                    var el = $(this);
                    var email = el.data('email');
                    var form = $('#send_mail_modal_form');
                    form.find('#email').val(email);
                });

            $('.summernote').summernote({
                height: 300,   //set editable area's height
                codemirror: { // codemirror options
                    theme: 'monokai'
                },
                callbacks: {
                    onChange: function(contents, $editable) {
                        $(this).prev('input').val(contents);
                    }
                }
            });

            $('.all-checkbox').on('change',function (e) {
                e.preventDefault();
                var value = $('.all-checkbox').is(':checked');
                var allChek = $(this).parent().parent().parent().parent().parent().find('.bulk-checkbox');
                //have write code here fr
                if( value == true){
                    allChek.prop('checked',true);
                }else{
                    allChek.prop('checked',false);
                }
            });

        });
        <x-btn.update/>
    })(jQuery);
        
    </script>
    <x-media.js/>
@endsection
