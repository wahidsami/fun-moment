@extends('frontend.user.buyer.buyer-master')
@section('site-title')
    {{__('Buyer Account Settings')}}
@endsection
@section('content')
    <x-frontend.seller-buyer-preloader/>
    @include('frontend.user.buyer.partials.sidebar-two')
    <div class="dashboard__right">
        @include('frontend.user.buyer.header.buyer-header')
        <div class="dashboard__body">
            <div class="dashboard__inner">
                <!-- Buyer Account settings section start-->
                <div class="dashboard_accountSettings">
                    <x-error-message/>
                    <div class="dashboard__inner__item dashboard_border padding-20 radius-10 bg-white">
                        <div class="dashboard_accountSettings__item">
                            <div class="dashboard_accountSettings__flex">
                                <div class="dashboard_accountSettings__left">
                                    <h4 class="dashboard_accountSettings__title">{{ __('Change Password') }}</h4>
                                    <p class="dashboard_accountSettings__para mt-2">{{ __('Last changed') }}
                                        @if(Auth::guard('web')->user()->password_changed_at)
                                            {{ \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', Auth::guard('web')->user()->password_changed_at)->diffForHumans() }}
                                        @endif
                                    </p>
                                </div>
                                <div class="dashboard_accountSettings__btn">
                                    <div class="btn-wrapper" data-bs-toggle="modal" data-bs-target="#changePassword">
                                        <a href="javascript:void(0)" class="dashboard_table__title__btn btn-bg-1 radius-5">{{ __('Change Password') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="dashboard_accountSettings__item">
                            <div class="dashboard_accountSettings__flex">
                                <div class="dashboard_accountSettings__left">
                                    <h4 class="dashboard_accountSettings__title"> {{ __('Deactivate/Delete account') }} </h4>
                                    <p class="dashboard_accountSettings__para mt-2">
                                        @if(!empty($user))
                                            @if($user->status === 0)
                                                {{ __('Currently your account is deactivated. You can activate from here.') }}
                                            @elseif($user->status === 1)
                                                {{ __('Your account has been deleted') }}
                                            @endif
                                        @else
                                            {{ __('You can deactivate your account temporarily or Delete permanently') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="dashboard_accountSettings__btn btn_flex">

                                    @if(empty($user))
                                        <a href="javascript:void(0)" class="dashboard_table__title__btn btn-bg-deactivate radius-5"
                                           data-bs-toggle="modal"
                                           data-bs-target="#deactivateAccount"
                                        >{{ __('Deactivate') }}</a>
                                    @endif

                                    <a href="javascript:void(0)" class="dashboard_table__title__btn btn-outline-delete hover_danger radius-5"
                                       data-bs-toggle="modal" data-bs-target="#deleteAccount">{{ __('Delete') }}</a>


                                    @if(!empty($user))
                                        @if($user->status === 0)
                                            <a href="{{route('buyer.account.deactive.cancel',$user->user_id)}}" class="dashboard_table__title__btn btn-bg-deactivate radius-5">{{__('Activate Your Account')}}</a>
                                        @elseif($user->status === 1)
                                            <a href="javascript:void(0)" class="dashboard_table__title__btn btn-bg-deactivate radius-5">{{__('Already Delete Account')}}</a>
                                        @endif
                                    @endif

                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- buyer Account settings section End-->
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePassword" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('Change Password') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="custom-form">
                        <form action="{{route('seller.account.settings')}}" method="post">
                            @csrf
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="CurrentPassword" class="label_title__postition">{{ __('Current password') }} <span class="text-danger">*</span> </label>
                                        <input class="form--control radius-10" type="password" name="current_password" id="current_password" placeholder="{{__('Type Current Password')}}">
                                        <span class="toggle_password"><i class="fa fa-eye-slash"></i></span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="newPassword" class="label_title__postition">{{ __('Type New password') }} <span class="text-danger">*</span> </label>
                                        <input class="form--control radius-10" type="password" name="new_password" id="new_password" placeholder="{{__('Type New Password')}}">
                                        <span class="toggle_password"><i class="fa fa-eye-slash"></i></span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="reTypePassword" class="label_title__postition">{{ __('Retype new password') }} <span class="text-danger">*</span> </label>
                                        <input class="form--control radius-10" type="password" name="confirm_password" id="confirm_password" placeholder="{{__('Retype Password')}}">
                                        <span class="toggle_password"><i class="fa fa-eye-slash"></i></span>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">{{ __('Change Password') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <!-- Deactivate Account Modal -->
    <div class="modal fade" id="deactivateAccount" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('Deactivate Account') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="custom-form">
                        <form action="{{route('buyer.account.deactive')}}" method="post">
                            @csrf

                            <div class="row">
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="CurrentPassword" class="label_title__postition">{{ __('Deactivation reason') }} <span class="text-danger">*</span> </label>
                                        <div class="single-input-select radius-10">
                                            <select class="select2_activation" name="reason" id="reason2">
                                                <option value="For Vacation">{{__('For Vacation')}}</option>
                                                <option value="Personal Reasons">{{__('Personal Reasons')}}</option>
                                                <option value="Others">{{__('Others')}}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mt-3">
                                    <div class="single-input">
                                        <label for="newPassword" class="label_title__postition">{{ __('Describe') }} <span class="text-danger">*</span> </label>
                                        <textarea class="form--control radius-10"  name="description" id="description" cols="30" rows="4"
                                                  placeholder="{{ __('e.g. explain why you are deactivating') }}"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Deactivate Now') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccount" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('Delete account') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="custom-form">
                        <form action="{{ route('buyer.account.delete') }}" method="post">
                            @csrf
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="dashboard_accountDelete">
                                        <p class="dashboard_accountDelete__reason">{{ __('Account deletion is permanent') }}</p>
                                        <p class="dashboard_accountDelete__reason">{{ __('We remove all your data') }}</p>
                                        <p class="dashboard_accountDelete__reason">{{ __('You can’t log in to this account anymore') }}</p>
                                        <p class="dashboard_accountDelete__reason">{{ __('Any services that are currently on progress will be suspended') }}</p>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="CurrentPassword" class="label_title__postition">{{ __('Delete reason') }} <span class="text-danger">*</span> </label>
                                        <div class="single-input-select radius-10">
                                            <select class="select2_activation" name="reason" id="reason">
                                                <option value="For Vacation">{{__('For Vacation')}}</option>
                                                <option value="Personal Reasons">{{__('Personal Reasons')}}</option>
                                                <option value="Others">{{__('Others')}}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="single-input">
                                        <label for="newPassword" class="label_title__postition">{{ __('Describe') }} <span class="text-danger">*</span> </label>
                                        <textarea class="form--control radius-10"  name="description" id="description" cols="30" rows="4"
                                                  placeholder="{{ __('e.g. explain why you are deactivating') }}"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Delete Now') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        (function($){
            "use strict";
            $(document).ready(function(){

                // modal close
                $('.close').on('click', function (){
                    $('#media_upload_modal').modal('hide');
                });

                $('#reason').select2({
                    dropdownParent: $('#deleteAccount')
                });
                $('#reason2').select2({
                    dropdownParent: $('#deactivateAccount')
                });

            });
        })(jQuery);
    </script>
@endsection

