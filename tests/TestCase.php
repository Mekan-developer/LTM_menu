<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** RefreshDatabase засеивает системные роли перед каждым тестом. */
    protected bool $seed = true;

    protected string $seeder = RoleSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Страницы Inertia в тестах не собираются Vite.
        $this->withoutVite();
    }
}
