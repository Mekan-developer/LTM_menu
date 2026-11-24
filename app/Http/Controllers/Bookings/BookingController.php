<?php

namespace App\Http\Controllers\Bookings;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\BookingRequest;
use App\Http\Requests\Bookings\BookingStatusRequest;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Repositories\BookingRepository;
use App\Repositories\MenuRepository;
use App\Services\BookingService;
use App\Support\MenuPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Брони на день (ТЗ 2.2): список, создание по звонку, стол, статусы, «гость пришёл». */
class BookingController extends Controller
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly BookingService $service,
        private readonly MenuRepository $menu,
        private readonly MenuPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $day = $this->day($request->string('date')->value());

        return Inertia::render('Bookings/Index', [
            'date' => $day->toDateString(),
            'bookings' => $this->bookings->forDay($day)->map(fn (Booking $b) => self::row($b))->values(),
            // Меню для предзаказа нужно только в форме — грузится частично.
            'menu' => Inertia::optional(fn () => $this->presenter->present($this->menu->activeMenu(), hideStopped: false)),
        ]);
    }

    public function store(BookingRequest $request): RedirectResponse
    {
        $booking = $this->service->create($request->validated(), 'staff', $request->user());

        return redirect()->route('bookings.index', ['date' => $booking->starts_at->toDateString()])
            ->with('success', __('crm.bookings.created'));
    }

    public function update(BookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->service->update($booking, $request->validated(), $request->user());

        return redirect()->route('bookings.index', ['date' => $booking->starts_at->toDateString()])
            ->with('success', __('crm.saved'));
    }

    public function status(BookingStatusRequest $request, Booking $booking): RedirectResponse
    {
        $this->service->setStatus($booking, BookingStatus::from($request->string('status')->value()), $request->user(), $request->input('reason'));

        return back()->with('success', __('crm.bookings.status_saved'));
    }

    /** Столы, свободные от броней на выбранные дату и время. */
    public function freeTables(Request $request): JsonResponse
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i']]);

        return response()->json($this->service->freeTables(
            $request->string('date')->value(),
            $request->string('time')->value(),
            $request->integer('booking') ?: null,
        ));
    }

    private function day(string $date): CarbonImmutable
    {
        try {
            return $date !== '' ? CarbonImmutable::createFromFormat('Y-m-d', $date) ?? CarbonImmutable::today() : CarbonImmutable::today();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    /** @return array<string, mixed> */
    public static function row(Booking $b): array
    {
        return [
            'id' => $b->id,
            'starts_at' => $b->starts_at->toIso8601String(),
            'ends_at' => $b->ends_at->toIso8601String(),
            'guests_count' => $b->guests_count,
            'status' => $b->status->value,
            'source' => $b->source,
            'comment' => $b->comment,
            'cancel_reason' => $b->cancel_reason,
            'order_id' => $b->order_id,
            'guest' => $b->guest ? ['id' => $b->guest->id, 'name' => $b->guest->name, 'phone' => $b->guest->phone, 'comment' => $b->guest->comment] : null,
            'table' => $b->table ? ['id' => $b->table->id, 'number' => $b->table->number, 'seats' => $b->table->seats] : null,
            'items' => $b->items->map(fn (BookingItem $i) => [
                'dish_id' => $i->dish_id, 'name_tm' => $i->name_tm, 'name_ru' => $i->name_ru,
                'price' => $i->price, 'quantity' => $i->quantity,
            ])->values(),
            'next' => array_map(fn (BookingStatus $s) => $s->value, $b->status->next()),
        ];
    }
}
