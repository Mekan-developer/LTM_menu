<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Costing\ProductRequest;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\CostingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Справочник продуктов (ТЗ 2.3): название, единица, цена закупки. */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CostingService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Costing/Products', [
            'products' => $this->products->all()->map(fn (Product $p) => self::row($p))->values(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $this->service->createProduct($request->validated());

        return back()->with('success', __('crm.saved'));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->service->updateProduct($product, $request->validated(), $request->user());

        return back()->with('success', __('crm.saved'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->service->deleteProduct($product);

        return back()->with('success', __('crm.deleted'));
    }

    /** @return array<string, mixed> */
    public static function row(Product $p): array
    {
        return [
            'id' => $p->id,
            'name_tm' => $p->name_tm,
            'name_ru' => $p->name_ru,
            'unit' => $p->unit->value,
            'price' => $p->price,
            'dishes_count' => (int) ($p->getAttribute('dishes_count') ?? 0),
        ];
    }
}
