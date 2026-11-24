<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Столы получают место на схеме зала. Текстовое поле hall превращается в залы:
 * каждый уникальный текст — зал, пустое значение — «Основной зал». Столы расставляются
 * сеткой с шагом 120 px, чтобы не пересекались. Колонка hall удаляется.
 */
return new class extends Migration
{
    public const STEP = 120;

    public const MARGIN = 40;

    public const DEFAULT_NAME = ['Esasy zal', 'Основной зал'];

    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->foreignId('hall_id')->nullable()->after('number')->constrained()->restrictOnDelete();
            $table->string('shape', 8)->default('square');
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->unsignedSmallInteger('width')->default(80);
            $table->unsignedSmallInteger('height')->default(80);
            $table->unsignedSmallInteger('rotation')->default(0); // 0 | 90
        });

        $this->moveTablesIntoHalls();

        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn('hall');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->string('hall', 64)->nullable()->after('number');
        });

        $names = DB::table('halls')->pluck('name_tm', 'id');
        foreach (DB::table('dining_tables')->whereNotNull('hall_id')->get(['id', 'hall_id']) as $t) {
            $name = $names[$t->hall_id] ?? null;
            DB::table('dining_tables')->where('id', $t->id)->update([
                'hall' => $name === self::DEFAULT_NAME[0] ? null : $name,
            ]);
        }

        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropForeign(['hall_id']);
            $table->dropColumn(['hall_id', 'shape', 'pos_x', 'pos_y', 'width', 'height', 'rotation']);
        });
    }

    private function moveTablesIntoHalls(): void
    {
        $tables = DB::table('dining_tables')->orderBy('sort')->orderBy('id')->get(['id', 'hall', 'seats']);

        if ($tables->isEmpty()) {
            return;
        }

        $groups = $tables->groupBy(fn ($t) => trim((string) $t->hall));
        $sort = 0;

        foreach ($groups as $name => $group) {
            [$tm, $ru] = $name === '' ? self::DEFAULT_NAME : [$name, $name];
            $width = 1000;
            $cols = intdiv($width - 2 * self::MARGIN, self::STEP);
            $rows = (int) ceil($group->count() / $cols);

            $hallId = DB::table('halls')->insertGetId([
                'name_tm' => mb_substr($tm, 0, 64),
                'name_ru' => mb_substr($ru, 0, 64),
                'sort' => $sort++,
                'width' => $width,
                'height' => max(640, 2 * self::MARGIN + $rows * self::STEP),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($group->values() as $i => $t) {
                // 6 мест и больше — длинный стол, 2 и меньше — круглый, остальные квадратные.
                [$shape, $w] = match (true) {
                    $t->seats >= 6 => ['rect', 100],
                    $t->seats <= 2 => ['round', 80],
                    default => ['square', 80],
                };

                DB::table('dining_tables')->where('id', $t->id)->update([
                    'hall_id' => $hallId,
                    'shape' => $shape,
                    'pos_x' => self::MARGIN + ($i % $cols) * self::STEP,
                    'pos_y' => self::MARGIN + intdiv($i, $cols) * self::STEP,
                    'width' => $w,
                    'height' => 80,
                ]);
            }
        }
    }
};
