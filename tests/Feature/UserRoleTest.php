<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_default_to_buyer_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame('buyer', $user->role);
        $this->assertTrue($user->isBuyer());
        $this->assertFalse($user->isSupplier());
        $this->assertFalse($user->isAdmin());
    }

    public function test_user_can_be_created_as_supplier(): void
    {
        $user = User::factory()->create([
            'role' => 'supplier',
        ]);

        $this->assertSame('supplier', $user->role);
        $this->assertTrue($user->isSupplier());
        $this->assertFalse($user->isBuyer());
        $this->assertFalse($user->isAdmin());
    }

    public function test_user_can_be_created_as_admin(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isBuyer());
        $this->assertFalse($user->isSupplier());
    }

    public function test_registration_does_not_allow_role_assignment(): void
    {
       $response = $this->postJson('/api/register', [
    'first_name' => 'Admin',
    'last_name' => 'Attempt',
    'email' => 'admin-attempt@example.com',
    'phone' => '+255700000001',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'verification_channel' => 'email',
    'role' => 'admin',
]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.role', 'buyer');

        $this->assertDatabaseHas('users', [
            'email' => 'admin-attempt@example.com',
            'role' => 'buyer',
        ]);
    }
}