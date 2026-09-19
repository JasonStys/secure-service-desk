<?php

/**
 * File: Registers application-level rate limiting and service boot behavior.
 * Symbols: AppServiceProvider, register(), and boot().
 * State: API rate-limit key and quota; exact declarations are in docs/code-index.md.
 */

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Concrete services are intentionally auto-resolved by the container.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $actor = $request->attributes->get('actor');

            return Limit::perMinute(60)->by($actor?->id ?? $request->ip());
        });
    }
}
