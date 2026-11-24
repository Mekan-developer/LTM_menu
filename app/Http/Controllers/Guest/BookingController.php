<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\PublicBookingRequest;
use App\Repositories\MenuRepository;
use App\Services\BookingService;
use App\Services\SettingsService;
use App\Support\MenuPresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Бронь на странице для гостей (ТЗ 2.2): без регистрации, стол назначит администратор. */
class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $service,
        private readonly MenuRepository $menu,
        private readonly MenuPresenter $presenter,
        private readonly SettingsService $settings,
    ) {}

    public function create(): Response
    {
        abort_unless((bool) $this->settings->get('online_booking'), 404);

        return Inertia::render('Guest/Booking', [
            'restaurant' => $this->settings->get('restaurant_name'),
            'menu' => $this->presenter->present($this->menu->activeMenu(), hideStopped: true),
            'done' => session('booking_done'),
        ]);
    }

    public function store(PublicBookingRequest $request): RedirectResponse
    {
        abort_unless((bool) $this->settings->get('online_booking'), 404);

        $booking = $this->service->create($request->validated(), 'guest');

        return redirect()->route('guest.booking')->with('booking_done', [
            'starts_at' => $booking->starts_at->toIso8601String(),
            'guests_count' => $booking->guests_count,
        ]);
    }
}
