# FUN MOMENT - Phase 4 Provider Production Audit

## Executive Summary

The FUN MOMENT platform uses a single Flutter application for both customer and service-provider roles. The provider lifecycle is not a separate app; it is implemented through shared Flutter screens and Laravel backend role/permission checks.

The provider stack is mostly real and backend-backed:

- Provider authentication and profile flow are real.
- Provider subscription data exists in the backend and is exposed through API endpoints.
- Provider service creation, editing, publish/disable, and approval gating are real.
- Provider schedules and booking availability are real, but the booking endpoint needed a server-side conflict guard.
- Provider earnings, wallet, and payout-related flows are real, but one balance-transfer path contained broken variables and needed correction.
- Provider support, notifications, jobs, and orders are all backed by backend routes and data.

I also applied two safe production fixes during this phase:

1. Added server-side protection against double-booking the same provider time slot.
2. Fixed the provider balance-transfer endpoint so it uses real request data instead of undefined variables.

## Provider Lifecycle

### Registration, Verification, Profile, Role, Activation, Login, Home

Status: REAL

Evidence:

- Flutter login flow uses the Laravel `/login` endpoint in [`lib/service/auth_services/login_service.dart`](../lib/service/auth_services/login_service.dart).
- The Flutter app stores the authenticated token and user identity, then loads the shared app shell.
- Provider role state is enforced through backend user type and permissions, not through a separate app.
- Provider profile screens and account settings are in the shared Flutter client and use backend-backed data.
- Provider account deactivation and delete flows exist in Laravel seller routes.

Backend references:

- [`backend/routes/web.php`](../backend/routes/web.php)
- [`backend/routes/seller.php`](../backend/routes/seller.php)
- [`backend/app/Http/Controllers/Frontend/SellerController.php`](../backend/app/Http/Controllers/Frontend/SellerController.php)

## Provider Authentication

Status: REAL

What exists:

- Shared Laravel login for the Flutter app.
- Provider-specific permissions are fetched from the backend.
- The provider menu hides or blocks modules based on server-returned permissions.

Key files:

- [`lib/service/auth_services/login_service.dart`](../lib/service/auth_services/login_service.dart)
- [`lib/service/permissions_service.dart`](../lib/service/permissions_service.dart)
- [`lib/view/tabs/settings/menu_page.dart`](../lib/view/tabs/settings/menu_page.dart)

Notes:

- The app is not relying on a separate provider app.
- The provider experience is role-based inside the same Flutter application.

## Subscription

Status: REAL

What exists:

- Provider subscription info/history APIs exist.
- Provider renewal through wallet exists.
- Provider service creation is blocked when subscription is required and missing.
- Admin subscription management routes exist in Laravel admin routing.

Backend evidence:

- [`backend/routes/api.php`](../backend/routes/api.php)
- [`backend/app/Http/Controllers/Api/SellerSubscriptionController.php`](../backend/app/Http/Controllers/Api/SellerSubscriptionController.php)
- [`backend/app/Http/Livewire/AddService.php`](../backend/app/Http/Livewire/AddService.php)
- [`backend/routes/admin.php`](../backend/routes/admin.php)

Important behaviors:

- If the platform is in subscription commission mode, providers must have an active subscription to create services.
- Subscription service-count limits are enforced before service creation.
- Provider add-service is also gated by seller verification when the admin setting requires verified sellers.

## Services

Status: REAL

What exists:

- Provider service list is real.
- Provider add/edit/delete/on-off flows are real.
- Service metadata, includes, extras, benefits, and FAQs are persisted to the database.
- Services can be approved/pending based on backend settings.

Backend/API evidence:

- [`backend/app/Http/Livewire/AddService.php`](../backend/app/Http/Livewire/AddService.php)
- [`backend/app/Http/Controllers/Api/SellerServiceController.php`](../backend/app/Http/Controllers/Api/SellerServiceController.php)
- [`backend/routes/seller.php`](../backend/routes/seller.php)
- [`backend/routes/api.php`](../backend/routes/api.php)

Website evidence:

- [`backend/app/Http/Controllers/Frontend/ServiceListController.php`](../backend/app/Http/Controllers/Frontend/ServiceListController.php)
- [`backend/app/Http/Controllers/Frontend/SellerController.php`](../backend/app/Http/Controllers/Frontend/SellerController.php)

## Availability

Status: REAL, with a production fix applied

What exists:

- Provider days and schedules are persisted in the backend.
- Booking-time slot lookup exists for both web and mobile.
- The booking system supports an allow-multiple-schedule flag.

Risk found:

- The UI already filtered unavailable slots, but the order creation endpoint still needed a backend conflict check to stop race-condition double bookings.

Fix applied:

- Added a server-side validation guard in both:
  - [`backend/app/Http/Controllers/Frontend/ServiceListController.php`](../backend/app/Http/Controllers/Frontend/ServiceListController.php)
  - [`backend/app/Http/Controllers/Api/ServiceController.php`](../backend/app/Http/Controllers/Api/ServiceController.php)
- The guard now rejects booking creation when the same provider/date/schedule is already reserved and multiple schedules are disabled.

## Orders

Status: REAL

What exists:

- Customer orders, seller orders, order status updates, completion requests, cancellations, and extra-service handling exist in Laravel.
- The provider dashboard reads real order aggregates from the database.
- Mobile and website share the same order tables and order lifecycle logic.

Backend evidence:

- [`backend/routes/buyer.php`](../backend/routes/buyer.php)
- [`backend/routes/seller.php`](../backend/routes/seller.php)
- [`backend/routes/web.php`](../backend/routes/web.php)
- [`backend/app/Http/Controllers/Frontend/ServiceListController.php`](../backend/app/Http/Controllers/Frontend/ServiceListController.php)
- [`backend/app/Http/Controllers/Frontend/SellerController.php`](../backend/app/Http/Controllers/Frontend/SellerController.php)
- [`backend/app/Http/Controllers/Api/ServiceController.php`](../backend/app/Http/Controllers/Api/ServiceController.php)

## Payments

Status: REAL

What exists:

- Wallet deposit, wallet balance, wallet history, and wallet deduction endpoints are real.
- Provider earnings and payout calculations are derived from order and payout tables.
- The platform supports multiple payment gateways and wallet-backed payment paths.

Flutter evidence:

- [`lib/service/wallet_service.dart`](../lib/service/wallet_service.dart)
- [`lib/service/booking_services/place_order_service.dart`](../lib/service/booking_services/place_order_service.dart)

Backend evidence:

- [`backend/routes/api.php`](../backend/routes/api.php)
- [`backend/app/Helpers/PaymentGatewayRenderHelper.php`](../backend/app/Helpers/PaymentGatewayRenderHelper.php)
- [`backend/app/Http/Controllers/Api/SellerController.php`](../backend/app/Http/Controllers/Api/SellerController.php)
- [`backend/app/Http/Controllers/Frontend/ServiceListController.php`](../backend/app/Http/Controllers/Frontend/ServiceListController.php)

## Payouts

Status: REAL, with a production fix applied

What exists:

- Payout request routes exist for seller and admin.
- Provider earnings are computed from completed orders and payout history.
- Admin payout request review/update routes exist.

Backend evidence:

- [`backend/routes/seller.php`](../backend/routes/seller.php)
- [`backend/routes/admin.php`](../backend/routes/admin.php)
- [`backend/app/Http/Controllers/Frontend/SellerController.php`](../backend/app/Http/Controllers/Frontend/SellerController.php)
- [`backend/app/Http/Controllers/PayoutRequestController.php`](../backend/app/Http/Controllers/PayoutRequestController.php)
- [`backend/app/Http/Controllers/Api/SellerController.php`](../backend/app/Http/Controllers/Api/SellerController.php)

Fix applied:

- Corrected the `depositFromBalance` flow in [`backend/app/Http/Controllers/Api/SellerController.php`](../backend/app/Http/Controllers/Api/SellerController.php)
- The previous implementation referenced undefined variables (`$buyer`, `$total`) and could break the provider balance-transfer path.
- It now uses the request amount and initializes wallet lookup safely.

## Notifications

Status: REAL

What exists:

- Order notifications, ticket notifications, push notification wiring, and seller notification routes are present.
- The provider experience has server-side notification flows, not just client-only badges.

Backend evidence:

- [`backend/app/Notifications/OrderNotification.php`](../backend/app/Notifications/OrderNotification.php)
- [`backend/app/Notifications/TicketNotificationSeller.php`](../backend/app/Notifications/TicketNotificationSeller.php)
- [`backend/routes/seller.php`](../backend/routes/seller.php)
- [`backend/routes/api.php`](../backend/routes/api.php)

## Support

Status: REAL

What exists:

- Provider support tickets and ticket messages are backed by Laravel routes and models.
- The provider menu exposes support ticket access in Flutter.

Evidence:

- [`backend/routes/seller.php`](../backend/routes/seller.php)
- [`backend/routes/buyer.php`](../backend/routes/buyer.php)
- [`backend/app/Http/Controllers/Frontend/SellerController.php`](../backend/app/Http/Controllers/Frontend/SellerController.php)
- [`lib/view/tabs/settings/menu_page.dart`](../lib/view/tabs/settings/menu_page.dart)

## Mock / Fallback Register

Status: No provider business mock data found in the inspected provider lifecycle paths

Findings:

- I did not find provider lifecycle code that fabricates fake provider services, fake orders, fake payouts, fake subscriptions, or fake schedules in the inspected Flutter and Laravel provider paths.
- The Flutter code uses `SharedPreferences` for auth/session state, which is expected and not business data.
- Permission gating is based on backend-returned flags, not hardcoded mock business records.

Important note:

- Empty states and loading states still exist in the UI, which is correct.
- Those are not mock records and should remain.

## Production Blockers

Current blockers after this audit:

1. No critical provider mock-data blocker remains in the inspected lifecycle path.
2. The booking slot race condition was a blocker and has been patched.
3. The provider balance-transfer endpoint bug was a blocker and has been patched.
4. The remaining risk is runtime-only validation of live provider flows:
   - subscription renew
   - payout request submission/approval
   - booking conflict behavior under concurrent requests

## Recommended Fixes

1. Smoke-test provider schedule creation and booking creation with live DB data.
2. Smoke-test provider renewal from wallet in a test environment.
3. Smoke-test payout request creation and admin payout review/update.
4. Confirm any provider mobile views consuming `api/seller/*` are showing database-backed values only.
5. Keep the shared Flutter app as the single client for both customer and provider roles.

## Completed Fixes

During this phase, the following safe backend fixes were implemented:

- Server-side booking conflict validation added to web and API order creation.
- Provider balance-transfer endpoint corrected in the API seller controller.

