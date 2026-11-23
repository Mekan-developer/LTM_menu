<?php

namespace App\Http\Controllers\Waiter;

use App\Enums\OrderItemStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Waiter\AddItemsRequest;
use App\Http\Requests\Waiter\RemoveItemRequest;
use App\Http\Requests\Waiter\TransferRequest;
use App\Http\Requests\Waiter\UpdateItemRequest;
use App\Http\Resources\OrderResource;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Repositories\MenuRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TableRepository;
use App\Services\WaiterOrderService;
use App\Support\MenuPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Экран официанта (телефон/планшет). */
class WaiterController extends Controller
{
    public function __construct(
        private readonly WaiterOrderService $service,
        private readonly OrderRepository $orders,
        private readonly TableRepository $tables,
        private readonly MenuRepository $menu,
        private readonly MenuPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $seeAll = $user->hasPermission(Permission::TransferTables);
        $orders = $this->orders->activeForWaiter($user, $seeAll);

        // Сортировка по срочности: QR ждёт → готово к выдаче → просят счёт → остальные.
        $rank = function (Order $o): int {
            $statuses = $o->items->pluck('status');

            return match (true) {
                $statuses->contains(OrderItemStatus::Pending) => 0,
                $statuses->contains(OrderItemStatus::Ready) => 1,
                $o->status->value === 'bill_requested' => 2,
                default => 3,
            };
        };

        $busyTableIds = $this->orders->activeTableIds();

        return Inertia::render('Waiter/Index', [
            'orders' => OrderResource::collection($orders->sortBy($rank)->values()),
            'freeTables' => $this->tables->active()
                ->reject(fn (DiningTable $t) => in_array($t->id, $busyTableIds, true))
                ->map(fn (DiningTable $t) => ['id' => $t->id, 'number' => $t->number, 'seats' => $t->seats])
                ->values(),
        ]);
    }

    public function open(Request $request, DiningTable $table): RedirectResponse
    {
        $order = $this->service->openTable($table, $request->user());

        return redirect()->route('waiter.order', $order);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->service->authorizeHandle($order, $request->user());
        $canTransfer = $request->user()->hasPermission(Permission::TransferTables);

        return Inertia::render('Waiter/Order', [
            'order' => new OrderResource($this->orders->loadForScreen($order)),
            'menu' => fn () => $this->presenter->present($this->menu->activeMenu(), hideStopped: false),
            'waiters' => $canTransfer
                ? User::query()->where('is_active', true)->get(['id', 'name', 'role_id'])
                    ->filter(fn (User $u) => $u->hasPermission(Permission::AreaWaiter))
                    ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()
                : [],
            'canRemoveSent' => $request->user()->hasPermission(Permission::RemoveAfterKitchen),
        ]);
    }

    public function addItems(AddItemsRequest $request, Order $order): RedirectResponse
    {
        $this->service->addDraftItems($order, $request->validated('items'), $request->user());

        return back();
    }

    public function updateItem(UpdateItemRequest $request, OrderItem $item): RedirectResponse
    {
        $this->service->updateDraft($item, $request->integer('quantity'), $request->input('comment'), $request->user());

        return back();
    }

    public function removeItem(RemoveItemRequest $request, OrderItem $item): RedirectResponse
    {
        $this->service->removeItem($item, $request->user(), $request->input('reason'));

        return back();
    }

    public function send(Request $request, Order $order): RedirectResponse
    {
        $this->service->sendToKitchen($order, $request->user());

        return back()->with('success', __('crm.order.sent'));
    }

    public function confirmQr(Request $request, Order $order, int $batch): RedirectResponse
    {
        $this->service->confirmQrBatch($order, $batch, $request->user());

        return back()->with('success', __('crm.order.sent'));
    }

    public function rejectQr(Request $request, Order $order, int $batch): RedirectResponse
    {
        $this->service->rejectQrBatch($order, $batch, $request->user());

        return back()->with('success', __('crm.order.rejected'));
    }

    public function served(Request $request, Order $order, int $batch): RedirectResponse
    {
        $this->service->markServed($order, $batch, $request->user());

        return back();
    }

    public function bill(Request $request, Order $order): RedirectResponse
    {
        $this->service->requestBill($order, $request->user());

        return redirect()->route('waiter.index')->with('success', __('crm.order.bill_requested'));
    }

    public function transfer(TransferRequest $request, Order $order): RedirectResponse
    {
        $this->service->transfer($order, User::findOrFail($request->integer('waiter_id')), $request->user());

        return redirect()->route('waiter.index')->with('success', __('crm.order.transferred'));
    }
}
