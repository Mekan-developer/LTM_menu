<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_super_admin()
    {
        $this->seed(SuperAdminSeeder::class);

        $admin = User::where('email', 'admin@gmail.com')->firstOrFail();

        $this->assertSame(UserRole::SuperAdmin, $admin->role);
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_it_is_idempotent()
    {
        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@gmail.com')->count());
    }

    public function test_it_promotes_an_existing_user_with_the_same_email()
    {
        User::factory()->create(['email' => 'admin@gmail.com']);

        $this->seed(SuperAdminSeeder::class);

        $this->assertTrue(User::where('email', 'admin@gmail.com')->first()->isSuperAdmin());
    }

    public function test_super_admin_can_log_in()
    {
        $this->seed(SuperAdminSeeder::class);

        $response = $this->post(route('login.store'), [
            'email' => 'admin@gmail.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_users_default_to_the_user_role()
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::User, $user->fresh()->role);
    }
}
