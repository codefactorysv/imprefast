FROM php:8.3-fpm-alpine
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev sqlite-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql pdo_sqlite intl zip bcmath opcache \
    && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/php/entrypoint.sh /usr/local/bin/imprefast-entrypoint
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
    && chown -R www-data:www-data storage bootstrap/cache \
    && sed -i 's/\r$//' /usr/local/bin/imprefast-entrypoint \
    && chmod 755 /usr/local/bin/imprefast-entrypoint
ENTRYPOINT ["imprefast-entrypoint"]
CMD ["php-fpm"]
