<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Демо-данные для локального показа. Названия и цены — примеры, реальное меню вносит Заказчик.
 * PIN сотрудников: 1111, 2222, 3333, 4444.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Category::exists()) {
            return;
        }

        $menu = [
            ['Gyzgyn tagamlar', 'Горячее', [
                ['Palaw', 'Плов', 'Tüwi, guzy eti, käşir, nohut', 'Рис, баранина, морковь, нут', '350 g', 9500, 'kitchen'],
                ['Dograma', 'Дограма', 'Owradylan çörek, guzy eti, çorba, sogan', 'Рубленый чурек, баранина, бульон, лук', '450 g', 8500, 'kitchen'],
                ['Manty, 5 sany', 'Манты, 5 шт', 'Guzy eti, gaýmak', 'Баранина, каймак', '300 g', 6000, 'kitchen'],
                ['Işlekli', 'Ишлекли', 'Etli we sogan', 'Пирог с мясом и луком', '1/4', 12000, 'kitchen'],
            ]],
            ['Çorbalar', 'Супы', [
                ['Unaş', 'Унаш', 'Ugra, noýba, gatyk', 'Лапша, фасоль, катык', '350 ml', 4500, 'kitchen'],
                ['Çorba', 'Чорба', 'Guzy eti, kartoşka', 'Баранина, картофель', '350 ml', 5500, 'kitchen'],
            ]],
            ['Içgiler', 'Напитки', [
                ['Gök çaý, çäýnek', 'Зелёный чай, чайник', null, null, '700 ml', 1500, 'bar'],
                ['Aýran', 'Айран', null, null, '300 ml', 2500, 'bar'],
            ]],
        ];

        foreach ($menu as $sort => [$tm, $ru, $dishes]) {
            $category = Category::create(['name_tm' => $tm, 'name_ru' => $ru, 'sort' => $sort]);

            foreach ($dishes as $i => [$nTm, $nRu, $dTm, $dRu, $weight, $price, $station]) {
                $category->dishes()->create([
                    'name_tm' => $nTm, 'name_ru' => $nRu,
                    'description_tm' => $dTm, 'description_ru' => $dRu,
                    'weight' => $weight, 'price' => $price, 'station' => $station, 'sort' => $i,
                ]);
            }
        }

        foreach (range(1, 12) as $n) {
            DiningTable::create([
                'number' => (string) $n,
                'hall' => $n > 9 ? 'Terrasa' : 'Esasy zal',
                'seats' => $n % 3 === 0 ? 6 : 4,
                'qr_token' => Str::lower(Str::random(32)),
                'sort' => $n,
            ]);
        }

        $staff = [
            ['Nurly A.', 'nurly', '1111', SystemRole::Waiter],
            ['Gülşat M.', 'gulshat', '2222', SystemRole::Waiter],
            ['Aşpez', 'cook', '3333', SystemRole::Cook],
            ['Aýgül K.', 'aygul', '4444', SystemRole::Cashier],
        ];

        foreach ($staff as [$name, $login, $pin, $role]) {
            User::create([
                'name' => $name,
                'login' => $login,
                'password' => 'password',
                'pin' => $pin,
                'role_id' => Role::where('slug', $role->value)->value('id'),
            ]);
        }
    }
}
