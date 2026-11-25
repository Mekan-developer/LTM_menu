<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Models\User;
use App\Repositories\StaffRepository;
use App\Services\AuthService;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Вход на общем планшете в зале: тап по себе → 4-значный PIN. */
class PinLoginController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly StaffRepository $staff,
        private readonly ShiftService $shifts,
    ) {}

    public function create(): Response
    {
        $onShift = $this->shifts->onShiftUserIds();

        return Inertia::render('Auth/Pin', [
            'staff' => $this->staff->pinUsers()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role?->localizedName(),
                'on_shift' => in_array($u->id, $onShift, true),
            ]),
        ]);
    }

    /**
     * Отметка прихода или ухода на общем планшете: тот же PIN, но без входа в систему (ТЗ 2.12).
     */
    public function clock(PinLoginRequest $request): RedirectResponse
    {
        $user = User::findOrFail($request->integer('user_id'));
        $this->auth->verifyPin($user, $request->string('pin')->value());

        $attendance = $this->shifts->clock($user);
        $time = $attendance->clock_out ?? $attendance->clock_in;

        if ($attendance->clock_out !== null) {
            $minutes = $attendance->minutes();
            $message = __('crm.shifts.clocked_out', [
                'name' => $user->name, 'time' => $time->format('H:i'),
                'hours' => intdiv($minutes, 60), 'minutes' => $minutes % 60,
            ]);
        } else {
            $late = $attendance->shift ? $this->shifts->lateMinutes($attendance->shift, $attendance) : 0;
            $message = __('crm.shifts.clocked_in', ['name' => $user->name, 'time' => $time->format('H:i')])
                .($late > 0 ? ' '.__('crm.shifts.late_by', ['n' => $late]) : '');
        }

        return redirect()->route('pin')->with('success', $message);
    }

    public function store(PinLoginRequest $request): RedirectResponse
    {
        $user = User::findOrFail($request->integer('user_id'));

        $this->auth->attemptPin($user, $request->string('pin')->value());

        $request->session()->regenerate();
        $request->session()->put('shared_device', true);

        return redirect()->route($this->auth->homeRouteFor($user));
    }
}
