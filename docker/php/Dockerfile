FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash git curl libpng-dev libzip-dev zip unzip \
    icu-dev oniguruma-dev freetype-dev libjpeg-turbo-dev \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_mysql mysqli gd zip intl mbstring bcmath opcache exif pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction || true

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

EXPOSE 9000
CMD ["php-fpm"]
