<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OwnerSeeder::class,
        ]);

        // Демо-меню, столы и сотрудники с PIN — только для локальной разработки и показа.
        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
