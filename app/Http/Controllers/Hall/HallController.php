<?php

namespace App\Http\Controllers\Hall;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hall\HallLayoutRequest;
use App\Http\Requests\Hall\HallRequest;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\User;
use App\Repositories\HallRepository;
use App\Services\HallService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Схема зала (ТЗ 2.2): режим «Работа» для зала и «Редактор» для права «Столы и QR». */
class HallController extends Controller
{
    public function __construct(
        private readonly HallRepository $halls,
        private readonly HallService $service,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $halls = $this->halls->all();
        $hall = $halls->firstWhere('id', $request->integer('hall')) ?? $halls->first();
        $tableId = $request->integer('table');

        return Inertia::render('Hall/Index', [
            'halls' => $halls->map(fn (Hall $h) => [
                'id' => $h->id,
                'name_tm' => $h->name_tm,
                'name_ru' => $h->name_ru,
                'width' => $h->width,
                'height' => $h->height,
                'tables_count' => (int) $h->getAttribute('tables_count'),
            ])->values(),
            'hallId' => $hall?->id,
            'board' => fn () => $hall ? $this->service->board($hall, $user) : ['tables' => [], 'elements' => []],
            // Панель выбранного стола грузится частичной перезагрузкой (?table=ID).
            'detail' => fn () => $this->detail($hall, $tableId, $user),
            'waiters' => fn () => $user->hasPermission(Permission::TransferTables)
                ? User::query()->where('is_active', true)->with('role.permissions')->get(['id', 'name', 'role_id'])
                    ->filter(fn (User $u) => $u->hasPermission(Permission::AreaWaiter))
                    ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()
                : [],
            'can' => [
                'edit' => $user->hasPermission(Permission::Tables),
                'work' => $user->hasPermission(Permission::AreaWaiter),
                'transfer' => $user->hasPermission(Permission::TransferTables),
            ],
        ]);
    }

    public function layout(HallLayoutRequest $request, Hall $hall): RedirectResponse
    {
        $this->service->saveLayout($hall, $request->validated('tables'), $request->validated('elements'), $request->user());

        return back()->with('success', __('crm.hall.saved'));
    }

    public function store(HallRequest $request): RedirectResponse
    {
        $hall = $this->service->createHall($request->validated());

        return redirect()->route('hall.index', ['hall' => $hall->id, 'mode' => 'edit'])->with('success', __('crm.saved'));
    }

    public function update(HallRequest $request, Hall $hall): RedirectResponse
    {
        $this->service->updateHall($hall, $request->validated());

        return back()->with('success', __('crm.saved'));
    }

    public function destroy(Hall $hall): RedirectResponse
    {
        $this->service->deleteHall($hall);

        return redirect()->route('hall.index')->with('success', __('crm.deleted'));
    }

    /** @return array<string, mixed>|null */
    private function detail(?Hall $hall, int $tableId, User $user): ?array
    {
        if ($hall === null || $tableId === 0) {
            return null;
        }

        $table = DiningTable::query()->where('hall_id', $hall->id)->where('is_active', true)->find($tableId);

        return $table ? $this->service->tableDetail($table, $user) : null;
    }
}
