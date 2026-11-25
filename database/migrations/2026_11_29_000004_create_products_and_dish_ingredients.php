<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Калькуляция блюд (ТЗ 2.3): справочник продуктов и состав блюда на одну порцию.
 * Справочник общий для всей сети (ТЗ разд. 4), поэтому без branch_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name_tm', 120);
            $table->string('name_ru', 120);
            $table->string('unit', 4); // kg | l | pcs
            $table->unsignedInteger('price')->default(0); // цена закупки за единицу, в тенге
            $table->timestamps();

            $table->index('name_ru');
        });

        Schema::create('dish_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 3); // на порцию, в единице продукта (0.100 кг = 100 г)
            $table->timestamps();

            $table->unique(['dish_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dish_ingredients');
        Schema::dropIfExists('products');
    }
};
