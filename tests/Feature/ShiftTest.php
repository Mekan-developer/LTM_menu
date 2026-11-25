<?php

use App\Enums\SystemRole;
use App\Models\ActionLog;
use App\Models\Attendance;
use App\Models\Delivery;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Shift;
use App\Services\SettingsService;
use App\Services\ShiftService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Carbon::setTestNow('2026-12-07 08:55'); // понедельник
    $this->admin = staff(SystemRole::HallAdmin);
    $this->actingAs($this->admin);
});

it('добавляет смену; ночная смена заканчивается на следующий день; пересечения нельзя', function () {
    $waiter = staff(SystemRole::Waiter);

    $this->post(route('shifts.store'), ['user_id' => $waiter->id, 'date' => '2026-12-07', 'start' => '18:00', 'end' => '02:00'])
        ->assertSessionHasNoErrors();
    $shift = Shift::sole();
    expect($shift->ends_at->toDateTimeString())->toBe('2026-12-08 02:00:00')
        ->and($shift->branch_id)->not->toBeNull();

    $this->post(route('shifts.store'), ['user_id' => $waiter->id, 'date' => '2026-12-07', 'start' => '20:00', 'end' => '23:00'])
        ->assertSessionHasErrors('start');
});

it('шаблон 2/2 заполняет период, занятые дни не трогает', function () {
    $cook = staff(SystemRole::Cook);
    $this->post(route('shifts.store'), ['user_id' => $cook->id, 'date' => '2026-12-07', 'start' => '10:00', 'end' => '16:00']);

    $this->post(route('shifts.pattern'), [
        'user_id' => $cook->id, 'from' => '2026-12-07', 'until' => '2026-12-14', 'work' => 2, 'off' => 2, 'start' => '09:00', 'end' => '21:00',
    ])->assertSessionHasNoErrors();

    // 7,8 — работа (7-е уже было), 9,10 — выходные, 11,12 — работа, 13,14 — выходные
    expect(Shift::where('user_id', $cook->id)->orderBy('starts_at')->get()->map(fn ($s) => $s->starts_at->format('d H:i'))->all())
        ->toBe(['07 10:00', '08 09:00', '11 09:00', '12 09:00']);
});

it('копирует прошлую неделю на текущую', function () {
    $waiter = staff(SystemRole::Waiter);
    Shift::create(['branch_id' => 1, 'user_id' => $waiter->id, 'starts_at' => '2026-12-01 09:00', 'ends_at' => '2026-12-01 18:00']);
    Shift::create(['branch_id' => 1, 'user_id' => $waiter->id, 'starts_at' => '2026-12-03 09:00', 'ends_at' => '2026-12-03 18:00']);

    $this->post(route('shifts.copy-week'), ['week' => '2026-12-07'])->assertSessionHasNoErrors();
    $this->post(route('shifts.copy-week'), ['week' => '2026-12-07']); // повтор — без дублей

    expect(Shift::where('starts_at', '>=', '2026-12-07')->pluck('starts_at')->map->format('Y-m-d')->all())
        ->toBe(['2026-12-08', '2026-12-10']);
});

it('отметка по PIN на планшете: приход с опозданием, потом уход; вход в систему не происходит', function () {
    auth()->logout();
    $waiter = staff(SystemRole::Waiter, ['pin' => Hash::make('1234')]);
    Shift::create(['branch_id' => 1, 'user_id' => $waiter->id, 'starts_at' => '2026-12-07 09:00', 'ends_at' => '2026-12-07 21:00']);

    Carbon::setTestNow('2026-12-07 09:12');
    $this->post(route('pin.clock'), ['user_id' => $waiter->id, 'pin' => '0000'])->assertSessionHasErrors('pin');
    $this->post(route('pin.clock'), ['user_id' => $waiter->id, 'pin' => '1234'])
        ->assertRedirect(route('pin'))->assertSessionHas('success', fn ($m) => str_contains($m, '12'));
    $this->assertGuest();

    Carbon::setTestNow('2026-12-07 20:30');
    $this->post(route('pin.clock'), ['user_id' => $waiter->id, 'pin' => '1234'])->assertSessionHasNoErrors();

    $a = Attendance::sole();
    expect($a->shift_id)->not->toBeNull()
        ->and($a->minutes())->toBe(678)
        ->and(app(ShiftService::class)->lateMinutes($a->shift, $a))->toBe(12)
        ->and(app(ShiftService::class)->earlyMinutes($a->shift, $a))->toBe(30);
});

it('исправление отметки — с причиной и в журнал; можно внести забытую', function () {
    $cook = staff(SystemRole::Cook);

    $this->post(route('shifts.attendance'), ['user_id' => $cook->id, 'clock_in' => '2026-12-06T09:00', 'clock_out' => '2026-12-06T18:00'])
        ->assertSessionHasErrors('reason');
    $this->post(route('shifts.attendance'), [
        'user_id' => $cook->id, 'clock_in' => '2026-12-06T09:00', 'clock_out' => '2026-12-06T18:00', 'reason' => 'забыл отметиться',
    ])->assertSessionHasNoErrors();

    $a = Attendance::sole();
    $this->post(route('shifts.attendance', $a), [
        'user_id' => $cook->id, 'clock_in' => '2026-12-06T09:30', 'clock_out' => '2026-12-06T18:00', 'reason' => 'пришёл позже',
    ])->assertSessionHasNoErrors();

    expect($a->fresh()->clock_in->format('H:i'))->toBe('09:30')
        ->and($a->fresh()->corrected_by)->toBe($this->admin->id)
        ->and(ActionLog::where('action', 'attendance_corrected')->count())->toBe(2);
});

it('табель: план, факт, опоздания и пропуски', function () {
    $waiter = staff(SystemRole::Waiter);
    $s1 = Shift::create(['branch_id' => 1, 'user_id' => $waiter->id, 'starts_at' => '2026-12-01 09:00', 'ends_at' => '2026-12-01 17:00']);
    Shift::create(['branch_id' => 1, 'user_id' => $waiter->id, 'starts_at' => '2026-12-02 09:00', 'ends_at' => '2026-12-02 17:00']); // пропуск
    Attendance::create(['branch_id' => 1, 'user_id' => $waiter->id, 'shift_id' => $s1->id, 'clock_in' => '2026-12-01 09:20', 'clock_out' => '2026-12-01 17:00']);

    $this->get(route('shifts.index', ['week' => '2026-12-01', 'from' => '2026-12-01', 'to' => '2026-12-31']))
        ->assertInertia(fn ($page) => $page->component('Shifts/Index')
            ->has('shifts', 2)
            ->where('shifts.0.late', 20)
            ->where('shifts.1.missed', true)
            ->where('timesheet.rows.0.planned_minutes', 960)
            ->where('timesheet.rows.0.worked_minutes', 460)
            ->where('timesheet.rows.0.late', 1)
            ->where('timesheet.rows.0.missed', 1));
});

it('с правилом «только после отметки» официант не открывает новый стол, курьер не получает заказ', function () {
    app(SettingsService::class)->update(['shift_clock_required' => true]);
    $waiter = staff(SystemRole::Waiter);
    $table = DiningTable::factory()->create();

    $this->actingAs($waiter)->post(route('waiter.open', $table))->assertSessionHasErrors('table');

    Attendance::create(['branch_id' => 1, 'user_id' => $waiter->id, 'clock_in' => now()]);
    $this->actingAs($waiter)->post(route('waiter.open', $table))->assertSessionHasNoErrors();

    $courier = staff(SystemRole::Courier);
    $dish = Dish::factory()->create();
    $this->actingAs($this->admin)->post(route('deliveries.store'), [
        'type' => 'delivery', 'name' => 'Merdan', 'phone' => '61234567', 'address' => 'Köşi 5', 'method' => 'cash',
        'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
    ]);
    $this->post(route('deliveries.action', Delivery::sole()), ['action' => 'courier', 'courier_id' => $courier->id])
        ->assertSessionHasErrors('courier_id');
});

it('сотрудник видит свой график; без права «Смены» общий график закрыт', function () {
    $waiter = staff(SystemRole::Waiter);
    Shift::create(['branch_id' => 1, 'user_id' => $waiter->id, 'starts_at' => '2026-12-08 09:00', 'ends_at' => '2026-12-08 18:00']);

    $this->actingAs($waiter)->get(route('my-shifts'))
        ->assertInertia(fn ($page) => $page->component('Shifts/Mine')->has('upcoming', 1));
    $this->actingAs($waiter)->get(route('shifts.index'))->assertForbidden();
});
