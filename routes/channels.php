<?php

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('kitchen.{station}', fn (User $user, string $station) => in_array($station, ['kitchen', 'bar'], true)
    && $user->hasPermission(Permission::AreaKitchen));

Broadcast::channel('waiters', fn (User $user) => $user->hasPermission(Permission::AreaWaiter));

Broadcast::channel('cashier', fn (User $user) => $user->hasPermission(Permission::AreaCashier));

Broadcast::channel('reviews', fn (User $user) => $user->hasPermission(Permission::Reviews));

Broadcast::channel('hall', fn (User $user) => $user->hasPermission(Permission::AreaWaiter)
    || $user->hasPermission(Permission::Tables)
    || $user->hasPermission(Permission::Reports));

Broadcast::channel('bookings', fn (User $user) => $user->hasPermission(Permission::Bookings));

Broadcast::channel('deliveries', fn (User $user) => $user->hasPermission(Permission::Delivery)
    || $user->hasPermission(Permission::AreaCourier));
