<?php

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;

it('блокирует PIN на 15 минут после 5 ошибок', function () {
    $user = staff(SystemRole::Waiter, ['pin' => '1234']);

    foreach (range(1, 5) as $_) {
        $this->post(route('pin.store'), ['user_id' => $user->id, 'pin' => '0000']);
    }

    expect($user->fresh()->pin_locked_until?->isFuture())->toBeTrue();

    // Даже верный PIN не пускает, пока идёт блокировка.
    $this->post(route('pin.store'), ['user_id' => $user->id, 'pin' => '1234'])->assertSessionHasErrors('pin');
    $this->assertGuest();
});

it('после входа по PIN официант попадает на свой экран', function () {
    $user = staff(SystemRole::Waiter, ['pin' => '1234']);

    $this->post(route('pin.store'), ['user_id' => $user->id, 'pin' => '1234'])
        ->assertRedirect(route('waiter.index'));

    $this->assertAuthenticatedAs($user);
});

it('у владельца все права есть всегда', function () {
    $owner = staff(SystemRole::Owner);

    foreach (Permission::cases() as $permission) {
        expect($owner->hasPermission($permission))->toBeTrue();
    }
});

it('права роли включаются галочками', function () {
    $owner = staff(SystemRole::Owner);
    $waiterRole = Role::where('slug', 'waiter')->first();

    $this->actingAs($owner)->put(route('roles.update', $waiterRole), [
        'name_tm' => $waiterRole->name_tm,
        'name_ru' => $waiterRole->name_ru,
        'permissions' => ['area_waiter', 'transfer_tables'],
    ])->assertRedirect();

    $waiter = staff(SystemRole::Waiter);

    expect($waiter->hasPermission(Permission::TransferTables))->toBeTrue()
        ->and($waiter->hasPermission(Permission::Settings))->toBeFalse();
});

it('системную роль удалить нельзя', function () {
    $this->actingAs(staff(SystemRole::Owner))
        ->delete(route('roles.destroy', Role::where('slug', 'cashier')->first()))
        ->assertSessionHasErrors('role');
});
