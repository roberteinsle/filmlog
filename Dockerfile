# PHP-Version: immer der neueste stabile Branch (Stand 2026-10-25: 8.5 → 8.5.12)
# Tag auf die Minor-Version gepinnt, damit jeder Build den neuesten Patch zieht.
ARG PHP_VERSION=8.5

# --- Stufe 1: Composer (gleiche PHP-Version wie Runtime) ---
FROM serversideup/php:${PHP_VERSION}-cli-alpine AS vendor
WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

# --- Stufe 2: Frontend-Assets (Vite + Tailwind, scannt auch vendor/-Views) ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build

# --- Basis: Runtime mit App-spezifischen Erweiterungen ---
FROM serversideup/php:${PHP_VERSION}-fpm-nginx-alpine AS base
ENV HEALTHCHECK_PATH=/up
USER root
RUN install-php-extensions intl
USER www-data
WORKDIR /var/www/html

# --- Lokale Entwicklung: Code per Bind-Mount, UID wie auf dem Host ---
FROM base AS development
ARG USER_ID=1000
ARG GROUP_ID=1000
USER root
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} \
    && docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID} --service fpm-nginx
USER www-data

# --- Produktion (Standard-Target) ---
FROM base AS production
ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    PHP_OPCACHE_ENABLE=1
COPY --chown=www-data:www-data --from=vendor /var/www/html /var/www/html
COPY --chown=www-data:www-data --from=assets /app/public/build /var/www/html/public/build
