@extends('backend.admin-master')
@section('site-title')
    {{__('Load more settings')}}
@endsection
@section('content')
    <div class="col-lg-12 col-ml-12 padding-bottom-30">
        <div class="row">
            <div class="col-6 mt-5">
                <x-msg.success/>
                <x-msg.error/>
                <div class="card">
                    <div class="card-body">
                        <h4 class="header-title mb-4">{{__("Load more settings")}}</h4>
                        <form action="{{route('admin.service.load.more.category.settings.update')}}" method="POST">
                            @csrf

                            <div class="form-group">
                                <label for="load_more_button_show_hide_settings"><strong>{{__('Load more Button Show/Hide')}}</strong></label>
                                <label class="switch">
                                    <input type="checkbox" name="load_more_button_show_hide_settings"  @if(!empty(get_static_option('load_more_button_show_hide_settings'))) checked @endif>
                                    <span class="slider-enable-disable"></span>
                                </label>
                                <small class="form-text text-muted">{{__('Enable, means Frontend Available Service Sub Categories Page load more button show')}}</small>
                            </div>

                            <button type="submit" id="update" class="btn btn-primary mt-4 pr-4 pl-4">{{__('Update Changes')}}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection