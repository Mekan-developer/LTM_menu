# Restoran CRM

CRM для ресторана (MVP) на **Laravel 13 + Inertia 3 + Vue 3** с Docker-окружением (Nginx, PHP-FPM, PostgreSQL, Redis, очередь, Laravel Reverb).

Что входит в MVP:

| Раздел                                                                                                        | Где                                              |
| ------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| Вход по логину/паролю и по PIN на общем планшете (блок на 15 мин после 5 ошибок, авто-выход при бездействии)  | `Auth/*`, `AuthService`                          |
| Роли и права: матрица галочек, свои роли, Владелец всегда со всеми правами                                    | `Roles/Index`, `StaffService`, enum `Permission` |
| Сотрудники: создание, PIN, блокировка                                                                         | `Staff/*`                                        |
| Меню TM/RU: категории, блюда, фото → WebP в очереди, цех кухня/бар                                            | `Menu/*`, `ConvertDishPhotoToWebp`               |
| Стоп-лист: до конца дня / до времени / вручную                                                                | `StopList/Index`, `StopListService`              |
| Столы списком + QR (PNG, печать всех), перевыпуск QR                                                          | `Tables/*`                                       |
| Страница гостя по QR: меню, корзина, проверка цен и стоп-листа на сервере, статус заказа                      | `Guest/*`, `GuestOrderService`                   |
| Экран официанта (ночная тема): мои столы, QR-заказ в одно нажатие, «На кухню», «Подано», счёт, передача стола | `Waiter/*`, `WaiterOrderService`                 |
| Экран кухни/бара на ТВ: карточки по возрасту заказа, «Готово»                                                 | `Kitchen/Index`, `KitchenService`                |
| Касса: скидка до K % / без лимита, нал / карта / нал+карта, итог дня, отмена оплаты с причиной, чек 80 мм     | `Cashier/*`, `CashierService`                    |
| Дашборд владельца: выручка, счета, средний чек, 7 дней, топ блюд                                              | `Dashboard`, `DashboardService`                  |
| Журнал действий                                                                                               | `Logs/Index`, `ActionLogger`                     |
| Языки TM (по умолчанию) / RU                                                                                  | `resources/js/i18n/*`, `lang/*`                  |

Отложено на следующий этап: бонусы и SMS, база гостей, брони, доставка, склад и калькуляция, смены и зарплата, схема зала, отчёты в Excel, push, офлайн-режим PWA.

---

## Содержание

- [Стек](#стек)
- [Архитектура](#архитектура)
- [Требования](#требования)
- [Быстрый старт (Docker)](#быстрый-старт-docker)
- [Локальный запуск без Docker](#локальный-запуск-без-docker)
- [Переменные окружения](#переменные-окружения)
- [Роли, вход и демо-данные](#роли-вход-и-демо-данные)
- [Реалтайм](#реалтайм)
- [Фронтенд](#фронтенд)
- [Качество кода и тесты](#качество-кода-и-тесты)
- [Продакшен](#продакшен)
- [Полезные команды](#полезные-команды)
- [Решение проблем](#решение-проблем)

---

## Стек

| Слой        | Технологии                                                                                  |
| ----------- | ------------------------------------------------------------------------------------------- |
| Backend     | PHP 8.3, Laravel 13, Inertia.js 3, Laravel Reverb, Ziggy                                    |
| Frontend    | Vue 3 (`<script setup>`, JavaScript), Tailwind CSS 4, laravel-echo + pusher-js, qrcode      |
| Шрифты      | Geologica, IBM Plex Mono, Sofia Sans Condensed, Spectral, Onest (fontsource, без интернета) |
| Сборка      | Vite+ (`vite-plus`)                                                                         |
| База данных | PostgreSQL 17 (в Docker), SQLite (в тестах)                                                 |
| Очереди     | `database` (worker — сервис `queue`)                                                        |
| Веб-сервер  | Nginx + PHP-FPM                                                                             |
| Инструменты | Pint, Larastan (уровень 7), Pest 4 (PHPUnit 12), vue-tsc, GitHub Actions                    |

---

## Архитектура

`Route → Controller → Service → Repository`, валидация в `FormRequest`, ответы через `JsonResource`. Контроллеры тонкие, бизнес-логика в `app/Services`, запросы к БД в `app/Repositories`.

**Деньги** хранятся в **тенге** целым числом (1 TMT = 100 тенге), без float. Формат вывода `1 234,50 TMT`: `App\Support\Money` и `resources/js/utils/format.js`.

Часовой пояс `Asia/Ashgabat`, язык по умолчанию `tk`, запасной `ru` (`config/app.php`). Значения настроек по умолчанию лежат в `config/restaurant.php`, действующие — в таблице `settings` (раздел «Настройки» в админке).

---

## Требования

**Для Docker:** Docker 24+ и Docker Compose v2.

**Для работы на хосте (сборка фронтенда, IDE, тесты):** PHP 8.3+ (`pdo_pgsql`, `pdo_sqlite`, `intl`, `gd` с WebP), Composer 2, Node.js 22+ и npm.

---

## Быстрый старт (Docker)

### 1. Файлы окружения

```bash
cp .env.example .env
```

Создайте `docker/.env.data` (в `.gitignore`) с `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` — они должны совпадать с `DB_*` в `.env`.

Для Docker в `.env` поменяйте:

```dotenv
APP_URL=http://localhost:8090

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=...      # как в docker/.env.data
DB_USERNAME=...
DB_PASSWORD=...

REVERB_HOST=reverb   # приложение публикует события в сервис reverb внутри сети compose
```

`VITE_REVERB_HOST=localhost` и `VITE_REVERB_PORT=8080` — адрес, по которому к Reverb подключается **браузер**. Это порт, проброшенный наружу из контейнера `reverb`.

### 2. Собрать и запустить контейнеры

```bash
docker compose up -d --build
```

| Сервис     | Что делает                                            | Порт на хосте (override)              |
| ---------- | ----------------------------------------------------- | ------------------------------------- |
| `nginx`    | Веб-сервер                                            | **http://localhost:8090**             |
| `app`      | PHP-FPM                                               | только внутри сети Docker, `app:9000` |
| `queue`    | `php artisan queue:work` (конвертация фото в WebP)    | —                                     |
| `reverb`   | `php artisan reverb:start --host=0.0.0.0 --port=8080` | `8080`                                |
| `postgres` | PostgreSQL 17                                         | `5435`                                |
| `redis`    | Redis 7                                               | `6379`                                |

Порты на хосте можно переопределить в `.env`, если они заняты другими проектами: `APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, `REVERB_FORWARD_PORT`. При смене `REVERB_FORWARD_PORT` поменяйте и `VITE_REVERB_PORT`, затем пересоберите фронтенд.

### 3. Зависимости и база

Корень проекта монтируется в контейнеры (`.:/var/www`), поэтому `vendor/` и `public/build/` берутся с хоста:

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
ln -s ../storage/app/public public/storage     # относительная ссылка работает и на хосте, и в контейнерах

npm install
npm run build        # или npm run dev для разработки с HMR
docker compose restart app queue reverb nginx   # nginx — чтобы он заново нашёл IP контейнера app (иначе 502)
```

### 4. Открыть приложение

- Админка: http://localhost:8090/login — `owner` / `change-me-now` (из `OWNER_LOGIN` / `OWNER_PASSWORD`)
- Общий планшет (PIN): http://localhost:8090/pin
- Гость: ссылка `/t/{qr_token}` из раздела «Столы и QR»
- Health-check: http://localhost:8090/up

### Важно: Docker-образ собран под продакшен

- `opcache.validate_timestamps=0`: PHP **не перечитывает** изменённые файлы;
- `docker/entrypoint.sh` при старте выполняет `config:cache`, `route:cache`, `view:cache`, `event:cache` и делает `chown www-data` для `storage` и `bootstrap/cache`.

После изменения PHP-кода, роутов или `.env` перезапустите контейнеры:

```bash
docker compose restart app queue reverb nginx   # nginx — чтобы он заново нашёл IP контейнера app (иначе 502)
```

---

## Локальный запуск без Docker

```bash
cp .env.example .env            # в .env.example по умолчанию SQLite
touch database/database.sqlite

composer setup   # composer install, key:generate, migrate, npm install, npm run build
php artisan db:seed
php artisan storage:link
composer dev     # сервер, очередь, Reverb, логи и Vite одной командой
```

Приложение откроется на http://localhost:8000.

---

## Переменные окружения

Полный список в [.env.example](.env.example).

| Переменная                                                                 | Назначение                                                                                            |
| -------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `APP_URL`                                                                  | Базовый URL. Должен совпадать с адресом в браузере: по нему строятся ссылки на фото (`/storage`) и QR |
| `APP_TIMEZONE`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`                        | `Asia/Ashgabat`, `tk`, `ru`                                                                           |
| `RESTAURANT_NAME`                                                          | Название ресторана по умолчанию (дальше меняется в «Настройках»)                                      |
| `OWNER_LOGIN`, `OWNER_PASSWORD`                                            | Владелец, которого создаёт `OwnerSeeder` при первом запуске                                           |
| `DB_*`                                                                     | Подключение к БД. В Docker хост — имя сервиса `postgres`                                              |
| `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE`                        | `database`                                                                                            |
| `FILESYSTEM_DISK`                                                          | `public` — фото блюд                                                                                  |
| `BROADCAST_CONNECTION`                                                     | `reverb`                                                                                              |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`                     | Ключи приложения Reverb                                                                               |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`                              | Куда приложение публикует события (в Docker: `reverb`, `8080`, `http`)                                |
| `VITE_REVERB_*`                                                            | Куда подключается браузер. Вшиваются в бандл при `npm run build`                                      |
| `APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, `REVERB_FORWARD_PORT` | Порты на хосте для `docker-compose.override.yml`                                                      |
| `RUN_MIGRATIONS`                                                           | Только для контейнера: `true` — выполнить `migrate --force` при старте                                |

---

## Роли, вход и демо-данные

Системные роли (`App\Enums\SystemRole`): Владелец, Администратор зала, Официант, Повар / бармен, Кассир. Права перечислены в `App\Enums\Permission` и проверяются только на сервере: middleware `permission:...` и `Gate::before`. У Владельца права есть всегда.

- `RoleSeeder` — системные роли и их права по умолчанию;
- `OwnerSeeder` — владелец из `OWNER_*`;
- `DemoSeeder` — демо-меню, 12 столов и сотрудники с PIN, **только при `APP_ENV=local`**:

| Сотрудник | Роль         | PIN  |
| --------- | ------------ | ---- |
| Nurly A.  | Официант     | 1111 |
| Gülşat M. | Официант     | 2222 |
| Aşpez     | Повар/бармен | 3333 |
| Aýgül K.  | Кассир       | 4444 |

Сброс пароля владельца:

```bash
docker compose exec app php artisan crm:owner-password owner
```

---

## Реалтайм

События `KitchenBoardChanged`, `WaiterAlert`, `CashierQueueChanged` уходят в Reverb по приватным каналам `kitchen.{station}`, `waiters`, `cashier` (`routes/channels.php`). Если Reverb не запущен, работа не ломается: ошибка пишется в лог, экраны обновляются опросом раз в 10–30 секунд (`composables/useRealtime.js`).

---

## Фронтенд

- Точка входа: `resources/js/app.ts`. Страницы — `resources/js/Pages/**/*.vue` (JavaScript, `<script setup>`), layout'ы — `Layouts/`, компоненты — `Components/`.
- Роуты во Vue: `route('name', params)` через Ziggy (`@routes` в `app.blade.php`).
- Переводы интерфейса: `resources/js/i18n/tk.js`, `ru.js`; серверные сообщения: `lang/tk`, `lang/ru`.
- Дизайн-система «Тамдыр» (цвета и шрифты) — в `resources/css/app.css`.
- Алиас `@/` указывает на `resources/js/`.

| Команда               | Описание                          |
| --------------------- | --------------------------------- |
| `npm run dev`         | Dev-сервер Vite с HMR             |
| `npm run build`       | Продакшен-сборка в `public/build` |
| `npm run check`       | Линтер и проверка форматирования  |
| `npm run check:fix`   | То же с автоисправлением          |
| `npm run types:check` | Проверка типов (`vue-tsc`)        |

---

## Качество кода и тесты

| Команда                | Описание                                                           |
| ---------------------- | ------------------------------------------------------------------ |
| `composer lint`        | Форматирование PHP (Pint, пресет `laravel`)                        |
| `composer lint:check`  | Проверка форматирования без изменений                              |
| `composer types:check` | Статический анализ Larastan (уровень 7)                            |
| `composer test`        | `config:clear` + `lint:check` + `types:check` + `php artisan test` |
| `composer ci:check`    | Всё сразу: `npm run check`, `npm run types:check`, `composer test` |

Тесты (Pest) работают на SQLite в памяти (`phpunit.xml`): QR-заказ и проверка цены, поток официант → кухня, касса и скидки, PIN-блокировка, права ролей.

> ⚠️ **Перед тестами сбросьте кэш конфига.** Entrypoint контейнера кэширует конфиг с PostgreSQL. Если запустить `php artisan test` с этим кэшем, `RefreshDatabase` очистит рабочую базу.

```bash
docker compose exec app sh -c 'php artisan config:clear && php artisan test; php artisan config:cache'
```

### CI

GitHub Actions ([.github/workflows/tests.yml](.github/workflows/tests.yml)) на каждый push в `main` и на каждый pull request выполняет `composer setup` и `composer ci:check` (PHP 8.3, Node 22).

---

## Продакшен

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

`docker-compose.prod.yml` открывает порты `80` / `443` для Nginx и `8080` для Reverb, включает `restart: always` для PostgreSQL, Redis, очереди и Reverb.

Как собирается образ (`docker/app/Dockerfile`):

1. **Этап `frontend`**: PHP 8.3 CLI + Node 22 ставят зависимости composer и npm и собирают фронтенд (Ziggy импортируется из `vendor/`).
2. **Этап `app`**: PHP 8.3-FPM Alpine с расширениями `pdo_pgsql`, `pgsql`, `redis`, `gd` (JPEG + WebP), `intl`, `bcmath`, `exif`, `pcntl`, `opcache`, `php.ini-production`, зависимости без dev и готовый `public/build`.

Чек-лист перед деплоем:

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, сгенерирован `APP_KEY`
- [ ] `APP_URL` — реальный домен с `https://`
- [ ] сменён пароль владельца (`OWNER_PASSWORD` или `crm:owner-password`), надёжный пароль в `docker/.env.data`
- [ ] свои `REVERB_APP_KEY` / `REVERB_APP_SECRET`; `VITE_REVERB_HOST` / `VITE_REVERB_PORT` / `VITE_REVERB_SCHEME=https` — публичный адрес Reverb
- [ ] `RUN_MIGRATIONS=true` задан только для одного контейнера (не для `queue` / `reverb`)
- [ ] в продакшене исходники **не** монтируются томом `.:/var/www`, иначе они перекрывают `vendor` и `public/build` из образа
- [ ] настроен HTTPS в Nginx (сейчас в `docker/nginx/conf.d/default.conf` только `listen 80`)

В продакшене `AppServiceProvider` запрещает опасные команды БД (`migrate:fresh`, `db:wipe` и т.п.).

---

## Полезные команды

```bash
# Контейнеры
docker compose up -d                          # запустить
docker compose down                           # остановить
docker compose logs -f app queue reverb       # логи
docker compose exec app sh                    # shell в PHP-контейнере

# Artisan в контейнере
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed   # пересоздать БД (только локально!)
docker compose exec app php artisan tinker
docker compose exec app php artisan optimize:clear

# PostgreSQL
docker compose exec postgres psql -U <POSTGRES_USER> -d <POSTGRES_DB>
# с хоста (DBeaver, DataGrip и т.п.): localhost:5435
```

---

## Решение проблем

**Изменения в PHP-коде не видны в Docker**
OPcache не проверяет изменения файлов, а конфиги закэшированы при старте. Выполните `docker compose restart app queue reverb nginx`.

**`Vite manifest not found` или страница без стилей**
Фронтенд не собран. Выполните `npm run build` или запустите `npm run dev`.

**`could not translate host name "postgres"` или `php artisan test` на хосте висит**
На хосте подхватился `bootstrap/cache/config.php`, созданный контейнером. Запускайте команды через `docker compose exec app ...`, а тесты — как описано в разделе [Качество кода и тесты](#качество-кода-и-тесты).

**`Permission denied` для `storage/` или `bootstrap/cache/`**

```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

**Экраны не обновляются мгновенно**
Проверьте `docker compose logs reverb`, что `VITE_REVERB_PORT` совпадает с проброшенным портом и что фронтенд пересобран после изменения `VITE_*`. Без Reverb экраны обновляются опросом.

**Фото блюда не появилось**
Конвертация в WebP идёт в очереди: проверьте `docker compose logs queue` и ссылку `public/storage`.

---

## Лицензия

MIT
