# Local Setup Guide (Backend + Dashboard + Mobile)

This guide prepares the full Fun Moments system on your local machine using PostgreSQL (managed via pgAdmin).

## 1) PostgreSQL (pgAdmin)

Create a database and user:

1. Open pgAdmin.
2. Create DB: `funmoments`.
3. Create/choose a user with full privileges on `funmoments`.
4. Note these values:
   - Host (usually `127.0.0.1`)
   - Port (usually `5432`)
   - Database (`funmoments`)
   - Username
   - Password

## 2) Backend + Dashboard (Laravel)

From project root:

```powershell
./scripts/setup-backend-local.ps1
```

Then edit `backend/.env` and ensure:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=funmoments
DB_USERNAME=postgres
DB_PASSWORD=your_password
QUEUE_CONNECTION=database
```

After editing `.env`, run:

```powershell
cd backend
php artisan config:clear
php artisan cache:clear
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

Dashboard URL: `http://127.0.0.1:8000/admin-home`
API base URL: `http://127.0.0.1:8000/api/v1`

## 3) Mobile App (Flutter)

In a second terminal at project root:

```powershell
./scripts/run-mobile-local.ps1 -BaseApi http://10.0.2.2:8000/api/v1
```

For physical phone, use your PC LAN IP:

```powershell
./scripts/run-mobile-local.ps1 -BaseApi http://192.168.1.100:8000/api/v1
```

## 4) Common Issues

- `could not find driver`: enable `pdo_pgsql` in PHP.
- `connection refused`: check PostgreSQL service is running and credentials are correct.
- mobile cannot reach backend: use `10.0.2.2` for Android emulator, machine LAN IP for real device.

## 5) Pre-Deploy Checklist

- Local login works for mobile and dashboard.
- Booking flow works end-to-end.
- Payment gateways set to sandbox in local.
- Queue jobs run if features depend on background jobs.
