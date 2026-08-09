# FUN MOMENT - Phase 3 Admin Production Audit

## Executive Summary

The React admin dashboard is now mostly wired to live Laravel endpoints for core admin workflows such as dashboard summary, users, services, orders, payments, support, CMS, settings, localization, and feature flags.

The main production gaps found in this phase were:

- The audit center was using `localStorage` with seeded fake logs and approval tasks.
- The add-on management screens were presenting fabricated wallet/chat/jobs/subscription datasets.
- The dashboard had no backend-backed immutable audit store.
- The backend does not yet expose the React add-on summary endpoints for wallet, chat, jobs, and subscription tiers.
- Some services screens still had local simulation paths for upload/category creation, which were neutralized so they no longer fabricate business records.

The dashboard should now behave in a production-safe way: live backend data where available, true empty states where no records exist, and error states where endpoints are missing or fail.

## Admin Module Matrix

| Module | React View | Backend Source | Status | Notes |
| --- | --- | --- | --- | --- |
| Overview | `DashboardView` | `GET /admin-home/dashboard-summary` | REAL | Uses Laravel summary payload, charts, and activity feed. |
| Users | `UsersView` | `GET /admin-home/frontend-users`, `POST /admin-home/frontend-users/{id}/status`, `POST /admin-home/frontend-users/{id}/balance` | REAL | Fallback admin and profile seed data removed. |
| Admin Directory / RBAC | `UsersView` | `GET /admin-home/admin-directory` | REAL | Uses backend role payload; no local fallback users now. |
| Services | `ServicesView` | `GET /admin-home/services-json`, `POST /admin-home/services-json` | REAL / PARTIAL | Core service CRUD is real. Local simulated upload and local category/geography creation were neutralized. |
| Orders | `OrdersView` | `GET /admin-home/orders-json`, status endpoints | REAL | Order data is backend-driven. |
| Payments | `PaymentsView` | `GET /admin-home/payments-json`, `POST /admin-home/payments-json/{txnId}/refund` | REAL | Payments are backend-driven. |
| Support | `SupportView` | `GET /admin-home/support-tickets-json` | REAL | Ticket list and updates are backend-driven. |
| CMS | `CMSView` | `GET /admin-home/cms-inventory`, blogs/pages/menus/widgets/media endpoints | REAL | CMS media upload is wired to Laravel. |
| Localization | `LocalizationView` | `GET /admin-home/settings-json` and translation data | PARTIAL | UI is real; localization config persistence is limited. |
| Settings | `SettingsView` | `GET /admin-home/settings-json`, `POST /admin-home/settings-json` | REAL | Laravel config is the source of truth. |
| Audit | `AuditView` | `GET /admin-home/dashboard-summary` activity feed only | PARTIAL | No immutable backend audit store exists yet. |
| Add-ons | `AddonLockedView` | `GET /admin-home/feature-flags` | PARTIAL | Feature flags are real, but module-specific summary endpoints are still missing in backend. |
| Wallet | `AddonLockedView` | `GET /addons/wallet/summary` | BROKEN / MISSING BACKEND | Endpoint not found in backend route scan. |
| Chat | `AddonLockedView` | `GET /addons/chat/rooms` | BROKEN / MISSING BACKEND | Endpoint not found in backend route scan. |
| Jobs | `AddonLockedView` | `GET /addons/jobs` | BROKEN / MISSING BACKEND | Endpoint not found in backend route scan. |
| Subscription | `AddonLockedView` | `GET /addons/subscriptions/tiers` | BROKEN / MISSING BACKEND | Endpoint not found in backend route scan. |
| Roles & Permissions | `UsersView`, `AuditView` | Spatie roles/permissions in backend | REAL / PARTIAL | Admin roles exist, but UI permission mapping is still partially inferred. |
| Notifications | API contract only | `GET /admin-home/notifications` | MISSING/UNVERIFIED | Contract exists in React service layer, but backend route was not confirmed in this phase. |

## API Matrix

| UI Area | API | Status | Backend Notes |
| --- | --- | --- | --- |
| Auth check | `GET /admin-home/auth-check` | REAL | Session-based admin auth works. |
| Login | `POST /login/admin` | REAL | Session login used by the React login screen. |
| Logout | `GET /logout/admin` | REAL | Session logout route is used by the shell. |
| Dashboard summary | `GET /admin-home/dashboard-summary` | REAL | Returns live stats plus derived activity feed. |
| Feature flags | `GET /admin-home/feature-flags` / `POST /admin-home/feature-flags/{module}` | REAL | Backed by `ModuleFlagService` and `modules_statuses.json`. |
| Users list | `GET /admin-home/frontend-users` | REAL | Real users from PostgreSQL. |
| User status | `POST /admin-home/frontend-users/{id}/status` | REAL | Updates live user state and seller services. |
| User balance | `POST /admin-home/frontend-users/{id}/balance` | REAL | Updates wallet balance in backend. |
| Admin directory | `GET /admin-home/admin-directory` | REAL | Uses admins + roles from backend. |
| Services | `GET/POST /admin-home/services-json` | REAL | Live service CRUD. |
| Orders | `GET/POST /admin-home/orders-json` | REAL | Live order CRUD. |
| Payments | `GET /admin-home/payments-json` | REAL | Live payment data. |
| Refund | `POST /admin-home/payments-json/{txnId}/refund` | REAL | Live refund action. |
| Support tickets | `GET/POST /admin-home/support-tickets-json` | REAL | Live tickets and status updates. |
| CMS inventory | `GET /admin-home/cms-inventory` | REAL | Blogs, pages, menus, widgets, media inventory. |
| CMS blogs/pages/menus/widgets/media | `POST` routes under `/admin-home/cms-*` | REAL | CRUD and media upload routes exist. |
| Settings | `GET/POST /admin-home/settings-json` | REAL | Commission, min payout, maintenance, app version, currency. |
| Add-on summaries | `/addons/wallet/summary`, `/addons/chat/rooms`, `/addons/jobs`, `/addons/subscriptions/tiers` | MISSING | React expects them, backend route scan did not find them. |
| Immutable audit log | Not found | MISSING | No backend audit table or endpoint confirmed. |

## Mock Data Register

| File | Previous Issue | Current State |
| --- | --- | --- |
| `admin-react/src/utils/auditLogger.ts` | Seeded fake audit logs, change history, approval tasks in `localStorage`. | Fixed. It now behaves as a backend-gap no-op with empty arrays and no persistence. |
| `admin-react/src/components/UsersView.tsx` | Hardcoded fallback admin directory and seller/buyer profile datasets. | Fixed. Fallback seed records removed. Empty state used when backend has no data. |
| `admin-react/src/components/AddonLockedView.tsx` | Fabricated wallet/chat/jobs/subscription datasets and settings. | Fixed. Replaced with live backend loading and empty/error states. |
| `admin-react/src/components/ServicesView.tsx` | Local simulated upload and local record creation paths. | Fixed. Local fabrication paths were neutralized. |
| `admin-react/src/components/AuditView.tsx` | Depended on `localStorage` seeded fake logs/changes/approvals. | Fixed. Now reads backend activity feed only and shows real empty states for missing backend audit storage. |
| `admin-react/src/api/client.ts` | Cached bearer token in `localStorage`. | Fixed. Token is now in-memory only. |

## Add-on Architecture

The backend already has a feature-flag system through `App\Services\Admin\ModuleFlagService` and `AdminFeatureFlagController`.

Key points:

- The source of truth for add-on enabled/disabled state is `modules_statuses.json`.
- The registry currently covers:
  - Wallet
  - Live Chat
  - JobPost
  - Subscription
- The React shell reads and writes feature flags through `/admin-home/feature-flags`.
- The backend determines whether add-ons are enabled, but it does not yet expose the add-on summary/list endpoints that the React add-on views expect.

Current production implication:

- Feature toggles are real.
- Add-on data screens are not fully real yet because wallet/chat/jobs/subscription summary endpoints are missing.

## Subscription Architecture

The backend does contain subscription-related business logic:

- The dashboard controller can switch revenue calculations to subscription mode.
- The job and service logic already references subscription enablement checks.
- The backend route scan shows subscription email templates and existing subscription module references.

What is still missing for the React admin:

- A dedicated admin subscription summary endpoint.
- A tier/listing endpoint for the dashboard.
- A clean admin CRUD surface for plans, activation, expiry, and renewals in the React add-on module.

Conclusion:

- Subscription exists in backend logic.
- The React admin subscription screen is not yet fully backed by dedicated dashboard endpoints.

## Audit Architecture

Current state:

- The React audit center no longer stores anything in `localStorage`.
- The backend does not yet provide an immutable admin audit log table or API in the inspected route surface.
- The only live audit-like data available today is the derived activity feed returned by `AdminDashboardController::buildActivityLogs()`.

This means:

- Dashboard activity feed is real but derived.
- Compliance-style audit history is still missing.
- Approval queue processing is not backed by Laravel yet.

## Missing Backend Capabilities

1. Immutable audit log table and API.
2. Admin approval queue persistence.
3. Add-on summary endpoints for:
   - wallet
   - chat
   - jobs
   - subscription
4. Clear admin subscription CRUD endpoints for plans and subscriber management.
5. Confirmed notifications admin API route surface.

## Production Blockers

1. No backend audit store exists yet, so the audit screen cannot be fully production-grade.
2. Add-on screens rely on backend summary endpoints that are not present yet.
3. Some React admin copy and UI behavior still references non-production concepts, but the underlying fake data sources were removed.
4. The backend feature flags are file-based rather than database-backed, which is workable but should be treated as an operational dependency.

## Evidence

- `backend/app/Http/Controllers/AdminDashboardController.php`
- `backend/app/Http/Controllers/AdminFeatureFlagController.php`
- `backend/app/Services/Admin/ModuleFlagService.php`
- `backend/app/Http/Controllers/FrontendUserManageController.php`
- `backend/routes/admin.php`
- `admin-react/src/utils/auditLogger.ts`
- `admin-react/src/components/UsersView.tsx`
- `admin-react/src/components/AddonLockedView.tsx`
- `admin-react/src/components/AuditView.tsx`
- `admin-react/src/components/ServicesView.tsx`
- `admin-react/src/api/client.ts`
- `admin-react/src/services/api.ts`

## CURRENT PRODUCTION BASELINE

The FUN MOMENT admin dashboard is now connected to live Laravel data for the core admin flows, but add-on summary endpoints and a real backend audit store are still missing. Fake production-path data sources have been removed from the React dashboard, so the remaining gaps are now explicit backend capabilities rather than hidden local mocks.
