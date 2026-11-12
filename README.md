# Laravel 13 Docker Starter

Готовый стартовый шаблон для новых проектов на **Laravel 13 + Inertia + Vue 3** с Docker-окружением (Nginx, PHP-FPM, PostgreSQL, Redis).

Из коробки уже настроены:

- вход по e-mail и паролю, подтверждение e-mail, двухфакторная аутентификация, passkeys (Laravel Fortify);
- роли пользователей и сидер супер-админа;
- страницы настроек: профиль, безопасность, тема оформления (светлая / тёмная / системная);
- UI-компоненты shadcn-vue и Tailwind CSS 4;
- типизированные роуты для фронтенда (Laravel Wayfinder);
- multi-stage Dockerfile для продакшена;
- линтеры, статический анализ, тесты и GitHub Actions CI.

Клонируйте шаблон, переименуйте проект и сразу пишите бизнес-логику.

---

## Содержание

- [Стек](#стек)
- [Требования](#требования)
- [Новый проект из шаблона](#новый-проект-из-шаблона)
- [Быстрый старт (Docker)](#быстрый-старт-docker)
- [Локальный запуск без Docker](#локальный-запуск-без-docker)
- [Переменные окружения](#переменные-окружения)
- [Структура проекта](#структура-проекта)
- [Аутентификация и роли](#аутентификация-и-роли)
- [Фронтенд](#фронтенд)
- [Качество кода и тесты](#качество-кода-и-тесты)
- [Продакшен](#продакшен)
- [Полезные команды](#полезные-команды)
- [Решение проблем](#решение-проблем)

---

## Стек

| Слой | Технологии |
|---|---|
| Backend | PHP 8.3, Laravel 13, Laravel Fortify, Inertia.js 3 |
| Frontend | Vue 3 + TypeScript, Tailwind CSS 4, shadcn-vue (reka-ui), lucide-иконки |
| Сборка | Vite+ (`vite-plus`), Laravel Wayfinder |
| База данных | PostgreSQL 17 (в Docker), SQLite (локально и в тестах) |
| Кэш / очереди | Redis 7 (phpredis) |
| Веб-сервер | Nginx + PHP-FPM |
| Инструменты | Pint, Larastan (PHPStan, уровень 7), PHPUnit 12, vue-tsc, GitHub Actions |

---

## Требования

**Для Docker:**
- Docker 24+ и Docker Compose v2

**Для работы на хосте (сборка фронтенда, IDE, тесты):**
- PHP 8.3+ с расширениями `pdo_pgsql`, `redis`, `intl`, `gd`, `bcmath`
- Composer 2
- Node.js 22 и npm

---

## Новый проект из шаблона

### 1. Скопировать шаблон

Через кнопку **Use this template** на GitHub или вручную:

```bash
git clone <url-этого-репозитория> my-project
cd my-project
rm -rf .git && git init
```

### 2. Переименовать проект

Замените имя шаблона на имя своего проекта:

| Файл | Что поменять |
|---|---|
| `docker-compose.yml` | `container_name` (`tmshop_app`, `tmshop_nginx`, `tmshop_postgres`, `tmshop_redis`) и имена томов (`tmshop-pgdata`, `tmshop-redisdata`) |
| `composer.json` | поля `name` и `description` |
| `.env` | `APP_NAME`, `DB_DATABASE`, `DB_USERNAME` |
| `docker/.env.data` | `POSTGRES_DB`, `POSTGRES_USER` |

Быстрая замена во всех Docker-файлах:

```bash
sed -i 's/tmshop/myproject/g' docker-compose*.yml
```

### 3. Выбрать нужные функции (необязательно)

Если вам не нужны 2FA или passkeys, их можно убрать командой (Laravel Chisel). Она удалит лишние страницы, компоненты и npm-пакеты:

```bash
php artisan install:features
```

---

## Быстрый старт (Docker)

### 1. Создать файлы окружения

**`.env` приложения:**

```bash
cp .env.example .env
```

В `.env.example` по умолчанию стоит SQLite. Для Docker замените эти переменные:

```dotenv
APP_URL=http://localhost:8090

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
```

**`docker/.env.data`** хранит учётные данные PostgreSQL. Файл в `.gitignore`, создайте его из примера. Значения должны совпадать с `DB_*` из `.env`:

```bash
cp docker/.env.data.example docker/.env.data
```

```dotenv
POSTGRES_DB=app
POSTGRES_USER=app
POSTGRES_PASSWORD=secret
```

### 2. Собрать и запустить контейнеры

```bash
docker compose up -d --build
```

`docker-compose.override.yml` подключается автоматически и открывает порты для разработки:

| Сервис | Порт на хосте |
|---|---|
| Nginx | **http://localhost:8090** |
| PostgreSQL | `5435` |
| Redis | `6379` |
| PHP-FPM (`app`) | только внутри сети Docker, `app:9000` |

### 3. Установить зависимости и подготовить приложение

Корень проекта монтируется в контейнер (`.:/var/www`), поэтому `vendor/` и `public/build/` берутся с хоста, а не из образа. Их нужно установить один раз:

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

npm install
npm run build        # или npm run dev для разработки с HMR
```

### 4. Открыть приложение

- Сайт: http://localhost:8090
- Вход: http://localhost:8090/login, данные супер-админа из `SUPER_ADMIN_*` (см. [Аутентификация и роли](#аутентификация-и-роли))
- Health-check: http://localhost:8090/up

### Важно: Docker-образ собран под продакшен

- `opcache.validate_timestamps=0`: PHP **не перечитывает** изменённые файлы;
- `docker/entrypoint.sh` при старте выполняет `config:cache`, `route:cache`, `view:cache`, `event:cache`.

Поэтому после изменения PHP-кода, роутов или `.env` перезапустите контейнер:

```bash
docker compose restart app
```

или сбросьте кэш:

```bash
docker compose exec app php artisan optimize:clear
```

---

## Локальный запуск без Docker

Самый быстрый вариант, на SQLite, без PostgreSQL и Redis:

```bash
cp .env.example .env
touch database/database.sqlite

composer setup   # composer install, key:generate, migrate, npm install, npm run build
php artisan db:seed
composer dev     # сервер, очередь, логи и Vite одной командой
```

Приложение откроется на http://localhost:8000.

---

## Переменные окружения

Основные переменные (полный список в [.env.example](.env.example)):

| Переменная | Назначение |
|---|---|
| `APP_URL` | Базовый URL. **Должен совпадать с адресом в браузере**, иначе не работают passkeys. Для Docker: `http://localhost:8090` |
| `DB_*` | Подключение к БД. В Docker хост — имя сервиса `postgres` |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | Драйверы кэша, очередей и сессий (`redis` / `database`) |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | Подключение к Redis. В Docker хост — `redis` |
| `MAIL_*` | Почта. По умолчанию `log`: письма пишутся в `storage/logs/laravel.log` |
| `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD` | Данные супер-админа для `SuperAdminSeeder` |
| `PASSKEYS_USER_HANDLE_SECRET` | Необязательно. Секрет для passkeys, по умолчанию берётся `APP_KEY` |
| `RUN_MIGRATIONS` | Только для контейнера: `true` — выполнить `migrate --force` при старте |

`docker/.env.data` содержит `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` для контейнера PostgreSQL.

---

## Структура проекта

```
app/
├── Concerns/              # Общие правила валидации (пароль, профиль)
├── Console/Commands/      # install:features — выбор функций шаблона
├── Enums/UserRole.php     # Роли: super_admin, user
├── Http/
│   ├── Controllers/Settings/   # Профиль и безопасность
│   ├── Middleware/             # Inertia, тема оформления
│   └── Requests/Settings/      # Form Request'ы
├── Models/User.php
└── Providers/             # AppServiceProvider, FortifyServiceProvider
config/                    # Конфигурация (fortify.php; auth.php — блок super_admin)
database/
├── migrations/
└── seeders/               # DatabaseSeeder → SuperAdminSeeder
docker/
├── app/Dockerfile         # Multi-stage: сборка фронтенда → PHP 8.3-FPM (Alpine)
├── entrypoint.sh          # Кэширование конфигов, опциональные миграции
├── nginx/conf.d/default.conf
└── .env.data              # Данные PostgreSQL (не в git)
resources/
├── css/app.css            # Tailwind 4
├── js/
│   ├── pages/             # Inertia-страницы (auth/, settings/, Dashboard, Welcome)
│   ├── layouts/           # Layout'ы приложения, авторизации, настроек
│   ├── components/        # Компоненты приложения
│   ├── components/ui/     # Компоненты shadcn-vue
│   ├── composables/
│   ├── actions/, routes/, wayfinder/   # Генерирует Wayfinder (в .gitignore)
│   └── app.ts
└── views/app.blade.php    # Корневой шаблон Inertia
routes/
├── web.php                # /, /dashboard
├── settings.php           # /settings/*
└── console.php
tests/                     # PHPUnit: Feature и Unit
```

---

## Аутентификация и роли

Аутентификация работает на **Laravel Fortify**, страницы сделаны на Inertia + Vue.

Включено в `config/fortify.php`:

- вход по e-mail и паролю (не больше 5 попыток в минуту на e-mail + IP);
- подтверждение e-mail: без него закрыты `/dashboard` и раздел безопасности;
- двухфакторная аутентификация (TOTP + коды восстановления);
- passkeys (WebAuthn);
- подтверждение пароля перед важными действиями.

**Регистрации и сброса пароля нет.** Первого пользователя (супер-админа) создаёт сидер. Если нужна регистрация, добавьте `Features::registration()` и `Features::resetPasswords()` в `config/fortify.php` и сделайте для них страницы.

### Роли

Роль хранится в колонке `users.role`, значения описаны в `App\Enums\UserRole`:

| Значение | Описание |
|---|---|
| `super_admin` | Супер-администратор |
| `user` | Обычный пользователь (по умолчанию) |

Проверка в коде: `$user->isSuperAdmin()`. Новые роли добавляйте в enum `UserRole`.

### Супер-админ

`SuperAdminSeeder` создаёт или обновляет аккаунт с e-mail из `SUPER_ADMIN_EMAIL`. Его можно запускать сколько угодно раз, дубликатов не будет:

```bash
php artisan db:seed --class=SuperAdminSeeder
```

> ⚠️ Значения по умолчанию (`admin@gmail.com` / `password`) только для локальной разработки. В продакшене обязательно задайте свои `SUPER_ADMIN_*`.

### Маршруты

| URL | Доступ | Описание |
|---|---|---|
| `/` | все | Главная (Welcome) |
| `/login` | гости | Вход |
| `/dashboard` | вход + подтверждённый e-mail | Панель |
| `/settings/profile` | вход | Профиль (удалить аккаунт можно только с подтверждённым e-mail) |
| `/settings/security` | вход + подтверждённый e-mail + пароль | Пароль, 2FA, passkeys |
| `/settings/appearance` | вход + подтверждённый e-mail | Тема оформления |
| `/up` | все | Health-check |

Полный список: `php artisan route:list`.

---

## Фронтенд

- Точка входа: `resources/js/app.ts`. Страницы лежат в `resources/js/pages`, рендерятся через `Inertia::render('folder/Page')` или `Route::inertia(...)`.
- Алиас `@/` указывает на `resources/js/`.
- UI-компоненты: [shadcn-vue](https://www.shadcn-vue.com/), стиль `new-york`, настройки в `components.json`. Новые компоненты добавляются через CLI shadcn-vue в `resources/js/components/ui`.
- **Wayfinder** при `npm run dev` / `npm run build` создаёт типизированные функции для роутов и контроллеров в `resources/js/routes` и `resources/js/actions`. Используйте их вместо URL, написанных вручную:

  ```ts
  import { dashboard } from '@/routes';
  import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

  dashboard().url;               // '/dashboard'
  ProfileController.update();    // { url: '/settings/profile', method: 'patch' }
  ```

  Эти папки в `.gitignore`. После добавления новых роутов перезапустите Vite.

- Wayfinder вызывает `php artisan` во время сборки, поэтому для `npm run dev` / `npm run build` на хосте нужны PHP и установленный `vendor/`.

### Скрипты npm

| Команда | Описание |
|---|---|
| `npm run dev` | Dev-сервер Vite с HMR |
| `npm run build` | Продакшен-сборка в `public/build` |
| `npm run build:ssr` | Сборка + SSR-бандл |
| `npm run check` | Линтер и проверка форматирования |
| `npm run check:fix` | То же с автоисправлением |
| `npm run types:check` | Проверка типов (`vue-tsc`) |

---

## Качество кода и тесты

| Команда | Описание |
|---|---|
| `composer lint` | Форматирование PHP (Pint, пресет `laravel`) |
| `composer lint:check` | Проверка форматирования без изменений |
| `composer types:check` | Статический анализ Larastan (уровень 7) |
| `composer test` | `lint:check` + `types:check` + `php artisan test` |
| `composer ci:check` | Всё сразу: `npm run check`, `npm run types:check`, `composer test` |

Тесты работают на SQLite в памяти (`phpunit.xml`), PostgreSQL и Redis для них не нужны:

```bash
php artisan test
php artisan test --filter=SuperAdminSeederTest
```

Перед пушем запустите:

```bash
composer ci:check
```

### CI

GitHub Actions ([.github/workflows/tests.yml](.github/workflows/tests.yml)) на каждый push в `main` и на каждый pull request выполняет `composer setup` и `composer ci:check` (PHP 8.3, Node 22). Dependabot раз в неделю обновляет версии actions.

---

## Продакшен

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

`docker-compose.prod.yml` открывает порты `80` / `443` для Nginx и включает `restart: always` для PostgreSQL и Redis. Порты БД и Redis наружу не открываются.

Как собирается образ (`docker/app/Dockerfile`):

1. **Этап `frontend`**: PHP 8.3 CLI + Node 22 ставят зависимости composer и npm и собирают фронтенд.
2. **Этап `app`**: PHP 8.3-FPM Alpine с расширениями `pdo_pgsql`, `pgsql`, `redis`, `gd`, `intl`, `bcmath`, `exif`, `pcntl`, `opcache`, `php.ini-production`, зависимости без dev и готовый `public/build` из первого этапа.

При старте `entrypoint.sh` кэширует конфиги, роуты, вью и события, при `RUN_MIGRATIONS=true` выполняет `php artisan migrate --force` и выставляет права на `storage` и `bootstrap/cache`.

Чек-лист перед деплоем:

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, сгенерирован `APP_KEY`
- [ ] `APP_URL` — реальный домен с `https://` (нужно для passkeys)
- [ ] свои `SUPER_ADMIN_*` и надёжный пароль в `docker/.env.data`
- [ ] настроена почта (`MAIL_*`), иначе не придут письма подтверждения e-mail
- [ ] `RUN_MIGRATIONS=true` задан только для одного контейнера
- [ ] в продакшене исходники **не** монтируются томом `.:/var/www`, иначе они перекрывают `vendor` и `public/build` из образа
- [ ] настроен HTTPS в Nginx (сейчас в `docker/nginx/conf.d/default.conf` только `listen 80`)

В продакшене `AppServiceProvider` запрещает опасные команды БД (`migrate:fresh`, `db:wipe` и т.п.) и требует сложные пароли: от 12 символов, буквы разного регистра, цифры, символы и проверка по базе утечек.

---

## Полезные команды

```bash
# Контейнеры
docker compose up -d                     # запустить
docker compose down                      # остановить
docker compose logs -f app nginx         # логи
docker compose exec app sh               # shell в PHP-контейнере

# Artisan в контейнере
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed   # пересоздать БД (только локально!)
docker compose exec app php artisan tinker
docker compose exec app php artisan optimize:clear

# PostgreSQL
docker compose exec postgres psql -U app -d app
# с хоста (DBeaver, DataGrip и т.п.): localhost:5435

# Логи приложения в реальном времени
php artisan pail
```

---

## Решение проблем

**Изменения в PHP-коде не видны в Docker**
OPcache не проверяет изменения файлов, а конфиги закэшированы при старте. Выполните `docker compose restart app` или `docker compose exec app php artisan optimize:clear`.

**`Vite manifest not found` или страница без стилей**
Фронтенд не собран. Выполните `npm run build` или запустите `npm run dev`.

**`could not translate host name "postgres"`**
Команда запущена на хосте, а не в контейнере. Выполните её через `docker compose exec app ...` или для хоста укажите `DB_HOST=127.0.0.1` и `DB_PORT=5435`.

**`Permission denied` для `storage/` или `bootstrap/cache/`**
```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

**Passkeys не работают**
`APP_URL` должен точно совпадать с адресом в браузере (схема, хост, порт). WebAuthn работает только на `localhost` или по HTTPS.

**Не приходит письмо подтверждения e-mail**
При `MAIL_MAILER=log` письма пишутся в `storage/logs/laravel.log`, ссылку для подтверждения можно взять оттуда.

---

## Лицензия

MIT
