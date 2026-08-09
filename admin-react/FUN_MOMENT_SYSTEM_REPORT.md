# FUN MOMENT — Technical Systems Report & Codebase Export Documentation
> **Enterprise-Grade Admin Panel Codebase Specifications & Integration Blueprint**

This document serves as the official specifications document and handover report for the FUN MOMENT Admin Control Panel. It maps the current state of the application, identifies integration gaps, and outlines the precise steps for production backend alignment.

---

## 📂 1. Core Folder Structure Tree

Below is the verified structural tree layout of the React dashboard workspace:

```text
/ (Workspace Root)
├── .env.example                # Templates for system integration credentials
├── .gitignore                  # Excluded folders (node_modules, dist, logs)
├── index.html                  # Standard entry DOM page
├── metadata.json               # Frame permission configurations & capability lists
├── package.json                # System dependencies & scripts
├── tsconfig.json               # TypeScript compiler config
├── vite.config.ts              # Vite configuration with strict dev port binding (3000)
├── README.md                   # Setup instructions, installation guides, and execution flows
├── FUN_MOMENT_SYSTEM_REPORT.md # [THIS FILE] Core technical specs, contracts, gaps, and answers
└── src/
    ├── main.tsx                # Client mounting point
    ├── App.tsx                 # Core page coordinator, security intercept views
    ├── index.css               # Tailwind CSS imports & global RTL theme variables
    ├── api.ts                  # Simulated API controller with seed dataset
    ├── theme.ts                # Visual palette theme variables
    ├── translations.ts         # Dual-language translations dictionary (en/ar)
    ├── types.ts                # Global core schema type declarations
    ├── types/
    │   ├── assumptions.md      # API schema & transport layer guidelines
    │   ├── contracts.ts        # Typed API responses & payload interfaces for all 24 scopes
    │   └── samples.ts          # Sample server response payload envelopes (Mock JSON)
    ├── utils/
    │   └── auditLogger.ts      # Policy matrix, active logs keeper & dual-language change tracker
    └── components/
        ├── Header.tsx          # Simulated identity header & role selector (Super Admin down to Support Agent)
        ├── Sidebar.tsx         # Responsive collateral-aware sidebar with locked modular states
        ├── StatsGrid.tsx       # Live status analytics widgets
        ├── DashboardView.tsx   # Dashboard overview view
        ├── ServicesView.tsx    # Marketplace services, categories & area nodes CRUD
        ├── UsersView.tsx       # Buyer ledger portfolios, seller verification, and balance adjustment
        ├── OrdersView.tsx      # Core booking cycles & order status transitions
        ├── PaymentsView.tsx    # Financial transaction audits, refunds, and escrow logs
        ├── SupportView.tsx     # Active support ticket thread manager & severity assignment
        ├── CMSView.tsx         # Media asset managers, sliding sliders, FAQs & dynamic pages
        ├── LocalizationView.tsx# Translate tables and localization keys
        ├── SettingsView.tsx    # Commission rate parameters, version limits & maintenance triggers
        ├── AuditView.tsx       # System transaction logs and double-entry changes
        ├── AddonLockedView.tsx # Feature lock fallback panel for unlicensed client domains
        ├── FeedbackStates.tsx  # Global alerts, notices, and success feedback toasts
        └── Breadcrumbs.tsx     # Breadcrumbs layout navigation helper
```

---

## ⛓️ 2. API Contract Files & Typed Service Stubs
The dashboard uses fully typed interfaces for state management and API communication. These are defined under `/src/types/contracts.ts` and `/src/types.ts`.

Below are the 24 targeted backend service modules with their respective React types and expected JSON schemas:

### 1. Dashboard Overview
- **React Type:** `DashboardStats`
- **Fields:** `totalSales: number`, `activeSellers: number`, `activeBuyers: number`, `pendingServices: number`, `orderCompletionRate: number`

### 2. Services
- **React Type:** `Service`
- **Fields:** `id: number`, `title_en: string`, `title_ar: string`, `price: number`, `status: 'active' | 'suspended' | 'pending'`, `category_id: number`, `sub_category_id: number`

### 3. Orders
- **React Type:** `Order`
- **Fields:** `id: number`, `buyerName: string`, `serviceTitle: string`, `price: number`, `status: 'pending' | 'in_progress' | 'completed' | 'cancelled'`, `createdAt: string`

### 4. Sellers (User Role)
- **React Type:** `User` (where `role === 'seller'`)
- **Fields:** `id: number`, `name: string`, `email: string`, `phone: string`, `status: 'active' | 'suspended'`, `wallet_balance: number`, `is_verified_seller: boolean`

### 5. Buyers (User Role)
- **React Type:** `User` (where `role === 'buyer'`)
- **Fields:** `id: number`, `name: string`, `email: string`, `phone: string`, `status: 'active' | 'suspended'`, `wallet_balance: number`

### 6. Categories
- **React Type:** `CategoryNode`
- **Fields:** `id: number`, `name_en: string`, `name_ar: string`, `parentId: number | null`

### 7. Subcategories
- **React Type:** `CategoryNode` (where `parentId !== null`)

### 8. Child Categories
- **React Type:** `CategoryNode` (where tertiary depth is supported)

### 9. Countries
- **React Type:** `GeoNode`
- **Fields:** `id: number`, `name_en: string`, `name_ar: string`, `type: 'country'`

### 10. Cities
- **React Type:** `GeoNode`
- **Fields:** `id: number`, `name_en: string`, `name_ar: string`, `type: 'city'`, `parentId: number`

### 11. Areas
- **React Type:** `GeoNode`
- **Fields:** `id: number`, `name_en: string`, `name_ar: string`, `type: 'area'`, `parentId: number`

### 12. Blogs
- **React Type:** `BlogArticle`
- **Fields:** `id: number`, `title_en: string`, `title_ar: string`, `content_en: string`, `content_ar: string`, `status: 'draft' | 'published'`

### 13. Pages
- **React Type:** `StaticPage`
- **Fields:** `id: number`, `slug: string`, `title_en: string`, `title_ar: string`, `content_en: string`, `content_ar: string`

### 14. Menus
- **React Type:** `NavigationMenu`
- **Fields:** `id: number`, `name_en: string`, `name_ar: string`, `items: MenuItem[]`

### 15. Widgets
- **React Type:** `Widget`
- **Fields:** `id: number`, `key: string`, `title_en: string`, `title_ar: string`, `content: string`

### 16. Notifications
- **React Type:** `NotificationTemplate`
- **Fields:** `id: number`, `trigger_event: string`, `title_en: string`, `title_ar: string`, `body_en: string`, `body_ar: string`

### 17. Reports
- **React Type:** `SystemReport`
- **Fields:** `id: number`, `reportType: string`, `dateRange: string`, `fileUrl: string`

### 18. Support Tickets
- **React Type:** `SupportTicket`
- **Fields:** `id: number`, `subject: string`, `user_id: number`, `priority: 'low' | 'medium' | 'high'`, `status: 'open' | 'resolved' | 'closed'`, `messages: MessageThread[]`

### 19. Payment Gateways
- **React Type:** `GatewayConfig`
- **Fields:** `id: string`, `name: string`, `enabled: boolean`, `sandbox_mode: boolean`

### 20. Localization (Translations & Key Manager)
- **React Type:** `TranslationKey`
- **Fields:** `id: number`, `key: string`, `val_en: string`, `val_ar: string`

### 21. General Settings
- **React Type:** `PlatformSettings`
- **Fields:** `commission_percentage: number`, `min_payout_amount: number`, `maintenance_mode: boolean`, `required_app_version: string`

### 22. Admin Roles & Permissions (Access Policy)
- **React Type:** `UserRole` ('super_admin' | 'moderator' | 'financial_manager' | 'support_agent')
- **Permissions List:** `manage_services`, `manage_users`, `manage_orders`, `manage_payments`, `manage_support`, `manage_cms`, `manage_settings`, `view_audit`

### 23. Add-on Modules (License Configuration)
- **React Type:** `AddonModuleKey` ('wallet' | 'chat' | 'jobs' | 'subscription')
- **Fields:** `licensed: boolean`, `price: number`, `expiresAt: string | null`

---

## 🧭 3. Navigation & Route Configuration
Routes are managed declaratively in `/src/App.tsx` and sidebar options are defined in `/src/components/Sidebar.tsx`.

### Route Definition Map:
```typescript
const VIEW_PERMISSIONS: Record<string, Permission> = {
  services: 'manage_services',
  users: 'manage_users',
  orders: 'manage_orders',
  payments: 'manage_payments',
  support: 'manage_support',
  cms: 'manage_cms',
  localization: 'manage_cms',
  settings: 'manage_settings',
  audit: 'view_audit',
};
```
*Note:* If the logged-in administrator lacks the target policy permissions, a security intercept is rendered instead of mounting the core view.

---

## 🌐 4. Dual-Language Translation Architecture
Dual-language translations are stored in `/src/translations.ts`. The application supports `en` and `ar`. When `ar` is toggled:
- The DOM class is updated to support standard RTL orientations (`dir="rtl"`).
- Structural fonts adapt: `Inter` for English, and `Cairo` / `Tajawal` for Arabic.
- Table headers, visual filters, input alignments, and form feedback translate instantly.

---

## 🛡️ 5. Permission & Access Matrix (RBAC)

The system maps roles to permissions deterministically:

| Section / Permission Scope | Required Permission Key | Super Admin | Moderator | Financial Manager | Support Agent |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **Dashboard** | N/A (General Read) | ✅ | ✅ | ✅ | ✅ |
| **Services View** | `manage_services` | ✅ | ✅ | ❌ | ❌ |
| **Users View** | `manage_users` | ✅ | ✅ | ❌ | ❌ |
| **User Balance Mod**| `financial_rights` | ✅ | ❌ | ✅ | ❌ |
| **Orders View** | `manage_orders` | ✅ | ✅ | ✅ | ❌ |
| **Payments View** | `manage_payments` | ✅ | ❌ | ✅ | ❌ |
| **Support Tickets** | `manage_support` | ✅ | ❌ | ❌ | ✅ |
| **CMS & Blogs** | `manage_cms` | ✅ | ✅ | ❌ | ❌ |
| **Localization Keys**| `manage_cms` | ✅ | ✅ | ❌ | ❌ |
| **General Settings**| `manage_settings` | ✅ | ❌ | ❌ | ❌ |
| **Security Audit Logs**| `view_audit` | ✅ | ❌ | ❌ | ❌ |

---

## 📊 6. TODO List, Unfinished Items, and Gaps

The following list maps remaining gaps before final deployment to production:

- [ ] **WS Live Chat integration:**
  The `Live Chat` add-on is simulated using static memory queues inside `AddonLockedView.tsx` (when licensed). Real WebSocket connections via a provider (Pusher, Socket.io) need to be wired up.
- [ ] **S3 Upload API for CMS Media:**
  The media manager currently loads files into memory using temporary browser paths (`URL.createObjectURL`). It must be replaced by direct S3 multi-part uploads.
- [ ] **Database Audit Log Integration:**
  Currently, double-entry audit logs are stored in the client's `localStorage` via `/src/utils/auditLogger.ts`. They must be synchronized to a persistent endpoint (`/api/v2/admin/audit-logs`) to prevent loss on browser cache cleared.
- [ ] **Payment Gateway SDK Activation:**
  Payment statuses (Refund/Escrow) are mock-updated. Full integration of STC Pay and PayTabs webhooks on the server side is required.

---

## 🔌 7. Assumed Backend Endpoints (Laravel API Contract)

To fully activate this administration dashboard, the backend team must build and expose the following JSON endpoints:

| Module Scope | HTTP Method | Laravel URL Endpoint | Description / Purpose |
| :--- | :---: | :--- | :--- |
| **Dashboard Stats** | `GET` | `/api/v2/admin/dashboard-summary` | Live numbers for overall revenue, active listings, order growth, and system health status. |
| **Services** | `GET` | `/api/v2/admin/services` | Fetches listings catalog. Supports sorting, status, category ID, and search term queries. |
| **Services** | `POST` | `/api/v2/admin/services` | Creates a new service listing (requires title_en, title_ar, and pricing). |
| **Services** | `PATCH` | `/api/v2/admin/services/{id}/status` | Updates listing state to Active, Pending, or Suspended. |
| **Users** | `GET` | `/api/v2/admin/users` | Lists registered accounts with filter variables by role (seller/buyer) and status. |
| **Users** | `PATCH` | `/api/v2/admin/users/{id}/status` | Toggles account status (Active, Suspended, Pending). |
| **Users** | `POST` | `/api/v2/admin/users/{id}/adjust-balance` | Modifies wallet balances (strictly requires Super Admin or Financial rights). |
| **Orders** | `GET` | `/api/v2/admin/orders` | Retrieves marketplace transactions with itemized services, buyers, and order stages. |
| **Orders** | `PATCH` | `/api/v2/admin/orders/{id}/status` | Updates checkout pipeline step (e.g. pending -> in_progress -> completed). |
| **Payments** | `GET` | `/api/v2/admin/payments` | Financial ledger list of all processed transactions and reference IDs. |
| **Payments** | `POST` | `/api/v2/admin/payments/{id}/refund` | Initiates refund request to the original card or stc_pay gateway. |
| **Support** | `GET` | `/api/v2/admin/tickets` | Lists support ticket inquiries by Priority (high, medium, low) and category. |
| **Support** | `POST` | `/api/v2/admin/tickets/{id}/reply` | Submits a replies array from admin to customer in real-time. |
| **CMS & Banners** | `GET` | `/api/v2/admin/cms` | Returns published or draft pages, slider banners, FAQs, and site widgets. |
| **CMS & Banners** | `POST` | `/api/v2/admin/cms` | Creates or saves blogs/pages content with localization parameters. |
| **Settings** | `GET` | `/api/v2/admin/settings` | Gets current system settings. |
| **Settings** | `POST` | `/api/v2/admin/settings` | Updates global commission percentage, forced client version, and maintenance mode status. |
| **Audit Logs** | `POST` | `/api/v2/admin/audit-logs` | Dispatches double-entry user action metrics directly to the system database. |

---

## 🎨 8. Skipped, Simplified, or Placeholder Screens

- **Jobs & Subscription Modules:** Rendered as beautifully stylized locked views with licensed expiration timelines.
- **Analytics PDF Exporter:** Triggers a standard visual export of current tables; actual dynamic PDF compiling needs a dedicated server-side tool like SnappyPDF or headless Puppeteer.

---

## 💡 9. Final Questions & Integration Guidance

### What is still pending?
1. **API State Bridge:** Swapping out static responses in `src/api.ts` with standard `fetch` or `axios` instances targeting the Laravel backend.
2. **Persistent Logs System:** Relaying audit events generated in `auditLogger.ts` through REST endpoints instead of client memory.
3. **Socket IO Chat Server:** Replacing static message loops in the live support section with active Pusher client scripts.

### What backend work is required before this dashboard is fully functional?
1. **Authentication Token Exchange:** A standard JWT or Laravel Sanctum gateway to authenticate administrators based on roles.
2. **Access Control Middleware:** Backend route guard policies matching our React RBAC matrix to ensure security integrity in case of client manipulation.
3. **Audit Logger API Tables:** Database logs tables to persist change history records.

### What parts are ready to wire now?
- **All Core CRUDs:** Services listing, order pipelines, support threads, platform configs, and and localized layouts are structurally ready and fully state-controlled. Simply replacing local mock states in `/src/api.ts` with API promises connects them immediately.
