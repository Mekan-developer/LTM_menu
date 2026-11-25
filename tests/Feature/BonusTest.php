<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Models\ActionLog;
use App\Models\BonusTransaction;
use App\Models\DiningTable;
use App\Models\Guest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\SettingsService;
use Illuminate\Support\Str;

function bonusBill(int $total = 20000, ?Guest $guest = null): Order
{
    $order = Order::create([
        'public_token' => Str::random(32),
        'dining_table_id' => DiningTable::factory()->create()->id,
        'guest_id' => $guest?->id,
        'source' => OrderSource::Waiter->value,
        'status' => OrderStatus::BillRequested->value,
        'opened_at' => now(),
    ]);
    $order->items()->create([
        'name_tm' => 'Palaw', 'name_ru' => 'Плов', 'price' => $total, 'quantity' => 1,
        'station' => 'kitchen', 'status' => OrderItemStatus::Served->value, 'batch' => 1,
    ]);

    return $order;
}

beforeEach(function () {
    app(SettingsService::class)->update([
        'bonus_enabled' => true, 'bonus_accrual_percent' => 5, 'bonus_max_spend_percent' => 50,
    ]);
    $this->actingAs(staff(SystemRole::Cashier));
});

it('начисляет N % с оплаченной деньгами суммы гостю по телефону', function () {
    $this->post(route('cashier.pay', bonusBill(20000)), [
        'method' => 'card', 'guest_phone' => '+993 61 234567', 'guest_name' => 'Merdan',
    ])->assertSessionHasNoErrors();

    $guest = Guest::sole();

    expect($guest->bonus_balance)->toBe(1000) // 5 % от 200 TMT = 10 бонусов
        ->and(Payment::sole()->bonus_accrued)->toBe(1000)
        ->and(BonusTransaction::sole()->type->value)->toBe('accrual');
});

it('частичная оплата бонусами: деньгами — остаток, начисление — только с денег', function () {
    $guest = Guest::factory()->create(['phone' => '+99361234567', 'bonus_balance' => 15000]);

    $this->post(route('cashier.pay', bonusBill(20000)), [
        'method' => 'cash', 'guest_phone' => '61234567', 'bonus_amount' => '80',
    ])->assertSessionHasNoErrors();

    $p = Payment::sole();

    expect($p->bonus_amount)->toBe(8000)
        ->and($p->total)->toBe(12000)
        ->and($p->cash_amount)->toBe(12000)
        ->and($p->bonus_accrued)->toBe(600) // 5 % от 120 TMT
        ->and($guest->fresh()->bonus_balance)->toBe(15000 - 8000 + 600)
        ->and($p->order->bonus_paid)->toBe(8000)
        ->and(ActionLog::where('action', 'bonus_spent')->sole()->payload['amount'])->toBe(8000);
});

it('весь счёт бонусами, если M = 100', function () {
    app(SettingsService::class)->update(['bonus_max_spend_percent' => 100]);
    $guest = Guest::factory()->create(['phone' => '+99361234567', 'bonus_balance' => 30000]);

    $this->post(route('cashier.pay', bonusBill(20000)), [
        'method' => 'mixed', 'guest_phone' => '61234567', 'bonus_amount' => '200',
    ])->assertSessionHasNoErrors();

    $p = Payment::sole();

    expect($p->total)->toBe(0)->and($p->cash_amount)->toBe(0)->and($p->card_amount)->toBe(0)
        ->and($p->bonus_accrued)->toBe(0)
        ->and($guest->fresh()->bonus_balance)->toBe(10000);
});

it('нельзя списать больше M % счёта, больше баланса, вместе со скидкой или без гостя', function () {
    Guest::factory()->create(['phone' => '+99361234567', 'bonus_balance' => 5000]);

    $this->post(route('cashier.pay', bonusBill(20000)), ['method' => 'card', 'guest_phone' => '61234567', 'bonus_amount' => '60'])
        ->assertSessionHasErrors('bonus_amount'); // баланс 50
    $this->post(route('cashier.pay', bonusBill(6000)), ['method' => 'card', 'guest_phone' => '61234567', 'bonus_amount' => '40'])
        ->assertSessionHasErrors('bonus_amount'); // 50 % от 60 = 30
    $this->post(route('cashier.pay', bonusBill(20000)), ['method' => 'card', 'guest_phone' => '61234567', 'bonus_amount' => '10', 'discount_percent' => 5])
        ->assertSessionHasErrors('bonus_amount'); // скидка и бонусы не суммируются
    $this->post(route('cashier.pay', bonusBill(20000)), ['method' => 'card', 'bonus_amount' => '10'])
        ->assertSessionHasErrors('bonus_amount'); // без гостя

    expect(Payment::count())->toBe(0);
});

it('программа выключена: не начисляет и не списывает, баланс сохраняется', function () {
    app(SettingsService::class)->update(['bonus_enabled' => false]);
    $guest = Guest::factory()->create(['phone' => '+99361234567', 'bonus_balance' => 5000]);

    $this->post(route('cashier.pay', bonusBill(20000)), ['method' => 'card', 'guest_phone' => '61234567', 'bonus_amount' => '10'])
        ->assertSessionHasErrors('bonus_amount');
    $this->post(route('cashier.pay', bonusBill(20000)), ['method' => 'card', 'guest_phone' => '61234567'])
        ->assertSessionHasNoErrors();

    expect($guest->fresh()->bonus_balance)->toBe(5000)->and(BonusTransaction::count())->toBe(0);
});

it('отмена оплаты снимает начисленные и возвращает списанные бонусы', function () {
    $guest = Guest::factory()->create(['phone' => '+99361234567', 'bonus_balance' => 10000]);
    $this->post(route('cashier.pay', bonusBill(20000)), ['method' => 'card', 'guest_phone' => '61234567', 'bonus_amount' => '50']);
    expect($guest->fresh()->bonus_balance)->toBe(10000 - 5000 + 750);

    $this->actingAs(staff(SystemRole::Owner))
        ->post(route('cashier.cancel', Payment::sole()), ['reason' => 'ошибка кассира'])->assertSessionHasNoErrors();

    expect($guest->fresh()->bonus_balance)->toBe(10000)
        ->and(BonusTransaction::whereIn('type', ['accrual_reversal', 'spend_reversal'])->count())->toBe(2);
});

it('гость счёта из доставки или брони получает бонусы без телефона на кассе', function () {
    $guest = Guest::factory()->create(['bonus_balance' => 0]);

    $this->post(route('cashier.pay', bonusBill(10000, $guest)), ['method' => 'cash'])->assertSessionHasNoErrors();

    expect($guest->fresh()->bonus_balance)->toBe(500);
});

it('касса подсказывает баланс гостя по телефону; карточка гостя показывает историю', function () {
    $guest = Guest::factory()->create(['phone' => '+99361234567', 'name' => 'Aýna', 'bonus_balance' => 4200]);

    $this->getJson(route('cashier.guest', ['phone' => '61 23 45 67']))
        ->assertOk()->assertJson(['name' => 'Aýna', 'balance' => 4200]);
    $this->getJson(route('cashier.guest', ['phone' => '65000000']))->assertOk()->assertExactJson([]);

    $this->post(route('cashier.pay', bonusBill(10000)), ['method' => 'cash', 'guest_phone' => '61234567']);

    $this->actingAs(staff(SystemRole::Owner))
        ->get(route('guests.show', $guest))
        ->assertInertia(fn ($page) => $page->where('guest.bonus_balance', 4700)->has('bonuses', 1));
});
