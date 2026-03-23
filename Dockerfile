# syntax=docker/dockerfile:1

# --- Build stage: install Composer dependencies ---
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-ansi \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

# --- Runtime stage: Apache + PHP ---
FROM php:8.2-apache

# Default for local runs; platforms like Heroku will set $PORT.
ENV PORT=8080

WORKDIR /var/www/html

# Copy app code and the vendor directory from build stage
COPY . ./
COPY --from=vendor /app/vendor ./vendor

# Rootless: listen on unprivileged port and make runtime dirs writable
RUN sed -ri 's/^\s*Listen\s+80\s*$/Listen ${PORT}/g' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g' /etc/apache2/sites-available/000-default.conf \
    && mkdir -p /var/run/apache2 /var/log/apache2 /var/lock/apache2 \
    && chown -R www-data:www-data /var/www/html /var/run/apache2 /var/log/apache2 /var/lock/apache2

USER www-data

EXPOSE 8080

CMD ["apache2-foreground"]
