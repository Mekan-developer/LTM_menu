<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('number', 16)->unique();
            $table->string('hall', 64)->nullable();
            $table->unsignedTinyInteger('seats')->default(4);
            $table->string('qr_token', 40)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('public_token', 40)->unique(); // ссылка статуса для гостя
            $table->foreignId('dining_table_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 16);
            $table->string('status', 24)->index();
            $table->text('guest_comment')->nullable();
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('batch_counter')->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('bill_requested_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['dining_table_id', 'status']);
            $table->index('paid_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_tm');   // снимок названия и цены на момент заказа
            $table->string('name_ru');
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('quantity');
            $table->string('station', 16);
            $table->string('status', 16);
            $table->string('comment')->nullable();
            $table->unsignedInteger('batch')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->string('removed_reason')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'station']);
            $table->index(['order_id', 'batch']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('method', 8);
            $table->unsignedInteger('cash_amount')->default(0);
            $table->unsignedInteger('card_amount')->default(0);
            $table->unsignedInteger('total');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('dining_tables');
    }
};
