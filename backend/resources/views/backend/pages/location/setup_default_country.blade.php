@extends('backend.admin-master')

@section('site-title')
    {{__('Setup default country')}}
@endsection
@section('content')
    <div class="col-lg-6 col-ml-12 padding-bottom-30">
        <div class="row">
            <div class="col-lg-12">
                <div class="margin-top-40"></div>
                <x-msg.success/>
                <x-msg.error/>
            </div>
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="header-wrap d-flex justify-content-between">
                            <div class="left-content">
                                <h4 class="header-title">{{__('Setup default country')}} </h4>
                            </div>
                        </div>
                        <form action="{{route('admin.country.settings.update')}}" method="post">
                            @csrf
                            <div class="form-group">
                                 <span class="text-danger">
                                    {{ __('Notes: If you designate a country as the default, it automatically assumes an active status, while all other countries, cities, and areas are  inactive status, hidden from the frontend.') }}
                                </span> <br>
                                <label class="mt-2">{{__('Select Default Country')}}</label>
                                <select name="country_id" class="form-control">
                                    <option value="">{{ __('Select Default Country') }}</option>
                                    @foreach($countries as $country)
                                    <option value="{{ $country->id }}" @if($countries_count === 1 && $country->status === 1) selected @endif> {{ $country->country }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary mt-3 submit_btn">{{__('Submit')}}</button>
                        </form>
                   </div>
                </div>
            </div>
        </div>
    </div>
@endsection