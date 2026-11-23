<?php

namespace App\Repositories;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class StaffRepository
{
    /** @return Collection<int, User> */
    public function users(): Collection
    {
        return User::query()->with('role')->orderBy('name')->get();
    }

    /**
     * Сотрудники для PIN-экрана общего планшета.
     *
     * @return Collection<int, User>
     */
    public function pinUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->with('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role_id']);
    }

    /** @return Collection<int, Role> */
    public function roles(): Collection
    {
        return Role::query()->with('permissions')->withCount('users')
            ->orderByDesc('is_system')->orderBy('id')->get();
    }

    public function roleBySlug(SystemRole $role): Role
    {
        return Role::where('slug', $role->value)->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function createUser(array $data): User
    {
        return User::create($data);
    }

    /** @param array<string, mixed> $data */
    public function updateUser(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    /** @param array<string, mixed> $data */
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
