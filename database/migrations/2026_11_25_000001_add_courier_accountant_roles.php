<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Роли «Курьер» и «Бухгалтер» (ТЗ 1.3) и право «Отзывы» для администратора зала
 * в уже работающей базе. На чистой базе роли создаёт RoleSeeder — тогда здесь нечего делать.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('roles')->where('slug', 'owner')->exists()) {
            return;
        }

        $roles = [
            'courier' => ['Kurýer', 'Курьер', []],
            'accountant' => ['Buhgalter', 'Бухгалтер', ['reports']],
        ];

        foreach ($roles as $slug => [$tm, $ru, $permissions]) {
            if (DB::table('roles')->where('slug', $slug)->exists()) {
                continue;
            }

            $id = DB::table('roles')->insertGetId([
                'slug' => $slug, 'name_tm' => $tm, 'name_ru' => $ru, 'is_system' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => $permission]);
            }
        }

        $hallAdmin = DB::table('roles')->where('slug', 'hall_admin')->value('id');

        if ($hallAdmin !== null) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $hallAdmin, 'permission' => 'reviews']);
        }
    }

    public function down(): void
    {
        $ids = DB::table('roles')->whereIn('slug', ['courier', 'accountant'])->pluck('id');

        // Роль с сотрудниками не удаляем — как и в интерфейсе.
        $free = $ids->reject(fn ($id) => DB::table('users')->where('role_id', $id)->exists());
        DB::table('roles')->whereIn('id', $free)->delete();
        DB::table('role_permissions')->where('permission', 'reviews')->delete();
    }
};
