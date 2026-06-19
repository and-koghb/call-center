#!/bin/sh
set -e

mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache/data \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ "$1" = 'php-fpm' ]; then
    exec docker-php-entrypoint php-fpm
fi

exec "$@"
