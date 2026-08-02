FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libwebp-dev \
    libmagickwand-dev \
    libheif-dev \
    libavif-dev \
    zip \
    unzip \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip \
    xml \
    opcache \
    intl \
    && curl -sSL https://codeload.github.com/Imagick/imagick/tar.gz/refs/tags/3.8.0 | tar -xz -C /tmp \
    && cd /tmp/imagick-3.8.0 \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable imagick \
    && rm -rf /tmp/imagick-3.8.0 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sSL https://codeload.github.com/phpredis/phpredis/tar.gz/refs/tags/6.1.0 | tar -xz -C /tmp \
    && cd /tmp/phpredis-6.1.0 \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/phpredis-6.1.0

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist
COPY . .

RUN composer dump-autoload --optimize

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
