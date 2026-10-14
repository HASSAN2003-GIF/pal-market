<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * /admin is reachable by URL only (no public link). The page shell is public
 * like every SPA route; everything it shows comes from /api/admin/*, which
 * the server protects regardless of how the page was reached.
 */
class AdminEntryTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private const ADMIN_ENDPOINTS = [
        '/api/admin/dashboard',
        '/api/admin/users',
        '/api/admin/suppliers',
        '/api/admin/buyers',
        '/api/admin/catalog/products',
        '/api/admin/buyer-requests',
        '/api/admin/purchase-orders',
        '/api/admin/audit-logs',
    ];

    public function test_admin_urls_serve_the_app_shell_without_admin_data(): void
    {
        foreach (['/admin', '/admin/users', '/admin/audit-log'] as $url) {
            $response = $this->get($url)->assertOk();
            $response->assertSee('<div id="app"></div>', false);
            $response->assertDontSee('admin@', false);
        }
    }

    public function test_guests_get_401_from_every_admin_endpoint(): void
    {
        foreach (self::ADMIN_ENDPOINTS as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    public function test_buyers_and_suppliers_get_403_from_every_admin_endpoint(): void
    {
        foreach ([$this->makeBuyer(), $this->makeSupplier()] as $user) {
            $token = $user->createToken('t')->plainTextToken;
            foreach (self::ADMIN_ENDPOINTS as $url) {
                $this->withToken($token)->getJson($url)->assertForbidden();
            }
            $this->withToken($token)->postJson('/api/admin/catalog/categories', ['name' => 'Sneaky'])->assertForbidden();
            $this->app['auth']->forgetGuards();
        }

        $this->assertDatabaseMissing('categories', ['name' => 'Sneaky']);
    }

    public function test_admins_can_use_every_admin_endpoint(): void
    {
        $token = $this->makeAdmin()->createToken('t')->plainTextToken;

        foreach (self::ADMIN_ENDPOINTS as $url) {
            $this->withToken($token)->getJson($url)->assertOk();
        }
    }

    public function test_admin_token_stops_working_after_logout(): void
    {
        $token = $this->makeAdmin()->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/admin/dashboard')->assertOk();

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_expired_admin_token_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('t')->plainTextToken;
        PersonalAccessToken::query()->update(['created_at' => now()->subMinutes(config('sanctum.expiration') + 1)]);

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_suspended_admin_is_locked_out(): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('t')->plainTextToken;
        $admin->forceFill(['suspended_at' => now()])->save();

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
    }
}
