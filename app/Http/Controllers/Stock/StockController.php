<?php

namespace App\Http\Controllers\Stock;

use App\Enums\StockMovementType;
use App\Enums\WriteOffReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\InventoryRequest;
use App\Http\Requests\Stock\StockLinesRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Repositories\StockRepository;
use App\Services\StockService;
use App\Support\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Склад (ТЗ 2.3 «Остатки»), право «Остатки». */
class StockController extends Controller
{
    public function __construct(
        private readonly StockRepository $stock,
        private readonly StockService $service,
    ) {}

    /** Остатки, минимумы и «Нужно закупить». */
    public function index(): Response
    {
        $balances = $this->stock->balances();

        return Inertia::render('Stock/Index', [
            'balances' => $balances->values(),
            'summary' => [
                'value' => (int) $balances->sum('value'),
                'to_buy' => $balances->where('to_buy', '>', 0)->count(),
                'negative' => $balances->where('quantity', '<', 0)->count(),
            ],
        ]);
    }

    /** Форма прихода или списания. */
    public function create(string $type): Response
    {
        abort_unless(in_array($type, ['receipt', 'write_off'], true), 404);

        return Inertia::render('Stock/Document', [
            'type' => $type,
            'products' => $this->stock->balances()->values(),
            'reasons' => array_map(fn (WriteOffReason $r) => $r->value, WriteOffReason::cases()),
        ]);
    }

    public function storeReceipt(StockLinesRequest $request): RedirectResponse
    {
        $doc = $this->service->receipt($request->lines(), $request->validated('note'), $request->user());

        return redirect()->route('stock.history', ['document' => $doc->id])->with('success', __('crm.stock.receipt_saved'));
    }

    public function storeWriteOff(StockLinesRequest $request): RedirectResponse
    {
        $doc = $this->service->writeOff(
            $request->lines(),
            WriteOffReason::from($request->validated('reason')),
            $request->validated('note'),
            $request->user(),
        );

        return redirect()->route('stock.history', ['document' => $doc->id])->with('success', __('crm.stock.write_off_saved'));
    }

    public function inventory(): Response
    {
        return Inertia::render('Stock/Inventory', [
            'balances' => $this->stock->balances()->values(),
        ]);
    }

    public function storeInventory(InventoryRequest $request): RedirectResponse
    {
        $doc = $this->service->inventory($request->counts(), $request->validated('note'), $request->user());

        return redirect()->route('stock.history', ['document' => $doc->id])->with('success', __('crm.stock.inventory_saved'));
    }

    public function minimum(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['min' => ['required', 'numeric', 'min:0', 'max:99999']]);
        $this->service->setMinimum($product, (float) $data['min']);

        return back()->with('success', __('crm.saved'));
    }

    /** История движений: период (по умолчанию 7 дней), тип, продукт или один документ. */
    public function history(Request $request): Response
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
            'product' => ['nullable', 'integer'],
            'document' => ['nullable', 'integer'],
        ]);

        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : $to->subDays(6)->startOfDay();
        $filters = [
            'type' => $data['type'] ?? null,
            'product' => isset($data['product']) ? (int) $data['product'] : null,
            'document' => isset($data['document']) ? (int) $data['document'] : null,
        ];
        $page = $this->stock->movements($from, $to, $filters);

        return Inertia::render('Stock/History', [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filters' => $filters,
            'types' => array_map(fn (StockMovementType $t) => $t->value, StockMovementType::cases()),
            'products' => Product::query()->orderBy('name_ru')->get(['id', 'name_tm', 'name_ru']),
            'movements' => [
                'data' => collect($page->items())->map(fn (StockMovement $m) => [
                    'id' => $m->id,
                    'type' => $m->type->value,
                    'product' => $m->product ? ['id' => $m->product->id, 'name_tm' => $m->product->name_tm, 'name_ru' => $m->product->name_ru, 'unit' => $m->product->unit->value] : null,
                    'quantity' => $m->quantity,
                    'price' => $m->price,
                    'amount' => $m->amount,
                    'balance_after' => $m->balance_after,
                    // Расчётный остаток до инвентаризации = остаток после − расхождение.
                    'expected' => round($m->balance_after - $m->quantity, 3),
                    'order_id' => $m->order_id,
                    'document_id' => $m->document_id,
                    'reason' => $m->document?->reason?->value,
                    'note' => $m->document?->note,
                    'user' => $m->user?->name,
                    'created_at' => $m->created_at->toIso8601String(),
                ])->values(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** «Нужно закупить» в Excel (ТЗ 2.3). */
    public function exportToBuy(): HttpResponse
    {
        $xlsx = (new XlsxWriter(__('crm.stock.to_buy')))
            ->setWidths([34, 8, 14, 14, 14])
            ->addHeader([
                __('crm.costing.product'), __('crm.costing.unit'), __('crm.stock.balance'),
                __('crm.stock.minimum'), __('crm.stock.need'),
            ]);

        foreach ($this->stock->balances()->where('to_buy', '>', 0) as $b) {
            $xlsx->addRow([
                $b[app()->getLocale() === 'ru' ? 'name_ru' : 'name_tm'],
                __('crm.costing.unit_'.$b['unit']),
                ['v' => $b['quantity'], 's' => 'decimal'],
                ['v' => $b['min'], 's' => 'decimal'],
                ['v' => $b['to_buy'], 's' => 'decimal'],
            ]);
        }

        $content = $xlsx->toString();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="to_buy_'.now()->format('Y-m-d').'.xlsx"',
            'Content-Length' => (string) strlen($content),
        ]);
    }
}
