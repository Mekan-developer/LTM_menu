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
    ],

    'auth' => [
        'max_attempts' => 5,
        'lock_minutes' => 15,
    ],

    'photo' => [
        'max_side' => 1200,
        'webp_quality' => 78,
    ],

    // Подсказка для владельца при первом запуске (см. OwnerSeeder).
    'owner' => [
        'login' => env('OWNER_LOGIN', 'owner'),
        'password' => env('OWNER_PASSWORD', 'change-me-now'),
    ],
];
