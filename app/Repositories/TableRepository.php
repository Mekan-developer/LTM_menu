<?php

namespace App\Repositories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TableRepository
{
    /** @return Collection<int, DiningTable> */
    public function all(): Collection
    {
        return DiningTable::query()->with(['activeOrder', 'hall'])->orderBy('sort')->orderBy('id')->get();
    }

    /** @return Collection<int, DiningTable> */
    public function active(): Collection
    {
        return DiningTable::query()->where('is_active', true)->with('activeOrder')
            ->orderBy('sort')->orderBy('id')->get();
    }

    public function findByToken(string $token): ?DiningTable
    {
        return DiningTable::query()->where('qr_token', $token)->where('is_active', true)->first();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): DiningTable
    {
        return DiningTable::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(DiningTable $table, array $data): DiningTable
    {
        $table->update($data);

        return $table;
    }

    /**
     * Удалить стол. С историей заказов или броней — мягко (номер нужен в истории), иначе насовсем.
     * Будущие брони на этом столе остаются без стола — администратор назначит другой.
     */
    public function remove(DiningTable $table): void
    {
        DB::transaction(function () use ($table) {
            Booking::query()
                ->where('dining_table_id', $table->id)
                ->whereIn('status', BookingStatus::holdingValues())
                ->where('starts_at', '>=', now())
                ->update(['dining_table_id' => null]);

            $hasHistory = $table->orders()->exists()
                || Booking::query()->where('dining_table_id', $table->id)->exists();

            $hasHistory ? $table->delete() : $table->forceDelete();
        });
    }

    public function nextSort(): int
    {
        return (int) DiningTable::max('sort') + 1;
    }
}
