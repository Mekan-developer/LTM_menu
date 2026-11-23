<?php

use App\Enums\OrderItemStatus;
use App\Enums\StopMode;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Order;
use App\Models\StopListEntry;
use App\Services\TableService;

it('принимает QR-заказ и ждёт подтверждения официанта', function () {
    $table = DiningTable::factory()->create();
    $dish = Dish::factory()->create(['price' => 8500]);

    $this->postJson(route('guest.orders.store', $table->qr_token), [
        'items' => [['dish_id' => $dish->id, 'quantity' => 2, 'price' => 8500]],
        'comment' => 'без лука',
    ])->assertCreated()->assertJsonStructure(['status_url']);

    $order = Order::with('items')->sole();

    expect($order->waiter_id)->toBeNull()
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->status)->toBe(OrderItemStatus::Pending)
        ->and($order->total)->toBe(0); // в счёт идут только подтверждённые позиции
});

it('сообщает гостю об изменившейся цене до отправки', function () {
    $table = DiningTable::factory()->create();
    $dish = Dish::factory()->create(['price' => 9000]);

    $this->postJson(route('guest.orders.store', $table->qr_token), [
        'items' => [['dish_id' => $dish->id, 'quantity' => 1, 'price' => 8500]],
    ])->assertStatus(409)
        ->assertJsonPath('problems.0.type', 'price_changed')
        ->assertJsonPath('problems.0.new', 9000);

    expect(Order::count())->toBe(0);
});

it('не принимает блюдо из стоп-листа', function () {
    $table = DiningTable::factory()->create();
    $dish = Dish::factory()->create();

    StopListEntry::create(['stoppable_type' => 'dish', 'stoppable_id' => $dish->id, 'mode' => StopMode::Manual->value]);

    $this->postJson(route('guest.orders.store', $table->qr_token), [
        'items' => [['dish_id' => $dish->id, 'quantity' => 1, 'price' => $dish->price]],
    ])->assertStatus(409)->assertJsonPath('problems.0.type', 'unavailable');
});

it('старый QR-код после перегенерации не работает', function () {
    $table = DiningTable::factory()->create();
    $old = $table->qr_token;

    app(TableService::class)->regenerateQr($table);

    $this->get(route('guest.menu', $old))->assertNotFound();
});
