<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Events\BookingsChanged;
use App\Events\HallChanged;
use App\Events\KitchenBoardChanged;
use App\Events\WaiterAlert;
use App\Models\Booking;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\MenuRepository;
use App\Repositories\OrderRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Брони (ТЗ 2.2). Бронь занимает стол на booking_minutes (2 часа); пересечения на одном
 * столе проверяются под блокировкой строки стола. Предзаказ уходит на кухню, когда
 * администратор отметит «гость пришёл».
 */
class BookingService
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly MenuRepository $menu,
        private readonly OrderRepository $orders,
        private readonly OrderCalculator $calculator,
        private readonly GuestService $guests,
        private readonly SettingsService $settings,
        private readonly ActionLogger $logger,
        private readonly Realtime $realtime,
    ) {}

    /**
     * Поля: date, time, guests_count, name, phone, comment?, dining_table_id?, confirm?, items?[{dish_id, quantity}].
     *
     * @param  array<string, mixed>  $data
     * @param  string  $source  staff — администратор по звонку, guest — гость на странице
     */
    public function create(array $data, string $source, ?User $user = null): Booking
    {
        [$from, $to] = $this->interval($data['date'], $data['time']);
        $this->assertNotInPast($from, $source);
        $lines = $this->preorderLines($data['items'] ?? []);
        $guest = $this->guests->findOrCreateByPhone($data['phone'], $data['name'], 'booking', $user);
        $tableId = isset($data['dining_table_id']) ? (int) $data['dining_table_id'] : null;
        $confirm = $source === 'staff' && ! empty($data['confirm']);

        $booking = DB::transaction(function () use ($data, $from, $to, $lines, $guest, $tableId, $confirm, $source, $user) {
            if ($tableId !== null) {
                $this->lockTableAndCheck($tableId, $from, $to, null);
            }

            $booking = Booking::create([
                'guest_id' => $guest->id,
                'dining_table_id' => $tableId,
                'starts_at' => $from,
                'ends_at' => $to,
                'guests_count' => (int) $data['guests_count'],
                'status' => $confirm ? BookingStatus::Confirmed->value : BookingStatus::New->value,
                'source' => $source,
                'comment' => $data['comment'] ?? null,
                'created_by' => $user?->id,
                'confirmed_at' => $confirm ? now() : null,
            ]);
            $booking->items()->createMany($lines);

            return $booking;
        });

        $this->notify($booking);

        return $booking;
    }

    /**
     * Изменить бронь, пока гость не пришёл: время, гостей, стол, комментарий, предзаказ.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Booking $booking, array $data, User $user): Booking
    {
        if (! in_array($booking->status->value, BookingStatus::waitingValues(), true)) {
            throw ValidationException::withMessages(['status' => __('crm.bookings.not_editable')]);
        }

        [$from, $to] = $this->interval($data['date'], $data['time']);
        $lines = $this->preorderLines($data['items'] ?? []);
        $guest = $this->guests->findOrCreateByPhone($data['phone'], $data['name'], 'booking', $user);
        $tableId = isset($data['dining_table_id']) ? (int) $data['dining_table_id'] : null;
        $oldHall = $booking->table?->hall_id;

        DB::transaction(function () use ($booking, $data, $from, $to, $lines, $guest, $tableId) {
            if ($tableId !== null) {
                $this->lockTableAndCheck($tableId, $from, $to, $booking->id);
            }

            $booking->update([
                'guest_id' => $guest->id,
                'dining_table_id' => $tableId,
                'starts_at' => $from,
                'ends_at' => $to,
                'guests_count' => (int) $data['guests_count'],
                'comment' => $data['comment'] ?? null,
            ]);
            $booking->items()->delete();
            $booking->items()->createMany($lines);
        });

        if ($oldHall !== null) {
            $this->realtime->send(new HallChanged($oldHall));
        }
        $this->notify($booking->unsetRelation('table'));

        return $booking;
    }

    /** Смена статуса по ТЗ: новая → подтверждена → пришёл; отменена / не пришёл. */
    public function setStatus(Booking $booking, BookingStatus $status, User $user, ?string $reason = null): void
    {
        if (! in_array($status, $booking->status->next(), true)) {
            throw ValidationException::withMessages(['status' => __('crm.bookings.bad_transition')]);
        }

        if ($status === BookingStatus::Arrived) {
            $this->arrive($booking, $user);

            return;
        }

        $booking->update([
            'status' => $status->value,
            'confirmed_at' => $status === BookingStatus::Confirmed ? now() : $booking->confirmed_at,
            'cancel_reason' => $status === BookingStatus::Cancelled ? $reason : $booking->cancel_reason,
        ]);

        if ($status === BookingStatus::Cancelled) {
            $this->logger->log(ActionLogger::BOOKING_CANCELLED, $booking, [
                'guest' => $booking->guest?->name, 'at' => $booking->starts_at->format('d.m.Y H:i'), 'reason' => $reason,
            ], $user);
        }

        $this->notify($booking);
    }

    /**
     * «Гость пришёл»: на столе брони открывается заказ гостя, предзаказ сразу уходит на кухню.
     * Заказ без официанта — его возьмёт любой официант зала.
     */
    public function arrive(Booking $booking, User $user): Order
    {
        if ($booking->dining_table_id === null) {
            throw ValidationException::withMessages(['dining_table_id' => __('crm.bookings.no_table')]);
        }

        $booking->loadMissing('items');
        /** @var list<string> $stations */
        $stations = array_values($booking->items->map(fn ($i) => $i->station->value)->unique()->all());

        $order = DB::transaction(function () use ($booking) {
            $table = DiningTable::query()->lockForUpdate()->findOrFail($booking->dining_table_id);

            if ($this->orders->activeForTableLocked($table) !== null) {
                throw ValidationException::withMessages(['dining_table_id' => __('crm.order.table_busy')]);
            }

            $hasItems = $booking->items->isNotEmpty();
            $order = Order::create([
                'public_token' => Str::lower(Str::random(32)),
                'dining_table_id' => $table->id,
                'guest_id' => $booking->guest_id,
                'waiter_id' => null,
                'source' => OrderSource::Booking->value,
                'status' => OrderStatus::Open->value,
                'guest_comment' => $booking->comment,
                'opened_at' => now(),
            ]);

            if ($hasItems) {
                $order->forceFill(['batch_counter' => 1])->save();
                foreach ($booking->items as $item) {
                    $order->items()->create([
                        'dish_id' => $item->dish_id,
                        'name_tm' => $item->name_tm,
                        'name_ru' => $item->name_ru,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                        'station' => $item->station->value,
                        'status' => OrderItemStatus::Sent->value,
                        'batch' => 1,
                        'sent_at' => now(),
                    ]);
                }
                $this->calculator->recalculate($order);
            }

            $booking->update([
                'status' => BookingStatus::Arrived->value,
                'arrived_at' => now(),
                'order_id' => $order->id,
            ]);

            return $order;
        });

        if ($stations !== []) {
            $this->realtime->send(new KitchenBoardChanged($stations));
        }
        $this->realtime->send(new WaiterAlert('booking', $order->id, $booking->table?->number, null, $stations !== [] ? 1 : null));
        $this->notify($booking);

        return $order;
    }

    /**
     * Свободные от броней столы на дату и время (для выбора стола в форме).
     *
     * @return list<array<string, mixed>>
     */
    public function freeTables(string $date, string $time, ?int $exceptId = null): array
    {
        [$from, $to] = $this->interval($date, $time);

        return array_values($this->bookings->freeTables($from, $to, $exceptId)->map(fn (DiningTable $t) => [
            'id' => $t->id,
            'number' => $t->number,
            'seats' => $t->seats,
            'hall' => $t->hall?->localizedName(),
        ])->all());
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function interval(string $date, string $time): array
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $time", (string) config('app.timezone'));

        if ($from === null) {
            throw ValidationException::withMessages(['time' => __('validation.date')]);
        }

        $from = $from->setSecond(0);

        return [$from, $from->addMinutes((int) $this->settings->get('booking_minutes'))];
    }

    /** Гость бронирует только на будущее; администратор — допускаем 15 минут назад (запись задним числом). */
    private function assertNotInPast(CarbonImmutable $from, string $source): void
    {
        $limit = $source === 'guest' ? now() : now()->subMinutes(15);

        if ($from->lessThan($limit)) {
            throw ValidationException::withMessages(['time' => __('crm.bookings.in_past')]);
        }
    }

    private function lockTableAndCheck(int $tableId, CarbonImmutable $from, CarbonImmutable $to, ?int $exceptId): void
    {
        $table = DiningTable::query()->lockForUpdate()->find($tableId);

        if ($table === null || ! $table->is_active) {
            throw ValidationException::withMessages(['dining_table_id' => __('crm.order.table_unavailable')]);
        }

        if ($this->bookings->overlaps($tableId, $from, $to, $exceptId)) {
            throw ValidationException::withMessages(['dining_table_id' => __('crm.bookings.table_taken', ['n' => $table->number])]);
        }
    }

    /**
     * Предзаказ — снимок блюд и цен на момент брони. Скрытое блюдо не принимаем; стоп-лист
     * действует на сегодня, а бронь бывает на другой день, поэтому его здесь не проверяем.
     *
     * @param  list<array{dish_id:int, quantity:int}>  $items
     * @return list<array<string, mixed>>
     */
    private function preorderLines(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $dishes = $this->menu->activeDishesByIds(array_map(fn ($i) => (int) $i['dish_id'], $items));
        $lines = [];

        foreach ($items as $item) {
            $dish = $dishes->get((int) $item['dish_id']);

            if ($dish === null) {
                throw ValidationException::withMessages(['items' => __('crm.order.dish_unavailable')]);
            }

            $lines[] = [
                'dish_id' => $dish->id,
                'name_tm' => $dish->name_tm,
                'name_ru' => $dish->name_ru,
                'price' => $dish->price,
                'quantity' => (int) $item['quantity'],
                'station' => $dish->station->value,
            ];
        }

        return $lines;
    }

    private function notify(Booking $booking): void
    {
        $this->realtime->send(new BookingsChanged($booking->id, $booking->starts_at->toDateString()));

        $hallId = $booking->table?->hall_id;
        if ($hallId !== null) {
            $this->realtime->send(new HallChanged($hallId));
        }
    }
}
