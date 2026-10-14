# Production image for the Laravel API (and a fallback copy of the web app).
# Used by render.yaml; works on any host that runs a container and sets PORT.

# --- Front-end assets (served by Laravel if the API host is opened directly)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- PHP application
FROM dunglas/frankenphp:1-php8.4 AS app

RUN install-php-extensions pdo_pgsql pdo_mysql gd exif intl zip bcmath opcache pcntl \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Upload limits: up to 8 photos of 5 MB each in one request.
RUN { \
      echo 'upload_max_filesize=6M'; \
      echo 'post_max_size=45M'; \
      echo 'memory_limit=256M'; \
      echo 'expose_php=Off'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/pal-market.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && rm -f public/hot \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/fonts storage/app/public \
    && chown -R www-data:www-data storage bootstrap/cache /data/caddy /config/caddy \
    && chmod +x docker/entrypoint.sh

USER www-data

ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]
