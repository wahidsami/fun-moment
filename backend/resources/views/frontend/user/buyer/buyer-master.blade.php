@include('frontend.user.seller.partials.header-two')
<!--Dashboard Markup -->
<div class="body-overlay"></div>
<div class="dashboard__area @if(Auth::guard('web')->user()->user_type == 0) seller_look @endif">
    <div class="container-fluid p-0">
        <div class="dashboard__contents__wrapper">
            <div class="dashboard__icon">
                <div class="dashboard__icon__bars sidebar-icon">
                    <i class="fa-solid fa-bars"></i>
                </div>
            </div>
             @yield('content')
        </div>
    </div>
</div>
@include('frontend.user.seller.partials.footer-two')