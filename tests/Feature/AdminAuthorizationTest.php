<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_admin_area(): void
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertUnauthorized();
    }

    public function test_buyer_cannot_access_admin_area(): void
    {
        $user = User::factory()->create([
            'role' => 'buyer',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_supplier_cannot_access_admin_area(): void
    {
        $user = User::factory()->create([
            'role' => 'supplier',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_area(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertOk();
    }
}
