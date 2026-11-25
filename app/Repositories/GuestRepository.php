<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Guest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class GuestRepository
{
    /**
     * Гости со статистикой визитов — подзапросами, без N+1.
     * Поиск по имени и телефону (ТЗ 2.1).
     *
     * @return Builder<Guest>
     */
    private function withStats(?string $search): Builder
    {
        $paid = OrderStatus::Paid->value;

        return Guest::query()
            ->select('guests.*')
            ->selectSub(fn ($q) => $q->from('orders')->whereColumn('orders.guest_id', 'guests.id')
                ->where('orders.status', $paid)->selectRaw('count(*)'), 'visits')
            ->selectSub(fn ($q) => $q->from('payments')->join('orders', 'orders.id', '=', 'payments.order_id')
                ->whereColumn('orders.guest_id', 'guests.id')->whereNull('payments.cancelled_at')
                ->selectRaw('coalesce(sum(payments.total), 0)'), 'spent')
            ->selectSub(fn ($q) => $q->from('orders')->whereColumn('orders.guest_id', 'guests.id')
                ->where('orders.status', $paid)->selectRaw('max(orders.paid_at)'), 'last_visit')
            ->when(filled($search), function (Builder $q) use ($search) {
                $term = mb_strtolower(trim((string) $search));
                $digits = preg_replace('/\D+/', '', $term) ?? '';

                $q->where(function (Builder $w) use ($term, $digits) {
                    $w->whereRaw('lower(guests.name) like ?', ['%'.$term.'%']);
                    if (strlen($digits) >= 3) {
                        $w->orWhere('guests.phone', 'like', '%'.$digits.'%');
                    }
                });
            });
    }

    /** @return LengthAwarePaginator<int, Guest> */
    public function search(?string $search, int $perPage = 30): LengthAwarePaginator
    {
        return $this->withStats($search)->orderBy('guests.name')->paginate($perPage)->withQueryString();
    }

    /** @return Collection<int, Guest> */
    public function forExport(): Collection
    {
        return $this->withStats(null)->orderBy('guests.name')->get();
    }

    public function stats(Guest $guest): ?Guest
    {
        return $this->withStats(null)->whereKey($guest->id)->first();
    }

    public function findByPhone(string $phone): ?Guest
    {
        return Guest::query()->where('phone', $phone)->first();
    }

    /**
     * История заказов гостя: оплаченные и возвращённые, новые первыми.
     *
     * @return BaseCollection<int, \stdClass>
     */
    public function orders(Guest $guest, int $limit = 50): BaseCollection
    {
        return DB::table('orders')
            ->leftJoin('dining_tables', 'dining_tables.id', '=', 'orders.dining_table_id')
            ->leftJoin('users', 'users.id', '=', 'orders.waiter_id')
            ->where('orders.guest_id', $guest->id)
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::Refunded->value])
            ->orderByDesc('orders.paid_at')
            ->limit($limit)
            ->get(['orders.id', 'orders.status', 'orders.channel', 'orders.total', 'orders.discount_percent', 'orders.paid_at',
                'dining_tables.number as table', 'users.name as waiter']);
    }

    /**
     * Отзывы гостя — через его счета.
     *
     * @return BaseCollection<int, \stdClass>
     */
    public function reviews(Guest $guest): BaseCollection
    {
        return DB::table('reviews')
            ->join('orders', 'orders.id', '=', 'reviews.order_id')
            ->where('orders.guest_id', $guest->id)
            ->whereNotNull('reviews.submitted_at')
            ->orderByDesc('reviews.submitted_at')
            ->get(['reviews.id', 'reviews.rating', 'reviews.comment', 'reviews.submitted_at', 'reviews.order_id']);
    }
}
