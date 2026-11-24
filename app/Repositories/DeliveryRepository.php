<?php

namespace App\Repositories;

use App\Enums\DeliveryStatus;
use App\Enums\Permission;
use App\Models\Delivery;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class DeliveryRepository
{
    /**
     * Экран «Доставка»: все незавершённые заказы и завершённые за сегодня.
     *
     * @return Collection<int, Delivery>
     */
    public function board(CarbonInterface $since): Collection
    {
        return Delivery::query()
            ->where(fn ($q) => $q->whereIn('status', DeliveryStatus::activeValues())
                ->orWhere('updated_at', '>=', $since))
            ->with(['order.items', 'order.activePayment', 'courier:id,name'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Заказы курьера: назначенные ему и ещё не доставленные + доставленные за сегодня.
     *
     * @return Collection<int, Delivery>
     */
    public function forCourier(User $courier, CarbonInterface $since): Collection
    {
        return Delivery::query()
            ->where('courier_id', $courier->id)
            ->where(fn ($q) => $q->whereIn('status', DeliveryStatus::activeValues())
                ->orWhere('finished_at', '>=', $since))
            ->where('status', '!=', DeliveryStatus::Cancelled->value)
            ->with(['order.items', 'order.activePayment'])
            ->orderByRaw('case when finished_at is null then 0 else 1 end')
            ->orderBy('created_at')
            ->get();
    }

    public function lockForOrder(int $orderId): ?Delivery
    {
        return Delivery::query()->where('order_id', $orderId)->lockForUpdate()->first();
    }

    /**
     * Активные сотрудники с экраном курьера — для назначения.
     *
     * @return BaseCollection<int, User>
     */
    public function couriers(): BaseCollection
    {
        return User::query()
            ->where('is_active', true)
            ->with('role.permissions')
            ->orderBy('name')
            ->get(['id', 'name', 'role_id'])
            // Владелец формально имеет все права, но курьером не работает.
            ->filter(fn (User $u) => ! $u->isOwner() && $u->hasPermission(Permission::AreaCourier))
            ->values();
    }

    /**
     * Наличные, которые курьеры ещё не сдали в кассу (ТЗ 2.6): по каждому курьеру сумма и число заказов.
     * Отменённые оплаты не считаются. $since — только оплаты с этого момента (итог дня).
     *
     * @return BaseCollection<int, \stdClass> courier_id, name, amount, orders
     */
    public function cashOnCouriers(?CarbonInterface $since = null): BaseCollection
    {
        return DB::table('deliveries')
            ->join('payments', 'payments.order_id', '=', 'deliveries.order_id')
            ->join('users', 'users.id', '=', 'deliveries.courier_id')
            ->whereNull('payments.cancelled_at')
            ->whereNull('deliveries.cash_received_at')
            ->where('deliveries.status', DeliveryStatus::Delivered->value)
            ->where('payments.cash_amount', '>', 0)
            ->when($since, fn ($q) => $q->where('payments.created_at', '>=', $since))
            ->groupBy('deliveries.courier_id', 'users.name')
            ->selectRaw('deliveries.courier_id, users.name, sum(payments.cash_amount) as amount, count(*) as orders')
            ->orderBy('users.name')
            ->get();
    }

    /**
     * Доставки курьера с несданными наличными — блокируются, чтобы кассир не принял деньги дважды.
     *
     * @return Collection<int, Delivery>
     */
    public function unreceivedCashLocked(int $courierId): Collection
    {
        return Delivery::query()
            ->where('courier_id', $courierId)
            ->where('status', DeliveryStatus::Delivered->value)
            ->whereNull('cash_received_at')
            ->whereHas('order.payments', fn ($q) => $q->whereNull('cancelled_at')->where('cash_amount', '>', 0))
            ->with('order.activePayment')
            ->lockForUpdate()
            ->get();
    }
}
