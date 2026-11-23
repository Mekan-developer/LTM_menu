<?php

namespace App\Services;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use App\Repositories\StaffRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Сотрудники и роли (ТЗ 1.3). Один сотрудник = одна роль. */
class StaffService
{
    public function __construct(
        private readonly StaffRepository $staff,
        private readonly ActionLogger $logger,
    ) {}

    public function createUser(array $data, User $actor): User
    {
        $this->assertCanAssignRole((int) $data['role_id'], $actor);

        return $this->staff->createUser([
            'name' => $data['name'],
            'login' => $data['login'],
            'password' => $data['password'],
            'pin' => $data['pin'] ?? null,
            'role_id' => $data['role_id'],
            'is_active' => true,
        ]);
    }

    public function updateUser(User $user, array $data, User $actor): User
    {
        $this->assertCanEdit($user, $actor);
        $this->assertCanAssignRole((int) $data['role_id'], $actor);

        $payload = [
            'name' => $data['name'],
            'login' => $data['login'],
            'role_id' => $data['role_id'],
        ];

        if (filled($data['password'] ?? null)) {
            $payload['password'] = $data['password'];
        }

        if (filled($data['pin'] ?? null)) {
            $payload['pin'] = $data['pin'];
            $payload['pin_failed_attempts'] = 0;
            $payload['pin_locked_until'] = null;
        } elseif (! empty($data['clear_pin'])) {
            $payload['pin'] = null;
        }

        return $this->staff->updateUser($user, $payload);
    }

    public function toggleActive(User $user, User $actor): User
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages(['user' => __('crm.staff.cannot_block_self')]);
        }

        $this->assertCanEdit($user, $actor);

        return $this->staff->updateUser($user, ['is_active' => ! $user->is_active]);
    }

    /** @param list<string> $permissions */
    public function createRole(string $nameTm, string $nameRu, array $permissions, User $actor): Role
    {
        $role = $this->staff->createRole(['name_tm' => $nameTm, 'name_ru' => $nameRu, 'is_system' => false]);
        $this->staff->syncPermissions($role, $permissions);
        $this->logger->log(ActionLogger::ROLE_PERMISSIONS_CHANGED, $role, ['permissions' => $permissions], $actor);

        return $role;
    }

    /** @param list<string> $permissions */
    public function updateRole(Role $role, string $nameTm, string $nameRu, array $permissions, User $actor): Role
    {
        // У роли «Владелец» все права есть всегда и не снимаются.
        if (! $role->isOwner()) {
            $this->staff->syncPermissions($role, $permissions);
            $this->logger->log(ActionLogger::ROLE_PERMISSIONS_CHANGED, $role, ['permissions' => $permissions], $actor);
        }

        if (! $role->is_system) {
            $role->update(['name_tm' => $nameTm, 'name_ru' => $nameRu]);
        }

        return $role;
    }

    /** Роли из ТЗ удалить нельзя; роль с сотрудниками тоже. */
    public function deleteRole(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => __('crm.staff.role_system')]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => __('crm.staff.role_has_users')]);
        }

        $role->delete();
    }

    /** Пароль Владельца в интерфейсе меняет только сам Владелец; сбрасывает Исполнитель командой. */
    private function assertCanEdit(User $user, User $actor): void
    {
        if ($user->isOwner() && $user->id !== $actor->id) {
            throw new AuthorizationException(__('crm.staff.owner_protected'));
        }
    }

    private function assertCanAssignRole(int $roleId, User $actor): void
    {
        $role = Role::findOrFail($roleId);

        if ($role->slug === SystemRole::Owner->value && ! $actor->isOwner()) {
            throw new AuthorizationException(__('crm.staff.owner_protected'));
        }
    }
}
