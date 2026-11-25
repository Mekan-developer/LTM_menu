<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Удалённый стол с историей заказов раньше только выключался (is_active = false)
 * и оставался в списке столов со своим QR. Теперь он удаляется мягко: пропадает
 * отовсюду, QR перестаёт работать, а в истории заказов номер стола сохраняется.
 * Номер удалённого стола можно занять снова — уникальность только среди живых.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->softDeletes();
            $table->dropUnique(['number']);
        });

        DB::statement('CREATE UNIQUE INDEX dining_tables_number_alive_unique ON dining_tables (number) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX dining_tables_number_alive_unique');
        DB::table('dining_tables')->whereNotNull('deleted_at')->update(['is_active' => false]);

        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique('number');
        });
    }
};
