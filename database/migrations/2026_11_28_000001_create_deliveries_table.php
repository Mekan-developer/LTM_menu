<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Доставка и самовывоз (ТЗ 2.6). Заказ доставки — обычный заказ без стола (channel = delivery / pickup):
 * те же позиции, кухня, пересчёт и оплата. Данные доставки и её статус — в таблице deliveries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('channel', 16)->default('hall')->after('source')->index();
            $table->unsignedInteger('delivery_fee')->default(0)->after('discount_amount');
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 16)->index();
            $table->string('customer_name', 120);
            $table->string('phone', 16);
            $table->string('address', 500)->nullable();
            $table->string('planned_method', 8); // способ оплаты, выбранный при заказе
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cooking_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            // Наличные курьера: кассир отмечает, когда курьер их сдал.
            $table->timestamp('cash_received_at')->nullable();
            $table->foreignId('cash_received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['courier_id', 'status']);
        });

        // Права в уже работающей базе: «Доставка» — администратору зала, экран курьера — курьеру.
        foreach (['hall_admin' => 'delivery', 'courier' => 'area_courier'] as $slug => $permission) {
            $role = DB::table('roles')->where('slug', $slug)->value('id');

            if ($role !== null) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission' => $permission]);
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', ['delivery', 'area_courier'])->delete();
        Schema::dropIfExists('deliveries');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropColumn(['channel', 'delivery_fee']);
        });
    }
};
