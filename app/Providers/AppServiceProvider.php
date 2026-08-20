<?php

namespace App\Providers;

use App\Models\BuyerRequest;
use App\Models\SupplierProduct;
use App\Policies\BuyerRequestPolicy;
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
    }
}
