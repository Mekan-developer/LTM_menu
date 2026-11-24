<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Models\ActionLog;
use App\Models\DiningTable;
use App\Models\Guest;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

function orderToPay(int $total): Order
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
        'name_tm' => 'Palaw', 'name_ru' => 'Плов', 'price' => $total, 'quantity' => 1,
        'station' => 'kitchen', 'status' => OrderItemStatus::Served->value, 'batch' => 1,
    ]);

    return $order;
}

it('администратор зала заводит гостя, телефон приводится к +993XXXXXXXX', function () {
    $this->actingAs(staff(SystemRole::HallAdmin))
        ->post(route('guests.store'), [
            'name' => 'Aýna', 'phone' => '8 65 12-34-56', 'birthday' => '1990-05-01', 'comment' => 'sogana allergiýa',
        ])
        ->assertSessionHasNoErrors();

    $guest = Guest::sole();
    expect($guest->phone)->toBe('+99365123456')
        ->and($guest->comment)->toBe('sogana allergiýa')
        ->and($guest->source)->toBe('staff');
});

it('один номер — один гость, неверный номер не принимается', function () {
    Guest::factory()->create(['phone' => '+99365123456']);
    $this->actingAs(staff(SystemRole::HallAdmin));

    $this->post(route('guests.store'), ['name' => 'Iki', 'phone' => '+993 65 123456'])
        ->assertSessionHasErrors('phone');
    $this->post(route('guests.store'), ['name' => 'Üç', 'phone' => '12345'])
        ->assertSessionHasErrors('phone');

    expect(Guest::count())->toBe(1);
});

it('ищет гостей по имени и по цифрам телефона', function () {
    Guest::factory()->create(['name' => 'Merdan Ataýew', 'phone' => '+99361111111']);
    Guest::factory()->create(['name' => 'Aýgül', 'phone' => '+99365222222']);
    $this->actingAs(staff(SystemRole::HallAdmin));

    $this->get(route('guests.index', ['q' => 'merdan']))
        ->assertInertia(fn ($p) => $p->where('guests.total', 1)->where('guests.data.0.name', 'Merdan Ataýew'));
    $this->get(route('guests.index', ['q' => '65 222']))
        ->assertInertia(fn ($p) => $p->where('guests.total', 1)->where('guests.data.0.name', 'Aýgül'));
});

it('кассир привязывает счёт к гостю по телефону — в карточке визиты и сумма покупок', function () {
    $cashier = staff(SystemRole::Cashier);
    $this->actingAs($cashier)->post(route('cashier.pay', orderToPay(10000)), [
        'method' => 'cash', 'guest_phone' => '65 33 44 55', 'guest_name' => 'Jeren',
    ])->assertSessionHasNoErrors();
    $this->post(route('cashier.pay', orderToPay(30000)), ['method' => 'card', 'guest_phone' => '+99365334455']);

    $guest = Guest::sole();
    expect($guest->name)->toBe('Jeren')->and($guest->source)->toBe('cashier');

    // Отменённая оплата не входит в сумму покупок.
    $this->actingAs(staff(SystemRole::Owner))
        ->post(route('cashier.cancel', Payment::latest('id')->first()), ['reason' => 'ошибка кассира']);

    $this->actingAs(staff(SystemRole::HallAdmin))
        ->get(route('guests.show', $guest))
        ->assertInertia(fn ($p) => $p->where('guest.visits', 1)
            ->where('guest.spent', 10000)
            ->has('orders', 2));
});

it('неверный телефон гостя на кассе не даёт оплатить', function () {
    $order = orderToPay(10000);

    $this->actingAs(staff(SystemRole::Cashier))
        ->post(route('cashier.pay', $order), ['method' => 'cash', 'guest_phone' => '123'])
        ->assertSessionHasErrors('guest_phone');

    expect($order->fresh()->status)->toBe(OrderStatus::BillRequested);
});

it('раздел гостей закрыт официанту, экспорт — только с правом экспорта', function () {
    $this->actingAs(staff(SystemRole::Waiter))->get(route('guests.index'))->assertForbidden();
    $this->actingAs(staff(SystemRole::HallAdmin))->get(route('guests.export'))->assertForbidden();
});

it('владелец выгружает базу гостей в Excel, выгрузка пишется в журнал', function () {
    Guest::factory()->count(3)->create();

    $response = $this->actingAs(staff(SystemRole::Owner))->get(route('guests.export'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect(substr($response->getContent(), 0, 2))->toBe('PK')
        ->and(ActionLog::where('action', 'guests_exported')->sole()->payload)->toMatchArray(['count' => 3]);
});

it('после удаления гостя его заказы остаются', function () {
    $this->actingAs(staff(SystemRole::Cashier))->post(route('cashier.pay', orderToPay(10000)), [
        'method' => 'cash', 'guest_phone' => '+99365334455',
    ]);
    $guest = Guest::sole();

    $this->actingAs(staff(SystemRole::HallAdmin))->delete(route('guests.destroy', $guest))->assertRedirect();

    expect(Guest::count())->toBe(0)
        ->and(Order::sole()->guest_id)->toBeNull()
        ->and(Order::sole()->status)->toBe(OrderStatus::Paid);
});
