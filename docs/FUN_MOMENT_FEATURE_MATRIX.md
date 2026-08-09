# FUN MOMENT Feature Matrix

## Scope

This matrix maps major product features to the backend, React dashboard, and mobile app surfaces.

## Matrix

| Feature | Backend evidence | React dashboard evidence | Mobile app evidence | Status |
|---|---|---|---|---|
| Admin login | `backend/routes/web.php:139-146`, `backend/app/Http/Controllers/Auth/LoginController.php` | `admin-react/src/components/AdminLoginScreen.tsx`, `admin-react/src/App.tsx:105` | Not applicable | Live |
| Dashboard overview | `backend/app/Http/Controllers/AdminDashboardController.php:145-187, 323-523` | `admin-react/src/components/DashboardView.tsx`, `admin-react/src/api.ts:1-60` | Not applicable | Live |
| Users directory | `backend/app/Http/Controllers/FrontendUserManageController.php:1-140` | `admin-react/src/components/UsersView.tsx`, `admin-react/src/api.ts:67-132` | Profile/account APIs exist | Mostly live, with fallback data in UI |
| Services management | `backend/app/Http/Controllers/ServiceController.php`, `backend/routes/admin.php:24-30` | `admin-react/src/components/ServicesView.tsx`, `admin-react/src/api.ts:155-300` | Services browsing and booking APIs exist | Live |
| Orders management | `backend/app/Http/Controllers/OrdersController.php`, `backend/routes/admin.php:31-34` | `admin-react/src/components/OrdersView.tsx`, `admin-react/src/api.ts:306-367` | Order list/detail APIs exist | Live |
| Payments/refunds | `backend/app/Http/Controllers/OrdersController.php:92-145`, `backend/routes/admin.php:34-35` | `admin-react/src/components/PaymentsView.tsx`, `admin-react/src/api.ts:368-449` | Wallet/payment services exist | Live |
| Support tickets | `backend/app/Http/Controllers/AdminTicketViewController.php`, `backend/routes/admin.php:36-37` | `admin-react/src/components/SupportView.tsx`, `admin-react/src/api.ts:450-491` | Ticket create/message services exist | Live |
| CMS inventory | `backend/app/Http/Controllers/AdminCmsController.php` | `admin-react/src/components/CMSView.tsx`, `admin-react/src/api.ts:454-764` | Public website content is driven by Laravel views/content | Live |
| Blogs CRUD | `backend/app/Http/Controllers/AdminCmsController.php:97-180` | `admin-react/src/components/CMSView.tsx:364-406` | Website blog routes in `backend/routes/web.php:15-23` | Live |
| Pages CRUD | `backend/app/Http/Controllers/AdminCmsController.php:182-250` | `admin-react/src/components/CMSView.tsx:433-479` | Website dynamic page routes in `backend/routes/web.php:149-152` | Live |
| Menus CRUD | `backend/app/Http/Controllers/AdminCmsController.php:252-323` | `admin-react/src/components/CMSView.tsx:502-544` | Website menu rendering exists in Laravel views/settings | Live |
| Widgets CRUD | `backend/app/Http/Controllers/AdminCmsController.php:325-396` | `admin-react/src/components/CMSView.tsx:566-622` | Widget rendering exists in Blade/Page Builder | Live |
| Media upload | `backend/app/Http/Controllers/AdminCmsController.php:398-470` | `admin-react/src/components/CMSView.tsx:631-665` | Laravel media upload routes exist | Live |
| Settings | `backend/app/Http/Controllers/AdminDashboardController.php:161-242` | `admin-react/src/components/SettingsView.tsx` | App-wide setting consumers exist in Laravel and Flutter | Live |
| Localization | `backend/routes/admin.php` and translation files | `admin-react/src/components/LocalizationView.tsx` | Flutter language handling exists in app | Partial, dashboard side is UI-only |
| Feature flags / add-ons | `backend/app/Services/Admin/ModuleFlagService.php`, `backend/app/Http/Controllers/AdminFeatureFlagController.php`, `backend/modules_statuses.json` | `admin-react/src/App.tsx:86-171`, `admin-react/src/components/AddonLockedView.tsx` | Mobile module gating via `/api/v1/module-permission` | Partial, live lock service exists but add-on screens still contain demo state |
| Audit logs | No persisted backend audit store found in current scan | `admin-react/src/components/AuditView.tsx`, `admin-react/src/utils/auditLogger.ts` | Not applicable | Partial, browser-local only |
| Roles and permissions | `backend/app/Http/Controllers/AdminRoleManageController.php` | `admin-react/src/components/UsersView.tsx`, `admin-react/src/components/AuditView.tsx` | Not applicable | Live for backend roles, partial in UI |
| Customer registration | `backend/routes/web.php:25-31`, `backend/routes/api.php:27-29` | Not applicable | `lib/service/auth_services/signup_service.dart` | Live |
| Customer booking | `backend/routes/web.php:59-75`, `backend/routes/api.php:189-193` | Not applicable | `lib/service/booking_services/place_order_service.dart` | Live |
| Wallet module | `backend/routes/api.php:79-86`, `backend/routes/api.php:228-230` | `admin-react/src/components/AddonLockedView.tsx` | `lib/service/wallet_service.dart` | Backend exists; dashboard add-on view still demo-heavy |
| Chat module | `backend/routes/api.php:124-134`, `backend/routes/api.php:294-304` | `admin-react/src/components/AddonLockedView.tsx` | `lib/service/live_chat/*` | Backend exists; dashboard add-on view still demo-heavy |
| Jobs module | `backend/routes/api.php:135-184`, `backend/routes/api.php:305-314` | `admin-react/src/components/AddonLockedView.tsx` | `lib/service/jobs_service/*` | Backend exists; dashboard add-on view still demo-heavy |
| Subscription module | `backend/routes/api.php:314-317` and related controllers | `admin-react/src/components/AddonLockedView.tsx` | `lib/service/*subscription*` | Backend exists; dashboard add-on view still demo-heavy |

## Interpretation

The platform is not a blank scaffold. It has real backend capabilities, real mobile API integration, and a React admin frontend that already consumes many live endpoints.

The main remaining gaps are:
- localStorage audit storage
- UI-only localization/branding blocks
- fallback admin user/profile state
- add-on dashboard sandbox data

