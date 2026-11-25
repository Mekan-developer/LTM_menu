<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Тип продукции (своя / готовая) и акционная цена блюда с необязательным периодом.
 * Все блюда в работающей базе считаем своими — менеджер отметит готовые сам.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->string('kind', 8)->default('made')->after('category_id')->index();
            $table->unsignedInteger('sale_price')->nullable()->after('price'); // в тенге, меньше обычной цены
            $table->date('sale_from')->nullable()->after('sale_price');
            $table->date('sale_until')->nullable()->after('sale_from');
        });
    }

    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'sale_price', 'sale_from', 'sale_until']);
        });
    }
};
