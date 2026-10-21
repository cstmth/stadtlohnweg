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
# PHP2Shell (CVE-2026-109618/-109619) ist erst ab PHP 8.5.12 behoben, das
# offizielle FrankenPHP-Image liefert aber noch 8.5.11. Deshalb setzen wir das
# FrankenPHP-Binary (linkt libphp.so dynamisch, ABI innerhalb 8.5.x stabil) auf
# das gepatchte offizielle ZTS-Image. Sobald dunglas/frankenphp:1-php8.5-alpine
# PHP >= 8.5.12 enthält, kann diese Stage wieder einfach
# `FROM dunglas/frankenphp:1-php8.5-alpine` sein.
FROM dunglas/frankenphp:1.13.1-php8.5.11-alpine AS frankenphp

FROM php:8.5.12-zts-alpine3.24 AS runtime

RUN apk add --no-cache ca-certificates libcap mailcap libstdc++ brotli-libs

COPY --from=frankenphp /usr/local/lib/libwatcher* /usr/local/lib/
COPY --from=frankenphp /usr/local/bin/frankenphp /usr/local/bin/install-php-extensions /usr/local/bin/
COPY --from=frankenphp /etc/caddy /etc/caddy
COPY --from=frankenphp /etc/frankenphp /etc/frankenphp
RUN ldconfig /usr/local/lib \
    && setcap cap_net_bind_service=+ep /usr/local/bin/frankenphp \
    && mkdir -p /config/caddy /data/caddy \
    && frankenphp version

ENV GODEBUG=cgocheck=0 \
    XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data

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
