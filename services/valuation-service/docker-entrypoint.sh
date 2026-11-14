#!/bin/sh
set -e
cd /var/www

# Migrations idempotentes au demarrage (base dédiée au service)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force || echo "[entrypoint] migrate ignore/echoue"
  php artisan db:seed --force || echo "[entrypoint] seed ignore/echoue"
fi

exec "$@"
