<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_tm');
            $table->string('name_ru');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name_tm');
            $table->string('name_ru');
            $table->text('description_tm')->nullable();
            $table->text('description_ru')->nullable();
            $table->string('weight', 40)->nullable();
            $table->unsignedInteger('price'); // в тенге (1 TMT = 100), без дробей
            $table->string('station', 16)->default('kitchen');
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'sort']);
        });

        Schema::create('stop_list_entries', function (Blueprint $table) {
            $table->id();
            $table->morphs('stoppable'); // блюдо или целая категория
            $table->string('mode', 16);
            $table->timestamp('until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['stoppable_type', 'stoppable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stop_list_entries');
        Schema::dropIfExists('dishes');
        Schema::dropIfExists('categories');
    }
};
