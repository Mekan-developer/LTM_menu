<?php

namespace App\Repositories;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class StaffRepository
{
    public function users(): Collection
    {
        return User::query()->with('role')->orderBy('name')->get();
    }

    /** Сотрудники для PIN-экрана общего планшета. */
    public function pinUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->with('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role_id']);
    }

    public function roles(): Collection
    {
        return Role::query()->with('permissions')->withCount('users')
            ->orderByDesc('is_system')->orderBy('id')->get();
    }

    public function roleBySlug(SystemRole $role): Role
    {
        return Role::where('slug', $role->value)->firstOrFail();
    }

    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function updateUser(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    public function createRole(array $data): Role
    {
        return Role::create($data);
    }

    /** @param list<string> $permissions */
    public function syncPermissions(Role $role, array $permissions): void
    {
        RolePermission::where('role_id', $role->id)->delete();

        foreach (array_unique($permissions) as $permission) {
            RolePermission::create(['role_id' => $role->id, 'permission' => $permission]);
        }

        $role->unsetRelation('permissions');
    }
}
