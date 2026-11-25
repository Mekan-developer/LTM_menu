<?php

namespace App\Http\Controllers\Shifts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shifts\AttendanceRequest;
use App\Http\Requests\Shifts\PatternRequest;
use App\Http\Requests\Shifts\ShiftRequest;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** График смен и табель (ТЗ 2.12), право «Смены». */
class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $shifts) {}

    /** Неделя: ?week=YYYY-MM-DD (любой день недели), по умолчанию текущая. Табель — за период ?from&to. */
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'week' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $monday = CarbonImmutable::parse($data['week'] ?? 'now')->startOfWeek();
        $sunday = $monday->addWeek();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : CarbonImmutable::now()->endOfMonth();

        $onShift = $this->shifts->onShiftUserIds();

        return Inertia::render('Shifts/Index', [
            'week' => $monday->toDateString(),
            'staff' => $this->shifts->staff()->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name,
                'role' => $u->role ? ['name_tm' => $u->role->name_tm, 'name_ru' => $u->role->name_ru] : null,
                'on_shift' => in_array($u->id, $onShift, true),
            ])->values(),
            'shifts' => $this->shifts->shiftsBetween($monday, $sunday)->map(fn (Shift $s) => $this->shiftRow($s))->values(),
            'unplanned' => $this->shifts->unplannedBetween($monday, $sunday)->map(fn (Attendance $a) => self::attendanceRow($a))->values(),
            'timesheet' => fn () => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'rows' => $this->shifts->timesheet($from, $to->addSecond()),
            ],
        ]);
    }

    public function store(ShiftRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $this->shifts->save(null, (int) $v['user_id'], $v['date'], $v['start'], $v['end'], $v['note'] ?? null, $request->user());

        return back()->with('success', __('crm.saved'));
    }

    public function update(ShiftRequest $request, Shift $shift): RedirectResponse
    {
        $v = $request->validated();
        $this->shifts->save($shift, (int) $v['user_id'], $v['date'], $v['start'], $v['end'], $v['note'] ?? null, $request->user());

        return back()->with('success', __('crm.saved'));
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $this->shifts->delete($shift);

        return back()->with('success', __('crm.deleted'));
    }

    public function copyWeek(Request $request): RedirectResponse
    {
        $data = $request->validate(['week' => ['required', 'date_format:Y-m-d']]);
        $n = $this->shifts->copyPreviousWeek(CarbonImmutable::parse($data['week'])->startOfWeek(), $request->user());

        return back()->with('success', __('crm.shifts.copied', ['n' => $n]));
    }

    public function pattern(PatternRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $n = $this->shifts->applyPattern(
            (int) $v['user_id'], $v['from'], $v['until'], (int) $v['work'], (int) $v['off'], $v['start'], $v['end'], $request->user(),
        );

        return back()->with('success', __('crm.shifts.created', ['n' => $n]));
    }

    /** Исправить отметку (?attendance) или внести забытую. */
    public function attendance(AttendanceRequest $request, ?Attendance $attendance = null): RedirectResponse
    {
        $v = $request->validated();
        $this->shifts->correct($attendance, (int) $v['user_id'], $v['clock_in'], $v['clock_out'] ?? null, $v['reason'], $request->user());

        return back()->with('success', __('crm.saved'));
    }

    /** @return array<string, mixed> */
    private function shiftRow(Shift $s): array
    {
        $a = $s->attendance;

        return [
            'id' => $s->id,
            'user_id' => $s->user_id,
            'date' => $s->starts_at->toDateString(),
            'start' => $s->starts_at->format('H:i'),
            'end' => $s->ends_at->format('H:i'),
            'starts_at' => $s->starts_at->toIso8601String(),
            'ends_at' => $s->ends_at->toIso8601String(),
            'note' => $s->note,
            'attendance' => $a ? self::attendanceRow($a) : null,
            'late' => $this->shifts->lateMinutes($s, $a),
            'early' => $this->shifts->earlyMinutes($s, $a),
            'missed' => $a === null && $s->ends_at->isPast(),
        ];
    }

    /** @return array<string, mixed> */
    public static function attendanceRow(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'clock_in' => $a->clock_in->toIso8601String(),
            'clock_out' => $a->clock_out?->toIso8601String(),
            'minutes' => $a->minutes(),
            'corrected' => $a->corrected_by !== null,
            'correction_reason' => $a->correction_reason,
        ];
    }
}
