<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeProduct(['name' => 'Simba Cement 42.5R', 'unit' => 'Bag (50kg)']);
        $this->makeProduct(['name' => 'Twiga Extra 32.5R', 'description' => 'Portland cement for plastering and blocks.']);
        $this->makeProduct(['name' => 'Twisted Steel Rebar 12mm', 'unit' => 'piece']);
        $this->makeProduct(['name' => 'Twisted Steel Rebar 16mm', 'unit' => 'piece']);
        $this->makeProduct(['name' => 'Versatile Roofing Sheet (Gauge 28)', 'unit' => 'piece']);
        $this->makeProduct(['name' => 'Hidden Cement Mix', 'is_active' => false]);
    }

    private function names(string $query, array $extra = []): array
    {
        return collect($this->getJson('/api/products?'.http_build_query(['search' => $query, ...$extra]))->assertOk()->json('data'))
            ->pluck('name')->all();
    }

    public function test_exact_partial_and_case_insensitive_matches(): void
    {
        $this->assertSame(['Simba Cement 42.5R'], $this->names('simba cement 42.5r'));
        $this->assertSame(['Simba Cement 42.5R'], $this->names('SIMBA-CEM'));
        $this->assertContains('Twisted Steel Rebar 12mm', $this->names('twis'));

        $this->getJson('/api/products?search=simba')->assertJsonPath('search.mode', 'exact');
    }

    public function test_units_and_numbers_are_normalised_and_not_fuzzed(): void
    {
        $this->assertSame(['Twisted Steel Rebar 12mm'], $this->names('rebar 12 mm'));
        $this->assertSame(['Twisted Steel Rebar 16mm'], $this->names('REBAR 16MM'));
    }

    public function test_plurals_and_swahili_vocabulary_match(): void
    {
        $this->assertSame(['Versatile Roofing Sheet (Gauge 28)'], $this->names('roofing sheets'));
        $this->assertSame(['Versatile Roofing Sheet (Gauge 28)'], $this->names('mabati'));

        // "saruji" is Swahili for cement: finds the product named "Cement" and
        // the one whose description mentions cement, but not inactive ones.
        $results = $this->names('saruji');
        $this->assertContains('Simba Cement 42.5R', $results);
        $this->assertContains('Twiga Extra 32.5R', $results);
        $this->assertNotContains('Hidden Cement Mix', $results);
        $this->assertSame('Simba Cement 42.5R', $results[0], 'Name matches rank above description matches.');

        $this->assertContains('Twisted Steel Rebar 12mm', $this->names('nondo'));
    }

    public function test_spelling_mistakes_return_close_matches_with_a_suggestion(): void
    {
        $response = $this->getJson('/api/products?search=cemnt')->assertOk();

        $response->assertJsonPath('search.mode', 'close')
            ->assertJsonPath('search.suggestion', 'cement');
        $this->assertContains('Simba Cement 42.5R', collect($response->json('data'))->pluck('name'));

        $this->getJson('/api/products?search=sarujy')->assertJsonPath('search.mode', 'close');
    }

    public function test_partial_matches_are_labelled_related_and_nonsense_finds_nothing(): void
    {
        $response = $this->getJson('/api/products?search='.urlencode('blue cement'))->assertOk();
        $response->assertJsonPath('search.mode', 'related');
        $this->assertContains('Simba Cement 42.5R', collect($response->json('data'))->pluck('name'));

        $this->getJson('/api/products?search=qqqqzzzz')
            ->assertOk()
            ->assertJsonPath('search.mode', 'none')
            ->assertJsonCount(0, 'data');

        // LIKE wildcards and odd characters are plain text.
        $this->getJson('/api/products?search='.urlencode('%_\\'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_aliases_and_swahili_names_are_searchable_and_reported_in_suggestions(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(['name' => 'OPC Cement 42.5N']);

        $this->actingAs($admin)->patchJson("/api/admin/catalog/products/{$product->id}", [
            'name_sw' => 'Saruji ya OPC',
            'aliases' => ['Ordinary Portland Cement', 'Dangote 3X'],
        ])->assertOk()->assertJsonCount(2, 'product.aliases');

        $this->assertSame(['OPC Cement 42.5N'], $this->names('dangote'));

        $this->getJson('/api/products/suggest?q=ordinary')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'OPC Cement 42.5N')
            ->assertJsonPath('data.0.matched', 'Ordinary Portland Cement')
            ->assertJsonPath('data.0.name_sw', 'Saruji ya OPC');
    }

    public function test_index_refreshes_when_the_catalog_changes(): void
    {
        $this->assertSame([], $this->names('tembo'));

        $product = $this->makeProduct(['name' => 'Tembo Paint 4L']);
        $this->assertSame(['Tembo Paint 4L'], $this->names('tembo'));

        $product->update(['is_active' => false]);
        $this->assertSame([], $this->names('tembo'));

        // Category names count as context for matching.
        Category::first()->update(['name' => 'Building Supplies']);
        $this->assertNotEmpty($this->names('building'));
    }

    public function test_suggest_validates_and_limits_results(): void
    {
        $this->getJson('/api/products/suggest')->assertUnprocessable();
        $this->getJson('/api/products/suggest?q='.str_repeat('a', 101))->assertUnprocessable();

        $this->getJson('/api/products/suggest?q=tw&limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['mode', 'data' => [['id', 'name', 'name_sw', 'unit', 'category', 'matched', 'image']]]);
    }

    public function test_filters_and_sorting_still_apply_to_search_results(): void
    {
        $other = Category::create(['name' => 'Roofing', 'slug' => 'roofing']);
        $this->makeProduct(['name' => 'Roofing Cement Sealant', 'category_id' => $other->id]);

        $this->assertSame(['Roofing Cement Sealant'], $this->names('cement', ['category_id' => $other->id]));
        $this->assertSame(
            ['Roofing Cement Sealant', 'Simba Cement 42.5R', 'Twiga Extra 32.5R'],
            $this->names('cement', ['sort' => 'name'])
        );
    }
}
