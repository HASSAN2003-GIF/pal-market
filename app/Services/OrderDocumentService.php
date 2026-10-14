<?php

namespace App\Services;

use App\Models\OrderDocument;
use App\Models\PurchaseOrder;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues and renders purchase order documents.
 *
 * A document's content is frozen in a snapshot when it is issued (normally
 * when the supplier confirms the order). Later edits to products, profiles or
 * prices never change it. Re-issuing with identical content returns the
 * existing version, so documents are not duplicated.
 */
class OrderDocumentService
{
    /** Orders that have been confirmed by the supplier at some point. */
    public const DOCUMENT_STATUSES = ['confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'];

    public const LOCALES = ['en', 'sw'];

    public function __construct(private AuditLogger $audit) {}

    public function latest(PurchaseOrder $order): ?OrderDocument
    {
        return $order->documents()->first();
    }

    /**
     * The current document, issuing the first version for orders confirmed
     * before documents existed.
     */
    public function current(PurchaseOrder $order, ?User $actor): OrderDocument
    {
        if ($document = $this->latest($order)) {
            return $document;
        }

        if ($order->status === 'pending' || ($order->status === 'cancelled' && ! $order->confirmed_at)) {
            throw ValidationException::withMessages([
                'status' => __('The order document is available once the supplier confirms the order.'),
            ]);
        }

        return $this->issue($order, 'first_download', $actor);
    }

    public function issue(PurchaseOrder $order, string $reason, ?User $actor, ?array $signatories = null): OrderDocument
    {
        $document = DB::transaction(function () use ($order, $reason, $actor, $signatories) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $latest = $order->documents()->first();

            // The order content is frozen at the first version; later versions
            // only change the names in the approval boxes.
            $content = $latest
                ? Arr::except($latest->snapshot, ['signatories', 'issued_at'])
                : $this->content($order);

            $snapshot = [
                ...$content,
                'signatories' => $this->cleanSignatories($signatories ?? $latest?->snapshot['signatories'] ?? []),
            ];
            $fingerprint = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE));

            if ($latest && hash_equals($latest->fingerprint, $fingerprint)) {
                return $latest;
            }

            return OrderDocument::create([
                'purchase_order_id' => $order->id,
                'version' => ($latest?->version ?? 0) + 1,
                'snapshot' => [...$snapshot, 'issued_at' => now()->toIso8601String()],
                'fingerprint' => $fingerprint,
                'reason' => $reason,
                'generated_by' => $actor?->id,
            ]);
        });

        if ($document->wasRecentlyCreated) {
            $this->audit->record(
                'order_document.issued',
                "Issued {$document->reference()} ({$reason}).",
                $order,
                ['version' => $document->version, 'fingerprint' => $document->fingerprint],
                $actor,
            );
        }

        return $document;
    }

    /**
     * Renders the PDF in English or Kiswahili. $liveStatus marks documents
     * of orders cancelled after issue.
     */
    public function render(OrderDocument $document, string $locale, string $liveStatus): string
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'en';
        $previous = App::getLocale();
        App::setLocale($locale);
        Carbon::setLocale($locale);

        try {
            $pdf = Pdf::setOptions([
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'chroot' => [public_path('brand')],
            ])->loadView('documents.purchase-order', [
                'doc' => $document->snapshot,
                'reference' => $document->reference(),
                'version' => $document->version,
                'fingerprint' => substr($document->fingerprint, 0, 16),
                'cancelled' => $liveStatus === 'cancelled',
                'logo' => $this->logo(),
            ])->setPaper('a4');

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();

            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
            $canvas->page_text(
                $canvas->get_width() - 125,
                $canvas->get_height() - 28,
                __('documents.page'),
                $font,
                8,
                [0.35, 0.38, 0.42],
            );

            return $dompdf->output();
        } finally {
            App::setLocale($previous);
            Carbon::setLocale($previous);
        }
    }

    public function filename(OrderDocument $document, string $locale): string
    {
        return preg_replace('/[^A-Za-z0-9-]/', '', $document->reference()).'-'.$locale.'.pdf';
    }

    private function content(PurchaseOrder $order): array
    {
        $order->loadMissing([
            'buyerProfile.user',
            'supplier.user',
            'supplier.locations',
            'supplierQuotation.buyerRequest',
            'items.product',
        ]);

        $request = $order->supplierQuotation?->buyerRequest;
        $location = $order->supplier?->locations->firstWhere('status', 'active') ?? $order->supplier?->locations->first();

        return [
            'schema' => 1,
            'order' => [
                'number' => $order->order_number,
                'status' => $order->status,
                'created_at' => $order->created_at?->toIso8601String(),
                'confirmed_at' => $order->confirmed_at?->toIso8601String(),
                'currency' => $order->currency,
                'subtotal' => (string) $order->subtotal,
                'delivery_fee' => (string) $order->delivery_fee,
                'total' => (string) $order->total_amount,
                'notes' => $order->notes,
            ],
            'quotation' => [
                'number' => $order->supplierQuotation?->quotation_number,
            ],
            'request' => [
                'number' => $request?->request_number,
                'title' => $request?->title,
                'delivery_region' => $request?->delivery_region,
                'delivery_address' => $request?->delivery_address,
                'needed_by' => $request?->needed_by ? Carbon::parse($request->needed_by)->toDateString() : null,
            ],
            'buyer' => [
                'business_name' => $order->buyerProfile?->business_name,
                'tin' => $order->buyerProfile?->tin_number,
                'contact_name' => $order->buyerProfile?->user?->name,
                'email' => $order->buyerProfile?->user?->email,
                'phone' => $order->buyerProfile?->user?->phone,
            ],
            'supplier' => [
                'business_name' => $order->supplier?->business_name,
                'tin' => $order->supplier?->tin_number,
                'contact_name' => $order->supplier?->user?->name,
                'email' => $order->supplier?->user?->email,
                'phone' => $order->supplier?->user?->phone,
                'address' => $location ? implode(', ', array_filter([
                    $location->address, $location->ward, $location->district, $location->region,
                ])) : null,
            ],
            'items' => $order->items->values()->map(fn ($item, $i) => [
                'no' => $i + 1,
                'name' => $item->product_name ?? $item->product?->name,
                'description' => $item->notes,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (string) $item->unit_price,
                'total' => (string) $item->total_price,
            ])->all(),
        ];
    }

    private function cleanSignatories(array $input): array
    {
        $clean = fn ($value) => is_string($value) && trim($value) !== '' ? mb_substr(trim(strip_tags($value)), 0, 120) : null;

        return [
            'engineer_name' => $clean($input['engineer_name'] ?? null),
            'engineer_designation' => $clean($input['engineer_designation'] ?? null),
            'supervisor_name' => $clean($input['supervisor_name'] ?? null),
            'supervisor_designation' => $clean($input['supervisor_designation'] ?? null),
        ];
    }

    private function logo(): ?string
    {
        $path = public_path('brand/pal-wordmark-light.svg');

        return is_file($path) ? 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }
}
