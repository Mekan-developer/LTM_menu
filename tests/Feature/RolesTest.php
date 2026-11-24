<?php

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;

it('в системе есть роли «Курьер» и «Бухгалтер», и их нельзя удалить', function () {
    foreach ([SystemRole::Courier, SystemRole::Accountant] as $role) {
        $model = Role::where('slug', $role->value)->sole();

        expect($model->is_system)->toBeTrue();

        $this->actingAs(staff(SystemRole::Owner))
            ->delete(route('roles.destroy', $model))
            ->assertSessionHasErrors('role');
    }
});

it('бухгалтер по умолчанию видит отчёты и не видит кассу', function () {
    $accountant = staff(SystemRole::Accountant);

    expect($accountant->hasPermission(Permission::Reports))->toBeTrue()
        ->and($accountant->hasPermission(Permission::AreaCashier))->toBeFalse();

    $this->actingAs($accountant)->get(route('home'))->assertRedirect(route('dashboard'));
    $this->get(route('reports.index'))->assertOk();
    $this->get(route('cashier.index'))->assertForbidden();
});

it('курьер после входа попадает на свой экран доставки', function () {
    $this->actingAs(staff(SystemRole::Courier))
        ->get(route('home'))
        ->assertRedirect(route('courier.index'));
});

it('право «Отзывы» по умолчанию у администратора зала, у официанта — нет', function () {
    expect(staff(SystemRole::HallAdmin)->hasPermission(Permission::Reviews))->toBeTrue()
        ->and(staff(SystemRole::Waiter)->hasPermission(Permission::Reviews))->toBeFalse();
});
