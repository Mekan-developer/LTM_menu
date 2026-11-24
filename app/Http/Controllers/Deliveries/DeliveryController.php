<?php

namespace App\Http\Controllers\Deliveries;

use App\Enums\OrderSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deliveries\CompleteDeliveryRequest;
use App\Http\Requests\Deliveries\DeliveryActionRequest;
use App\Http\Requests\Deliveries\DeliveryRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\User;
use App\Repositories\DeliveryRepository;
use App\Repositories\GuestRepository;
use App\Repositories\MenuRepository;
use App\Services\DeliveryService;
use App\Services\SettingsService;
use App\Support\MenuPresenter;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Экран «Доставка» (ТЗ 2.6): заказы по звонку и со страницы, статусы, курьеры. */
class DeliveryController extends Controller
{
    public function __construct(
        private readonly DeliveryService $service,
        private readonly DeliveryRepository $deliveries,
        private readonly GuestRepository $guests,
        private readonly MenuRepository $menu,
        private readonly MenuPresenter $presenter,
        private readonly SettingsService $settings,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Deliveries/Index', [
            'deliveries' => DeliveryResource::collection($this->deliveries->board(now()->startOfDay())),
            'couriers' => $this->deliveries->couriers()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'cashOnCouriers' => $this->deliveries->cashOnCouriers()->map(fn ($r) => [
                'courier_id' => (int) $r->courier_id, 'name' => $r->name, 'amount' => (int) $r->amount, 'orders' => (int) $r->orders,
            ])->values(),
            'fee' => (int) $this->settings->get('delivery_fee'),
            // Меню нужно только форме нового заказа — грузится по запросу.
            'menu' => Inertia::optional(fn () => $this->presenter->present($this->menu->activeMenu(), hideStopped: true)),
        ]);
    }

    public function store(DeliveryRequest $request): RedirectResponse
    {
        $order = $this->service->create($request->validated(), OrderSource::Phone, $request->user());

        return back()->with('success', __('crm.delivery.created', ['n' => $order->id]));
    }

    public function action(DeliveryActionRequest $request, Delivery $delivery): RedirectResponse
    {
        $user = $request->user();
        $delivery->loadMissing('order');

        match ($request->validated('action')) {
            'accept' => $this->service->accept($delivery, $user),
            'kitchen' => $this->service->sendToKitchen($delivery, $user),
            'courier' => $this->service->assignCourier(
                $delivery,
                $request->filled('courier_id') ? User::query()->findOrFail((int) $request->validated('courier_id')) : null,
                $user,
            ),
            'fee' => $this->service->setFee($delivery, (int) $request->validated('delivery_fee'), $user),
            'pick_up' => $this->service->pickUp($delivery, $user),
            default => $this->service->cancel($delivery, (string) $request->validated('reason'), $user),
        };

        return back()->with('success', __('crm.delivery.saved'));
    }

    /** Администратор отмечает вручение сам — например, если у курьера сел телефон. */
    public function complete(CompleteDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->service->complete($delivery->loadMissing('order'), $request->payData(), $request->user());

        return back()->with('success', __('crm.delivery.delivered'));
    }

    /** Подсказка по телефону: гость уже есть в базе — подставляем имя и адрес. */
    public function guest(Request $request): JsonResponse
    {
        $phone = Phone::normalize($request->string('phone')->value());
        $guest = $phone ? $this->guests->findByPhone($phone) : null;

        return response()->json($guest ? ['name' => $guest->name, 'address' => $guest->address] : null);
    }
}
