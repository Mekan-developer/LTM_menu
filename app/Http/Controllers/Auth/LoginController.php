<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $this->auth->attemptPassword(
            $request->string('login')->trim()->value(),
            $request->string('password')->value(),
            (string) $request->ip(),
            $request->boolean('remember'),
        );

        $request->session()->regenerate();
        $request->session()->put('shared_device', false);

        return redirect()->intended(route($this->auth->homeRouteFor($user)));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $shared = (bool) $request->session()->get('shared_device', false);
        $locale = $request->session()->get('locale');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($locale) {
            $request->session()->put('locale', $locale);
        }

        // С общего планшета — обратно на PIN-экран.
        return redirect()->route($shared ? 'pin' : 'login');
    }
}
