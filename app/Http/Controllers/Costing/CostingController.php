<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Costing\RecipeRequest;
use App\Models\Dish;
use App\Models\DishIngredient;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\CostingExporter;
use App\Services\CostingService;
use App\Support\Costing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Калькуляция блюд (ТЗ 2.3): себестоимость порции, наценка, доля себестоимости; печать и Excel. */
class CostingController extends Controller
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CostingService $service,
        private readonly CostingExporter $exporter,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Costing/Index', [
            'dishes' => $this->products->dishesWithRecipes()->map(fn (Dish $d) => self::summary($d))->values(),
        ]);
    }

    public function edit(Dish $dish): Response
    {
        $dish->load(['category:id,name_tm,name_ru', 'ingredients.product']);

        return Inertia::render('Costing/Dish', [
            'dish' => self::detail($dish),
            'products' => $this->products->all()->map(fn (Product $p) => ProductController::row($p))->values(),
        ]);
    }

    public function update(RecipeRequest $request, Dish $dish): RedirectResponse
    {
        $this->service->saveRecipe($dish, $request->lines(), $request->user());

        return back()->with('success', __('crm.saved'));
    }

    /** Excel: одно блюдо (?dish=ID) или сводка по всем блюдам. */
    public function export(Request $request): HttpResponse
    {
        $dish = $request->filled('dish')
            ? Dish::query()->with(['category:id,name_tm,name_ru', 'ingredients.product'])->findOrFail($request->integer('dish'))
            : null;

        $xlsx = $dish ? $this->exporter->dish($dish) : $this->exporter->all($this->products->dishesWithRecipes());
        $content = $xlsx->toString();
        $name = $dish ? 'costing_dish_'.$dish->id.'.xlsx' : 'costing_'.now()->format('Y-m-d').'.xlsx';

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Content-Length' => (string) strlen($content),
        ]);
    }

    /** @return array<string, mixed> */
    private static function summary(Dish $d): array
    {
        return [
            'id' => $d->id,
            'name_tm' => $d->name_tm,
            'name_ru' => $d->name_ru,
            'kind' => $d->kind->value,
            'is_active' => $d->is_active,
            'category' => $d->category ? ['id' => $d->category->id, 'name_tm' => $d->category->name_tm, 'name_ru' => $d->category->name_ru] : null,
            'price' => $d->price,
            'lines' => $d->ingredients->count(),
            ...Costing::of($d),
        ];
    }

    /** @return array<string, mixed> */
    private static function detail(Dish $d): array
    {
        return self::summary($d) + [
            'weight' => $d->weight,
            'ingredients' => $d->ingredients->map(fn (DishIngredient $i) => [
                'product_id' => $i->product_id,
                'quantity' => $i->quantity,
                'cost' => $i->cost(),
            ])->values(),
        ];
    }
}
