<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Repositories\StaffRepository;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Матрица «роль × право» с галочками; свои роли, например «Старший официант». */
class RoleController extends Controller
{
    public function __construct(
        private readonly StaffRepository $staff,
        private readonly StaffService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Roles/Index', [
            'roles' => RoleResource::collection($this->staff->roles()),
            'permissions' => array_map(fn (Permission $p) => ['key' => $p->value, 'group' => $p->group()], Permission::cases()),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->service->createRole(
            $request->string('name_tm')->value(),
            $request->string('name_ru')->value(),
            $request->input('permissions', []),
            $request->user(),
        );

        return back()->with('success', __('crm.saved'));
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->service->updateRole(
            $role,
            $request->string('name_tm')->value(),
            $request->string('name_ru')->value(),
            $request->input('permissions', []),
            $request->user(),
        );

        return back()->with('success', __('crm.saved'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->service->deleteRole($role);

        return back()->with('success', __('crm.deleted'));
    }
}
