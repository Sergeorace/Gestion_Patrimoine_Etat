# =============================================================================
# Monolithe Patrimoine - image unique pour deploiement type SnapDeploy / PaaS.
# Contient : nginx + 10 micro-services Laravel + frontend Inertia + PostgreSQL
# + Redis, le tout supervise par supervisord. RabbitMQ est omis (evenements
# publies en best-effort, degradation silencieuse).
# =============================================================================
FROM php:8.3-cli-alpine

# Dependances systeme : postgres, redis, nginx, supervisor, node (build Vite)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        postgresql-dev linux-headers \
        icu-dev libzip-dev oniguruma-dev curl-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev \
    && (apk add --no-cache postgresql16 postgresql16-client \
        || apk add --no-cache postgresql17 postgresql17-client \
        || apk add --no-cache postgresql postgresql-client) \
    && apk add --no-cache redis nginx supervisor nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql bcmath intl zip mbstring exif pcntl gd sockets \
    && pecl install redis \
    && docker-php-ext-enable redis opcache \
    && apk del .build-deps $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# --- Code source des 10 micro-services + frontend -----------------------------
COPY services ./services
COPY frontend ./services/frontend

# --- Assets institutionnels du monolithe --------------------------------------
COPY docker/keys ./docker/keys
COPY docker/postgres/init.sql ./init.sql
COPY docker/monolith/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/monolith/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/monolith/proxy_params.inc /etc/nginx/proxy_params.inc
COPY docker/monolith/supervisord.conf /etc/supervisor/conf.d/monolith.conf
RUN chmod +x /usr/local/bin/entrypoint.sh

# --- Dependencies PHP de chaque service (cache par couche) --------------------
RUN set -e; for s in auth-service organisation-service asset-service movement-service \
        inventory-service maintenance-service valuation-service document-service \
        notification-service reporting-service frontend; do \
      cd /var/www/services/$s; \
      composer update --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader; \
      mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
               storage/framework/views storage/logs bootstrap/cache; \
    done

# --- Build des assets frontend (Vite) ------------------------------------------
RUN cd /var/www/services/frontend \
    && npm install --no-audit --no-fund \
    && npm run build

# --- Repertoires d'ecriture -----------------------------------------------------
RUN mkdir -p /var/lib/pgsql/data /run/nginx /var/log/supervisor /etc/patrimoine \
    && chown -R postgres:postgres /var/lib/pgsql \
    && chmod 700 /var/lib/pgsql/data \
    && cp docker/keys/jwt_public.pem /etc/patrimoine/jwt_public.pem 2>/dev/null || true

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
