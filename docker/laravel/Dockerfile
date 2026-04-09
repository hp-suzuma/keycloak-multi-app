FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev curl \
    && docker-php-ext-install pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

RUN composer create-project --prefer-dist --no-interaction laravel/laravel . '^11.0'

COPY docker/laravel/init-laravel-app.sh /usr/local/bin/init-laravel-app
COPY laravel-overlay /opt/laravel-overlay

RUN cp -a /opt/laravel-overlay/. /var/www/app/ \
    && chmod +x /usr/local/bin/init-laravel-app

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/init-laravel-app"]
