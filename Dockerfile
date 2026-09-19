FROM dunglas/frankenphp:php8.4-bookworm AS base

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        unzip \
        git \
        jpegoptim \
        optipng \
        pngquant \
        gifsicle \
        webp \
    && install-php-extensions \
        pdo_mysql \
        redis \
        pcntl \
        opcache \
        gd \
        exif \
        zip \
        intl \
        bcmath \
        imagick \
    && rm -rf /var/lib/apt/lists/*


FROM base AS composer

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts


FROM base AS production

COPY --from=composer /app/vendor ./vendor
COPY . .

RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

RUN php artisan package:discover --ansi || echo "package:discover skipped (dev providers missing)"

ENV APP_ENV=production
ENV APP_DEBUG=false

EXPOSE 80

CMD ["frankenphp", "php-server", "--root", "public", "--listen", ":80"]
