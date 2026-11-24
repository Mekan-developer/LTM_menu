<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Залы и неинтерактивные объекты схемы (ТЗ 2.2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('halls', function (Blueprint $table) {
            $table->id();
            $table->string('name_tm', 64);
            $table->string('name_ru', 64);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedSmallInteger('width')->default(1000);  // размер полотна, px
            $table->unsignedSmallInteger('height')->default(640);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hall_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // bar | entrance | window | wall | label
            $table->string('label_tm', 64)->nullable();
            $table->string('label_ru', 64)->nullable();
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->unsignedSmallInteger('width')->default(120);
            $table->unsignedSmallInteger('height')->default(40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hall_elements');
        Schema::dropIfExists('halls');
    }
};
