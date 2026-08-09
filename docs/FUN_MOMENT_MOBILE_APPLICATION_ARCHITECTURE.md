# FUN MOMENT Mobile Application Architecture

## Scope
This document is an investigation-only architecture report for the mobile application in this repository. It does not change source code.

## Executive Finding
FUN MOMENT is currently built as **one Flutter mobile application**, not two separate mobile apps.

The same Flutter codebase serves both:
- the **customer / buyer** experience
- the **service provider / seller** experience

The split is implemented by:
- shared authentication
- role-aware backend responses
- permission checks
- separate feature screens inside the same app

There is no evidence in this repository of a second Flutter entrypoint, a separate provider app project, or flavor-based mobile app split.

## Evidence Summary

### 1) Single Flutter app entrypoint
- The repository has one Flutter project root with one `pubspec.yaml`.
- The main mobile entrypoint is `lib/main.dart`.
- No alternate entrypoints such as `main_customer.dart`, `main_provider.dart`, or `main_seller.dart` were found.

### 2) Single Android / iOS mobile identity
- Android application id: `com.sa.funmomments`
- iOS display name: `Fun Moments`
- iOS bundle identifier uses the standard Flutter placeholder `$(PRODUCT_BUNDLE_IDENTIFIER)`
- No mobile flavors were found in the Android or iOS build files.

### 3) Shared API base
- The Flutter app uses one API base configuration through `BASE_API`.
- Default mobile API base is `https://funmoments.sa/api/v1`.

### 4) One login system
- Login is handled by a shared auth service.
- Registration is also shared.
- The same login flow is used regardless of whether the signed-in user later behaves as a customer or a provider.

### 5) Role separation happens inside the app
- Customer-facing features and provider-facing features are both present in the same Flutter source tree.
- Backend permissions and user/profile data control which parts of the app are active.

## Definitive Answers

### 1. How many Flutter mobile applications are in the repo?
One.

### 2. Is there a separate customer app and provider app?
No separate mobile apps were found. There is one Flutter app with both customer and provider flows.

### 3. Is there a separate provider-only Flutter entrypoint?
No.

### 4. Is the provider experience web-only?
No. Provider-facing screens and services exist in Flutter.

### 5. Is the customer experience also in Flutter?
Yes.

### 6. Does the app use one shared login system?
Yes.

### 7. Does the app use one shared backend API base?
Yes.

### 8. Does the app use flavors to split customer vs provider builds?
No flavor split was found.

### 9. Does the app use different package IDs for customer vs provider?
No evidence of separate package IDs was found.

### 10. Are buyer/customer and seller/provider features present in the same codebase?
Yes.

### 11. Are seller/provider capabilities implemented as real Flutter screens?
Yes.

### 12. Are seller/provider capabilities implemented as backend-only routes?
No. Backend routes exist, but the Flutter app also contains the UI and services for them.

### 13. Is the mobile app tied to the same backend as the website?
Yes, through the shared Laravel API.

### 14. Does the app look role-based at runtime?
Yes. Runtime permissions and profile data gate features.

### 15. Is there explicit mock-only architecture for the mobile app?
No explicit mock-only mobile architecture was found.

### 16. Is the architecture best described as one marketplace app with two roles?
Yes.

## Application Structure

### Core startup flow
1. `lib/main.dart` initializes Flutter, Firebase, local notifications, maps, and providers.
2. `lib/view/intro/splash.dart` runs the first screen.
3. The splash flow sends first-time users to intro screens, then to the landing page.
4. If a token exists, login state is reused.

### Authentication flow
- Login request is sent to `POST /login`
- Registration request is sent to `POST /register`
- Auth token, user id, and remember-me data are stored in `SharedPreferences`
- After login, profile data is loaded from `GET /user/profile`
- Email verification is part of the auth flow

### Customer-facing screens and flows
These are customer/buyer-oriented features inside the Flutter app:
- home / landing page
- category browsing
- service search and filtering
- service details
- booking / checkout / payment
- orders
- saved items
- profile edit
- support tickets
- password management
- account deletion

### Provider-facing screens and flows
These are provider/seller-oriented features inside the same Flutter app:
- my jobs
- job requests
- job conversations
- seller service listings
- seller service details
- live chat
- wallet
- order handling actions
- report and dispute-related screens
- permission-gated add-on modules

## Provider Features Found In Flutter
The Flutter app contains provider-specific or seller-specific UI and services for:
- jobs
- live chat
- wallet
- seller service management
- order response actions
- notification interests for seller channels

This is strong evidence that the provider experience is **not web-only**.

## Customer Features Found In Flutter
The Flutter app contains customer-facing UI and services for:
- home feed
- service discovery
- bookings
- payments
- saved services
- customer orders
- support tickets
- profile management
- login and registration

## Backend Coupling
The mobile app is tightly coupled to the Laravel backend through API calls.

Important examples:
- `POST /login`
- `POST /register`
- `GET /user/profile`
- `GET /module-permission`
- `GET /user/chat/seller-lists`
- `POST /service/order`
- seller order and job actions through seller-prefixed API routes

This means the mobile app is not a standalone frontend. It is a client for the Laravel platform.

## Permission And Module Control
The app already contains a permission layer:
- job permission
- subscription permission
- chat permission
- wallet permission

These permissions come from the backend and are used to hide or block feature access in the Flutter UI.

## Mock Data Audit
No explicit mock-data-only pages were identified in the mobile app scan.

What was found instead:
- placeholder images
- empty states
- loading states
- generic fallback UI

That means the app may still show placeholder content in some states, but there is no evidence of a separate mock mobile architecture or a mock-only customer/provider split.

## Architecture Conclusion
FUN MOMENT mobile should be treated as:

**one Flutter marketplace app**
with
**two runtime roles**
inside the same binary:
- customer / buyer
- service provider / seller

So the correct architectural model is **shared app, role-based experience**, not two separate mobile applications.

## Recommended Next Validation Steps
1. Confirm the provider role mapping from backend profile data and permissions.
2. Verify which provider screens are active for real seller accounts.
3. Verify whether any provider features are gated by hidden backend flags.
4. Review the website and backend admin panels separately, since they are not the mobile app.

## Source Files Reviewed
- `lib/main.dart`
- `lib/view/intro/splash.dart`
- `lib/service/auth_services/login_service.dart`
- `lib/view/auth/login/login.dart`
- `lib/view/auth/signup/signup.dart`
- `lib/view/home/landing_page.dart`
- `lib/view/tabs/settings/menu_page.dart`
- `lib/view/home/bottom_nav.dart`
- `lib/service/profile_service.dart`
- `lib/service/permissions_service.dart`
- `lib/view/utils/others_helper.dart`
- `android/app/build.gradle`
- `android/app/src/main/AndroidManifest.xml`
- `ios/Runner/Info.plist`
