param(
  [string]$BackendPath = "backend"
)

$ErrorActionPreference = "Stop"

Write-Host "[1/7] Checking tools..."
php -v | Out-Null
composer --version | Out-Null
npm --version | Out-Null

Write-Host "[2/7] Entering backend folder..."
Set-Location $BackendPath

Write-Host "[3/7] Preparing .env..."
if (-not (Test-Path .env)) {
  Copy-Item .env.example .env
  Write-Host "Created backend/.env from .env.example"
} else {
  Write-Host "backend/.env already exists; keeping existing values."
}

Write-Host "[4/7] Installing PHP dependencies..."
composer install

Write-Host "[5/7] Generating app key (safe to re-run)..."
php artisan key:generate --force

Write-Host "[6/7] Running migrations..."
php artisan migrate

Write-Host "[7/7] Installing frontend dependencies and building assets..."
npm install
npm run dev

Write-Host "Done. Start backend with: php artisan serve --host=0.0.0.0 --port=8000"
