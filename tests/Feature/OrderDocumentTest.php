<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\OrderDocument;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\OrderDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class OrderDocumentTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    /**
     * @return array{buyer: User, supplier: User, order: PurchaseOrder, product: Product}
     */
    private function pendingOrder(): array
    {
        ['buyer' => $buyer, 'supplier' => $supplier, 'request' => $request, 'product' => $product] = $this->openRequestWithSupplier();
        $product->update(['name' => 'Simba Cement 42.5R']);

        Sanctum::actingAs($supplier);
        $quotationId = $this->postJson("/api/buyer-requests/{$request->id}/quotations", [
            'delivery_fee' => 50000,
            'valid_until' => now()->addDays(5)->toIso8601String(),
            'notes' => 'Delivered to site within 48h.',
            'submit' => true,
            'items' => [['product_id' => $product->id, 'unit_price' => 17500, 'notes' => 'Grey bags']],
        ])->assertCreated()->json('quotation.id');

        Sanctum::actingAs($buyer);
        $orderId = $this->postJson("/api/quotations/{$quotationId}/accept", ['create_purchase_order' => true])
            ->assertOk()->json('purchase_order.id');

        return ['buyer' => $buyer, 'supplier' => $supplier, 'order' => PurchaseOrder::findOrFail($orderId), 'product' => $product];
    }

    private function confirmedOrder(): array
    {
        $setup = $this->pendingOrder();
        Sanctum::actingAs($setup['supplier']);
        $this->postJson("/api/purchase-orders/{$setup['order']->id}/confirm")->assertOk();
        $setup['order']->refresh();

        return $setup;
    }

    public function test_confirming_an_order_issues_a_frozen_document(): void
    {
        ['order' => $order, 'product' => $product] = $this->confirmedOrder();

        $this->assertNotNull($order->confirmed_at);
        $document = OrderDocument::sole();
        $this->assertSame(1, $document->version);
        $this->assertSame('confirmed', $document->reason);
        $this->assertSame('Simba Cement 42.5R', $document->snapshot['items'][0]['name']);
        $this->assertSame('Grey bags', $document->snapshot['items'][0]['description']);
        $this->assertEquals(40 * 17500 + 50000, (float) $document->snapshot['order']['total']);
        $this->assertTrue(AuditLog::where('action', 'order_document.issued')->exists());

        // Order lines keep the name they were ordered under.
        $this->assertSame('Simba Cement 42.5R', $order->items->first()->product_name);

        // Later catalog changes do not alter the issued document.
        $product->update(['name' => 'Renamed Cement']);
        $this->getJson("/api/purchase-orders/{$order->id}/document/pdf")->assertOk();
        $this->assertSame(1, OrderDocument::count());
        $this->assertSame('Simba Cement 42.5R', OrderDocument::sole()->snapshot['items'][0]['name']);
    }

    public function test_document_is_unavailable_until_the_supplier_confirms(): void
    {
        ['buyer' => $buyer, 'order' => $order] = $this->pendingOrder();
        Sanctum::actingAs($buyer);

        $this->getJson("/api/purchase-orders/{$order->id}/document")->assertOk()->assertJsonPath('available', false);
        $this->getJson("/api/purchase-orders/{$order->id}/document/pdf")->assertUnprocessable();
        $this->assertSame(0, OrderDocument::count());
    }

    public function test_buyer_supplier_and_admin_can_download_but_others_cannot(): void
    {
        ['buyer' => $buyer, 'supplier' => $supplier, 'order' => $order] = $this->confirmedOrder();

        foreach ([$buyer, $supplier, $this->makeAdmin()] as $user) {
            Sanctum::actingAs($user);
            $response = $this->get("/api/purchase-orders/{$order->id}/document/pdf?lang=sw");
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertStringContainsString($order->order_number.'-D1-sw.pdf', $response->headers->get('Content-Disposition'));
        }

        foreach ([$this->makeBuyer(), $this->makeSupplier()] as $stranger) {
            Sanctum::actingAs($stranger);
            $this->getJson("/api/purchase-orders/{$order->id}/document/pdf")->assertForbidden();
            $this->getJson("/api/purchase-orders/{$order->id}/document")->assertForbidden();
        }

        $this->assertSame(3, AuditLog::where('action', 'order_document.downloaded')->count());
        $this->assertSame('confirmed', $order->fresh()->status, 'Downloading never changes the order.');
    }

    public function test_guests_cannot_download(): void
    {
        ['order' => $order] = $this->confirmedOrder();
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/purchase-orders/{$order->id}/document/pdf")->assertUnauthorized();
    }

    public function test_document_renders_in_english_and_swahili_with_blank_signature_boxes(): void
    {
        ['order' => $order] = $this->confirmedOrder();
        $document = OrderDocument::sole();

        $html = fn (string $locale) => (function () use ($document, $locale) {
            app()->setLocale($locale);

            return view('documents.purchase-order', [
                'doc' => $document->snapshot,
                'reference' => $document->reference(),
                'version' => 1,
                'fingerprint' => substr($document->fingerprint, 0, 16),
                'cancelled' => false,
                'logo' => null,
            ])->render();
        })();

        $en = $html('en');
        $this->assertStringContainsString('Purchase Order', $en);
        $this->assertStringContainsString('Prepared / checked by engineer', $en);
        $this->assertStringContainsString('Approved by supervisor / authorised officer', $en);
        $this->assertStringContainsString('Official stamp', $en);
        $this->assertStringContainsString('does not confirm that any payment has been made', $en);
        $this->assertStringContainsString('700,000.00', $en);
        $this->assertStringContainsString('750,000.00', $en);
        $this->assertStringContainsString($order->order_number, $en);

        $sw = $html('sw');
        $this->assertStringContainsString('Oda ya Ununuzi', $sw);
        $this->assertStringContainsString('Imeandaliwa / kukaguliwa na mhandisi', $sw);
        $this->assertStringContainsString('Muhuri rasmi', $sw);

        $pdf = app(OrderDocumentService::class)->render($document, 'sw', 'confirmed');
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_buyer_can_print_signatory_names_which_creates_a_new_version(): void
    {
        ['buyer' => $buyer, 'supplier' => $supplier, 'order' => $order] = $this->confirmedOrder();

        Sanctum::actingAs($supplier);
        $this->putJson("/api/purchase-orders/{$order->id}/document/signatories", ['engineer_name' => 'X'])->assertForbidden();

        Sanctum::actingAs($buyer);
        $names = [
            'engineer_name' => 'Eng. Asha Mollel',
            'engineer_designation' => 'Site Engineer',
            'supervisor_name' => '<b>Juma Said</b>',
            'supervisor_designation' => 'Project Manager',
        ];
        $this->putJson("/api/purchase-orders/{$order->id}/document/signatories", $names)
            ->assertOk()->assertJsonPath('version', 2);

        // Saving the same names again does not create a duplicate.
        $this->putJson("/api/purchase-orders/{$order->id}/document/signatories", $names)
            ->assertOk()->assertJsonPath('version', 2);

        $this->getJson("/api/purchase-orders/{$order->id}/document")
            ->assertOk()
            ->assertJsonPath('current.version', 2)
            ->assertJsonPath('current.signatories.engineer_name', 'Eng. Asha Mollel')
            ->assertJsonPath('current.signatories.supervisor_name', 'Juma Said')
            ->assertJsonCount(2, 'versions');

        $latest = OrderDocument::where('version', 2)->sole();
        $first = OrderDocument::where('version', 1)->sole();
        $this->assertSame(
            collect($first->snapshot)->except(['signatories', 'issued_at'])->all(),
            collect($latest->snapshot)->except(['signatories', 'issued_at'])->all(),
            'Only the approval names change between versions.',
        );

        // Earlier versions remain downloadable.
        $this->get("/api/purchase-orders/{$order->id}/document/pdf?version=1")->assertOk();
    }

    public function test_cancelled_after_confirmation_still_downloads_marked_cancelled(): void
    {
        ['buyer' => $buyer, 'order' => $order] = $this->confirmedOrder();

        Sanctum::actingAs($buyer);
        $this->postJson("/api/purchase-orders/{$order->id}/cancel")->assertOk();
        $this->get("/api/purchase-orders/{$order->id}/document/pdf")->assertOk();
        $this->putJson("/api/purchase-orders/{$order->id}/document/signatories", ['engineer_name' => 'X'])->assertForbidden();

        $html = view('documents.purchase-order', [
            'doc' => OrderDocument::sole()->snapshot,
            'reference' => 'R', 'version' => 1, 'fingerprint' => 'f', 'cancelled' => true, 'logo' => null,
        ])->render();
        $this->assertStringContainsString('CANCELLED', $html);
    }

    public function test_orders_confirmed_before_documents_existed_get_one_on_first_download(): void
    {
        ['buyer' => $buyer, 'order' => $order] = $this->pendingOrder();
        $order->forceFill(['status' => 'processing'])->save();

        Sanctum::actingAs($buyer);
        $this->get("/api/purchase-orders/{$order->id}/document/pdf")->assertOk();
        $this->get("/api/purchase-orders/{$order->id}/document/pdf")->assertOk();

        $this->assertSame(1, OrderDocument::count());
        $this->assertSame('first_download', OrderDocument::sole()->reason);
    }
}
