<?php

namespace App\Http\Controllers\Guest;

use App\Enums\OrderSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deliveries\PublicDeliveryRequest;
use App\Repositories\MenuRepository;
use App\Services\DeliveryService;
use App\Services\SettingsService;
use App\Support\MenuPresenter;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Доставка и самовывоз на странице для гостей (ТЗ 2.6): то же меню, что по QR, плюс адрес и телефон. */
class DeliveryController extends Controller
{
    public function __construct(
        private readonly DeliveryService $service,
        private readonly MenuRepository $menu,
        private readonly MenuPresenter $presenter,
        private readonly SettingsService $settings,
    ) {}

    public function create(): Response
    {
        $this->assertEnabled();
        $menu = $this->presenter->present($this->menu->activeMenu(), hideStopped: true);

        return Inertia::render('Guest/Menu', [
            'table' => null,
            'delivery' => ['fee' => (int) $this->settings->get('delivery_fee')],
            'menu' => $menu,
            'version' => $this->presenter->version($menu),
            'restaurant' => $this->settings->get('restaurant_name'),
        ]);
    }

    /** Фоновое обновление меню, цен и стоп-листа (как у меню по QR). */
    public function menuJson(): JsonResponse
    {
        $this->assertEnabled();
        $menu = $this->presenter->present($this->menu->activeMenu(), hideStopped: true);

        return response()->json(['menu' => $menu, 'version' => $this->presenter->version($menu)]);
    }

    public function store(PublicDeliveryRequest $request): JsonResponse
    {
        $this->assertEnabled();
        $order = $this->service->create($request->validated(), OrderSource::Online);

        return response()->json(['status_url' => route('guest.order', $order->public_token)], 201);
    }

    private function assertEnabled(): void
    {
        abort_unless((bool) $this->settings->get('online_delivery'), 404);
    }
}
