# FUN MOMENT Phase 1

## Executive Summary
FUN MOMENT mobile is a single Flutter marketplace app with both customer and provider experiences in the same codebase.

What is strong:
- authentication, registration, email verification, profiles, bookings, orders, chat, wallet, support tickets, and jobs are all represented in real Flutter screens and services
- the app uses live Laravel API endpoints, not a separate mock mobile backend
- bilingual support exists at the app and API-string level

What is still risky:
- several flows keep prior in-memory or SharedPreferences state when API calls fail
- provider service-management support exists in backend routes, but the Flutter UI does not expose a full create/edit/delete service-management flow
- subscription readiness is only partial in Flutter
- the Android build was already failing on the Flutter/Gradle plugin setup before this audit

Safe mobile-only fixes applied during this phase:
- removed a duplicate `BookService` provider registration from `lib/main.dart`
- fixed two no-op state assignments in `lib/service/rtl_service.dart`

## Customer Feature Matrix

| Feature | Status | Evidence |
|---|---|---|
| Registration | REAL | `lib/view/auth/signup/signup.dart`, `lib/service/auth_services/signup_service.dart`, `POST /register` |
| Login | REAL | `lib/view/auth/login/login.dart`, `lib/service/auth_services/login_service.dart`, `POST /login` |
| Verification | REAL | `lib/service/auth_services/email_verify_service.dart`, `POST /send-otp-in-mail`, `POST /user/send-otp-in-mail/success` |
| Profile | REAL | `lib/service/profile_service.dart`, `GET /user/profile` |
| Home | REAL | `lib/view/home/home.dart`, `lib/view/home/landing_page.dart`, `GET /slider`, `GET /category`, `GET /top-services`, `GET /latest-services` |
| Categories | REAL | category widgets and services, `GET /category`, `GET /category/sub-category/{id}` |
| Search | REAL | `lib/view/search/search_page.dart`, `lib/service/searchbar_with_dropdown_service.dart`, `GET /service/search`, `POST /home/home-search` |
| Filters | REAL | `lib/view/search/components/*`, `lib/service/filter_*` |
| Services | REAL | `lib/view/services/*`, `lib/service/service_details_service.dart` |
| Provider profiles | REAL | `lib/view/services/components/about_seller_tab.dart`, seller payloads in service detail responses |
| Service details | REAL | `lib/view/services/service_details_page.dart`, `GET /service-details/{id}` |
| Availability | REAL | `lib/view/booking/service_schedule_page.dart`, `GET /service-list/service-schedule/{day}/{seller_id}` |
| Booking | REAL | `lib/view/booking/*`, `POST /service/order` |
| Extras | REAL | `lib/view/booking/components/extras.dart`, extra service endpoints in order details |
| Checkout | REAL | `lib/view/booking/payment_choose_page.dart`, payment gateway screens, wallet deduction flow |
| Payment | REAL/PARTIAL | many gateway screens exist; actual success depends on gateway route and backend response |
| Orders | REAL | `lib/view/tabs/orders/orders_page.dart`, `lib/service/my_orders_service.dart` |
| Cancellation | REAL | `lib/service/orders_service.dart`, seller-side status route and buyer completion/decline flows |
| Reviews | REAL | `lib/view/services/review/write_review_page.dart`, `POST /user/add-service-rating/{id}` |
| Saved services | REAL | `lib/view/tabs/saved_item_page.dart`, local DB save cache plus live service cards |
| Notifications | REAL | push service, Pusher credentials endpoint, buyer/seller interests |
| Support | REAL | `lib/view/tabs/settings/supports/*`, `POST /user/ticket/create`, ticket message routes |
| Chat | REAL | `lib/view/live_chat/*`, `GET /user/chat/seller-lists`, `GET /user/chat/all-messages`, `POST /user/chat/send` |
| Jobs | REAL | `lib/view/jobs/*`, `POST /user/job/add-job`, `POST /user/job/edit-job/{id}`, `GET /job/details/{id}` |
| Wallet | REAL | `lib/view/wallet/wallet_page.dart`, `GET /user/wallet/balance`, `GET /user/wallet/history`, `POST /user/wallet/deposit`, `POST /user/wallet/deduct` |
| Subscription | PARTIAL | permission exists, but no dedicated mature provider subscription UI was found in Flutter |

## Provider Feature Matrix

| Feature | Status | Evidence |
|---|---|---|
| Provider registration | PARTIAL | same shared registration flow as customers; no separate onboarding flow was found |
| Provider login | REAL | shared login flow, role determined after auth/profile fetch |
| Provider profile | REAL | profile screens and seller/profile payloads are present |
| Verification | REAL | email verification is shared across roles |
| Subscription | PARTIAL | permission exists, but Flutter does not show a full subscription management flow |
| Service creation | PARTIAL | backend supports it, but Flutter does not expose a complete service-create UI flow |
| Service editing | PARTIAL | backend supports it, but Flutter does not expose a complete service-edit UI flow |
| Service deletion | PARTIAL | backend supports it, but Flutter does not expose a complete service-delete UI flow |
| Service availability | REAL | schedule and schedule-related service endpoints exist |
| Schedule | REAL | `lib/view/booking/service_schedule_page.dart`, schedule endpoint |
| Service zones | REAL/PARTIAL | zone/country/city/area data exists, but provider zone management UI is limited |
| Orders | REAL | seller/customer order flows exist in shared order stack |
| Accept/reject | REAL | order completion approval/decline flows and seller order status routes exist |
| Completion | REAL | buyer approval and seller completion workflow are present |
| Jobs | REAL | `lib/view/jobs/*` and `lib/service/jobs_service/*` |
| Chat | REAL | buyer/seller live chat exists |
| Wallet | REAL | wallet balance/history/deposit/deduct exist |
| Earnings | PARTIAL | wallet and order totals exist, but no dedicated earnings analytics screen was found |
| Payout | PARTIAL | backend supports money movement, but Flutter lacks a dedicated payout management surface |
| Notifications | REAL | seller notification interests and push dispatch exist |
| Support | REAL | seller-facing support ticket flows exist |
| Reports | REAL | report list/chat screens exist |

## Screen Inventory

The inventory below focuses on routeable screens and major feature pages. Component widgets are grouped under the parent screen they belong to.

| Screen | Role | Route | API | Backend | Real Data | Status |
|---|---|---|---|---|---|---|
| SplashScreen | Shared | app launch | `GET /language`, `GET /currency`, translations, permissions | `RtlService`, `AppStringService`, `HomepageHelper` | Yes | REAL |
| IntroductionPage | Shared | first run only | none | local onboarding state | Local only | REAL |
| LoginPage | Shared | auth gate | `POST /login`, social login endpoints | auth services | Yes | REAL |
| SignupPage | Shared | auth gate | `POST /register`, city/state/area lookups | signup service | Yes | REAL |
| Reset password screens | Shared | auth gate | OTP and reset endpoints | reset-password services | Yes | REAL |
| Email verification page | Shared | auth gate | `POST /send-otp-in-mail`, `POST /user/send-otp-in-mail/success` | email verify service | Yes | REAL |
| LandingPage | Shared | post-login shell | profile, permissions, push config | `landing_page.dart`, `bottom_nav.dart` | Yes | REAL |
| Homepage | Customer | tab 0 | slider, category, top services, recent services, recent jobs | home services | Yes | REAL |
| All categories page | Customer | from home | `GET /category` | category service | Yes | REAL |
| Top all services page | Customer | from home | `GET /top-services` | top services service | Yes | REAL |
| Search page | Customer | tab 3 / search | `GET /service/search`, filter endpoints | search services | Yes | REAL |
| All services page | Customer | discovery | service listing/search APIs | all-services service | Yes | REAL |
| Service by category page | Customer | browse | `GET /service-list/search-by-category/{id}` | service-by-category service | Yes | REAL |
| Service details page | Customer | from listings | `GET /service-details/{id}` | service details service | Yes | REAL |
| Seller all service page | Shared | seller profile browse | `GET /services-by-seller-id?seller_id=...` | seller services service | Yes | REAL |
| Book flow screens | Customer | booking flow | booking, schedule, coupon, order APIs | booking services | Yes | REAL |
| Payment choose page | Customer | checkout | payment gateway list | payment gateway service | Yes | REAL |
| Payment gateway pages | Customer | checkout | gateway-specific endpoints | payment services | Yes | REAL/PARTIAL |
| Payment success page | Customer | post payment | payment status update | place order service | Yes | REAL |
| Orders page | Shared | tab 1 | my orders, details, status actions | my orders service | Yes | REAL |
| Order details page | Shared | order detail | buyer and seller order detail APIs | order details service | Yes | REAL |
| Saved item page | Customer | tab 2 | local saved items + live service cards | db service + service services | Yes | REAL |
| Menu page | Shared | tab 4 | profile, permissions, support, jobs, wallet, reports | profile and permissions services | Yes | REAL |
| Profile edit page | Shared | menu | update profile API | profile edit service | Yes | REAL |
| Change password page | Shared | menu | change password API | change-pass service | Yes | REAL |
| Delete account page | Shared | menu | account delete API | delete-account service | Yes | REAL |
| My tickets page | Shared | support | support ticket list/create | support ticket service | Yes | REAL |
| Ticket chat page | Shared | support | ticket message endpoints | support messages service | Yes | REAL |
| Live chat list page | Shared | chat | seller list endpoint | chat list service | Yes | REAL |
| Live chat message page | Shared | chat | all messages/send | chat message service | Yes | REAL |
| My jobs page | Provider | jobs | user job list/on-off/delete | my jobs service | Yes | REAL |
| Create job page | Provider | jobs | add-job | create job service | Yes | REAL |
| Edit job page | Provider | jobs | edit-job | edit job service | Yes | REAL |
| Job request page | Provider | jobs | request lists, hire, conversation | job request service | Yes | REAL |
| Job conversation page | Provider | jobs | job conversation | job conversation service | Yes | REAL |
| Wallet page | Shared | menu | balance/history/deposit/deduct | wallet service | Yes | REAL |
| Report list page | Shared | menu | report list/details/send-message | report services | Yes | REAL |
| Report chat page | Shared | report flow | report messages | report message service | Yes | REAL |
| Notification helper flows | Shared | background | Pusher credential endpoint | push notification service | Yes | REAL |

## API Traceability

### Authentication and identity
- UI: login, signup, email verification
- State: `LoginService`, `SignupService`, `EmailVerifyService`
- API: `POST /login`, `POST /register`, `POST /send-otp-in-mail`, `POST /user/send-otp-in-mail/success`
- Backend: Laravel auth controller routes and Sanctum-backed user flows
- Database: `users`
- Response: token, user data, verification status
- UI: stores token, user id, remember-me state in SharedPreferences

### Customer browsing and booking
- UI: home, categories, service cards, details, schedule, booking, payment choose
- State: home/category/service/schedule/personalization/booking services
- API: `GET /slider`, `GET /category`, `GET /top-services`, `GET /latest-services`, `GET /service-details/{id}`, `GET /service-list/service-book/{id}`, `GET /service-list/service-schedule/{day}/{seller_id}`, `POST /service/order`
- Backend: `Api\SliderController`, `Api\CategoryController`, `Api\ServiceController`
- Database: `sliders`, `categories`, `subcategories`, `services`, `orders`, `order_includes`, `order_additionals`, `reviews`
- Response: service cards, pricing, seller profile snippets, schedules, order summary, payment result

### Orders and completion flow
- UI: orders page, order details, completion/decline components
- State: `MyOrdersService`, `OrderDetailsService`, `OrdersService`
- API: `GET /user/my-orders`, `POST /user/my-orders/{id}`, `POST /user/order/request/status/complete/approve`, `POST /user/order/request/status/complete/decline`, `GET /user/order/request/complete/decline/history`
- Backend: `Api\UserController`, `OrdersController`
- Database: `orders`, `order_additionals`, `support_tickets` when disputes are raised
- Response: order status changes, extra item approval/decline, decline history

### Wallet
- UI: wallet page, deposit flow, wallet deduct in checkout and job hire
- State: `WalletService`
- API: `GET /user/wallet/balance`, `GET /user/wallet/history`, `POST /user/wallet/deposit`, `POST /user/wallet/deposit/payment-status`, `POST /user/wallet/deduct`
- Backend: user wallet controller methods via Laravel API routes
- Database: wallet/history tables and related payment records
- Response: balance, transactions, deposit references, success/failure status

### Live chat and support
- UI: chat list, chat messages, support tickets, ticket chat, report chat
- State: `ChatListService`, `ChatMessagesService`, `SupportTicketService`, `SupportMessagesService`, `ReportService`, `ReportMessagesService`
- API: `GET /user/chat/seller-lists`, `GET /user/chat/all-messages`, `POST /user/chat/send`, `GET /user/support-tickets`, `POST /user/ticket/create`, `GET /user/view-ticket/{id}`, `POST /user/ticket/message-send`, `GET /user/report/list`, `GET /user/report/details/{id}`, `POST /user/report/send-message`
- Backend: buyer/seller chat and support controllers
- Database: chat tables, support ticket tables, report tables
- Response: room lists, message threads, ticket histories

### Jobs
- UI: job list, create, edit, requests, conversation
- State: `CreateJobService`, `EditJobService`, `MyJobsService`, `JobRequestService`, `JobConversationService`
- API: `POST /user/job/add-job`, `POST /user/job/edit-job/{id}`, `GET /user/job/job-lists`, `POST /user/job/on-off`, `POST /user/job/delete-job/{id}`, `GET /job/details/{id}`, `GET /user/job/request/request-lists`, `GET /user/job/request/conversation`, `POST /user/job/request/conversation/send`, `POST /user/job/request/seller-hire/{id}`
- Backend: job controllers in API routes
- Database: jobs, job requests, job conversations
- Response: job listings, request threads, hiring actions

### Permissions and module control
- UI: menu page and provider-only feature gates
- State: `PermissionsService`
- API: `GET /module-permission`
- Backend: miscellaneous controller / module flags
- Database: module/permission configuration
- Response: feature booleans for jobs, subscription, chat, wallet

## Mock/Fallback Register

No explicit mock-only business dataset was found in the Flutter mobile app.

What was found:
- placeholders for profile and service images
- empty states
- loading spinners
- local caching for translations and RTL/currency metadata
- stale-data reuse when API calls fail in some services

Items worth tracking:
- `lib/service/app_string_service.dart` caches translated strings in SharedPreferences and can reuse stale translations
- `lib/service/rtl_service.dart` caches language/currency values in SharedPreferences
- `lib/service/wallet_service.dart` can preserve old wallet history in memory if refresh fails
- `lib/service/my_jobs_service.dart` can keep prior jobs in memory after a failed refresh
- `lib/service/orders_service.dart` and `lib/service/profile_service.dart` show toasts but do not always clear old state on error

Not mock data:
- image placeholders
- loading states
- empty states
- fallback error screens

## Authentication & Authorization

### Authentication
- Shared login and registration
- Token stored in SharedPreferences
- Remember-me flow is enabled
- Social login hooks exist for Google, Facebook, and Apple
- Email verification is part of the auth lifecycle

### Authorization
- Mobile uses `PermissionsService` to hide or block some features
- Backend route prefixes separate buyer-side and seller-side APIs
- Buyer route prefix: `/user/*`
- Seller route prefix: `/seller/*`
- Sanctum-protected routes exist for authenticated operations

### Security assessment
- UI-side gating exists and is useful
- Real protection still depends on Laravel route/controller enforcement
- Conceptually, customer users should not invoke seller routes and seller users should not invoke buyer/admin routes
- This is present in the route structure, but mobile-only verification cannot prove every controller enforces role checks

## Payment Readiness

### Ready
- booking payment flow exists
- wallet deposit and wallet deduct exist
- payment gateway selection UI exists
- multiple gateway integrations are wired as separate screens/services

### Partial
- many gateways are present, but not all were end-to-end validated in this audit
- some flows depend on gateway-specific success pages or external redirects
- the app still relies on backend response correctness for final success state

### Risk
- payment failures should never be turned into false success states
- current flows usually toast or navigate based on response status, but some gateway-specific screens were not exhaustively validated

## Booking Readiness

### Ready
- browse service
- inspect provider
- choose schedule
- choose personalisation
- apply coupon
- place order
- view payment success
- send seller notification

### Partial
- manual/COD and gateway flows are both present, but not all gateway branches were validated
- the app uses several service objects and provider state holders, so broken state at one step can affect checkout

### Risk
- if schedule/category/country/state selection is empty or stale, booking can fail late in the flow
- some booking services keep prior state in memory until reset

## Subscription Readiness

### Customer-side
- not a primary mobile customer feature

### Provider-side
- backend permission flag exists for Subscription
- Flutter does not show a complete subscription management screen set

### Status
- PARTIAL

## Critical Mobile Bugs

1. Duplicate `BookService` provider registration in `lib/main.dart` could have created confusing state behavior.
2. `lib/service/rtl_service.dart` had two no-op state assignments:
   - `alreadyCurrencyLoaded == true;`
   - `alreadyRtlLoaded == true;`
   These are now fixed.
3. Flutter Android build is currently blocked by the current Gradle plugin application style, independent of the mobile feature audit.
4. Some services keep previous data in memory if an API refresh fails, which can produce stale UI.
5. Subscription management is incomplete in Flutter compared with backend permissions.
6. Provider service-management UI is not fully exposed in Flutter even though backend support exists.

## Production Blockers

- Flutter Android build is currently failing in this environment
- the mobile app still contains some stale-state risk on failed refreshes
- provider service-management is incomplete in the Flutter UI
- subscription readiness is partial
- some gateway/payment branches were not end-to-end validated in this audit

## Recommended Fixes

1. Keep the mobile app as a single shared buyer/provider app.
2. Finish provider service-management screens only if the backend expects them to be used from mobile.
3. Add explicit error states where some services currently preserve stale data.
4. Validate all payment gateway branches end to end.
5. Add a clearer provider subscription surface if the business expects it in mobile.
6. Fix the Flutter Android toolchain issue before release packaging.

## Changes Made During This Phase

- `lib/main.dart`
  - removed the duplicate `BookService` provider registration
- `lib/service/rtl_service.dart`
  - corrected the currency and RTL loaded-state assignments

## CURRENT MOBILE BASELINE
The mobile product is one Flutter marketplace app with both customer and provider roles, most core marketplace flows are real and backend-connected, but subscription and provider service-management remain incomplete in the mobile UI and the Android build toolchain still needs repair before production packaging.
