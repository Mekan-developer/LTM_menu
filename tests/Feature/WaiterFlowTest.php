<?php

use App\Enums\OrderItemStatus;
use App\Enums\SystemRole;
use App\Models\ActionLog;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;

function openOrderWithSentItem($waiter): OrderItem
{
    $table = DiningTable::factory()->create();
    $dish = Dish::factory()->create(['price' => 9500]);

    test()->actingAs($waiter)->post(route('waiter.open', $table));
    $order = Order::sole();

    test()->post(route('waiter.items.store', $order), ['items' => [['dish_id' => $dish->id, 'quantity' => 2]]]);
    test()->post(route('waiter.send', $order));

    return OrderItem::sole();
}

it('официант открывает стол и отправляет блюда на кухню', function () {
    $waiter = staff(SystemRole::Waiter);
    $item = openOrderWithSentItem($waiter);

    expect($item->status)->toBe(OrderItemStatus::Sent)
        ->and($item->batch)->toBe(1)
        ->and($item->order->total)->toBe(19000);
});

it('официант без права не может удалить блюдо после кухни', function () {
    $waiter = staff(SystemRole::Waiter);
    $item = openOrderWithSentItem($waiter);

    $this->actingAs($waiter)
        ->delete(route('waiter.items.destroy', $item), ['reason' => 'гость передумал'])
        ->assertForbidden();

    expect($item->fresh()->status)->toBe(OrderItemStatus::Sent);
});

it('администратор зала удаляет блюдо после кухни с причиной и это пишется в журнал', function () {
    $waiter = staff(SystemRole::Waiter);
    $admin = staff(SystemRole::HallAdmin);
    $item = openOrderWithSentItem($waiter);

    $this->actingAs($admin)
        ->delete(route('waiter.items.destroy', $item), ['reason' => 'гость передумал'])
        ->assertRedirect();

    expect($item->fresh()->status)->toBe(OrderItemStatus::Removed)
        ->and($item->order->fresh()->total)->toBe(0)
        ->and(ActionLog::where('action', 'item_removed')->count())->toBe(1);
});

it('чужой официант не видит стол коллеги', function () {
    $item = openOrderWithSentItem(staff(SystemRole::Waiter));

    $this->actingAs(staff(SystemRole::Waiter))
        ->get(route('waiter.order', $item->order_id))
        ->assertForbidden();
});

it('кухня отмечает «Готово»', function () {
    $item = openOrderWithSentItem(staff(SystemRole::Waiter));

    $this->actingAs(staff(SystemRole::Cook))
        ->post(route('kitchen.ready', [$item->order_id, 1]), ['station' => 'kitchen'])
        ->assertRedirect();

    expect($item->fresh()->status)->toBe(OrderItemStatus::Ready);
});
