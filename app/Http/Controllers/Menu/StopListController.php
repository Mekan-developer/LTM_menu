<?php

namespace App\Http\Controllers\Menu;

use App\Enums\StopMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\StopRequest;
use App\Models\StopListEntry;
use App\Services\StopListService;
use App\Support\StopListScreen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Стоп-лист: всё, что сейчас в стопе, на одном экране (ТЗ 2.3). */
class StopListController extends Controller
{
    public function __construct(
        private readonly StopListScreen $screen,
        private readonly StopListService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('StopList/Index', $this->screen->data());
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
