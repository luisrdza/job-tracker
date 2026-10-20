FROM php:8.3-fpm-alpine

RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app