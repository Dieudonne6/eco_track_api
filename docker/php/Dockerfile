FROM php:8.2-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    libonig-dev \
    zlib1g-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-interaction --optimize-autoloader

CMD ["php-fpm"]

# RUN chmod -R 775 storage bootstrap/cache

# EXPOSE 10000

# CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=10000