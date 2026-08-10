# FUN MOMENT Web Frontend Inventory

## Executive Summary
The public web layer is a Laravel/Blade frontend that sits on top of the same Laravel business logic, database, and payment flows used by the mobile app and admin panel.

The inventory below covers the live customer website, auth surfaces, public discovery pages, booking flow, and the customer/provider dashboard shells that are still rendered through Blade.

No separate React/SPA frontend exists for the public web.

## Global Shell

| Area | File | Data Source | Status |
| --- | --- | --- | --- |
| Shared HTML shell | `backend/resources/views/frontend/partials/header.blade.php` | Laravel helpers, static options, locale helpers | Real |
| Shared footer / JS shell | `backend/resources/views/frontend/partials/footer.blade.php` | Laravel helpers, module checks, payment widgets | Real |
| Navbar variants | `backend/resources/views/frontend/partials/pages-portion/navbars/navbar-01.blade.php`, `navbar-02.blade.php` | Menu builder, auth state, notifications | Real |
| Footer variants | `backend/resources/views/frontend/partials/pages-portion/footers/footer-01.blade.php`, `footer-02.blade.php` | Widget areas, static options | Real |
| RTL/LTR handling | Shared header + helpers | `get_user_lang_direction()` | Real |

## Public Website Routes

| Route Group | Route Examples | Controller / View | Status |
| --- | --- | --- | --- |
| Homepage | `/` | `FrontendController@index` -> `frontend.frontend-home` | Real |
| Search | `/home-search`, `/home-search-two`, `/home-search/single-page` | `FrontendController@home_search*` | Real |
| Blog | blog prefix routes from `web.php` | `Frontend\BlogController` views | Real |
| Auth | `/login`, `/register`, `/email-verify`, `/otp-verify/{user_id?}`, `/user/forget-password` | Auth controllers + Blade views | Real |
| Service discovery | `/service-list/{slug}`, `/service-list/category/{slug?}`, `/service-list/sub-category/{slug?}`, `/service-list/child-category/{slug?}` | `Frontend\ServiceListController` | Real |
| Service details | `/service-list/{slug}` | `frontend.pages.services.service-details` | Real |
| Booking | `/service-list/book-now/{slug}` + related AJAX endpoints | `frontend.pages.services.service-book` | Real |
| Orders / payment callbacks | `/order-success/{id}`, `/order-cancel/{id}`, IPN routes | `ServicePaymentController`, `Frontend\ServiceListController` | Real |
| Public seller profile | `/{username?}` and `/seller/all` | `FrontendController@dynamic_single_page` / `Frontend\ServiceListController` | Real |
| Buyer profile | `/buyer-profile/{username?}` | `FrontendController@buyerProfile` | Real |
| Static pages | `/{slug}` | `FrontendController@dynamic_single_page` | Real |

## Customer Dashboard Shells

| Area | File | Notes | Status |
| --- | --- | --- | --- |
| Buyer shell | `backend/resources/views/frontend/user/buyer/buyer-master.blade.php` | Uses shared header/footer and dashboard wrapper | Real |
| Seller shell | `backend/resources/views/frontend/user/seller/seller-master.blade.php` | Uses shared header/footer and dashboard wrapper | Real |
| Buyer sidebar | `backend/resources/views/frontend/user/buyer/partials/sidebar-two.blade.php` | Dashboard navigation and module-gated items | Real |
| Seller sidebar | `backend/resources/views/frontend/user/seller/partials/sidebar-two.blade.php` | Dashboard navigation and module-gated items | Real |

## Customer-Facing Content Pages

| Page | File | Data Type | Status |
| --- | --- | --- | --- |
| Homepage | `backend/resources/views/frontend/frontend-home.blade.php` | Live categories, services, sellers, blogs, page-builder content | Real |
| Login | `backend/resources/views/frontend/user/login.blade.php` | Real auth form, no demo records | Real |
| Register | `backend/resources/views/frontend/user/register.blade.php` | Real auth form, OTP and social options when enabled | Real |
| Category listing | `backend/resources/views/frontend/pages/category/all-category.blade.php` | Live categories | Real |
| Service listing | `backend/resources/views/frontend/pages/services/service-static.blade.php` | Live services | Real |
| Service details | `backend/resources/views/frontend/pages/services/service-details.blade.php` | Live service, seller, reviews, booking CTA | Real |
| Booking | `backend/resources/views/frontend/pages/services/service-book.blade.php` | Live booking flow, real payment gateways | Real |
| Seller profile | `backend/resources/views/frontend/pages/seller/profile.blade.php` | Live seller profile, services, schedule, reviews | Real |
| Buyer profile | `backend/resources/views/frontend/pages/buyer/profile.blade.php` | Live buyer profile, posted jobs, reviews | Real |
| Order details | `backend/resources/views/frontend/pages/order/order-details.blade.php` | Live order data | Real |

## Existing Notes

* The public frontend already depends on the live Laravel models and controllers.
* The main remaining work area is visual consistency and shell modernization, not replacing the frontend architecture.
* No mock business data is required for the current public routes.

