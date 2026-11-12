############################################
# Этап 1: сборка фронтенда
# Node 22 + PHP (Wayfinder вызывает artisan во время vp build)
############################################
FROM php:8.3-cli-bookworm AS frontend

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Этот этап временный — ему не нужны gd/pgsql и т.п., поэтому игнорируем ext-*
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction \
    --prefer-dist --ignore-platform-req=ext-*

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --no-dev --optimize --ignore-platform-req=ext-* \
 && npm run build


 


############################################
# Этап 2: продакшен PHP-FPM
############################################
FROM php:8.3-fpm-alpine AS app

# Runtime-библиотеки — остаются в образе
RUN apk add --no-cache libpq libpng icu-libs unzip

# Build-зависимости ставятся во временный пакет и удаляются в том же слое
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        postgresql-dev libpng-dev icu-dev \
 && docker-php-ext-install -j$(nproc) pdo_pgsql pgsql exif pcntl bcmath gd intl opcache \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps \
 && rm -rf /tmp/pear

# Продакшен-настройки PHP + OPcache
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.interned_strings_buffer=16'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/opcache-prod.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Сначала только зависимости — слой кешируется, пока не меняется composer.lock
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# Код проекта + готовый фронтенд из первого этапа
COPY . .
COPY --from=frontend /app/public/build ./public/build

# dump-autoload запустит package:discover из post-autoload-dump
RUN composer dump-autoload --no-dev --optimize \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
EXPOSE 9000
CMD ["php-fpm"]