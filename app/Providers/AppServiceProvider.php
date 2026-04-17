<?php

namespace App\Providers;

use App\Services\GS1\Gs1ValidooService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Gs1ValidooService::class, fn () => Gs1ValidooService::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Railway routes traffic through a proxy that terminates TLS, so the
        // container sees HTTP. Honor the X-Forwarded-Proto: https header by
        // forcing the scheme when APP_URL is https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
