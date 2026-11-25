<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\Hall;
use App\Repositories\TableRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TableService
{
    public function __construct(
        private readonly TableRepository $tables,
        private readonly HallService $halls,
    ) {}

    /**
     * Новый стол из списка встаёт на первое свободное место схемы зала.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DiningTable
    {
        return $this->tables->create($data + $this->placement((int) $data['hall_id'], (int) $data['seats']) + [
            'qr_token' => $this->newToken(),
            'sort' => $this->tables->nextSort(),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(DiningTable $table, array $data): DiningTable
    {
        // Переехал в другой зал — ищем место на его схеме.
        if (isset($data['hall_id']) && (int) $data['hall_id'] !== $table->hall_id) {
            $data += $this->placement((int) $data['hall_id'], (int) ($data['seats'] ?? $table->seats));
        }

        return $this->tables->update($table, $data);
    }

    /**
     * Форма и место нового стола: до 2 мест — круглый, 6 и больше — длинный, иначе квадратный.
     *
     * @return array<string, mixed>
     */
    private function placement(int $hallId, int $seats): array
    {
        [$shape, $w, $h] = match (true) {
            $seats >= 6 => ['rect', 160, 80],
            $seats <= 2 => ['round', 80, 80],
            default => ['square', 80, 80],
        };

        $spot = $this->halls->freeSpot(Hall::findOrFail($hallId), $w, $h);

        if ($spot === null) {
            throw ValidationException::withMessages(['hall_id' => __('crm.hall.no_space')]);
        }

        return ['shape' => $shape, 'pos_x' => $spot[0], 'pos_y' => $spot[1], 'width' => $w, 'height' => $h, 'rotation' => 0];
    }

    /** При перегенерации старый QR-код стола перестаёт работать (ТЗ 2.3). */
    public function regenerateQr(DiningTable $table): DiningTable
    {
        return $this->tables->update($table, ['qr_token' => $this->newToken()]);
    }

    /**
     * Стол с историей заказов или броней удаляется мягко (номер остаётся в истории), без истории — насовсем.
     * В обоих случаях он пропадает из списка, печати и схемы, а его QR перестаёт работать.
     */
    public function delete(DiningTable $table): void
    {
        if ($table->activeOrder()->exists()) {
            throw ValidationException::withMessages(['table' => __('crm.tables.busy')]);
        }

        $this->tables->remove($table);
    }

    private function newToken(): string
    {
        return Str::lower(Str::random(32));
    }
}
