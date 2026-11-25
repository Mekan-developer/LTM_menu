<?php

use App\Enums\DishKind;
use App\Enums\SystemRole;
use App\Models\ActionLog;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;

function dishForm(Dish|Category $target, array $override = []): array
{
    $category = $target instanceof Category ? $target : $target->category;

    return [
        'category_id' => $category->id,
        'kind' => 'made',
        'name_tm' => 'Palaw',
        'name_ru' => 'Плов',
        'price' => '50',
        'station' => 'kitchen',
        'is_active' => true,
        ...$override,
    ];
}

it('акционная цена действует внутри периода, границы включительно', function () {
    Carbon::setTestNow('2026-12-10 12:00');
    $dish = Dish::factory()->make(['price' => 5000, 'sale_price' => 4000]);

    expect($dish->currentPrice())->toBe(4000); // без дат — действует всегда

    $dish->sale_from = Carbon::parse('2026-12-10');
    $dish->sale_until = Carbon::parse('2026-12-10');
    expect($dish->onSale())->toBeTrue();

    $dish->sale_from = Carbon::parse('2026-12-11');
    expect($dish->onSale())->toBeFalse()->and($dish->currentPrice())->toBe(5000);

    $dish->sale_from = null;
    $dish->sale_until = Carbon::parse('2026-12-09');
    expect($dish->onSale())->toBeFalse();

    // Акция не дороже обычной цены.
    $dish->sale_until = null;
    $dish->sale_price = 5000;
    expect($dish->onSale())->toBeFalse();
});

it('сохраняет тип продукции и акцию; изменение акции пишется в журнал', function () {
    $this->actingAs(staff(SystemRole::Owner));
    $category = Category::factory()->create();

    $this->post(route('menu.dishes.store'), dishForm($category, ['kind' => 'resale', 'name_ru' => 'Пепси']))
        ->assertSessionHasNoErrors();
    $dish = Dish::sole();
    expect($dish->kind)->toBe(DishKind::Resale)->and($dish->sale_price)->toBeNull();

    $this->put(route('menu.dishes.update', $dish), dishForm($dish, [
        'kind' => 'resale', 'sale_price' => '39,50', 'sale_from' => '2026-12-01', 'sale_until' => '2026-12-07',
    ]))->assertSessionHasNoErrors();

    $dish->refresh();
    expect($dish->sale_price)->toBe(3950)
        ->and($dish->sale_from->toDateString())->toBe('2026-12-01')
        ->and(ActionLog::where('action', 'sale_changed')->sole()->payload['new'])
        ->toBe(['price' => 3950, 'from' => '2026-12-01', 'until' => '2026-12-07']);

    // Без акционной цены даты не храним.
    $this->put(route('menu.dishes.update', $dish), dishForm($dish, ['sale_price' => '', 'sale_from' => '2026-12-01']))
        ->assertSessionHasNoErrors();
    expect($dish->fresh()->sale_price)->toBeNull()->and($dish->fresh()->sale_from)->toBeNull();
});

it('акция должна быть дешевле обычной цены, а конец — не раньше начала', function () {
    $this->actingAs(staff(SystemRole::Owner));
    $dish = Dish::factory()->create();

    $this->put(route('menu.dishes.update', $dish), dishForm($dish, ['price' => '50', 'sale_price' => '50']))
        ->assertSessionHasErrors('sale_price');
    $this->put(route('menu.dishes.update', $dish), dishForm($dish, [
        'sale_price' => '40', 'sale_from' => '2026-12-07', 'sale_until' => '2026-12-01',
    ]))->assertSessionHasErrors('sale_until');
    $this->put(route('menu.dishes.update', $dish), dishForm($dish, ['kind' => 'other']))
        ->assertSessionHasErrors('kind');
});

it('официант пробивает блюдо по акционной цене', function () {
    $waiter = staff(SystemRole::Waiter);
    $table = DiningTable::factory()->create();
    $dish = Dish::factory()->create(['price' => 9500, 'sale_price' => 7000]);

    $this->actingAs($waiter)->post(route('waiter.open', $table));
    $this->post(route('waiter.items.store', Order::sole()), ['items' => [['dish_id' => $dish->id, 'quantity' => 2]]])
        ->assertSessionHasNoErrors();

    expect(OrderItem::sole()->price)->toBe(7000);
});

it('гость видит акционную и старую цену; заказ по старой цене получает сообщение', function () {
    $table = DiningTable::factory()->create();
    $dish = Dish::factory()->create(['price' => 9000, 'sale_price' => 7500]);

    $this->get(route('guest.menu', $table->qr_token))
        ->assertInertia(fn ($page) => $page
            ->where('menu.0.dishes.0.price', 7500)
            ->where('menu.0.dishes.0.old_price', 9000));

    $this->postJson(route('guest.orders.store', $table->qr_token), [
        'items' => [['dish_id' => $dish->id, 'quantity' => 1, 'price' => 9000]],
    ])->assertStatus(409)->assertJsonPath('problems.0.new', 7500);

    $this->postJson(route('guest.orders.store', $table->qr_token), [
        'items' => [['dish_id' => $dish->id, 'quantity' => 1, 'price' => 7500]],
    ])->assertCreated();

    expect(OrderItem::sole()->price)->toBe(7500);
});
