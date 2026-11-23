<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Repositories\TableRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TableService
{
    public function __construct(private readonly TableRepository $tables) {}

    public function create(array $data): DiningTable
    {
        return $this->tables->create($data + [
            'qr_token' => $this->newToken(),
            'sort' => $this->tables->nextSort(),
        ]);
    }

    public function update(DiningTable $table, array $data): DiningTable
    {
        return $this->tables->update($table, $data);
    }

    /** При перегенерации старый QR-код стола перестаёт работать (ТЗ 2.3). */
    public function regenerateQr(DiningTable $table): DiningTable
    {
        return $this->tables->update($table, ['qr_token' => $this->newToken()]);
    }

    /** Стол с историей заказов не удаляем, а выключаем. */
    public function delete(DiningTable $table): void
    {
        if ($table->activeOrder()->exists()) {
            throw ValidationException::withMessages(['table' => __('crm.tables.busy')]);
        }

        $table->orders()->exists()
            ? $this->tables->update($table, ['is_active' => false])
            : $table->delete();
    }

    private function newToken(): string
    {
        return Str::lower(Str::random(32));
    }
}
