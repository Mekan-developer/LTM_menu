<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Брони и предзаказ (ТЗ 2.2). Бронь занимает стол на интервал [starts_at, ends_at). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete(); // стол назначает администратор
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('guests_count');
            $table->string('status', 16)->index();
            $table->string('source', 16); // staff | guest
            $table->text('comment')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // заказ, открытый при приходе
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamps();

            $table->index(['dining_table_id', 'starts_at']);
            $table->index('starts_at');
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_tm');   // снимок названия и цены на момент брони
            $table->string('name_ru');
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('quantity');
            $table->string('station', 16);
            $table->timestamps();
        });

        // Право «Брони» администратору зала в уже работающей базе (ТЗ 1.3).
        $hallAdmin = DB::table('roles')->where('slug', 'hall_admin')->value('id');
        if ($hallAdmin !== null) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $hallAdmin, 'permission' => 'bookings']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
        DB::table('role_permissions')->where('permission', 'bookings')->delete();
    }
};
