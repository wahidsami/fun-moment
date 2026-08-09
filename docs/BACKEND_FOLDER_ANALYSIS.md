# Backend Folder Analysis

**Generated:** May 19, 2026  
**Folder:** `backend/`  
**Purpose:** Laravel backend API and admin dashboard for the Fun Moments mobile app

---

## 1. Overview

The `backend/` folder contains the complete Laravel application that powers the Fun Moments service marketplace backend and admin dashboard. It includes:
- REST API endpoints used by the Flutter mobile app
- Admin dashboard and public web interface
- Business logic, authentication, payment processing, and content management
- Database migrations, seeders, and test scaffolding
- Frontend build assets, public assets, and resource views

This folder is the working backend root after reorganizing the original `Archive/@core` content.

> Note: The `Archive/@core` source contains the original backend connection and integration metadata. For the most complete view of how the Flutter app connects to the backend, refer to `docs/ARCHIVE_BACKEND_ANALYSIS.md`.

---

## 2. Top-Level Contents

```
backend/
├── .env
├── .git/
├── .gitignore
├── .htaccess
├── ajax.php
├── app/
├── artisan
├── assets/
├── bootstrap/
├── change-logs.json
├── composer.json
├── composer.lock
├── config/
├── custom/
├── database/
├── index.php
├── license.json
├── Modules/
├── modules_statuses.json
├── package-lock.json
├── package.json
├── phpunit.xml
├── public/
├── readme.md
├── resources/
├── routes/
├── server.php
├── storage/
├── tests/
├── vendor/
├── webpack.mix.js
└── __rootFiles/
```

---

## 3. Purpose of Key Root Files

- **`.env`** - environment configuration for database, mail, payment, and service keys
- **`artisan`** - Laravel CLI tool for migrations, cache, server, and other commands
- **`composer.json`** - PHP dependency manifest and autoload configuration
- **`composer.lock`** - locked PHP dependency versions
- **`package.json`** - Node dependency manifest for frontend assets and build tooling
- **`package-lock.json`** - locked npm dependency versions
- **`webpack.mix.js`** - Laravel Mix configuration for compiling CSS/JS
- **`phpunit.xml`** - PHPUnit test runner configuration
- **`public/index.php`** - public Laravel web entry point
- **`server.php`** - alternate PHP development server entry
- **`readme.md`** - backend documentation summary and tech stack
- **`Modules/`** - modular Laravel structure enabled by `nwidart/laravel-modules`
- **`__rootFiles/`** - extra static files included from the archive source

---

## 4. Composer Dependencies

The backend is built with **Laravel 10.x** and depends on the following key packages:

### Core Framework
- `php:^8.1`
- `laravel/framework:^10.10`
- `laravel/sanctum:^3.2`
- `laravel/socialite:^5.2`
- `laravel/tinker:^2.0`

### Real-Time, Authentication & Admin
- `livewire/livewire:^2.12`
- `spatie/laravel-permission:^5.5`
- `pusher/pusher-php-server:^7.0.6`
- `twilio/sdk:^7.4`
- `stevebauman/location:^7.3.2`

### Payments & Integration
- `xgenious/paymentgateway:^4.6.0`
- `xgenious/xgapiclient:^3.0`
- `guzzlehttp/guzzle:^7.0.1`
- `guzzlehttp/psr7:^2.5`
- `yajra/laravel-datatables-oracle:~10.0`

### Content, Media & Utilities
- `barryvdh/laravel-dompdf:^v3.0.0`
- `intervention/image:^2.5`
- `spatie/laravel-sitemap:^6.3`
- `mews/purifier:^3.3`
- `matanyadaev/laravel-eloquent-spatial:^4.2.1`
- `kkomelin/laravel-translatable-string-exporter:^1.11`
- `nwidart/laravel-modules:^8.3`

### Developer & Testing
- `spatie/laravel-ignition:2.0`
- `laravel/ui:^4.0`
- `mockery/mockery:^1.0`
- `nunomaduro/collision:^7.0`
- `phpunit/phpunit:^10.0`

### Autoload Configuration
- PSR-4 mapping:
  - `App\` -> `app/`
  - `Modules\` -> `Modules/`
- Files autoloaded:
  - `app/Helpers/helpers.php`
- Classmap autoloaded:
  - `database/seeds`
  - `database/factories`

---

## 5. Laravel Application Structure

### `app/`

This folder holds the application core logic, including models, controllers, middleware, requests, events, mail, notifications, and helper classes.

#### Main contents
- `Accountdeactive.php`
- `Admin.php`
- `AdminCommission.php`
- `AdminNotice.php`
- `AdminNotification.php`
- `AdminRole.php`
- `AmountSettings.php`
- `Blog.php`
- `BlogComment.php`
- `Brand.php`
- `Category.php`
- `ChildCategory.php`
- `Country.php`
- `CustomFontImport.php`
- `Day.php`
- `EditServiceHistory.php`
- `ExtraService.php`
- `FormBuilder.php`
- `GalleryCategory.php`
- `HeaderSlider.php`
- `Language.php`
- `Location.php`
- `MediaUpload.php`
- `Menu.php`
- `MetaData.php`
- `OnlineServiceFaq.php`
- `Order.php`
- `OrderAdditional.php`
- `OrderBookingDateTimeChange.php`
- `OrderCompleteDecline.php`
- `OrderInclude.php`
- `OrderRequestCompleteHistory.php`
- `Page.php`
- `PageBuilder.php`
- `PayoutRequest.php`
- `Report.php`
- `ReportChatMessage.php`
- `Review.php`
- `Schedule.php`
- `SellerVerify.php`
- `Service.php`
- `Serviceadditional.php`
- `ServiceArea.php`
- `Serviceattribute.php`
- `Servicebenifit.php`
- `ServiceCity.php`
- `ServiceCoupon.php`
- `Serviceinclude.php`
- `Slider.php`
- `SocialIcon.php`
- `StaticOption.php`
- `Subcategory.php`
- `SupportTicket.php`
- `SupportTicketMessage.php`
- `Tag.php`
- `Tax.php`
- `ToDoList.php`
- `User.php`
- `UserUniqueKey.php`
- `Widgets.php`

#### Subdirectories
- `Actions/` - domain action classes
- `Console/` - artisan commands
- `Events/` - event classes
- `Exceptions/` - custom exception types
- `FormBuilder/` - page/form builder engine
- `GalleryCategory/` - gallery management
- `Helpers/` - helper utilities
- `Http/` - controllers, middleware, requests, Livewire components
- `Listeners/` - event listeners
- `Mail/` - email notifications
- `MenuBuilder/` - menu builder logic
- `Notifications/` - app notifications
- `PageBuilder/` - page builder implementation
- `Providers/` - service providers
- `WidgetsBuilder/` - widget builder logic

### `app/Http/`

Key folders:
- `Controllers/` - request handlers for web and API requests
- `Middleware/` - HTTP middleware
- `Requests/` - form request validation classes
- `Livewire/` - Livewire component classes
- `Kernel.php` - HTTP kernel registration

---

## 6. Routing Structure

### Route files
- `routes/web.php` - main web routes for frontend and admin dashboard
- `routes/api.php` - mobile app API endpoints
- `routes/admin.php` - admin panel route definitions
- `routes/seller.php` - seller-specific routes
- `routes/buyer.php` - buyer-specific routes
- `routes/channels.php` - broadcast channel definitions for real-time events
- `routes/console.php` - artisan command routes

This separation indicates a multi-role system with distinct buyer, seller, and admin routing.

---

## 7. Public Assets and Frontend

### `public/`
Contains static assets and the public Laravel entry point.

Main `public/` contents:
- `index.php` - Laravel public web entry point
- `robots.txt` - search engine rules
- `favicon.ico`
- `env-sample.txt`
- `web.config` - IIS configuration
- `css/` - compiled stylesheet files
- `js/` - compiled JavaScript files
- `vendor/` - published package assets
- `assets/` - frontend resources used by admin and site
- `common/` - shared fonts and assets

### `resources/`
Contains source assets and view templates.

Main `resources/` contents:
- `js/` - frontend JS source files
- `sass/` - SCSS source files used by Laravel Mix
- `lang/` - localization files
- `views/` - Blade templates
  - `auth/`
  - `backend/`
  - `components/`
  - `errors/`
  - `frontend/`
  - `layouts/`
  - `livewire/`
  - `mail/`
  - `vendor/`

This confirms the backend includes a full web UI with admin and frontend views.

---

## 8. Assets Folder

### `backend/assets/`
This folder contains merged assets from the original archive data and now serves as a shared resource repository for backend build assets.

Includes:
- `backend/` - admin/backend asset theme
- `common/` - shared asset resources
- `frontend/` - front-facing site assets
- `uploads/` - user-uploaded and import assets

It is separate from `public/`, which is the actual served asset folder. The `backend/assets/` directory appears to be a source library for the Laravel admin and site themes.

---

## 9. Database and Storage

### `database/`
Key contents:
- `migrations/` - database schema migration files
- `factories/` - model factory templates for testing and seeding
- `seeds/` - database seeders for initial data
- `static_options.json` - static settings payload

### `storage/`
Standard Laravel storage directories for:
- `app/` - application file storage
- `framework/` - framework cache and views
- `logs/` - application logs
- `public/` - files served via `storage:link`

---

## 10. Modules and Extensibility

### `Modules/`
This folder is available for modular feature organization via `nwidart/laravel-modules`. It may be unused or reserved for future modularization.

### `modules_statuses.json`
Used to track enabled/disabled module state.

The composer autoload configuration confirms modules can be mapped under `Modules\`.

---

## 11. Development Tooling and Scripts

### `composer.json` scripts
- `post-autoload-dump` - runs Laravel package discovery automatically
- `post-root-package-install` - copies `.env.example` to `.env` if missing
- `post-create-project-cmd` - generates `APP_KEY`

### Node/Frontend tooling
The backend includes `package.json` and `webpack.mix.js`, indicating Laravel Mix is used to compile frontend assets and admin themes.

### Testing
The backend includes a `tests/` folder and `phpunit.xml`, supporting unit and feature tests.

---

## 12. Key Backend Capabilities

The backend supports the following feature set:
- Multi-vendor marketplace admin
- Buyer and seller roles with separate routes
- REST API for mobile app communication
- Livewire-powered admin dashboard
- Payment gateway integration through XGenius payment gateway package
- Support tickets and reporting system
- Blog and CMS page builder
- Multi-language support with translatable strings
- Real-time notifications via Pusher
- SMS integration via Twilio
- Role and permission management via Spatie package
- Sitemap generation and SEO support
- PDF generation with DomPDF
- Image processing with Intervention Image
- Location-based services using geospatial packages

---

## 13. Dashboard Roles and Functional Sections

### Admin Dashboard

The admin dashboard is served through `routes/admin.php` and the backend views under `resources/views/backend/`. It is the primary web control panel for site administration.

Core admin functions include:
- Dashboard home, dark mode toggle, media uploads, and dashboard variant selection.
- Content management: blogs, blog categories, blog tags, dynamic pages, form builder, email templates, widgets, and menu builder.
- Marketplace configuration: brands, sliders, categories, subcategories, child categories, country/city/area/location management, service zones, and tax settings.
- Service management: review and service detail viewing, admin-created services, service attribute management, coupon listing, service booking settings, service zone settings, and login/register settings.
- Order management: view all orders, cancel orders, order details, complete-request handling, seller/buyer reports, report chat, order success page settings, and order-related notifications.
- User management: frontend user lists, deactivated users, seller verification, seller profile views, email/OTP verification codes, and token-based login into a frontend account.
- Admin and staff management: admin users and roles, admin notices, profile and password settings, language management, and general site settings.
- General settings: site identity, payment gateway settings, Pusher settings, OTP settings, SMTP settings, SEO settings, typography, scripts, custom CSS/JS, cache management, sitemap settings, GDPR settings, and database upgrade.
- Support: ticket management, admin ticket details, and notification management.

Limitations of the admin dashboard:
- It is a web administration panel only; it does not provide mobile-native UI access or embed the Flutter app experience.
- It is not a full analytics/BI suite; the route structure focuses on management and configuration rather than advanced reporting dashboards.
- It does not directly expose buyer/seller frontend dashboards, and it cannot perform buyer or seller account actions except through admin user management and reports.
- There is no obvious inventory or wallet module in the inspected routes; the dashboard appears oriented toward service marketplace configuration rather than retail inventory tracking.

### Seller Dashboard

Seller dashboard functions are defined in `routes/seller.php` and are intended for seller accounts.

Seller capabilities include:
- Seller profile viewing and editing, account settings, account deactivation, account deletion, and logout.
- Service lifecycle management: add/edit/delete services, manage service attributes, enable/disable service availability, and manage service coupon rules.
- Scheduling and availability: add working days, manage schedules, update multiple schedules, and configure booking date/time options.
- Order management: view service orders, job orders, order details, change order and payment status, cancel orders, and view active/complete/delivered/cancelled/pending orders.
- Reporting and disputes: report orders, view report lists, chat with admin on reports, and view decline history.
- Payments and payouts: request payouts, create payout requests, view payout request details, and access payout/order invoices.
- Customer service: support ticket creation and management, ticket priority/status updates, ticket view, and ticket message sending.
- Notifications and task management: view notifications, clear notifications, and manage a seller-specific to-do list.
- Seller zone configuration: manage seller service zones and seller profile verification.

Seller dashboard limitations:
- It does not include admin-level content or platform configuration screens.
- It is focused on seller-owned services and orders, not on buyer interactions beyond order fulfillment and reporting.
- Payout processing is likely request-based; there is no explicit route for automated settlement or escrow functions.

### Buyer Dashboard

Buyer dashboard functions are defined in `routes/buyer.php`.

Buyer capabilities include:
- Buyer profile viewing and editing, account settings, account deactivation, account deletion, and logout.
- Order tracking: view all orders, view job orders, access order details, approve or decline order completion requests, and review decline history.
- Payment flows: access order invoice details and handle extra service payment success/cancel flows.
- Support and reporting: report orders, view report lists, open report chat to admin, and submit support tickets.
- Notifications: view all notifications and clear notifications.

Buyer dashboard limitations:
- It does not provide seller or admin tools such as service management, payout requests, or global site settings.
- It does not appear to support wishlist, saved services, or direct buyer-to-seller messaging outside report/admin chat.
- There is no explicit buyer-side wallet or refund management route visible in the inspected routes.

---

## 14. How the Backend Connects to the Mobile App

### API interaction
The mobile app uses `routes/api.php` endpoints to perform operations such as:
- authentication and profile management
- service listing and booking
- order creation and tracking
- payment processing
- chat and notifications
- support tickets

### Authentication
Laravel Sanctum is used for API token authentication.

### Real-time
Broadcast channels defined in `routes/channels.php` are used for live notifications and chat updates.

---

## 15. Recommended Backend Setup Steps

From `backend/`:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run dev
php artisan storage:link
php artisan serve
```

---

## 16. Backend Organization Notes

### Current status
The backend folder is now the active Laravel application root.

### What is complete
- Full Laravel folder structure is in place
- Public and source asset folders are present
- Routes, config, database, and app logic are available
- Composer and npm dependencies are defined

### What remains
- The `.env` file must be configured for your environment
- `storage/` permissions may need adjustment
- `backend/assets/` is present as source-level theming assets and may need cleanup depending on usage

---

## 17. Summary

This document describes the complete contents and structure of the `backend/` folder for Fun Moments. It is a full Laravel backend ready for development and enhancement, with separate web, API, and admin components. The backend folder is now the canonical backend root for the project.
