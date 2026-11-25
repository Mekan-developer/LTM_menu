<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Склад (ТЗ 2.3 «Остатки») и зацепка под филиалы (ТЗ разд. 4).
 *
 * Филиалов пока нет: одна запись «Основной филиал», без интерфейса. Но всё, что по ТЗ
 * «своё в каждом филиале», сразу хранится с branch_id — при включении филиалов склад переделывать не придётся.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name_tm', 120);
            $table->string('name_ru', 120);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        DB::table('branches')->insert([
            'name_tm' => 'Esasy şahamça', 'name_ru' => 'Основной филиал', 'is_default' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Текущий остаток продукта в филиале и минимум для списка «Нужно закупить».
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->default(0); // может уйти в минус (ТЗ)
            $table->decimal('min_quantity', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });

        // Документ: приход, ручное списание или инвентаризация. Продажа документа не имеет — у неё order_id.
        Schema::create('stock_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('type', 16); // receipt | write_off | inventory
            $table->string('reason', 16)->nullable(); // списание: spoiled | broken | staff | other
            $table->string('note', 255)->nullable();
            $table->integer('total')->default(0); // сумма в тенге; у инвентаризации — расхождение со знаком
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'type', 'created_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('type', 16); // receipt | sale | sale_return | write_off | inventory
            $table->decimal('quantity', 12, 3); // со знаком: + пришло, − ушло
            $table->unsignedInteger('price'); // цена за единицу на момент движения, в тенге
            $table->integer('amount'); // quantity × price, со знаком
            $table->decimal('balance_after', 12, 3);
            $table->foreignId('document_id')->nullable()->constrained('stock_documents')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'product_id', 'created_at']);
            $table->index(['order_id', 'type']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_documents');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('branches');
    }
};
