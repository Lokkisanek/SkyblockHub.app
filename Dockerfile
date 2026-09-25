FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs

FROM node:20-bookworm AS assets
WORKDIR /app
COPY package.json package-lock.json ./
COPY scripts/ensure-skyhelper-items-backup-link.cjs scripts/ensure-skyhelper-items-backup-link.cjs
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ARG VITE_APP_NAME=SkyblockHub
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME \
    VITE_APP_NAME=$VITE_APP_NAME
RUN npm run build

FROM php:8.3-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor git unzip libpq-dev libzip-dev libicu-dev zlib1g-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY --from=node:20-bookworm /usr/local/bin/node /usr/local/bin/node
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=assets /app/bootstrap/ssr ./bootstrap/ssr
COPY --from=assets /app/node_modules ./node_modules
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && node scripts/ensure-skyhelper-items-backup-link.cjs \
    && chown -R www-data:www-data storage bootstrap/cache node_modules/skyhelper-networth

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/php.ini /usr/local/etc/php/conf.d/skyblockhub.ini
RUN chmod +x /entrypoint.sh \
    && ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

EXPOSE 80
CMD ["/entrypoint.sh"]
