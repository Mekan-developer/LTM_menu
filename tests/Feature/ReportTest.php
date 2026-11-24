<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Models\DiningTable;
use App\Models\Order;
use App\Services\ReportService;
use Illuminate\Support\Str;

function paidOrder(int $total, ?int $waiterId, string $name = 'Palaw', array $pay = []): Order
{
    $order = Order::create([
        'public_token' => Str::random(32),
        'dining_table_id' => DiningTable::factory()->create()->id,
        'waiter_id' => $waiterId,
        'source' => OrderSource::Waiter->value,
        'status' => OrderStatus::BillRequested->value,
        'opened_at' => now()->subHour(),
        'subtotal' => $total,
        'total' => $total,
    ]);
    $order->items()->create([
        'name_tm' => $name, 'name_ru' => $name, 'price' => $total, 'quantity' => 1,
        'station' => 'kitchen', 'status' => OrderItemStatus::Served->value, 'batch' => 1,
    ]);

    test()->actingAs(staff(SystemRole::Cashier))->post(route('cashier.pay', $order), ['method' => 'cash', ...$pay]);

    return $order->fresh();
}

it('отчёт «Выручка» считает сумму, счета и средний чек за период', function () {
    $waiter = staff(SystemRole::Waiter);
    paidOrder(10000, $waiter->id);
    paidOrder(30000, $waiter->id, 'Dograma');

    $service = app(ReportService::class);
    [$from, $to] = $service->period(now()->toDateString(), now()->toDateString());
    $revenue = $service->revenue($from, $to);

    expect($revenue['totals']['revenue'])->toBe(40000)
        ->and($revenue['totals']['bills'])->toBe(2)
        ->and($revenue['totals']['avg_check'])->toBe(20000)
        ->and($revenue['days'])->toHaveCount(1);
});

it('отчёт «Сотрудники» показывает выручку официанта', function () {
    $waiter = staff(SystemRole::Waiter, ['name' => 'Nurly']);
    paidOrder(25000, $waiter->id);

    $service = app(ReportService::class);
    [$from, $to] = $service->period(null, null);
    $row = collect($service->staff($from, $to))->firstWhere('id', $waiter->id);

    expect($row['name'])->toBe('Nurly')
        ->and($row['bills'])->toBe(1)
        ->and($row['revenue'])->toBe(25000);
});

it('загрузка зала отмечает занятый стол в нужном часе', function () {
    paidOrder(10000, null);

    $service = app(ReportService::class);
    [$from, $to] = $service->period(now()->toDateString(), now()->toDateString());
    $load = $service->hallLoad($from, $to);

    expect($load['matrix'][now()->dayOfWeekIso - 1][(int) now()->format('G')])->toBeGreaterThan(0.0);
});

it('отчёты выгружаются в Excel (.xlsx)', function () {
    paidOrder(10000, staff(SystemRole::Waiter)->id);

    foreach (['revenue', 'guests', 'load', 'dishes', 'staff'] as $report) {
        $response = $this->actingAs(staff(SystemRole::Owner))->get(route('reports.export', ['report' => $report]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        expect(substr($response->getContent(), 0, 2))->toBe('PK');
    }
});

it('отчёт «Постоянные гости» делит гостей на новых и повторных и строит топ по сумме', function () {
    // Merdan бывал 10 дней назад — в периоде «сегодня» он повторный; Aýna пришла впервые и дважды.
    $this->travelTo(now()->subDays(10));
    paidOrder(5000, null, pay: ['guest_phone' => '61234567', 'guest_name' => 'Merdan']);
    $this->travelBack();
    paidOrder(10000, null, pay: ['guest_phone' => '61234567']);
    paidOrder(20000, null, pay: ['guest_phone' => '65111111', 'guest_name' => 'Aýna']);
    paidOrder(15000, null, pay: ['guest_phone' => '65111111']);
    paidOrder(7000, null); // счёт без телефона гостя

    $service = app(ReportService::class);
    [$from, $to] = $service->period(now()->toDateString(), now()->toDateString());
    $report = $service->guests($from, $to, true);

    expect($report['totals'])->toMatchArray([
        'guests' => 2, 'new' => 1, 'returning' => 1, 'bills' => 4, 'bills_with_guest' => 3, 'spent' => 45000,
    ])
        ->and($report['days'][0])->toMatchArray(['new' => 1, 'returning' => 1])
        ->and(array_column($report['top'], 'name'))->toBe(['Aýna', 'Merdan'])
        ->and($report['top'][0])->toMatchArray(['visits' => 2, 'spent' => 35000, 'avg_check' => 17500, 'is_new' => true, 'phone' => '+99365111111'])
        ->and($report['top'][1])->toMatchArray(['visits' => 1, 'spent' => 10000, 'is_new' => false]);
});

it('телефоны гостей в отчёте видны только с правом «Гости»', function () {
    paidOrder(10000, null, pay: ['guest_phone' => '61234567', 'guest_name' => 'Merdan']);

    $this->actingAs(staff(SystemRole::Accountant))->get(route('reports.index'))
        ->assertInertia(fn ($p) => $p->where('canGuests', false)
            ->where('guests.top.0.name', 'Merdan')->where('guests.top.0.phone', null));

    $this->actingAs(staff(SystemRole::Owner))->get(route('reports.index'))
        ->assertInertia(fn ($p) => $p->where('canGuests', true)->where('guests.top.0.phone', '+99361234567'));
});

it('отчёты закрыты без права «Отчёты»', function () {
    $this->actingAs(staff(SystemRole::Cashier))->get(route('reports.index'))->assertForbidden();
});
