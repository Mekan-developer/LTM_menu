<?php

namespace App\Repositories;

use App\Models\Branch;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockRepository
{
    /**
     * Все продукты справочника с остатком и минимумом в текущем филиале (нет строки — остаток 0).
     *
     * @return Collection<int, array{id: int, name_tm: string, name_ru: string, unit: string, price: int, quantity: float, min: float, value: int, to_buy: float}>
     */
    public function balances(): Collection
    {
        $branchId = Branch::currentId();

        return DB::table('products')
            ->leftJoin('stock_balances as b', fn ($j) => $j->on('b.product_id', '=', 'products.id')->where('b.branch_id', $branchId))
            ->orderBy('products.name_ru')
            ->get(['products.id', 'products.name_tm', 'products.name_ru', 'products.unit', 'products.price', 'b.quantity', 'b.min_quantity'])
            ->map(function ($r) {
                $qty = round((float) ($r->quantity ?? 0), 3);
                $min = round((float) ($r->min_quantity ?? 0), 3);

                return [
                    'id' => (int) $r->id,
                    'name_tm' => (string) $r->name_tm,
                    'name_ru' => (string) $r->name_ru,
                    'unit' => (string) $r->unit,
                    'price' => (int) $r->price,
                    'quantity' => $qty,
                    'min' => $min,
                    'value' => (int) round(max(0, $qty) * (int) $r->price),
                    // «Нужно закупить»: сколько докупить до минимума (ТЗ 2.3).
                    'to_buy' => $min > 0 && $qty < $min ? round($min - $qty, 3) : 0.0,
                ];
            });
    }

    /**
     * История движений в текущем филиале, новые первыми.
     *
     * @param  array{type: ?string, product: ?int, document: ?int}  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function movements(CarbonInterface $from, CarbonInterface $to, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return StockMovement::query()
            ->with(['product:id,name_tm,name_ru,unit', 'user:id,name', 'document:id,type,reason,note'])
            ->where('branch_id', Branch::currentId())
            ->when($filters['document'], fn (Builder $q, int $doc) => $q->where('document_id', $doc),
                fn (Builder $q) => $q->whereBetween('created_at', [$from, $to]))
            ->when($filters['type'], fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['product'], fn (Builder $q, int $product) => $q->where('product_id', $product))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
