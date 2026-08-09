@extends('backend.admin-master')
@section('site-title')
    {{__('Add Notice')}}
@endsection
@section('content')
    <div class="col-lg-12 col-ml-12 padding-bottom-30">
        <div class="row">
            <div class="col-lg-12">
                <div class="margin-top-40"></div>
                <x-msg.success/>
                <x-msg.error/>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="header-wrap d-flex justify-content-between">
                            <div class="left-content">
                                <h3 class="header-title">{{__('All Notices')}}   </h3>
                            </div>
                            <div class="header-title d-flex">
                                <div class="btn-wrapper-inner">
                                    <a href="{{ route('admin.all.notice') }}" class="btn btn-primary">{{__('All Notices')}}</a>
                                </div>
                            </div>
                        </div>

                        <form action="{{route('admin.add.notice')}}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <label for="title">{{__('Title')}} <span class="text-danger">*</span> </label>
                                <input type="text" class="form-control" name="title" id="title"  placeholder="{{__('Title')}}">
                            </div>

                            <div class="form-group">
                                <label>{{__('Notice Description')}}</label>
                                <textarea name="description" class="form-control max-height-150" cols="20" rows="5"></textarea>
                            </div>

                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">

                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <div class="category-section">
                                                <label for="notice_type" class="notice-type-label"><strong>{{__('Select Notice Type')}}</strong> <span class="text-danger">*</span> </label>
                                                <select name="notice_type" id="notice_type" class="form-control">
                                                    <option value="">{{ __('Select Notice Type') }}</option>
                                                    <option value="1">{{ __('Error') }}</option>
                                                    <option value="2">{{ __('Warning') }}</option>
                                                    <option value="3">{{ __('Success') }}</option>
                                                    <option value="4">{{ __('Info') }}</option>
                                                </select>
                                                <div class="notice-descriptions" style="display: grid">
                                                    <small class="text-danger"> <span class="notice-error">{{__('Error')}}</span>- {{__('–Displays the message with a red background')}}</small>
                                                    <small class="text-warning"> <span class="notice-warning">{{__('Warning')}}</span>– {{__('Displays the message with a yellow/orange background')}}</small>
                                                    <small class="text-success"> <span class="success">{{__('Success')}}</span>– {{__('Displays the message with a green background')}}</small>
                                                    <small class="text-info"> <span class="">{{__('Info')}}</span>– {{__('Displays the message with a blue background')}}</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="category-section">
                                                <label for="notice_for" class="notice_for-label"><strong>{{__('Select Notice for')}}</strong> <span class="text-danger">*</span> </label>
                                                <select name="notice_for" id="notice_for" class="form-control">
                                                    <option value="">{{ __('Select Notice for') }}</option>
                                                    <option value="1">{{ __('Frontend') }}</option>
                                                    <option value="2">{{ __('Buyer Dashboard') }}</option>
                                                    <option value="3">{{ __('Seller Dashboard') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <hr>


                                        <div class="form-group ">
                                            <label for="expire_date">{{__('Expire Date')}} <span class="text-danger">*</span> </label>
                                            <input type="date" name="expire_date" placeholder="{{ __('Select Date') }}" class="form-control mt-2 date" id="expire_date">
                                        </div>

                                        <div class="form-group ">
                                            <label for="status">{{__('Status')}}</label>
                                            <select name="status" class="form-control" id="status">
                                                <option value="1">{{__("Active")}}</option>
                                                <option value="0">{{__("Inactive")}}</option>
                                            </select>
                                        </div>

                                        <div class="submit_btn mt-5">
                                            <button type="submit" class="btn btn-success pull-right">{{__('Submit')}}</button>
                                        </div>

                                    </div>
                                </div>
                             </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
@section('script')
    <script>
        //Date Picker
        flatpickr('#expire_date', {
            enableTime: false,
            dateFormat: "Y-m-d",
            minDate: "today"
        });
    </script>
@endsection