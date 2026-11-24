<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Models\DiningTable;
use App\Models\Order;
use App\Services\ReportService;
use Illuminate\Support\Str;

function paidOrder(int $total, ?int $waiterId, string $name = 'Palaw'): Order
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

    test()->actingAs(staff(SystemRole::Cashier))->post(route('cashier.pay', $order), ['method' => 'cash']);

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

    foreach (['revenue', 'load', 'dishes', 'staff'] as $report) {
        $response = $this->actingAs(staff(SystemRole::Owner))->get(route('reports.export', ['report' => $report]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        expect(substr($response->getContent(), 0, 2))->toBe('PK');
    }
});

it('отчёты закрыты без права «Отчёты»', function () {
    $this->actingAs(staff(SystemRole::Cashier))->get(route('reports.index'))->assertForbidden();
});
