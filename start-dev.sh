#!/bin/bash

# SkyblockHub - macOS/Linux Development Startup Script

cd "$(dirname "$0")" || exit 1

echo ""
echo "========================================"
echo "     SkyblockHub - Development Start"
echo "========================================"
echo ""

# Check if project exists
if [ ! -f "package.json" ]; then
    echo "❌ ERROR: package.json not found!"
    echo "Run this script in the SkyblockHub.play directory"
    exit 1
fi

echo "✓ Project found"
echo ""

# Check and install npm dependencies
if [ ! -d "node_modules" ]; then
    echo "⚙️  npm dependencies missing, installing..."
    npm install
    if [ $? -ne 0 ]; then
        echo "❌ npm install failed!"
        exit 1
    fi
fi

echo "✓ npm dependencies ready"
echo ""

# Check and install PHP dependencies
if [ ! -d "vendor" ]; then
    echo "⚙️  Composer dependencies missing, installing..."
    composer install
    if [ $? -ne 0 ]; then
        echo "❌ Composer install failed!"
        exit 1
    fi
fi

echo "✓ PHP dependencies ready"
echo ""

# Check .env file
if [ ! -f ".env" ]; then
    echo "⚙️  .env file missing, copying from .env.example..."
    if [ -f ".env.example" ]; then
        cp ".env.example" ".env"
        echo "✓ .env created, REMEMBER TO CONFIGURE IT!"
    else
        echo "❌ .env.example not found! Create .env manually"
        exit 1
    fi
fi

echo "✓ .env configuration file exists"
echo ""

# Only generate an application key when one is missing.
if ! grep -Eq '^APP_KEY=[^[:space:]]+' ".env"; then
    echo "⚙️  APP_KEY missing, generating one..."
    php artisan key:generate --ansi
    if [ $? -ne 0 ]; then
        echo "❌ APP_KEY generation failed!"
        exit 1
    fi
else
    echo "✓ APP_KEY already configured"
fi

echo ""

echo "========================================"
echo "     Starting services..."
echo "========================================"
echo ""
echo "📌 Information:"
echo "   • Laravel server: http://localhost:8000"
echo "   • Vite (HMR): http://localhost:5173"
echo "   • Reverb WebSocket: ws://localhost:8080"
echo ""
echo "Press Ctrl+C to stop all services"
echo ""

# Ensure SQLite database file exists (if using sqlite)
mkdir -p database
if [ ! -f "database/database.sqlite" ]; then
    echo "⚙️  Creating SQLite database file..."
    touch database/database.sqlite
    chmod 664 database/database.sqlite || true
fi

# Run migrations before any data seeding/fetching.
echo "⚙️  Running migrations..."
php artisan migrate --force
if [ $? -ne 0 ]; then
    echo "❌ Migrations failed!"
    exit 1
fi

# Run the bazaar job that populates bazaar_products before seeding recipes.
echo "⚙️  Running initial bazaar sync..."
php scripts/run_fetch_hypixel_bazaar_job.php || echo "bazaar sync failed"

# Seed crafting recipes after bazaar products exist.
echo "⚙️  Seeding recipes for Crafting page..."
php artisan recipes:seed || echo "recipes:seed failed"

# Function to kill all background processes on exit
cleanup() {
    echo ""
    echo "Stopping services..."
    kill $VITE_PID $REVERB_PID $LARAVEL_PID $QUEUE_PID $SCHEDULE_PID 2>/dev/null
    wait 2>/dev/null
    echo "Services stopped"
    exit 0
}

trap cleanup SIGINT SIGTERM

# Start Vite dev server in background
echo "🚀 Starting Vite dev server..."
npm run dev &
VITE_PID=$!

# Wait for Vite to start
sleep 3

# Start Laravel Reverb in background
echo "🚀 Starting Laravel Reverb server..."
php artisan reverb:start &
REVERB_PID=$!

# Wait for Reverb to start
sleep 2

# Start Laravel development server in background
echo "🚀 Starting Laravel dev server..."
php artisan serve --host=127.0.0.1 --port=8000 &
LARAVEL_PID=$!

# Start queue worker in background
echo "🚀 Starting queue worker..."
php artisan queue:work --sleep=3 --tries=3 &
QUEUE_PID=$!

# Start schedule worker in background
echo "🚀 Starting schedule worker..."
php artisan schedule:work &
SCHEDULE_PID=$!

echo ""
echo "✓ All services started!"
echo "✓ Open http://localhost:8000 in your browser"
echo ""

# Wait for all background processes
wait
