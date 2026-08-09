# FUN MOMENT Mock Data Register

## Purpose

This register lists every clear mock, demo, fallback, or UI-only data source found during the audit.

## Register

| File | Mock or fallback source | Impact | Status |
|---|---|---|---|
| `admin-react/src/components/UsersView.tsx` | `FALLBACK_ADMIN_USERS`, hardcoded seller profiles, hardcoded buyer profiles | The admin directory and profile drill-down can render non-backend people/data when the API fails or when those panes load | Active mock/fallback |
| `admin-react/src/components/AddonLockedView.tsx` | `walletDb`, `chatDb`, `jobsDb`, `sellerSubsDb`, `plansDb`, `couponsDb`, “fallback playground” copy | Add-on screens are still demo sandboxes rather than production data screens | Active mock/demo |
| `admin-react/src/utils/auditLogger.ts` | `DEFAULT_AUDIT_LOGS`, `DEFAULT_CHANGES`, `DEFAULT_APPROVALS` stored in localStorage | Audit trail, changes, and approval queue are browser-local and seeded with defaults | Active mock/local state |
| `admin-react/src/components/AuditView.tsx` | Reads from localStorage audit helpers and includes a fallback localization permission row | Audit UI does not yet reflect a real server audit store | Active local-state view |
| `admin-react/src/components/LocalizationView.tsx` | Hardcoded exchange-rate field default and save handler that only updates local component state | Localization screen looks editable but does not persist to backend in current form | UI-only default |
| `admin-react/src/components/CMSView.tsx` | `siteIdentity`, `themeConfig`, `seoConfig`, `scriptsConfig`, `maintenanceReasonEn/Ar`, `custom404TextEn/Ar` are component-local defaults | Branding, SEO, scripts, maintenance, and 404 text are not fully backend-backed in the current UI | UI-only default |
| `admin-react/src/api.ts` | `getAdminDirectory()` falls back to empty arrays; `getMediaLibrary()` falls back to `[]`; `createCMSItem()` throws as unwired | These are not seeded records, but they are fallback or incomplete paths that still matter for completeness | Partial wiring |
| `admin-react/src/translations.ts` | `unlock_demo` label text | Demo wording remains in the UI copy | Cosmetic leftover |
| `admin-react/src/api/client.ts` | Comments mention mock exceptions/fallback handling | Not runtime mock data, but a sign that the client layer was designed to tolerate fallback behavior | Non-data fallback |
| `admin-react/src/types/samples.ts` | Example contract payloads | Useful documentation, not runtime state | Sample-only |
| `lib/service/payment_gateway_list_service.dart` | Wallet gateway is appended locally after fetching the gateway list | This is a small local augmentation, not a core mock dataset, but it is still a frontend-side synthetic entry | Minor synthetic UI entry |

## Not Classified As Mock Data

These are real backend-backed defaults or legitimate form placeholders, not mock datasets:
- empty form placeholders in add/edit screens
- translation/UI labels
- hardcoded logo imports
- read-only UI hints

Examples:
- `admin-react/src/components/AdminLoginScreen.tsx`
- `admin-react/src/components/ServicesView.tsx`
- `admin-react/src/components/CMSView.tsx`

## Highest Priority Cleanup Targets

1. `admin-react/src/components/AddonLockedView.tsx`
2. `admin-react/src/utils/auditLogger.ts`
3. `admin-react/src/components/UsersView.tsx`
4. `admin-react/src/components/LocalizationView.tsx`
5. `admin-react/src/components/CMSView.tsx`

