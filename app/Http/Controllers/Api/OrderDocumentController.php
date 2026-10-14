<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\AuditLogger;
use App\Services\OrderDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The printable purchase order. Available to the buyer, the supplier and
 * administrators once the supplier has confirmed the order. Downloading it
 * never changes the order, approves anything or records a payment.
 */
class OrderDocumentController extends Controller
{
    public function __construct(
        private OrderDocumentService $documents,
        private AuditLogger $audit,
    ) {}

    public function show(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('viewDocument', $purchaseOrder);

        $versions = $purchaseOrder->documents()->with('generator:id,name')->get();
        $latest = $versions->first();

        return response()->json([
            'available' => $latest !== null
                || ! ($purchaseOrder->status === 'pending' || ($purchaseOrder->status === 'cancelled' && ! $purchaseOrder->confirmed_at)),
            'can_edit_signatories' => $request->user()->can('editDocument', $purchaseOrder),
            'current' => $latest ? [
                'reference' => $latest->reference(),
                'version' => $latest->version,
                'issued_at' => $latest->created_at,
                'fingerprint' => substr($latest->fingerprint, 0, 16),
                'signatories' => $latest->snapshot['signatories'] ?? null,
            ] : null,
            'versions' => $versions->map(fn ($doc) => [
                'reference' => $doc->reference(),
                'version' => $doc->version,
                'reason' => $doc->reason,
                'issued_at' => $doc->created_at,
                'issued_by' => $doc->generator?->name,
            ]),
        ]);
    }

    public function download(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('viewDocument', $purchaseOrder);

        $validated = $request->validate([
            'lang' => ['nullable', Rule::in(OrderDocumentService::LOCALES)],
            'version' => ['nullable', 'integer', 'min:1'],
            'disposition' => ['nullable', Rule::in(['inline', 'attachment'])],
        ]);

        $document = isset($validated['version'])
            ? $purchaseOrder->documents()->where('version', $validated['version'])->firstOrFail()
            : $this->documents->current($purchaseOrder, $request->user());

        $locale = $validated['lang'] ?? app()->getLocale();
        $pdf = $this->documents->render($document, $locale, $purchaseOrder->status);

        $this->audit->record('order_document.downloaded', "Downloaded {$document->reference()} ({$locale}).", $purchaseOrder, [
            'version' => $document->version,
        ]);

        $disposition = ($validated['disposition'] ?? 'attachment').'; filename="'.$this->documents->filename($document, $locale).'"';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Buyers may print the names and job titles of the people who will sign
     * by hand. Signatures and dates are never filled in by the system.
     */
    public function updateSignatories(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('editDocument', $purchaseOrder);

        $validated = $request->validate([
            'engineer_name' => ['nullable', 'string', 'max:120'],
            'engineer_designation' => ['nullable', 'string', 'max:120'],
            'supervisor_name' => ['nullable', 'string', 'max:120'],
            'supervisor_designation' => ['nullable', 'string', 'max:120'],
        ]);

        $this->documents->current($purchaseOrder, $request->user());
        $document = $this->documents->issue($purchaseOrder, 'signatories', $request->user(), $validated);

        return response()->json([
            'message' => __('Names saved. The document was updated to version :version.', ['version' => $document->version]),
            'version' => $document->version,
        ]);
    }
}
