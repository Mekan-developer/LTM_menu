<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /** «/» — сразу на рабочий экран сотрудника. */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($this->auth->homeRouteFor($request->user()));
    }

    public function noAccess(): Response
    {
        return Inertia::render('NoAccess');
    }
}
