# syntax=docker/dockerfile:1.7
# File: Builds a reproducible, non-root PHP application image with compiled frontend assets.

FROM composer:2.10 AS php-dependencies
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

FROM node:25-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.5-cli-alpine AS runtime
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql \
    && addgroup -S app \
    && adduser -S -G app app
WORKDIR /app
COPY --chown=app:app . .
COPY --from=php-dependencies --chown=app:app /build/vendor ./vendor
COPY --from=frontend --chown=app:app /build/public/build ./public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R app:app storage bootstrap/cache database
USER app
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8000/up || exit 1
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
