# FUN MOMENT - Phase 2 Website Production Audit

## Executive Summary

The public website is built on the existing Laravel architecture and most customer-facing commerce flows are real and database-backed. The major production gap is the homepage: the selected homepage setting is unset, the homepage widget location is empty, and the legacy `frontend.home-pages.home-*` demo views are not present in this checkout. Without a fallback, the landing page can render blank.

I implemented two safe production fixes:

1. The live homepage now falls back to rendering the `homepage` page-builder location when no `Page` is selected.
2. The legacy demo homepage selector now redirects back to the live homepage if the variant view is missing.

No fake business records were added. Empty states are preserved.

## Website Inventory

| Page / Capability | Status | Notes |
| --- | --- | --- |
| Homepage | PARTIAL | Live route exists, but `home_page`, `home_page_variant`, and `home_page_page_builder_status` are unset and there are no `homepage` widgets saved. |
| Registration | REAL | Public user registration routes and views exist. |
| Login | REAL | Public login routes and views exist. |
| Password recovery | REAL | User and admin reset flows exist. |
| Service listing | REAL | Public service listing pages are backed by the database. |
| Categories | REAL | Category and subcategory browsing is database-backed. |
| Search | REAL | Home search and service search are live. |
| Filters | REAL | Rating, sorting, category, and subcategory filters exist. |
| Provider profiles | REAL | Seller profile pages are routed from username slugs. |
| Service details | REAL | Service detail pages are database-backed. |
| Availability | REAL | Booking availability uses seller days and schedules. |
| Booking | REAL | Booking flow posts to live order creation logic. |
| Extras | REAL | Extra services are part of the order flow. |
| Checkout | REAL | Checkout is handled inside the booking form and payment flow. |
| Payment | REAL | Real gateways and IPN handlers exist. |
| Orders | REAL | Buyer and seller dashboards show real orders. |
| Order details | REAL | Real order detail pages exist. |
| Cancellation | REAL | Order cancel routes and payment cancel pages exist. |
| Reviews | REAL | Review creation and display are wired to the database. |
| Saved services | MISSING | No clear public website route or view was found for saved services. |
| Support | REAL | Support tickets and report flows exist. |
| Chat | PARTIAL | Only report/ticket chat is exposed on the web; there is no clear public live customer chat surface. |
| Jobs | PARTIAL | Job-related order support exists in dashboards, but no full public customer job marketplace was confirmed in the web surface. |
| Wallet | PARTIAL | Wallet gateway support exists in checkout and dashboard links, but the public website does not expose a complete standalone customer wallet surface. |
| Subscription | PARTIAL | Backend/module hooks exist, but the website customer surface is not complete across all flows. |
| Profile | REAL | Buyer and seller profile pages exist. |
| Notifications | REAL | Buyer and seller notifications are exposed in the dashboards. |

## Homepage Investigation

### Selected Homepage

No homepage is currently selected in the database.

- `home_page = null`
- `home_page_variant = null`
- `home_page_page_builder_status = null`

### Homepage Database Setting

The homepage selection is controlled by the `home_page` static option in the reading settings flow.

### Page-Builder Status

No widgets are currently saved in the homepage builder location.

- `homepage_widgets = 0`

### Current Homepage Content

The production homepage now has a safe fallback:

- If a `Page` is selected, the existing page-builder rendering path is used.
- If no page is selected, the homepage renders the `homepage` widget location directly.
- If there is still no content, the UI shows an empty-state notice instead of breaking.

### Available Page-Builder Blocks

The homepage builder registers these frontend widgets in the current codebase:

- HeaderStyleOne
- ServiceListOne
- FeatureService
- FeatureServiceTwo
- FeatureServiceThree
- PopularService
- PopularServiceTwo
- PopularServiceThree
- ProfessionalService
- OnlineService
- OnlineServiceTwo
- BrowseCategoryOne
- BrowseCategoryTwo
- BrowseCategoryThree
- WhyOurMarketplace
- WhyOurMarketplaceTwo
- WhyOurMarketplaceThree
- BecomeSeller
- BecomeSellerTwo
- BecomeSellerThree
- RecentBlog
- RecentBlogTwo
- RecentBlogThree
- ContactInfo
- ContactMessage
- AboutUs
- Brands
- AllBlog
- Faq
- RawHTML
- TextEditor
- OnlyImage
- SellerProfile
- SellerProfileTwo
- AllSellerList
- OnlineServiceList
- CustomerReviewOne
- BannerOne

Module widgets are also conditionally added when their modules are enabled:

- Subscription
- JobPost
- Wallet

### Configured Blocks

None.

### Missing Blocks

The missing content is not a missing code widget list. It is missing database configuration and saved homepage content.

### Missing Views

The legacy demo include path `frontend.home-pages.home-*` is not present in this repository checkout.

### Missing Assets / CSS / JS

No missing homepage-only asset was required for the live route. The main issue was missing content configuration, not missing frontend files.

### Missing Database Content

- `home_page` is unset
- `home_page_variant` is unset
- `home_page_page_builder_status` is unset
- no `homepage` page-builder widgets are saved

## Customer Parity

| Capability | Mobile | Website | Backend | Status |
| --- | --- | --- | --- | --- |
| Landing / homepage | Yes | Partial | Yes | Website homepage is now safe, but content is not configured. |
| Registration | Yes | Yes | Yes | Parity |
| Login | Yes | Yes | Yes | Parity |
| Password recovery | Yes | Yes | Yes | Parity |
| Service browsing / search / filters | Yes | Yes | Yes | Parity |
| Provider profiles | Yes | Yes | Yes | Parity |
| Service details | Yes | Yes | Yes | Parity |
| Availability / schedules | Yes | Yes | Yes | Parity |
| Booking / checkout / payment | Yes | Yes | Yes | Parity |
| Orders / order details / cancellation | Yes | Yes | Yes | Parity |
| Reviews | Yes | Yes | Yes | Parity |
| Support tickets | Yes | Yes | Yes | Parity |
| Chat | Yes | Partial | Yes | Web chat is limited to report/ticket chat. |
| Wallet | Yes | Partial | Yes | Web wallet is not a full standalone customer surface. |
| Subscription | Yes | Partial | Yes | Web subscription surface is not complete. |
| Saved services | Yes | Missing | Yes | No confirmed public website surface. |
| Profile | Yes | Yes | Yes | Parity |
| Notifications | Yes | Yes | Yes | Parity |
| Jobs | Yes | Partial | Yes | Web job surface is not fully exposed as a customer feature. |

## Booking Traceability

The public booking flow is real and stays inside the shared Laravel backend and PostgreSQL database.

Flow:

`service` -> `availability` -> `date` -> `time` -> `extras` -> `checkout` -> `payment` -> `order` -> `confirmation` -> `provider`

Key backend entry points:

- `Frontend\ServiceListController@serviceBook`
- `Frontend\ServiceListController@scheduleByDay`
- `Frontend\ServiceListController@couponApply`
- `Frontend\ServiceListController@createOrder`
- payment success / cancel routes in `routes/web.php`
- buyer and seller order dashboards in `routes/buyer.php` and `routes/seller.php`

The booking flow writes into the same order tables used by the buyer and seller dashboards and by the admin side.

## Website Mock Data Register

No active fake or demo business records were found in the production website paths inspected for this phase.

Notes:

- Some commented examples exist in old builder partials.
- Some static assets use placeholder visuals such as no-image cards.
- Those are UI placeholders, not business records.

## Homepage and Demo Risks

### Live Risk Before Fix

- The selected homepage setting was empty.
- The homepage builder had no saved widgets.
- The live homepage could render as a blank page.
- The legacy `frontend.home-pages.home-*` include was pointing at files that are not present here.

### Safe Fixes Applied

- `backend/resources/views/frontend/frontend-home.blade.php`
  - now renders the real homepage builder location when no page is selected
  - now shows an empty-state notice if there is still no configured homepage content

- `backend/app/Http/Controllers/FrontendController.php`
  - now redirects missing demo homepage variants back to the live homepage

## Existing Risks

1. The homepage content is not configured in the database.
2. No widgets are saved in the homepage builder location.
3. The legacy demo homepage path is absent from this checkout.
4. Saved services are still missing from the public website surface.
5. Web chat, wallet, subscription, and jobs are only partially exposed on the customer web side.

## Existing Uncommitted Work

Not modified in this audit unless listed in the implemented fixes above.

## Production Blockers

1. Homepage configuration is missing.
2. Homepage builder content is empty.
3. Legacy demo homepage views are missing.
4. Some customer parity features are not fully exposed on the public website.

## Baseline Recommendation

1. Populate a real homepage through the page-builder UI.
2. Set the `home_page` reading setting to the correct production page if a page-based homepage is desired.
3. Add a public saved-services surface if that capability is required on web.
4. Decide whether wallet, subscription, chat, and jobs need full customer-web parity or should remain dashboard/module-only.

## Evidence

- `backend/routes/web.php`
- `backend/routes/buyer.php`
- `backend/routes/seller.php`
- `backend/app/Http/Controllers/FrontendController.php`
- `backend/app/Http/Controllers/Frontend/ServiceListController.php`
- `backend/resources/views/frontend/frontend-home.blade.php`
- `backend/resources/views/frontend/frontend-home-demo.blade.php`
- `backend/resources/views/backend/general-settings/reading.blade.php`
- `backend/app/Http/Controllers/GeneralSettingsController.php`
- `backend/resources/views/frontend/pages/services/service-book.blade.php`
- `backend/resources/views/frontend/pages/services/service-details.blade.php`
- `backend/resources/views/frontend/pages/services/category-services.blade.php`

## Implemented Changes

1. `backend/resources/views/frontend/frontend-home.blade.php`
   - added a fallback to render `PageBuilderSetup::render_frontend_pagebuilder_content_by_location('homepage')`
   - added an empty-state notice when no homepage content exists

2. `backend/app/Http/Controllers/FrontendController.php`
   - added a guard so missing legacy demo variants redirect to `homepage`

### CURRENT WEBSITE BASELINE

The website is on the real Laravel backend and most commerce flows are live, but the public homepage is currently unconfigured in the database and has no saved homepage widgets. The site now fails safe instead of breaking, but the homepage content still needs to be built in the page builder before the customer experience is production-ready.
