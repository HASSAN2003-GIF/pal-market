<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BuyerRequest;
use App\Models\PurchaseOrder;
use App\Models\SupplierQuotation;
use App\Support\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class MarketplaceWorkflowTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    public function test_full_request_to_completed_order_journey(): void
    {
        $buyer = $this->makeBuyer();
        $supplier = $this->makeSupplier();
        $cement = $this->makeProduct(['name' => 'Cement 42.5R']);
        $rebar = $this->makeProduct(['name' => 'Rebar 12mm', 'unit' => 'piece']);
        $supplier->supplier->supplierProducts()->create(['product_id' => $cement->id]);
        $supplier->supplier->supplierProducts()->create(['product_id' => $rebar->id]);

        // Buyer drafts a request with items in one call, then publishes it.
        Sanctum::actingAs($buyer);
        $requestId = $this->postJson('/api/buyer-requests', [
            'title' => 'Mbezi house foundation',
            'delivery_region' => 'Dar es Salaam',
            'needed_by' => now()->addWeek()->toDateString(),
            'items' => [
                ['product_id' => $cement->id, 'quantity' => 120],
                ['product_id' => $rebar->id, 'quantity' => 80, 'notes' => 'Straight lengths'],
            ],
        ])->assertCreated()
            ->assertJsonPath('buyer_request.status', 'draft')
            ->assertJsonCount(2, 'buyer_request.items')
            ->assertJsonPath('buyer_request.items.0.unit', 'bag')
            ->json('buyer_request.id');

        $this->postJson("/api/buyer-requests/{$requestId}/publish", ['open_for_days' => 10])
            ->assertOk()
            ->assertJsonPath('buyer_request.status', 'open');
        $this->assertTrue(BuyerRequest::find($requestId)->expires_at->between(now()->addDays(9), now()->addDays(11)));

        // Supplier finds it and submits a full quotation atomically.
        Sanctum::actingAs($supplier);
        $this->getJson('/api/market-requests?quoted=no')->assertOk()->assertJsonPath('data.0.id', $requestId);

        $quotation = $this->postJson("/api/buyer-requests/{$requestId}/quotations", [
            'delivery_fee' => 50000,
            'valid_until' => now()->addDays(5)->toIso8601String(),
            'notes' => 'Delivered to site within 48h.',
            'submit' => true,
            'items' => [
                ['product_id' => $cement->id, 'unit_price' => 17500],
                ['product_id' => $rebar->id, 'unit_price' => 24000],
            ],
        ])->assertCreated()
            ->assertJsonPath('quotation.status', 'submitted')
            ->json('quotation');

        $this->assertEquals(120 * 17500 + 80 * 24000, (float) $quotation['subtotal']);
        $this->assertEquals(120 * 17500 + 80 * 24000 + 50000, (float) $quotation['total_amount']);
        $this->getJson('/api/quotations')->assertOk()->assertJsonPath('data.0.id', $quotation['id']);

        // Buyer reviews, accepts and raises the order in one step.
        Sanctum::actingAs($buyer);
        $this->getJson("/api/buyer-requests/{$requestId}")
            ->assertOk()
            ->assertJsonCount(1, 'buyer_request.quotations')
            ->assertJsonPath('buyer_request.quotations.0.supplier.business_name', $supplier->supplier->business_name);

        $orderId = $this->postJson("/api/quotations/{$quotation['id']}/accept", ['create_purchase_order' => true])
            ->assertOk()
            ->assertJsonPath('quotation.status', 'accepted')
            ->assertJsonPath('purchase_order.status', 'pending')
            ->json('purchase_order.id');

        $this->assertSame('closed', BuyerRequest::find($requestId)->status);

        // Supplier fulfils, buyer receives and completes.
        Sanctum::actingAs($supplier);
        foreach (['confirm' => 'confirmed', 'process' => 'processing', 'ship' => 'shipped'] as $action => $status) {
            $this->postJson("/api/purchase-orders/{$orderId}/{$action}")->assertOk()->assertJsonPath('purchase_order.status', $status);
        }
        $this->postJson("/api/purchase-orders/{$orderId}/deliver")->assertForbidden();

        Sanctum::actingAs($buyer);
        $this->postJson("/api/purchase-orders/{$orderId}/deliver")->assertOk();
        $this->postJson("/api/purchase-orders/{$orderId}/complete")->assertOk()->assertJsonPath('purchase_order.status', 'completed');

        $this->getJson('/api/purchase-orders?status=completed')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/workspace/summary')->assertOk()->assertJsonPath('summary.buyer.requests_by_status.closed', 1);

        // Six status changes plus the order document issued on confirmation.
        $this->assertSame(7, AuditLog::where('subject_type', 'PurchaseOrder')->count());
        $this->assertTrue(AuditLog::where('action', 'order_document.issued')->exists());
    }

    public function test_atomic_quotation_rolls_back_entirely_when_a_line_is_invalid(): void
    {
        ['request' => $request, 'supplier' => $supplier, 'product' => $product] = $this->openRequestWithSupplier();
        $notStocked = $this->makeProduct();
        $request->items()->create(['product_id' => $notStocked->id, 'quantity' => 5, 'unit' => 'bag']);

        Sanctum::actingAs($supplier);
        $this->postJson("/api/buyer-requests/{$request->id}/quotations", [
            'submit' => true,
            'items' => [
                ['product_id' => $product->id, 'unit_price' => 100],
                ['product_id' => $notStocked->id, 'unit_price' => 100],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');

        $this->assertSame(0, SupplierQuotation::count(), 'A failed submission must not leave an orphaned draft.');

        // ...so the supplier can simply retry with a valid quotation.
        $this->postJson("/api/buyer-requests/{$request->id}/quotations", [
            'submit' => true,
            'items' => [['product_id' => $product->id, 'unit_price' => 100]],
        ])->assertCreated();
    }

    public function test_publishing_an_empty_request_is_rejected(): void
    {
        $buyer = $this->makeBuyer();
        Sanctum::actingAs($buyer);

        $id = $this->postJson('/api/buyer-requests', ['title' => 'Empty'])->assertCreated()->json('buyer_request.id');

        $this->postJson("/api/buyer-requests/{$id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_submitting_a_quotation_without_items_is_rejected(): void
    {
        ['request' => $request, 'supplier' => $supplier] = $this->openRequestWithSupplier();
        Sanctum::actingAs($supplier);

        $id = $this->postJson("/api/buyer-requests/{$request->id}/quotations")->assertCreated()->json('quotation.id');

        $this->postJson("/api/quotations/{$id}/submit")->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_draft_items_can_be_edited_and_removed_but_not_after_publishing(): void
    {
        $buyer = $this->makeBuyer();
        $product = $this->makeProduct();
        Sanctum::actingAs($buyer);

        $request = $this->postJson('/api/buyer-requests', [
            'title' => 'Roofing', 'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ])->json('buyer_request');
        $itemId = $request['items'][0]['id'];

        $this->putJson("/api/buyer-requests/{$request['id']}/items/{$itemId}", ['quantity' => 25])
            ->assertOk()->assertJsonPath('item.quantity', 25);

        $this->postJson("/api/buyer-requests/{$request['id']}/publish")->assertOk();

        $this->putJson("/api/buyer-requests/{$request['id']}/items/{$itemId}", ['quantity' => 30])->assertForbidden();
        $this->deleteJson("/api/buyer-requests/{$request['id']}/items/{$itemId}")->assertForbidden();
        $this->deleteJson("/api/buyer-requests/{$request['id']}")->assertForbidden();
    }

    public function test_buyer_cannot_touch_items_of_another_buyers_request(): void
    {
        ['request' => $request] = $this->openRequestWithSupplier();
        $request->update(['status' => 'draft']);
        $item = $request->items()->first();

        Sanctum::actingAs($this->makeBuyer());
        $this->deleteJson("/api/buyer-requests/{$request->id}/items/{$item->id}")->assertForbidden();
    }

    public function test_cancelling_a_request_cancels_live_quotations(): void
    {
        ['request' => $request, 'supplier' => $supplier, 'buyer' => $buyer, 'product' => $product] = $this->openRequestWithSupplier();

        Sanctum::actingAs($supplier);
        $quotationId = $this->postJson("/api/buyer-requests/{$request->id}/quotations", [
            'submit' => true, 'items' => [['product_id' => $product->id, 'unit_price' => 900]],
        ])->json('quotation.id');

        Sanctum::actingAs($buyer);
        $this->postJson("/api/buyer-requests/{$request->id}/cancel")->assertOk();

        $this->assertSame('cancelled', SupplierQuotation::find($quotationId)->status);
        $this->postJson("/api/quotations/{$quotationId}/accept")->assertUnprocessable();
    }

    public function test_quotation_cannot_be_accepted_once_request_has_expired(): void
    {
        ['request' => $request, 'supplier' => $supplier, 'buyer' => $buyer, 'product' => $product] = $this->openRequestWithSupplier();

        Sanctum::actingAs($supplier);
        $quotationId = $this->postJson("/api/buyer-requests/{$request->id}/quotations", [
            'submit' => true, 'items' => [['product_id' => $product->id, 'unit_price' => 900]],
        ])->json('quotation.id');

        $request->update(['expires_at' => now()->subMinute()]);
        $this->artisan('app:expire-buyer-requests')->assertSuccessful();
        $this->assertSame('expired', $request->fresh()->status);

        Sanctum::actingAs($buyer);
        $this->postJson("/api/quotations/{$quotationId}/accept")->assertUnprocessable();
    }

    public function test_supplier_can_withdraw_and_buyer_can_decline(): void
    {
        ['request' => $request, 'supplier' => $supplier, 'buyer' => $buyer, 'product' => $product] = $this->openRequestWithSupplier();
        $second = $this->makeSupplier();
        $second->supplier->supplierProducts()->create(['product_id' => $product->id]);

        Sanctum::actingAs($supplier);
        $first = $this->postJson("/api/buyer-requests/{$request->id}/quotations", [
            'submit' => true, 'items' => [['product_id' => $product->id, 'unit_price' => 900]],
        ])->json('quotation.id');
        $this->postJson("/api/quotations/{$first}/withdraw")->assertOk()->assertJsonPath('quotation.status', 'cancelled');

        Sanctum::actingAs($second);
        $other = $this->postJson("/api/buyer-requests/{$request->id}/quotations", [
            'submit' => true, 'items' => [['product_id' => $product->id, 'unit_price' => 850]],
        ])->json('quotation.id');

        Sanctum::actingAs($buyer);
        $this->postJson("/api/quotations/{$other}/reject")->assertOk()->assertJsonPath('quotation.status', 'rejected');
        $this->postJson("/api/quotations/{$other}/accept")->assertUnprocessable();
    }

    public function test_purchase_order_list_only_shows_own_orders(): void
    {
        ['request' => $request, 'supplier' => $supplier, 'buyer' => $buyer, 'product' => $product] = $this->openRequestWithSupplier();

        Sanctum::actingAs($supplier);
        $quotationId = $this->postJson("/api/buyer-requests/{$request->id}/quotations", [
            'submit' => true, 'items' => [['product_id' => $product->id, 'unit_price' => 900]],
        ])->json('quotation.id');

        Sanctum::actingAs($buyer);
        $this->postJson("/api/quotations/{$quotationId}/accept", ['create_purchase_order' => true])->assertOk();

        $this->getJson('/api/purchase-orders')->assertOk()->assertJsonPath('meta.total', 1);
        Sanctum::actingAs($supplier);
        $this->getJson('/api/purchase-orders')->assertOk()->assertJsonPath('meta.total', 1);
        Sanctum::actingAs($this->makeBuyer());
        $this->getJson('/api/purchase-orders')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/purchase-orders/'.PurchaseOrder::first()->id)->assertForbidden();
    }

    public function test_document_numbers_do_not_collide_within_the_same_second(): void
    {
        $numbers = collect(range(1, 500))->map(fn () => DocumentNumber::make('QUO'));

        $this->assertSame(500, $numbers->unique()->count());
        $this->assertMatchesRegularExpression('/^QUO-\d{6}-[A-Z2-9]{6}$/', $numbers->first());
    }

    public function test_supplier_summary_counts_open_requests_not_yet_quoted(): void
    {
        ['supplier' => $supplier] = $this->openRequestWithSupplier();

        Sanctum::actingAs($supplier);
        $this->getJson('/api/workspace/summary')
            ->assertOk()
            ->assertJsonPath('summary.supplier.open_market_requests', 1)
            ->assertJsonPath('summary.supplier.catalog_products', 1);
    }
}
