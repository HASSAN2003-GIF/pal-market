<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\SupplierQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    public function test_admin_can_suspend_and_reactivate_a_user_which_revokes_their_tokens(): void
    {
        $admin = $this->makeAdmin();
        $buyer = $this->makeBuyer();
        $buyer->createToken('phone');

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/users/{$buyer->id}/suspend", ['reason' => 'Fraud report'])
            ->assertOk()
            ->assertJsonPath('user.id', $buyer->id);

        $this->assertTrue($buyer->fresh()->isSuspended());
        $this->assertSame(0, $buyer->tokens()->count());
        $log = AuditLog::where('action', 'user.suspended')->firstOrFail();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame('Fraud report', $log->metadata['reason']);

        $this->postJson("/api/admin/users/{$buyer->id}/reactivate")->assertOk();
        $this->assertFalse($buyer->fresh()->isSuspended());
    }

    public function test_admin_cannot_lock_themselves_out_or_remove_the_last_admin(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$admin->id}/suspend")->assertUnprocessable();
        $this->patchJson("/api/admin/users/{$admin->id}/role", ['role' => 'buyer'])->assertUnprocessable();
        $this->deleteJson("/api/admin/users/{$admin->id}")->assertUnprocessable();

        // A second admin can be demoted while another active admin remains.
        $second = $this->makeAdmin();
        $this->patchJson("/api/admin/users/{$second->id}/role", ['role' => 'buyer'])->assertOk();
        $this->assertNotNull($second->fresh()->buyerProfile, 'Changing role provisions the matching profile.');
    }

    public function test_role_change_to_supplier_creates_pending_supplier_and_forces_new_sign_in(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeBuyer();
        $user->createToken('old');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/users/{$user->id}/role", ['role' => 'supplier'])->assertOk();

        $this->assertSame('pending', $user->fresh()->supplier->status);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_accounts_with_trading_history_cannot_be_deleted(): void
    {
        ['request' => $request, 'supplier' => $supplier, 'buyer' => $buyer, 'product' => $product] = $this->openRequestWithSupplier();

        Sanctum::actingAs($supplier);
        $quotationId = $this->postJson("/api/buyer-requests/{$request->id}/quotations", [
            'submit' => true, 'items' => [['product_id' => $product->id, 'unit_price' => 100]],
        ])->json('quotation.id');

        Sanctum::actingAs($this->makeAdmin());
        $this->deleteJson("/api/admin/users/{$buyer->id}")->assertStatus(409);
        $this->deleteJson("/api/admin/users/{$supplier->id}")->assertStatus(409);
        $this->assertNotNull(User::find($buyer->id));
        $this->assertNotNull(SupplierQuotation::find($quotationId));
    }

    public function test_admin_user_list_supports_search_and_filters(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $match = User::factory()->create(['name' => 'Zuberi Kassim', 'role' => 'supplier']);
        User::factory()->create(['name' => 'Someone Else']);

        $this->getJson('/api/admin/users?search=zuberi')
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $match->id)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/admin/users?role=supplier')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_admin_manages_the_product_catalog(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $categoryId = $this->postJson('/api/admin/catalog/categories', ['name' => 'Paint & Finishes'])
            ->assertCreated()
            ->assertJsonPath('category.slug', 'paint-finishes')
            ->json('category.id');

        $productId = $this->postJson('/api/admin/catalog/products', [
            'name' => 'Emulsion Paint 20L',
            'category_id' => $categoryId,
            'unit' => 'Bucket (20L)',
        ])->assertCreated()->json('product.id');

        $this->getJson('/api/products?search=Emulsion')->assertOk()->assertJsonPath('data.0.id', $productId);

        $this->patchJson("/api/admin/catalog/products/{$productId}", ['is_active' => false])->assertOk();
        $this->getJson('/api/products?search=Emulsion')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/catalog/products?status=inactive')->assertOk()->assertJsonPath('meta.total', 1);

        $this->postJson('/api/admin/catalog/categories', ['name' => 'Paint & Finishes'])->assertUnprocessable();
        $this->assertSame(1, Category::where('name', 'Paint & Finishes')->count());
    }

    public function test_non_admins_cannot_reach_admin_tools(): void
    {
        foreach ([$this->makeBuyer(), $this->makeSupplier()] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/admin/catalog/products', ['name' => 'X'])->assertForbidden();
            $this->getJson('/api/admin/audit-logs')->assertForbidden();
            $this->getJson('/api/admin/purchase-orders')->assertForbidden();
            $this->postJson("/api/admin/users/{$user->id}/reactivate")->assertForbidden();
        }
    }

    public function test_dashboard_reports_live_breakdowns_and_attention_items(): void
    {
        $this->makeSupplier('pending');
        $this->openRequestWithSupplier();

        Sanctum::actingAs($this->makeAdmin());
        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('statistics.buyer_requests_by_status.open', 1)
            ->assertJsonPath('attention.suppliers_awaiting_approval', 1)
            ->assertJsonStructure(['statistics' => ['completed_order_value', 'purchase_orders_by_status'], 'recent_activity']);
    }

    public function test_admin_oversight_lists(): void
    {
        $this->openRequestWithSupplier();
        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/admin/buyer-requests?status=open')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/purchase-orders')->assertOk()->assertJsonPath('meta.total', PurchaseOrder::count());
        $this->getJson('/api/admin/audit-logs')->assertOk()->assertJsonStructure(['data', 'meta']);
    }

    public function test_supplier_approval_is_audited(): void
    {
        $supplier = $this->makeSupplier('pending');
        $admin = $this->makeAdmin();

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/suppliers/{$supplier->supplier->id}/approve")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'supplier.approved',
            'actor_id' => $admin->id,
            'subject_type' => 'Supplier',
            'subject_id' => $supplier->supplier->id,
        ]);
    }

    public function test_create_admin_command_promotes_existing_user(): void
    {
        $user = $this->makeBuyer();

        $this->artisan('pal:create-admin', ['email' => $user->email])
            ->expectsConfirmation("{$user->email} already exists as a buyer. Promote it to administrator?", 'yes')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
    }
}
