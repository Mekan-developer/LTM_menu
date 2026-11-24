<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Review;
use App\Services\SettingsService;
use Illuminate\Support\Str;

function paidOrderWithReview(): Order
{
    $order = Order::create([
        'public_token' => Str::random(32),
        'dining_table_id' => DiningTable::factory()->create()->id,
        'waiter_id' => staff(SystemRole::Waiter)->id,
        'source' => OrderSource::Waiter->value,
        'status' => OrderStatus::BillRequested->value,
        'opened_at' => now(),
        'subtotal' => 10000,
        'total' => 10000,
    ]);
    $order->items()->create([
        'name_tm' => 'Palaw', 'name_ru' => 'Плов', 'price' => 10000, 'quantity' => 1,
        'station' => 'kitchen', 'status' => OrderItemStatus::Served->value, 'batch' => 1,
    ]);

    test()->actingAs(staff(SystemRole::Cashier))->post(route('cashier.pay', $order), ['method' => 'cash']);
    auth()->logout();

    return $order->fresh();
}

it('после оплаты создаётся одноразовая ссылка на отзыв, привязанная к официанту', function () {
    $order = paidOrderWithReview();
    $review = Review::sole();

    expect($review->order_id)->toBe($order->id)
        ->and($review->waiter_id)->toBe($order->waiter_id);

    $this->getJson(route('guest.order.status', $order->public_token))
        ->assertJsonPath('review_url', route('guest.review', $review->token));
});

it('гость оценивает счёт один раз', function () {
    paidOrderWithReview();
    $review = Review::sole();

    $this->post(route('guest.review.store', $review->token), ['rating' => 5, 'comment' => 'Очень вкусно'])
        ->assertRedirect(route('guest.review', $review->token));

    $this->post(route('guest.review.store', $review->token), ['rating' => 1])
        ->assertSessionHasErrors('rating');

    expect($review->fresh()->rating)->toBe(5)
        ->and($review->fresh()->comment)->toBe('Очень вкусно');
});

it('низкая оценка видна счётчиком в админке, пока раздел не открыли', function () {
    paidOrderWithReview();
    $review = Review::sole();
    $this->post(route('guest.review.store', $review->token), ['rating' => 2]);

    $admin = staff(SystemRole::HallAdmin);
    $this->actingAs($admin)->get(route('waiter.index'))
        ->assertInertia(fn ($page) => $page->where('reviews_unseen', 1));

    $this->get(route('reviews.index'))->assertOk();

    $this->get(route('waiter.index'))
        ->assertInertia(fn ($page) => $page->where('reviews_unseen', 0));
});

it('раздел отзывов закрыт без права «Отзывы»', function () {
    $this->actingAs(staff(SystemRole::Waiter))->get(route('reviews.index'))->assertForbidden();
});

it('если отзывы выключены в настройках, ссылка не создаётся', function () {
    app(SettingsService::class)->update(['reviews_enabled' => false]);

    paidOrderWithReview();

    expect(Review::count())->toBe(0);
});
