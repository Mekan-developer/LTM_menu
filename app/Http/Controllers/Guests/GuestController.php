<?php

namespace App\Http\Controllers\Guests;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\GuestRequest;
use App\Models\BonusTransaction;
use App\Models\Guest;
use App\Repositories\BookingRepository;
use App\Repositories\GuestRepository;
use App\Services\BonusService;
use App\Services\GuestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** База гостей (ТЗ 2.1): поиск, карточка с историей, экспорт в Excel. */
class GuestController extends Controller
{
    public function __construct(
        private readonly GuestRepository $guests,
        private readonly GuestService $service,
        private readonly BookingRepository $bookings,
        private readonly BonusService $bonuses,
    ) {}

    public function index(Request $request): Response
    {
        $page = $this->guests->search($request->string('q')->value());

        return Inertia::render('Guests/Index', [
            'q' => $request->string('q')->value(),
            'guests' => [
                'data' => collect($page->items())->map(fn (Guest $g) => self::row($g))->values(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'canExport' => $request->user()->hasPermission(Permission::ExportGuests),
        ]);
    }

    public function show(Guest $guest): Response
    {
        $stats = $this->guests->stats($guest) ?? $guest;

        return Inertia::render('Guests/Show', [
            'guest' => self::row($stats) + [
                'address' => $guest->address,
                'comment' => $guest->comment,
                'source' => $guest->source,
                'created_at' => $guest->created_at?->toIso8601String(),
            ],
            'orders' => $this->guests->orders($guest)->map(fn ($o) => [
                'id' => (int) $o->id,
                'status' => $o->status,
                'channel' => $o->channel, // доставка и самовывоз — без стола (ТЗ 2.6)
                'total' => (int) $o->total,
                'discount_percent' => (int) $o->discount_percent,
                'paid_at' => $o->paid_at ? Carbon::parse($o->paid_at)->toIso8601String() : null,
                'table' => $o->table,
                'waiter' => $o->waiter,
            ]),
            'bookings' => $this->bookings->forGuest($guest)->map(fn ($b) => [
                'id' => $b->id,
                'starts_at' => $b->starts_at->toIso8601String(),
                'guests_count' => $b->guests_count,
                'status' => $b->status->value,
                'table' => $b->table?->number,
            ])->values(),
            // Бонусы (ТЗ 2.8): последние операции с балансом после каждой.
            'bonuses' => $this->bonuses->history($guest)->map(fn (BonusTransaction $b) => [
                'id' => $b->id,
                'type' => $b->type->value,
                'amount' => $b->amount,
                'balance_after' => $b->balance_after,
                'order_id' => $b->order_id,
                'user' => $b->user?->name,
                'created_at' => $b->created_at->toIso8601String(),
            ])->values(),
            'reviews' => $this->guests->reviews($guest)->map(fn ($r) => [
                'id' => (int) $r->id,
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'order_id' => (int) $r->order_id,
                'submitted_at' => Carbon::parse($r->submitted_at)->toIso8601String(),
            ]),
        ]);
    }

    public function store(GuestRequest $request): RedirectResponse
    {
        $guest = $this->service->create($request->validated(), $request->user());

        return redirect()->route('guests.show', $guest)->with('success', __('crm.saved'));
    }

    public function update(GuestRequest $request, Guest $guest): RedirectResponse
    {
        $this->service->update($guest, $request->validated());

        return back()->with('success', __('crm.saved'));
    }

    public function destroy(Guest $guest): RedirectResponse
    {
        $this->service->delete($guest);

        return redirect()->route('guests.index')->with('success', __('crm.deleted'));
    }

    public function export(Request $request): HttpResponse
    {
        $content = $this->service->export($request->user())->toString();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="guests_'.now()->format('Y-m-d').'.xlsx"',
            'Content-Length' => (string) strlen($content),
        ]);
    }

    /** @return array<string, mixed> */
    private static function row(Guest $g): array
    {
        $visits = (int) $g->getAttribute('visits');
        $spent = (int) $g->getAttribute('spent');
        $last = $g->getAttribute('last_visit');

        return [
            'id' => $g->id,
            'name' => $g->name,
            'phone' => $g->phone,
            'birthday' => $g->birthday?->toDateString(),
            'visits' => $visits,
            'spent' => $spent,
            'avg_check' => $visits > 0 ? intdiv($spent, $visits) : 0,
            'last_visit' => $last ? Carbon::parse($last)->toIso8601String() : null,
            'bonus_balance' => $g->bonus_balance,
        ];
    }
}
