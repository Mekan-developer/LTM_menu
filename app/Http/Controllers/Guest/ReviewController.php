<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\ReviewRequest;
use App\Repositories\ReviewRepository;
use App\Services\ReviewService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Оценка гостя по одноразовой ссылке (ТЗ 2.9). Без входа. */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewRepository $reviews,
        private readonly ReviewService $service,
        private readonly SettingsService $settings,
    ) {}

    public function show(string $token): Response
    {
        $review = $this->reviews->findByToken($token);
        abort_if($review === null, 404);
        $review->loadMissing('order.table:id,number');

        return Inertia::render('Guest/Review', [
            'token' => $token,
            'table' => $review->order?->table?->number,
            'submitted' => $review->isSubmitted(),
            'restaurant' => $this->settings->get('restaurant_name'),
        ]);
    }

    public function store(ReviewRequest $request, string $token): RedirectResponse
    {
        $review = $this->reviews->findByToken($token);
        abort_if($review === null, 404);

        $this->service->submit($review, $request->integer('rating'), $request->input('comment'));

        return redirect()->route('guest.review', $token);
    }
}
