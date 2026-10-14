#!/bin/sh
# Container start for the production image (see Dockerfile and render.yaml).
set -e

cd /app

# Render's generateValue gives a bare base64 string; Laravel expects the
# "base64:" prefix for base64 keys.
if [ -n "$APP_KEY" ]; then
    case "$APP_KEY" in
        base64:*) ;;
        *) export APP_KEY="base64:$APP_KEY" ;;
    esac
else
    echo "APP_KEY is not set. Set it in the host's environment settings." >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Additive migrations only; review new ones before deploying (docs/DEPLOYMENT.md).
php artisan migrate --force

# First administrator from ADMIN_EMAIL (prints a one-time setup link until
# that admin signs in). Never blocks the start.
php artisan pal:bootstrap-admin || true

# Scheduled jobs (expiring requests and quotations, token pruning). Runs
# while the container is awake; free hosts that sleep simply catch up later.
php artisan schedule:work >/dev/null 2>&1 &

exec frankenphp php-server --listen ":${PORT:-8080}" --root /app/public
