FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install --yes --no-install-recommends git libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
