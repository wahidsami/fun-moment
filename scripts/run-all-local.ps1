param(
  [string]$BackendPath = "backend",
  [string]$AdminPath = "admin-react",
  [string]$FlutterBaseApi = "http://127.0.0.1:8000/api/v1",
  [switch]$IncludeMobile,
  [switch]$OpenBrowser
)

$ErrorActionPreference = "Stop"

function Start-HiddenProcess {
  param(
    [Parameter(Mandatory = $true)][string]$FilePath,
    [Parameter(Mandatory = $true)][string[]]$Arguments,
    [Parameter(Mandatory = $true)][string]$WorkingDirectory
  )

  Start-Process -FilePath $FilePath -ArgumentList $Arguments -WorkingDirectory $WorkingDirectory -WindowStyle Hidden
}

$projectRoot = Split-Path -Parent $PSScriptRoot
$backendRoot = Join-Path $projectRoot $BackendPath
$adminRoot = Join-Path $projectRoot $AdminPath

if (-not (Test-Path $backendRoot)) {
  throw "Backend folder not found: $backendRoot"
}

if (-not (Test-Path $adminRoot)) {
  throw "Admin dashboard folder not found: $adminRoot"
}

Write-Host "[1/4] Starting Laravel backend..."
Start-HiddenProcess -FilePath "php" -Arguments @("artisan", "serve", "--host=127.0.0.1", "--port=8000") -WorkingDirectory $backendRoot

Write-Host "[2/4] Starting React dashboard..."
Start-HiddenProcess -FilePath "npm.cmd" -Arguments @("run", "dev") -WorkingDirectory $adminRoot

if ($IncludeMobile) {
  Write-Host "[3/4] Starting Flutter app..."
  Start-HiddenProcess -FilePath "flutter" -Arguments @("run", "--dart-define=BASE_API=$FlutterBaseApi") -WorkingDirectory $projectRoot
}
else {
  Write-Host "[3/4] Flutter app skipped. Re-run with -IncludeMobile to launch it too."
}

Start-Sleep -Seconds 4

if ($OpenBrowser) {
  Write-Host "[4/4] Opening the dashboard in your browser..."
  Start-Process "http://127.0.0.1:3000"
}
else {
  Write-Host "[4/4] Launch complete. Open http://127.0.0.1:3000 for the React dashboard."
}

Write-Host ""
Write-Host "Backend:  http://127.0.0.1:8000"
Write-Host "Dashboard: http://127.0.0.1:3000"
if ($IncludeMobile) {
  Write-Host "Mobile:    flutter run is now starting in the background"
}
