<?php

namespace App\Http\Controllers\Shifts;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Shift;
use App\Services\ShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «Мой график»: каждый сотрудник видит свои смены и отработанные часы (ТЗ 2.12). */
class MyShiftsController extends Controller
{
    public function __construct(private readonly ShiftService $shifts) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::now()->startOfDay();
        $monthStart = $today->startOfMonth();

        return Inertia::render('Shifts/Mine', [
            'upcoming' => $this->shifts->shiftsBetween($today, $today->addDays(31), $user->id)
                ->map(fn (Shift $s) => [
                    'id' => $s->id,
                    'starts_at' => $s->starts_at->toIso8601String(),
                    'ends_at' => $s->ends_at->toIso8601String(),
                    'note' => $s->note,
                ])->values(),
            'month' => $this->shifts->timesheet($monthStart, $monthStart->addMonth(), $user->id)->first(),
            'recent' => Attendance::query()->where('user_id', $user->id)->latest('clock_in')->limit(10)->get()
                ->map(fn (Attendance $a) => ShiftController::attendanceRow($a))->values(),
            'onShift' => $this->shifts->openAttendance($user) !== null,
        ]);
    }
}
