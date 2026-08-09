# FUN MOMENT Architecture Map

## System Shape

FUN MOMENT is currently a multi-app platform with one shared Laravel backend.

```text
                +----------------------------+
                |  React Admin Dashboard     |
                |  admin-react/              |
                +-------------+--------------+
                              |
                              | fetch /session /admin-home/*
                              v
+-----------------------------+-----------------------------+
|                      Laravel Backend                      |
|  backend/                                                 |
|  - web routes                                             |
|  - api routes                                             |
|  - admin JSON routes                                      |
|  - Blade frontend                                         |
|  - admin controllers                                      |
|  - permissions / roles                                    |
|  - feature flags                                          |
|  - CMS / media / orders / services / tickets / settings   |
+-----------------------------+-----------------------------+
                              |
                              | database models / static options
                              v
                     +-------------------+
                     | PostgreSQL DB      |
                     | funmoments         |
                     +-------------------+
                              ^
                              |
                              | REST API / auth:sanctum / module-permission
                +-------------+--------------+
                |   Flutter Mobile App       |
                |   lib/                     |
                +----------------------------+
```

## Applications

### 1. Laravel backend

Responsibilities:
- customer website routes
- admin website routes
- admin JSON endpoints for the React dashboard
- public API v1 for the mobile app
- permissions, roles, modules, settings, CMS, orders, payments, support

Key files:
- `backend/routes/web.php`
- `backend/routes/api.php`
- `backend/routes/admin.php`
- `backend/app/Http/Controllers/AdminDashboardController.php`
- `backend/app/Http/Controllers/AdminCmsController.php`
- `backend/app/Http/Controllers/OrdersController.php`
- `backend/app/Http/Controllers/ServiceController.php`
- `backend/app/Http/Controllers/FrontendUserManageController.php`
- `backend/app/Http/Controllers/AdminTicketViewController.php`
- `backend/app/Http/Controllers/AdminFeatureFlagController.php`
- `backend/app/Services/Admin/ModuleFlagService.php`

### 2. Legacy customer website

Responsibilities:
- homepage and content pages
- customer signup/login
- service browsing and booking
- blog pages
- buyer and seller dashboards
- order and ticket workflows

Evidence:
- `backend/routes/web.php`
- `backend/resources/views/frontend/*`
- `backend/app/Http/Controllers/Frontend/*`

### 3. React admin dashboard

Responsibilities:
- admin login
- dashboard overview
- users
- services
- orders
- payments
- support
- CMS
- settings
- audit
- add-on lock/unlock UI

Key files:
- `admin-react/src/App.tsx`
- `admin-react/src/components/AdminLoginScreen.tsx`
- `admin-react/src/components/DashboardView.tsx`
- `admin-react/src/components/UsersView.tsx`
- `admin-react/src/components/ServicesView.tsx`
- `admin-react/src/components/OrdersView.tsx`
- `admin-react/src/components/PaymentsView.tsx`
- `admin-react/src/components/SupportView.tsx`
- `admin-react/src/components/CMSView.tsx`
- `admin-react/src/components/SettingsView.tsx`
- `admin-react/src/components/AuditView.tsx`
- `admin-react/src/components/AddonLockedView.tsx`
- `admin-react/src/api.ts`
- `admin-react/src/services/api.ts`

### 4. Flutter mobile app

Responsibilities:
- customer onboarding
- browsing categories and services
- booking and payments
- wallet
- live chat
- support tickets
- jobs
- seller flows

Key files:
- `lib/main.dart`
- `lib/view/*`
- `lib/service/*`
- `lib/view/utils/others_helper.dart`

### 5. Database

The live data model includes:
- users
- admins
- roles and permissions
- orders
- services
- blogs
- pages
- menus
- widgets
- support tickets
- payout requests
- static options

Evidence:
- `data-1782730720165.csv`

## Data Flow

### React dashboard path

1. User opens `admin-react`.
2. `AdminLoginScreen` posts to Laravel admin login.
3. `App.tsx` checks `/admin-home/auth-check`.
4. Dashboard screens call `/admin-home/*` JSON endpoints.
5. Laravel controllers read/write PostgreSQL and static options.

### Mobile app path

1. Flutter reads `BASE_API` from `lib/view/utils/others_helper.dart`.
2. Services call Laravel `api/v1` endpoints.
3. API controllers return JSON.
4. The app uses those responses for login, booking, wallet, support, chat, and jobs.

### Website path

1. Browser hits Laravel web routes.
2. Blade views render content from models and static options.
3. Booking and payment flows go through Laravel controllers and IPN callbacks.

## Feature Flag System

Current add-on controls are backed by:
- `backend/modules_statuses.json`
- `backend/app/Services/Admin/ModuleFlagService.php`
- `backend/app/Http/Controllers/AdminFeatureFlagController.php`

The registry currently covers:
- Wallet
- LiveChat
- JobPost
- Subscription

## Important Architectural Note

There are two admin surfaces in the repository:
- the legacy Laravel/Blade admin surface
- the newer React admin surface

The React dashboard is the preferred modern admin UI, but the Laravel admin code still exists and remains the source of truth for business logic.

