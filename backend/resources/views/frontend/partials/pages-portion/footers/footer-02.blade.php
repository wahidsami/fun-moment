<footer class="footer-area color-two new-section-bg-2 gradient-footer">
    <div class="footer-top padding-top-100 padding-bottom-70">
        <div class="container container-two">
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="fm-footer-brand">
                        <a href="{{ route('homepage') }}" class="fm-brand">
                            <img src="{{ asset('logo.png') }}" alt="{{ get_static_option('site_title') ?? __('FUN MOMENT') }}">
                        </a>
                        <div>
                            <h3 class="mb-2">{{ __('FUN MOMENT') }}</h3>
                            <p>{{ __('The live booking platform for unforgettable events, trusted providers, and customer-first service.') }}</p>
                        </div>
                        <div class="fm-footer-actions">
                            <a class="fm-btn" href="{{ route('service.list.category') }}">{{ __('Explore Services') }}</a>
                            <a class="btn-outline-1" href="{{ route('all.sellers') }}">{{ __('Meet Providers') }}</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                {!! render_frontend_sidebar('footer_two') !!}
            </div>
        </div>
    </div>
    <div class="copyright-area style-02 copyright-border">
        <div class="container container-two">
            <div class="row align-items-center">
                {!! render_frontend_sidebar('copyright') !!}
            </div>
        </div>
    </div>
</footer>
