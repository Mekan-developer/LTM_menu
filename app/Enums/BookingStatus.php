<?php

namespace App\Enums;

/** Статусы брони (ТЗ 2.2): новая → подтверждена → гость пришёл; или отменена / не пришёл. */
enum BookingStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** Бронь держит стол: с ней нельзя пересекаться. */
    public function holdsTable(): bool
    {
        return in_array($this, [self::New, self::Confirmed, self::Arrived], true);
    }

    /** @return list<string> */
    public static function holdingValues(): array
    {
        return [self::New->value, self::Confirmed->value, self::Arrived->value];
    }

    /**
     * Ещё ждём гостя — стол показывается «забронирован».
     *
     * @return list<string>
     */
    public static function waitingValues(): array
    {
        return [self::New->value, self::Confirmed->value];
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::New => [self::Confirmed, self::Arrived, self::Cancelled, self::NoShow],
            self::Confirmed => [self::Arrived, self::Cancelled, self::NoShow],
            default => [],
        };
    }
}
