<?php

namespace App\Providers;

use App\Models\BuyerRequest;
use App\Models\SupplierInventory;
use App\Models\SupplierLocation;
use App\Models\SupplierPrice;
use App\Models\SupplierProduct;
use App\Policies\BuyerRequestPolicy;
use App\Policies\SupplierInventoryPolicy;
use App\Policies\SupplierLocationPolicy;
use App\Policies\SupplierPricePolicy;
use App\Policies\SupplierProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(
            BuyerRequest::class,
            BuyerRequestPolicy::class
        );

        Gate::policy(
            SupplierProduct::class,
            SupplierProductPolicy::class
        );

        Gate::policy(
            SupplierLocation::class,
            SupplierLocationPolicy::class
        );

        Gate::policy(
            SupplierInventory::class,
            SupplierInventoryPolicy::class
        );

        Gate::policy(
            SupplierPrice::class,
            SupplierPricePolicy::class
        );
    }
}
