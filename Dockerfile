# syntax=docker/dockerfile:1

# PHP 8.3 + php-fpm on Alpine (nhẹ). Dùng cho cả dev (bị bind mount đè) lẫn prod.
FROM php:8.3-fpm-alpine

# ---- System libs + PHP extension dependencies ----
# Cài lib runtime, rồi cài build-deps tạm để build extension và xoá đi cho image nhẹ.
RUN set -eux; \
    apk add --no-cache \
        bash git curl unzip \
        libpng libjpeg-turbo freetype libzip icu-libs oniguruma; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev icu-dev oniguruma-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql bcmath gd zip intl mbstring exif pcntl opcache; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apk del .build-deps

# ---- Composer (copy từ image chính thức, khỏi cài thủ công) ----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---- PHP config tùy chỉnh ----
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# ---- Application code ----
# Ở DEV: thư mục này bị volume mount (./:/var/www/html) đè, nên COPY chỉ có ý nghĩa cho image PROD.
COPY . /var/www/html

# Chỉ chạy composer install nếu ĐÃ có Laravel (composer.json) trong build context.
# Lần build đầu tiên (chưa tạo Laravel) sẽ bỏ qua bước này => image vẫn build được.
RUN if [ -f composer.json ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist; \
    fi

# Laravel cần ghi được vào storage/ và bootstrap/cache/.
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
