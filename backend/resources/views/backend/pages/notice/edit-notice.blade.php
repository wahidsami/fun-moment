@extends('backend.admin-master')
@section('site-title')
    {{__('Edit Notice')}}
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

                        <form action="{{route('admin.notice.update')}}" method="post" enctype="multipart/form-data">
                            @csrf

                            <input type="hidden" name="notice_id" value="{{ $notice->id }}">
                            <div class="form-group">
                                <label for="title">{{__('Title')}} <span class="text-danger">*</span> </label>
                                <input type="text" class="form-control" name="title" id="title" value="{{ $notice->title }}" placeholder="{{__('Title')}}">
                            </div>

                            <div class="form-group">
                                <label>{{__('Notice Description')}}</label>
                                <textarea name="description" id="description" class="form-control max-height-150" cols="30"  rows="10">{{ $notice->description }}</textarea>
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
                                                @php
                                                    $noticeTypes = [
                                                        'Error' => '1',
                                                        'Warning' => '2',
                                                        'Success' => '3',
                                                        'Info' => '4',
                                                    ];
                                                @endphp

                                                <select name="notice_type" id="notice_type" class="form-control">
                                                    <option value="">{{ __('Select Notice Type') }}</option>
                                                    @foreach ($noticeTypes as $label => $value)
                                                        <option value="{{ $value }}" @if($value == $notice->notice_type) selected @endif>{{ __($label) }}</option>
                                                    @endforeach
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
                                                @php
                                                    $noticeForOptions = [
                                                        'Frontend' => '1',
                                                        'Buyer Dashboard' => '2',
                                                        'Seller Dashboard' => '3',
                                                    ];
                                                @endphp
                                                <select name="notice_for" id="notice_for" class="form-control">
                                                    <option value="">{{ __('Select Notice for') }}</option>
                                                    @foreach ($noticeForOptions as $label => $value)
                                                        <option value="{{ $value }}" @if($value == $notice->notice_for) selected @endif>{{ __($label) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <hr>

                                        <div class="form-group ">
                                            <label for="expire_date">{{__('Expire Date')}} <span class="text-danger">*</span> </label>
                                            <input type="text" name="expire_date" value="{{ $notice->expire_date }}"
                                                   placeholder="{{ __('Select Date') }}" class="form-control mt-2 date" id="expire_date">
                                        </div>

                                        <div class="form-group ">
                                            <label for="status">{{__('Status')}}</label>
                                            <select name="status" class="form-control" id="status">
                                                <option value="1" @if($notice->status == 1) selected @endif>{{__("Active")}}</option>
                                                <option value="0" @if($notice->status == 0) selected @endif>{{__("Inactive")}}</option>
                                            </select>
                                        </div>

                                        <div class="submit_btn mt-5">
                                            <button type="submit" class="btn btn-success pull-right">{{__('Update')}}</button>
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
            dateFormat:  "Y-m-d",
        });
    </script>
@endsection