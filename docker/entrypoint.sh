#!/bin/sh
set -e

# APP_ENV из окружения контейнера, иначе из .env
APP_ENV="${APP_ENV:-$(grep -E '^APP_ENV=' .env 2>/dev/null | cut -d= -f2)}"

# Кеши только в продакшене. В dev код смонтирован с хоста, и закешированный
# конфиг с путями /var/www ломает artisan и VS Code-расширение Laravel на хосте
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# Миграции — только если этот контейнер единственный (не queue/reverb)
if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
