# ---- Étape 1 : dépendances Composer (sans dev) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---- Étape 2 : image d'exécution PHP-FPM + Nginx ----
FROM php:8.4-fpm-alpine AS runtime

# mbstring, dom, curl : déjà compilés dans l'image officielle
# gd (freetype/png/jpeg) : tampon de l'entreprise (StampService) et images des PDF (dompdf)
RUN apk add --no-cache \
        nginx \
        supervisor \
        libpq \
        libzip \
        icu-libs \
        freetype \
        libpng \
        libjpeg-turbo \
        libwebp \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        freetype-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        zip \
        bcmath \
        intl \
        gd \
        opcache \
    && apk del .build-deps \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www
COPY --from=vendor --chown=www-data:www-data /app /var/www

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up > /dev/null || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
