<?php

namespace App\Repositories;

use App\Models\Review;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReviewRepository
{
    /** @param array<string, mixed> $data */
    public function firstOrCreateForOrder(int $orderId, array $data): Review
    {
        return Review::firstOrCreate(['order_id' => $orderId], $data);
    }

    public function findByToken(string $token): ?Review
    {
        return Review::query()->where('token', $token)->first();
    }

    /** @return LengthAwarePaginator<int, Review> */
    public function submitted(bool $onlyLow, int $perPage = 30): LengthAwarePaginator
    {
        return Review::query()
            ->with(['order:id,dining_table_id,total,paid_at', 'order.table:id,number', 'waiter:id,name'])
            ->whereNotNull('submitted_at')
            ->when($onlyLow, fn ($q) => $q->where('rating', '<=', Review::LOW_RATING))
            ->latest('submitted_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array{count:int, avg:float|null, distribution:array<int,int>} */
    public function stats(): array
    {
        $rows = Review::query()->whereNotNull('submitted_at')
            ->groupBy('rating')->selectRaw('rating, count(*) as n')->pluck('n', 'rating');

        $count = (int) $rows->sum();
        $distribution = [];
        foreach (range(1, 5) as $r) {
            $distribution[$r] = (int) ($rows[$r] ?? 0);
        }

        $avg = $count > 0
            ? round(collect($distribution)->map(fn ($n, $r) => $n * $r)->sum() / $count, 2)
            : null;

        return ['count' => $count, 'avg' => $avg, 'distribution' => $distribution];
    }

    public function unseenLowCount(): int
    {
        return Review::query()->whereNotNull('submitted_at')
            ->where('rating', '<=', Review::LOW_RATING)->whereNull('seen_at')->count();
    }

    public function markLowSeen(): void
    {
        Review::query()->whereNotNull('submitted_at')
            ->where('rating', '<=', Review::LOW_RATING)->whereNull('seen_at')
            ->update(['seen_at' => now()]);
    }

    /**
     * Средняя оценка и число отзывов по официантам за период (для отчёта «Сотрудники»).
     *
     * @return Collection<int, \stdClass>
     */
    public function byWaiter(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('reviews')
            ->whereNotNull('submitted_at')
            ->whereNotNull('waiter_id')
            ->whereBetween('submitted_at', [$from, $to])
            ->groupBy('waiter_id')
            ->selectRaw('waiter_id, avg(rating) as avg_rating, count(*) as reviews')
            ->get();
    }
}
