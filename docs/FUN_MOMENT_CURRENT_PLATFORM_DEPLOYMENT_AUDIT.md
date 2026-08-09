# FUN MOMENT Current Platform Deployment Audit

## Executive Summary

FUN MOMENT is not a single app. It is a multi-surface platform made up of:

- a Flutter mobile application in the repository root
- a Laravel backend that also serves the public website, buyer dashboard, seller dashboard, and legacy admin routes
- a separate React admin dashboard in `admin-react/`
- a PostgreSQL database named `funmoments`

The local platform is already structured so that the Laravel backend is the source of truth for business data. The Flutter app connects through the Laravel API, while the Laravel web routes serve the public website and role-based dashboards. The React admin is a separate frontend that also consumes Laravel-backed admin APIs.

This codebase can be deployed to a VPS, but not as a blind copy. The deployable parts are the Laravel backend, its web assets, its public website, its API, scheduled jobs, queue workers, storage, and optionally the React admin build if that dashboard is intended to be hosted separately. The Flutter application itself is not VPS-hosted; it is built and distributed separately, but it must be pointed at the correct backend URL.

The most important current risk is that the Flutter app still defaults to the legacy `https://funmoments.sa/api/v1` base API unless overridden at runtime. The repo also contains archive and packaging directories that should not be deployed. The local database export shows seeded/demo data, so production migration must be treated carefully rather than assuming an empty database.

## Repository State

### Top-level layout

- Flutter mobile app: repository root (`lib/`, `android/`, `ios/`, `web/`, `windows/`)
- Laravel backend: [`backend/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend)
- React admin: [`admin-react/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/admin-react)
- Documentation: [`docs/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/docs)
- Archive / legacy material: [`Archive/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/Archive)
- Local helper scripts: [`scripts/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/scripts)

### Current repository classification

- Current:
  - Flutter app root
  - Laravel backend
  - React admin
  - `scripts/`
  - `docs/`
- Historical / legacy / archive:
  - `Archive/`
  - `backend/custom/`
  - `backend/__rootFiles/`
  - `data-1782730720165.csv` as a local database export artifact
- Generated / local environment:
  - `.dart_tool/`
  - `build/`
  - `node_modules` if present in subprojects
  - compiled assets and build outputs

### Git state

Git metadata is not usable from this workspace shell:

- the `.git` directory exists
- `git status`, `git log`, and `git branch` report: `fatal: not a git repository`

So I could not reliably extract branch name, latest commit, or clean/dirty status from Git in this environment.

## Architecture

### Current platform architecture

- Mobile:
  - one Flutter application for customer and provider roles
  - runtime base API is configurable through `BASE_API`
- Website:
  - Laravel public/customer website served from the backend
  - Laravel also serves buyer and seller dashboards
- Admin:
  - Laravel admin routes exist in the backend
  - a separate React admin dashboard exists in `admin-react/`
- Backend:
  - Laravel 10 application
  - PHP 8.1+ based on composer constraints
- Database:
  - PostgreSQL
  - local database name: `funmoments`

### Backend role coverage

The Laravel backend contains:

- public web routes
- buyer/customer dashboard routes
- seller/provider dashboard routes
- admin routes
- API routes under `api/v1`

That means the backend is the real business core for the web site, mobile app, and admin operations.

### Archive / duplicate source warning

`Archive/`, `backend/custom/`, and `backend/__rootFiles/` look like packaging or historical copies. They are not the active runtime source and should not be deployed as production code.

## Mobile Application

### Runtime backend configuration

The Flutter app currently resolves its API base from:

- [`lib/view/utils/others_helper.dart`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/lib/view/utils/others_helper.dart)

Current behavior:

- `BASE_API` is read from a compile-time `String.fromEnvironment`
- the default fallback is `https://funmoments.sa/api/v1`

So if `--dart-define=BASE_API=...` is not passed, the app targets the legacy production API.

### Mobile dependencies on backend services

The Flutter app references:

- authentication APIs
- image and media APIs
- booking APIs
- payment APIs
- chat APIs
- notification-related services
- provider/service APIs

The app also initializes:

- Firebase
- Google Maps
- local notifications
- location permission flow

### Mobile deployment meaning

The Flutter app is not “deployed” to the VPS. It must be built separately for Android/iOS/web. The VPS requirement for the mobile app is only that it points to the correct backend API and that the backend exposes the required endpoints.

## Laravel Backend

### Backend technology

The backend is a Laravel 10 application. Relevant evidence:

- [`backend/composer.json`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/composer.json)

It uses Laravel and supporting packages for:

- Sanctum auth
- permissions / roles
- datatables
- social login
- Pusher
- Livewire
- modular feature toggles
- payment integrations

### Routes and surfaces

Relevant route files:

- [`backend/routes/web.php`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/web.php)
- [`backend/routes/api.php`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/api.php)
- [`backend/routes/admin.php`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/admin.php)
- [`backend/routes/buyer.php`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/buyer.php)
- [`backend/routes/seller.php`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/seller.php)

This confirms the backend already contains:

- the public website
- the booking flow
- buyer/customer dashboards
- provider/seller dashboards
- admin dashboards and CMS routes
- JSON API endpoints for the mobile app and admin tooling

### Views

Relevant view tree:

- [`backend/resources/views`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/resources/views)

This is a Laravel Blade / Livewire style website implementation, not a React frontend.

## Website

The public website is already part of the Laravel backend, not a separate app.

Evidence from the route structure shows support for:

- homepage
- registration / login
- password reset / verification
- service discovery
- booking
- orders
- support
- pages / blogs / menus
- buyer and seller dashboards

That means the website should be deployed with the backend as one unit.

## Admin Dashboard

### Laravel admin

The backend has its own admin route set in `backend/routes/admin.php`.

### React admin

There is also a separate React admin project:

- [`admin-react/package.json`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/admin-react/package.json)

This project is a separate frontend and must be built and hosted independently if the React dashboard is intended to replace or sit beside the old admin UI.

## Database

### Local database

Current database configuration in:

- [`backend/.env`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/.env)

Configured values:

- DB driver: PostgreSQL
- DB name: `funmoments`
- DB host: `127.0.0.1`
- DB port: `5432`
- DB username: `postgres`

### Database evidence

The exported CSV file:

- [`data-1782730720165.csv`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/data-1782730720165.csv)

shows the database already contains seeded/demo data for:

- users
- admins
- orders
- services
- blogs
- categories
- menus
- pages
- widgets
- payout requests
- support tickets
- static options

This means the platform is not starting from an empty database.

## Environment Configuration

### Current backend env highlights

Relevant values from [`backend/.env`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/.env):

- `APP_URL=http://127.0.0.1:8000`
- `DB_CONNECTION=pgsql`
- `DB_DATABASE=funmoments`
- `DB_HOST=127.0.0.1`
- `DB_PORT=5432`
- mail is configured through SendGrid SMTP variables
- payment providers include Paystack, Flutterwave, PayPal, Stripe, Razorpay, Paytm, Midtrans, PayFast, Cashfree, Instamojo, and Mercado Pago variables
- social login variables are present but many are empty
- Pusher variables exist
- Redis variables exist
- currency is set through platform variables

### Module status flags

From [`backend/modules_statuses.json`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/modules_statuses.json):

- LiveChat: disabled
- Subscription: disabled
- JobPost: disabled
- Wallet: disabled

These modules exist in the codebase but are not enabled in the current local backend state.

## External Services

The current platform expects configuration for:

- Firebase
- Google Maps
- SMTP mail
- payment gateways
- Pusher / real-time messaging
- social sign-in providers
- possibly storage/CDN depending on deployment target

The app starts with Firebase initialization and map integration, so those services matter for a complete production deployment.

## Build Status

### Mobile

- Flutter source exists
- exact installed Flutter/Dart toolchain version could not be reliably queried in this shell
- the project is Dart 3 compatible by `pubspec.yaml`

### Laravel backend

- Laravel backend source is present
- the backend is structured for local development and deployment

### React admin

- the React admin is a real separate frontend project
- it has its own build pipeline

### Database

- PostgreSQL database exists locally
- data export shows seeded content

## Existing Risks

### High-risk items

- The Flutter app still defaults to the legacy `https://funmoments.sa/api/v1` base API unless overridden at build time.
- The database export shows demo/seeding data, so production migration requires deliberate data strategy.
- The React admin is separate from the Laravel admin, so deployment architecture must be chosen carefully to avoid split-source confusion.
- Multiple payment integrations are present, so only the configured gateways should be enabled for any given environment.
- Several platform add-on modules are currently disabled in backend state.
- The repository contains archive and duplicate packaging directories that should not be shipped as active code.

### Verification limitations in this audit

- Git state could not be read because the shell does not recognize the workspace as a Git repository.
- I did not perform any code changes.
- I did not run a deployment.

## Existing Uncommitted Work

I could not reliably extract Git branch or diff state in this shell because Git reports the workspace as not a repository.

Separately, the filesystem contains local build and environment artifacts such as:

- `.dart_tool/`
- `build/`
- package lockfiles
- local export CSV

These are local artifacts, not evidence of source edits by this audit.

## Production Blockers

### Blockers to clean deployment

1. Flutter default API still points to the legacy `funmoments.sa` endpoint unless explicitly overridden.
2. Backend environment still uses localhost values that must be replaced on a VPS.
3. Real production credentials and gateway settings need environment-by-environment verification.
4. Disabled module flags must be reviewed before expecting add-ons to work in production.
5. Database migration cannot assume demo data should be copied unchanged.
6. Git metadata is not usable in this shell, so a proper source-control release baseline still needs to be established.

## Baseline Recommendation

The safest production path is:

1. Treat the Laravel backend as the first deployable unit.
2. Move the PostgreSQL schema and required data to a VPS-hosted database.
3. Configure backend `.env` for production URLs, mail, storage, queues, real-time, maps, and payment services.
4. Deploy the Laravel public website, buyer dashboard, seller dashboard, and API together.
5. Deploy the React admin only if it is intended to be the active admin frontend.
6. Build the Flutter app separately and point it to the new VPS API using `--dart-define=BASE_API=...`.
7. Validate real authentication, booking, media, payment, and notification flows before exposing production users.

## Evidence

- [backend/.env](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/.env)
- [lib/view/utils/others_helper.dart](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/lib/view/utils/others_helper.dart)
- [lib/main.dart](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/lib/main.dart)
- [lib/firebase_options.dart](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/lib/firebase_options.dart)
- [android/app/build.gradle](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/android/app/build.gradle)
- [android/build.gradle](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/android/build.gradle)
- [android/gradle.properties](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/android/gradle.properties)
- [android/app/src/main/AndroidManifest.xml](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/android/app/src/main/AndroidManifest.xml)
- [android/app/google-services.json](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/android/app/google-services.json)
- [backend/composer.json](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/composer.json)
- [backend/package.json](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/package.json)
- [backend/routes/web.php](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/web.php)
- [backend/routes/api.php](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/api.php)
- [backend/routes/admin.php](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/admin.php)
- [backend/routes/buyer.php](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/buyer.php)
- [backend/routes/seller.php](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes/seller.php)
- [backend/resources/views](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/resources/views)
- [admin-react/package.json](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/admin-react/package.json)
- [backend/modules_statuses.json](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/modules_statuses.json)
- [data-1782730720165.csv](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/data-1782730720165.csv)

## CURRENT PRODUCTION BASELINE

The current local platform is a hybrid Laravel + Flutter + React stack with seeded PostgreSQL data, local-development environment values, disabled add-on modules, and a Flutter default API that still falls back to the legacy `funmoments.sa` endpoint unless overridden at build time.
