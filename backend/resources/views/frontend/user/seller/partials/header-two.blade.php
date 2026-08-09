<!DOCTYPE html>
<html lang="{{get_user_lang()}}" dir="{{get_user_lang_direction()}}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('site-title','')</title>
    {!! render_favicon_by_id(get_static_option('site_favicon')) !!}
    {!! load_google_fonts() !!}
    <!-- header two -->
    <link rel="stylesheet" href=" {{ asset('assets/frontend/theme-two/css/animate.css') }}">
    <link rel="stylesheet" href=" {{ asset('assets/frontend/theme-two/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href=" {{ asset('assets/frontend/theme-two/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href=" {{ asset('assets/frontend/theme-two/css/flaticon.css') }}">
    <link rel="stylesheet" href=" {{ asset('assets/frontend/theme-two/css/slick.css') }}">
    <link rel="stylesheet" href=" {{ asset('assets/frontend/theme-two/css/line-awesome.min.css') }}">
    <link rel="stylesheet" href=" {{ asset('assets/frontend/theme-two/css/select2.min.css') }}">
    <link rel="stylesheet" href=" {{ asset('assets/frontend/theme-two/css/flatpickr.min.css') }}">
    <link rel="stylesheet" href=" {{ asset('assets/frontend/theme-two/css/buyer_dashboard.css') }}">
    <link rel="stylesheet" href="{{asset('assets/common/css/toastr.min.css')}}">
    @if( get_user_lang_direction() === 'rtl')
    <link rel="stylesheet" href="{{asset('assets/common/css/rtl.css')}}">
    @endif
    <link rel="canonical" href="{{request()->url()}}" />
    <script src="{{asset('assets/common/js/jquery-3.6.0.min.js')}}"></script>
    @include('frontend.partials.root-style')
    @yield('style')
</head>
<body>
