# FUN MOMENT Phase 0

## Executive Summary
FUN MOMENT is a multi-surface marketplace platform with:
- one Flutter mobile application
- one Laravel backend/public website
- one React admin dashboard
- a PostgreSQL-backed database layer

The current baseline is **not production-clean**. The backend repository is heavily dirty, the Flutter Android build is currently broken by a Gradle/Flutter plugin incompatibility, the Laravel frontend asset pipeline is not runnable on this host, and the React admin still contains mock/sample content in some views.

## Repository State

### Git Baseline
- The usable Git repository is the Laravel backend under `backend/`.
- Backend branch: `main`
- Latest commit: `d1fbe7c4` `v2relse`
- Backend Git status: heavily modified, with many deleted and untracked files
- The workspace root itself does not behave as a usable Git repository from the shell

### Repository Structure
Current major areas in the workspace:
- `lib/` Flutter mobile app
- `android/`, `ios/`, `web/`, `windows/` Flutter platform folders
- `backend/` Laravel backend and website
- `admin-react/` React admin dashboard
- `Archive/` legacy/static historical assets and old application snapshot
- `docs/` project documentation
- `scripts/` local start/stop helper scripts
- `build/` Flutter generated build output

### Current vs Historical vs Generated

#### Current
- `lib/`
- `android/`
- `ios/`
- `web/`
- `backend/`
- `admin-react/`
- `docs/`
- `scripts/`

#### Historical / Legacy / Backup
- `Archive/`
- `backend/custom/`
- `backend/__rootFiles/`
- `from google studio.md`
- `data-1782730720165.csv`
- `backend/readme.md` and other legacy documentation files that describe older system behavior

#### Generated / Temporary
- `build/`
- `admin-react/dist/`
- `.dart_tool/`
- `backend/storage/framework/cache/`
- `backend/storage/framework/sessions/`
- `backend/storage/framework/views/`
- `backend/storage/logs/`
- `backend/storage/assets/uploads/`
- `backend/public/js/` and `backend/public/css/` compiled assets
- `.DS_Store` files

## Architecture

### Mobile
There is **one Flutter app** with both roles in the same codebase:
- customer / buyer
- service provider / seller

Evidence:
- one Flutter entrypoint: `lib/main.dart`
- shared login flow: `lib/view/auth/login/login.dart`, `lib/service/auth_services/login_service.dart`
- shared landing shell with role-sensitive tabs: `lib/view/home/landing_page.dart`
- provider-only screens live in the same Flutter app tree:
  - jobs
  - wallet
  - live chat
  - seller services
  - seller order actions

### Website
The public/customer website is Laravel-based and handled by backend web routes and Blade views.

Evidence:
- `backend/routes/web.php`
- `backend/app/Http/Controllers/FrontendController.php`
- `backend/resources/views/frontend/*`

### Admin
The admin dashboard is the React app in `admin-react/`.

Evidence:
- `admin-react/src/App.tsx`
- `admin-react/src/components/AdminLoginScreen.tsx`
- `admin-react/src/services/api.ts`

### Backend
Laravel is the source of truth for business logic, auth, permissions, routes, and website rendering.

Evidence:
- `backend/routes/api.php`
- `backend/routes/web.php`
- `backend/app/Http/Controllers/*`

### Database
The project is configured for PostgreSQL.

Evidence:
- `backend/.env`
- `backend/.env.example`

## Environment Configuration

### Development Environment
- Backend `.env` is configured for local development
- Backend app URL is local
- Backend debug mode is enabled
- Flutter local scripts point the mobile app at the local API
- React admin runs locally on Vite port `3000`

### Staging Environment
- No dedicated staging environment file was found in the workspace

### Production Environment
- Production-like mobile API defaults exist in Flutter code
- The backend working tree itself is still configured as a local environment
- No separate production env file was found in the workspace

### API Base URLs
- Flutter mobile default API base: production URL in code, overrideable by `BASE_API`
- Flutter local script uses `http://127.0.0.1:8000/api/v1`
- React admin API client uses `/api/v2`
- React admin also calls Laravel session endpoints under `/admin-home/*`

### Configured Integrations

| Area | Status | Notes |
|---|---|---|
| Database | Configured | PostgreSQL is set in backend env |
| Mail | Configured | SMTP variables are present |
| Payments | Configured | Multiple gateways are present, including Paystack, Mollie, Flutterwave, PayPal, Paytm, Razorpay, Stripe, Midtrans, PayFast, Cashfree, Instamojo, Mercado Pago |
| Pusher / realtime | Partial | Pusher variables exist, but some values are blank |
| Storage | Configured | Local file storage exists; uploaded asset folders are present |
| Maps | Configured | Mobile maps configuration is present in Flutter and Android config |
| Social auth | Partial | Facebook and Google hooks exist, but backend env entries are blank |
| SMS / Twilio | Not fully confirmed | Twilio is in Composer dependencies, but no dedicated SMS env block was found in the current env file |
| Localization | Configured | Arabic/English support exists across mobile and React admin |

### Environment Variable Families Present

#### Backend `.env`
- `APP_*`
- `LOG_*`
- `DB_*`
- `BROADCAST_DRIVER`
- `CACHE_DRIVER`
- `QUEUE_CONNECTION`
- `SESSION_*`
- `REDIS_*`
- `MAIL_*`
- `AWS_*`
- `PUSHER_*`
- `PAYSTACK_*`
- `MOLLIE_KEY`
- `FLW_*`
- `PAYPAL_*`
- `PAYTM_*`
- `FACEBOOK_*`
- `GOOGLE_*`
- `RAZORPAY_*`
- `STRIPE_*`
- `MIDTRANS_*`
- `PF_*`
- `CASHFREE_*`
- `INSTAMOJO_*`
- `MERCADO_PAGO_*`
- `SITE_GLOBAL_CURRENCY`
- payment/test mode flags
- exchange rate variables
- RSS feed variables

#### Backend `.env.example`
- core Laravel environment variables
- database variables
- mail variables
- Pusher variables
- filesystem/logging variables

#### React Admin `.env.example`
- `GEMINI_API_KEY`
- `APP_URL`

## Build Status

### Flutter Android
Status: **failed**

Command tested:
- `flutter build apk --debug --dart-define=BASE_API=http://127.0.0.1:8000/api/v1`

Failure:
- Gradle failed in Flutter's `app_plugin_loader.gradle`
- The build is using the old imperative `apply script` plugin style, which the current Flutter toolchain rejects
- The build also printed plugin compatibility warnings for Linux/macOS/Windows packages, but the fatal error was the Gradle plugin application method

### Flutter iOS
Status: **not verifiable on this Windows host**

Reason:
- The current machine is Windows, so iOS build validation is not available here

### Laravel Backend / Website Core
Status: **boots successfully**

Command tested:
- `php artisan about`

Result:
- Laravel booted successfully
- Laravel version and configuration loaded
- Database driver is PostgreSQL
- Debug mode is enabled

### Laravel Frontend Assets
Status: **failed on this host**

Command tested:
- `npm run production` in `backend/`

Failure:
- Node failed with `EPERM: operation not permitted, lstat 'C:\\Users\\wahid'`
- This means the backend asset pipeline is not currently runnable on this host as-is

### React Admin
Status: **build succeeds**

Command tested:
- `npm run build` in `admin-react/`

Result:
- Production build completed successfully
- Output generated under `admin-react/dist/`
- A chunk-size warning was emitted, but it did not fail the build

## Existing Risks

- The backend repository is very dirty and contains extensive local modifications
- There are many deleted, modified, and untracked runtime files under `backend/storage/`
- `APP_DEBUG=true` and a local `APP_URL` remain in the backend environment
- The React admin still contains mock/sample content in some areas
- The mobile Flutter Android build is currently blocked by a tooling compatibility issue
- Backend frontend asset compilation is currently blocked on this host
- Several payment and integration variables are present in mixed test/live or partially empty states
- There is no separate staging configuration in the repo
- The root workspace is not a clean, single Git repo surface from the shell

## Existing Uncommitted Work

The backend Git status shows ongoing work in these major areas:

- `.env`
- app controllers and middleware
- migrations
- frontend Blade views
- routes
- configuration files
- generated cache/session/view/log artifacts
- uploaded media assets
- deleted legacy files such as `.DS_Store`, update archives, and obsolete view files

There is also generated output present outside backend Git tracking:
- `admin-react/dist/`
- Flutter `build/`

## Production Blockers

1. The backend repository is not in a production-clean state.
2. The backend environment is still local/debug oriented.
3. Flutter Android build currently fails on the current toolchain.
4. Laravel frontend asset rebuild currently fails on this host.
5. React admin still contains mock/sample dataset code in some views.
6. There is no separate staging/release baseline captured in the workspace.

## Baseline Recommendation

Before any production-hardening work:

1. Freeze this state as the baseline.
2. Do not clean or overwrite the current backend working tree until the team agrees on what is intentional.
3. Remove mock/sample data from the React admin and replace it with live API wiring.
4. Fix the Flutter Android build toolchain compatibility.
5. Define a clean production `.env` strategy separate from the local `.env`.
6. Treat `Archive/` and similar folders as legacy reference material only.

## Evidence

- [lib/main.dart](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/lib/main.dart)
- [lib/view/auth/login/login.dart](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/lib/view/auth/login/login.dart)
- [lib/view/home/landing_page.dart](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/lib/view/home/landing_page.dart)
- [backend/routes/web.php](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/web.php)
- [backend/routes/api.php](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/api.php)
- [backend/.env](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/.env)
- [backend/.env.example](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/.env.example)
- [admin-react/src/App.tsx](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/admin-react/src/App.tsx)
- [admin-react/src/components/AdminLoginScreen.tsx](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/admin-react/src/components/AdminLoginScreen.tsx)
- [admin-react/src/components/AddonLockedView.tsx](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/admin-react/src/components/AddonLockedView.tsx)
- [admin-react/src/services/api.ts](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/admin-react/src/services/api.ts)
- [backend/composer.json](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/composer.json)
- [backend/package.json](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/package.json)
- [scripts/run-all-local.ps1](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/scripts/run-all-local.ps1)

## CURRENT PRODUCTION BASELINE
FUN MOMENT currently exists as a multi-surface marketplace platform with a single Flutter mobile app, a Laravel backend/public website, and a React admin dashboard, but the workspace is not production-clean: the backend tree is heavily modified, the Flutter Android build is broken, backend asset compilation fails on this host, and the React admin still contains mock/sample content that should be removed before production release.
