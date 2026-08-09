# FUN MOMENT - Phase 8 Production Release Plan

## Release Versions

- Flutter mobile app: `1.0.0+4` from `pubspec.yaml`
- Laravel backend: `main` branch, commit `d1fbe7c4`
- React admin: `0.0.0` from `admin-react/package.json`; no separate git repository detected in `admin-react/`
- Public website: served from the Laravel backend at commit `d1fbe7c4`

## Deployment Order

1. Create fresh backups of PostgreSQL, storage, and configuration.
2. Verify production environment variables and rotate any local/test secrets before release.
3. Deploy the Laravel backend and website code.
4. Run migrations and refresh caches only after backup confirmation.
5. Build and deploy the React admin bundle.
6. Publish the Flutter mobile release build after backend endpoints are confirmed stable.
7. Run smoke tests across admin, web, mobile, and provider flows.
8. Roll out gradually if the hosting setup supports staged release.

## Database Plan

### Pending Migrations

- None reported by `php artisan migrate:status`.

### Required Indexes

These should be validated in PostgreSQL before launch if they are not already present:

- `orders(seller_id, status, created_at)`
- `orders(buyer_id, status)`
- `orders(service_id)`
- `orders(payment_status)`
- `orders(transaction_id)`
- `services(seller_id, status, is_service_on)`
- `services(slug)`
- `services(category_id, subcategory_id, child_category_id)`
- `services(featured, view)`
- `wallet_histories(buyer_id, payment_status, created_at)`
- `wallet_histories(payment_gateway)`
- `wallet_histories(transaction_id)`
- `seller_subscriptions(seller_id, status, expire_date)`
- `seller_subscriptions(subscription_id)`
- `subscriptions(status)`
- `subscriptions(type)`
- `subscriptions(price)`
- `days(seller_id, day)`
- `schedules(seller_id, day_id, allow_multiple_schedule, status)`
- `live_chat_messages(buyer_id, seller_id, created_at)`
- `buyer_jobs(buyer_id, status, is_job_on, created_at)`
- `job_requests(buyer_id, seller_id, job_post_id, status, created_at)`
- `support_tickets(seller_id, buyer_id, status, priority, created_at)`
- `blogs(status, schedule_date, created_at)`
- `pages(slug, status)`
- `static_options(option_name)`

### Required Configuration

- Database connection and credentials
- Timezone and locale
- Queue driver and worker supervision
- Cache and session drivers
- Mail transport
- Push/real-time configuration
- Storage disks and upload paths
- Payment gateway callback/webhook URLs
- Public application URL

### Required Production Records

- One verified super admin account
- Home page / landing page configuration
- Navigation, footer, and widget configuration
- English and Arabic language records
- Enabled payment gateway records
- Real service categories and subcategories
- Real city/area/location data if used by the app
- Active subscription plans if subscriptions are part of the launch scope
- Real CMS pages, banners, blogs, and notices
- Support and notification templates

### Required Admin Account

- A single verified production super admin must exist and be stored securely outside source control.

### Required Subscription Plans

- Real plans only
- No fabricated test plans in the production database

### Required Categories

- Real categories, subcategories, and service grouping records must exist before launch if the customer journey depends on them.

### Required Service Configuration

- Real service listings
- Real service prices, durations, images, and availability rules
- Real provider activation rules if service publishing depends on approval

### Required CMS Configuration

- Real homepage selection
- Real page-builder blocks
- Real footer/menu widgets
- Real blog/page records

## Environment Plan

Production variables should be confirmed without printing secrets:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `QUEUE_CONNECTION`
- `CACHE_DRIVER`
- `SESSION_DRIVER`
- `SESSION_LIFETIME`
- `BROADCAST_DRIVER`
- `REDIS_HOST`
- `REDIS_PORT`
- `REDIS_PASSWORD`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_ENCRYPTION`
- `PUSHER_APP_ID`
- `PUSHER_APP_KEY`
- `PUSHER_APP_SECRET`
- `PUSHER_APP_CLUSTER`
- payment gateway keys and callback URLs
- storage and CDN variables if used
- currency and localization variables

Production should use HTTPS URLs only, and any test/local credentials must be replaced before launch.

## Backup

### Database Backup

- Take a full PostgreSQL backup before deployment.
- Keep one restore point from immediately before the release.

### Storage Backup

- Back up uploaded images, documents, chat attachments, service media, and any exported files.

### Configuration Backup

- Archive the active environment configuration, web server configuration, queue worker config, scheduler config, and the built React admin artifact.

### Rollback Strategy

- Keep the previous Laravel deployment artifact available.
- Keep the previous React admin build available.
- Keep a verified PostgreSQL restore path available.
- Keep the current mobile release available for rollback if the backend release exposes regressions.

## Smoke Tests

After deployment, run these checks against real production-like data:

1. Admin login
2. Customer registration
3. Provider login
4. Service browsing
5. Service creation
6. Subscription
7. Booking
8. Payment
9. Provider order
10. Customer order
11. Add-ons
12. Notifications
13. Support

Acceptance criteria:

- Each screen loads real backend data.
- Create/update flows persist to PostgreSQL.
- Payment success, failure, and cancellation follow the correct backend state.
- Arabic and English layouts render correctly.
- No mock business records appear in production paths.

## Rollback Plan

### Application Rollback

- Revert to the previous Laravel release artifact.
- Revert to the previous React admin build if the dashboard release breaks admin workflows.

### Database Rollback

- Restore the last known-good PostgreSQL backup if a migration or data change causes a blocker.

### Configuration Rollback

- Reapply the prior validated environment and web server configuration if the new config causes downtime.

### Mobile Release Rollback

- Keep the previous mobile release available while the backend is being validated.
- If a backend incompatibility is discovered, pause the mobile rollout and revert to the previous release channel.

## Launch Checklist

- Backups completed and verified
- Production environment variables reviewed
- Debug mode disabled
- Secrets rotated or replaced
- CORS and auth policies reviewed
- Rate limiting enabled where applicable
- Laravel deployed successfully
- Website assets deployed successfully
- React admin build deployed successfully
- Flutter release build validated
- Migrations reviewed
- Smoke tests passed
- Monitoring and alerting enabled
- Rollback package ready

## Post-Launch Monitoring

Monitor the following closely after release:

- authentication failures
- authorization denials
- payment failures and duplicate callbacks
- booking conflicts
- subscription renewals and expiries
- wallet balance mismatches
- chat/support delivery delays
- admin runtime errors
- slow queries and API timeouts
- storage upload failures
- localization or RTL regressions

