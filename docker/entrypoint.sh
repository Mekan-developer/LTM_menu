#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Миграции — только если этот контейнер единственный (не queue/reverb)
if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"