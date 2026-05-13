#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/skyblockhub}"
WEB_USER="${WEB_USER:-www-data}"
SUPERVISORCTL="${SUPERVISORCTL:-supervisorctl}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
NGINX_SERVICE="${NGINX_SERVICE:-nginx}"

log(){ echo \"[$(date +'%Y-%m-%d %H:%M:%S')] $*\"; }

usage(){
  echo \"Usage: $0 {start|stop|restart|status|deploy}\"
  exit 2
}

stop_services(){
  log \"Stopping supervisor-managed processes...\"
  $SUPERVISORCTL stop all || true
  log \"Stopping $PHP_FPM_SERVICE...\"
  systemctl stop $PHP_FPM_SERVICE || true
  log \"Stopping $NGINX_SERVICE...\"
  systemctl stop $NGINX_SERVICE || true
}

start_services(){
  log \"Starting $NGINX_SERVICE...\"
  systemctl start $NGINX_SERVICE
  log \"Starting $PHP_FPM_SERVICE...\"
  systemctl start $PHP_FPM_SERVICE
  log \"Reloading supervisor config and starting processes...\"
  $SUPERVISORCTL reread || true
  $SUPERVISORCTL update || true
  $SUPERVISORCTL start all || true
  log \"Fixing ownership for $APP_DIR...\"
  chown -R $WEB_USER:$WEB_USER \"$APP_DIR\" || true
}

status_services(){
  log \"Service statuses:\"
  systemctl is-active $NGINX_SERVICE || echo \"$NGINX_SERVICE not active\"
  systemctl is-active $PHP_FPM_SERVICE || echo \"$PHP_FPM_SERVICE not active\"
  $SUPERVISORCTL status || true
}

deploy_assets(){
  log \"Deploy: building frontend (if present) and caching Laravel\"
  if [ -d \"$APP_DIR\" ]; then
    cd \"$APP_DIR\"
    rm -f public/hot
    if [ -f package.json ]; then
      npm ci --no-audit --no-fund
      npm run build
    else
      log \"No package.json, skipping npm build\"
    fi
    if command -v php >/dev/null 2>&1 && [ -f artisan ]; then
      php artisan cache:clear || true
      php artisan config:cache || true
      php artisan route:cache || true
      php artisan view:cache || true
    fi
    chown -R $WEB_USER:$WEB_USER \"$APP_DIR\" || true
  else
    log \"App directory $APP_DIR not found, skipping deploy steps\"
  fi
}

case \"${1:-}\" in
  stop)
    stop_services
    status_services
    ;;
  start)
    start_services
    status_services
    ;;
  restart)
    stop_services
    sleep 2
    start_services
    status_services
    ;;
  status)
    status_services
    ;;
  deploy)
    stop_services
    deploy_assets
    start_services
    status_services
    ;;
  *)
    usage
    ;;
esac