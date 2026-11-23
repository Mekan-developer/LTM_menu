<?php

namespace App\Http\Controllers\Menu;

use App\Enums\StopMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\StopRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Dish;
use App\Models\StopListEntry;
use App\Repositories\MenuRepository;
use App\Repositories\StopListRepository;
use App\Services\StopListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Стоп-лист: всё, что сейчас в стопе, на одном экране (ТЗ 2.3). */
class StopListController extends Controller
{
    public function __construct(
        private readonly StopListRepository $stopList,
        private readonly MenuRepository $menu,
        private readonly StopListService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('StopList/Index', [
            'entries' => $this->stopList->active()->map(fn (StopListEntry $e) => [
                'id' => $e->id,
                'type' => $e->stoppable_type,
                'name_tm' => $e->stoppable instanceof Dish || $e->stoppable instanceof Category ? $e->stoppable->name_tm : '—',
                'name_ru' => $e->stoppable instanceof Dish || $e->stoppable instanceof Category ? $e->stoppable->name_ru : '—',
                'mode' => $e->mode->value,
                'until' => $e->until?->toIso8601String(),
                'author' => $e->author?->name,
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
            'categories' => CategoryResource::collection($this->menu->categoriesWithDishes()),
            'modes' => array_map(fn (StopMode $m) => $m->value, StopMode::cases()),
        ]);
    }

    public function store(StopRequest $request): RedirectResponse
    {
        $this->service->stop(
            $request->string('type')->value(),
            $request->integer('id'),
            StopMode::from($request->string('mode')->value()),
            $request->input('until'),
            $request->user(),
        );

        return back()->with('success', __('crm.stop.added'));
    }

    public function destroy(Request $request, StopListEntry $entry): RedirectResponse
    {
        $this->service->remove($entry, $request->user());

        return back()->with('success', __('crm.stop.removed'));
    }
}
