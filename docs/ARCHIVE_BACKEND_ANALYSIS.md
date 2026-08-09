# Archive Backend Analysis - Laravel Dashboard & API

**Generated:** May 19, 2026  
**Component:** Backend API & Admin Dashboard  
**Technology:** Laravel 10 (PHP)  
**Status:** Production-Ready Backend System

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Directory Structure](#directory-structure)
4. [Technology Stack](#technology-stack)
5. [Database Models](#database-models)
6. [API Endpoints](#api-endpoints)
7. [Admin Dashboard Features](#admin-dashboard-features)
8. [Key Dependencies](#key-dependencies)
9. [Module System](#module-system)
10. [Configuration & Setup](#configuration--setup)
11. [Integration with Flutter App](#integration-with-flutter-app)
12. [Organization Recommendations](#organization-recommendations)

---

## 🎯 Overview

The **Archive/@core** folder contains the complete **Laravel PHP backend** for Fun Moments marketplace. It serves as:

1. **REST API Server** - Provides endpoints for the Flutter mobile app
2. **Admin Dashboard** - Web-based management interface for marketplace operators
3. **Business Logic Engine** - Processes orders, payments, user management
4. **Content Management System (CMS)** - Blog, pages, sliders, widgets
5. **Database Management** - 50+ Eloquent models with relationships
6. **Real-Time Services** - Pusher integration for live updates

### Project Information
- **Name:** FunMoment Service Marketplace
- **Owner:** funmoment.sa
- **Type:** Multi-vendor on-demand service marketplace
- **License:** Proprietary
- **Version:** Laravel 10.x

> This document is the authoritative source for how the Flutter mobile app connects to the Laravel backend. Use `docs/ARCHIVE_BACKEND_ANALYSIS.md` as the primary reference for app-backend integration details.

---

## 🏗️ Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────────┐
│                   Fun Moments Complete Ecosystem                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────┐    ┌──────────────────────────┐
│   📱 MOBILE CLIENT              │    │ 🖥️ ADMIN DASHBOARD       │
│   (Flutter - lib/)              │    │ (Laravel Livewire)       │
├─────────────────────────────────┤    ├──────────────────────────┤
│ • User Interfaces               │    │ • Service Management     │
│ • Service Browsing              │    │ • User Management        │
│ • Booking Management            │    │ • Order Management       │
│ • Real-time Chat                │    │ • Payment Monitoring     │
│ • Notifications                 │    │ • Analytics & Reports    │
│ • Payment Processing            │    │ • Content Management     │
│ • Profile Management            │    │ • Seller Verification    │
└─────────────────────────────────┘    └──────────────────────────┘
              │                                      │
              └──────────────┬───────────────────────┘
                             │
                    REST API (HTTP/JSON)
                    WebSocket (Chat/Notifications)
                             │
                             ↓
        ┌────────────────────────────────────────┐
        │  📦 BACKEND API (Archive/@core)       │
        ├────────────────────────────────────────┤
        │ • Laravel 10 Framework                 │
        │ • API Routes & Controllers             │
        │ • Business Logic Services              │
        │ • Database Models (50+)                │
        │ • Payment Processing (15+ gateways)    │
        │ • Authentication (Sanctum)             │
        │ • Real-time (Pusher)                   │
        │ • CMS & Content Management             │
        └────────────────────────────────────────┘
                             │
                             ↓
        ┌────────────────────────────────────────┐
        │  💾 DATABASE (MySQL)                  │
        ├────────────────────────────────────────┤
        │ • Users                                │
        │ • Services                             │
        │ • Orders                               │
        │ • Payments                             │
        │ • Reviews & Ratings                    │
        │ • Messages & Chat                      │
        │ • Content (Blog, Pages)                │
        │ • Settings & Configuration             │
        └────────────────────────────────────────┘
```

### Data Flow

```
Mobile App User          Admin Dashboard User
     │                          │
     └─────────┬────────────────┘
               │
        HTTP/REST API Calls
               │
    ┌──────────┴──────────┐
    │                     │
  GET/POST            WebSocket
  /api/services       /notifications
  /api/orders         /chat
  /api/auth           /updates
    │                 │
    └────────┬────────┘
             │
        Laravel Backend
        • Validate Request
        • Process Logic
        • Database Query
        • Return Response
             │
    ┌────────┴────────┐
    │                 │
  JSON Response    Live Updates
  200/400/500      (Pusher)
    │                 │
    └────────┬────────┘
             │
    Mobile/Admin Updates UI
```

---

## 📁 Directory Structure

### Root Level

```
Archive/
├── 📁 @core/                    # ⭐ MAIN LARAVEL APPLICATION
├── 📁 assets/                   # Shared assets (backend, frontend, common, uploads)
├── 📄 index.php                 # Root entry point (redirects to @core)
├── 📄 web.config                # IIS server configuration
├── 📁 vendor/                   # Composer packages (root level)
└── 📁 __MACOSX/                 # macOS metadata (from extraction)
```

### @core/ - Main Application Structure

```
@core/
│
├── 📄 index.php                 # Entry point - checks .env and loads Laravel
├── 📄 server.php                # PHP development server
├── 📄 artisan                   # Laravel CLI tool
│
├── 📄 composer.json             # PHP dependencies definition
├── 📄 composer.lock             # Locked dependency versions
├── 📄 package.json              # Node.js dependencies (frontend build)
├── 📄 package-lock.json         # Locked npm versions
│
├── 📄 webpack.mix.js            # Laravel Mix (Webpack) build config
├── 📄 phpunit.xml               # PHPUnit testing configuration
├── 📄 .env                      # 🔐 Environment variables (DB, API keys)
├── 📄 .env.example              # Template for .env file
├── 📄 .gitignore                # Git ignore patterns
├── 📄 .htaccess                 # Apache URL rewriting
│
├── 📄 readme.md                 # Project documentation
├── 📄 change-logs.json          # Version/change history
├── 📄 license.json              # License information
├── 📄 modules_statuses.json     # Module activation status
│
├── 📄 .git/                     # Version control repository
│
├── 📁 app/                      # 🔥 APPLICATION CODE
│   ├── 📄 Accountdeactive.php
│   ├── 📄 Admin.php             # Admin model
│   ├── 📄 AdminCommission.php   # Commission tracking
│   ├── 📄 AdminNotice.php       # Admin notifications
│   ├── 📄 AdminNotification.php
│   ├── 📄 AdminRole.php         # Admin roles & permissions
│   ├── 📄 Blog.php              # Blog model
│   ├── 📄 BlogComment.php       # Blog comments
│   ├── 📄 Brand.php             # Service brands
│   ├── 📄 Category.php          # Service categories
│   ├── 📄 ChildCategory.php     # Sub-categories level 2
│   ├── 📄 Country.php           # Countries
│   ├── 📄 CustomFontImport.php  # Custom fonts
│   ├── 📄 DateTime.php          # DateTime utilities
│   ├── 📄 Day.php               # Day model
│   ├── 📄 EditServiceHistory.php # Service edit tracking
│   ├── 📄 ExtraService.php      # Service add-ons/extras
│   ├── 📄 FormBuilder.php       # Dynamic form builder
│   ├── 📄 GalleryCategory.php   # Gallery organization
│   ├── 📄 HeaderSlider.php      # Homepage slider
│   ├── 📄 Language.php          # Language/localization
│   ├── 📄 Location.php          # Geographic locations
│   ├── 📄 MediaUpload.php       # File uploads
│   ├── 📄 Menu.php              # Menu items
│   ├── 📄 MetaData.php          # SEO metadata
│   ├── 📄 OnlineServiceFaq.php  # FAQ management
│   ├── 📄 Order.php             # ⭐ Orders/Bookings
│   ├── 📄 OrderAdditional.php   # Order extras
│   ├── 📄 OrderBookingDateTimeChange.php # Reschedule requests
│   ├── 📄 OrderCompleteDecline.php # Order completion/cancellation
│   ├── 📄 OrderInclude.php      # Order inclusions
│   ├── 📄 OrderRequestCompleteHistory.php # Order audit log
│   ├── 📄 Page.php              # Static pages
│   ├── 📄 PageBuilder.php       # Page builder config
│   ├── 📄 PayoutRequest.php     # Seller payout requests
│   ├── 📄 Report.php            # User reports
│   ├── 📄 ReportChatMessage.php # Message reports
│   ├── 📄 Review.php            # Service reviews & ratings
│   ├── 📄 Schedule.php          # Service scheduling
│   ├── 📄 SellerVerify.php      # Seller verification status
│   ├── 📄 Service.php           # ⭐ Services
│   ├── 📄 Serviceadditional.php # Service extra options
│   ├── 📄 ServiceArea.php       # Service geographic areas
│   ├── 📄 Serviceattribute.php  # Service attributes
│   ├── 📄 Servicebenifit.php    # Service benefits
│   ├── 📄 ServiceCity.php       # Service cities
│   ├── 📄 ServiceCoupon.php     # Discount coupons
│   ├── 📄 Serviceinclude.php    # Service inclusions
│   ├── 📄 Slider.php            # Image sliders
│   ├── 📄 SocialIcon.php        # Social media links
│   ├── 📄 StaticOption.php      # System settings/config
│   ├── 📄 Subcategory.php       # Category level 3
│   ├── 📄 SupportTicket.php     # Support ticket system
│   ├── 📄 SupportTicketMessage.php # Support messages
│   ├── 📄 Tag.php               # Content tags
│   ├── 📄 Tax.php               # Tax rates & calculations
│   ├── 📄 ToDoList.php          # Todo list feature
│   ├── 📄 User.php              # ⭐ User model
│   ├── 📄 UserUniqueKey.php     # User identification
│   ├── 📄 Widgets.php           # Widget system
│   │
│   ├── 📁 Actions/              # Action classes (domain logic)
│   ├── 📁 Http/                 # HTTP handlers
│   │   ├── Controllers/         # API & Web Controllers
│   │   ├── Requests/            # Form requests & validation
│   │   ├── Resources/           # API response transformers
│   │   └── Middleware/          # Request middleware
│   ├── 📁 Services/             # Business logic services
│   ├── 📁 Mail/                 # Email notifications
│   ├── 📁 Listeners/            # Event listeners
│   ├── 📁 Events/               # Domain events
│   ├── 📁 Exceptions/           # Custom exceptions
│   ├── 📁 Providers/            # Service providers
│   ├── 📁 Notifications/        # Notification classes
│   ├── 📁 Console/              # Artisan commands
│   ├── 📁 Helpers/              # Helper functions
│   ├── 📁 MenuBuilder/          # Menu building logic
│   ├── 📁 PageBuilder/          # Page building logic
│   └── 📁 WidgetsBuilder/       # Widget building logic
│
├── 📁 Modules/                  # 🔌 MODULAR FEATURES (currently empty)
│   └── (Modules can be added for scalability)
│
├── 📁 bootstrap/                # Application bootstrap
│   ├── app.php                  # Bootstrap container
│   └── cache/                   # Bootstrap cache
│
├── 📁 config/                   # ⚙️ CONFIGURATION
│   ├── app.php                  # App configuration
│   ├── auth.php                 # Authentication config
│   ├── database.php             # Database connection
│   ├── cache.php                # Cache configuration
│   ├── queue.php                # Job queue config
│   ├── mail.php                 # Email configuration
│   ├── filesystems.php          # Storage configuration
│   ├── logging.php              # Log configuration
│   └── (other configs)
│
├── 📁 database/                 # 📊 DATABASE
│   ├── migrations/              # Database schema migrations
│   ├── factories/               # Model factories for testing
│   └── seeders/                 # Database seeders
│
├── 📁 resources/                # 🎨 FRONTEND RESOURCES
│   ├── views/                   # Blade templates
│   │   ├── admin/               # Admin dashboard views
│   │   ├── frontend/            # Public-facing views
│   │   └── components/          # Reusable components
│   ├── css/                     # Stylesheets
│   ├── js/                      # JavaScript files
│   └── lang/                    # Localization files
│
├── 📁 routes/                   # 🛣️ ROUTING
│   ├── web.php                  # Web routes (dashboard, admin)
│   ├── api.php                  # API routes (mobile app endpoints)
│   └── channels.php             # WebSocket channels
│
├── 📁 storage/                  # 💾 STORAGE
│   ├── app/                     # Application files
│   ├── logs/                    # Application logs
│   ├── framework/               # Framework files
│   └── public/                  # Public uploads (symlinked to public/)
│
├── 📁 public/                   # 🌐 PUBLIC (Web Root)
│   ├── index.php                # Public entry point
│   ├── css/                     # Compiled CSS
│   ├── js/                      # Compiled JavaScript
│   ├── images/                  # Public images
│   └── uploads/                 # User uploads (symlinked from storage/)
│
├── 📁 tests/                    # ✅ TESTING
│   ├── Unit/                    # Unit tests
│   ├── Feature/                 # Feature tests
│   └── CreatesApplication.php   # Test setup
│
├── 📁 vendor/                   # 📦 COMPOSER PACKAGES
│   └── (All PHP dependencies)
│
├── 📁 custom/                   # 🎯 CUSTOM CODE/EXTENSIONS
│   └── (Custom implementations)
│
└── 📁 __rootFiles/              # 📄 ROOT FILES
    └── (Additional root-level files)
```

### assets/ - Shared Assets

```
assets/
├── 📁 backend/                  # Backend-specific assets
│   └── (Backend images, styles, etc.)
├── 📁 frontend/                 # Frontend-specific assets
│   └── (Frontend images, styles, etc.)
├── 📁 common/                   # Shared assets
│   └── (Common images, styles, etc.)
└── 📁 uploads/                  # User-generated content
    ├── avatars/                 # User profile pictures
    ├── services/                # Service images
    ├── orders/                  # Order-related uploads
    └── documents/               # User documents
```

---

## 🛠️ Technology Stack

### Backend Framework & Core
| Component | Version | Purpose |
|-----------|---------|---------|
| **PHP** | 8.1+ | Server-side language |
| **Laravel** | 10.x | Web framework |
| **MySQL** | 5.7+ | Database |
| **Apache/IIS** | Latest | Web server |

### Frontend & UI
| Component | Version | Purpose |
|-----------|---------|---------|
| **Livewire** | 2.x | Real-time component framework |
| **Laravel Mix** | Latest | Build tool (Webpack wrapper) |
| **Blade** | Built-in | Template engine |
| **Bootstrap/Tailwind** | Varies | CSS framework |

### API & Authentication
| Component | Version | Purpose |
|-----------|---------|---------|
| **Laravel Sanctum** | 3.2 | API token authentication |
| **Passport** | Optional | OAuth2 authentication |

### Real-Time & Communication
| Component | Version | Purpose |
|-----------|---------|---------|
| **Pusher** | 7.0.6 | Real-time notifications & chat |
| **Twilio** | 7.4 | SMS & voice calls |

### Payments & Integrations
| Component | Version | Purpose |
|-----------|---------|---------|
| **XGenius Payment Gateway** | 4.6.0 | **15+ payment gateways** |
| **Stripe** | Via gateway | Credit cards |
| **PayPal** | Via gateway | PayPal payments |
| **Razorpay** | Via gateway | Indian payments |
| **Flutterwave** | Via gateway | African payments |

### Content & Media
| Component | Version | Purpose |
|-----------|---------|---------|
| **Intervention Image** | 2.5 | Image processing & manipulation |
| **Laravel DomPDF** | 3.0 | PDF generation |
| **Spatie Sitemap** | 6.3 | XML sitemap generation |

### Security & Validation
| Component | Version | Purpose |
|-----------|---------|---------|
| **Mews Purifier** | 3.3 | HTML sanitization |
| **Spatie Permission** | 5.5 | Role-based access control (RBAC) |
| **Doctrine DBAL** | 3.6.4 | Database abstraction |

### Utilities
| Component | Version | Purpose |
|-----------|---------|---------|
| **Guzzle HTTP** | 7.0.1 | HTTP client |
| **Eloquent Spatial** | 4.2.1 | Geographic queries |
| **Laravel Modules** | 8.3 | Modular architecture |
| **Translatable String Exporter** | 1.11 | i18n support |
| **Location** | 7.3.2 | IP geolocation |
| **Toastr** | 5.56 | Toast notifications |

---

## 📊 Database Models (50+ Entities)

### Core Business Models
| Model | Purpose | Relations |
|-------|---------|-----------|
| **Service** | Service offerings | Has many Orders, Reviews, Images |
| **Order** | Service bookings/orders | Belongs to User & Service |
| **User** | Platform users | Has many Orders, Reviews, Services |
| **Review** | Service ratings & reviews | Belongs to Service & User |
| **Category** | Service categories | Has many Services |

### Service Management
| Model | Purpose |
|-------|---------|
| **ChildCategory** | Sub-category level 2 |
| **Subcategory** | Sub-category level 3 |
| **ExtraService** | Add-on services |
| **ServiceAttribute** | Service options |
| **ServiceBenefit** | Service benefits |
| **Serviceadditional** | Additional service info |
| **Serviceinclude** | What's included in service |
| **ServiceArea** | Geographic service areas |
| **ServiceCity** | Service cities |
| **Brand** | Service brands |

### Order & Booking Management
| Model | Purpose |
|-------|---------|
| **OrderAdditional** | Order extras/add-ons |
| **OrderInclude** | Order inclusions |
| **OrderBookingDateTimeChange** | Reschedule requests |
| **OrderCompleteDecline** | Order completion/cancellation |
| **OrderRequestCompleteHistory** | Order audit log/history |

### User & Account Management
| Model | Purpose |
|-------|---------|
| **Admin** | Admin users |
| **AdminRole** | Admin role assignments |
| **SellerVerify** | Seller verification status |
| **Accountdeactive** | Deactivated accounts |
| **UserUniqueKey** | User unique identifiers |

### Review & Feedback
| Model | Purpose |
|-------|---------|
| **Review** | Service reviews & ratings |
| **Report** | User reports (abuse, spam) |
| **ReportChatMessage** | Message reports |

### Communication & Support
| Model | Purpose |
|-------|---------|
| **SupportTicket** | Support request tickets |
| **SupportTicketMessage** | Support ticket messages |

### Scheduling & Availability
| Model | Purpose |
|-------|---------|
| **Schedule** | Service schedules |
| **Day** | Day availability |
| **DateTime** | Date/time slots |

### Pricing & Discounts
| Model | Purpose |
|-------|---------|
| **ServiceCoupon** | Discount codes |
| **Tax** | Tax rates & rules |
| **AdminCommission** | Commission tracking |
| **PayoutRequest** | Seller payout requests |

### Content Management (CMS)
| Model | Purpose |
|-------|---------|
| **Blog** | Blog posts |
| **BlogComment** | Blog comments |
| **Page** | Static pages |
| **PageBuilder** | Page builder config |
| **Slider** | Image sliders |
| **HeaderSlider** | Homepage sliders |
| **OnlineServiceFaq** | FAQ entries |
| **Tag** | Content tags |
| **MetaData** | SEO metadata |

### Visual & Media
| Model | Purpose |
|-------|---------|
| **MediaUpload** | File uploads |
| **GalleryCategory** | Gallery organization |
| **CustomFontImport** | Custom fonts |

### UI & Navigation
| Model | Purpose |
|-------|---------|
| **Menu** | Menu items |
| **MenuBuilder** | Menu structure |
| **Widgets** | Widget system |
| **WidgetsBuilder** | Widget configuration |

### Geographic & Settings
| Model | Purpose |
|-------|---------|
| **Country** | Countries list |
| **Location** | Locations/regions |
| **Language** | Languages & translations |
| **StaticOption** | System settings |
| **SocialIcon** | Social media links |

### Admin Notifications
| Model | Purpose |
|-------|---------|
| **AdminNotification** | Admin notifications |
| **AdminNotice** | Admin notices |
| **AdminCommission** | Commission notifications |

### Miscellaneous
| Model | Purpose |
|-------|---------|
| **EditServiceHistory** | Service edit tracking |
| **ToDoList** | Todo management |

---

## 🔌 API Endpoints

### Authentication Endpoints
```
POST   /api/auth/register          # User registration
POST   /api/auth/login             # User login
POST   /api/auth/logout            # User logout
POST   /api/auth/refresh           # Refresh token
GET    /api/auth/profile           # Get user profile
PUT    /api/auth/profile           # Update profile
```

### Service Endpoints
```
GET    /api/services               # List all services
GET    /api/services/{id}          # Get service details
POST   /api/services               # Create service (seller)
PUT    /api/services/{id}          # Update service (seller)
DELETE /api/services/{id}          # Delete service (seller)
GET    /api/services/search        # Search services
GET    /api/categories             # List categories
GET    /api/services/by-category   # Services by category
GET    /api/services/featured      # Featured services
```

### Order Endpoints
```
GET    /api/orders                 # List user orders
GET    /api/orders/{id}            # Get order details
POST   /api/orders                 # Create order
PUT    /api/orders/{id}            # Update order
DELETE /api/orders/{id}            # Cancel order
POST   /api/orders/{id}/reschedule # Reschedule order
POST   /api/orders/{id}/complete   # Mark complete
POST   /api/orders/{id}/decline    # Decline order
```

### Payment Endpoints
```
POST   /api/payments               # Create payment
GET    /api/payments/{id}          # Get payment status
POST   /api/payments/verify        # Verify payment
GET    /api/payment-gateways       # Available gateways
POST   /api/coupons/apply          # Apply coupon code
```

### Review Endpoints
```
GET    /api/reviews/service/{id}   # Get service reviews
POST   /api/reviews                # Create review
GET    /api/reviews/{id}           # Get review details
PUT    /api/reviews/{id}           # Update review
DELETE /api/reviews/{id}           # Delete review
```

### Chat Endpoints
```
GET    /api/messages               # Get messages
POST   /api/messages               # Send message
GET    /api/conversations          # Get conversations
WebSocket /chat                    # Real-time chat (Pusher)
```

### Support Tickets
```
GET    /api/support-tickets        # List tickets
POST   /api/support-tickets        # Create ticket
GET    /api/support-tickets/{id}   # Get ticket details
POST   /api/support-tickets/{id}/messages # Add message
```

### Seller Endpoints
```
GET    /api/seller/dashboard       # Seller dashboard stats
GET    /api/seller/services        # Seller's services
GET    /api/seller/orders          # Seller's orders
GET    /api/seller/payouts         # Payout history
POST   /api/seller/payout-request  # Request payout
GET    /api/seller/analytics       # Analytics data
```

### Admin Endpoints
```
GET    /api/admin/dashboard        # Admin dashboard
GET    /api/admin/users            # Manage users
GET    /api/admin/services         # Manage services
GET    /api/admin/orders           # Manage orders
GET    /api/admin/payments         # Payment tracking
GET    /api/admin/reports          # Reports & analytics
GET    /api/admin/commissions      # Commission tracking
```

---

## 🎛️ Admin Dashboard Features

### Core Admin Panel Capabilities

#### 1. **Service Management**
- Add/edit/delete services
- Service verification & approval
- Category management (3 levels)
- Service images & media
- Service pricing & extras
- Service area/city assignment

#### 2. **User Management**
- View all users (buyers & sellers)
- User verification & approval
- User profile management
- Ban/suspend users
- User role assignment
- Activity tracking

#### 3. **Order Management**
- View all orders/bookings
- Order status tracking
- Order cancellation/refunds
- Reschedule requests handling
- Order history & audit log
- Commission calculation

#### 4. **Payment Management**
- Payment gateway configuration (15+ gateways)
- Payment verification
- Transaction tracking
- Refund processing
- Tax calculation
- Currency settings

#### 5. **Seller Management**
- Seller verification process
- Seller commission settings
- Payout request handling
- Seller analytics
- Seller documents verification
- Bank account management

#### 6. **Financial Reporting**
- Revenue tracking
- Commission tracking
- Tax reports
- Payout reports
- Financial analytics
- Profit & loss statements

#### 7. **Content Management (CMS)**
- Blog post creation/editing
- Page builder with drag-drop
- Slider/promotional content management
- Widget system configuration
- Menu management
- Metadata (SEO) management
- Localization/multi-language support

#### 8. **Communication**
- Support ticket management
- Ticket message threading
- Live chat monitoring
- Notification broadcasting
- Email template management
- SMS campaign management (via Twilio)

#### 9. **Reporting & Analytics**
- Dashboard statistics
- User analytics
- Service performance metrics
- Order analytics
- Payment analytics
- Traffic/visitor analytics
- Custom report generation

#### 10. **Settings & Configuration**
- System settings
- Email configuration
- SMS configuration
- Payment gateway setup
- Tax settings
- Commission settings
- Localization settings
- Language management

---

## 📦 Key Dependencies & Packages

### Laravel Framework Components
```json
{
  "laravel/framework": "^10.10",
  "laravel/sanctum": "^3.2",
  "laravel/socialite": "^5.2",
  "livewire/livewire": "^2.12",
  "nwidart/laravel-modules": "^8.3",
  "spatie/laravel-permission": "^5.5"
}
```

### Payment Processing
```json
{
  "xgenious/paymentgateway": "^4.6.0",
  "xgenious/installer": "^1.0.7",
  "xgenious/xgapiclient": "^3.0"
}
```

### Real-Time & Communication
```json
{
  "pusher/pusher-php-server": "^7.0.6",
  "twilio/sdk": "^7.4"
}
```

### Content & Media
```json
{
  "barryvdh/laravel-dompdf": "^3.0.0",
  "intervention/image": "^2.5",
  "spatie/laravel-sitemap": "^6.3"
}
```

### Database & Geolocation
```json
{
  "doctrine/dbal": "3.6.4",
  "matanyadaev/laravel-eloquent-spatial": "^4.2.1",
  "stevebauman/location": "^7.3.2"
}
```

### Security & Utilities
```json
{
  "mews/purifier": "^3.3",
  "guzzlehttp/guzzle": "^7.0.1",
  "guzzlehttp/psr7": "^2.5",
  "yajra/laravel-datatables-oracle": "~10.0"
}
```

### Development Tools
```json
{
  "laravel/tinker": "^2.0",
  "laravel/ui": "^4.0",
  "phpunit/phpunit": "^10.0",
  "mockery/mockery": "^1.0"
}
```

---

## 🔌 Module System

### Current Status
- **Framework:** Laravel Modules (nwidart/laravel-modules v8.3)
- **Current Modules:** None (modules_statuses.json indicates empty)
- **Purpose:** Scalable architecture for adding features as separate, self-contained modules

### Modular Architecture Benefits
- ✅ Code organization by feature
- ✅ Independent module deployment
- ✅ Reduced coupling between features
- ✅ Easy to enable/disable features
- ✅ Cleaner codebase as project grows

### Potential Modules (Best Practice)
```
Modules/
├── Users/              # User management
├── Services/           # Service management
├── Orders/             # Order processing
├── Payments/           # Payment handling
├── Chat/               # Messaging system
├── Reviews/            # Review system
├── Support/            # Support tickets
├── Content/            # CMS features
└── Admin/              # Admin panel
```

---

## ⚙️ Configuration & Setup

### Environment Setup (.env)

Key environment variables needed:

```bash
APP_NAME=FunMoment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.funmoments.sa

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=funmoment_db
DB_USERNAME=root
DB_PASSWORD=***

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=cookie

# Mail
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=***
MAIL_PASSWORD=***

# Pusher (Real-time)
PUSHER_APP_ID=***
PUSHER_APP_KEY=***
PUSHER_APP_SECRET=***
PUSHER_APP_CLUSTER=mt1

# Twilio (SMS)
TWILIO_ACCOUNT_SID=***
TWILIO_AUTH_TOKEN=***
TWILIO_PHONE_NUMBER=***

# Social Login
GOOGLE_CLIENT_ID=***
GOOGLE_CLIENT_SECRET=***
FACEBOOK_APP_ID=***
FACEBOOK_APP_SECRET=***

# Payment Gateways
STRIPE_PUBLIC_KEY=***
STRIPE_SECRET_KEY=***
RAZORPAY_KEY_ID=***
RAZORPAY_KEY_SECRET=***

# AWS S3 (Optional)
AWS_ACCESS_KEY_ID=***
AWS_SECRET_ACCESS_KEY=***
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=***
```

### Installation Steps

```bash
# 1. Navigate to backend folder
cd Archive/@core

# 2. Install PHP dependencies
composer install

# 3. Copy environment file
cp .env.example .env

# 4. Generate app key
php artisan key:generate

# 5. Run database migrations
php artisan migrate

# 6. Seed database (optional)
php artisan db:seed

# 7. Install frontend dependencies
npm install

# 8. Build frontend assets
npm run dev    # Development
npm run prod   # Production

# 9. Create storage symlink
php artisan storage:link

# 10. Start development server
php artisan serve
```

---

## 🔗 Integration with Flutter App

### API Communication Flow

```
┌─────────────────────────────┐
│   Flutter Mobile App        │
│   (lib/ folder)             │
└──────────────┬──────────────┘
               │ API Request (HTTP/JSON)
               │ GET/POST /api/services
               │ GET /api/orders
               │ POST /api/payments
               │
               ↓
┌──────────────────────────────┐
│   Laravel Backend            │
│   (Archive/@core)            │
│   • API Routes               │
│   • Controllers              │
│   • Business Logic           │
│   • Database Queries         │
└──────────────┬───────────────┘
               │ JSON Response
               │ {
               │   "status": "success",
               │   "data": { ... }
               │ }
               ↓
┌─────────────────────────────┐
│   Flutter App               │
│   • Parse JSON              │
│   • Update UI               │
│   • Store in Provider state │
└─────────────────────────────┘
```

### Real-Time Communication

```
Flutter App              Laravel Backend         Pusher
    │                        │                      │
    ├─ User sends message ──→│                      │
    │                        ├─ Broadcast event ───→│
    │                        │                      │
    │←─ WebSocket update ────┼──────────────────────┤
    │  (Pusher)              │                      │
    │
    • Live chat messages
    • Order status updates
    • Notifications
    • Real-time notifications
```

### API Authentication (Sanctum Tokens)

```
1. Login Request:
   POST /api/auth/login
   { "email": "user@example.com", "password": "***" }
   
   Response:
   { "token": "abc123...", "user": {...} }

2. Subsequent Requests:
   GET /api/orders
   Header: Authorization: Bearer abc123...
   
3. Token Refresh (if needed):
   POST /api/auth/refresh
   { "token": "abc123..." }
```

---

## 📋 Organization Recommendations

### Current Structure Analysis
```
Current Setup:
└── Archive/              (Root project folder)
    └── @core/           (Actual Laravel application)
        └── [app, config, routes, etc.]
```

### ⚠️ ISSUE WITH CURRENT STRUCTURE
The nested folder structure is problematic because:
1. ❌ Extra nesting level (`Archive/@core/`) adds complexity
2. ❌ Harder to run commands (must cd twice)
3. ❌ CI/CD pipelines need special path handling
4. ❌ Documentation becomes confusing
5. ❌ Not standard Laravel project layout

### ✅ RECOMMENDED SOLUTION

**Option 1: Move Backend to Root Level (RECOMMENDED)**

```
Project Root/
├── 📁 lib/                      # Flutter mobile app
│   ├── main.dart
│   ├── service/
│   ├── view/
│   └── model/
│
├── 📁 backend/                  # Laravel backend (MOVED HERE)
│   ├── app/
│   ├── config/
│   ├── routes/
│   ├── resources/
│   ├── database/
│   ├── artisan
│   ├── composer.json
│   └── .env
│
├── 📁 docs/                     # Documentation
│   ├── PROJECT_ANALYSIS.md
│   └── ARCHIVE_BACKEND_ANALYSIS.md
│
├── 📁 assets/                   # Shared assets
│   ├── backend/
│   ├── frontend/
│   ├── common/
│   └── uploads/
│
├── 📁 Archive/                  # Keep only for backup
│   └── (Backup of original Laravel structure)
│
├── 📄 README.md                 # Main project readme
├── 📄 pubspec.yaml              # Flutter config
└── 📄 .gitignore                # Global git ignore
```

### Steps to Implement Option 1

**Step 1: Backup Current Structure**
```bash
# Make a backup of the entire Archive folder
cp -r Archive Archive.backup
```

**Step 2: Extract Backend Files**
```bash
# Copy the @core contents to a new backend folder at root level
cp -r Archive/@core/* backend/
# Copy assets folder
cp -r Archive/assets/* backend/storage/
```

**Step 3: Update Paths**
```bash
# Update any hardcoded paths in:
# - config/filesystems.php
# - config/app.php
# - routes/web.php
# - .env
```

**Step 4: Clean Up Archive**
```bash
# Remove the redundant Archive folder structure
# Keep only as backup if needed
```

### Final Structure Benefits
✅ Cleaner organization  
✅ Single root project  
✅ Easier to document  
✅ Standard Laravel layout  
✅ Simpler CI/CD setup  
✅ Better team collaboration  
✅ Easier deployment  

---

## 🚀 Running the Complete Project

### Prerequisites
- PHP 8.1+
- Node.js 16+
- MySQL 5.7+
- Composer
- Flutter SDK
- Git

### Setup Instructions

**1. Clone/Setup Backend**
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

**2. Setup Mobile App**
```bash
# In separate terminal from backend
flutter pub get
flutter run    # For development
flutter build apk    # For Android release
flutter build ios    # For iOS release
```

**3. Database Setup**
```bash
# Create database
mysql -u root -p
CREATE DATABASE funmoment_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# Run migrations
php artisan migrate

# Seed sample data
php artisan db:seed
```

**4. Environment Configuration**
```bash
# Set up .env variables:
- Database credentials
- API keys (Stripe, Razorpay, etc.)
- Pusher credentials
- Mail/SMS settings
- Social login credentials
```

**5. Access Points**

After setup, access:
- **Mobile App:** Flutter dev mode or installed APK/iOS app
- **Admin Dashboard:** http://localhost:8000/admin
- **API Documentation:** http://localhost:8000/api/docs
- **API Endpoints:** http://localhost:8000/api/*

---

## 📝 Summary

### Archive (@core) - Backend API & Dashboard

**What It Is:**
- Laravel 10 PHP web application
- REST API for Flutter mobile app
- Admin dashboard for marketplace management
- 50+ Eloquent models with complex relationships
- 15+ payment gateway integrations
- Real-time communication via Pusher

**Key Features:**
- Multi-vendor service marketplace
- Complete order/booking management
- Payment processing & commission tracking
- CMS with blog, pages, widgets
- Real-time chat & notifications
- Support ticket system
- Role-based access control
- Geographic service filtering
- Analytics & reporting

**Architecture:**
- Service-based business logic
- RESTful API design
- Livewire for real-time dashboard
- Modular structure (ready for modules)
- Clean separation of concerns

**Integration:**
- Serves as API backend for Flutter app
- Provides admin dashboard for management
- Handles all business logic & transactions
- Database of truth for all data

### Recommended Action
**Move `/Archive/@core` to `/backend` at project root** for:
- Better organization
- Easier setup & deployment
- Cleaner project structure
- Standard Laravel practices
- Simplified documentation

---

**Document Version:** 1.0  
**Last Updated:** May 19, 2026  
**Status:** Complete Analysis  
**Recommendation:** Extract @core to backend/ folder
