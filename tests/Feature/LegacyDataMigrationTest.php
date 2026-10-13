<?php

namespace Tests\Feature;

use App\Models\BuyerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class LegacyDataMigrationTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    public function test_open_requests_without_a_window_get_one_and_others_are_untouched(): void
    {
        $profile = $this->makeBuyer()->buyerProfile;
        $legacy = $profile->requests()->create(['request_number' => 'REQ-L-1', 'title' => 'Legacy', 'status' => 'open']);
        $draft = $profile->requests()->create(['request_number' => 'REQ-L-2', 'title' => 'Draft', 'status' => 'draft']);
        $windowed = $profile->requests()->create([
            'request_number' => 'REQ-L-3', 'title' => 'Has window', 'status' => 'open', 'expires_at' => now()->addDays(3),
        ]);

        (require database_path('migrations/2026_10_13_090400_backfill_open_request_windows.php'))->up();

        $legacy = $legacy->fresh();
        $this->assertTrue($legacy->expires_at->between(now()->addDays(13), now()->addDays(15)));
        $this->assertNotNull($legacy->published_at);
        $this->assertNull($draft->fresh()->expires_at);
        $this->assertTrue($windowed->fresh()->expires_at->between(now()->addDays(2), now()->addDays(4)));
    }
}
