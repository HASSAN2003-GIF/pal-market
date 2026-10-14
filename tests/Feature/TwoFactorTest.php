<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.admin_two_factor' => true]);
    }

    private function code(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    /** Turns two-step on through the API and returns [secret, recovery codes]. */
    private function enable(User $user, string $password = 'password'): array
    {
        $token = $user->createToken('t')->plainTextToken;

        $secret = $this->withToken($token)->postJson('/api/me/two-factor', ['password' => $password])
            ->assertOk()
            ->assertJsonStructure(['secret', 'otpauth_url', 'qr_svg'])
            ->json('secret');

        $codes = $this->withToken($token)->postJson('/api/me/two-factor/confirm', ['code' => $this->code($secret)])
            ->assertOk()
            ->assertJsonCount(8, 'recovery_codes')
            ->json('recovery_codes');

        // A used step cannot be used again; let the next login use a fresh one.
        Cache::forget('two-factor-last-step:'.$user->id);
        $this->app['auth']->forgetGuards();

        return [$secret, $codes];
    }

    private function loginChallenge(User $user): string
    {
        return $this->postJson('/api/login', ['login' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonMissingPath('token')
            ->json('challenge');
    }

    public function test_admin_console_requires_two_step_sign_in(): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_setup_required');
        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.two_factor_required', true)
            ->assertJsonPath('user.two_factor_enabled', false);

        $this->app['auth']->forgetGuards();
        [$secret] = $this->enable($admin->fresh());

        $challenge = $this->loginChallenge($admin);
        $newToken = $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => $this->code($secret)])
            ->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true)
            ->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken($newToken)->getJson('/api/admin/dashboard')->assertOk();
    }

    public function test_enabling_signs_out_other_devices_and_never_exposes_the_secret(): void
    {
        $user = $this->makeBuyer();
        $other = $user->createToken('phone')->plainTextToken;

        $this->enable($user);

        $this->assertSame(1, $user->tokens()->count());
        $this->withToken($other)->getJson('/api/me')->assertUnauthorized();

        $me = $this->withToken($user->createToken('x')->plainTextToken)->getJson('/api/me')->assertOk();
        $this->assertStringNotContainsString('two_factor_secret', $me->getContent());
        $this->assertNotNull($user->fresh()->getRawOriginal('two_factor_secret'));
        $this->assertStringNotContainsString($user->fresh()->two_factor_secret, (string) $user->fresh()->getRawOriginal('two_factor_secret'), 'Stored encrypted.');
        $this->assertTrue(AuditLog::where('action', 'account.two_factor_enabled')->exists());
    }

    public function test_setup_needs_the_password_and_a_working_code(): void
    {
        $user = $this->makeBuyer();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/me/two-factor', ['password' => 'wrong'])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/me/two-factor', ['password' => 'password'])->assertOk();
        $this->withToken($token)->postJson('/api/me/two-factor/confirm', ['code' => '000000'])->assertUnprocessable();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_sign_in_needs_a_valid_unused_code(): void
    {
        $user = $this->makeBuyer();
        [$secret] = $this->enable($user);

        $challenge = $this->loginChallenge($user);
        $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $code = $this->code($secret);
        $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => $code])->assertOk()->assertJsonStructure(['token']);

        // The challenge is single use, and the same code cannot be replayed.
        $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => $code])->assertUnprocessable();
        $this->postJson('/api/login/two-factor', ['challenge' => $this->loginChallenge($user), 'code' => $code])->assertUnprocessable();
    }

    public function test_a_challenge_dies_after_five_wrong_codes(): void
    {
        $user = $this->makeBuyer();
        [$secret] = $this->enable($user);
        $challenge = $this->loginChallenge($user);

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => '00000'.$i])->assertUnprocessable();
        }

        $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => $this->code($secret)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_backup_codes_work_once(): void
    {
        $user = $this->makeBuyer();
        [, $codes] = $this->enable($user);

        $this->postJson('/api/login/two-factor', ['challenge' => $this->loginChallenge($user), 'recovery_code' => strtoupper($codes[0])])->assertOk();
        $this->postJson('/api/login/two-factor', ['challenge' => $this->loginChallenge($user), 'recovery_code' => $codes[0]])->assertUnprocessable();

        $this->assertSame(7, count($user->fresh()->two_factor_recovery_codes));
        $this->assertTrue(AuditLog::where('action', 'account.two_factor_recovery_used')->exists());
    }

    public function test_turning_off_needs_password_and_code(): void
    {
        $user = $this->makeBuyer();
        [$secret] = $this->enable($user);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/me/two-factor', ['password' => 'password', 'code' => '000000'])->assertUnprocessable();
        $this->withToken($token)->deleteJson('/api/me/two-factor', ['password' => 'wrong', 'code' => $this->code($secret)])->assertUnprocessable();
        $this->withToken($token)->deleteJson('/api/me/two-factor', ['password' => 'password', 'code' => $this->code($secret)])->assertOk();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'password'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_google_sign_in_also_asks_for_the_code(): void
    {
        $user = $this->makeBuyer();
        $this->enable($user);

        $code = str_repeat('g', 64);
        Cache::put('google-login:'.hash('sha256', $code), $user->id, 120);

        $this->postJson('/api/auth/google/exchange', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonMissingPath('token');
    }

    public function test_bootstrap_creates_the_first_admin_and_prints_a_one_time_setup_link(): void
    {
        config(['auth.bootstrap_admin_email' => 'Owner@Example.com', 'app.url' => 'https://pal.example']);

        $this->assertSame(0, Artisan::call('pal:bootstrap-admin'));
        $output = Artisan::output();

        $admin = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertStringContainsString('https://pal.example/reset-password?token=', $output);

        // The link sets the owner's own password.
        parse_str(parse_url(trim(collect(explode("\n", $output))->first(fn ($l) => str_contains($l, 'reset-password'))), PHP_URL_QUERY), $query);
        $this->postJson('/api/reset-password', [
            'token' => $query['token'],
            'email' => $query['email'],
            'password' => 'Long-and-unique-42',
            'password_confirmation' => 'Long-and-unique-42',
        ])->assertOk();
        $this->postJson('/api/login', ['login' => 'owner@example.com', 'password' => 'Long-and-unique-42'])->assertOk();

        // Once signed in, later deploys print nothing.
        Artisan::call('pal:bootstrap-admin');
        $this->assertStringNotContainsString('reset-password', Artisan::output());
        $this->assertSame(1, User::where('email', 'owner@example.com')->count());
    }

    public function test_bootstrap_never_promotes_an_existing_non_admin_account(): void
    {
        $buyer = $this->makeBuyer();
        config(['auth.bootstrap_admin_email' => $buyer->email]);

        $this->assertSame(1, Artisan::call('pal:bootstrap-admin'));
        $this->assertSame('buyer', $buyer->fresh()->role);
        $this->assertStringNotContainsString('reset-password', Artisan::output());
    }

    public function test_bootstrap_does_nothing_without_admin_email(): void
    {
        config(['auth.bootstrap_admin_email' => null]);

        $this->assertSame(0, Artisan::call('pal:bootstrap-admin'));
        $this->assertSame(0, User::where('role', 'admin')->count());
    }
}
