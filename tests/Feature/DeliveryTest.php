<?php

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Models\ActionLog;
use App\Models\Delivery;
use App\Models\Dish;
use App\Models\Guest;
use App\Models\Order;
use App\Services\CashierService;
use App\Services\ReportService;

/** Заказ по звонку от администратора; по умолчанию — доставка, оплата наличными. */
function phoneOrder(array $override = [], ?Dish $dish = null): Delivery
{
    $dish ??= Dish::factory()->create(['price' => 10000]);

    test()->actingAs(staff(SystemRole::HallAdmin))->post(route('deliveries.store'), [
        'type' => 'delivery', 'name' => 'Merdan', 'phone' => '61 23 45 67', 'address' => 'Aşgabat, Oguzhan 12',
        'method' => 'cash', 'items' => [['dish_id' => $dish->id, 'quantity' => 2]], ...$override,
    ])->assertSessionHasNoErrors();

    return Delivery::query()->latest('id')->firstOrFail();
}

function deliveryAction(Delivery $d, string $action, array $data = [], ?SystemRole $role = SystemRole::HallAdmin)
{
    return test()->actingAs(staff($role))->post(route('deliveries.action', $d), ['action' => $action, ...$data]);
}

/** Кухня нажала «Готово» по всем позициям заказа. */
function kitchenDone(Delivery $d): void
{
    test()->actingAs(staff(SystemRole::Cook))
        ->post(route('kitchen.ready', [$d->order_id, 1]), ['station' => 'kitchen'])->assertSessionHasNoErrors();
}

it('гость заказывает доставку на странице: попадает в базу, к сумме добавляется доставка', function () {
    $dish = Dish::factory()->create(['price' => 12000]);

    $this->postJson(route('guest.delivery.store'), [
        'type' => 'delivery', 'name' => 'Aýna', 'phone' => '+993 65 111111', 'address' => 'Köşi, 5',
        'method' => 'card', 'items' => [['dish_id' => $dish->id, 'quantity' => 1, 'price' => 12000]],
    ])->assertCreated()->assertJsonStructure(['status_url']);

    $order = Order::query()->with('delivery')->latest('id')->firstOrFail();

    expect($order->channel->value)->toBe('delivery')
        ->and($order->dining_table_id)->toBeNull()
        ->and($order->delivery->status)->toBe(DeliveryStatus::New)
        ->and($order->delivery_fee)->toBe(1500)
        ->and($order->total)->toBe(13500)
        ->and(Guest::query()->where('phone', '+99365111111')->value('address'))->toBe('Köşi, 5');
});

it('гость видит сообщение, если цена в его меню устарела', function () {
    $dish = Dish::factory()->create(['price' => 12000]);

    $this->postJson(route('guest.delivery.store'), [
        'type' => 'pickup', 'name' => 'Aýna', 'phone' => '65111111', 'method' => 'cash',
        'items' => [['dish_id' => $dish->id, 'quantity' => 1, 'price' => 9000]],
    ])->assertStatus(409)->assertJsonPath('problems.0.type', 'price_changed');

    expect(Order::query()->count())->toBe(0);
});

it('для доставки нужен адрес, для самовывоза — нет', function () {
    $dish = Dish::factory()->create();

    $this->actingAs(staff(SystemRole::HallAdmin))->post(route('deliveries.store'), [
        'type' => 'delivery', 'name' => 'Merdan', 'phone' => '61234567', 'method' => 'cash',
        'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('address');

    $pickup = phoneOrder(['type' => 'pickup', 'address' => null], $dish);

    expect($pickup->order->channel->value)->toBe('pickup')
        ->and($pickup->order->delivery_fee)->toBe(0)
        ->and($pickup->status)->toBe(DeliveryStatus::Accepted); // по звонку — сразу «принят»
});

it('полный путь доставки: кухня, курьер, вручение с другим способом оплаты, сдача наличных', function () {
    $courier = staff(SystemRole::Courier);
    $d = phoneOrder();

    deliveryAction($d, 'kitchen')->assertSessionHasNoErrors();
    expect($d->fresh()->status)->toBe(DeliveryStatus::Cooking)
        ->and($d->order->items()->where('status', 'sent')->count())->toBe(1);

    kitchenDone($d);
    expect($d->fresh()->status)->toBe(DeliveryStatus::Ready);

    deliveryAction($d, 'courier', ['courier_id' => $courier->id])->assertSessionHasNoErrors();

    // Курьер видит заказ у себя, забирает и вручает: гость заплатил частью наличными.
    $this->actingAs($courier)->get(route('courier.index'))
        ->assertInertia(fn ($p) => $p->where('deliveries.0.order_id', $d->order_id));
    $this->actingAs($courier)->post(route('courier.pick-up', $d))->assertSessionHasNoErrors();
    // Курьер забрал — заказ ушёл из строки «Ждут выдачи» на кухне.
    expect($d->order->items()->where('status', 'ready')->exists())->toBeFalse();
    $this->actingAs($courier)->post(route('courier.complete', $d), ['method' => 'mixed', 'cash_amount' => '150'])
        ->assertSessionHasNoErrors();

    $d->refresh();
    $payment = $d->order->activePayment;
    expect($d->status)->toBe(DeliveryStatus::Delivered)
        ->and($d->order->status)->toBe(OrderStatus::Paid)
        ->and($payment->total)->toBe(21500)
        ->and($payment->cash_amount)->toBe(15000)
        ->and($payment->card_amount)->toBe(6500)
        ->and(ActionLog::query()->where('action', 'payment_method_changed')->exists())->toBeTrue();

    // До сдачи наличные видны «у курьера» отдельной строкой итога дня.
    $summary = app(CashierService::class)->daySummary();
    expect($summary['courier_cash'])->toBe(15000)->and($summary['cash'])->toBe(0);

    $this->actingAs(staff(SystemRole::Cashier))->post(route('cashier.courier-cash', $courier))->assertSessionHasNoErrors();
    $summary = app(CashierService::class)->daySummary();
    expect($summary['courier_cash'])->toBe(0)->and($summary['cash'])->toBe(15000)
        ->and($d->fresh()->cash_received_at)->not->toBeNull();
});

it('курьер не видит и не меняет чужие заказы', function () {
    $d = phoneOrder();
    deliveryAction($d, 'kitchen');
    kitchenDone($d);
    deliveryAction($d, 'courier', ['courier_id' => staff(SystemRole::Courier)->id]);

    $other = staff(SystemRole::Courier);
    $this->actingAs($other)->get(route('courier.index'))->assertInertia(fn ($p) => $p->where('deliveries', []));
    $this->actingAs($other)->post(route('courier.pick-up', $d))->assertForbidden();
    $this->actingAs(staff(SystemRole::Waiter))->get(route('courier.index'))->assertForbidden();
});

it('доставку не оплатить на кассе, самовывоз — только готовый, и тогда он «выдан»', function () {
    $delivery = phoneOrder();
    $this->actingAs(staff(SystemRole::Cashier))
        ->post(route('cashier.pay', $delivery->order_id), ['method' => 'cash'])->assertSessionHasErrors('order');

    $pickup = phoneOrder(['type' => 'pickup', 'address' => null]);
    deliveryAction($pickup, 'kitchen');
    $this->actingAs(staff(SystemRole::Cashier))
        ->post(route('cashier.pay', $pickup->order_id), ['method' => 'cash'])->assertSessionHasErrors('order');

    kitchenDone($pickup);
    $this->actingAs(staff(SystemRole::Cashier))->get(route('cashier.index'))
        ->assertInertia(fn ($p) => $p->where('queue.0.id', $pickup->order_id)->where('queue.0.channel', 'pickup'));
    $this->actingAs(staff(SystemRole::Cashier))
        ->post(route('cashier.pay', $pickup->order_id), ['method' => 'card'])->assertSessionHasNoErrors();

    expect($pickup->fresh()->status)->toBe(DeliveryStatus::Issued);
});

it('отмена с причиной снимает заказ с кухни и пишется в журнал', function () {
    $d = phoneOrder();
    deliveryAction($d, 'kitchen');

    deliveryAction($d, 'cancel')->assertSessionHasErrors('reason');
    deliveryAction($d, 'cancel', ['reason' => 'Гость передумал'])->assertSessionHasNoErrors();

    expect($d->fresh()->status)->toBe(DeliveryStatus::Cancelled)
        ->and($d->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($d->order->items()->where('status', 'sent')->exists())->toBeFalse()
        ->and(ActionLog::query()->where('action', 'order_cancelled')->exists())->toBeTrue();
});

it('стоимость доставки меняется в заказе и пишется в журнал', function () {
    $d = phoneOrder();

    deliveryAction($d, 'fee', ['delivery_fee' => '20'])->assertSessionHasNoErrors();

    expect($d->order->fresh()->delivery_fee)->toBe(2000)
        ->and($d->order->fresh()->total)->toBe(22000)
        ->and(ActionLog::query()->where('action', 'delivery_fee_changed')->exists())->toBeTrue();
});

it('официант не видит заказы доставки и не может их открыть', function () {
    $d = phoneOrder();
    $waiter = staff(SystemRole::Waiter);

    $this->actingAs($waiter)->get(route('waiter.index'))
        ->assertInertia(fn ($p) => $p->where('orders', fn ($orders) => collect($orders)->doesntContain('id', $d->order_id)));
    $this->actingAs($waiter)->get(route('waiter.order', $d->order_id))->assertForbidden();
});

it('отчёт «Выручка» делит выручку по каналам', function () {
    $d = phoneOrder();
    deliveryAction($d, 'kitchen');
    kitchenDone($d);
    deliveryAction($d, 'pick_up', ['courier_id' => null]); // без курьера нельзя
    expect($d->fresh()->status)->toBe(DeliveryStatus::Ready);

    deliveryAction($d, 'courier', ['courier_id' => staff(SystemRole::Courier)->id]);
    deliveryAction($d, 'pick_up');
    $this->actingAs(staff(SystemRole::HallAdmin))->post(route('deliveries.complete', $d), ['method' => 'cash']);

    [$from, $to] = app(ReportService::class)->period(null, null);
    expect(app(ReportService::class)->channels($from, $to))->toBe(['hall' => 0, 'delivery' => 21500, 'pickup' => 0]);
});
