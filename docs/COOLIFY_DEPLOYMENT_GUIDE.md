# Fun Moment Coolify Guide

This is the simplest production setup for this repo.

## What to deploy

1. Deploy the Laravel backend first.
2. Use the Laravel backend domain for the public website and admin.
3. Do not deploy the Flutter mobile app to Coolify.
4. Do not deploy `admin-react` for the first pass unless you specifically want a second frontend.

Reason:

- The public website is already inside `backend/`
- The API is inside `backend/`
- The Laravel admin is inside `backend/`
- The Flutter app only consumes the API

## Project map

1. `backend/`
   - Public website
   - API
   - Admin routes
   - Buyer and seller dashboards
2. `admin-react/`
   - Optional separate admin frontend
   - Not needed for the first deployment
3. Repository root Flutter app
   - Mobile app
   - Built separately, not hosted on Coolify

## Step 1 - Create the Coolify project

Create one Coolify project for the backend.

### In Git

Select:

- Git provider: your GitHub account or GitHub app
- Repository: this repository
- Branch: the branch you want to deploy, usually `main`

### In Coolify

Create a new Application and set:

- Application name: `fun-moment-backend`
- Build pack: `Nixpacks`
- Base directory: `backend`
- Port exposes: `80`
- Public domain: `http://vks008w44skg0cs0gkc80cgo.141.140.0.90.sslip.io`

If Coolify asks for the application URL, use:

- `http://vks008w44skg0cs0gkc80cgo.141.140.0.90.sslip.io`

## Step 2 - Add backend environment variables

Start from the client's existing `.env` and keep every current value unless it is one of the deployment values below.

Replace only these lines in Coolify:

```dotenv
APP_URL=http://vks008w44skg0cs0gkc80cgo.141.140.0.90.sslip.io
APP_KEY=base64:KURTu1uwFlb+pcmYFFIAFqEE0yrLgfJhsRDOaisj+9E=

DB_CONNECTION=pgsql
DB_HOST=a0gos8o4wgwko4wsgkswg48w
DB_PORT=5432
DB_DATABASE=funmoments
DB_USERNAME=postgres
DB_PASSWORD=PASTE_THE_POSTGRES_PASSWORD_YOU_ALREADY_SET
```

Keep the rest of the client values as-is for now:

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG`
- `ASSET_URL`
- `BROADCAST_DRIVER`
- `CACHE_DRIVER`
- `QUEUE_CONNECTION`
- `SESSION_*`
- `FILESYSTEM_*`
- `REDIS_*`
- `MAIL_*`
- `AWS_*`
- `PUSHER_*`
- `VITE_PUSHER_*`
- `PAYTM_*`
- `FACEBOOK_*`
- `GOOGLE_*`
- every other payment gateway key already present in the client file

## Step 3 - Create the PostgreSQL database

Create a PostgreSQL database in Coolify or use an external PostgreSQL server.

Use:

- Database name: `funmoments`
- Database username: `postgres`
- Port: `5432`

## Step 4 - Set persistent storage

You need two different storage choices depending on the resource.

### 4A - PostgreSQL storage

On the PostgreSQL resource, open **Persistent Storage** and click **+ Add**.

When the dropdown shows:

- `Volume Mount`
- `File Mount`
- `Directory Mount`

choose **Volume Mount**.

Use this:

- Name: `funmoments-postgres-data`
- Source Path: leave the default or leave it empty if Coolify allows it
- Destination Path: `/var/lib/postgresql` for PostgreSQL 18 and newer, or `/var/lib/postgresql/data` for PostgreSQL 17 and older

Do not use `File Mount` or `Directory Mount` for PostgreSQL data.

### 4B - Laravel storage

On the Laravel backend application, open **Persistent Storage** and click **+ Add**.

Choose **Directory Mount**.

Set the container path to:

- `/app/storage`

This keeps Laravel uploads and runtime files after redeploys.

Use this:

- Name: `funmoments-storage`
- Source Directory: keep the Coolify default if it is already filled in, or use the app's generated directory path
- Destination Directory: `/app/storage`

## Step 5 - Deploy the backend

Deploy the Laravel backend application.

If Coolify asks for build or install commands, use:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run production
```

Build note:

- `backend/composer.json` now points to the public `xgenious-fundorex/paymentgateway` fork
- `backend/packages/cinetpay/cinetpay-php` now satisfies the `cinetpay/cinetpay-php` dependency locally
- Coolify no longer needs SSH access to the dead third-party dependency repo

If Coolify asks for a post-deploy command, use:

```bash
php artisan optimize:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan optimize
```

## Step 6 - Run Laravel commands

After the app is deployed, run these commands in the backend app container:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Step 7 - Confirm the URLs

Use these URLs for the Laravel deployment:

- Website: `http://vks008w44skg0cs0gkc80cgo.141.140.0.90.sslip.io`
- Admin: `http://vks008w44skg0cs0gkc80cgo.141.140.0.90.sslip.io/admin-home`
- API: `http://vks008w44skg0cs0gkc80cgo.141.140.0.90.sslip.io/api/v1`

## Step 8 - Optional queue worker

If you need background jobs, add a worker that runs:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## Step 9 - Optional scheduler

Run Laravel scheduler every minute:

```bash
php artisan schedule:run
```

## Step 10 - Optional React admin

Only deploy `admin-react/` if you really want the React dashboard.

If you do deploy it, set:

- Base directory: `admin-react`
- Build pack: `Static`
- Publish directory: `dist`
- Build command: `npm run build`

Important:

- `admin-react` expects `/admin-home/...` to be available on the same origin
- if it is on a different domain, its requests can break
- for the first deployment, skip it and use the Laravel admin in `backend/`

## Step 11 - Flutter app

The Flutter app is not hosted on Coolify.

When you build it, point it to the new API:

```bash
flutter build appbundle --release --dart-define=BASE_API=http://vks008w44skg0cs0gkc80cgo.141.140.0.90.sslip.io/api/v1
```

## Simple first deployment order

1. Create PostgreSQL
2. In PostgreSQL Persistent Storage, choose Volume Mount
3. Create the Laravel backend application
4. Set backend environment variables
5. In Laravel Persistent Storage, choose Directory Mount and mount `/app/storage`
6. Deploy
7. Run migrations and `storage:link`
8. Test website, admin, and API
9. Build the Flutter app with the new API URL

## Files used for this guide

- `backend/composer.json`
- `backend/package.json`
- `backend/routes/web.php`
- `backend/routes/api.php`
- `backend/app/Providers/RouteServiceProvider.php`
- `backend/.env.example`
- `admin-react/vite.config.ts`
- `lib/view/utils/others_helper.dart`
