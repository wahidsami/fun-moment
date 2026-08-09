@extends('backend.admin-master')
@section('site-title')
    {{__('Service Reviews')}}
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
                                <h4 class="header-title">{{__('Service Reviews')}}  </h4>
                            </div>
                        </div>
                        <div class="table-wrap table-responsive">
                            <table class="table table-default">
                                <thead>
                                <th>{{__('Order ID')}}</th>
                                <th>{{__('Buyer Name')}}</th>
                                <th>{{__('Seller Name')}}</th>
                                <th>{{__('Review Rating')}}</th>
                                <th>{{__('Review Message')}}</th>
                                <th>{{__('Action')}}</th>
                                </thead>
                                <tbody>
                                @foreach($service_reviews as $data)
                                    <tr>
                                        <td>{{$data->order_id}}</td>
                                        <td>{{optional($data->buyer)->name}}</td>
                                        <td>{{optional($data->seller)->name}}</td>
                                        <td>{{$data->rating}}</td>
                                        <td width="45%">{{$data->message}}</td>
                                        <td>
                                                <a href="#"
                                                   data-toggle="modal"
                                                   data-target="#reviewModal"
                                                   data-id="{{ $data->id }}"
                                                   data-rating="{{ $data->rating }}"
                                                   data-message="{{  $data->message }}"
                                                   class="review_add_modal btn btn-primary btn-xs mb-3 mr-1"
                                                >
                                                <span class="icon eye-icon" data-toggle="tooltip" data-placement="top" title="{{ __('Edit') }}"> <i class="ti-pencil"></i> </span>
                                                </a>
                                               <x-delete-popover :url="route('admin.service.review.delete',$data->id)"/>
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

    <!--Status Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1" role="dialog" aria-labelledby="editModal"
         aria-hidden="true">
        <form action="{{ route('admin.service.review.add') }}" method="post" id="review_add_form">
            @csrf
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModal">{{ __('Edit Review') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="comments-flex-item">
                            <div class="single-commetns" style="font-size: 1em;">
                                <label class="comment-label"> {{ __('Ratings*') }} </label>
                                <div id="review"></div>
                            </div>
                            <div class="single-commetns">
                                <label class="comment-label" for="star_input">{{ __('Stars') }}</label>
                                <input type="text" readonly id="rating" name="rating" class="form-control form-control-sm">
                            </div>

                            <input type="hidden" id="id" name="id" class="form-control form-control-sm">
                        </div>
                        <div class="form-group">
                            <label class="payout-request-note d-block pt-4" for="amount">{{ __('Comments') }}</label>
                            <textarea rows="5" name="message" class="form-control form--message" id="message" placeholder="{{ __('Post Comments') }}">  </textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

@endsection

@section('script')
    <x-datatable.js/>
    <script src="{{ asset('assets/frontend/js/rating.js') }}"></script>
    <script type="text/javascript">
        (function(){
            "use strict";
            $(document).ready(function(){
                $(document).on('click','.report_description',function(e){
                    let service_description = $(this).data('service_description');
                    $('#service_description').text(service_description);
                });

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

                // review start
                $(document).on('click', '.review_add_modal', function () {
                    let el = $(this);
                    let id = el.data('id');
                    let rating = el.data('rating');
                    let message = el.data('message');

                    let form = $('#review_add_form');
                    form.find('#id').val(id);
                    form.find('#rating').val(rating);
                    form.find('#message').val(message);

                    $("#review").rating({
                        "value": rating,
                        "click": function (e) {
                            $("#rating").val(e.stars);
                        }
                    });

                });
                // review end


            });
        })(jQuery);
    </script>
@endsection
