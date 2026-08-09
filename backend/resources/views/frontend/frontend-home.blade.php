@extends('frontend.frontend-master')
@section('content')
    @if(!empty($page_details))
        @include('frontend.partials.pages-portion.dynamic-page-builder-part',['page_post' => $page_details])
    @else
        @php
            $homepage_content = \App\PageBuilder\PageBuilderSetup::render_frontend_pagebuilder_content_by_location('homepage');
        @endphp

        @if(!empty(trim($homepage_content)))
            {!! $homepage_content !!}
        @else
            <section class="padding-top-100 padding-bottom-100">
                <div class="container">
                    <div class="alert alert-info mb-0">
                        {{ __('The homepage builder is not configured yet.') }}
                    </div>
                </div>
            </section>
        @endif
    @endif
@endsection
