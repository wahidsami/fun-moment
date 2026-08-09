# FUN MOMENT - Phase 9 Mobile 2.0 UI/UX Blueprint

## Executive Summary

FUN MOMENT already has the right functional ingredients for a strong mobile marketplace, but the current Flutter UI reads as an older marketplace template rather than a polished commercial product. The app is one shared Flutter codebase for both customer and provider roles, and the backend/API architecture should remain untouched in this phase.

The current UI has:
- a single shared theme, but only partially centralized
- a real orange brand color and existing logo assets
- reusable helper widgets, but many one-off styles still scattered across screens
- a role-based app shell for the customer journey
- provider-capable flows, but no clearly branded provider workspace

The redesign should therefore focus on:
- a centralized design system
- clearer navigation by role
- stronger information hierarchy
- premium marketplace presentation
- RTL-first quality
- reusable Flutter components
- preserving all existing API contracts and business logic

This phase does not redesign the backend or change the mobile API architecture. It defines the implementation blueprint for the next UI modernization phase.

## Current UI Audit

| Screen | Role | Current Route | Current UI Pattern | API/Data Source | Reusable | Needs Redesign |
|---|---|---|---|---|---|---|
| Splash | Shared | `lib/view/intro/splash.dart` | Logo + loader + version text | App init, locale, shared prefs | Partial | Yes |
| Introduction | Shared | `lib/view/intro/introduction_page.dart` | Carousel onboarding | Local assets + intro flag | Partial | Yes |
| Login | Shared | `lib/view/auth/login/login.dart` | Large hero image, stacked fields, social buttons | Auth services | Yes | Yes |
| Registration | Shared | `lib/view/auth/signup/signup.dart` | 3-step form wizard | Signup services | Yes | Yes |
| Password reset | Shared | `lib/view/auth/reset_password/*` | Form-driven flow | Reset services | Partial | Yes |
| Home | Customer | `lib/view/home/home.dart` | Search bar, sliders, category strip, service cards, recent jobs | Home services | Yes | Yes |
| Bottom navigation | Customer | `lib/view/home/bottom_nav.dart` | 5-tab customer nav | ValueNotifier state | Yes | Yes |
| Categories | Customer | `lib/view/home/categories/all_categories_page.dart` | Grid/list category browsing | Category service | Yes | Yes |
| Search | Customer | `lib/view/search/search_page.dart` and `lib/view/tabs/search/search_tab.dart` | Filter sheets, search bar, list results | Search/filter services | Yes | Yes |
| Service listing | Customer | `lib/view/services/all_services_page.dart`, `service_by_category_page.dart` | Card grid/list with price and saved icon | Services APIs | Yes | Yes |
| Service details | Customer | `lib/view/services/service_details_page.dart` | Hero image, tabs, sticky booking CTA | Service details service | Yes | Yes |
| Provider profile | Customer | `lib/view/services/components/about_seller_tab.dart`, `lib/view/jobs/seller_info.dart` | Inline seller card/tab | Seller/profile service | Partial | Yes |
| Booking flow | Customer | `lib/view/booking/*` | Multi-step flow with personalization and schedule | Booking services | Yes | Yes |
| Payment flow | Customer | `lib/view/booking/payment_choose_page.dart`, `lib/view/payments/*` | Gateway-specific pages | Payment services | Yes | Yes |
| Orders | Customer | `lib/view/tabs/orders/orders_page.dart` | Status-based order lists | Orders services | Yes | Yes |
| Order details | Customer | `lib/view/tabs/orders/order_details_page.dart` | Dense detail panel | Order details service | Yes | Yes |
| Saved services | Customer | `lib/view/tabs/saved_item_page.dart` | Saved list | Saved items service | Yes | Yes |
| Wallet | Shared | `lib/view/wallet/wallet_page.dart` | Financial list/card layout | Wallet service | Partial | Yes |
| Chat list | Shared | `lib/view/live_chat/chat_list_page.dart` | Conversation list | Chat list service | Yes | Yes |
| Chat messages | Shared | `lib/view/live_chat/chat_message_page.dart` | Bubble chat with attachments | Chat messages service | Yes | Yes |
| Jobs list | Shared | `lib/view/jobs/my_jobs_page.dart` | Provider job list | Jobs services | Yes | Yes |
| Job create/edit | Shared | `lib/view/jobs/create_job_page.dart`, `edit_job_page.dart` | Form-heavy workspace UI | Jobs services | Yes | Yes |
| Job requests | Shared | `lib/view/jobs/job_request_page.dart` | Request queue | Job request service | Yes | Yes |
| Settings / menu | Shared | `lib/view/tabs/settings/menu_page.dart` | Vertical settings menu | Profile, permissions, wallet, reports, tickets | Yes | Yes |
| Profile edit | Shared | `lib/view/tabs/settings/profile_edit.dart` | Form profile editor | Profile edit service | Partial | Yes |
| Support tickets | Shared | `lib/view/tabs/settings/supports/*` | Ticket list, ticket chat | Ticket services | Partial | Yes |
| Reports | Shared | `lib/view/report/*` | Report list/chat | Report services | Partial | Yes |
| Provider home | Provider | Missing | No clearly branded provider workspace found | Shared provider-capable services | No | Yes |
| Provider service management hub | Provider | Partial | Service owner views exist, but not a full workspace | Seller service APIs | Partial | Yes |
| Provider subscription screen | Provider | Partial | Add-on gating exists, but UX is incomplete | Subscription APIs | Partial | Yes |
| Notifications inbox | Shared | Missing | Only push handling and alerts are visible | Push notification services | No | Yes |

## Current Design System

The current design system is real, but not fully centralized.

### Found in the repo

- Colors are defined in `lib/view/utils/constant_colors.dart`
- Button, input, and app bar styling are centralized in `lib/themes/default_themes.dart`
- Shared helpers exist in `lib/view/utils/common_helper.dart`
- Shared form widgets exist in `lib/view/utils/custom_input.dart`, `custom_button.dart`, `custom_dropdown.dart`, and `field_label.dart`
- Navigation styling is handled in `lib/view/home/bottom_nav.dart`

### Existing visual language

- Primary brand color: `#FF6B2C`
- Background surface: `#FAFAFA`
- Success green: `#65C18C`
- Warning/error red: `#F05454`
- Yellow accent: `#FFC300`
- Borders and neutral grays: `#EAECF0`, `#D0D5DD`, `#98A2B3`, `#667085`, `#475467`, `#344054`, `#1D2939`
- Rounded corners: mostly `8px` to `10px`
- Buttons: orange filled primary buttons, outlined secondary buttons
- Inputs: light bordered text fields with orange focus state
- App bar: white, flat, minimal elevation
- Bottom nav: white background, orange active icon

### Current styling pattern

- Styling is partly centralized and partly repeated directly inside screens
- There is no single modern token system for spacing, typography, elevation, radius, and surface hierarchy
- Several widget copies and duplicated variants exist, including `custom_input copy.dart` and duplicate job helper files
- The app relies on many file-level style decisions instead of a coherent system

### Typography observation

- No authoritative custom font family is configured in the active theme
- The active theme appears to use the platform default text family
- There is a commented historical reference to `Gilroy`
- Text sizes are mostly set ad hoc in widgets, not through a design token ladder

## Official FUN MOMENT Identity Found

The repository does contain an existing, authoritative FUN MOMENT identity:

- Brand name: `Fun Moments`
- Mobile splash and login logo: `assets/images/logo.png`
- App icon assets: `assets/images/app_icon.png` and `assets/images/icon.png`
- Web branding metadata: `web/index.html` and `web/manifest.json`
- Android app label: `Fun Moments`
- iOS app display name: `Fun Moments`
- Primary brand color in UI assets and theme: `#FF6B2C`

No separate new official identity system was found in the repository. There is no alternate authoritative palette, font system, or brand guide that replaces the current orange-led visual identity.

## Visual Problems

### P0

- The app does not yet feel like a cohesive premium marketplace product
- Customer and provider experiences do not have strongly differentiated visual hierarchies
- The navigation model is serviceable, but not yet intentionally designed for role-based marketplace work
- There is no dedicated provider workspace surface with a clear business-dashboard feel

### P1

- Styling is inconsistent across screens even when using the same helper widgets
- Cards, sections, and spacing patterns vary too much between modules
- Many screens are information-dense without a clearer hierarchy
- Search, discovery, and booking screens are functional but not yet fast or elegant enough
- Loading, empty, and error states exist, but are not yet part of a clearly unified system
- RTL support exists, but icon direction and layout balance are not yet consistently treated as first-class design concerns

### P2

- Borders, radius, and shadow usage are not fully standardized
- Some screens look older because they use mixed icon styles and mixed spacing densities
- Button and input styles are reused, but not always consistently applied
- The app would benefit from stronger section headers, clearer data grouping, and a calmer visual rhythm

## New Design Direction

The new FUN MOMENT mobile identity should feel like a premium service marketplace designed for Saudi users first, while remaining equally strong in English.

### Direction keywords

- premium
- trustworthy
- energetic
- marketplace-oriented
- business-friendly
- RTL-first
- clear
- modern
- service-focused
- calm, not noisy

### Visual principles

- Keep the orange brand color, but use it more strategically
- Use fewer borders and more surface hierarchy
- Prefer clean cards with subtle elevation or soft outlines, not heavy decoration
- Make price, duration, availability, and status visually dominant
- Separate customer and provider information architecture instead of reusing one layout everywhere
- Make Arabic layout a first-class design condition, not an adaptation step
- Use restrained motion only where it helps understanding

### Avoid

- generic marketplace templates
- excessive gradients
- excessive glassmorphism
- oversized cards
- cluttered dashboards
- inconsistent rounding
- decorative animation that slows down booking or discovery

## Design Tokens

### Colors

| Token | Proposed Value | Source |
|---|---|---|
| Primary | `#FF6B2C` | Existing repo brand color |
| Primary dark | `#E85A1F` | Derived from existing identity |
| Primary light | `#FFF1EA` | Derived from existing identity |
| Secondary | `#344054` | Existing neutral palette |
| Accent | `#FFC300` | Existing repo accent color |
| Background | `#FAFAFA` | Existing repo background color |
| Surface | `#FFFFFF` | Existing repo surface color |
| Elevated surface | `#F8FAFC` | Neutral supporting surface |
| Text primary | `#1D2939` | Existing neutral palette |
| Text secondary | `#344054` | Existing neutral palette |
| Text muted | `#667085` | Existing neutral palette |
| Border | `#EAECF0` | Existing repo border color |
| Success | `#65C18C` | Existing repo success color |
| Warning | `#FFC300` | Existing repo yellow accent |
| Error | `#F05454` | Existing repo warning color |
| Info | `#2E90FA` | Suggested info tone |

### Typography

| Token | Size | Weight | Line Height | Use |
|---|---:|---:|---:|---|
| Display | 32 | 700 | 1.15 | Hero titles |
| H1 | 28 | 700 | 1.20 | Main page titles |
| H2 | 22 | 700 | 1.25 | Section headers |
| H3 | 18 | 700 | 1.30 | Card titles |
| Body large | 16 | 500 | 1.45 | Lead content |
| Body | 14 | 400 or 500 | 1.45 | Standard text |
| Body small | 13 | 400 | 1.40 | Secondary text |
| Caption | 12 | 400 | 1.35 | Meta labels |
| Button | 14 | 600 | 1.00 | CTA labels |
| Label | 13 | 600 | 1.20 | Form labels |

### Font family

- No verified branded font family exists in the repository
- The safest redesign path is to keep a clean platform-native family until a formal brand font is supplied
- If a new font is introduced later, it should be defined centrally for Arabic and English together

## Spacing

The current app uses mixed spacing values rather than a formal scale. The redesign should standardize on a coherent set:

- 4
- 8
- 12
- 16
- 20
- 24
- 32
- 40
- 48

This scale fits the current visual language and gives enough room for premium commerce layouts without making cards too heavy.

## Component System

Reusable Flutter components should be standardized around these building blocks:

- AppBar
- BottomNavigation
- PrimaryButton
- SecondaryButton
- TextButton
- Input
- SearchBar
- ServiceCard
- ProviderCard
- CategoryCard
- BookingCard
- OrderCard
- ReviewCard
- PriceRow
- StatusBadge
- Avatar
- Rating
- EmptyState
- ErrorState
- LoadingState
- Skeleton
- BottomSheet
- ConfirmationDialog
- SectionHeader
- FilterChip
- DateSelector
- TimeSlot
- PaymentMethod
- WalletCard
- SubscriptionCard
- JobCard
- ChatBubble

### What already exists

- `CommonHelper` is the current shared UI helper layer
- `CustomInput`, `CustomButton`, `CustomDropdown`, and `FieldLabel` already exist
- `BottomNav` already exists for the customer shell
- `ServiceCard` already exists as a reusable marketplace card

### What needs consolidation

- repeated borders, radius, and text styles
- duplicated helper files
- screen-level styling that bypasses shared components
- separate representations for customer and provider cards

## Customer Navigation

The current customer nav is:

- Home
- Orders
- Saved
- Search
- Menu

That structure works, but it is not yet the best commerce-first navigation for Mobile 2.0.

### Recommended structure

- Home
- Discover
- Bookings
- Saved
- Profile or More

### Capability mapping

- Home keeps hero content, recommendations, and active booking entry points
- Discover combines search, categories, filters, and lists
- Bookings keeps order lifecycle and active service tracking
- Saved remains favorites and watched services
- Profile or More keeps account, support, wallet, and settings

## Customer Home

The current home screen hierarchy is:

- greeting and profile area
- search bar
- slider/banner
- categories
- top-rated services
- recent services
- recent jobs

### Proposed hierarchy

- greeting and current context
- location if applicable
- search entry
- category row or chip strip
- promotional banner
- featured services
- popular services
- recommended services
- provider highlights
- active booking or active order
- secondary marketplace content

### Data states

- The layout must work with many records
- The layout must work with few records
- The layout must work with zero records
- The layout must show real loading skeletons
- The layout must show real error states

## Discovery

The current discovery surfaces are functional but visually uneven.

### Redesign goals

- make search the fastest path to service discovery
- make price, rating, and availability easy to compare
- make filters obvious but not dominant
- make category browsing feel lighter and faster
- make service cards more scannable
- make provider cards more trustworthy and less generic

### Discovery structure

- category browsing
- search
- filters
- sorting
- service cards
- provider cards

## Service Details

The current detail page already has the right business sections, but the hierarchy can be much stronger.

### Recommended hierarchy

- service hero image
- title
- rating and review count
- provider summary
- price and duration
- booking CTA
- overview
- included features
- extras
- availability
- FAQ
- reviews

### Main UX rule

- The booking CTA must remain visually dominant at all times
- The page should never make users hunt for the price or the next step

## Booking

The booking experience is a critical revenue flow and should feel much simpler in Mobile 2.0.

### Recommended flow

- service
- date
- time
- extras
- customer details
- summary
- payment
- confirmation

### Booking design requirements

- show price early and keep it visible
- show duration clearly
- show selected date and time clearly
- show extras in a concise, scannable summary
- show taxes, discounts, and total in one place
- preserve backend booking rules exactly as they are
- never show success until backend confirmation is complete

## Checkout

The checkout and payment screens should feel trustworthy, calm, and explicit.

### Required states

- selected payment method
- wallet payment if supported
- gateway payment
- processing state
- success state after backend confirmation
- failure state with retry
- cancelled state with clear next action

### Design rule

- no fabricated success state
- no optimistic business confirmation before the server confirms it

## Orders

The current order experience is business-capable, but it needs a clearer status-first presentation.

### Required sections

- upcoming
- active
- completed
- cancelled

### Order detail must show

- provider
- service
- date
- time
- price
- payment
- status
- available actions

## Provider Experience

The provider experience should feel like a professional workspace, not a customer screen with different labels.

### Recommended provider hierarchy

- dashboard overview
- today’s bookings
- pending orders
- earnings snapshot
- wallet
- services
- jobs
- chat
- subscription

### Key gap

- A dedicated provider home/workspace is missing as a clearly branded entry surface

## Provider Service Management

The backend service management exists, but the Flutter UI does not yet expose a complete provider workspace for it.

### UI blueprint

- service list
- add service
- edit service
- delete service
- enable or disable service
- pricing
- duration
- extras
- benefits
- FAQs
- images
- availability
- approval status

### Backend dependency

- Use the existing seller/service APIs already present in the Laravel backend
- Do not invent new service business logic in the app
- If a capability is not exposed by the current backend, document the gap instead of faking it

## Provider Subscription

The backend subscription system is real, but the current Flutter subscription UI is incomplete.

### UI blueprint

- current plan
- available plans
- plan comparison
- price
- duration
- benefits
- renewal
- expiry
- subscription status
- wallet payment
- confirmation
- failure

### Backend dependency

- Use the existing subscription APIs and module permission checks
- Keep entitlement logic server-driven

## Chat

The chat experience should look modern and status-aware.

### Required UI pieces

- conversation list
- conversation view
- message state
- attachment handling
- unread state
- read state

### Design notes

- use tighter message grouping
- show sender context clearly
- keep timestamps lightweight
- keep attachment handling explicit

## Jobs

Jobs should feel like a professional hiring workflow rather than a generic list.

### Required UI pieces

- job list
- job details
- provider responses
- customer responses
- conversation entry point
- hiring or payment state

### Design note

- job cards should be different from service cards

## Wallet

Wallet screens should emphasize trust, balance clarity, and transaction history.

### Required UI pieces

- current balance
- transaction list
- deposit
- deduction
- payment states
- payout states where supported

### Design note

- avoid making wallet screens look like settings pages

## Loading States

Every major screen should have a consistent skeleton strategy.

### Shared loading pattern

- page title skeleton
- hero or header skeleton
- card list skeleton
- action button skeleton
- details skeleton for forms and checkout

### Rule

- loading should never look like real business data

## Empty States

Empty states should be legitimate, not fabricated.

### Shared empty pattern

- simple illustration or icon
- clear title
- short explanation
- one primary action

### Important

- do not use fake records to make the screen feel populated

## Error States

Every important screen should fail visibly and honestly.

### Error pattern

- short error title
- readable explanation
- retry action
- no fake fallback content

### Important

- API failure should never silently become mock data

## RTL / Arabic

The new design must be RTL-first.

### Areas to audit carefully

- icon direction
- back buttons
- cards
- forms
- numbers
- prices
- dates
- time
- alignment
- bottom navigation
- horizontal lists
- chat bubbles

### Direction rule

- Arabic should feel native, not mirrored as an afterthought
- English should remain equally polished

## Accessibility

Accessibility should be part of the token system, not added late.

### Minimum standards

- touch targets should remain comfortably tappable
- text contrast should stay readable
- body text should not shrink below practical marketplace usability
- semantic labels should be added for core actions
- dynamic text should not break key booking screens
- keyboard and screen-reader behavior should be preserved where relevant

## Animation

Animation should be restrained.

### Appropriate uses

- page transitions
- button feedback
- loading transitions
- success confirmation
- bottom sheet motion
- card interaction feedback

### Avoid

- decorative motion that slows down commerce tasks
- large animations on every screen

## Implementation Architecture

### What can be reused

- shared auth services
- booking services
- payment services
- chat services
- jobs services
- wallet service
- rtl service
- profile service
- permissions service
- current backend API contracts

### What should be replaced

- screen-level visual composition
- ad hoc spacing and borders
- mixed card styles
- old bottom navigation presentation
- repeated hero/header patterns

### What should remain untouched

- API contracts
- Laravel business logic
- PostgreSQL schema
- payment logic
- booking logic
- authentication architecture
- role architecture
- permission architecture
- module architecture

### Where the design system should live

- colors and tokens should remain centralized
- typography should be centralized
- spacing should be centralized
- reusable marketplace components should live in a shared widget layer
- screen-specific pages should consume shared widgets rather than reimplementing them

## Screen-by-Screen Migration Plan

### Phase 1

- create the token system
- centralize typography
- standardize buttons, inputs, cards, and empty/error/loading states

### Phase 2

- redesign splash, intro, login, signup, and reset flows

### Phase 3

- redesign the customer shell and navigation
- redesign home and discovery

### Phase 4

- redesign service details, booking, checkout, and payment

### Phase 5

- redesign customer orders, saved items, wallet, chat, support, and reports

### Phase 6

- create a true provider workspace
- redesign provider dashboard, services, subscription, jobs, and financial surfaces

### Phase 7

- perform RTL polish
- accessibility review
- component cleanup
- state and empty/error alignment

## Files To Modify

The next implementation phase will likely touch these areas first:

- `lib/themes/default_themes.dart`
- `lib/view/utils/constant_colors.dart`
- `lib/view/utils/common_helper.dart`
- `lib/view/utils/custom_input.dart`
- `lib/view/utils/custom_button.dart`
- `lib/view/utils/custom_dropdown.dart`
- `lib/view/home/bottom_nav.dart`
- `lib/view/home/home.dart`
- `lib/view/home/components/*`
- `lib/view/auth/login/login.dart`
- `lib/view/auth/signup/signup.dart`
- `lib/view/intro/splash.dart`
- `lib/view/services/service_details_page.dart`
- `lib/view/booking/*`
- `lib/view/tabs/orders/*`
- `lib/view/live_chat/*`
- `lib/view/jobs/*`
- `lib/view/wallet/wallet_page.dart`
- `lib/view/tabs/settings/*`

## Files To Leave Untouched

These should remain unchanged unless a real UI bug forces a small fix:

- `backend/**`
- API controller logic
- payment gateway implementations
- booking state logic
- order state logic
- auth and permission services
- PostgreSQL schema
- module gating logic

## Backend Dependencies

The redesign depends on existing backend services already present in the platform:

- login and signup APIs
- profile and permission APIs
- home and discovery APIs
- service detail and seller APIs
- booking and payment APIs
- orders and order details APIs
- wallet APIs
- chat APIs
- jobs APIs
- support and report APIs
- subscription and module permission APIs

### Backend gaps that affect UI

- there is no clearly branded provider home endpoint visible as a separate UI surface
- there is no dedicated in-app notification inbox in the current Flutter tree
- provider management UX is incomplete even though the backend capability exists
- some advanced role and module screens still need clearer product mapping before redesign

## Risks

- redesigning screens before consolidating shared widgets will create more inconsistency
- changing navigation too aggressively could confuse existing customers
- provider screens may require more careful mapping than customer screens
- mixing design work with backend changes would increase release risk
- RTL polish can regress if it is treated as a final pass instead of a core requirement

## Recommended Implementation Order

1. Centralize tokens, typography, spacing, and state components
2. Redesign auth and entry screens
3. Redesign the customer shell and discovery flow
4. Redesign service details and booking
5. Redesign checkout, orders, and support surfaces
6. Build the provider workspace and management flows
7. Finish RTL, accessibility, and component cleanup

# FUN MOMENT MOBILE 2.0 DESIGN READINESS

## Is the current codebase structurally ready for UI redesign?

Yes. The app already has a single shared Flutter codebase, a functioning provider pattern, a shared service layer, and reusable widgets that can be refactored into a stronger design system.

## What must be refactored before UI implementation?

- centralized spacing and typography tokens
- shared card, button, input, and empty-state components
- repeated style definitions in screen files
- customer/provider navigation structure
- any duplicated widget files that are acting as accidental variants

## What can be redesigned immediately?

- splash
- introduction
- login
- signup
- home
- discovery
- service details
- booking
- checkout
- orders
- saved items
- wallet
- chat
- settings

## What backend gaps affect UI?

- provider workspace mapping is incomplete at the product-surface level
- notification inbox support is not clearly exposed as a full screen
- some provider management UX still needs clear endpoint-to-screen mapping
- module and permission-gated experiences need to stay backend-driven

## What screens should be implemented first?

- design system foundations
- auth and entry screens
- customer home and discovery
- service details
- booking and checkout
- customer orders

## What screens should not be touched until later?

- provider subscription workspace
- provider service management workspace
- advanced jobs/workflow surfaces
- any screen whose UI depends on unresolved backend capability mapping

