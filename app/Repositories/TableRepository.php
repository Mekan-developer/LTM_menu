<?php

namespace App\Repositories;

use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Collection;

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

    public function nextSort(): int
    {
        return (int) DiningTable::max('sort') + 1;
    }
}
