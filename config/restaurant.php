<?php

// Значения по умолчанию для настроек. Действующие значения хранятся в таблице settings
// и меняются в админке пользователем с правом «Настройки».
return [
    'defaults' => [
        'restaurant_name' => env('RESTAURANT_NAME', 'Restoran'),
        'discount_limit_k' => 10,          // разовая скидка до K % — право «Скидка до K %»
        'qr_confirmation' => true,         // QR-заказ ждёт подтверждения официанта
        'kitchen_warn_minutes' => 10,      // карточка на кухне становится охристой
        'kitchen_late_minutes' => 20,      // карточка становится красной
        'shared_idle_seconds' => 60,       // выход с общего планшета при бездействии
        'reviews_enabled' => true,         // ссылка на отзыв после оплаты (ТЗ 2.9)
        'booking_minutes' => 120,          // бронь занимает стол на 2 часа (ТЗ 2.2)
        'booking_reserve_before' => 60,    // за час до брони стол на схеме «забронирован»
        'online_booking' => true,          // гость может бронировать на странице /book
    ],

    'auth' => [
        'max_attempts' => 5,
        'lock_minutes' => 15,
    ],

    'photo' => [
        'max_side' => 1200,
        'webp_quality' => 78,
    ],

    // Резервные копии БД (ТЗ 3.1): раз в сутки, хранятся 14 последних.
    // На продакшене BACKUP_PATH — отдельный диск (см. docker-compose.prod.yml).
    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'keep' => (int) env('BACKUP_KEEP', 14),
        'at' => env('BACKUP_AT', '03:00'),
        'connection' => env('BACKUP_CONNECTION'), // пусто — основное подключение
        'stale_hours' => 26, // копия старше — предупреждение в настройках
    ],

    // Подсказка для владельца при первом запуске (см. OwnerSeeder).
    'owner' => [
        'login' => env('OWNER_LOGIN', 'owner'),
        'password' => env('OWNER_PASSWORD', 'change-me-now'),
    ],
];
