<?php

namespace App\Providers;

use App\Models\BuyerRequest;
use App\Policies\BuyerRequestPolicy;
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
    }
}
