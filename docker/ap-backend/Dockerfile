FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev default-mysql-client curl bash \
    && docker-php-ext-install pdo_mysql pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

CMD ["tail", "-f", "/dev/null"]
