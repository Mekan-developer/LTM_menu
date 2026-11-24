<?php

use App\Enums\BookingStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\SystemRole;
use App\Models\ActionLog;
use App\Models\Booking;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Guest;
use App\Models\Hall;
use App\Models\Order;
use App\Services\HallService;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-12-01 12:00:00', config('app.timezone')));
});
afterEach(fn () => Carbon::setTestNow());

function bookingData(array $override = []): array
{
    return array_merge([
        'date' => '2026-12-01', 'time' => '19:00', 'guests_count' => 4,
        'name' => 'Aýna', 'phone' => '+993 65 123456', 'confirm' => true,
    ], $override);
}

it('администратор создаёт бронь по звонку: гость в базе, стол занят на 2 часа', function () {
    $table = DiningTable::factory()->create();

    $this->actingAs(staff(SystemRole::HallAdmin))
        ->post(route('bookings.store'), bookingData(['dining_table_id' => $table->id]))
        ->assertSessionHasNoErrors();

    $booking = Booking::sole();
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->source)->toBe('staff')
        ->and($booking->ends_at->diffInMinutes($booking->starts_at, true))->toEqual(120)
        ->and($booking->starts_at->format('H:i'))->toBe('19:00')
        ->and(Guest::sole()->phone)->toBe('+99365123456')
        ->and(Guest::sole()->source)->toBe('booking');
});

it('пересекающиеся брони на один стол система не даёт', function () {
    $table = DiningTable::factory()->create();
    $other = DiningTable::factory()->create();
    $this->actingAs(staff(SystemRole::HallAdmin));
    $this->post(route('bookings.store'), bookingData(['dining_table_id' => $table->id, 'time' => '19:00']));

    // 20:00 пересекается с 19:00–21:00.
    $this->post(route('bookings.store'), bookingData(['dining_table_id' => $table->id, 'time' => '20:00', 'phone' => '+99365000001']))
        ->assertSessionHasErrors('dining_table_id');
    // Встык (21:00) и на другой стол — можно.
    $this->post(route('bookings.store'), bookingData(['dining_table_id' => $table->id, 'time' => '21:00', 'phone' => '+99365000002']))
        ->assertSessionHasNoErrors();
    $this->post(route('bookings.store'), bookingData(['dining_table_id' => $other->id, 'time' => '20:00', 'phone' => '+99365000003']))
        ->assertSessionHasNoErrors();

    // Отменённая бронь стол не держит.
    Booking::where('dining_table_id', $table->id)->orderBy('starts_at')->first()->update(['status' => 'cancelled']);
    $this->post(route('bookings.store'), bookingData(['dining_table_id' => $table->id, 'time' => '19:30', 'phone' => '+99365000004']))
        ->assertSessionHasErrors('dining_table_id'); // 19:30–21:30 задевает бронь на 21:00

    expect(Booking::count())->toBe(3);
});

it('свободные столы на время не включают уже забронированные', function () {
    $taken = DiningTable::factory()->create();
    $free = DiningTable::factory()->create();
    $this->actingAs(staff(SystemRole::HallAdmin));
    $this->post(route('bookings.store'), bookingData(['dining_table_id' => $taken->id]));

    $ids = collect($this->getJson(route('bookings.free-tables', ['date' => '2026-12-01', 'time' => '20:00']))->json())->pluck('id');

    expect($ids)->toContain($free->id)->not->toContain($taken->id);
});

it('гость бронирует на странице: новая бронь без стола и гость в базе', function () {
    $dish = Dish::factory()->create(['price' => 9500]);

    $this->post(route('guest.booking.store'), bookingData([
        'time' => '20:00', 'items' => [['dish_id' => $dish->id, 'quantity' => 2]],
    ]))->assertRedirect(route('guest.booking'));

    $booking = Booking::sole();
    expect($booking->status)->toBe(BookingStatus::New)
        ->and($booking->source)->toBe('guest')
        ->and($booking->dining_table_id)->toBeNull()
        ->and($booking->items()->sole()->quantity)->toBe(2);

    // На прошедшее время — нельзя.
    $this->post(route('guest.booking.store'), bookingData(['time' => '11:00', 'phone' => '+99365000009']))
        ->assertSessionHasErrors('time');
});

it('без онлайн-брони страница гостя закрыта', function () {
    app(SettingsService::class)->update(['online_booking' => false]);

    $this->get(route('guest.booking'))->assertNotFound();
});

it('статусы меняются по ТЗ, отмена пишется в журнал', function () {
    $this->actingAs(staff(SystemRole::HallAdmin));
    $this->post(route('bookings.store'), bookingData(['confirm' => false]));
    $booking = Booking::sole();
    expect($booking->status)->toBe(BookingStatus::New);

    $this->post(route('bookings.status', $booking), ['status' => 'confirmed'])->assertSessionHasNoErrors();
    $this->post(route('bookings.status', $booking), ['status' => 'cancelled', 'reason' => 'позвонил, не придёт'])->assertSessionHasNoErrors();
    $this->post(route('bookings.status', $booking), ['status' => 'confirmed'])->assertSessionHasErrors('status');

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and(ActionLog::where('action', 'booking_cancelled')->sole()->payload['reason'])->toBe('позвонил, не придёт');
});

it('«гость пришёл» открывает заказ гостя на столе и отправляет предзаказ на кухню', function () {
    $table = DiningTable::factory()->create();
    $dish = Dish::factory()->create(['price' => 9500]);
    $this->actingAs(staff(SystemRole::HallAdmin));
    $this->post(route('bookings.store'), bookingData([
        'dining_table_id' => $table->id, 'items' => [['dish_id' => $dish->id, 'quantity' => 2]],
    ]));
    $booking = Booking::sole();

    $this->post(route('bookings.status', $booking), ['status' => 'arrived'])->assertSessionHasNoErrors();

    $order = Order::sole();
    expect($booking->fresh()->status)->toBe(BookingStatus::Arrived)
        ->and($booking->fresh()->order_id)->toBe($order->id)
        ->and($order->dining_table_id)->toBe($table->id)
        ->and($order->guest_id)->toBe($booking->guest_id)
        ->and($order->source)->toBe(OrderSource::Booking)
        ->and($order->total)->toBe(19000)
        ->and($order->items()->sole()->status)->toBe(OrderItemStatus::Sent);
});

it('«гость пришёл» без стола или за занятый стол — нельзя', function () {
    $this->actingAs(staff(SystemRole::HallAdmin));
    $this->post(route('bookings.store'), bookingData());
    $this->post(route('bookings.status', Booking::sole()), ['status' => 'arrived'])->assertSessionHasErrors('dining_table_id');

    $table = DiningTable::factory()->create();
    Booking::sole()->update(['dining_table_id' => $table->id]);
    $this->actingAs(staff(SystemRole::Waiter))->post(route('waiter.open', $table));
    $this->actingAs(staff(SystemRole::HallAdmin))
        ->post(route('bookings.status', Booking::sole()), ['status' => 'arrived'])
        ->assertSessionHasErrors('dining_table_id');
});

it('за час до брони стол на схеме «забронирован», раньше — свободен', function () {
    $hall = Hall::factory()->create();
    $soon = DiningTable::factory()->create(['hall_id' => $hall->id]);
    $later = DiningTable::factory()->create(['hall_id' => $hall->id]);
    $admin = staff(SystemRole::HallAdmin);
    $this->actingAs($admin);
    $this->post(route('bookings.store'), bookingData(['dining_table_id' => $soon->id, 'time' => '12:40']));
    $this->post(route('bookings.store'), bookingData(['dining_table_id' => $later->id, 'time' => '14:00', 'phone' => '+99365000005']));

    $board = collect(app(HallService::class)->board($hall, $admin)['tables'])->keyBy('id');
    expect($board[$soon->id]['status'])->toBe('reserved')
        ->and($board[$soon->id]['booking']['name'])->toBe('Aýna')
        ->and($board[$later->id]['status'])->toBe('free');

    // Официант видит «забронирован», но без имени гостя.
    $row = collect(app(HallService::class)->board($hall, staff(SystemRole::Waiter))['tables'])->firstWhere('id', $soon->id);
    expect($row['status'])->toBe('reserved')->and($row['booking']['name'])->toBeNull();

    // Гость пришёл — стол занят, а не «забронирован».
    $this->post(route('bookings.status', Booking::where('dining_table_id', $soon->id)->sole()), ['status' => 'arrived']);
    $board = collect(app(HallService::class)->board($hall, $admin)['tables'])->keyBy('id');
    expect($board[$soon->id]['status'])->toBe('occupied');
});

it('брони закрыты официанту и видны на главной и в карточке гостя', function () {
    $this->actingAs(staff(SystemRole::Waiter))->get(route('bookings.index'))->assertForbidden();

    $this->actingAs(staff(SystemRole::Owner));
    $this->post(route('bookings.store'), bookingData());

    $this->get(route('dashboard'))->assertInertia(fn ($p) => $p->where('today.bookings', 1));
    $this->get(route('guests.show', Guest::sole()))->assertInertia(fn ($p) => $p->has('bookings', 1));
    $this->get(route('bookings.index', ['date' => '2026-12-01']))->assertInertia(fn ($p) => $p->has('bookings', 1));
});
