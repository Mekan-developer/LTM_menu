<?php

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Repositories\ReviewRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Отзывы гостей в админке — право «Отзывы» (ТЗ 2.9). */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewRepository $reviews) {}

    public function index(Request $request): Response
    {
        $onlyLow = $request->boolean('low');
        $page = $this->reviews->submitted($onlyLow);

        // Открыли раздел — уведомление о низких оценках прочитано.
        $unseenIds = collect($page->items())
            ->filter(fn (Review $r) => $r->isLow() && $r->seen_at === null)
            ->pluck('id')->all();
        $this->reviews->markLowSeen();

        return Inertia::render('Reviews/Index', [
            'filter' => ['low' => $onlyLow],
            'stats' => $this->reviews->stats(),
            'reviews' => [
                'data' => collect($page->items())->map(fn (Review $r) => [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'comment' => $r->comment,
                    'submitted_at' => $r->submitted_at?->toIso8601String(),
                    'table' => $r->order?->table?->number,
                    'order_id' => $r->order_id,
                    'total' => $r->order?->total,
                    'waiter' => $r->waiter?->name,
                    'is_new' => in_array($r->id, $unseenIds, true),
                ])->values(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
