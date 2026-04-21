# SkyblockHub Development Server Launcher

if ($env:OS -ne 'Windows_NT') {
    $bashScript = Join-Path $PSScriptRoot "start-dev.sh"

    if (-not (Get-Command bash -ErrorAction SilentlyContinue)) {
        Write-Host "ERROR: bash is not installed!" -ForegroundColor Red
        exit 1
    }

    if (-not (Test-Path $bashScript)) {
        Write-Host "ERROR: start-dev.sh not found!" -ForegroundColor Red
        exit 1
    }

    & bash $bashScript
    exit $LASTEXITCODE
}

Set-Location $PSScriptRoot

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "     SkyblockHub - Development Start" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check npm
if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
    Write-Host "ERROR: npm is not installed!" -ForegroundColor Red
    exit 1
}

# Check php
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "ERROR: PHP is not installed!" -ForegroundColor Red
    exit 1
}

Write-Host "OK: npm and PHP found" -ForegroundColor Green
Write-Host ""

# Install dependencies if needed
if (-not (Test-Path "node_modules")) {
    Write-Host "Running: npm install..." -ForegroundColor Yellow
    npm install
}

if (-not (Test-Path "vendor")) {
    Write-Host "Running: composer install..." -ForegroundColor Yellow
    composer install
}

Write-Host "OK: Dependencies OK" -ForegroundColor Green
Write-Host ""

# Setup .env
if (-not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Write-Host "OK: .env created (please configure it)" -ForegroundColor Green
    } else {
        Write-Host "ERROR: .env.example not found!" -ForegroundColor Red
        exit 1
    }
}

if (-not (Select-String -Path ".env" -Pattern '^APP_KEY=[^\s]+$' -Quiet)) {
    Write-Host "Running: php artisan key:generate..." -ForegroundColor Yellow
    php artisan key:generate --ansi
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ERROR: Failed to generate APP_KEY" -ForegroundColor Red
        exit 1
    }
}

# Setup database
if (-not (Test-Path "database")) {
    New-Item -ItemType Directory "database" | Out-Null
}
if (-not (Test-Path (Join-Path "database" "database.sqlite"))) {
    New-Item -ItemType File (Join-Path "database" "database.sqlite") | Out-Null
}

# Laravel setup
Write-Host "Running migrations..." -ForegroundColor Yellow
php artisan migrate --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Migrations failed" -ForegroundColor Red
    exit 1
}

Write-Host "Running initial bazaar sync..." -ForegroundColor Yellow
php scripts/run_fetch_hypixel_bazaar_job.php

Write-Host "Seeding crafting recipes..." -ForegroundColor Yellow
php artisan recipes:seed

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "     Starting services..." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Services to be opened:" -ForegroundColor Cyan
Write-Host "   - Laravel: http://localhost:8000" -ForegroundColor Cyan
Write-Host "   - Vite: http://localhost:5173" -ForegroundColor Cyan
Write-Host "   - WebSocket: ws://localhost:8080" -ForegroundColor Cyan
Write-Host ""

$jobs = @()

# Start Vite
Write-Host "Starting Vite dev server..."
$jobs += Start-Process -PassThru powershell -ArgumentList "-NoExit", "-Command", "npm run dev" -WindowStyle Normal

Start-Sleep -Seconds 3

# Start Laravel Reverb
Write-Host "Starting Laravel Reverb..."
$jobs += Start-Process -PassThru powershell -ArgumentList "-NoExit", "-Command", "php artisan reverb:start" -WindowStyle Normal

Start-Sleep -Seconds 2

# Start Laravel server
Write-Host "Starting Laravel dev server..."
$jobs += Start-Process -PassThru powershell -ArgumentList "-NoExit", "-Command", "php artisan serve" -WindowStyle Normal

Start-Sleep -Seconds 2

# Start Queue Worker
Write-Host "Starting Queue Worker..."
$jobs += Start-Process -PassThru powershell -ArgumentList "-NoExit", "-Command", "php artisan queue:work --sleep=3 --tries=3" -WindowStyle Normal

# Start Schedule Worker
Write-Host "Starting Schedule Worker..."
$jobs += Start-Process -PassThru powershell -ArgumentList "-NoExit", "-Command", "php artisan schedule:work" -WindowStyle Normal

Write-Host ""
Write-Host "OK: All services started!" -ForegroundColor Green
Write-Host ""
Write-Host "Close the opened windows to stop the development services." -ForegroundColor Yellow
