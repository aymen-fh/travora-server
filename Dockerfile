FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash git unzip icu-dev libpq-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql intl zip bcmath opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

CMD ["php-fpm"]
