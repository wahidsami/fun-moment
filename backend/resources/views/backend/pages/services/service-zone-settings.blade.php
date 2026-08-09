@extends('backend.admin-master')
@section('site-title')
    {{__('Google Map Settings')}}
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
                <form action="{{route('admin.service.zone.settings.update')}}" method="post">
                    @csrf
                <div class="card">
                    <div class="card-body">
                        <div class="header-wrap d-flex justify-content-between">
                            <div class="left-content">
                                <h4 class="header-title">{{__('Google Map Settings')}} </h4>
                            </div>
                        </div>
                            <div class="tab-content margin-top-40">
                                <div class="form-group">
                                    <label for="service_google_map_api_key">{{__('Google Map Api Key')}}</label>
                                    <input type="text" class="form-control" name="service_google_map_api_key" id="service_google_map_api_key"
                                           value="{{ get_static_option('service_google_map_api_key') }}" placeholder="{{__('google map api key')}}">
                                </div>

                            </div>
                    </div>
                </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="tab-content marsgin-top-40">
                                <div class="form-group">
                                    <label for="google_map_settings">{{__('Google Map NO/OFF')}}</label>
                                    <label class="switch">
                                        <input type="checkbox" name="google_map_settings"  @if(!empty(get_static_option('google_map_settings'))) checked @endif id="google_map_settings">
                                        <span class="slider-enable-disable"></span>
                                    </label>
                                    <small class="form-text text-muted">{{__('Disable, means google map off your system.')}}</small>
                                </div>

                                <div class="form-group">
                                    <label for="google_map_search_placeholder_title">{{__('Google Map Search Placeholder Title')}}</label>
                                    <input type="text" name="google_map_search_placeholder_title"  class="form-control"
                                           placeholder="{{ __('Search Placeholder Title') }}"
                                           value="{{get_static_option('google_map_search_placeholder_title')}}">
                                </div>

                                <div class="form-group">
                                    <label for="google_map_search_button_title">{{__('Google Map Search Button Title')}}</label>
                                    <input type="text" name="google_map_search_button_title"  class="form-control" placeholder="{{ __('Search Button Title') }}"
                                           value="{{get_static_option('google_map_search_button_title')}}">
                                </div>

                                @php
                                    $all_pages = App\Page::select('id','title','slug')->latest()->get();
                                @endphp
                                <div class="form-group">
                                    <label for="select_home_page_search_service_page_url">{{__('Select the Page for home page search service list display')}}</label>
                                    <select name="select_home_page_search_service_page_url" id="select_home_page_search_service_page_url" class="form-control">
                                        <option value="">{{ __('Select Page') }}</option>
                                        @foreach($all_pages as $page)
                                            <option @if(get_static_option('select_home_page_search_service_page_url') == $page->slug ) selected @endif value="{{ $page->slug }}">{{ $page->title }}</option>
                                        @endforeach
                                    </select>
                                </div>


                                <button type="submit" class="btn btn-primary mt-3 submit_btn">{{__('Submit')}}</button>
                            </div>
                        </div>
                    </div>

                  </form>
                 </div>
        </div>
    </div>
@endsection


