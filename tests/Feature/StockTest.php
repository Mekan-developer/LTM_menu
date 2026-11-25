<?php

use App\Enums\DishKind;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\SystemRole;
use App\Models\ActionLog;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\DishIngredient;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Support\Str;

function balanceOf(Product $p): float
{
    return (float) (StockBalance::where('product_id', $p->id)->value('quantity') ?? 0);
}

/** Счёт к оплате: блюда × количество, всё уже подано. */
function stockOrder(array $dishes): Order
{
    $order = Order::create([
        'public_token' => Str::random(32),
        'dining_table_id' => DiningTable::factory()->create()->id,
        'source' => OrderSource::Waiter->value,
        'status' => OrderStatus::BillRequested->value,
        'opened_at' => now(),
    ]);

    foreach ($dishes as [$dish, $qty]) {
        $order->items()->create([
            'dish_id' => $dish->id, 'name_tm' => $dish->name_tm, 'name_ru' => $dish->name_ru, 'price' => $dish->price,
            'quantity' => $qty, 'station' => 'kitchen', 'status' => OrderItemStatus::Served->value, 'batch' => 1,
        ]);
    }

    return $order;
}

beforeEach(function () {
    $this->actingAs(staff(SystemRole::Owner));
});

it('приход увеличивает остаток и обновляет цену продукта', function () {
    $flour = Product::factory()->create(['unit' => 'kg', 'price' => 1500]);

    $this->post(route('stock.receipts.store'), [
        'lines' => [['product_id' => $flour->id, 'quantity' => '25', 'price' => '18,50']],
        'note' => 'базар',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $m = StockMovement::sole();

    expect(balanceOf($flour))->toBe(25.0)
        ->and($flour->fresh()->price)->toBe(1850)
        ->and($m->amount)->toBe(46250)
        ->and($m->document->total)->toBe(46250)
        ->and(ActionLog::where('action', 'stock_receipt')->exists())->toBeTrue()
        ->and(ActionLog::where('action', 'product_price_changed')->sole()->payload)->toMatchArray(['old' => 1500, 'new' => 1850]);
});

it('оплата списывает продукты по составу блюд; готовая продукция — 1:1; блюдо без калькуляции — ничего', function () {
    $flour = Product::factory()->create(['unit' => 'kg', 'price' => 2000]);
    $potato = Product::factory()->create(['unit' => 'kg', 'price' => 1000]);
    $pepsiCan = Product::factory()->create(['unit' => 'pcs', 'price' => 600]);

    $pirozhok = Dish::factory()->create(['price' => 1000]);
    DishIngredient::create(['dish_id' => $pirozhok->id, 'product_id' => $flour->id, 'quantity' => 0.1]);
    DishIngredient::create(['dish_id' => $pirozhok->id, 'product_id' => $potato->id, 'quantity' => 0.05]);
    $pepsi = Dish::factory()->create(['price' => 1200, 'kind' => DishKind::Resale]);
    DishIngredient::create(['dish_id' => $pepsi->id, 'product_id' => $pepsiCan->id, 'quantity' => 1]);
    $tea = Dish::factory()->create(['price' => 500]); // без калькуляции

    $order = stockOrder([[$pirozhok, 3], [$pepsi, 2], [$tea, 1]]);

    $this->actingAs(staff(SystemRole::Cashier))
        ->post(route('cashier.pay', $order), ['method' => 'cash'])->assertSessionHasNoErrors();

    expect(balanceOf($flour))->toBe(-0.3) // остаток уходит в минус — продажу не блокирует
        ->and(balanceOf($potato))->toBe(-0.15)
        ->and(balanceOf($pepsiCan))->toBe(-2.0)
        ->and(StockMovement::where('type', 'sale')->where('order_id', $order->id)->count())->toBe(3)
        ->and((int) StockMovement::where('order_id', $order->id)->sum('amount'))->toBe(-600 - 150 - 1200);
});

it('отмена оплаты возвращает списанное на склад, повторно не списывает и не возвращает', function () {
    $meat = Product::factory()->create(['unit' => 'kg', 'price' => 10000]);
    $dish = Dish::factory()->create(['price' => 5000]);
    DishIngredient::create(['dish_id' => $dish->id, 'product_id' => $meat->id, 'quantity' => 0.2]);
    $order = stockOrder([[$dish, 2]]);

    $this->actingAs(staff(SystemRole::Cashier))->post(route('cashier.pay', $order), ['method' => 'card']);
    app(StockService::class)->consumeOrder($order->fresh()); // повтор — без эффекта
    expect(balanceOf($meat))->toBe(-0.4);

    $this->actingAs(staff(SystemRole::Owner))
        ->post(route('cashier.cancel', Payment::sole()), ['reason' => 'ошибка кассира'])->assertSessionHasNoErrors();
    app(StockService::class)->restoreOrder($order->fresh()); // повтор — без эффекта

    expect(balanceOf($meat))->toBe(0.0)
        ->and(StockMovement::where('type', 'sale_return')->count())->toBe(1);
});

it('ручное списание с причиной; «другое» — только с комментарием', function () {
    $milk = Product::factory()->create(['unit' => 'l', 'price' => 1200]);
    $this->post(route('stock.receipts.store'), ['lines' => [['product_id' => $milk->id, 'quantity' => 10, 'price' => 12]]]);

    $this->post(route('stock.write-offs.store'), ['lines' => [['product_id' => $milk->id, 'quantity' => 2]], 'reason' => 'other'])
        ->assertSessionHasErrors('note');

    $this->post(route('stock.write-offs.store'), ['lines' => [['product_id' => $milk->id, 'quantity' => '1,5']], 'reason' => 'spoiled'])
        ->assertSessionHasNoErrors();

    expect(balanceOf($milk))->toBe(8.5)
        ->and(StockMovement::where('type', 'write_off')->sole()->amount)->toBe(-1800)
        ->and(ActionLog::where('action', 'stock_write_off')->sole()->payload['reason'])->toBe('spoiled');
});

it('инвентаризация исправляет остатки и считает расхождение в TMT', function () {
    $flour = Product::factory()->create(['unit' => 'kg', 'price' => 2000]);
    $oil = Product::factory()->create(['unit' => 'l', 'price' => 3000]);
    $salt = Product::factory()->create(['unit' => 'kg', 'price' => 500]);
    $this->post(route('stock.receipts.store'), ['lines' => [
        ['product_id' => $flour->id, 'quantity' => 10, 'price' => 20],
        ['product_id' => $oil->id, 'quantity' => 5, 'price' => 30],
        ['product_id' => $salt->id, 'quantity' => 1, 'price' => 5],
    ]]);

    $this->post(route('stock.inventory.store'), ['counts' => [
        ['product_id' => $flour->id, 'actual' => '8'],   // недостача 2 кг = −40 TMT
        ['product_id' => $oil->id, 'actual' => '5,5'],   // излишек 0,5 л = +15 TMT
        ['product_id' => $salt->id, 'actual' => '1'],    // сошлось — движения нет
    ]])->assertSessionHasNoErrors();

    expect(balanceOf($flour))->toBe(8.0)->and(balanceOf($oil))->toBe(5.5)
        ->and(StockMovement::where('type', 'inventory')->count())->toBe(2)
        ->and(ActionLog::where('action', 'stock_inventory')->sole()->payload)->toMatchArray(['shortage' => -4000, 'surplus' => 1500]);
});

it('«Нужно закупить»: ниже минимума, сколько докупить; минус подсвечивается', function () {
    $flour = Product::factory()->create(['unit' => 'kg', 'price' => 2000]);
    $sugar = Product::factory()->create(['unit' => 'kg', 'price' => 1500]);
    $this->post(route('stock.receipts.store'), ['lines' => [['product_id' => $flour->id, 'quantity' => 3, 'price' => 20]]]);
    $this->put(route('stock.minimum', $flour), ['min' => 10])->assertSessionHasNoErrors();
    $this->put(route('stock.minimum', $sugar), ['min' => 2]);

    $this->get(route('stock.index'))
        ->assertInertia(fn ($page) => $page->component('Stock/Index')
            ->where('summary.to_buy', 2)
            ->where('summary.value', 6000)
            ->where('balances', fn ($rows) => collect($rows)->firstWhere('id', $flour->id)['to_buy'] == 7
                && collect($rows)->firstWhere('id', $sugar->id)['to_buy'] == 2));

    $this->get(route('stock.to-buy.export'))->assertOk();
});

it('история показывает документ с расчётным и фактическим остатком', function () {
    $flour = Product::factory()->create(['unit' => 'kg', 'price' => 2000]);
    $this->post(route('stock.receipts.store'), ['lines' => [['product_id' => $flour->id, 'quantity' => 10, 'price' => 20]]]);
    $this->post(route('stock.inventory.store'), ['counts' => [['product_id' => $flour->id, 'actual' => 7]]]);
    $doc = StockMovement::where('type', 'inventory')->sole()->document_id;

    $this->get(route('stock.history', ['document' => $doc]))
        ->assertInertia(fn ($page) => $page->has('movements.data', 1)
            ->where('movements.data.0.expected', 10)
            ->where('movements.data.0.balance_after', 7));
});

it('продукт с историей склада удалить нельзя', function () {
    $p = Product::factory()->create();
    $this->post(route('stock.receipts.store'), ['lines' => [['product_id' => $p->id, 'quantity' => 1, 'price' => 1]]]);

    $this->delete(route('costing.products.destroy', $p))->assertSessionHasErrors('product');
});

it('без права «Остатки» склад закрыт; формы открываются', function () {
    $this->get(route('stock.create', 'receipt'))->assertInertia(fn ($p) => $p->component('Stock/Document')->where('type', 'receipt'));
    $this->get(route('stock.inventory'))->assertOk();

    $this->actingAs(staff(SystemRole::HallAdmin))->get(route('stock.index'))->assertForbidden();
});
