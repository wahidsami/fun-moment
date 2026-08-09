# VPS Deployment Guide (Backend + Dashboard + Mobile)

## 1) VPS Requirements

- Ubuntu 22.04+ (recommended)
- Nginx
- PHP 8.1+ with extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo`, `pdo_pgsql`, `tokenizer`, `xml`, `zip`
- Composer 2.x
- Node.js 18+
- PostgreSQL 14+
- Redis (recommended)
- Supervisor (queue workers)
- Certbot (SSL)

## 2) Backend Deploy Steps

```bash
cd /var/www
sudo mkdir -p funmoments
sudo chown -R $USER:$USER funmoments
cd funmoments

# clone your repo
# git clone <REPO_URL> .

cd backend
cp .env.example .env
composer install --no-dev --optimize-autoloader
npm install
npm run build || npm run prod || npm run dev
```

Set production `.env` values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.com`
- `DB_CONNECTION=pgsql`
- `DB_HOST=<vps-or-managed-db-host>`
- `DB_PORT=5432`
- `DB_DATABASE=<db_name>`
- `DB_USERNAME=<db_user>`
- `DB_PASSWORD=<db_password>`
- `QUEUE_CONNECTION=database` (or `redis`)
- Real credentials for mail, pusher, twilio, payment gateways

Then run:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 3) Queue + Scheduler

### Supervisor

Create `/etc/supervisor/conf.d/funmoments-worker.conf`:

```ini
[program:funmoments-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/funmoments/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/funmoments/backend/storage/logs/worker.log
stopwaitsecs=3600
```

Apply:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start funmoments-worker:*
```

### Cron

```bash
* * * * * php /var/www/funmoments/backend/artisan schedule:run >> /dev/null 2>&1
```

## 4) Nginx

Point site root to:

`/var/www/funmoments/backend/public`

After Nginx config, enable SSL with Certbot.

## 5) Mobile Release Wiring

Build app with production API URL:

```bash
flutter build appbundle --release --dart-define=BASE_API=https://your-domain.com/api/v1
flutter build ipa --release --dart-define=BASE_API=https://your-domain.com/api/v1
```

Optional map key override:

```bash
--dart-define=GOOGLE_MAPS_API_KEY=YOUR_KEY
```

## 6) Safe Deployment Flow

1. Push to `staging` branch and deploy to staging domain first.
2. Run smoke tests: login, services list, booking, payment sandbox, chat.
3. Promote to `main` and deploy production.
4. Build/release mobile app against production API.

## 7) Rollback Strategy

- Keep previous release folder and symlink-based deploy, or keep previous git tag.
- Rollback command pattern:
  - checkout previous tag/commit
  - `composer install --no-dev`
  - `php artisan migrate:status` (avoid destructive rollback unless needed)
  - `php artisan config:cache`
