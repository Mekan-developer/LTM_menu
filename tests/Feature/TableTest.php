<?php

use App\Enums\BookingStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Models\Booking;
use App\Models\DiningTable;
use App\Models\Guest;
use App\Models\Hall;
use App\Models\Order;
use App\Services\HallService;
use Illuminate\Support\Str;

function orderOn(DiningTable $table, OrderStatus $status = OrderStatus::Paid): Order
{
    return Order::create([
        'public_token' => Str::random(32),
        'dining_table_id' => $table->id,
        'source' => OrderSource::Waiter->value,
        'status' => $status->value,
        'opened_at' => now()->subHour(),
        'total' => 5000,
    ]);
}

beforeEach(function () {
    $this->actingAs(staff(SystemRole::Owner));
    $this->hall = Hall::factory()->create();
});

it('удалённый стол с историей пропадает из списка и печати, а его QR не работает', function () {
    $kept = DiningTable::factory()->create(['hall_id' => $this->hall->id, 'number' => '1']);
    $gone = DiningTable::factory()->create(['hall_id' => $this->hall->id, 'number' => '2']);
    $order = orderOn($gone);

    $this->delete(route('tables.destroy', $gone))->assertSessionHasNoErrors();

    expect(DiningTable::pluck('id')->all())->toBe([$kept->id])
        ->and(DiningTable::withTrashed()->find($gone->id)?->trashed())->toBeTrue()
        // В истории заказа номер стола остаётся.
        ->and($order->fresh()->table?->number)->toBe('2');

    $this->get(route('tables.index'))
        ->assertInertia(fn ($page) => $page->has('tables', 1)->where('tables.0.id', $kept->id));
    $this->get(route('tables.print'))
        ->assertInertia(fn ($page) => $page->has('tables', 1));
    $this->get(route('guest.menu', $gone->qr_token))->assertNotFound();
});

it('стол без истории удаляется насовсем', function () {
    $table = DiningTable::factory()->create(['hall_id' => $this->hall->id]);

    $this->delete(route('tables.destroy', $table))->assertSessionHasNoErrors();

    expect(DiningTable::withTrashed()->find($table->id))->toBeNull();
});

it('стол с открытым заказом удалить нельзя', function () {
    $table = DiningTable::factory()->create(['hall_id' => $this->hall->id]);
    orderOn($table, OrderStatus::Open);

    $this->delete(route('tables.destroy', $table))->assertSessionHasErrors('table');

    expect(DiningTable::find($table->id))->not->toBeNull();
});

it('номер удалённого стола можно занять снова', function () {
    $old = DiningTable::factory()->create(['hall_id' => $this->hall->id, 'number' => '9']);
    orderOn($old);
    $this->delete(route('tables.destroy', $old));

    $this->post(route('tables.store'), ['number' => '9', 'hall_id' => $this->hall->id, 'seats' => 4])
        ->assertSessionHasNoErrors();

    expect(DiningTable::where('number', '9')->count())->toBe(1)
        ->and(DiningTable::withTrashed()->where('number', '9')->count())->toBe(2);
});

it('будущая бронь удалённого стола остаётся без стола', function () {
    $table = DiningTable::factory()->create(['hall_id' => $this->hall->id]);
    $booking = Booking::create([
        'guest_id' => Guest::factory()->create()->id,
        'dining_table_id' => $table->id,
        'status' => BookingStatus::Confirmed->value,
        'source' => 'staff',
        'guests_count' => 2,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
    ]);

    $this->delete(route('tables.destroy', $table))->assertSessionHasNoErrors();

    expect($booking->fresh()->dining_table_id)->toBeNull();
});

it('стол, убранный со схемы зала, удаляется мягко', function () {
    $kept = DiningTable::factory()->create([
        'hall_id' => $this->hall->id, 'number' => '1', 'pos_x' => 0, 'pos_y' => 0, 'width' => 80, 'height' => 80, 'rotation' => 0,
    ]);
    $gone = DiningTable::factory()->create(['hall_id' => $this->hall->id, 'number' => '2']);
    orderOn($gone);

    $this->put(route('halls.layout', $this->hall), [
        'revision' => app(HallService::class)->board($this->hall, auth()->user())['revision'],
        'tables' => [[
            'id' => $kept->id, 'number' => $kept->number, 'seats' => $kept->seats, 'shape' => $kept->shape->value,
            'pos_x' => $kept->pos_x, 'pos_y' => $kept->pos_y, 'width' => $kept->width, 'height' => $kept->height,
            'rotation' => $kept->rotation,
        ]],
        'elements' => [],
    ])->assertSessionHasNoErrors();

    expect(DiningTable::pluck('id')->all())->toBe([$kept->id])
        ->and(DiningTable::withTrashed()->find($gone->id)?->trashed())->toBeTrue();
});
