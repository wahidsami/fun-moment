# FUN MOMENT System Audit

Date of audit: 2026-08-09

## Executive Summary

FUN MOMENT is a multi-surface service marketplace platform built around a Laravel backend, a legacy Laravel/Blade customer website, a Flutter mobile app, and a newer React admin dashboard.

The backend is real and active. The React dashboard is mostly wired to live Laravel JSON endpoints, but it is not fully mock-free yet. A few dashboard screens still use local fallback datasets or browser-localStorage seeds, and several CMS/branding sections are still UI-only presets rather than backend-persisted state.

The mobile app is connected to the Laravel backend through real API calls. It is not connected directly to the React dashboard; both the app and the dashboard consume the same backend and database.

## What Exists

| Layer | What it is | Evidence |
|---|---|---|
| Backend | Laravel app with web routes, API routes, admin controllers, models, permissions, and modules | `backend/routes/web.php`, `backend/routes/api.php`, `backend/routes/admin.php`, `backend/app/Http/Controllers/*` |
| Customer website | Laravel Blade frontend with login, register, service listing, booking, payment, blog, seller/buyer dashboards | `backend/routes/web.php:10-163` |
| Admin dashboard | React app in `admin-react/` | `admin-react/src/App.tsx`, `admin-react/src/components/*` |
| Mobile app | Flutter app in `lib/` | `lib/main.dart`, `lib/service/*`, `lib/view/*` |
| Database | PostgreSQL data is present and non-empty | `data-1782730720165.csv` |
| Feature flags | Server-side module lock file plus admin API | `backend/modules_statuses.json`, `backend/app/Services/Admin/ModuleFlagService.php`, `backend/app/Http/Controllers/AdminFeatureFlagController.php` |

## Customer Website

Yes, there is a customer website.

The Laravel web routes include:
- homepage
- blog pages
- user register and login
- service list and service details
- booking flow
- order success/cancel pages
- payment callback/IPN routes

Evidence:
- `backend/routes/web.php:10-130`
- `backend/routes/web.php:136-163`

## Customer Account Creation

Yes, customers can create accounts.

Evidence:
- `backend/routes/web.php:25-31`
- `backend/routes/api.php:27-29`

## Booking

Yes, booking exists.

Evidence:
- Website booking route: `backend/routes/web.php:59-75`
- API booking route: `backend/routes/api.php:189-193`
- Order creation in frontend controller: `backend/app/Http/Controllers/Frontend/ServiceListController.php`

## Dashboard Login and Auth

The React dashboard uses the existing Laravel admin session.

Evidence:
- React login screen posts to `/login/admin`: `admin-react/src/components/AdminLoginScreen.tsx`
- React app session check calls `/admin-home/auth-check`: `admin-react/src/App.tsx:105`
- Logout calls `/logout/admin`: `admin-react/src/App.tsx:211`
- Laravel admin login route: `backend/routes/web.php:139-146`

## Dashboard Data Reality Check

### Live / backend-backed

These major React dashboard areas are wired to real Laravel endpoints:
- overview dashboard
- users
- services
- orders
- payments
- support tickets
- CMS inventory and CRUD
- settings

Evidence:
- `admin-react/src/components/DashboardView.tsx`
- `admin-react/src/components/UsersView.tsx`
- `admin-react/src/components/ServicesView.tsx`
- `admin-react/src/components/OrdersView.tsx`
- `admin-react/src/components/PaymentsView.tsx`
- `admin-react/src/components/SupportView.tsx`
- `admin-react/src/components/CMSView.tsx`
- `admin-react/src/components/SettingsView.tsx`
- `admin-react/src/api.ts`
- `backend/routes/admin.php`

### Not fully clean yet

The dashboard still contains local fallback or demo sources in these areas:
- admin directory fallback users
- seller/buyer profile seed data in the users screen
- add-on module sandbox data for wallet/chat/jobs/subscription
- audit logs and approval queue stored in localStorage with default seed records
- localization screen uses hardcoded UI defaults and does not persist through the backend
- CMS branding/SEO/maintenance panels still use local UI defaults for several fields

Evidence:
- `admin-react/src/components/UsersView.tsx`
- `admin-react/src/components/AddonLockedView.tsx`
- `admin-react/src/utils/auditLogger.ts`
- `admin-react/src/components/AuditView.tsx`
- `admin-react/src/components/LocalizationView.tsx`
- `admin-react/src/components/CMSView.tsx`

## Mobile App Connectivity

The Flutter app is connected to the Laravel backend through real API calls.

Evidence:
- Base API configuration: `lib/view/utils/others_helper.dart`
- Login API: `lib/service/auth_services/login_service.dart`
- Register API: `lib/service/auth_services/signup_service.dart`
- Profile API: `lib/service/profile_service.dart`
- Orders API: `lib/service/orders_service.dart`
- Booking API: `lib/service/booking_services/place_order_service.dart`
- Wallet API: `lib/service/wallet_service.dart`
- Chat API: `lib/service/live_chat/chat_list_service.dart`, `lib/service/live_chat/chat_message_service.dart`
- Ticket API: `lib/service/support_ticket/create_ticket_service.dart`, `lib/service/support_ticket/support_messages_service.dart`
- Module permission API: `lib/service/permissions_service.dart`

Important nuance:
- The mobile app connects to the backend.
- The React dashboard also connects to the backend.
- There is no direct UI-to-UI connection between mobile app and React dashboard.

## Database State

The exported CSV shows real rows in core tables, but many are demo/test records.

Evidence:
- `data-1782730720165.csv`

Observed counts from the export:
- `static_options`: 53
- `orders`: 2
- `services`: 2
- `users`: 2
- `admin_commissions`: 1
- `admins`: 1
- `blogs`: 1
- `categories`: 1
- `child_categories`: 1
- `countries`: 1
- `languages`: 1
- `menus`: 1
- `pages`: 1
- `payout_requests`: 1
- `subcategories`: 1
- `support_tickets`: 1
- `widgets`: 1
- `media_uploads`: 0
- `permissions`: 0
- `roles`: 0

## Production Readiness Gaps

The main gaps are:
- remove all local fallback/demo datasets from the React dashboard
- persist audit logs and approval workflows in Laravel instead of localStorage
- persist localization and branding sections in backend settings
- verify login/session/CORS in a stable deployment setup
- make the add-on modules either fully backed or fully hidden until backed
- populate permissions/roles and media tables correctly in production

## Confidence

| Claim | Confidence | Why |
|---|---|---|
| Laravel backend exists and is active | HIGH | Direct route/controller evidence |
| Customer website exists | HIGH | `backend/routes/web.php` contains full frontend surface |
| Booking exists | HIGH | Booking and order creation routes are present |
| React dashboard is mostly backend-backed | HIGH | Live fetch calls to admin JSON endpoints |
| Dashboard is 100% mock-free | LOW | Multiple local/demo sources still exist |
| Mobile app is connected to backend | HIGH | Direct API calls in Flutter services |
| Mobile app is directly connected to React dashboard | LOW | No direct UI connection found; both share backend |

