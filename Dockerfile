FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libicu-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql pgsql bcmath intl mbstring zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/php-fpm/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/start.sh /usr/local/bin/start-app
RUN chmod +x /usr/local/bin/start-app

CMD ["start-app"]
