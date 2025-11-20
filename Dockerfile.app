# ---------- Stage 1 : Composer build ----------
FROM php:8.4-fpm AS composerbuild
WORKDIR /app

# Install system dependencies for PHP extensions
RUN apt-get update && apt-get install -y \
    git zip unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libicu-dev curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip intl gd opcache

# Install Composer (from the official Composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy all application files (including everything Composer may want!)
COPY . .

# Install Composer dependencies (with necessary PHP extensions present)
# --no-scripts disables "artisan package:discover" and other post-install scripts
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress

# Optionally optimize Composer autoloader, also with --no-scripts
RUN composer dump-autoload --optimize --no-scripts

# ---------- Stage 2 : PHP‑FPM Runtime ----------
FROM php:8.4-fpm AS php
# System deps for required PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libicu-dev curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip intl gd opcache

WORKDIR /var/www

# Copy full built app from previous stage
COPY --from=composerbuild /app /var/www
COPY .deploy/php.ini /usr/local/etc/php/php.ini

RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm","-F"]

# ---------- Stage 3 : Nginx web server ----------
FROM nginx:stable-alpine AS nginx
WORKDIR /var/www

# Copy just the public assets for serving
COPY --from=php /var/www/public /var/www/public
COPY .deploy/nginx/laravel.conf /etc/nginx/conf.d/default.conf
CMD ["nginx","-g","daemon off;"]