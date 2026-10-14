<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class PreferencesApiTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_guests_cannot_save_preferences(): void
    {
        $this->putJson('/api/me/preferences', ['locale' => 'sw'])->assertUnauthorized();
    }

    public function test_language_and_region_are_saved_and_returned_with_the_account(): void
    {
        $buyer = $this->makeBuyer();

        $this->actingAs($buyer, 'sanctum')
            ->putJson('/api/me/preferences', ['locale' => 'sw', 'region' => 'Mwanza'])
            ->assertOk()
            ->assertJsonPath('message', 'Mapendeleo yamehifadhiwa.')
            ->assertJsonPath('user.locale', 'sw')
            ->assertJsonPath('user.region', 'Mwanza');

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/me')
            ->assertJsonPath('user.locale', 'sw')
            ->assertJsonPath('user.region', 'Mwanza');
    }

    public function test_fields_can_be_changed_independently_and_cleared(): void
    {
        $buyer = $this->makeBuyer();
        $buyer->forceFill(['locale' => 'sw', 'region' => 'Arusha'])->save();

        $this->actingAs($buyer, 'sanctum')
            ->putJson('/api/me/preferences', ['region' => null])
            ->assertOk()
            ->assertJsonPath('user.locale', 'sw')
            ->assertJsonPath('user.region', null);

        $this->assertDatabaseHas('users', ['id' => $buyer->id, 'locale' => 'sw', 'region' => null]);
    }

    public function test_only_supported_languages_and_real_regions_are_accepted(): void
    {
        $buyer = $this->makeBuyer();

        $this->actingAs($buyer, 'sanctum')
            ->putJson('/api/me/preferences', ['locale' => 'fr', 'region' => 'Atlantis'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale', 'region']);

        $this->assertDatabaseHas('users', ['id' => $buyer->id, 'locale' => null, 'region' => null]);
    }

    public function test_preferences_cannot_be_used_to_change_protected_fields(): void
    {
        $buyer = $this->makeBuyer();

        $this->actingAs($buyer, 'sanctum')
            ->putJson('/api/me/preferences', ['locale' => 'en', 'role' => 'admin', 'email' => 'x@example.com'])
            ->assertOk();

        $this->assertSame('buyer', $buyer->fresh()->role);
        $this->assertNotSame('x@example.com', $buyer->fresh()->email);
    }

    public function test_browsers_may_use_their_location_on_this_site_only(): void
    {
        $policy = $this->getJson('/api/products')->headers->get('Permissions-Policy');

        $this->assertStringContainsString('geolocation=(self)', $policy);
        $this->assertStringContainsString('camera=()', $policy);
    }
}
