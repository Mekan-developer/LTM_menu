<?php

namespace App\Http\Controllers\Menu;

use App\Enums\Station;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\CategoryRequest;
use App\Http\Requests\Menu\DishRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\DishResource;
use App\Models\Category;
use App\Models\Dish;
use App\Repositories\MenuRepository;
use App\Services\MenuService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Меню и цены (ТЗ 2.3): категории и блюда на TM/RU, фото, цех. */
class MenuController extends Controller
{
    public function __construct(
        private readonly MenuRepository $menu,
        private readonly MenuService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Menu/Index', [
            'categories' => CategoryResource::collection($this->menu->categoriesWithDishes()),
        ]);
    }

    public function storeCategory(CategoryRequest $request): RedirectResponse
    {
        $this->service->createCategory($request->validated());

        return back()->with('success', __('crm.saved'));
    }

    public function updateCategory(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->service->updateCategory($category, $request->validated());

        return back()->with('success', __('crm.saved'));
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        $this->service->deleteCategory($category);

        return back()->with('success', __('crm.deleted'));
    }

    public function createDish(): Response
    {
        return Inertia::render('Menu/DishForm', $this->formData(null));
    }

    public function storeDish(DishRequest $request): RedirectResponse
    {
        $this->service->createDish($request->dishData(), $request->file('photo'));

        return redirect()->route('menu.index')->with('success', __('crm.saved'));
    }

    public function editDish(Dish $dish): Response
    {
        return Inertia::render('Menu/DishForm', $this->formData($dish));
    }

    public function updateDish(DishRequest $request, Dish $dish): RedirectResponse
    {
        $this->service->updateDish($dish, $request->dishData(), $request->file('photo'), $request->boolean('remove_photo'));

        return redirect()->route('menu.index')->with('success', __('crm.saved'));
    }

    public function destroyDish(Dish $dish): RedirectResponse
    {
        $this->service->deleteDish($dish);

        return back()->with('success', __('crm.deleted'));
    }

    private function formData(?Dish $dish): array
    {
        return [
            'dish' => $dish ? new DishResource($dish) : null,
            'categories' => Category::query()->orderBy('sort')->get(['id', 'name_tm', 'name_ru']),
            'stations' => array_map(fn (Station $s) => $s->value, Station::cases()),
        ];
    }
}
