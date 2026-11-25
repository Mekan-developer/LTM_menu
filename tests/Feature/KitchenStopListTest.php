<?php

use App\Enums\SystemRole;
use App\Models\Category;
use App\Models\Dish;

it('экран кухни не грузит стоп-лист, пока панель не открыли', function () {
    $this->actingAs(staff(SystemRole::Cook));

    $this->get(route('kitchen.index', 'kitchen'))
        ->assertInertia(fn ($page) => $page->component('Kitchen/Index')->missing('stopList'));
});

it('панель стоп-листа на кухне: данные по запросу и постановка на стоп без ухода с экрана', function () {
    $this->actingAs(staff(SystemRole::Cook));
    $dish = Dish::factory()->for(Category::factory())->create();

    $this->get(route('kitchen.index', 'kitchen'))
        ->assertInertia(fn ($page) => $page
            ->reloadOnly('stopList', fn ($reload) => $reload
                ->where('stopList.entries', [])
                ->has('stopList.categories', 1)
                ->has('stopList.modes', 3)));

    $this->from(route('kitchen.index', 'kitchen'))
        ->post(route('stop-list.store'), ['type' => 'dish', 'id' => $dish->id, 'mode' => 'manual'])
        ->assertRedirect(route('kitchen.index', 'kitchen'));

    $this->get(route('kitchen.index', 'kitchen'))
        ->assertInertia(fn ($page) => $page
            ->reloadOnly('stopList', fn ($reload) => $reload
                ->has('stopList.entries', 1)
                ->where('stopList.entries.0.name_ru', $dish->name_ru)));
});

it('без права «Стоп-меню» панель на кухне пустая', function () {
    $cook = staff(SystemRole::Cook);
    $cook->role->permissions()->where('permission', 'stop_list')->delete();
    $this->actingAs($cook->fresh());

    $this->get(route('kitchen.index', 'kitchen'))
        ->assertInertia(fn ($page) => $page
            ->reloadOnly('stopList', fn ($reload) => $reload->where('stopList', null)));
});
