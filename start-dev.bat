@echo off
chcp 65001 >nul
cls
echo.
echo ========================================
echo     SkyblockHub - Vývoj Spuštění
echo ========================================
echo.

:: Zkontrolovat, zda je projekt v adresáři
if not exist "package.json" (
    echo ❌ CHYBA: package.json nenalezen!
    echo Spusťte tento soubor v adresáři SkyblockHub.play
    pause
    exit /b 1
)

echo ✓ Projekt nalezen
echo.

:: Zkontrolovat, zda existují node_modules
if not exist "node_modules" (
    echo ⚙️  npm dependencies chybí, instaluji...
    call npm install
    if errorlevel 1 (
        echo ❌ npm install selhalo!
        pause
        exit /b 1
    )
)

echo ✓ npm dependencies jsou v pořádku
echo.

:: Zkontrolovat, zda existují PHP dependencies
if not exist "vendor" (
    echo ⚙️  Composer dependencies chybí, instaluji...
    call composer install
    if errorlevel 1 (
        echo ❌ Composer install selhalo!
        pause
        exit /b 1
    )
)

echo ✓ PHP dependencies jsou v pořádku
echo.

:: Zkontrolovat .env soubor
if not exist ".env" (
    echo ⚙️  .env soubor chybí, kopíruji z .env.example...
    if exist ".env.example" (
        copy ".env.example" ".env" >nul
        echo ✓ .env byl vytvořen, NEZAPOMEŇTE HO KONFIGUROVAT!
    ) else (
        echo ❌ .env.example nenalezen! Vytvořte .env ručně
        pause
        exit /b 1
    )
)

echo ✓ .env konfigurační soubor existuje
echo.

:: Ensure SQLite database exists
if not exist "database" (
    mkdir "database"
)
if not exist "database\database.sqlite" (
    type NUL > "database\database.sqlite"
)

:: Generate APP_KEY and run migrations
echo ⚙️  Generating APP_KEY and running migrations...
php artisan key:generate --ansi
php artisan migrate --force

:: Seed crafting recipes
echo ⚙️  Seeding recipes for Crafting page...
php artisan recipes:seed

:: Run initial fetches
echo ⚙️  Running initial fetches (bin, bazaar)...
start "Bin Fetch" cmd /c php artisan bin:fetch || echo bin:fetch failed
start "Bazaar Fetch" cmd /c php artisan bazaar:fetch || echo bazaar:fetch failed


echo ========================================
echo     Spouštění služeb...
echo ========================================
echo.
echo 📌 Informace:
echo    • Laravel server: http://localhost:8000
echo    • Vite (HMR): http://localhost:5173
echo    • Reverb WebSocket: ws://localhost:8080
echo.
echo Všechny služby se otevřou v nových oknech
echo Zavřete okna, když chcete zastavit vývoj
echo.

:: Spustit Vite dev server
echo 🚀 Spouštím Vite dev server...
start "Vite Dev Server" cmd /k npm run dev

:: Čekat chvíli, aby se Vite spustil
timeout /t 3 /nobreak >nul

:: Spustit Laravel Reverb
echo 🚀 Spouštím Arduino Reverb server...
start "Laravel Reverb" cmd /k php artisan reverb:start

:: Čekat chvíli, aby se Reverb spustil
timeout /t 2 /nobreak >nul

:: Spustit Laravel development server
echo 🚀 Spouštím Laravel dev server...
start "Laravel Dev Server" cmd /k php artisan serve

:: Spustit queue worker a schedule ve vlastních oknech
echo 🚀 Spouštím queue worker a schedule worker...
start "Queue Worker" cmd /k php artisan queue:work --sleep=3 --tries=3
start "Schedule Worker" cmd /k php artisan schedule:work

echo.
echo ✓ Všechny služby byly spuštěny!
echo.
echo Čekám na zavření tohoto okna...
pause
