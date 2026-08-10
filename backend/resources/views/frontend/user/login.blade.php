@extends('frontend.frontend-master')

@section('page-meta-data')
    <title>{{ __('User Login') }}</title>
@endsection

@section('content')
    <section class="fm-hero">
        <div class="container">
            <div class="hero-shell">
                <div class="row align-items-center g-4">
                    <div class="col-lg-6">
                        <span class="hero-badge">
                            <i class="las la-sign-in-alt"></i>
                            {{ __('Secure access to the live FUN MOMENT platform') }}
                        </span>
                        <h1 class="hero-title">{{ __('Welcome back to your dashboard, bookings, and provider tools.') }}</h1>
                        <p class="hero-subtitle">
                            {{ __('Sign in with your real account to continue browsing services, managing orders, and using the same backend that powers the mobile app and admin panel.') }}
                        </p>
                        <div class="hero-actions">
                            <a href="{{ route('user.register') }}" class="fm-btn">{{ __('Create an account') }}</a>
                            <a href="{{ route('homepage') }}" class="btn-outline-1">{{ __('Return to home') }}</a>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fm-glass-card p-4 p-lg-5">
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <img src="{{ asset('logo.png') }}" alt="{{ get_static_option('site_title') ?? __('FUN MOMENT') }}" style="width:72px;height:72px;object-fit:contain;">
                                <div>
                                    <h2 class="mb-1">{{ get_static_option('login_form_title') ?? __('Login to your account') }}</h2>
                                    <p class="mb-0 fm-muted">{{ __('Use your username, email, or OTP flow depending on the site settings.') }}</p>
                                </div>
                            </div>

                            @if(Session::has('msg'))
                                <p class="alert alert-{{ Session::get('type') ?? 'success' }}">{{ Session::get('msg') }}</p>
                            @endif
                            <div class="error-message"></div>

                            <form class="signup-forms" method="post">
                                @csrf
                                <div class="single-signup">
                                    <label class="signup-label">{{ __('Username or Email *') }}</label>
                                    <input class="form--control" type="text" name="username" id="username" placeholder="{{ __('Username Or Email') }}">
                                </div>

                                @if(empty(get_static_option('disable_user_otp_verify')))
                                    <div class="text-success mt-2">
                                        <a href="{{ route('user.login.set.phone.number') }}"><strong id="loginWithOtp">{{ __('Login with OTP') }}</strong></a>
                                    </div>
                                @endif

                                <div class="single-signup">
                                    <label class="signup-label">{{ __('Password *') }}</label>
                                    <input class="form--control" type="password" name="password" id="password" placeholder="{{ __('Password') }}">
                                </div>

                                <div class="signup-checkbox d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="checkbox-inlines">
                                        <input class="check-input" name="remember" id="remember" type="checkbox">
                                        <label class="checkbox-label" for="remember">{{ __('Remember me') }}</label>
                                    </div>
                                    <div class="forgot-btn">
                                        <a href="{{ route('user.forget.password') }}" class="forgot-pass">{{ __('Forgot Password') }}</a>
                                    </div>
                                </div>

                                <button id="signin_form" type="submit" class="w-100 mt-4">{{ __('Login Now') }}</button>

                                <div class="text-center mt-4">
                                    <span class="fm-muted">{{ __('Do not have an account?') }}</span>
                                    <a class="resgister-link" href="{{ route('user.register') }}">{{ __('Register') }}</a>
                                </div>
                            </form>

                            <div class="social-login-wrapper mt-4">
                                @if(get_static_option('enable_google_login') || get_static_option('enable_facebook_login'))
                                    <div class="bar-wrap">
                                        <span class="bar"></span>
                                        <p class="or">{{ __('or') }}</p>
                                        <span class="bar"></span>
                                    </div>
                                @endif

                                <div class="sin-in-with">
                                    @if(get_static_option('enable_google_login'))
                                        <a href="{{ route('login.google.redirect') }}" class="sign-in-btn">
                                            <img src="{{ asset('assets/frontend/img/static/google.png') }}" alt="icon">
                                            {{ __('Sign in with Google') }}
                                        </a>
                                    @endif
                                    @if(get_static_option('enable_facebook_login'))
                                        <a href="{{ route('login.facebook.redirect') }}" class="sign-in-btn">
                                            <img src="{{ asset('assets/frontend/img/static/facebook.png') }}" alt="icon">
                                            {{ __('Sign in with Facebook') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('assets/backend/js/sweetalert2.js') }}"></script>
    <script>
        "use strict";
        $(document).ready(function () {
            $(document).on('click', '#signin_form', function (e) {
                e.preventDefault();
                var el = $(this);
                var erContainer = $(".error-message");
                erContainer.html('');
                el.text('{{ __('Please Wait..') }}');
                $.ajax({
                    url: "{{ route('user.login') }}",
                    type: "POST",
                    data: {
                        username: $('#username').val(),
                        password: $('#password').val(),
                        remember: $('#remember').val(),
                        _token: "{{ csrf_token() }}"
                    },
                    error: function (data) {
                        var errors = data.responseJSON;
                        erContainer.html('<div class="alert alert-danger"></div>');
                        $.each(errors.errors, function (index, value) {
                            erContainer.find('.alert.alert-danger').append('<p>' + value + '</p>');
                        });
                        el.text('{{ __('Login Now') }}');
                    },
                    success: function (data) {
                        $('.alert.alert-danger').remove();
                        if (data.status == 'seller-login') {
                            el.text('{{ __('Redirecting') }}..');
                            erContainer.html('<div class="alert alert-' + data.type + '">' + data.msg + '</div>');
                            let redirectPath = "{{ route('seller.dashboard') }}";
                            @if(!empty(request()->get('return')))
                                redirectPath = "{{ url('/'.request()->get('return')) }}";
                            @endif
                            window.location = redirectPath;
                        } else if (data.status == 'buyer-login') {
                            el.text('{{ __('Redirecting') }}..');
                            erContainer.html('<div class="alert alert-' + data.type + '">' + data.msg + '</div>');
                            let redirectPath = "{{ route('buyer.dashboard') }}";
                            @if(!empty(request()->get('return')))
                                redirectPath = "{{ url('/'.request()->get('return')) }}";
                            @endif
                            window.location = redirectPath;
                        } else if (data.status == 'account-delete') {
                            el.text('{{ __('Redirecting') }}..');
                            erContainer.html('<div class="alert alert-' + data.type + '">' + data.msg + '</div>');
                            let redirectPath = "{{ route('seller.logout') }}";
                            @if(!empty(request()->get('return')))
                                redirectPath = "{{ url('/'.request()->get('return')) }}";
                            @endif
                            window.location = redirectPath;
                        } else {
                            erContainer.html('<div class="alert alert-' + data.type + '">' + data.msg + '</div>');
                            el.text('{{ __('Login Now') }}');
                        }
                    }
                });
            });
        });
    </script>
@endsection
