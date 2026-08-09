# FUN MOMENT - Phase 6 Add-ons E2E Validation

## Subscription

Status: REAL

What exists:

- Admin subscription management routes exist in Laravel.
- Provider subscription info/history endpoints exist in the API.
- Provider renewal is wallet-backed.
- Service creation is gated when the platform runs in subscription commission mode.
- Subscription module availability is controlled by backend module checks.

Key references:

- [`backend/routes/api.php`](../backend/routes/api.php)
- [`backend/routes/admin.php`](../backend/routes/admin.php)
- [`backend/app/Http/Controllers/Api/SellerSubscriptionController.php`](../backend/app/Http/Controllers/Api/SellerSubscriptionController.php)
- [`backend/app/Http/Controllers/Api/SellerController.php`](../backend/app/Http/Controllers/Api/SellerController.php)
- [`backend/app/Http/Livewire/AddService.php`](../backend/app/Http/Livewire/AddService.php)
- [`backend/app/Http/Controllers/Frontend/SellerController.php`](../backend/app/Http/Controllers/Frontend/SellerController.php)

Observed lifecycle:

1. Admin manages plans in backend/admin surfaces.
2. Provider sees subscription info and history via API.
3. Provider renews using wallet funds.
4. Active subscription unlocks service-creation entitlement.
5. Backend enforces expiry and service-count limits.

Failure / disabled-module behavior:

- If the subscription module is disabled, the related routes are not mounted.
- If no subscription exists, the backend returns a no-subscription response rather than fake data.

## Wallet

Status: REAL

What exists:

- Wallet balance is stored in the backend database.
- Wallet history is stored in the backend database.
- Wallet deposit, wallet deduction, and wallet balance APIs are real.
- Wallet-backed payment paths are used for subscription renewal and some payment flows.

Key references:

- [`backend/routes/api.php`](../backend/routes/api.php)
- [`backend/app/Http/Controllers/Api/UserController.php`](../backend/app/Http/Controllers/Api/UserController.php)
- [`backend/app/Http/Controllers/Api/SellerController.php`](../backend/app/Http/Controllers/Api/SellerController.php)
- [`lib/service/wallet_service.dart`](../lib/service/wallet_service.dart)
- [`lib/view/wallet/wallet_page.dart`](../lib/view/wallet/wallet_page.dart)

Observed lifecycle:

1. Wallet record exists in PostgreSQL.
2. Balance and history are fetched from backend endpoints.
3. Deposit flows create wallet history records.
4. Payment callbacks update wallet balance and transaction state.
5. Provider renewal can deduct from wallet balance.

Notes:

- No local fake wallet balance is being used as the source of truth in the inspected path.
- Empty wallet history is handled as empty state, not fabricated content.

## Chat

Status: REAL, with one missing backend capability

What exists:

- Customer ↔ provider chat is backed by the database.
- Chat conversations and messages persist in backend models.
- Chat attachments are supported.
- Real-time event hooks are present through message-sent events and push-notification wiring.
- Module access is permission-controlled.

Key references:

- [`backend/routes/api.php`](../backend/routes/api.php)
- [`backend/app/Http/Controllers/Api/BuyerChatController.php`](../backend/app/Http/Controllers/Api/BuyerChatController.php)
- [`backend/app/Http/Controllers/Api/SellerChatController.php`](../backend/app/Http/Controllers/Api/SellerChatController.php)
- [`lib/service/live_chat/chat_list_service.dart`](../lib/service/live_chat/chat_list_service.dart)
- [`lib/service/live_chat/chat_message_service.dart`](../lib/service/live_chat/chat_message_service.dart)
- [`lib/view/live_chat/chat_message_page.dart`](../lib/view/live_chat/chat_message_page.dart)

Observed lifecycle:

1. Customer or provider opens live chat.
2. Backend returns real counterpart lists from persisted messages.
3. Messages are loaded from the database.
4. New messages are stored and emitted through event/push flows.
5. Attachments can be uploaded and persisted.

Missing backend capability:

- I did not find a clear persistent message-level read-receipt implementation for chat in the inspected code.
- Unread notifications exist for other parts of the app, but chat read/unread state is not clearly persisted as a first-class backend field in the inspected live-chat flow.

## Jobs

Status: REAL

What exists:

- Customer job creation exists.
- Provider job discovery exists.
- Provider request/response/conversation flows exist.
- Wallet-backed job hiring paths exist where supported.
- Job module access is module-gated.

Key references:

- [`backend/routes/api.php`](../backend/routes/api.php)
- [`backend/app/Http/Controllers/Api/BuyerJobController.php`](../backend/app/Http/Controllers/Api/BuyerJobController.php)
- [`backend/app/Http/Controllers/Api/SellerJobController.php`](../backend/app/Http/Controllers/Api/SellerJobController.php)
- [`backend/app/Http/Controllers/Api/JobDetailsController.php`](../backend/app/Http/Controllers/Api/JobDetailsController.php)
- [`lib/service/jobs_service/my_jobs_service.dart`](../lib/service/jobs_service/my_jobs_service.dart)
- [`lib/service/jobs_service/job_request_service.dart`](../lib/service/jobs_service/job_request_service.dart)

Observed lifecycle:

1. Customer creates a job.
2. Backend persists the job.
3. Providers discover available jobs.
4. Providers respond or converse through backend storage.
5. Customer receives responses through the same backend data.

## Permission Tests

Status: REAL

What exists:

- Flutter reads backend permissions via `/module-permission`.
- Menu items are blocked at runtime when permissions are disabled.
- Backend routes for add-ons are wrapped with `moduleExists(...)` checks.

Key references:

- [`lib/service/permissions_service.dart`](../lib/service/permissions_service.dart)
- [`lib/view/tabs/settings/menu_page.dart`](../lib/view/tabs/settings/menu_page.dart)
- [`lib/view/tabs/settings/components/chat_icon.dart`](../lib/view/tabs/settings/components/chat_icon.dart)
- [`backend/app/Http/Controllers/Api/MiscellaneousController.php`](../backend/app/Http/Controllers/Api/MiscellaneousController.php)
- [`backend/routes/api.php`](../backend/routes/api.php)
- [`backend/routes/admin.php`](../backend/routes/admin.php)

Observed permission behavior:

- Enable module: related routes and UI access become available.
- Disable module: related backend routes disappear and the app should not expose the feature.
- Re-enable module: access returns through the same backend source of truth.

## Module Toggle Tests

Status: REAL AT ARCHITECTURE LEVEL

Subscription:

- Controlled by backend module checks and commission mode logic.

Wallet:

- Controlled by backend module checks in routes and wallet-specific code paths.

Live Chat:

- Controlled by backend module checks in routes and chat-specific code paths.

Jobs:

- Controlled by backend module checks in routes and job-specific code paths.

Expected result:

- Toggle changes are reflected by backend permissions and route availability, not local client storage.

## Data Integrity

Status: REAL

What is persistent:

- subscription records
- wallet balances
- wallet history
- chat messages
- jobs
- job conversations
- provider entitlements

What is not used as source of truth:

- local fake balances
- mock conversations
- mock subscription states
- mock job lists

Important note:

- Loading and empty states still exist in the UI. Those are correct and are not mock data.

## Production Blockers

1. Chat read-receipt state is not clearly implemented as a persistent backend capability in the inspected live-chat flow.
2. The add-ons themselves are real and backend-backed, but full operational validation still requires live environment smoke tests for:
   - subscription purchase/renew/expiry
   - wallet deposit/deduction
   - chat message sync and real-time delivery
   - job create/discover/respond flow

## Summary

The four add-ons are real and backend-backed:

- Subscription
- Wallet
- Live Chat
- Jobs

The platform uses backend module checks and runtime permissions rather than client-side mock records. The main remaining gap found in this phase is chat read-state persistence, which should be treated as a backend capability gap, not a frontend placeholder.

