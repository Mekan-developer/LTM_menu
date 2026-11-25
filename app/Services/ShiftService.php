<?php

namespace App\Services;

use App\Enums\SystemRole;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Смены (ТЗ 2.12 «График смен»): график на неделю или месяц, копирование прошлой недели,
 * шаблоны вроде 2/2, отметка прихода и ухода по PIN, опоздания и ранний уход, табель.
 */
class ShiftService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActionLogger $logger,
    ) {}

    /**
     * Сотрудники для графика: все активные.
     *
     * @return Collection<int, User>
     */
    public function staff(): Collection
    {
        return User::query()->where('is_active', true)->with('role:id,slug,name_tm,name_ru')
            ->orderBy('name')->get(['id', 'name', 'role_id']);
    }

    /** @return Collection<int, Shift> */
    public function shiftsBetween(CarbonInterface $from, CarbonInterface $to, ?int $userId = null): Collection
    {
        return Shift::query()
            ->where('branch_id', Branch::currentId())
            ->where('starts_at', '>=', $from)->where('starts_at', '<', $to)
            ->when($userId, fn ($q, $id) => $q->where('user_id', $id))
            ->with('attendance')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Отметки без смены в графике (вышел не по графику) — чтобы их тоже было видно.
     *
     * @return Collection<int, Attendance>
     */
    public function unplannedBetween(CarbonInterface $from, CarbonInterface $to, ?int $userId = null): Collection
    {
        return Attendance::query()
            ->where('branch_id', Branch::currentId())
            ->whereNull('shift_id')
            ->where('clock_in', '>=', $from)->where('clock_in', '<', $to)
            ->when($userId, fn ($q, $id) => $q->where('user_id', $id))
            ->orderBy('clock_in')
            ->get();
    }

    /** Добавить или изменить смену. Конец раньше начала — смена ночная, до следующего дня. */
    public function save(?Shift $shift, int $userId, string $date, string $start, string $end, ?string $note, User $actor): Shift
    {
        [$from, $to] = $this->span($date, $start, $end);
        $this->assertNoOverlap($userId, $from, $to, $shift?->id);

        $data = ['user_id' => $userId, 'starts_at' => $from, 'ends_at' => $to, 'note' => filled($note) ? $note : null];

        if ($shift !== null) {
            $shift->update($data);

            return $shift;
        }

        return Shift::query()->create($data + ['branch_id' => Branch::currentId(), 'created_by' => $actor->id]);
    }

    public function delete(Shift $shift): void
    {
        $shift->delete();
    }

    /**
     * Скопировать график прошлой недели на эту (ТЗ 2.12). Если в этот день у сотрудника уже есть смена — пропускаем.
     */
    public function copyPreviousWeek(CarbonImmutable $monday, User $actor): int
    {
        $source = $this->shiftsBetween($monday->subWeek(), $monday);

        return DB::transaction(function () use ($source, $actor) {
            $created = 0;

            foreach ($source as $s) {
                $from = CarbonImmutable::parse($s->starts_at)->addWeek();
                $to = CarbonImmutable::parse($s->ends_at)->addWeek();

                if ($this->overlaps($s->user_id, $from, $to)) {
                    continue;
                }

                Shift::query()->create([
                    'branch_id' => $s->branch_id, 'user_id' => $s->user_id, 'starts_at' => $from, 'ends_at' => $to,
                    'note' => $s->note, 'created_by' => $actor->id,
                ]);
                $created++;
            }

            return $created;
        });
    }

    /**
     * Шаблон: N дней работы, M выходных по кругу (2/2, 5/2, сутки через трое…) с даты начала по дату конца.
     * Дни, где у сотрудника уже есть смена, не трогаем.
     */
    public function applyPattern(int $userId, string $from, string $until, int $work, int $off, string $start, string $end, User $actor): int
    {
        $day = CarbonImmutable::parse($from)->startOfDay();
        $last = CarbonImmutable::parse($until)->startOfDay();

        if ($last->lessThan($day)) {
            throw ValidationException::withMessages(['until' => __('crm.shifts.bad_period')]);
        }

        return DB::transaction(function () use ($userId, $day, $last, $work, $off, $start, $end, $actor) {
            $created = 0;

            for ($i = 0; $day->lessThanOrEqualTo($last); $i++, $day = $day->addDay()) {
                if ($i % ($work + $off) >= $work) {
                    continue; // выходной
                }

                [$s, $e] = $this->span($day->toDateString(), $start, $end);

                if ($this->overlaps($userId, $s, $e)) {
                    continue;
                }

                Shift::query()->create([
                    'branch_id' => Branch::currentId(), 'user_id' => $userId, 'starts_at' => $s, 'ends_at' => $e,
                    'created_by' => $actor->id,
                ]);
                $created++;
            }

            return $created;
        });
    }

    /**
     * Отметка на общем планшете: нет открытой — приход, есть — уход.
     * Приход привязывается к смене, которая идёт сейчас или начнётся в ближайшие 3 часа.
     */
    public function clock(User $user): Attendance
    {
        return DB::transaction(function () use ($user) {
            $open = $this->openAttendance($user, lock: true);

            if ($open !== null) {
                $open->forceFill(['clock_out' => now()])->save();

                return $open;
            }

            $shift = Shift::query()
                ->where('user_id', $user->id)
                ->where('starts_at', '<=', now()->addHours(3))
                ->where('ends_at', '>=', now())
                ->whereDoesntHave('attendance')
                ->orderBy('starts_at')
                ->first();

            return Attendance::query()->create([
                'branch_id' => Branch::currentId(),
                'user_id' => $user->id,
                'shift_id' => $shift?->id,
                'clock_in' => now(),
            ]);
        });
    }

    /**
     * Кто сейчас на смене (отметил приход и не ушёл).
     *
     * @return list<int>
     */
    public function onShiftUserIds(): array
    {
        return array_values(array_map('intval', Attendance::query()->whereNull('clock_out')->distinct()->pluck('user_id')->all()));
    }

    public function openAttendance(User $user, bool $lock = false): ?Attendance
    {
        return Attendance::query()->where('user_id', $user->id)->whereNull('clock_out')
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->latest('clock_in')->first();
    }

    /**
     * Официант и курьер получают новые столы и заказы только после отметки прихода (ТЗ 2.12).
     * Правило включается в настройках; владельца и администратора не касается.
     */
    public function assertOnShift(User $user, string $field = 'shift'): void
    {
        if (! $this->settings->get('shift_clock_required')) {
            return;
        }

        $slug = $user->role?->slug;

        if (! in_array($slug, [SystemRole::Waiter->value, SystemRole::Courier->value], true)) {
            return;
        }

        if ($this->openAttendance($user) === null) {
            throw ValidationException::withMessages([$field => __('crm.shifts.clock_in_first', ['name' => $user->name])]);
        }
    }

    /**
     * Исправить отметку (или внести забытую) — с причиной, в журнал (ТЗ 2.12).
     */
    public function correct(?Attendance $attendance, int $userId, string $clockIn, ?string $clockOut, string $reason, User $actor): Attendance
    {
        $in = CarbonImmutable::parse($clockIn, config('app.timezone'));
        $out = filled($clockOut) ? CarbonImmutable::parse($clockOut, config('app.timezone')) : null;

        if ($out !== null && $out->lessThanOrEqualTo($in)) {
            throw ValidationException::withMessages(['clock_out' => __('crm.shifts.out_before_in')]);
        }

        $before = $attendance ? [
            'in' => $attendance->clock_in->toIso8601String(), 'out' => $attendance->clock_out?->toIso8601String(),
        ] : null;

        $shift = Shift::query()->where('user_id', $userId)
            ->where('starts_at', '<=', $in->addHours(3))->where('ends_at', '>=', $in)
            ->orderBy('starts_at')->first();

        $data = [
            'user_id' => $userId,
            'shift_id' => $attendance->shift_id ?? $shift?->id,
            'clock_in' => $in,
            'clock_out' => $out,
            'corrected_by' => $actor->id,
            'correction_reason' => $reason,
        ];

        if ($attendance !== null) {
            $attendance->forceFill($data)->save();
        } else {
            $attendance = Attendance::query()->create($data + ['branch_id' => Branch::currentId()]);
        }

        $this->logger->log(ActionLogger::ATTENDANCE_CORRECTED, $attendance, [
            'name' => User::query()->whereKey($userId)->value('name'),
            'old' => $before,
            'new' => ['in' => $in->toIso8601String(), 'out' => $out?->toIso8601String()],
            'reason' => $reason,
        ], $actor);

        return $attendance;
    }

    /** Опоздание в минутах (больше порога) или 0. */
    public function lateMinutes(Shift $shift, ?Attendance $a): int
    {
        if ($a === null) {
            return 0;
        }

        $late = (int) $shift->starts_at->diffInMinutes($a->clock_in, false);

        return $late > (int) $this->settings->get('shift_grace_minutes') ? $late : 0;
    }

    /** Ранний уход в минутах (больше порога) или 0. */
    public function earlyMinutes(Shift $shift, ?Attendance $a): int
    {
        if ($a?->clock_out === null) {
            return 0;
        }

        $early = (int) $a->clock_out->diffInMinutes($shift->ends_at, false);

        return $early > (int) $this->settings->get('shift_grace_minutes') ? $early : 0;
    }

    /**
     * Табель за период: план и факт по часам, опоздания, ранние уходы, пропуски.
     *
     * @return BaseCollection<int, array{user_id: int, name: string, shifts: int, planned_minutes: int, worked_minutes: int, late: int, early: int, missed: int}>
     */
    public function timesheet(CarbonInterface $from, CarbonInterface $to, ?int $userId = null): BaseCollection
    {
        $shifts = $this->shiftsBetween($from, $to, $userId)->groupBy('user_id');
        $worked = Attendance::query()
            ->where('branch_id', Branch::currentId())
            ->where('clock_in', '>=', $from)->where('clock_in', '<', $to)
            ->when($userId, fn ($q, $id) => $q->where('user_id', $id))
            ->get()->groupBy('user_id');

        $users = User::query()->whereIn('id', $shifts->keys()->merge($worked->keys())->unique())
            ->orderBy('name')->get(['id', 'name']);

        return $users->map(function (User $u) use ($shifts, $worked) {
            /** @var Collection<int, Shift> $mine */
            $mine = $shifts->get($u->id, new Collection);
            /** @var Collection<int, Attendance> $marks */
            $marks = $worked->get($u->id, new Collection);

            return [
                'user_id' => $u->id,
                'name' => $u->name,
                'shifts' => $mine->count(),
                'planned_minutes' => (int) $mine->sum(fn (Shift $s) => $s->minutes()),
                'worked_minutes' => (int) $marks->sum(fn (Attendance $a) => $a->minutes()),
                'late' => $mine->filter(fn (Shift $s) => $this->lateMinutes($s, $s->attendance) > 0)->count(),
                'early' => $mine->filter(fn (Shift $s) => $this->earlyMinutes($s, $s->attendance) > 0)->count(),
                'missed' => $mine->filter(fn (Shift $s) => $s->attendance === null && $s->ends_at->isPast())->count(),
            ];
        })->values();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function span(string $date, string $start, string $end): array
    {
        $tz = config('app.timezone');
        $from = CarbonImmutable::parse("$date $start", $tz);
        $to = CarbonImmutable::parse("$date $end", $tz);

        if ($to->lessThanOrEqualTo($from)) {
            $to = $to->addDay(); // ночная смена
        }

        return [$from, $to];
    }

    private function overlaps(int $userId, CarbonInterface $from, CarbonInterface $to, ?int $exceptId = null): bool
    {
        return Shift::query()->where('user_id', $userId)
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->when($exceptId, fn ($q, $id) => $q->whereKeyNot($id))
            ->exists();
    }

    private function assertNoOverlap(int $userId, CarbonInterface $from, CarbonInterface $to, ?int $exceptId): void
    {
        if ($this->overlaps($userId, $from, $to, $exceptId)) {
            throw ValidationException::withMessages(['start' => __('crm.shifts.overlap')]);
        }
    }
}
