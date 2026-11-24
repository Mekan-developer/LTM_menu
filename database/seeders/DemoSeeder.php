<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Hall;
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

        // Два зала (ТЗ 2.2): основной с баром и входом и терраса. Столы расставлены без пересечений.
        $main = Hall::create(['name_tm' => 'Esasy zal', 'name_ru' => 'Основной зал', 'sort' => 0, 'width' => 1000, 'height' => 640]);
        $terrace = Hall::create(['name_tm' => 'Terrasa', 'name_ru' => 'Терраса', 'sort' => 1, 'width' => 800, 'height' => 480]);

        $main->elements()->createMany([
            ['type' => 'bar', 'label_tm' => 'Bar', 'label_ru' => 'Бар', 'pos_x' => 40, 'pos_y' => 40, 'width' => 280, 'height' => 60],
            ['type' => 'entrance', 'label_tm' => 'Giriş', 'label_ru' => 'Вход', 'pos_x' => 440, 'pos_y' => 600, 'width' => 120, 'height' => 40],
            ['type' => 'window', 'pos_x' => 960, 'pos_y' => 120, 'width' => 20, 'height' => 400],
        ]);
        $terrace->elements()->create(['type' => 'entrance', 'label_tm' => 'Giriş', 'label_ru' => 'Вход', 'pos_x' => 0, 'pos_y' => 200, 'width' => 40, 'height' => 100]);

        // [номер, зал, места, форма, x, y, ширина, высота]
        $layout = [
            [1, $main, 2, 'round', 400, 40, 80, 80], [2, $main, 2, 'round', 520, 40, 80, 80],
            [3, $main, 6, 'rect', 680, 40, 160, 80], [4, $main, 4, 'square', 60, 180, 80, 80],
            [5, $main, 4, 'square', 220, 180, 80, 80], [6, $main, 6, 'rect', 400, 200, 160, 80],
            [7, $main, 4, 'square', 680, 200, 80, 80], [8, $main, 4, 'square', 60, 340, 80, 80],
            [9, $main, 8, 'rect', 400, 380, 200, 100],
            [10, $terrace, 4, 'square', 120, 80, 80, 80], [11, $terrace, 4, 'square', 320, 80, 80, 80],
            [12, $terrace, 6, 'rect', 520, 280, 160, 80],
        ];

        foreach ($layout as [$n, $hall, $seats, $shape, $x, $y, $w, $h]) {
            DiningTable::create([
                'number' => (string) $n,
                'hall_id' => $hall->id,
                'seats' => $seats,
                'shape' => $shape,
                'pos_x' => $x, 'pos_y' => $y, 'width' => $w, 'height' => $h,
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
