<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UserRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\StaffRepository;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Сотрудники: завести, заблокировать, сбросить пароль/PIN — право «Сотрудники и права». */
class UserController extends Controller
{
    public function __construct(
        private readonly StaffRepository $staff,
        private readonly StaffService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Staff/Index', [
            'users' => UserResource::collection($this->staff->users()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Staff/Form', [
            'user' => null,
            'roles' => RoleResource::collection($this->staff->roles()),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->service->createUser($request->validated(), $request->user());

        return redirect()->route('staff.index')->with('success', __('crm.saved'));
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Staff/Form', [
            'user' => new UserResource($user),
            'roles' => RoleResource::collection($this->staff->roles()),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->service->updateUser($user, $request->validated(), $request->user());

        return redirect()->route('staff.index')->with('success', __('crm.saved'));
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        $user = $this->service->toggleActive($user, $request->user());

        return back()->with('success', __($user->is_active ? 'crm.staff.unblocked' : 'crm.staff.blocked'));
    }
}
