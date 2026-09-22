# ===========================================
# PARKFLOW BACKEND DOCKERFILE
# Multi-stage: development + production
# ===========================================

# --- Stage: Base PHP ---
FROM php:8.4-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libzip-dev libicu-dev libonig-dev \
    libsqlite3-dev libxml2-dev \
 && docker-php-ext-install pdo_pgsql pdo_sqlite bcmath intl zip mbstring pcntl opcache \
 && pecl install redis && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# --- Stage: Development ---
FROM base AS development

COPY . .
RUN composer install --prefer-dist --no-interaction \
 && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm"]

# --- Stage: Production ---
FROM base AS production

# Install Nginx
RUN apt-get update && apt-get install -y --no-install-recommends nginx \
 && rm -rf /var/lib/apt/lists/*

# PHP production config: enable opcache
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=10000"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.save_comments=1"; \
    echo "opcache.jit=1255"; \
    echo "opcache.jit_buffer_size=64M"; \
} > /usr/local/etc/php/conf.d/opcache-prod.ini

# PHP-FPM tuning for t3.micro (1GB RAM)
RUN { \
    echo "[www]"; \
    echo "pm = dynamic"; \
    echo "pm.max_children = 10"; \
    echo "pm.start_servers = 2"; \
    echo "pm.min_spare_servers = 1"; \
    echo "pm.max_spare_servers = 3"; \
    echo "pm.max_requests = 500"; \
} > /usr/local/etc/php-fpm.d/zzz-production.conf

# Nginx config: reverse proxy to PHP-FPM on port 8443
RUN { \
    echo 'server {'; \
    echo '    listen 8443;'; \
    echo '    server_name _;'; \
    echo '    root /var/www/html/public;'; \
    echo '    index index.php;'; \
    echo '    client_max_body_size 10m;'; \
    echo ''; \
    echo '    add_header X-Content-Type-Options nosniff always;'; \
    echo '    add_header X-Frame-Options DENY always;'; \
    echo '    add_header Referrer-Policy no-referrer always;'; \
    echo ''; \
    echo '    location / {'; \
    echo '        try_files $uri $uri/ /index.php?$query_string;'; \
    echo '    }'; \
    echo ''; \
    echo '    location ~ \.php$ {'; \
    echo '        fastcgi_split_path_info ^(.+\.php)(/.+)$;'; \
    echo '        fastcgi_pass 127.0.0.1:9000;'; \
    echo '        fastcgi_index index.php;'; \
    echo '        include fastcgi_params;'; \
    echo '        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;'; \
    echo '        fastcgi_param HTTP_PROXY "";'; \
    echo '    }'; \
    echo ''; \
    echo '    location ~ /\.(?!well-known) {'; \
    echo '        deny all;'; \
    echo '    }'; \
    echo '}'; \
} > /etc/nginx/sites-available/default

# Copy application
COPY . .

# Install production dependencies only
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
 && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Entrypoint script: start both Nginx and PHP-FPM
RUN { \
    echo '#!/bin/sh'; \
    echo 'set -e'; \
    echo '# Cache config/routes if APP_KEY is set'; \
    echo 'if [ -n "$APP_KEY" ]; then'; \
    echo '    php artisan config:cache 2>/dev/null || true'; \
    echo '    php artisan route:cache 2>/dev/null || true'; \
    echo '    php artisan view:cache 2>/dev/null || true'; \
    echo 'fi'; \
    echo '# Start PHP-FPM in background'; \
    echo 'php-fpm -D'; \
    echo '# Start Nginx in foreground'; \
    echo 'nginx -g "daemon off;"'; \
} > /usr/local/bin/start-server && chmod +x /usr/local/bin/start-server

EXPOSE 8443

CMD ["/usr/local/bin/start-server"]
