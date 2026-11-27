<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Events\HallChanged;
use App\Models\ActionLog;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Order;
use App\Models\User;
use App\Services\HallService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function tableWithOrder(Hall $hall, ?User $waiter, string $status = 'open', array $items = []): DiningTable
{
    $table = DiningTable::factory()->create(['hall_id' => $hall->id]);
    $order = Order::create([
        'public_token' => Str::random(32),
        'dining_table_id' => $table->id,
        'waiter_id' => $waiter?->id,
        'source' => OrderSource::Waiter->value,
        'status' => $status,
        'opened_at' => now()->subMinutes(15),
        'total' => 9500,
    ]);
    foreach ($items as $itemStatus) {
        $order->items()->create([
            'name_tm' => 'Palaw', 'name_ru' => 'Плов', 'price' => 9500, 'quantity' => 1,
            'station' => 'kitchen', 'status' => $itemStatus, 'batch' => 1,
        ]);
    }

    return $table;
}

function layoutTable(DiningTable $t, array $override = []): array
{
    return array_merge([
        'id' => $t->id, 'number' => $t->number, 'seats' => $t->seats, 'shape' => $t->shape->value,
        'pos_x' => $t->pos_x, 'pos_y' => $t->pos_y, 'width' => $t->width, 'height' => $t->height, 'rotation' => $t->rotation,
    ], $override);
}

function boardRevision(Hall $hall): string
{
    return app(HallService::class)->board($hall, staff(SystemRole::Owner))['revision'];
}

it('миграция переносит столы из текстового поля hall в залы без пересечений', function () {
    $migration = require database_path('migrations/2026_11_26_000002_add_layout_to_dining_tables.php');
    $migration->down();

    foreach (range(1, 12) as $n) {
        DB::table('dining_tables')->insert([
            'number' => (string) $n, 'hall' => $n > 9 ? 'Terrasa' : ($n === 1 ? null : 'Esasy zal'),
            'seats' => $n % 3 === 0 ? 6 : 4, 'qr_token' => Str::random(32), 'is_active' => true, 'sort' => $n,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $migration->up();

    expect(Schema::hasColumn('dining_tables', 'hall'))->toBeFalse()
        ->and(Hall::pluck('name_tm')->sort()->values()->all())->toBe(['Esasy zal', 'Esasy zal', 'Terrasa'])
        ->and(DiningTable::whereNull('hall_id')->count())->toBe(0);

    // Пустое значение → «Основной зал».
    expect(Hall::where('name_ru', 'Основной зал')->exists())->toBeTrue();

    foreach (Hall::with('tables')->get() as $hall) {
        $boxes = $hall->tables->map(fn ($t) => HallService::box($t->toArray()))->values();
        foreach ($boxes as $i => $a) {
            expect($a[0] + $a[2])->toBeLessThanOrEqual($hall->width)
                ->and($a[1] + $a[3])->toBeLessThanOrEqual($hall->height);
            foreach ($boxes->slice($i + 1) as $b) {
                expect(HallService::overlaps($a, $b))->toBeFalse();
            }
        }
    }
});

it('сохранять схему можно только с правом «Столы и QR»', function () {
    $hall = Hall::factory()->create();

    $this->actingAs(staff(SystemRole::Waiter))
        ->put(route('halls.layout', $hall), ['tables' => [], 'elements' => []])
        ->assertForbidden();
});

it('схема с пересечением столов или выходом за границы не сохраняется', function () {
    $hall = Hall::factory()->create(['width' => 1000, 'height' => 640]);
    $a = DiningTable::factory()->create(['hall_id' => $hall->id, 'pos_x' => 40, 'pos_y' => 40]);
    $b = DiningTable::factory()->create(['hall_id' => $hall->id, 'pos_x' => 200, 'pos_y' => 40]);
    $this->actingAs(staff(SystemRole::Owner));

    $revision = boardRevision($hall);

    $this->put(route('halls.layout', $hall), [
        'revision' => $revision,
        'tables' => [layoutTable($a), layoutTable($b, ['pos_x' => 60, 'pos_y' => 60])],
        'elements' => [],
    ])->assertSessionHasErrors('tables.1.pos_x');

    $this->put(route('halls.layout', $hall), [
        'revision' => $revision,
        'tables' => [layoutTable($a), layoutTable($b, ['pos_x' => 960])],
        'elements' => [],
    ])->assertSessionHasErrors('tables.1.pos_x');

    // Повёрнутый длинный стол занимает 80×160 — с поворотом выходит за нижний край.
    $this->put(route('halls.layout', $hall), [
        'revision' => $revision,
        'tables' => [layoutTable($a), layoutTable($b, ['width' => 160, 'pos_y' => 520, 'rotation' => 90])],
        'elements' => [],
    ])->assertSessionHasErrors('tables.1.pos_x');

    expect($b->fresh()->pos_x)->toBe(200);
});

it('схема сохраняется целиком: перемещение, новый стол, объект и удаление', function () {
    Event::fake([HallChanged::class]);
    $hall = Hall::factory()->create();
    $keep = DiningTable::factory()->create(['hall_id' => $hall->id, 'pos_x' => 40, 'pos_y' => 40]);
    $drop = DiningTable::factory()->create(['hall_id' => $hall->id, 'pos_x' => 200, 'pos_y' => 40]);

    $this->actingAs(staff(SystemRole::Owner))->put(route('halls.layout', $hall), [
        'revision' => boardRevision($hall),
        'tables' => [
            layoutTable($keep, ['pos_x' => 400, 'pos_y' => 300, 'rotation' => 90]),
            ['id' => null, 'number' => 'T-50', 'seats' => 6, 'shape' => 'rect', 'pos_x' => 40, 'pos_y' => 400, 'width' => 160, 'height' => 80, 'rotation' => 0],
        ],
        'elements' => [
            ['id' => null, 'type' => 'bar', 'label_tm' => 'Bar', 'label_ru' => 'Бар', 'pos_x' => 600, 'pos_y' => 40, 'width' => 240, 'height' => 60],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $new = DiningTable::where('number', 'T-50')->sole();

    expect($keep->fresh()->pos_x)->toBe(400)
        ->and($keep->fresh()->rotation)->toBe(90)
        ->and($new->hall_id)->toBe($hall->id)
        ->and($new->qr_token)->not->toBeEmpty()
        ->and(DiningTable::find($drop->id))->toBeNull()
        ->and($hall->elements()->count())->toBe(1)
        ->and(ActionLog::where('action', 'hall_layout_saved')->exists())->toBeTrue();

    Event::assertDispatched(HallChanged::class, fn ($e) => $e->hallId === $hall->id);
});

it('стол с активным заказом со схемы удалить нельзя', function () {
    $hall = Hall::factory()->create();
    $busy = tableWithOrder($hall, staff(SystemRole::Waiter));

    $this->actingAs(staff(SystemRole::Owner))
        ->put(route('halls.layout', $hall), ['revision' => boardRevision($hall), 'tables' => [], 'elements' => []])
        ->assertSessionHasErrors('tables');

    expect(DiningTable::find($busy->id))->not->toBeNull();
});

it('перенос на занятый стол запрещён, на свободный — освобождает старый и пишет журнал', function () {
    $hall = Hall::factory()->create();
    $waiter = staff(SystemRole::Waiter);
    $from = tableWithOrder($hall, $waiter);
    $busy = tableWithOrder($hall, $waiter);
    $free = DiningTable::factory()->create(['hall_id' => $hall->id]);
    $order = $from->activeOrder()->sole();

    $this->actingAs($waiter)
        ->post(route('waiter.move', $order), ['table_id' => $busy->id])
        ->assertSessionHasErrors('table_id');

    $this->post(route('waiter.move', $order), ['table_id' => $free->id])->assertSessionHasNoErrors();

    $board = collect(app(HallService::class)->board($hall, $waiter)['tables'])->keyBy('id');

    expect($board[$from->id]['status'])->toBe('free')
        ->and($board[$free->id]['status'])->toBe('occupied')
        ->and(ActionLog::where('action', 'table_moved')->sole()->payload)
        ->toMatchArray(['from' => $from->number, 'to' => $free->number]);
});

it('доска считает статусы free / occupied / bill и флаги qr_pending / ready', function () {
    $hall = Hall::factory()->create();
    $waiter = staff(SystemRole::Waiter);
    $free = DiningTable::factory()->create(['hall_id' => $hall->id]);
    $occupied = tableWithOrder($hall, $waiter, 'open', [OrderItemStatus::Sent->value]);
    $bill = tableWithOrder($hall, $waiter, OrderStatus::BillRequested->value);
    $qr = tableWithOrder($hall, null, 'open', [OrderItemStatus::Pending->value]);
    $ready = tableWithOrder($hall, $waiter, 'open', [OrderItemStatus::Ready->value]);

    $board = collect(app(HallService::class)->board($hall, $waiter)['tables'])->keyBy('id');

    expect($board[$free->id]['status'])->toBe('free')
        ->and($board[$occupied->id]['status'])->toBe('occupied')
        ->and($board[$occupied->id]['ready'])->toBeFalse()
        ->and($board[$bill->id]['status'])->toBe('bill')
        ->and($board[$qr->id]['qr_pending'])->toBeTrue()
        ->and($board[$qr->id]['mine'])->toBeTrue() // стол без официанта — «свой»
        ->and($board[$ready->id]['ready'])->toBeTrue()
        ->and($board[$ready->id]['waiter']['initials'])->not->toBeEmpty();
});

it('число SQL-запросов доски не растёт с количеством столов', function () {
    $waiter = staff(SystemRole::Waiter);
    $waiter->loadMissing('role.permissions');
    $service = app(HallService::class);
    // Прогрев: настройки читаются из БД один раз и дальше берутся из кэша — это не зависит от столов.
    $service->board(Hall::factory()->create(), $waiter);

    $count = function (int $n) use ($waiter, $service) {
        $hall = Hall::factory()->create();
        foreach (range(1, $n) as $i) {
            tableWithOrder($hall, $waiter, 'open', [OrderItemStatus::Pending->value, OrderItemStatus::Ready->value]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->board($hall, $waiter);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(5))->toBe($count(30));
});

it('официант без «Передачи столов» не получает деталей чужого стола', function () {
    $hall = Hall::factory()->create();
    $me = staff(SystemRole::Waiter);
    $other = staff(SystemRole::Waiter);
    $table = tableWithOrder($hall, $other, 'open', [OrderItemStatus::Ready->value]);

    $row = collect(app(HallService::class)->board($hall, $me)['tables'])->firstWhere('id', $table->id);

    expect($row['status'])->toBe('occupied')
        ->and($row['details'])->toBeFalse()
        ->and($row['order_id'])->toBeNull()
        ->and($row['total'])->toBeNull()
        ->and($row['waiter'])->toBeNull()
        ->and($row['ready'])->toBeFalse();

    $this->actingAs($me)
        ->get(route('hall.index', ['hall' => $hall->id, 'table' => $table->id]))
        ->assertInertia(fn ($page) => $page->where('detail.details', false)->where('detail.order', null));

    // Администратор зала с правом передачи видит всё.
    $row = collect(app(HallService::class)->board($hall, staff(SystemRole::HallAdmin))['tables'])->firstWhere('id', $table->id);
    expect($row['details'])->toBeTrue()->and($row['total'])->toBe(9500);
});

it('HallChanged уходит при запросе счёта и оплате', function () {
    Event::fake([HallChanged::class]);
    $hall = Hall::factory()->create();
    $waiter = staff(SystemRole::Waiter);
    $table = tableWithOrder($hall, $waiter, 'open', [OrderItemStatus::Served->value]);
    $order = $table->activeOrder()->sole();

    $this->actingAs($waiter)->post(route('waiter.bill', $order));
    Event::assertDispatched(HallChanged::class, fn ($e) => $e->hallId === $hall->id);

    Event::fake([HallChanged::class]);
    $this->actingAs(staff(SystemRole::Cashier))->post(route('cashier.pay', $order), ['method' => 'cash']);
    Event::assertDispatched(HallChanged::class, fn ($e) => $e->hallId === $hall->id);
});

it('страница схемы открыта официанту, редактору и отчётам, но не кухне', function () {
    Hall::factory()->create();

    $this->actingAs(staff(SystemRole::Waiter))->get(route('hall.index'))->assertOk();
    $this->actingAs(staff(SystemRole::Accountant))->get(route('hall.index'))->assertOk();
    $this->actingAs(staff(SystemRole::Cook))->get(route('hall.index'))->assertForbidden();
});

it('пустой зал удаляется, зал со столами — нет', function () {
    $empty = Hall::factory()->create();
    $full = Hall::factory()->create();
    DiningTable::factory()->create(['hall_id' => $full->id]);
    $this->actingAs(staff(SystemRole::Owner));

    $this->delete(route('halls.destroy', $full))->assertSessionHasErrors('hall');
    $this->delete(route('halls.destroy', $empty))->assertSessionHasNoErrors();

    expect(Hall::find($empty->id))->toBeNull()->and(Hall::find($full->id))->not->toBeNull();
});

it('зал, где остались только удалённые столы с историей, удаляется, а в истории сохраняется', function () {
    $hall = Hall::factory()->create();
    $table = tableWithOrder($hall, null, OrderStatus::Paid->value);
    $this->actingAs(staff(SystemRole::Owner))->delete(route('tables.destroy', $table));

    $this->delete(route('halls.destroy', $hall))->assertSessionHasNoErrors();

    expect(Hall::find($hall->id))->toBeNull()
        ->and(Order::sole()->table?->hall?->id)->toBe($hall->id);

    // В удалённый зал новый стол не добавить.
    $this->post(route('tables.store'), ['number' => '77', 'hall_id' => $hall->id, 'seats' => 4])
        ->assertSessionHasErrors('hall_id');
});

it('устаревшая схема не сохраняется и не стирает стол, добавленный после начала правки', function () {
    $hall = Hall::factory()->create();
    $table = DiningTable::factory()->create(['hall_id' => $hall->id, 'pos_x' => 40, 'pos_y' => 40]);
    $this->actingAs(staff(SystemRole::Owner));
    $revision = boardRevision($hall);

    $added = DiningTable::factory()->create(['hall_id' => $hall->id, 'pos_x' => 400, 'pos_y' => 40]);

    $this->put(route('halls.layout', $hall), [
        'revision' => $revision,
        'tables' => [layoutTable($table, ['pos_x' => 200])],
        'elements' => [],
    ])->assertSessionHasErrors('revision');

    expect(DiningTable::find($added->id))->not->toBeNull()
        ->and($table->fresh()->pos_x)->toBe(40);
});

it('заказы за столами не мешают сохранить схему с объектами', function () {
    $hall = Hall::factory()->create();
    $hall->elements()->create(['type' => 'wall', 'pos_x' => 600, 'pos_y' => 400, 'width' => 200, 'height' => 20]);
    $table = DiningTable::factory()->create(['hall_id' => $hall->id, 'pos_x' => 40, 'pos_y' => 40]);
    $this->actingAs(staff(SystemRole::Owner));
    $revision = boardRevision($hall);

    $this->post(route('waiter.open', $table));

    $this->put(route('halls.layout', $hall), [
        'revision' => $revision,
        'tables' => [layoutTable($table, ['pos_x' => 200])],
        'elements' => [],
    ])->assertSessionHasNoErrors();

    expect($table->fresh()->pos_x)->toBe(200)
        ->and($table->activeOrder()->exists())->toBeTrue();
});

it('новый стол из списка не встаёт на объект схемы и на выключенный стол', function () {
    $hall = Hall::factory()->create();
    $hall->elements()->create(['type' => 'bar', 'pos_x' => 0, 'pos_y' => 0, 'width' => 400, 'height' => 140]);
    $off = DiningTable::factory()->create([
        'hall_id' => $hall->id, 'is_active' => false, 'pos_x' => 420, 'pos_y' => 40, 'width' => 80, 'height' => 80,
    ]);

    $this->actingAs(staff(SystemRole::Owner))
        ->post(route('tables.store'), ['number' => '55', 'hall_id' => $hall->id, 'seats' => 4])
        ->assertSessionHasNoErrors();

    $box = HallService::box(DiningTable::where('number', '55')->sole()->toArray());

    expect(HallService::overlaps($box, [0, 0, 400, 140]))->toBeFalse()
        ->and(HallService::overlaps($box, HallService::box($off->toArray())))->toBeFalse();
});
