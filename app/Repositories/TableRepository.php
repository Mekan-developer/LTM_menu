<?php

namespace App\Repositories;

use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Collection;

class TableRepository
{
    public function all(): Collection
    {
        return DiningTable::query()->with('activeOrder')->orderBy('sort')->orderBy('id')->get();
    }

    public function active(): Collection
    {
        return DiningTable::query()->where('is_active', true)->with('activeOrder')
            ->orderBy('sort')->orderBy('id')->get();
    }

    public function findByToken(string $token): ?DiningTable
    {
        return DiningTable::query()->where('qr_token', $token)->where('is_active', true)->first();
    }

    public function create(array $data): DiningTable
    {
        return DiningTable::create($data);
    }

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
