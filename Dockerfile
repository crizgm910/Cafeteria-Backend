FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath mbstring opcache pdo_pgsql \
    && apt-get purge -y --auto-remove libonig-dev libpq-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info

EXPOSE 10000

CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]

