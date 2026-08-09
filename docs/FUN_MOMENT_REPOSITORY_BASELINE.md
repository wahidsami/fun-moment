# FUN MOMENT Repository Baseline

## 1. Repository Purpose

FUN MOMENT is a multi-surface service marketplace platform composed of:

- a Flutter mobile application
- a Laravel backend and public website
- a React admin dashboard
- shared documentation and helper scripts

The repository is the source-of-truth workspace for the full platform.

## 2. Current Architecture

- Mobile app:
  - Flutter app in the repository root
  - serves both customer and provider experiences
- Backend:
  - Laravel application under [`backend/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend)
  - serves API, public website, buyer dashboard, seller dashboard, and legacy admin routes
- Admin:
  - React admin dashboard under [`admin-react/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/admin-react)
- Database:
  - PostgreSQL database named `funmoments`
- Documentation:
  - project docs under [`docs/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/docs)
- Scripts:
  - local run scripts under [`scripts/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/scripts)

## 3. Flutter Location

Flutter source is at the repository root:

- [`lib/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/lib)
- [`android/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/android)
- [`ios/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/ios)
- [`web/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/web)

## 4. Laravel Location

Laravel backend lives in:

- [`backend/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend)

Relevant subpaths include:

- [`backend/routes/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/routes)
- [`backend/resources/views/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/resources/views)
- [`backend/database/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/database)
- [`backend/storage/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/backend/storage)

## 5. React Admin Location

React admin dashboard lives in:

- [`admin-react/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/admin-react)

## 6. Documentation Location

Current documentation is stored in:

- [`docs/`](/D:/Waheed/MypProjects/FUN%20MOMENT/funMoments-main%202/docs)

Key documents already present include platform audits and implementation reports.

## 7. What Is Tracked

The active baseline should track:

- Flutter source code
- Laravel backend source code
- React admin source code
- documentation
- helper scripts
- approved static assets such as `logo.png` and the production app image assets
- safe client configuration files required for builds

## 8. What Is Ignored

The baseline ignores:

- Flutter build outputs and tool caches
- Android local build files
- iOS build caches and Pods
- Node dependencies
- Laravel vendor and cache directories
- environment files with secrets
- database exports and backups
- archive/legacy packaging directories
- editor/system junk files

## 9. Secret Handling

Secret-bearing files must not be committed, including:

- backend `.env`
- payment secrets
- mail credentials
- database passwords
- Pusher secrets
- private keys and certificates

Safe client configuration files that may be tracked because the app needs them to build include:

- `android/app/google-services.json`
- `lib/firebase_options.dart`

Those files are client configuration, not private secret stores.

## 10. Local Development Requirements

Local development for FUN MOMENT requires:

- Flutter SDK
- PHP 8.1+
- Composer
- Node.js for `admin-react`
- PostgreSQL
- Laravel environment configuration
- Firebase and Google Maps configuration for the mobile app

## 11. Future Staging Deployment

For staging or production deployment:

- Laravel backend should be deployed first
- the database should be migrated separately
- the public website should be served by the Laravel backend
- the React admin should be deployed only if it is intended to be the active admin frontend
- the Flutter app should be rebuilt with the correct backend API URL

## 12. Legacy API Warning

`funmoments.sa/api/v1` is a legacy default API target in the Flutter app and should not be assumed to be the final production endpoint for future deployments.

## 13. Disabled Module Flags

Current backend module flags are disabled for:

- LiveChat
- Subscription
- JobPost
- Wallet

These modules exist in the codebase but are not active in the current local backend state.

## 14. Database Migration Warning

The local PostgreSQL database already contains seeded/demo content and must be migrated with care.

Do not assume the database is empty.
Do not copy development-only demo rows into production without review.
