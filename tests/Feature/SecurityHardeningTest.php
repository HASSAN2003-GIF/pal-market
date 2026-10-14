<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'Neema',
            'last_name' => 'Mrisho',
            'email' => 'neema@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            ...$overrides,
        ];
    }

    private function freshRequest(): void
    {
        // The test client caches the authenticated user between requests.
        $this->app['auth']->forgetGuards();
    }

    public function test_supplier_registration_creates_pending_supplier_without_fabricated_tin(): void
    {
        $this->postJson('/api/register', $this->registerPayload([
            'role' => 'supplier',
            'business_name' => 'Mrisho Building Supplies',
        ]))->assertCreated()
            ->assertJsonPath('user.role', 'supplier')
            ->assertJsonPath('user.supplier.status', 'pending')
            ->assertJsonPath('user.supplier.business_name', 'Mrisho Building Supplies')
            ->assertJsonPath('user.supplier.tin_number', null);
    }

    public function test_registration_rejects_weak_passwords(): void
    {
        $this->postJson('/api/register', $this->registerPayload([
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'target@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['login' => 'target@example.com', 'password' => 'wrong-'.$i])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['login' => 'target@example.com', 'password' => 'password'])
            ->assertStatus(429);
    }

    public function test_suspended_account_cannot_sign_in_and_existing_tokens_stop_working(): void
    {
        $user = $this->makeBuyer();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')->assertOk();

        $user->forceFill(['suspended_at' => now()])->save();
        $this->freshRequest();

        $this->withToken($token)->getJson('/api/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'account_suspended');

        $this->freshRequest();
        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'password'])
            ->assertForbidden();
    }

    public function test_wrong_password_for_suspended_account_does_not_reveal_suspension(): void
    {
        $user = $this->makeBuyer();
        $user->forceFill(['suspended_at' => now()])->save();

        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'not-it'])
            ->assertUnprocessable()
            ->assertJsonMissing(['message' => 'This account has been suspended. Please contact PAL Market support.']);
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $user = new User(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123']);

        $this->assertFalse($user->isFillable('role'));
        $this->assertFalse($user->isFillable('suspended_at'));
    }

    public function test_password_change_requires_current_password_and_signs_out_other_devices(): void
    {
        $user = $this->makeBuyer();
        $current = $user->createToken('current')->plainTextToken;
        $other = $user->createToken('other')->plainTextToken;

        $this->withToken($current)->putJson('/api/me/password', [
            'current_password' => 'wrong',
            'password' => 'newsecret1',
            'password_confirmation' => 'newsecret1',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->freshRequest();
        $this->withToken($current)->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'newsecret1',
            'password_confirmation' => 'newsecret1',
        ])->assertOk();

        $this->freshRequest();
        $this->withToken($other)->getJson('/api/me')->assertUnauthorized();
        $this->freshRequest();
        $this->withToken($current)->getJson('/api/me')->assertOk();
        $this->assertTrue(AuditLog::where('action', 'account.password_changed')->exists());
    }

    public function test_forgot_password_response_does_not_reveal_whether_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/forgot-password', ['email' => 'known@example.com'])->assertOk()->json('message');
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_changes_password_and_revokes_all_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'reset@example.com']);
        $token = $user->createToken('old')->plainTextToken;

        $this->postJson('/api/forgot-password', ['email' => 'reset@example.com']);

        $resetToken = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$resetToken) {
            $resetToken = $n->token;

            return true;
        });

        $this->postJson('/api/reset-password', [
            'token' => $resetToken,
            'email' => 'reset@example.com',
            'password' => 'brandnew9',
            'password_confirmation' => 'brandnew9',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at, 'Completing a reset proves control of the address.');
        $this->freshRequest();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
        $this->freshRequest();
        $this->postJson('/api/login', ['login' => 'reset@example.com', 'password' => 'brandnew9'])->assertOk();
    }

    public function test_google_exchange_code_is_single_use(): void
    {
        $user = $this->makeBuyer();
        $code = Str::random(64);
        Cache::put('google-login:'.hash('sha256', $code), $user->id, 120);

        $this->postJson('/api/auth/google/exchange', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);

        $this->postJson('/api/auth/google/exchange', ['code' => $code])->assertUnprocessable();
    }

    public function test_api_tokens_expire(): void
    {
        $this->assertSame(60 * 24 * 7, config('sanctum.expiration'));
    }

    public function test_security_headers_are_sent(): void
    {
        $this->getJson('/api/products')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_market_feed_is_only_available_to_suppliers_and_hides_buyer_details(): void
    {
        ['request' => $request] = $this->openRequestWithSupplier();
        $otherBuyer = $this->makeBuyer();

        Sanctum::actingAs($otherBuyer);
        $this->getJson('/api/market-requests')->assertForbidden();
        $this->getJson("/api/market-requests/{$request->id}")->assertForbidden();

        $pending = $this->makeSupplier('pending');
        Sanctum::actingAs($pending);

        $response = $this->getJson('/api/market-requests')->assertOk()->assertJsonPath('data.0.id', $request->id);
        $this->assertSame(['business_name'], array_keys($response->json('data.0.buyer')));
        $this->assertStringNotContainsString('tin_number', $response->getContent());
        $this->assertStringNotContainsString('user_id', $response->getContent());
    }

    public function test_market_feed_excludes_drafts_and_expired_requests(): void
    {
        ['request' => $open, 'buyer' => $buyer, 'supplier' => $supplier] = $this->openRequestWithSupplier();

        $draft = $buyer->buyerProfile->requests()->create(['request_number' => 'REQ-D-1', 'title' => 'Draft', 'status' => 'draft']);
        $lapsed = $buyer->buyerProfile->requests()->create([
            'request_number' => 'REQ-L-1', 'title' => 'Lapsed', 'status' => 'open', 'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($supplier);

        $ids = collect($this->getJson('/api/market-requests')->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$open->id], $ids->all());
        $this->getJson("/api/market-requests/{$draft->id}")->assertNotFound();
        $this->getJson("/api/market-requests/{$lapsed->id}")->assertNotFound();
    }

    public function test_suspended_buyer_profile_can_read_but_not_trade(): void
    {
        $buyer = $this->makeBuyer('suspended');
        Sanctum::actingAs($buyer);

        $this->getJson('/api/buyer-requests')->assertOk();
        $this->postJson('/api/buyer-requests', ['title' => 'Blocked'])->assertForbidden();
    }

    public function test_suspended_supplier_cannot_change_catalog_or_quote(): void
    {
        ['request' => $request, 'supplier' => $supplier, 'product' => $product] = $this->openRequestWithSupplier();
        $supplierProduct = $supplier->supplier->supplierProducts()->first();
        $supplier->supplier->update(['status' => 'suspended']);

        Sanctum::actingAs($supplier->fresh());

        $this->putJson("/api/supplier-products/{$supplierProduct->id}", ['is_active' => false])->assertForbidden();
        $this->postJson("/api/buyer-requests/{$request->id}/quotations", [
            'items' => [['product_id' => $product->id, 'unit_price' => 100]],
        ])->assertUnprocessable();
    }

    public function test_health_endpoint_reports_dependencies(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok');
    }
}
