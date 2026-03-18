# SkyblockHub Development Server

# Check npm and php
if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
    "ERROR: npm not found" | Write-Host -ForegroundColor Red
    exit 1
}
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    "ERROR: PHP not found" | Write-Host -ForegroundColor Red
    exit 1
}

# Install dependencies
if (-not (Test-Path "node_modules")) {
    "Installing npm packages..." | Write-Host -ForegroundColor Yellow
    npm install
}
if (-not (Test-Path "vendor")) {
    "Installing composer packages..." | Write-Host -ForegroundColor Yellow
    composer install
}

# Setup files
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
}
if (-not (Test-Path "database")) {
    New-Item -ItemType Directory "database" | Out-Null
}
if (-not (Test-Path "database\database.sqlite")) {
    New-Item -ItemType File "database\database.sqlite" | Out-Null
}

# Setup Laravel
php artisan key:generate --ansi
php artisan migrate --force
php artisan recipes:seed

"Starting development services..." | Write-Host -ForegroundColor Cyan
"http://localhost:8000 (Laravel)" | Write-Host -ForegroundColor Cyan
"http://localhost:5173 (Vite)" | Write-Host -ForegroundColor Cyan
""

# Start services
Start-Process powershell -ArgumentList "-NoExit", "-Command", "npm run dev"
Start-Sleep -Seconds 3
Start-Process powershell -ArgumentList "-NoExit", "-Command", "php artisan reverb:start"
Start-Sleep -Seconds 2
Start-Process powershell -ArgumentList "-NoExit", "-Command", "php artisan serve"
Start-Sleep -Seconds 2
Start-Process powershell -ArgumentList "-NoExit", "-Command", "php artisan queue:work --sleep=3 --tries=3"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "php artisan schedule:work"

"Services started! Close windows to stop." | Write-Host -ForegroundColor Green
