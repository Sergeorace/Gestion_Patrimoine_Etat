# Microservice Laravel - image de production-like (dev)
FROM php:8.3-fpm-alpine

# Dependances systeme : pgsql, bcmath, intl, zip (excel/dompdf), gd (qrcode), sockets (amqp), redis
RUN apk add --no-cache \
        postgresql-dev linux-headers $PHPIZE_DEPS \
        icu-dev libzip-dev oniguruma-dev curl-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql bcmath intl zip mbstring exif pcntl gd sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Dependencies d'abord (cache Docker)
COPY composer.json composer.lock* ./
RUN composer update --no-dev --prefer-dist --no-interaction --no-scripts

# Dossiers requis par artisan (package:discover) avant le dump-autoload
RUN mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# Code applicatif
COPY . .
RUN composer dump-autoloader --optimize --no-dev 2>/dev/null || composer dump-autoload --optimize

# Permissions storage
RUN chown -R www-data:www-data storage bootstrap/cache storage/logs

# Point d'entree : migrations idempotentes puis PHP-FPM
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

USER www-data
EXPOSE 8000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
