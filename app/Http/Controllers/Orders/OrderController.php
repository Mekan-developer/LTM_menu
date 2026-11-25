<?php

namespace App\Http\Controllers\Orders;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\OrderListRequest;
use App\Models\ActionLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Tip;
use App\Repositories\OrderMonitorRepository;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/** «Все заказы»: мониторинг зала, QR, доставки и самовывоза за период (по умолчанию — сегодня). */
class OrderController extends Controller
{
    private const MAX_DAYS = 92;

    public function __construct(private readonly OrderMonitorRepository $orders) {}

    public function index(OrderListRequest $request): Response
    {
        [$from, $to] = $this->period($request->validated('from'), $request->validated('to'));
        $filters = $request->filters();
        // Телефоны гостей — только с правом «Гости», как в отчётах.
        $canGuests = $request->user()->hasPermission(Permission::Guests);
        $show = $request->filled('show') ? (int) $request->validated('show') : null;

        return Inertia::render('Orders/Index', [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filters' => $filters,
            'summary' => fn () => $this->orders->summary($from, $to, $filters),
            'orders' => function () use ($from, $to, $filters, $canGuests) {
                $page = $this->orders->list($from, $to, $filters);

                return [
                    'data' => collect($page->items())->map(fn (Order $o) => $this->row($o, $canGuests))->values(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'total' => $page->total(),
                ];
            },
            'waiters' => fn () => $this->orders->waiters($from, $to),
            // Карточка заказа в боковой панели: ?show=ID
            'detail' => fn () => $show !== null ? $this->detail($show, $canGuests) : null,
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(?string $from, ?string $to): array
    {
        $end = $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfDay();
        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : $end->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        if ($start->diffInDays($end) >= self::MAX_DAYS) {
            $start = $end->subDays(self::MAX_DAYS - 1)->startOfDay();
        }

        return [$start, $end];
    }

    /** @return array<string, mixed> */
    private function row(Order $o, bool $canGuests): array
    {
        return [
            'id' => $o->id,
            'opened_at' => $o->opened_at->toIso8601String(),
            'paid_at' => $o->paid_at?->toIso8601String(),
            'channel' => $o->channel->value,
            'source' => $o->source->value,
            'status' => $o->status->value,
            'delivery_status' => $o->delivery?->status->value,
            'table' => $o->table?->number,
            'waiter' => $o->waiter?->name,
            'guest' => $o->delivery->customer_name ?? $o->guest?->name,
            'phone' => $canGuests ? ($o->delivery->phone ?? $o->guest?->phone) : null,
            'items_qty' => (int) ($o->getAttribute('items_qty') ?? 0),
            'total' => $o->total,
            'method' => $o->activePayment?->method->value,
        ];
    }

    /** @return array<string, mixed>|null */
    private function detail(int $id, bool $canGuests): ?array
    {
        $o = $this->orders->detail($id);

        if ($o === null) {
            return null;
        }

        return $this->row($o, $canGuests) + [
            'hall' => $o->table?->hall ? ['name_tm' => $o->table->hall->name_tm, 'name_ru' => $o->table->hall->name_ru] : null,
            'table_deleted' => (bool) $o->table?->trashed(),
            'guest_comment' => $o->guest_comment,
            'subtotal' => $o->subtotal,
            'discount_percent' => $o->discount_percent,
            'discount_amount' => $o->discount_amount,
            'delivery_fee' => $o->delivery_fee,
            'bill_requested_at' => $o->bill_requested_at?->toIso8601String(),
            'closed_at' => $o->closed_at?->toIso8601String(),
            'items' => $o->items->map(fn (OrderItem $i) => [
                'id' => $i->id,
                'name_tm' => $i->name_tm,
                'name_ru' => $i->name_ru,
                'price' => $i->price,
                'qty' => $i->quantity,
                'total' => $i->lineTotal(),
                'status' => $i->status->value,
                'comment' => $i->comment,
                'added_by' => $i->addedBy?->name,
                'sent_at' => $i->sent_at?->toIso8601String(),
                'ready_at' => $i->ready_at?->toIso8601String(),
                'served_at' => $i->served_at?->toIso8601String(),
                'removed_reason' => $i->removed_reason,
                'removed_by' => $i->removedBy?->name,
            ])->values(),
            'payments' => $o->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'method' => $p->method->value,
                'cash' => $p->cash_amount,
                'card' => $p->card_amount,
                'total' => $p->total,
                'cashier' => $p->cashier?->name,
                'created_at' => $p->created_at?->toIso8601String(),
                'cancelled_at' => $p->cancelled_at?->toIso8601String(),
                'cancel_reason' => $p->cancel_reason,
                'tips' => (int) $p->tips->sum(fn (Tip $t) => $t->amount),
            ])->values(),
            'delivery' => $o->delivery ? [
                'status' => $o->delivery->status->value,
                'address' => $o->delivery->address,
                'planned_method' => $o->delivery->planned_method->value,
                'courier' => $o->delivery->courier?->name,
                'accepted_at' => $o->delivery->accepted_at?->toIso8601String(),
                'cooking_at' => $o->delivery->cooking_at?->toIso8601String(),
                'ready_at' => $o->delivery->ready_at?->toIso8601String(),
                'picked_at' => $o->delivery->picked_at?->toIso8601String(),
                'finished_at' => $o->delivery->finished_at?->toIso8601String(),
                'cancelled_at' => $o->delivery->cancelled_at?->toIso8601String(),
                'cancel_reason' => $o->delivery->cancel_reason,
            ] : null,
            'logs' => $this->orders->logs($o)->map(fn (ActionLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'user' => $l->user?->name,
                'payload' => $l->payload,
                'created_at' => $l->created_at->toIso8601String(),
            ])->values(),
        ];
    }
}
