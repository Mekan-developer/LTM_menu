<?php

namespace App\Http\Controllers\Kitchen;

use App\Enums\Permission;
use App\Enums\Station;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\KitchenService;
use App\Services\SettingsService;
use App\Support\StopListScreen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Экран кухни/бара (ТВ или планшет). */
class KitchenController extends Controller
{
    public function __construct(
        private readonly KitchenService $kitchen,
        private readonly SettingsService $settings,
        private readonly StopListScreen $stopList,
    ) {}

    public function index(Request $request, ?string $station = null): Response
    {
        $station = Station::tryFrom($station ?? 'kitchen') ?? Station::Kitchen;

        return Inertia::render('Kitchen/Index', [
            'station' => $station->value,
            'board' => fn () => $this->kitchen->board($station),
            'thresholds' => [
                'warn' => (int) $this->settings->get('kitchen_warn_minutes'),
                'late' => (int) $this->settings->get('kitchen_late_minutes'),
            ],
            // Стоп-лист в боковой панели: грузится, только когда панель открыли.
            'stopList' => Inertia::optional(fn () => $request->user()?->hasPermission(Permission::StopList)
                ? $this->stopList->data()
                : null),
        ]);
    }

    public function ready(Request $request, Order $order, int $batch): RedirectResponse
    {
        $data = $request->validate(['station' => ['required', Rule::enum(Station::class)]]);

        $this->kitchen->markReady($order, $batch, Station::from($data['station']));

        return back();
    }
}
