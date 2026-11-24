<?php

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('kitchen.{station}', fn (User $user, string $station) => in_array($station, ['kitchen', 'bar'], true)
    && $user->hasPermission(Permission::AreaKitchen));

Broadcast::channel('waiters', fn (User $user) => $user->hasPermission(Permission::AreaWaiter));

Broadcast::channel('cashier', fn (User $user) => $user->hasPermission(Permission::AreaCashier));

Broadcast::channel('reviews', fn (User $user) => $user->hasPermission(Permission::Reviews));
