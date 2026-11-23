<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\CancelPaymentRequest;
use App\Http\Requests\Cashier\PayRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Services\CashierService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashierController extends Controller
{
    public function __construct(
        private readonly CashierService $cashier,
        private readonly OrderRepository $orders,
        private readonly PaymentRepository $payments,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $queue = $this->orders->activeForCashier();
        $selectedId = $request->integer('order') ?: $queue->first()?->id;
        $selected = $selectedId ? Order::find($selectedId) : null;

        return Inertia::render('Cashier/Index', [
            'queue' => OrderResource::collection($queue),
            'selected' => $selected ? new OrderResource($this->orders->loadForScreen($selected)) : null,
            'summary' => fn () => $this->cashier->daySummary(),
            'recent' => fn () => PaymentResource::collection($this->payments->recentForCashier()),
            'rules' => [
                'discount_limit_k' => $this->cashier->discountLimit(),
                'can_discount' => $user->hasPermission(Permission::DiscountUpToK) || $user->hasPermission(Permission::DiscountUnlimited),
                'can_discount_unlimited' => $user->hasPermission(Permission::DiscountUnlimited),
                'can_cancel' => $user->hasPermission(Permission::CancelPayment),
            ],
        ]);
    }

    public function pay(PayRequest $request, Order $order): RedirectResponse
    {
        $payment = $this->cashier->pay($order, $request->payData(), $request->user());

        return redirect()
            ->route('cashier.index', ['paid' => $payment->id])
            ->with('success', __('crm.cashier.paid'));
    }

    public function cancel(CancelPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->cashier->cancel($payment, $request->string('reason')->value(), $request->user());

        return back()->with('success', __('crm.cashier.cancelled'));
    }

    /** Счёт/чек 80 мм — печать через браузер. */
    public function receipt(Order $order): Response
    {
        return Inertia::render('Cashier/Receipt', [
            'order' => new OrderResource($this->orders->loadForScreen($order)),
            'restaurant' => $this->settings->get('restaurant_name'),
        ]);
    }
}
