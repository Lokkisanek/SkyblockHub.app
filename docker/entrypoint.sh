#!/bin/sh
set -e
cd /var/www/html
mkdir -p storage/app/skyhelper-networth \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
node scripts/ensure-skyhelper-items-backup-link.cjs || true
php artisan storage:link || true
php artisan migrate --force
php artisan config:cache
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
