<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

function billableOrder(int $total = 27500): Order
{
    $order = Order::create([
        'public_token' => Str::random(32),
        'dining_table_id' => DiningTable::factory()->create()->id,
        'source' => OrderSource::Waiter->value,
        'status' => OrderStatus::BillRequested->value,
        'opened_at' => now(),
        'subtotal' => $total,
        'total' => $total,
    ]);

    $order->items()->create([
        'name_tm' => 'Dograma', 'name_ru' => 'Дограма', 'price' => $total, 'quantity' => 1,
        'station' => 'kitchen', 'status' => OrderItemStatus::Served->value, 'batch' => 1,
    ]);

    return $order;
}

it('принимает смешанную оплату: карта = остаток', function () {
    $order = billableOrder(27500);

    $this->actingAs(staff(SystemRole::Cashier))
        ->post(route('cashier.pay', $order), ['method' => 'mixed', 'cash_amount' => '100,00'])
        ->assertRedirect();

    $payment = Payment::sole();

    expect($payment->cash_amount)->toBe(10000)
        ->and($payment->card_amount)->toBe(17500)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('кассир не даёт скидку больше K %', function () {
    $order = billableOrder();

    $this->actingAs(staff(SystemRole::Cashier))
        ->post(route('cashier.pay', $order), ['method' => 'card', 'discount_percent' => 50])
        ->assertSessionHasErrors('discount_percent');

    expect(Payment::count())->toBe(0);
});

it('скидка до K % уменьшает сумму и пишется в журнал', function () {
    $order = billableOrder(20000);

    $this->actingAs(staff(SystemRole::Cashier))
        ->post(route('cashier.pay', $order), ['method' => 'cash', 'discount_percent' => 10])
        ->assertSessionHasNoErrors();

    expect(Payment::sole()->total)->toBe(18000);
    $this->assertDatabaseHas('action_logs', ['action' => 'discount_applied']);
});

it('отменить оплату может только пользователь с правом', function () {
    $order = billableOrder();
    $cashier = staff(SystemRole::Cashier);
    $this->actingAs($cashier)->post(route('cashier.pay', $order), ['method' => 'card']);
    $payment = Payment::sole();

    $this->actingAs($cashier)
        ->post(route('cashier.cancel', $payment), ['reason' => 'ошибка кассира'])
        ->assertForbidden();

    $this->actingAs(staff(SystemRole::Owner))
        ->post(route('cashier.cancel', $payment), ['reason' => 'ошибка кассира'])
        ->assertRedirect();

    expect($payment->fresh()->cancelled_at)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded);
});
