FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev default-mysql-client curl bash \
    && docker-php-ext-install pdo_mysql pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

COPY docker/laravel/init-laravel-app.sh /usr/local/bin/init-laravel-app
COPY docker/laravel/init-laravel-fpm-app.sh /usr/local/bin/init-laravel-fpm-app

RUN chmod +x /usr/local/bin/init-laravel-app /usr/local/bin/init-laravel-fpm-app

EXPOSE 8000
EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/init-laravel-app"]
