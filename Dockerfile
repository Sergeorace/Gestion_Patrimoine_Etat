FROM php:8.3-cli-alpine
RUN apk add --no-cache --virtual .b $PHPIZE_DEPS postgresql-dev linux-headers icu-dev libzip-dev oniguruma-dev curl-dev freetype-dev libjpeg-turbo-dev libpng-dev \
 && (apk add --no-cache postgresql16 postgresql16-client || apk add --no-cache postgresql17 postgresql17-client || apk add --no-cache postgresql postgresql-client) \
 && apk add --no-cache redis nginx supervisor nodejs npm \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_pgsql bcmath intl zip mbstring exif pcntl gd sockets \
 && pecl install redis && docker-php-ext-enable redis opcache \
 && apk del .b $PHPIZE_DEPS linux-headers
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY services ./services
COPY frontend ./services/frontend
COPY docker ./docker
COPY docker/postgres/init.sql ./init.sql
COPY docker/monolith/ /monolith/
RUN set -e; cd /var/www/services; \
 S="auth-service organisation-service asset-service movement-service inventory-service maintenance-service valuation-service document-service notification-service reporting-service"; \
 (cd auth-service && composer update --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader); \
 for s in $S; do mkdir -p $s/storage/app/public $s/storage/framework/cache/data $s/storage/framework/sessions $s/storage/framework/views $s/storage/logs $s/bootstrap/cache; done; \
 for s in organisation-service asset-service movement-service inventory-service maintenance-service valuation-service document-service notification-service reporting-service; do cp -a auth-service/vendor $s/vendor && (cd $s && composer dump-autoload --no-dev -q); done; \
 (cd frontend && composer update --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache); \
 cd frontend && npm install --no-audit --no-fund && npm run build; \
 for s in $S frontend; do [ -d $s/vendor ] || exit 1; done
RUN mkdir -p /var/lib/pgsql/data /run/nginx /var/log/supervisor && chown -R postgres:postgres /var/lib/pgsql && chmod 700 /var/lib/pgsql/data
EXPOSE 80
ENTRYPOINT ["sh","/monolith/entrypoint.sh"]
