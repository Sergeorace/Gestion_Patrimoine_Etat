#!/bin/sh
# =============================================================================
# Entry point monolithe Patrimoine (SnapDeploy / PaaS single-container)
# 1. Cles JWT (generees si absentes)
# 2. PostgreSQL local (initdb + demarrage)
# 3. Redis local
# 4. Bases logiques + migrations + seed demo
# 5. Fichiers .env par service (URLs -> 127.0.0.1)
# 6. supervisord : nginx + 11 serveurs PHP artisan
# =============================================================================
set -e

APP_ROOT=/var/www/services
PATRIMOINE=/etc/patrimoine
PGDATA=/var/lib/pgsql/data
export PGPASSWORD=patrimoine

say() { echo "[monolithe] $*"; }

# Configs systeme depuis /monolith (image monolithe)
cp /monolith/nginx.conf /etc/nginx/http.d/default.conf
cp /monolith/proxy_params.inc /etc/nginx/proxy_params.inc
# php-fpm : remplacer les configs docker par defaut (pool www sans user = fatal)
rm -f /usr/local/etc/php-fpm.d/*.conf /usr/local/etc/php-fpm.d/*.default
printf '[global]\nerror_log = /proc/self/fd/2\nlog_limit = 8192\ndaemonize = no\n' > /usr/local/etc/php-fpm.d/00-globals.conf
cp /monolith/php-fpm-pools.conf /usr/local/etc/php-fpm.d/patrimoine.conf

# ---------------------------------------------------------------------------
# 1. Cles JWT RS256 (privee pour auth-service, publique pour tous)
# ---------------------------------------------------------------------------
mkdir -p "$PATRIMOINE"
if [ ! -f "$PATRIMOINE/jwt_private.pem" ]; then
    say "Generation des cles JWT…"
    openssl genrsa -out "$PATRIMOINE/jwt_private.pem" 2048 2>/dev/null
    openssl rsa -in "$PATRIMOINE/jwt_private.pem" -pubout -out "$PATRIMOINE/jwt_public.pem" 2>/dev/null
fi
chmod 644 "$PATRIMOINE"/*.pem

# ---------------------------------------------------------------------------
# 2. PostgreSQL
# ---------------------------------------------------------------------------
PGBIN=$(dirname "$(ls /usr/libexec/postgresql*/initdb 2>/dev/null | head -1)")
if [ ! -s "$PGDATA/PG_VERSION" ]; then
    say "Initialisation PostgreSQL…"
    mkdir -p "$PGDATA"
    chown postgres:postgres "$PGDATA"
    chmod 700 "$PGDATA"
    su postgres -c "$PGBIN/initdb -D $PGDATA -U patrimoine -A trust -E UTF8" >/dev/null
fi
if ! su postgres -c "$PGBIN/pg_ctl -D $PGDATA status" >/dev/null 2>&1; then
    say "Demarrage PostgreSQL…"
    su postgres -c "$PGBIN/pg_ctl -D $PGDATA -l $PGDATA/server.log -w -t 60 \
        -o '-c listen_addresses=127.0.0.1 -p 5432 -c max_connections=30 -c shared_buffers=32MB -c work_mem=2MB -c maintenance_work_mem=16MB -c unix_socket_directories=/tmp' start" >/dev/null
fi

# Bases logiques (idempotent)
say "Creation des bases…"
psql -h 127.0.0.1 -U patrimoine -d postgres -v ON_ERROR_STOP=0 -f /var/www/init.sql >/dev/null 2>&1 || true
for db in auth_db organisation_db asset_db movement_db inventory_db maintenance_db \
          valuation_db document_db notification_db reporting_db web_db; do
    psql -h 127.0.0.1 -U patrimoine -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='$db'" \
        | grep -q 1 || createdb -h 127.0.0.1 -U patrimoine "$db" 2>/dev/null || true
done

# ---------------------------------------------------------------------------
# 3. Redis
# ---------------------------------------------------------------------------
if ! redis-cli -h 127.0.0.1 ping >/dev/null 2>&1; then
    say "Demarrage Redis…"
    redis-server --daemonize yes --dir /tmp --save '' --appendonly no --maxmemory 48mb \
        --maxmemory-policy allkeys-lru >/dev/null 2>&1 || true
fi

# ---------------------------------------------------------------------------
# 4. Secrets partages + .env par service
# ---------------------------------------------------------------------------
SERVICE_TOKEN=$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')
APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"

# service|base|port|APP_NAME
SERVICES="
auth-service|auth_db|8001|Auth Service
organisation-service|organisation_db|8002|Organisation Service
asset-service|asset_db|8003|Asset Service
movement-service|movement_db|8004|Movement Service
inventory-service|inventory_db|8005|Inventory Service
maintenance-service|maintenance_db|8006|Maintenance Service
valuation-service|valuation_db|8007|Valuation Service
document-service|document_db|8008|Document Service
notification-service|notification_db|8009|Notification Service
reporting-service|reporting_db|8010|Reporting Service
"

write_service_env() {
    svc=$1; db=$2; appname=$3
    envf="$APP_ROOT/$svc/.env"
    {
        echo "APP_NAME=\"$appname\""
        echo "APP_ENV=local"
        echo "APP_KEY=$APP_KEY"
        echo "APP_DEBUG=false"
        echo "APP_URL=http://localhost"
        echo "LOG_CHANNEL=stderr"
        echo "LOG_LEVEL=warning"
        echo "DB_CONNECTION=pgsql"
        echo "DB_HOST=127.0.0.1"
        echo "DB_PORT=5432"
        echo "DB_DATABASE=$db"
        echo "DB_USERNAME=patrimoine"
        echo "DB_PASSWORD=patrimoine"
        echo "CACHE_STORE=redis"
        echo "SESSION_DRIVER=redis"
        echo "QUEUE_CONNECTION=database"
        echo "REDIS_HOST=127.0.0.1"
        echo "REDIS_PORT=6379"
        echo "REDIS_PASSWORD=null"
        echo "JWT_PUBLIC_KEY_FILE=$PATRIMOINE/jwt_public.pem"
        if [ "$svc" = "auth-service" ]; then echo "JWT_PRIVATE_KEY_FILE=$PATRIMOINE/jwt_private.pem"; fi
        echo "SERVICE_TOKEN=$SERVICE_TOKEN"
        echo "RABBITMQ_HOST=127.0.0.1"
        echo "RABBITMQ_PORT=5672"
        echo "RABBITMQ_USER=patrimoine"
        echo "RABBITMQ_PASSWORD=unused"
        echo "RABBITMQ_VHOST=/"
        echo "MAIL_MAILER=log"
        echo "MAIL_FROM_ADDRESS=patrimoine@etat.tg"
        echo "MAIL_FROM_NAME=\"Patrimoine Etat\""
        # URLs inter-services -> localhost
        echo "$SERVICES" | while IFS='|' read -r s2 db2 port2 name2; do
            [ -n "$s2" ] || continue
            var=$(echo "${s2%-service}_SERVICE_URL" | tr 'a-z-' 'A-Z_')
            echo "$var=http://127.0.0.1:$port2"
        done
    } > "$envf"
}

echo "$SERVICES" | while IFS='|' read -r svc db port appname; do
    if [ -n "$svc" ]; then write_service_env "$svc" "$db" "$appname"; fi
done

# .env frontend (sessions/fichiers, pas de JWT)
cat > "$APP_ROOT/frontend/.env" <<EOF
APP_NAME="Patrimoine Etat"
APP_ENV=local
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_URL=http://localhost
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=web_db
DB_USERNAME=patrimoine
DB_PASSWORD=patrimoine
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
VITE_APP_NAME="Patrimoine Etat"
EOF

# ---------------------------------------------------------------------------
# 5. Migrations + seed demo (idempotent)
# ---------------------------------------------------------------------------
echo "$SERVICES" | while IFS='|' read -r svc db port appname; do
    if [ -z "$svc" ]; then continue; fi
    say "Migration $svc…"
    (cd "$APP_ROOT/$svc" && php artisan migrate --force >/dev/null 2>&1) || say "  migrate $svc : ignore"
    (cd "$APP_ROOT/$svc" && php artisan db:seed --force >/dev/null 2>&1) || true
done
say "Seed demo (donnees metier)…"
psql -h 127.0.0.1 -U patrimoine -d postgres -v ON_ERROR_STOP=0 -f /var/www/seed_demo.sql >/dev/null 2>&1 || say "  seed demo : ignore"

# ---------------------------------------------------------------------------
# 6. Supervisord
# ---------------------------------------------------------------------------
say "Demarrage supervisord (nginx + services PHP)…"
exec /usr/bin/supervisord -c /monolith/supervisord.conf
