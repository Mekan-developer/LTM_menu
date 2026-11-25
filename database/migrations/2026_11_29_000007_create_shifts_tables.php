<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Смены (ТЗ 2.12 «График смен»): плановые смены и фактические отметки прихода/ухода.
 * Свои в каждом филиале (ТЗ разд. 4) — поэтому сразу с branch_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at'); // ночная смена заканчивается на следующий день
            $table->string('note', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'starts_at']);
            $table->index(['user_id', 'starts_at']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('clock_in');
            $table->dateTime('clock_out')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('correction_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'clock_in']);
            $table->index(['branch_id', 'clock_in']);
        });

        // Право «Смены» в уже работающей базе: администратору зала (ТЗ 1.3); владельцу — всегда.
        $hallAdmin = DB::table('roles')->where('slug', 'hall_admin')->value('id');
        if ($hallAdmin !== null) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $hallAdmin, 'permission' => 'shifts']);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'shifts')->delete();
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('shifts');
    }
};
