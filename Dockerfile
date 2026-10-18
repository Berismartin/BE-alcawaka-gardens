# syntax=docker/dockerfile:1
#
# Alcawaka Gardens API — Laravel 13 / PHP 8.4. One image, two roles (see docker-compose.yml):
# web (Nginx + PHP-FPM on :8080, internal only) and the scheduler.
# Base: serversideup/php (production-tuned, non-root, healthcheck on /up, Laravel automations),
# the same base the Campus 360 API uses on this server.

############################
# 1. PHP dependencies (no dev packages)
############################
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

############################
# 2. Runtime
############################
FROM serversideup/php:8.4-fpm-nginx-bookworm

USER root

# pdo_pgsql, redis, opcache, pcntl, zip ship with the base image. gd for PDF receipts (dompdf).
RUN install-php-extensions gd intl bcmath exif

ENV PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up \
    LOG_CHANNEL=stderr \
    PHP_MEMORY_LIMIT=256M \
    # Photos up to 5 MB, ID documents up to 8 MB.
    PHP_UPLOAD_MAX_FILE_SIZE=10M \
    PHP_POST_MAX_SIZE=12M \
    NGINX_CLIENT_MAX_BODY_SIZE=12M

COPY --chown=www-data:www-data --from=vendor /app /var/www/html

RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
      storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
