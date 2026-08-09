# FUN MOMENT Dashboard Mock Data Audit

Date: 2026-08-09

## Scope

This audit covers the React admin dashboard in `admin-react/src` and the Flutter mobile app in `lib/`.

Goal:
- Identify every page or module that still uses seeded, mock, demo, fallback, or hardcoded record data.
- Separate true data records from harmless UI text placeholders and loading states.
- Confirm whether the mobile app is connected to the same Laravel backend used by the dashboard.

## Executive Summary

- The dashboard is mostly wired to the Laravel backend now.
- There are still a few real mock datasets left in the dashboard codebase.
- The biggest remaining mock-data sources are:
  - `admin-react/src/components/UsersView.tsx`
  - `admin-react/src/components/AddonLockedView.tsx`
  - `admin-react/src/components/AuditView.tsx`
  - `admin-react/src/utils/auditLogger.ts`
- The dashboard shell also keeps a small local fallback for addon lock states in `admin-react/src/App.tsx`, but that is state fallback, not a seeded record list.
- The mobile app is connected to the Laravel backend through `BASE_API` and many direct API calls. It is not directly connected to the admin dashboard UI itself, but it does use the same backend API family.

## Section By Section Audit

| Section | Status | Mock/Data Found | Notes |
| --- | --- | --- | --- |
| Dashboard Overview | Clean | No seeded dashboard records found | Dashboard summary, chart series, categories, and activities are fetched from Laravel now. |
| Services | Mostly clean | No seeded service dataset in the component after the recent cleanup | The page still has some UI-only hardcoded field hints and labels, but not mock records. |
| Users | Needs cleanup | Yes | Contains fallback admin users, seller profile seed data, and buyer profile seed data. |
| Orders | Clean | No seeded order dataset found in the current front-end API path | Uses live backend endpoints. |
| Payments | Clean | No seeded payment dataset found in the current front-end API path | Uses live backend endpoints. |
| Support | Clean | No seeded support-ticket dataset found in the current front-end API path | Uses live backend endpoints. |
| CMS | Clean with UI defaults | No seeded CMS content list found in the current component state | The page still has form defaults and empty UI states, but not a mock content database. |
| Localization | Clean with defaults | No seeded records found | The view uses form defaults like `3.75`, but those are editable config defaults, not a fake dataset. |
| Settings | Clean | No seeded settings dataset remains in the React API layer | Settings now read and write through backend JSON endpoints. |
| Add-ons | Needs cleanup | Yes | Contains wallet/chat/jobs/subscription mock databases and coupon data. |
| Audit | Needs cleanup | Yes | Audit logger seeds logs, change history, and approval queues into localStorage. |
| App Shell | Fallback only | No seeded records, but local fallback lock state exists | `lockedModules` starts with all modules locked until feature flags load. |
| Login Screen | Clean | No seeded record data found | Uses real auth flow, not mock auth data. |

## Detailed Findings

### 1) Users

File:
- `admin-react/src/components/UsersView.tsx`

Findings:
- `FALLBACK_ADMIN_USERS` is a real seeded admin list.
- Seller profile details are hardcoded in local component state.
- Buyer profile details are hardcoded in local component state.
- The page uses these values if the admin directory API fails.

Impact:
- This page still exposes mock admin and profile data even when the backend is unavailable.

### 2) Add-Ons

File:
- `admin-react/src/components/AddonLockedView.tsx`

Findings:
- The component contains mock datasets for:
  - wallet transactions
  - chat rooms and messages
  - job listings and milestones
  - subscription plans
  - seller subscriptions
  - coupons
- These are seeded directly in component state.

Impact:
- This is the largest remaining mock-data area in the dashboard.
- It is intentionally used as a playground for locked modules, but it is still mock data.

### 3) Audit

Files:
- `admin-react/src/components/AuditView.tsx`
- `admin-react/src/utils/auditLogger.ts`

Findings:
- `AuditView.tsx` still includes a fallback permission row for localization.
- `auditLogger.ts` seeds:
  - default audit logs
  - default change history
  - default approval tasks
- Those defaults are written into localStorage on first load and on reset.

Impact:
- The audit screen is still demo-seeded on a fresh browser profile.

### 4) App Shell

File:
- `admin-react/src/App.tsx`

Findings:
- `lockedModules` starts with all add-ons locked until feature flags are fetched.
- If the feature-flag endpoint fails, the app keeps the local fallback lock state.

Impact:
- This is not mock record data, but it is fallback behavior that can mask backend issues.

### 5) API Layer

File:
- `admin-react/src/api.ts`

Findings:
- The old in-memory mock datasets were removed.
- The file still has some non-mock fallback behavior:
  - admin directory returns empty arrays if unavailable
  - media library returns an empty array if unavailable
  - `createCMSItem` throws because there is no direct backend route yet

Impact:
- No seeded fake records remain in this file, but a few endpoints still degrade to empty states.

### 6) CMS

File:
- `admin-react/src/components/CMSView.tsx`

Findings:
- The component now starts from empty arrays and live fetches.
- No obvious seeded CMS record list remains in the current component state.

Impact:
- CMS is largely clean from mock record usage.

### 7) Orders, Payments, Support, Services

Files:
- `admin-react/src/components/OrdersView.tsx`
- `admin-react/src/components/PaymentsView.tsx`
- `admin-react/src/components/SupportView.tsx`
- `admin-react/src/components/ServicesView.tsx`

Findings:
- These sections are now mostly live-data driven.
- I did not find current seeded record lists inside their main data flows.
- Some components still contain UI-only placeholders, labels, and helper text, but not mock database rows.

## Mobile App Connectivity Verdict

### Is the mobile app connected to the backend?

Yes.

Evidence:
- `lib/view/utils/others_helper.dart` defines the default base API as `https://funmoments.sa/api/v1`.
- `scripts/run-mobile-local.ps1` launches Flutter with `--dart-define=BASE_API=http://10.0.2.2:8000/api/v1` for local development.
- The Flutter app makes many real HTTP calls through `http` and `dio` to endpoints like:
  - `/login`
  - `/register`
  - `/user/profile`
  - `/category`
  - `/service/order`
  - `/user/wallet/history`
  - `/user/chat/seller-lists`
  - `/user/ticket/create`

### Is the mobile app directly connected to the admin dashboard UI?

No direct UI link was found.

The mobile app talks to the Laravel backend API, and the admin dashboard also talks to the same Laravel backend. They are connected through the backend, not through each other.

## Cleanup Priority

1. Remove seeded admin/user profile fallbacks from `admin-react/src/components/UsersView.tsx`.
2. Replace the mock playground datasets in `admin-react/src/components/AddonLockedView.tsx` with backend-driven data or a gated empty state.
3. Move audit defaults out of localStorage seeding in `admin-react/src/utils/auditLogger.ts` and load real audit data from the backend.
4. Decide whether the app shell fallback lock state in `admin-react/src/App.tsx` should default to locked or be driven entirely by backend feature flags.

## Bottom Line

- The dashboard is not fully free of mock data yet.
- The remaining mock data is concentrated in three main places: users, add-ons, and audit storage.
- The mobile app is connected to the Laravel backend, but not directly to the admin dashboard UI.
