<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Отзывы (ТЗ 2.9): одноразовая ссылка — один счёт, один отзыв. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 40)->unique();
            $table->unsignedTinyInteger('rating')->nullable(); // 1–5, null — ещё не оценили
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('seen_at')->nullable(); // низкую оценку посмотрели в админке
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
