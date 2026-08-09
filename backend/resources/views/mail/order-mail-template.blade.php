<!doctype html>
@php
    $default_lang = get_default_language();
@endphp
<html lang="{{$default_lang}}" dir="{{ get_user_lang_direction() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{get_static_option('site_title').' '. __('Mail')}}</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    @if(get_user_lang_direction() === 'rtl')
        <link rel="stylesheet" href="{{asset('assets/backend/css/rtl.css')}}">
    @endif

</head>
<body>
    
    
<style>
        *{
            font-family: 'Open Sans', sans-serif;
        }
        .mail-container {
            max-width: 650px;
            margin: 0 auto;
            text-align: center;
            padding: 40px 0;
        }
        .inner-wrap {
            background-color: #fff;
            margin: 40px;
            padding: 30px 20px;
            text-align: left;
            box-shadow: 0 0 20px 0 rgba(0,0,0,0.01);
        }
        .inner-wrap p {
            font-size: 16px;
            line-height: 26px;
            color: #656565;
            margin: 0;
        }
        .inner-wrap .table {
            overflow-x: auto !important;
            width: 100% !important;
        }

        .message-wrap {
            background-color: #f2f2f2;
            padding: 30px;
            margin-top: 40px;
        }

        .message-wrap p {
            font-size: 14px;
            line-height: 26px;
        }
        .table {
           
        }
        .table {
            border-collapse: collapse;
            width: 100%;
            text-align: center;
        }

        .table td, .table th {
            border: 1px solid #ddd;
            padding: 8px;
        }

        .table .table-row:nth-child(even){background-color: #f2f2f2;}

        .table .table-row:hover {background-color: #ddd;}

        .table .table-row .table-heading {
            padding-top: 12px;
            padding-bottom: 12px;
            text-align: left;
            background-color: #111d5c;
            color: white;
        }
        .earning-title {
            border-bottom: 1px solid #ddd;
            font-size: 24px;
            color: red;
        }
        @media only screen and (max-width: 575px) {
            .inner-wrap {
                background-color: #fff;
                margin: 30px 0 !important;
                padding: 30px 10px !important;
                text-align: left;
                box-shadow: 0 0 20px 0 rgba(0,0,0,0.01);
            }
            .inner-wrap .table {
                overflow-x: auto;
                width: 100%;
                margin: 30px 0 !important;
            }
            .table td, .table th {
                border: 1px solid #ddd;
                padding: 8px 0;
                font-size: 14px;
            }
            .earning-title {
                font-size: 18px;
            }
        }

        [dir="rtl"] .earning-wrapper {
            text-align: right !important;
        }
        [dir="rtl"] .earning-wrapper .earning-title {
            text-align: right !important;
        }
        [dir="rtl"] .wrap-para {
            text-align: right !important;
        }
        [dir="rtl"] .inner-wrap-contents p {
            text-align: right !important;
        }
        [dir="rtl"] .inner-wrap-contents .earning-order-title {
            text-align: right !important;
        }
        [dir="rtl"] .earning-title {
            text-align: right !important;
        }
</style>
<div class="mail-container" style="max-width: 650px;margin: 0 auto;text-align: center;background-color: #f2f2f2;padding: 40px 0;">
    <div class="logo-wrapper">
        <a href="{{url('/')}}">
            {!! render_image_markup_by_attachment_id(get_static_option('site_logo')) !!}
        </a>
    </div>
    <div class="inner-wrap" style="background-color: #fff;text-align: left;box-shadow: 0 0 20px 0 rgba(0,0,0,0.01);">
        {!! $message_body !!}
    </div>
    <footer>
        {!! get_footer_copyright_text() !!}
    </footer>
</div>

</body>
</html>
