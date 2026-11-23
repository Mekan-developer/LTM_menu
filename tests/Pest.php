<?php

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function staff(SystemRole $role, array $attributes = []): User
{
    return User::factory()->role($role)->create($attributes);
}
