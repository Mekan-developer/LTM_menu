<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Models\User;
use App\Repositories\StaffRepository;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Вход на общем планшете в зале: тап по себе → 4-значный PIN. */
class PinLoginController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly StaffRepository $staff,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Pin', [
            'staff' => $this->staff->pinUsers()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role?->localizedName(),
            ]),
        ]);
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
