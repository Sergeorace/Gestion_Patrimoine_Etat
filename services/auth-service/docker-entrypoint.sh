#!/bin/sh
set -e
cd /var/www

# Migrations idempotentes au demarrage (base dédiée au service)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force || echo "[entrypoint] migrate ignore/echoue"
  # Le seed (comptes de demo) ne tourne qu'en environnement local/developpement
  if [ "${APP_ENV:-local}" != "production" ]; then
    php artisan db:seed --force || echo "[entrypoint] seed ignore/echoue"
  fi
fi

exec "$@"
