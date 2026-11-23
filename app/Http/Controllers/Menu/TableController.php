<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\TableRequest;
use App\Http\Resources\TableResource;
use App\Models\DiningTable;
use App\Repositories\TableRepository;
use App\Services\TableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Столы и их QR-коды. В MVP — список вместо схемы зала. */
class TableController extends Controller
{
    public function __construct(
        private readonly TableRepository $tables,
        private readonly TableService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Tables/Index', [
            'tables' => TableResource::collection($this->tables->all()),
        ]);
    }

    public function store(TableRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return back()->with('success', __('crm.saved'));
    }

    public function update(TableRequest $request, DiningTable $table): RedirectResponse
    {
        $this->service->update($table, $request->validated());

        return back()->with('success', __('crm.saved'));
    }

    public function destroy(DiningTable $table): RedirectResponse
    {
        $this->service->delete($table);

        return back()->with('success', __('crm.deleted'));
    }

    public function regenerate(DiningTable $table): RedirectResponse
    {
        $this->service->regenerateQr($table);

        return back()->with('success', __('crm.tables.qr_regenerated'));
    }

    /** Печать QR: один стол (?table=ID) или все активные. */
    public function print(Request $request): Response
    {
        $tables = $this->tables->active()
            ->when($request->integer('table'), fn ($c, $id) => $c->where('id', $id))
            ->values();

        return Inertia::render('Tables/Print', [
            'tables' => TableResource::collection($tables),
        ]);
    }
}
