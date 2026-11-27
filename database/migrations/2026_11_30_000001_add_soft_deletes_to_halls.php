<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Зал, в котором остались только удалённые столы с историей, нельзя стереть: на него ссылаются
 * эти столы. Такой зал удаляется мягко — его нет на экранах, а в истории заказов название сохраняется.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('halls', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('halls', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
