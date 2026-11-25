<?php

use App\Enums\OrderChannel;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Models\DiningTable;
use App\Models\Guest;
use App\Models\Hall;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

function monitorOrder(array $attrs = []): Order
{
    return Order::create([
        'public_token' => Str::random(32),
        'source' => OrderSource::Waiter->value,
        'channel' => OrderChannel::Hall->value,
        'status' => OrderStatus::Open->value,
        'opened_at' => now(),
        'total' => 10000,
        ...$attrs,
    ]);
}

beforeEach(function () {
    $this->actingAs(staff(SystemRole::HallAdmin));
});

it('по умолчанию показывает заказы за сегодня со сводкой', function () {
    $today = monitorOrder(['status' => OrderStatus::Paid->value, 'paid_at' => now(), 'total' => 25000]);
    monitorOrder(); // открыт сейчас
    monitorOrder(['status' => OrderStatus::Cancelled->value]);
    monitorOrder(['opened_at' => now()->subDays(2)]); // не сегодня

    $this->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('period.from', now()->toDateString())
            ->where('period.to', now()->toDateString())
            ->has('orders.data', 3)
            ->where('summary', ['count' => 3, 'active' => 1, 'paid' => 1, 'cancelled' => 1, 'revenue' => 25000])
            ->where('detail', null));

    expect($today->id)->toBeInt();
});

it('фильтрует по периоду, каналу, статусу и официанту', function () {
    $waiter = staff(SystemRole::Waiter);
    monitorOrder(['opened_at' => now()->subDays(3), 'waiter_id' => $waiter->id]);
    monitorOrder(['opened_at' => now()->subDays(3), 'channel' => OrderChannel::Delivery->value, 'source' => OrderSource::Online->value]);
    monitorOrder(['opened_at' => now()->subDays(3), 'status' => OrderStatus::Paid->value]);

    $from = now()->subDays(4)->toDateString();
    $to = now()->subDays(2)->toDateString();

    $this->get(route('orders.index', ['from' => $from, 'to' => $to]))
        ->assertInertia(fn ($page) => $page->has('orders.data', 3)->has('waiters', 1));

    $this->get(route('orders.index', ['from' => $from, 'to' => $to, 'channel' => 'delivery']))
        ->assertInertia(fn ($page) => $page->has('orders.data', 1)->where('orders.data.0.channel', 'delivery'));

    // Статус «в работе» = открытые и ждущие счёт; сводка при этом считает все статусы.
    $this->get(route('orders.index', ['from' => $from, 'to' => $to, 'status' => 'active']))
        ->assertInertia(fn ($page) => $page->has('orders.data', 2)->where('summary.count', 3));

    $this->get(route('orders.index', ['from' => $from, 'to' => $to, 'waiter' => $waiter->id]))
        ->assertInertia(fn ($page) => $page->has('orders.data', 1)->where('orders.data.0.waiter', $waiter->name));
});

it('ищет по номеру заказа, номеру стола, телефону и имени гостя', function () {
    $table = DiningTable::factory()->create(['hall_id' => Hall::factory()->create()->id, 'number' => '17']);
    $guest = Guest::factory()->create(['name' => 'Merdan Ataýew', 'phone' => '+99361234567']);
    $byTable = monitorOrder(['dining_table_id' => $table->id]);
    $byGuest = monitorOrder(['guest_id' => $guest->id]);
    $other = monitorOrder();

    $ids = fn (string $q) => collect($this->get(route('orders.index', ['q' => $q]))
        ->viewData('page')['props']['orders']['data'])->pluck('id')->all();

    expect($ids((string) $other->id))->toContain($other->id)
        ->and($ids('17'))->toContain($byTable->id)
        ->and($ids('6123'))->toBe([$byGuest->id])
        ->and($ids('merdan'))->toBe([$byGuest->id]);
});

it('карточка заказа открывается по ?show и содержит позиции и хронологию', function () {
    $order = monitorOrder();
    $order->items()->create([
        'name_tm' => 'Palaw', 'name_ru' => 'Плов', 'price' => 5000, 'quantity' => 2,
        'station' => 'kitchen', 'status' => 'sent', 'batch' => 1, 'sent_at' => now(),
    ]);

    $this->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page
            ->reloadOnly(['detail'], fn ($reload) => $reload->where('detail', null)));

    $this->get(route('orders.index', ['show' => $order->id]))
        ->assertInertia(fn ($page) => $page
            ->where('detail.id', $order->id)
            ->has('detail.items', 1)
            ->where('detail.items.0.name_ru', 'Плов')
            ->where('detail.items.0.total', 10000)
            ->has('detail.payments', 0)
            ->has('detail.logs', 0));
});

it('телефон гостя виден только с правом «Гости»', function () {
    $guest = Guest::factory()->create(['phone' => '+99361234567']);
    monitorOrder(['guest_id' => $guest->id]);

    $this->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page->where('orders.data.0.phone', '+99361234567'));

    $this->actingAs(staff(SystemRole::Accountant))
        ->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page->where('orders.data.0.phone', null));
});

it('без права «Все заказы» экран закрыт', function () {
    $this->actingAs(staff(SystemRole::Waiter))
        ->get(route('orders.index'))
        ->assertForbidden();
});

it('владелец видит экран всегда', function () {
    $this->actingAs(User::factory()->role(SystemRole::Owner)->create())
        ->get(route('orders.index'))
        ->assertOk();
});
