ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip

ENV COMPOSER_HOME=/tmp/composer
RUN mkdir -p $COMPOSER_HOME && chmod 1777 $COMPOSER_HOME
WORKDIR /app
