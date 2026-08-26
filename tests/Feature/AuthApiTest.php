<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_for_email_verification(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Ahmed',
            'email' => 'hassan@example.com',
            'phone' => '+255718660882',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_channel' => 'email',
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
                ],
                'verification' => [
                    'channel',
                    'destination',
                ],
            ])
            ->assertJsonMissingPath('token')
            ->assertJsonMissing([
                'password' => 'password123',
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Hassan Ahmed',
            'email' => 'hassan@example.com',
            'phone' => '+255718660882',
            'email_verified_at' => null,
        ]);

        $this->assertDatabaseHas('verification_codes', [
            'channel' => 'email',
            'destination' => 'hassan@example.com',
        ]);
    }

    public function test_user_can_register_for_phone_verification(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Ahmed',
            'email' => 'hassan@example.com',
            'phone' => '+255718660882',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_channel' => 'phone',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'verification.channel',
                'phone'
            )
            ->assertJsonMissingPath('token');

        $this->assertDatabaseHas('verification_codes', [
            'channel' => 'phone',
            'destination' => '+255718660882',
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
                'verification_channel',
            ]);
    }

    public function test_phone_verification_requires_phone_number(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Ahmed',
            'email' => 'hassan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_channel' => 'phone',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone',
            ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'hassan@example.com',
        ]);

        $response = $this->postJson('/api/register', [
            'first_name' => 'Another',
            'last_name' => 'Hassan',
            'email' => 'hassan@example.com',
            'phone' => '+255718660882',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_channel' => 'email',
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
            'phone' => '+255718660882',
        ]);

        $response = $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Ahmed',
            'email' => 'another@example.com',
            'phone' => '+255718660882',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_channel' => 'phone',
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
            'last_name' => 'Ahmed',
            'email' => 'hassan@example.com',
            'phone' => '+255718660882',
            'password' => 'password123',
            'verification_channel' => 'email',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);
    }

    public function test_user_can_verify_email_and_receive_token(): void
{
    Mail::fake();

    $this->postJson('/api/register', [
        'first_name' => 'Hassan',
        'last_name' => 'Ahmed',
        'email' => 'hassan@example.com',
        'phone' => '+255718660882',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'verification_channel' => 'email',
    ])->assertCreated();

    $user = User::where('email', 'hassan@example.com')->firstOrFail();

    $verification = VerificationCode::query()
        ->where('user_id', $user->id)
        ->where('channel', 'email')
        ->latest()
        ->firstOrFail();

    $plainCode = '123456';

    $verification->update([
        'code' => Hash::make($plainCode),
    ]);

    $response = $this->postJson('/api/verify', [
        'email' => 'hassan@example.com',
        'channel' => 'email',
        'code' => $plainCode,
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'user',
            'token',
        ])
        ->assertJsonPath(
            'user.email',
            'hassan@example.com'
        );

    $this->assertNotNull(
        $response->json('token')
    );

    $this->assertNotNull(
        $response->json('user.email_verified_at')
    );

    $this->assertDatabaseHas('verification_codes', [
        'id' => $verification->id,
    ]);

    $this->assertNotNull(
        VerificationCode::find($verification->id)->verified_at
    );
}

    public function test_user_can_login_with_email_after_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'hassan@example.com',
            'password' => 'password123',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'hassan@example.com',
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

    public function test_user_can_login_with_phone_after_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'hassan@example.com',
            'phone' => '+255718660882',
            'password' => 'password123',
            'phone_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => '+255718660882',
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

    public function test_unverified_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'hassan@example.com',
            'password' => 'password123',
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'hassan@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'verification',
            ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'hassan@example.com',
            'password' => 'password123',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'hassan@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'login',
            ]);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_their_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Hassan Ahmed',
            'email' => 'hassan@example.com',
            'email_verified_at' => now(),
        ]);

        $token = $user
            ->createToken('test-token')
            ->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'hassan@example.com')
            ->assertJsonMissingPath('user.password');
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $token = $user
            ->createToken('test-token')
            ->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/logout');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Logout successful.',
            ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_password_is_hashed(): void
    {
        Mail::fake();

        $this->postJson('/api/register', [
            'first_name' => 'Hassan',
            'last_name' => 'Ahmed',
            'email' => 'hassan@example.com',
            'phone' => '+255718660882',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_channel' => 'email',
        ])->assertCreated();

        $user = User::where(
            'email',
            'hassan@example.com'
        )->firstOrFail();

        $this->assertTrue(
            Hash::check('password123', $user->password)
        );

        $this->assertNotSame(
            'password123',
            $user->password
        );
    }
}