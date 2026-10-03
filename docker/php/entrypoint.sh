#!/bin/sh
set -eu

mkdir -p /var/www/html/storage/app/private/orders
chown -R www-data:www-data /var/www/html/storage/app/private

exec /usr/local/bin/docker-php-entrypoint "$@"
