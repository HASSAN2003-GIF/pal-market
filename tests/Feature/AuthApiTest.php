<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Test',
            'email' => 'hassan@example.com',
            'phone' => '+255700000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'role',
                ],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'hassan@example.com',
            'name' => 'Hassan Test',
            'role' => 'buyer',
        ]);

        $user = User::where('email', 'hassan@example.com')->first();

        $this->assertNotNull($user->email_verified_at);
    }

    public function test_registration_does_not_require_phone(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Test',
            'email' => 'hassan2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'hassan2@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'hassan2@example.com',
            'phone' => null,
        ]);
    }

    public function test_registration_requires_valid_data(): void
    {
        $response = $this->postJson('/api/register', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'email',
                'password',
            ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Test',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_registration_rejects_duplicate_phone(): void
    {
        User::factory()->create([
            'phone' => '+255700000002',
        ]);

        $response = $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Test',
            'email' => 'phone@example.com',
            'phone' => '+255700000002',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone',
            ]);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Test',
            'email' => 'confirmation@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);
    }

    public function test_password_is_hashed(): void
    {
        $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Test',
            'email' => 'hash@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'hash@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(
            Hash::check('password123', $user->password)
        );
        $this->assertNotSame(
            'password123',
            $user->password
        );
    }

    public function test_user_can_login_with_email_immediately_after_registration(): void
    {
        $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Test',
            'email' => 'login@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $response = $this->postJson('/api/login', [
            'login' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    public function test_user_can_login_with_phone(): void
    {
        $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Test',
            'email' => 'phone-login@example.com',
            'phone' => '+255700000003',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $response = $this->postJson('/api/login', [
            'login' => '+255700000003',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'invalid@example.com',
            'password' => 'password123',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'invalid@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'login',
            ]);
    }

    public function test_authenticated_user_can_view_their_profile(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Logout successful.',
            ]);
    }
}