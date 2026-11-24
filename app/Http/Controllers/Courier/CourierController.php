<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deliveries\CompleteDeliveryRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Repositories\DeliveryRepository;
use App\Services\DeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Экран курьера (ТЗ 1.3, 2.6): свои заказы, «Забрал», «Доставлен» с фактическим способом оплаты. */
class CourierController extends Controller
{
    public function __construct(
        private readonly DeliveryService $service,
        private readonly DeliveryRepository $deliveries,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $owed = $this->deliveries->cashOnCouriers()->firstWhere('courier_id', $user->id);

        return Inertia::render('Courier/Index', [
            'deliveries' => DeliveryResource::collection($this->deliveries->forCourier($user, now()->startOfDay())),
            // Сколько наличных курьер должен сдать в кассу (ТЗ 2.6).
            'cashOwed' => (int) ($owed->amount ?? 0),
        ]);
    }

    public function pickUp(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->service->pickUp($delivery->loadMissing('order'), $request->user());

        return back();
    }

    public function complete(CompleteDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->service->complete($delivery->loadMissing('order'), $request->payData(), $request->user());

        return back()->with('success', __('crm.delivery.delivered'));
    }
}
