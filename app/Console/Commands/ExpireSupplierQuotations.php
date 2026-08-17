<?php

namespace App\Console\Commands;

use App\Models\SupplierQuotation;
use App\Services\SupplierQuotationStatusService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:expire-supplier-quotations')]
#[Description('Expire supplier quotations whose validity period has passed')]
class ExpireSupplierQuotations extends Command
{
    public function handle(
        SupplierQuotationStatusService $statusService
    ): int {
        $expiredCount = 0;

        SupplierQuotation::query()
            ->where('status', 'submitted')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', now())
            ->each(function (SupplierQuotation $quotation) use (
                $statusService,
                &$expiredCount
            ): void {
                $statusService->expire($quotation);
                $expiredCount++;
            });

        $this->info(
            "Expired {$expiredCount} supplier quotation(s)."
        );

        return self::SUCCESS;
    }
}
