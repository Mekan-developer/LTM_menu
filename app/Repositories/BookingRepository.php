<?php

namespace App\Repositories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\DiningTable;
use App\Models\Guest;
use App\Models\Hall;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class BookingRepository
{
    /** @return Collection<int, Booking> */
    public function forDay(CarbonInterface $day): Collection
    {
        return Booking::query()
            ->with(['guest:id,name,phone,comment', 'table:id,number,hall_id,seats', 'items'])
            ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->orderBy('starts_at')->orderBy('id')
            ->get();
    }

    /** Пересекается ли интервал с другой бронью этого стола, которая держит стол. */
    public function overlaps(int $tableId, CarbonInterface $from, CarbonInterface $to, ?int $exceptId = null): bool
    {
        return Booking::query()
            ->where('dining_table_id', $tableId)
            ->whereIn('status', BookingStatus::holdingValues())
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    /**
     * Активные столы, свободные от броней на интервал (2 запроса).
     *
     * @return Collection<int, DiningTable>
     */
    public function freeTables(CarbonInterface $from, CarbonInterface $to, ?int $exceptId = null): Collection
    {
        $taken = Booking::query()
            ->whereNotNull('dining_table_id')
            ->whereIn('status', BookingStatus::holdingValues())
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->pluck('dining_table_id')->all();

        return DiningTable::query()->with('hall:id,name_tm,name_ru')
            ->where('is_active', true)
            ->whereNotIn('id', $taken)
            ->orderBy('hall_id')->orderBy('sort')->orderBy('id')
            ->get();
    }

    /**
     * Брони столов зала, из-за которых стол сейчас «забронирован»:
     * гость ещё не пришёл, до начала меньше $before минут и бронь не закончилась. Один запрос.
     *
     * @return BaseCollection<int, \stdClass> по dining_table_id — ближайшая бронь
     */
    public function reservedInHall(Hall $hall, CarbonInterface $now, int $before): BaseCollection
    {
        return DB::table('bookings')
            ->join('dining_tables', 'dining_tables.id', '=', 'bookings.dining_table_id')
            ->join('guests', 'guests.id', '=', 'bookings.guest_id')
            ->where('dining_tables.hall_id', $hall->id)
            ->whereIn('bookings.status', BookingStatus::waitingValues())
            ->where('bookings.starts_at', '<=', $now->copy()->addMinutes($before))
            ->where('bookings.ends_at', '>', $now)
            ->orderBy('bookings.starts_at')
            ->get(['bookings.id', 'bookings.dining_table_id', 'bookings.starts_at', 'bookings.guests_count', 'guests.name'])
            ->unique('dining_table_id')
            ->keyBy('dining_table_id');
    }

    public function countForDay(CarbonInterface $day): int
    {
        return Booking::query()
            ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->whereIn('status', BookingStatus::holdingValues())
            ->count();
    }

    /** @return Collection<int, Booking> */
    public function forGuest(Guest $guest, int $limit = 50): Collection
    {
        return $guest->bookings()->with('table:id,number')->latest('starts_at')->limit($limit)->get();
    }

    /**
     * Брони стола на сегодня, которых ещё ждём (для панели стола на схеме).
     *
     * @return Collection<int, Booking>
     */
    public function upcomingForTable(int $tableId, CarbonInterface $now): Collection
    {
        return Booking::query()->with('guest:id,name,phone')
            ->where('dining_table_id', $tableId)
            ->whereIn('status', BookingStatus::waitingValues())
            ->where('ends_at', '>', $now)
            ->where('starts_at', '<=', $now->copy()->endOfDay())
            ->orderBy('starts_at')
            ->get();
    }
}
