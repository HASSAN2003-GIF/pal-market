<?php

namespace App\Providers;

use App\Models\BuyerRequest;
use App\Models\SupplierInventory;
use App\Models\SupplierLocation;
use App\Models\SupplierPrice;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Policies\BuyerRequestPolicy;
use App\Policies\SupplierInventoryPolicy;
use App\Policies\SupplierLocationPolicy;
use App\Policies\SupplierPricePolicy;
use App\Policies\SupplierProductPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Surface lazy-loading (N+1) and silently discarded attributes during
        // development and tests; never in production.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Gate::policy(BuyerRequest::class, BuyerRequestPolicy::class);
        Gate::policy(SupplierProduct::class, SupplierProductPolicy::class);
        Gate::policy(SupplierLocation::class, SupplierLocationPolicy::class);
        Gate::policy(SupplierInventory::class, SupplierInventoryPolicy::class);
        Gate::policy(SupplierPrice::class, SupplierPricePolicy::class);

        $this->configureRateLimiting();

        // Password reset emails link to the SPA, which posts to the API.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => url('/reset-password').'?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Sign-in, registration and password-reset endpoints.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by('auth-ip:'.$request->ip()),
            Limit::perHour(60)->by('auth-ip-hour:'.$request->ip()),
        ]);

        // Writes that create marketplace records.
        RateLimiter::for('marketplace-writes', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Type-ahead fires on every keystroke (the client debounces).
        RateLimiter::for('catalog-suggest', fn (Request $request) => Limit::perMinute(90)
            ->by('suggest:'.($request->user()?->id ?: $request->ip())));

        // Photo uploads are processed in the request: keep them modest.
        RateLimiter::for('uploads', fn (Request $request) => [
            Limit::perMinute(20)->by('uploads:'.($request->user()?->id ?: $request->ip())),
            Limit::perHour(200)->by('uploads-hour:'.($request->user()?->id ?: $request->ip())),
        ]);

        // PDF rendering is CPU-bound.
        RateLimiter::for('documents', fn (Request $request) => Limit::perMinute(20)
            ->by('documents:'.($request->user()?->id ?: $request->ip())));
    }
}
