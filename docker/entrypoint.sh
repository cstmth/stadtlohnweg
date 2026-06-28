#!/bin/sh
set -e

# Cloud Run gibt den Port über $PORT vor (Standard 8080).
# FrankenPHP/Caddy lauscht über SERVER_NAME auf genau diesem Port.
export SERVER_NAME=":${PORT:-8080}"

# Laufzeit-Caches: müssen hier laufen, weil die Env-Variablen (APP_KEY,
# DB-Zugang, Secrets) erst zur Laufzeit von Cloud Run injiziert werden.
php artisan config:cache

# Datenbank-Datei für SQLite erstellen, falls sie noch nicht existiert.
# (Notwendig für Cloud Run Volume Mounts, falls das Volume leer ist)
DB_FILE=${DB_DATABASE:-/app/storage/database/database.sqlite}
if [ ! -f "$DB_FILE" ]; then
    mkdir -p "$(dirname "$DB_FILE")"
    touch "$DB_FILE"
    chmod 666 "$DB_FILE"
fi

# Datenbank-Migrationen beim Start (abschaltbar über RUN_MIGRATIONS=false,
# z. B. wenn Migrationen separat als Cloud Run Job laufen sollen).
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
