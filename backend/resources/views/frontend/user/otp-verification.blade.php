@extends('frontend.frontend-master')
@section('page-meta-data')
    <title>{{__('Verify Account')}}</title>
@endsection
@section('style')
    <style>
        .timer {
            font-size: 16px;
            text-align: center;
            margin-bottom: 20px;
        }
        .timer #counter {
            font-weight: bold;
        }
    </style>
@endsection
@section('content')
    <div class="signup-area padding-top-70 padding-bottom-100">
        <div class="container">
            <div class="signup-wrapper">
                <div class="signup-contents">
                    <h3 class="signup-title"> {{ __('Verify Your Account')}} </h3>
                    @php
                         $twilioSid = env('TWILIO_SID');
                         $twilioAuthToken = env('TWILIO_AUTH_TOKEN');
                         $twilioNumber = env('TWILIO_NUMBER');
                         // Check if Twilio credentials are empty
                         if (empty($twilioSid) || empty($twilioAuthToken) || empty($twilioNumber)) {
                             $opt_empty_message = false;
                         }else{
                             $opt_empty_message = true;
                         }
                    @endphp

                      @if($opt_empty_message === false)
                        <div class="alert alert-danger alert-dismissible fade show mt-5 mb-1" role="alert">
                            {{ __('Oops! It appears that we are currently unable to send verification codes. Please try again later or get help from admin.') }}
                        </div>
                    @else
                        <div class="alert alert-info alert-dismissible fade show mt-5 mb-1" role="alert">
                            {{__('OTP has been sent on Your Phone Number.')}}
                        </div>
                     @endif

                    <div class="mt-2">
                        <x-session-msg/>
                        <x-msg.error/>
                    </div>

                    @if($opt_empty_message === false)
                    <div class="timer">  </div>
                    @else
                        <div class="timer">
                            <span id="counter">{{ __('00:00') }}</span> <br>
                            <small id="counter">{{ __('OTP Expire Time') }}</small> <br>
                        </div>
                    @endif

                    <form class="signup-forms"  @if(!empty($user_details))  action="{{ route('user.login.with.otp.code')}}"  @else  action="{{ route('email.verify')}}"  @endif  method="post">
                        @csrf
                        @if(empty($user_details))
                            <input type="hidden" name="user_id" value="{{$user_id}}" />
                        @else
                            <input type="hidden" name="user_id" value="{{$user_details->id}}" />
                        @endif

                        <div class="single-signup margin-top-30">
                            <label class="signup-label"> {{__('Enter OTP Code')}} <span class="text-danger">*</span> </label>
                            <input id="check_opt_send_login" type="hidden" name="check_opt_send_login"  value="">
                            <input id="otp_code" type="number" class="form-control" name="otp_code" value="{{ old('otp_code') }}"  placeholder="{{ __('Enter OTP') }}">
                        </div>
                        <button type="submit" class="otpCodeCheck">{{ __('Verify Account') }}</button>
                    </form>



                    <div class="resend-verify-code-wrap">
                        <span>{{ __('Did not you receive any code?') }}</span>
                        <strong>
                            <a class="text-center"
                               @if(empty($user_details))
                                   href="{{ route('user.resent.otp', $user_id) }}"
                               @else href="{{ route('user.resent.otp.login', $user_details->id) }}" @endif > {{ __('Resend Code') }}</a>
                        </strong>
                    </div>
                </div>
                <br>

            </div>
        </div>
    </div>
@endsection
@section('scripts')
    @php
    if(empty($user_details)){
         $user_otp_time = \App\User::select('id', 'otp_expire_at', 'otp_code')->findOrFail($user_id);
    }else{
         $user_otp_time = \App\User::select('id', 'otp_expire_at', 'otp_code')->findOrFail($user_details->id);
    }

    if(request()->isMethod('post')) {
        $input_otp_code = request()->input('otp_code');
    }else{
        $input_otp_code = 0;
    }

     // if current time is otp time is big
     $current_time = \Carbon\Carbon::now();
     $opt_time = \Carbon\Carbon::parse($user_otp_time->otp_expire_at);
     if ($current_time < $opt_time){
         $otp_countdown_start = 1;
     }elseif($user_otp_time->otp_code != $input_otp_code){
         $otp_countdown_start = 0;
     }else{
         $otp_countdown_start = 0;
     }
    @endphp
    <script type="text/javascript">
        "use strict";
        $(document).ready(function () {

            // opt time count
                function countdown() {
                    var seconds = 0;
                    var counter = $("#counter");
                    var timer;

                    // Function to update the countdown display
                    function updateCounterDisplay() {
                        var minutes = Math.floor(seconds / 60);
                        var remainingSeconds = seconds % 60;
                        counter.text(
                            (minutes < 10 ? "0" : "") + minutes + ":" + (remainingSeconds < 10 ? "0" : "") + remainingSeconds
                        );
                    }

                    // Function to start the countdown
                    function startCountdown() {
                        timer = setInterval(function () {
                            if (seconds > 0) {
                                seconds--;
                                updateCounterDisplay();
                            } else {
                                clearInterval(timer);
                                counter.text("00:00");
                            }
                        }, 1000);
                    }

                    // Check if the countdown was previously started and has remaining seconds stored in cookies
                    var remainingSecondsCookie = getCookie("remainingSeconds");
                    if (remainingSecondsCookie) {
                        seconds = parseInt(remainingSecondsCookie);
                        updateCounterDisplay();
                    } else {
                        // Replace this part with your own logic to set initial seconds based on your requirements
                        @if ($otp_countdown_start == 1)
                                @if (!empty(get_static_option("user_otp_expire_time")))
                                @if (get_static_option("user_otp_expire_time") == "30")
                            seconds = 30;
                        @else
                            seconds = {{ get_static_option("user_otp_expire_time") }} * 60;
                        @endif
                                @else
                            seconds = 60;
                        @endif
                        @endif
                    }

                    startCountdown();

                    // Save remaining seconds in cookies before page reload
                    $(window).on("beforeunload", function () {
                        setCookie("remainingSeconds", seconds, 1); // Expiry set to 1 day
                    });
                }

                // Helper function to set a cookie
                function setCookie(name, value, days) {
                    var expires = "";
                    if (days) {
                        var date = new Date();
                        date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
                        expires = "; expires=" + date.toUTCString();
                    }
                    document.cookie = name + "=" + (value || "") + expires + "; path=/";
                }

                // Helper function to get a cookie value
                function getCookie(name) {
                    var nameEQ = name + "=";
                    var ca = document.cookie.split(";");
                    for (var i = 0; i < ca.length; i++) {
                        var c = ca[i];
                        while (c.charAt(0) === " ") c = c.substring(1, c.length);
                        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
                    }
                    return null;
                }

                // Helper function to erase a cookie
                function eraseCookie(name) {
                    document.cookie = name + "=; Max-Age=-99999999;";
                }

                countdown();

           // if opt code is empty
            $(document).on('submit', '.signup-forms', function(e) {
                var otpCode = $("#otp_code").val();
                if (otpCode === ""){
                    //error msg
                    Command: toastr["error"]("{{__('OTP code is required')}}","{{__('Warning')}}")
                    toastr.options = {
                        "closeButton": true,
                        "debug": false,
                        "newestOnTop": false,
                        "progressBar": true,
                        "positionClass": "toast-top-right",
                        "preventDuplicates": false,
                        "onclick": null,
                        "showDuration": "300",
                        "hideDuration": "1000",
                        "timeOut": "5000",
                        "extendedTimeOut": "1000",
                        "showEasing": "swing",
                        "hideEasing": "linear",
                        "showMethod": "fadeIn",
                        "hideMethod": "fadeOut"
                    }
                    return false;
                }
            });

        });
    </script>
@endsection