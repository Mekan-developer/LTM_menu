<?php

namespace App\Support;

/** Деньги храним в тенге (1 TMT = 100) целым числом. Формат вывода: «1 234,50 TMT». */
final class Money
{
    /** "1 234,50" | "1234.5" | 85 → 123450 | 8500 */
    public static function toMinor(string|int|float|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value * 100;
        }

        $normalized = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $value);

        return (int) round(((float) $normalized) * 100);
    }

    public static function format(int $minor, bool $withCurrency = true): string
    {
        $formatted = number_format($minor / 100, 2, ',', ' ');

        return $withCurrency ? $formatted.' TMT' : $formatted;
    }

    /** Процент от суммы с округлением до тенге. */
    public static function percentOf(int $minor, int $percent): int
    {
        return intdiv($minor * $percent + 50, 100);
    }
}
