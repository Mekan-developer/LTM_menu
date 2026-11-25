<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Право «Все заказы» в уже работающей базе: администратору зала и бухгалтеру. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['hall_admin', 'accountant'] as $slug) {
            $role = DB::table('roles')->where('slug', $slug)->value('id');

            if ($role !== null) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission' => 'orders']);
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'orders')->delete();
    }
};
