<?php

namespace App\Support;

/**
 * Телефон Туркменистана: +993 и 8 цифр (ТЗ 2.1). Храним как «+99361234567».
 * Принимаем «+993 61 23-45-67», «8 61 234567», «861234567», «61234567».
 */
final class Phone
{
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        $national = match (true) {
            strlen($digits) === 11 && str_starts_with($digits, '993') => substr($digits, 3),
            strlen($digits) === 9 && str_starts_with($digits, '8') => substr($digits, 1),
            strlen($digits) === 8 => $digits,
            default => null,
        };

        return $national !== null ? '+993'.$national : null;
    }

    /** «+99361234567» → «+993 61 234567». */
    public static function format(?string $phone): string
    {
        if ($phone === null || ! preg_match('/^\+993(\d{2})(\d{6})$/', $phone, $m)) {
            return (string) $phone;
        }

        return "+993 {$m[1]} {$m[2]}";
    }
}
