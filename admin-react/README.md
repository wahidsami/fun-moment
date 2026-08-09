# FUN MOMENT — Admin Control Panel v2.0
> **Modern React Dashboard with Laravel API v2 Interface Integration**

FUN MOMENT is an elite, multi-lingual event and marketplace platform operating in the Gulf region (primarily Saudi Arabia). This repository contains the complete, pixel-perfect, double-entry audit-logged, RTL-supported frontend React administration panel for overseeing transactions, services, user portfolios, support logs, payment gateways, and dynamically licensed modular add-on parameters.

---

## 📂 Project Folder Structure

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

## ⚙️ Installation & Developer Guide

Follow these instructions to run and build the dashboard locally.

### 1. Prerequisites
Ensure you have **Node.js 18+** and **npm v9+** installed.

### 2. Dependency Setup
Install project dependencies from the lockfile:
```bash
npm install
```

### 3. Run Development Server
Boot the Vite dev server locally:
```bash
npm run dev
```
*Note:* The system is pre-configured to bind automatically to `http://localhost:3000` to accommodate reverse-proxy container routing.

### 4. Build for Production
To bundle and compile highly optimized static assets:
```bash
npm run build
```
This script generates the compiled build output folder `/dist` ready for immediate production delivery on CDNs or hosting environments.

### 5. Code Linter and Verification
To run static analysis checks:
```bash
npm run lint
```

---

## 🔑 Environment Variables & Security Proxying

Rename `.env.example` to `.env` to configure external connections.

```env
# Server Ingress and Proxy Configuration
VITE_API_BASE_URL=https://api.funmoment.sa/api/v2
VITE_ENABLE_MOCK_FALLBACK=false

# Feature Flags
VITE_DEBUG_AUDIT_TRAILS=true
```

> ⚠️ **API Security Rule:** Secret keys or payment credentials must *never* be directly declared in this client-side environment. In production, requests should be proxied through the server or securely handled via Laravel middleware to avoid exposure in user web consoles.

---

## 🌐 Dual-Language Translation (i18n) & RTL Layouts

The application implements a custom-built, lightweight, high-performance translation and styling strategy optimized for bidirectional Arabization.

### Translation Keys (`/src/translations.ts`)
Instead of heavy external framework bundles, a single structural dictionary holds perfect matching values for both languages:
```typescript
export const translations = {
  en: {
    nav_dashboard: "Dashboard Overview",
    total_sales: "Total Marketplace Revenue"
  },
  ar: {
    nav_dashboard: "لوحة التحكم العامة",
    total_sales: "إجمالي إيرادات المنصة"
  }
};
```

### Layout bidirectionality (`rtl` and `ltr`)
The application listens to the `language` state. When set to `ar`:
- The HTML root class automatically receives the dynamic attribute `dir="rtl"`.
- Alignment styles transition dynamically:
  - Flex layouts mirror (e.g., `flex-row` and `space-x-reverse`).
  - Font pairings scale gracefully with customized margins and line heights (using Inter for English, and Cairo/Tajawal font stacks for Arabic to prevent truncated Arabic typography).
  - Arrow and caret icons flip horizontally using specialized transforms.

---

## 🛡️ Role-Based Permission Matrix (RBAC)

The workspace enforces a clear multi-tier role hierarchy. Administrators are intercepted if their active role lacks permission for the requested workspace module.

### Core Role List
- **Super Admin (`super_admin`):** Complete structural platform authority. Unrestricted read/write across all modules, payment adjustment, logs monitoring, and system properties.
- **Moderator (`moderator`):** General operations controller. Can manage user statuses, services, listings, and localization keys, but cannot adjust financials.
- **Financial Manager (`financial_manager`):** Oversight on ledger books. Access to payment records, refunds, balance adjustments, and ledger adjustments.
- **Support Agent (`support_agent`):** Ticketing desk. Read-only on general sections, but full access to manage and respond to Support Tickets.

### Policy Ruleset Matrix

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

## 📈 Unfinished Items, Mock Data, and Gaps (TODO List)

While the administration UI is fully built, interactive, and functional, a series of items must be addressed before final production integration:

1. **Active Websocket connection for Live Chat Add-on:**
   - *Current State:* Simulated message threads.
   - *TODO:* Wire to a real socket connection (Pusher or Socket.io) in `/src/components/AddonLockedView.tsx` or backend services.
2. **True File Upload Integration:**
   - *Current State:* Media file uploads in CMS are simulated via browser memory URLs.
   - *TODO:* Integrate AWS S3, Cloudflare R2, or Laravel Storage APIs into the media modal in `CMSView.tsx`.
3. **Database-backed Audit Logs:**
   - *Current State:* Audit logs and change tracking are stored in client-side memory (`localStorage`) within `/src/utils/auditLogger.ts`.
   - *TODO:* Route the log creation payload to a real `/api/v2/audit-logs` database endpoint to avoid losing data when browser cache clears.
4. **Third-Party Payment Gateway Integration Sandbox:**
   - *Current State:* Sandbox modes for PayTabs, Apple Pay, and STC Pay can be toggled in `PaymentsView.tsx`, but no external gateways are loaded.
   - *TODO:* Implement gateway client libraries (e.g., PayTabs SDK or STC Pay direct checkout webhooks).

---

## 🔌 Assumed Backend Endpoints (Laravel API Contract)

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
