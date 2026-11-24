<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * База гостей (ТЗ 2.1): один номер телефона — один гость.
 * Заказ ссылается на гостя — так в карточке видны заказы, сумма покупок и отзывы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 16)->unique(); // +993XXXXXXXX
            $table->date('birthday')->nullable();
            $table->string('address', 255)->nullable(); // адрес доставки
            $table->text('comment')->nullable();        // аллергии, любимый стол
            $table->string('source', 16)->default('staff'); // staff | cashier | booking | order
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('guest_id')->nullable()->after('waiter_id')->constrained()->nullOnDelete();
        });

        // Права раздела в уже работающей базе: «Гости» — администратору зала (ТЗ 1.3).
        $hallAdmin = DB::table('roles')->where('slug', 'hall_admin')->value('id');
        if ($hallAdmin !== null) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $hallAdmin, 'permission' => 'guests']);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['guest_id']);
            $table->dropColumn('guest_id');
        });
        Schema::dropIfExists('guests');
        DB::table('role_permissions')->whereIn('permission', ['guests', 'export_guests'])->delete();
    }
};
