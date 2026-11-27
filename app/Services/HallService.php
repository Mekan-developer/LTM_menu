<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\TableStatus;
use App\Events\HallChanged;
use App\Http\Resources\OrderResource;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\HallElement;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\HallRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TableRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Схема зала (ТЗ 2.2, 2.4): статусы столов для режима «Работа» и сохранение раскладки для «Редактора».
 * Размеры стола хранятся без учёта поворота; при rotation = 90 ширина и высота меняются местами.
 */
class HallService
{
    public const GRID = 20;

    public const MIN_SIZE = 60;

    public function __construct(
        private readonly HallRepository $halls,
        private readonly OrderRepository $orders,
        private readonly ActionLogger $logger,
        private readonly Realtime $realtime,
        private readonly BookingRepository $bookings,
        private readonly SettingsService $settings,
        private readonly TableRepository $tables,
    ) {}

    /**
     * Столы зала со статусами — фиксированное число запросов при любом количестве столов:
     * столы + активные заказы + официанты одним JOIN, позиции одним GROUP BY, объекты зала.
     *
     * @return array{tables: array<int, array<string, mixed>>, elements: array<int, array<string, mixed>>, revision: string}
     */
    public function board(Hall $hall, User $viewer): array
    {
        // Один активный заказ на стол; если вдруг их два, берём новый (сортировка в запросе).
        $rows = $this->halls->boardRows($hall)->unique('id')->values();
        $orderIds = $rows->pluck('order_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $counts = $this->halls->itemCounts($orderIds)->groupBy('order_id');
        $seeAll = $viewer->hasPermission(Permission::TransferTables);
        $editor = $viewer->hasPermission(Permission::Tables);
        // Брони, из-за которых свободный стол сейчас «забронирован» — один запрос на зал.
        $reserved = $this->bookings->reservedInHall($hall, now(), (int) $this->settings->get('booking_reserve_before'));
        $seeGuest = $viewer->hasPermission(Permission::Bookings);

        $tables = $rows->map(function (\stdClass $r) use ($counts, $seeAll, $editor, $viewer, $reserved, $seeGuest) {
            $orderId = $r->order_id !== null ? (int) $r->order_id : null;
            $waiterId = $r->waiter_id !== null ? (int) $r->waiter_id : null;
            $mine = $waiterId === null || $waiterId === $viewer->id;
            $details = $orderId === null || $mine || $seeAll;
            $byStatus = $orderId !== null ? $counts->get($orderId, collect())->pluck('n', 'status') : collect();

            $status = match (true) {
                $r->order_status === OrderStatus::BillRequested->value => TableStatus::Bill,
                $orderId !== null => TableStatus::Occupied,
                default => $this->reservedStatus((int) $r->id, $reserved) ?? TableStatus::Free,
            };
            $booking = $status === TableStatus::Reserved ? $reserved->get((int) $r->id) : null;

            return [
                'id' => (int) $r->id,
                'number' => $r->number,
                'seats' => (int) $r->seats,
                'shape' => $r->shape,
                'pos_x' => (int) $r->pos_x,
                'pos_y' => (int) $r->pos_y,
                'width' => (int) $r->width,
                'height' => (int) $r->height,
                'rotation' => (int) $r->rotation,
                // Ссылка QR нужна только редактору схемы (скачать / печать).
                'qr_url' => $editor ? route('guest.menu', $r->qr_token) : null,
                'status' => $status->value,
                'mine' => $mine,
                'details' => $details,
                // Чужой стол без права «Передача столов»: только «занят», без деталей.
                'qr_pending' => $details && (int) ($byStatus[OrderItemStatus::Pending->value] ?? 0) > 0,
                'ready' => $details && (int) ($byStatus[OrderItemStatus::Ready->value] ?? 0) > 0,
                'order_id' => $details ? $orderId : null,
                'opened_at' => $details && $r->opened_at ? Carbon::parse($r->opened_at)->toIso8601String() : null,
                'total' => $details && $r->total !== null ? (int) $r->total : null,
                'guests_hint' => null, // число гостей в заказе не хранится; показываем время с открытия
                // Ближайшая бронь «забронированного» стола; имя гостя — только с правом «Брони».
                'booking' => $booking ? [
                    'id' => (int) $booking->id,
                    'starts_at' => Carbon::parse($booking->starts_at)->toIso8601String(),
                    'guests_count' => (int) $booking->guests_count,
                    'name' => $seeGuest ? $booking->name : null,
                ] : null,
                'waiter' => $details && $waiterId !== null ? [
                    'id' => $waiterId,
                    'name' => $r->waiter_name,
                    'initials' => self::initials((string) $r->waiter_name),
                ] : null,
            ];
        })->values()->all();

        $elements = $this->halls->elements($hall)->map(fn (HallElement $e) => self::elementData($e))->values()->all();

        return ['tables' => $tables, 'elements' => $elements, 'revision' => self::revision($tables, $elements)];
    }

    /**
     * Версия раскладки: меняется при правке столов или объектов зала, но не при заказах и бронях.
     * Редактор присылает её при сохранении, чтобы не затереть чужие изменения.
     *
     * @param  array<int, array<string, mixed>>  $tables
     * @param  array<int, array<string, mixed>>  $elements
     */
    private static function revision(array $tables, array $elements): string
    {
        $pick = fn (array $items, array $keys) => collect($items)->sortBy('id')->values()
            ->map(fn (array $item) => array_map(fn (string $key) => (string) $item[$key], $keys))
            ->all();

        return md5((string) json_encode([
            $pick($tables, ['id', 'number', 'seats', 'shape', 'pos_x', 'pos_y', 'width', 'height', 'rotation']),
            $pick($elements, ['id', 'type', 'label_tm', 'label_ru', 'pos_x', 'pos_y', 'width', 'height']),
        ]));
    }

    /**
     * Брони (ТЗ 2.2): за booking_reserve_before минут (1 час) до брони свободный стол
     * становится «забронирован» и остаётся им до прихода гостя или конца брони.
     *
     * @param  Collection<int, \stdClass>  $reserved  брони зала по id стола
     */
    public function reservedStatus(int $tableId, Collection $reserved): ?TableStatus
    {
        return $reserved->has($tableId) ? TableStatus::Reserved : null;
    }

    /**
     * Панель стола: заказ с позициями — только своему официанту или с правом «Передача столов».
     *
     * @return array<string, mixed>
     */
    public function tableDetail(DiningTable $table, User $viewer): array
    {
        $order = $table->activeOrder()->first();
        $mine = $order === null || $order->waiter_id === null || $order->waiter_id === $viewer->id;
        $details = $mine || $viewer->hasPermission(Permission::TransferTables);

        return [
            'id' => $table->id,
            'number' => $table->number,
            'seats' => $table->seats,
            'hall_id' => $table->hall_id,
            'qr_url' => route('guest.menu', $table->qr_token),
            'details' => $details,
            // Брони этого стола на сегодня; гостя видит только право «Брони».
            'bookings' => $viewer->hasPermission(Permission::Bookings)
                ? $this->bookings->upcomingForTable($table->id, now())->map(fn ($b) => [
                    'id' => $b->id,
                    'starts_at' => $b->starts_at->toIso8601String(),
                    'guests_count' => $b->guests_count,
                    'status' => $b->status->value,
                    'name' => $b->guest?->name,
                    'phone' => $b->guest?->phone,
                ])->values()->all()
                : [],
            'order' => $order !== null && $details
                ? (new OrderResource($this->orders->loadForScreen($order)))->resolve()
                : null,
        ];
    }

    /**
     * Сохранение схемы одним запросом (ТЗ 2.2): столы и объекты целиком.
     * Проверяет, что схему не изменили после начала правки, затем границы полотна,
     * пересечения столов и уникальность номеров.
     *
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array<string, mixed>>  $elements
     */
    public function saveLayout(Hall $hall, string $revision, array $tables, array $elements, User $user): void
    {
        DB::transaction(function () use ($hall, $revision, $tables, $elements, $user) {
            $this->halls->lock($hall);
            $existing = $this->halls->activeTables($hall)->keyBy('id');

            $current = self::revision(
                $existing->toArray(),
                $this->halls->elements($hall)->map(fn (HallElement $e) => self::elementData($e))->all(),
            );

            if ($revision !== $current) {
                throw ValidationException::withMessages(['revision' => __('crm.hall.stale')]);
            }

            $this->assertLayoutValid($hall, $tables, $elements);

            $keep = collect($tables)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            $busy = $this->halls->busyTableIds($hall);

            // Удалённые со схемы столы: с активным заказом — нельзя, с историей — мягкое удаление.
            foreach ($existing->except($keep) as $table) {
                if (in_array($table->id, $busy, true)) {
                    throw ValidationException::withMessages(['tables' => __('crm.hall.busy_delete', ['n' => $table->number])]);
                }

                $this->tables->remove($table);
            }

            $sort = (int) DiningTable::max('sort');
            foreach ($tables as $data) {
                $attrs = [
                    'number' => (string) $data['number'],
                    'seats' => (int) $data['seats'],
                    'shape' => $data['shape'],
                    'pos_x' => (int) $data['pos_x'],
                    'pos_y' => (int) $data['pos_y'],
                    'width' => (int) $data['width'],
                    'height' => (int) $data['height'],
                    'rotation' => (int) $data['rotation'],
                ];

                if (! empty($data['id'])) {
                    $existing->get((int) $data['id'])?->update($attrs);
                } else {
                    DiningTable::create($attrs + [
                        'hall_id' => $hall->id,
                        'qr_token' => Str::lower(Str::random(32)),
                        'sort' => ++$sort,
                        'is_active' => true,
                    ]);
                }
            }

            $keepElements = collect($elements)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            $hall->elements()->whereNotIn('id', $keepElements)->delete();
            $current = $hall->elements()->get()->keyBy('id');

            foreach ($elements as $data) {
                $attrs = [
                    'type' => $data['type'],
                    'label_tm' => $data['label_tm'] ?? null,
                    'label_ru' => $data['label_ru'] ?? null,
                    'pos_x' => (int) $data['pos_x'],
                    'pos_y' => (int) $data['pos_y'],
                    'width' => (int) $data['width'],
                    'height' => (int) $data['height'],
                ];

                if (! empty($data['id']) && $current->has((int) $data['id'])) {
                    $current->get((int) $data['id'])?->update($attrs);
                } else {
                    $hall->elements()->create($attrs);
                }
            }

            $this->logger->log(ActionLogger::HALL_LAYOUT_SAVED, $hall, [
                'hall' => $hall->name_ru, 'tables' => count($tables), 'elements' => count($elements),
            ], $user);
        });

        $this->realtime->send(new HallChanged($hall->id));
    }

    /** @param array<string, mixed> $data */
    public function createHall(array $data): Hall
    {
        return Hall::create($data + ['sort' => $this->halls->nextSort(), 'is_active' => true]);
    }

    /**
     * Новый размер полотна должен вмещать всё, что уже стоит на схеме.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateHall(Hall $hall, array $data): Hall
    {
        $width = (int) ($data['width'] ?? $hall->width);
        $height = (int) ($data['height'] ?? $hall->height);

        $boxes = $this->halls->activeTables($hall)->map(fn (DiningTable $t) => self::box($t->toArray()))
            ->concat($this->halls->elements($hall)->map(fn (HallElement $e) => self::box($e->toArray())));

        foreach ($boxes as [$x, $y, $w, $h]) {
            if ($x + $w > $width || $y + $h > $height) {
                throw ValidationException::withMessages(['width' => __('crm.hall.too_small')]);
            }
        }

        $hall->update($data);
        $this->realtime->send(new HallChanged($hall->id));

        return $hall;
    }

    /**
     * Удалить можно только зал без столов, считая выключенные. Если в нём остались удалённые
     * столы с историей, зал удаляется мягко, иначе насовсем.
     */
    public function deleteHall(Hall $hall): void
    {
        if ($hall->tables()->exists()) {
            throw ValidationException::withMessages(['hall' => __('crm.hall.not_empty')]);
        }

        $hall->tables()->withTrashed()->exists() ? $hall->delete() : $hall->forceDelete();
    }

    /**
     * Свободное место для нового стола из списка «Столы и QR»: первая клетка сетки без пересечений.
     *
     * @return array{0:int, 1:int}|null
     */
    public function freeSpot(Hall $hall, int $width, int $height): ?array
    {
        $boxes = $this->halls->placedTables($hall)->map(fn (DiningTable $t) => self::box($t->toArray()))
            ->concat($this->halls->elements($hall)->map(fn (HallElement $e) => self::box($e->toArray())));

        for ($y = self::GRID * 2; $y + $height <= $hall->height; $y += self::GRID) {
            for ($x = self::GRID * 2; $x + $width <= $hall->width; $x += self::GRID) {
                $candidate = [$x, $y, $width, $height];
                $clear = $boxes->every(fn ($b) => ! self::overlaps($candidate, $b, self::GRID));

                if ($clear) {
                    return [$x, $y];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array<string, mixed>>  $elements
     */
    private function assertLayoutValid(Hall $hall, array $tables, array $elements): void
    {
        $errors = [];
        $boxes = [];

        foreach ($tables as $i => $t) {
            $box = self::box($t);
            [$x, $y, $w, $h] = $box;

            if ($x < 0 || $y < 0 || $x + $w > $hall->width || $y + $h > $hall->height) {
                $errors["tables.$i.pos_x"] = __('crm.hall.out_of_bounds', ['n' => $t['number']]);
            }

            foreach ($boxes as $j => $other) {
                if (self::overlaps($box, $other)) {
                    $errors["tables.$i.pos_x"] = __('crm.hall.overlap', ['a' => $tables[$j]['number'], 'b' => $t['number']]);
                }
            }

            $boxes[$i] = $box;
        }

        foreach ($elements as $i => $e) {
            [$x, $y, $w, $h] = self::box($e);

            if ($x < 0 || $y < 0 || $x + $w > $hall->width || $y + $h > $hall->height) {
                $errors["elements.$i.pos_x"] = __('crm.hall.element_out_of_bounds');
            }
        }

        // Номер стола уникален во всём ресторане, включая другие залы и выключенные (но не удалённые) столы.
        $ids = collect($tables)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $taken = DiningTable::query()
            ->whereIn('number', collect($tables)->pluck('number')->map(fn ($n) => (string) $n)->all())
            ->whereNotIn('id', $ids)
            ->pluck('number')->all();

        foreach ($tables as $i => $t) {
            if (in_array((string) $t['number'], $taken, true)) {
                $errors["tables.$i.number"] = __('crm.hall.number_taken', ['n' => $t['number']]);
            }
        }

        // id стола из чужого зала или выключенного — в эту схему не принимаем.
        $own = $this->halls->activeTables($hall)->pluck('id')->all();
        foreach ($tables as $i => $t) {
            if (! empty($t['id']) && ! in_array((int) $t['id'], $own, true)) {
                $errors["tables.$i.id"] = __('crm.hall.foreign_table');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Прямоугольник объекта на полотне с учётом поворота: [x, y, w, h].
     *
     * @param  array<string, mixed>  $o
     * @return array{0:int, 1:int, 2:int, 3:int}
     */
    public static function box(array $o): array
    {
        $w = (int) $o['width'];
        $h = (int) $o['height'];
        $rotated = (int) ($o['rotation'] ?? 0) === 90;

        return [(int) $o['pos_x'], (int) $o['pos_y'], $rotated ? $h : $w, $rotated ? $w : $h];
    }

    /**
     * Пересечение прямоугольников; касание краями — не пересечение. $gap — минимальный зазор.
     *
     * @param  array{0:int, 1:int, 2:int, 3:int}  $a
     * @param  array{0:int, 1:int, 2:int, 3:int}  $b
     */
    public static function overlaps(array $a, array $b, int $gap = 0): bool
    {
        return $a[0] < $b[0] + $b[2] + $gap && $b[0] < $a[0] + $a[2] + $gap
            && $a[1] < $b[1] + $b[3] + $gap && $b[1] < $a[1] + $a[3] + $gap;
    }

    /** «Gülşat Myradowa» → «GM». */
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        return mb_strtoupper(collect($parts)->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    }

    /** @return array<string, mixed> */
    public static function elementData(HallElement $e): array
    {
        return [
            'id' => $e->id,
            'type' => $e->type->value,
            'label_tm' => $e->label_tm,
            'label_ru' => $e->label_ru,
            'pos_x' => $e->pos_x,
            'pos_y' => $e->pos_y,
            'width' => $e->width,
            'height' => $e->height,
        ];
    }
}
