<?php

use App\Enums\DishKind;
use App\Enums\SystemRole;
use App\Models\ActionLog;
use App\Models\Dish;
use App\Models\DishIngredient;
use App\Models\Product;
use App\Support\Costing;

beforeEach(function () {
    $this->actingAs(staff(SystemRole::Owner));
});

it('считает себестоимость порции, наценку и долю себестоимости', function () {
    $flour = Product::factory()->create(['unit' => 'kg', 'price' => 2000]);   // 20 TMT/кг
    $potato = Product::factory()->create(['unit' => 'kg', 'price' => 1000]);  // 10 TMT/кг
    $dish = Dish::factory()->create(['price' => 1000]);                        // 10 TMT

    $this->put(route('costing.update', $dish), ['lines' => [
        ['product_id' => $flour->id, 'quantity' => 0.1],   // 100 г → 2 TMT
        ['product_id' => $potato->id, 'quantity' => 0.05], // 50 г → 0,50 TMT
    ]])->assertSessionHasNoErrors();

    $c = Costing::of($dish->fresh()->load('ingredients.product'));

    expect($c['cost'])->toBe(250)
        ->and($c['markup'])->toBe(300.0)
        ->and($c['share'])->toBe(25.0);
});

it('новая цена продукта меняет себестоимость блюд и пишется в журнал', function () {
    $meat = Product::factory()->create(['unit' => 'kg', 'price' => 10000]);
    $dish = Dish::factory()->create(['price' => 5000]);
    DishIngredient::create(['dish_id' => $dish->id, 'product_id' => $meat->id, 'quantity' => 0.2]);

    $this->put(route('costing.products.update', $meat), [
        'name_tm' => $meat->name_tm, 'name_ru' => $meat->name_ru, 'unit' => 'kg', 'price' => '120',
    ])->assertSessionHasNoErrors();

    expect(Costing::of($dish->fresh()->load('ingredients.product'))['cost'])->toBe(2400)
        ->and(ActionLog::where('action', 'product_price_changed')->sole()->payload)
        ->toMatchArray(['old' => 10000, 'new' => 12000]);
});

it('изменение состава пишется в журнал, повторное сохранение того же — нет', function () {
    $salt = Product::factory()->create(['name_ru' => 'Соль']);
    $dish = Dish::factory()->create();
    $lines = ['lines' => [['product_id' => $salt->id, 'quantity' => 0.002]]];

    $this->put(route('costing.update', $dish), $lines)->assertSessionHasNoErrors();
    $this->put(route('costing.update', $dish), $lines)->assertSessionHasNoErrors();

    expect(ActionLog::where('action', 'recipe_changed')->sole()->payload['new'])->toBe(['Соль' => 0.002]);
});

it('у готовой продукции в составе один товар; продукт не повторяется', function () {
    [$a, $b] = Product::factory()->count(2)->create(['unit' => 'pcs']);
    $pepsi = Dish::factory()->create(['kind' => DishKind::Resale]);

    $this->put(route('costing.update', $pepsi), ['lines' => [
        ['product_id' => $a->id, 'quantity' => 1], ['product_id' => $b->id, 'quantity' => 1],
    ]])->assertSessionHasErrors('lines');

    $dish = Dish::factory()->create();
    $this->put(route('costing.update', $dish), ['lines' => [
        ['product_id' => $a->id, 'quantity' => 1], ['product_id' => $a->id, 'quantity' => 2],
    ]])->assertSessionHasErrors('lines');

    $this->put(route('costing.update', $pepsi), ['lines' => [['product_id' => $a->id, 'quantity' => 1]]])
        ->assertSessionHasNoErrors();
    expect($pepsi->ingredients()->count())->toBe(1);
});

it('продукт из состава блюда удалить нельзя, неиспользуемый — можно', function () {
    $used = Product::factory()->create();
    $free = Product::factory()->create();
    DishIngredient::create(['dish_id' => Dish::factory()->create()->id, 'product_id' => $used->id, 'quantity' => 1]);

    $this->delete(route('costing.products.destroy', $used))->assertSessionHasErrors('product');
    $this->delete(route('costing.products.destroy', $free))->assertSessionHasNoErrors();

    expect(Product::pluck('id')->all())->toBe([$used->id]);
});

it('экраны калькуляции и выгрузка в Excel', function () {
    $p = Product::factory()->create(['price' => 3000]);
    $dish = Dish::factory()->create(['price' => 9000]);
    DishIngredient::create(['dish_id' => $dish->id, 'product_id' => $p->id, 'quantity' => 0.5]);
    Dish::factory()->create(); // без калькуляции

    $this->get(route('costing.index'))
        ->assertInertia(fn ($page) => $page->component('Costing/Index')
            ->has('dishes', 2)
            ->where('dishes.0.cost', 1500)
            ->where('dishes.1.has_recipe', false));

    $this->get(route('costing.edit', $dish))
        ->assertInertia(fn ($page) => $page->component('Costing/Dish')
            ->where('dish.ingredients.0.cost', 1500)
            ->has('products', 1));

    $this->get(route('costing.products.index'))
        ->assertInertia(fn ($page) => $page->where('products.0.dishes_count', 1));

    $this->get(route('costing.export', ['dish' => $dish->id]))->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $this->get(route('costing.export'))->assertOk();
});

it('без права «Калькуляция» экраны закрыты', function () {
    $this->actingAs(staff(SystemRole::HallAdmin));

    $this->get(route('costing.index'))->assertForbidden();
    $this->get(route('costing.products.index'))->assertForbidden();
});
