# Development image for the Laravel API (docs/developer/docker-development.md).
# App source is bind-mounted at runtime, not baked in here -- this image
# only provides the PHP runtime, its extensions, and Composer. `vendor/`
# lives in a named volume (see docker-compose.yml), never bind-mounted
# from the Windows host, to avoid the well-known severe I/O penalty of
# running composer/npm against an NTFS bind mount through Docker
# Desktop's WSL2 layer.
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        # jcupitt/vips (spatie/image's Vips driver) talks to libvips via
        # FFI, not a compiled PHP extension -- it needs the shared
        # library itself present at runtime.
        libvips42 \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libffi-dev \
        pkg-config \
        default-mysql-client \
        git \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip intl bcmath gd exif ffi opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && echo "ffi.enable=1" > /usr/local/etc/php/conf.d/ffi.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
