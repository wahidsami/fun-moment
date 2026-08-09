# FUN MOMENT — Backend-Frontend Contract Assumptions

This document outlines key technical assumptions made during the design of the FUN MOMENT TypeScript API Contract Layer. These must be reviewed, confirmed, and implemented by the Laravel backend development team.

---

## 1. Multi-Language & Database Schema
- **Assumption:** All user-facing localized strings must be stored as separate database columns (e.g., `title_en` and `title_ar`) instead of a single dynamic JSON structure. This simplifies direct indexing, avoids JSON parsing overhead, and guarantees strict type alignment in TypeScript.
- **Action Required:** Ensure Laravel Eloquent models expose both fields in serialization, rather than performing automatic runtime translation stripping.

---

## 2. Authentication & Session Management
- **Assumption:** The Laravel backend will utilize **Laravel Sanctum** or **Passport** for stateless token-based authentication.
- **Action Required:**
  - Token is passed in the header as `Authorization: Bearer <token>`.
  - Token responses must return the bearer token along with the expiration date and user's role/permissions list to allow instant frontend capability toggling.

---

## 3. Localization & Direction Headers
- **Assumption:** The React frontend will communicate the active user locale and visual direction with every request.
- **Action Required:** The backend middleware must respect:
  - `Accept-Language: ar` or `Accept-Language: en`
  - `X-App-Locale: ar` or `X-App-Locale: en`
  - `X-Layout-Direction: rtl` or `X-Layout-Direction: ltr`
  - Validation messages must be returned in the requested language.

---

## 4. Standard Envelope Format
- **Assumption:** All responses must follow a consistent top-level envelope structure to handle success, message, and execution telemetry gracefully.
- **Response Format:**
  ```json
  {
    "success": true,
    "data": { ... },
    "message_en": "Action completed successfully",
    "message_ar": "تم تنفيذ العملية بنجاح",
    "meta": {
      "timestamp": "2026-06-28T20:15:00Z",
      "api_version": "v2.0.0",
      "execution_time_ms": 22
    }
  }
  ```

---

## 5. Standard Paginated Output
- **Assumption:** Paginated listings (such as orders or services) must include metadata on total record size, per-page values, and state indicators instead of simple arrays.
- **Response Format:**
  ```json
  {
    "items": [ ... ],
    "pagination": {
      "total_records": 120,
      "current_page": 1,
      "per_page": 15,
      "last_page": 8,
      "has_more": true
    }
  }
  ```

---

## 6. Add-on Module Enablement State
- **Assumption:** Modules like `wallet`, `chat`, `jobs`, and `subscriptions` are dynamically active or inactive depending on licensing and backend config flags.
- **Action Required:** The general settings or authentication handshakes must include the Boolean active flags so the React sidebar can dynamically reflect locks or active routes.
