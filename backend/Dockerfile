FROM php:8.5-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libicu-dev libonig-dev libsqlite3-dev libxml2-dev \
 && docker-php-ext-install pdo_pgsql pdo_sqlite bcmath intl zip mbstring pcntl \
 && pecl install redis && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
 && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
