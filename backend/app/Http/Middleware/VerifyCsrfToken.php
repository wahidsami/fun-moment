<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'login/admin',
        'event-paypal-ipn',
        'event-paytm-ipn',


        'admin-home/update-static-option',
        'admin-home/get-static-option',
        'admin-home/set-static-option',
        'admin-home/frontend-users',
        'admin-home/frontend-users/*/status',
        'admin-home/frontend-users/*/balance',
        'admin-home/admin-directory',
        'admin-home/services-json',
        'admin-home/services-json/*',
        'admin-home/services-json/*/*',
        'admin-home/services-json/*/*/*',
        'admin-home/orders-json',
        'admin-home/orders-json/*',
        'admin-home/payments-json',
        'admin-home/payments-json/*',
        'admin-home/payments-json/*/*',
        'admin-home/support-tickets-json',
        'admin-home/support-tickets-json/*',
        'admin-home/support-tickets-json/*/*',
        'admin-home/cms-inventory',
        'admin-home/cms-inventory/*',
        'admin-home/cms-inventory/*/*',
        'admin-home/cms-inventory/*/*/*',
        'admin-home/cms-blogs',
        'admin-home/cms-blogs/*',
        'admin-home/cms-blogs/*/*',
        'admin-home/cms-pages',
        'admin-home/cms-pages/*',
        'admin-home/cms-pages/*/*',
        'admin-home/cms-menus',
        'admin-home/cms-menus/*',
        'admin-home/cms-menus/*/*',
        'admin-home/cms-widgets',
        'admin-home/cms-widgets/*',
        'admin-home/cms-widgets/*/*',
        'admin-home/cms-media',
        'admin-home/cms-media/*',
        'admin-home/cms-media/*/*',
        'admin-home/feature-flags',
        'admin-home/feature-flags/*',
        'contribution-paytm-ipn',


        'subscription/paytm/ipn',
        'subscription/payfast-ipn',
        'subscription/cinetpay-ipn',
        'subscription/zitopay-ipn',
        'subscription/kineticpay-ipn',
        'subscription/paytabs-ipn',


        'buyer/paytm-ipn',
        'buyer/cashfree-ipn',
        'buyer/payfast-ipn',
        'buyer/cinetpay-ipn',
        'buyer/zitopay-ipn',
        'buyer/kineticpay-ipn',
        'buyer/paytabs-ipn',


        'cashfree/ipn',
        'cinetpay-ipn',
        'paypal/ipn',
        'paytm/ipn',
        'payfast-ipn',
        'zitopay-ipn',
        'kineticpay-ipn',
        'paytabs-ipn',

        'jobpost/cashfree-ipn',
    ];
}
