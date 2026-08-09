# FUN MOMENT Dashboard Rebuild Plan

## Goal
Replace the legacy PHP admin dashboard with the new React dashboard and make it the single control panel for:
- the website
- the mobile app
- the backend database and settings
- future admin-controlled add-on modules

The new dashboard must work in both Arabic and English and remain compatible with the existing Laravel backend and Flutter app.

## Current State
- The current backend is Laravel and already owns most of the business logic.
- The mobile app still uses the existing `api/v1` backend contract.
- The new React dashboard export exists in `admin-react/`.
- The exported dashboard currently assumes a `/api/v2/admin/*` API layer that does not yet exist in the Laravel backend.
- Add-ons are currently disabled in `backend/modules_statuses.json`:
  - Wallet
  - Chat
  - Jobs
  - Subscription

## Phase 1: Source-of-Truth Audit
### Deliverables
- Inventory all Laravel admin routes, controllers, and setting screens.
- Inventory all app and web features that depend on admin-controlled data.
- Map the React dashboard screens to the real backend capabilities.
- Build a feature matrix with four states:
  - already supported
  - supported but needs API wrapping
  - missing and must be built
  - add-on locked

### Coverage Areas
- auth and roles
- dashboard stats
- users
- services
- orders
- payments
- support
- CMS, pages, blog
- localization and languages
- general settings
- media uploads
- add-ons

## Phase 2: Admin API Contract Layer
### Deliverables
- Add JSON admin endpoints or adapters for the React dashboard.
- Keep Laravel as the source of truth for business logic.
- Normalize list, detail, create, update, delete, status change, and bulk action responses.
- Add secure admin authentication for the React dashboard.
- Add RBAC permissions that map directly to dashboard sections.
- Add persistent server-side audit logging.

### Contract Rules
- The React dashboard must not depend on browser memory for critical actions.
- The dashboard must not assume frontend-only permission checks.
- Every destructive action must be validated on the server.

## Phase 3: Core Dashboard Wiring
### Deliverables
- Connect the React dashboard to live backend data for:
  - dashboard overview and analytics
  - users and roles
  - services
  - orders
  - payments and refunds
  - support tickets and disputes
  - CMS pages, blogs, banners, widgets
  - languages and translations
  - settings and maintenance controls
- Ensure Arabic and English labels are fully supported.
- Ensure RTL mode works correctly for Arabic.

### Acceptance Criteria
- Each screen loads real backend data.
- CRUD actions update the database correctly.
- Permission-denied states are handled cleanly.

## Phase 4: Feature Flags and Module Locking
### Deliverables
- Introduce a backend feature-flag system for modules.
- Let the admin lock or unlock modules later without changing frontend code.
- Make the React dashboard show locked and unlocked states from backend flags.
- Support future module expansion with the same pattern.

### Modules in Scope
- Wallet
- Chat
- Jobs
- Subscription

### Behavior
- If a module is locked, the dashboard should show the section but disable operations and explain why.
- If a module is unlocked, the section should behave like a normal admin module.

## Phase 5: Media, CMS, and Platform Operations
### Deliverables
- Replace mock upload behavior with real backend media handling.
- Connect CMS and page-builder actions to Laravel storage and settings.
- Wire menus, widgets, notices, form builder, and appearance settings where needed.
- Ensure platform changes propagate to website and app behavior.

## Phase 6: Mobile App and Website Alignment
### Deliverables
- Verify admin actions correctly affect:
  - website listings and content
  - mobile app services and categories
  - orders and payment states
  - user status and restrictions
  - module availability
- Keep existing `api/v1` mobile flows stable.
- Prefer compatibility wrappers over rewriting working app endpoints.

## Phase 7: Validation and Rollout
### Deliverables
- Test all dashboard screens against live backend data.
- Test permission-denied behavior for non-authorized roles.
- Test locked module behavior and unlock flow.
- Test Arabic and English layouts.
- Test create/update/delete/status operations end to end.
- Roll out in stages so the old dashboard can be retired safely.

## Test Plan
- Verify each React screen has a matching backend endpoint or approved fallback.
- Validate CRUD flows for users, services, orders, payments, support, CMS, and settings.
- Validate feature flags for locked and unlocked add-ons.
- Validate RBAC access control for each admin role.
- Validate audit logging persistence.
- Validate RTL and LTR rendering.
- Validate website and mobile app behavior after admin changes.

## Recommended Implementation Order
1. Build the backend contract map.
2. Add admin auth, RBAC, and audit logging.
3. Wire the React dashboard to the live backend.
4. Add feature flags for add-on lock/unlock.
5. Connect CMS, media, and platform settings.
6. Verify web and mobile compatibility.
7. Test and retire the old dashboard.

## Assumptions
- React will be the new dashboard frontend.
- Laravel remains the backend source of truth.
- Existing app and website APIs should stay stable unless migration is explicitly needed.
- Add-ons should be controllable by admin in the future, even if some are initially disabled.
- Backend adapters and feature flags are preferred over rewriting proven business logic.
