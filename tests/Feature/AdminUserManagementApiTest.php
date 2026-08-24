<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_users(): void
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertUnauthorized();
    }

    public function test_buyer_cannot_list_users(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_supplier_cannot_list_users(): void
    {
        $supplier = User::factory()->create([
            'role' => 'supplier',
        ]);

        $response = $this->actingAs($supplier, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'Buyer User',
            'email' => 'buyer@example.com',
            'role' => 'buyer',
        ]);

        User::factory()->create([
            'name' => 'Supplier User',
            'email' => 'supplier@example.com',
            'role' => 'supplier',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'users' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'email' => 'buyer@example.com',
                'role' => 'buyer',
            ])
            ->assertJsonFragment([
                'email' => 'supplier@example.com',
                'role' => 'supplier',
            ]);
    }

    public function test_admin_can_view_a_user(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'name' => 'Hassan Buyer',
            'email' => 'hassan@example.com',
            'role' => 'buyer',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/users/{$user->id}");

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Hassan Buyer')
            ->assertJsonPath('user.email', 'hassan@example.com')
            ->assertJsonPath('user.role', 'buyer')
            ->assertJsonMissingPath('user.password');
    }

    public function test_non_admin_cannot_view_a_user(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $user = User::factory()->create([
            'role' => 'supplier',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson("/api/admin/users/{$user->id}");

        $response->assertForbidden();
    }

    public function test_admin_can_change_user_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'role' => 'buyer',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/role", [
                'role' => 'supplier',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'supplier');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'supplier',
        ]);
    }

    public function test_admin_cannot_assign_invalid_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'role' => 'buyer',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/role", [
                'role' => 'manager',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);
    }

    public function test_non_admin_cannot_change_user_role(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $user = User::factory()->create([
            'role' => 'supplier',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'supplier',
        ]);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'role' => 'buyer',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$user->id}");

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'User deleted successfully.',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_non_admin_cannot_delete_user(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $user = User::factory()->create([
            'role' => 'supplier',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->deleteJson("/api/admin/users/{$user->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }
}
