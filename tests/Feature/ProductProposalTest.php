<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ProductProposalTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private function propose(array $data = []): TestResponse
    {
        return $this->postJson('/api/product-proposals', [
            'name' => 'Kiboko Gloss Paint 4L',
            'name_sw' => 'Rangi ya Kiboko 4L',
            'unit' => 'tin',
            'description' => 'White, oil based',
            ...$data,
        ]);
    }

    public function test_approved_supplier_can_propose_a_new_product(): void
    {
        $supplier = $this->makeSupplier();
        Sanctum::actingAs($supplier);

        $this->propose()->assertCreated()->assertJsonPath('proposal.status', 'pending');
        $this->getJson('/api/product-proposals')->assertOk()->assertJsonCount(1, 'proposals');

        // Proposals are not visible to buyers.
        $this->getJson('/api/products?search=kiboko')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('audit_logs', ['action' => 'product_proposal.created']);

        // Asking twice for the same thing is refused.
        $this->propose()->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_existing_catalog_products_are_not_proposed_again(): void
    {
        $this->makeProduct(['name' => 'Simba Cement 42.5R']);
        Sanctum::actingAs($this->makeSupplier());

        $this->propose(['name' => 'simba cement 42.5 R'])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_only_approved_suppliers_can_propose(): void
    {
        Sanctum::actingAs($this->makeSupplier('pending'));
        $this->propose()->assertForbidden();

        Sanctum::actingAs($this->makeBuyer());
        $this->propose()->assertForbidden();
        $this->getJson('/api/product-proposals')->assertForbidden();
    }

    public function test_suppliers_only_see_their_own_proposals(): void
    {
        Sanctum::actingAs($this->makeSupplier());
        $this->propose()->assertCreated();

        Sanctum::actingAs($this->makeSupplier());
        $this->getJson('/api/product-proposals')->assertOk()->assertJsonCount(0, 'proposals');
    }

    public function test_admin_approves_a_proposal_into_the_catalog_and_the_supplier_list(): void
    {
        $supplier = $this->makeSupplier();
        $category = Category::create(['name' => 'Paint', 'slug' => 'paint']);
        Sanctum::actingAs($supplier);
        $proposalId = $this->propose()->json('proposal.id');

        Sanctum::actingAs($this->makeAdmin());
        $this->getJson('/api/admin/product-proposals')->assertOk()->assertJsonPath('pending_count', 1);

        $this->postJson("/api/admin/product-proposals/{$proposalId}/approve", [
            'name' => 'Kiboko Gloss Paint 4L',
            'name_sw' => 'Rangi ya Kiboko 4L',
            'category_id' => $category->id,
            'unit' => 'tin',
        ])->assertOk()->assertJsonPath('proposal.status', 'approved');

        $product = Product::where('name', 'Kiboko Gloss Paint 4L')->firstOrFail();
        $this->assertSame('Rangi ya Kiboko 4L', $product->name_sw);
        $this->assertDatabaseHas('supplier_products', ['supplier_id' => $supplier->supplier->id, 'product_id' => $product->id]);
        $this->assertTrue(AuditLog::where('action', 'product_proposal.approved')->exists());

        // Reviewed once only.
        $this->postJson("/api/admin/product-proposals/{$proposalId}/reject", ['review_note' => 'Duplicate'])
            ->assertUnprocessable();

        // Searchable straight away, including by its Swahili name.
        $this->getJson('/api/products?search=rangi')->assertOk()->assertJsonPath('data.0.id', $product->id);
    }

    public function test_admin_can_link_to_an_existing_product_or_reject_with_a_reason(): void
    {
        $supplier = $this->makeSupplier();
        $existing = $this->makeProduct(['name' => 'Gloss Paint White']);
        Sanctum::actingAs($supplier);
        $first = $this->propose()->json('proposal.id');
        $second = $this->propose(['name' => 'Something odd'])->json('proposal.id');

        Sanctum::actingAs($this->makeAdmin());
        $this->postJson("/api/admin/product-proposals/{$first}/approve", ['product_id' => $existing->id])
            ->assertOk()
            ->assertJsonPath('proposal.product.id', $existing->id);
        $this->assertDatabaseMissing('products', ['name' => 'Kiboko Gloss Paint 4L']);

        $this->postJson("/api/admin/product-proposals/{$second}/reject", [])->assertUnprocessable()->assertJsonValidationErrors('review_note');
        $this->postJson("/api/admin/product-proposals/{$second}/reject", ['review_note' => 'Please give the brand and size.'])
            ->assertOk()
            ->assertJsonPath('proposal.status', 'rejected');

        Sanctum::actingAs($supplier);
        $this->getJson('/api/product-proposals')->assertJsonFragment(['review_note' => 'Please give the brand and size.']);
    }

    public function test_non_admins_cannot_review_proposals(): void
    {
        $proposal = ProductProposal::forceCreate([
            'supplier_id' => $this->makeSupplier()->supplier->id,
            'name' => 'Thing',
            'unit' => 'piece',
            'status' => 'pending',
        ]);

        foreach ([$this->makeSupplier(), $this->makeBuyer()] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/admin/product-proposals')->assertForbidden();
            $this->postJson("/api/admin/product-proposals/{$proposal->id}/approve", ['product_id' => 1])->assertForbidden();
        }
    }
}
