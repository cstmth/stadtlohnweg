# syntax=docker/dockerfile:1

# ---------- Stage 1: PHP-Abhängigkeiten ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# ---------- Stage 2: Frontend-Assets (Vite) ----------
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Flux liefert CSS unter vendor/livewire/flux/dist/flux.css, das Tailwind importiert.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---------- Stage 3: Runtime (FrankenPHP) ----------
FROM dunglas/frankenphp:1-php8.5-alpine AS runtime

WORKDIR /app

# PHP-Erweiterungen – pdo_pgsql für Postgres (Neon), Rest für Laravel.
RUN install-php-extensions \
    pdo_sqlite \
    ffi \
    intl \
    zip \
    opcache \
    pcntl

# Produktions-OPcache-Konfiguration.
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# App-Code.
COPY . .

# Optimierte Vendor-Dateien + gebaute Assets aus den Build-Stages.
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Schreibrechte für Laravel (Cache, Logs, Sessions, kompilierte Views).
RUN mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# stdout/stderr landet im Cloud Logging.
ENV LOG_CHANNEL=stderr

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Cloud Run leitet Traffic an den Container-Port (Standard 8080).
EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
