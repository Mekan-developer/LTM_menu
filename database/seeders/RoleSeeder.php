<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

/** Системные роли и права по умолчанию (ТЗ 1.3). Повторный запуск права не затирает. */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SystemRole::cases() as $systemRole) {
            $role = Role::firstOrCreate(
                ['slug' => $systemRole->value],
                ['name_tm' => $systemRole->nameTm(), 'name_ru' => $systemRole->nameRu(), 'is_system' => true],
            );

            if (! $role->wasRecentlyCreated) {
                continue;
            }

            foreach ($systemRole->defaultPermissions() as $permission) {
                /** @var Permission $permission */
                RolePermission::create(['role_id' => $role->id, 'permission' => $permission->value]);
            }
        }
    }
}
