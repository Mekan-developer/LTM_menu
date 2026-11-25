<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Бонусы (ТЗ 2.8): 1 бонус = 1 TMT, храним в тенге. Баланс гостя общий на всю сеть (ТЗ разд. 4).
 * Каждое начисление и списание — отдельная операция с остатком после неё.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->integer('bonus_balance')->default(0)->after('comment'); // может стать < 0 после отмены оплаты
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('bonus_paid')->default(0)->after('discount_amount'); // часть счёта, оплаченная бонусами
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('bonus_amount')->default(0)->after('card_amount'); // списано бонусов
            $table->unsignedInteger('bonus_accrued')->default(0)->after('bonus_amount'); // начислено бонусов
        });

        Schema::create('bonus_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // accrual | spend | accrual_reversal | spend_reversal
            $table->integer('amount'); // со знаком, в тенге
            $table->integer('balance_after');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['guest_id', 'id']);
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_transactions');
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['bonus_amount', 'bonus_accrued']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('bonus_paid'));
        Schema::table('guests', fn (Blueprint $t) => $t->dropColumn('bonus_balance'));
    }
};
