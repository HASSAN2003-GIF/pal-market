<?php

namespace App\Console\Commands;

use App\Models\BuyerRequest;
use App\Services\BuyerRequestStatusService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:expire-buyer-requests')]
#[Description('Close open buyer requests whose quoting window has ended')]
class ExpireBuyerRequests extends Command
{
    public function handle(BuyerRequestStatusService $statusService): int
    {
        $expired = 0;

        BuyerRequest::query()
            ->where('status', 'open')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lazyById()
            ->each(function (BuyerRequest $request) use ($statusService, &$expired): void {
                $statusService->expire($request);
                $expired++;
            });

        $this->info("Expired {$expired} buyer request(s).");

        return self::SUCCESS;
    }
}
