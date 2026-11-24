<?php

namespace App\Http\Controllers\Guest;

use App\Enums\OrderItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\GuestOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\MenuRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TableRepository;
use App\Services\GuestOrderService;
use App\Services\ReviewService;
use App\Services\SettingsService;
use App\Support\MenuPresenter;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Страница гостя по QR-коду стола: меню, корзина, статус заказа. Без входа. */
class GuestController extends Controller
{
    public function __construct(
        private readonly TableRepository $tables,
        private readonly MenuRepository $menu,
        private readonly MenuPresenter $presenter,
        private readonly GuestOrderService $orders,
        private readonly OrderRepository $orderRepository,
        private readonly SettingsService $settings,
        private readonly ReviewService $reviews,
    ) {}

    public function menu(string $token): Response
    {
        $table = $this->tables->findByToken($token);
        abort_if($table === null, 404);

        $menu = $this->presenter->present($this->menu->activeMenu(), hideStopped: true);

        return Inertia::render('Guest/Menu', [
            'table' => ['number' => $table->number, 'token' => $table->qr_token],
            'menu' => $menu,
            'version' => $this->presenter->version($menu),
            'restaurant' => $this->settings->get('restaurant_name'),
        ]);
    }

    /** Фоновое обновление меню, цен и стоп-листа на устройстве гостя. */
    public function menuJson(string $token): JsonResponse
    {
        abort_if($this->tables->findByToken($token) === null, 404);

        $menu = $this->presenter->present($this->menu->activeMenu(), hideStopped: true);

        return response()->json(['menu' => $menu, 'version' => $this->presenter->version($menu)]);
    }

    public function store(GuestOrderRequest $request, string $token): JsonResponse
    {
        $table = $this->tables->findByToken($token);
        abort_if($table === null, 404);

        $order = $this->orders->place($table, $request->validated('items'), $request->validated('comment'));

        return response()->json([
            'status_url' => route('guest.order', $order->public_token),
        ], 201);
    }

    public function show(string $publicToken): Response
    {
        $order = $this->orderRepository->findByPublicToken($publicToken);
        abort_if($order === null, 404);

        return Inertia::render('Guest/OrderStatus', [
            'token' => $publicToken,
            'initial' => $this->statusPayload($order),
            'restaurant' => $this->settings->get('restaurant_name'),
        ]);
    }

    public function status(string $publicToken): JsonResponse
    {
        $order = $this->orderRepository->findByPublicToken($publicToken);
        abort_if($order === null, 404);

        return response()->json($this->statusPayload($order));
    }

    /** @return array<string, mixed> */
    private function statusPayload(Order $order): array
    {
        // У доставки блюда видны сразу, ещё до отправки на кухню.
        $visible = $order->isHall()
            ? OrderItemStatus::visibleToGuest()
            : [OrderItemStatus::Draft->value, ...OrderItemStatus::visibleToGuest()];
        $order->load(['table:id,number', 'review', 'delivery', 'items' => fn ($q) => $q->whereIn('status', $visible)->orderBy('batch')]);

        return [
            'table' => $order->table?->number,
            'status' => $order->status->value,
            'total' => $order->total,
            // Доставка и самовывоз: свой путь статусов (ТЗ 2.6).
            'channel' => $order->channel->value,
            'number' => $order->id,
            'delivery_fee' => $order->delivery_fee,
            'delivery_status' => $order->delivery?->status->value,
            // После оплаты — ссылка на оценку, пока гость её не оставил (ТЗ 2.9).
            'review_url' => $this->reviews->pendingUrl($order),
            'reviewed' => (bool) $order->review?->isSubmitted(),
            'items' => $order->items->map(fn (OrderItem $i) => [
                'id' => $i->id,
                'name_tm' => $i->name_tm,
                'name_ru' => $i->name_ru,
                'quantity' => $i->quantity,
                'status' => $i->status->value,
            ])->values(),
        ];
    }
}
