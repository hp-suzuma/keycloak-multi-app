<?php

namespace App\Providers;

use App\Auth\SsoUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SsoUserProvider::class, fn () => new SsoUserProvider());
    }

    public function boot(): void
    {
        Auth::provider('sso-cache', fn ($app) => $app->make(SsoUserProvider::class));

        if (filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)) {
            URL::forceScheme('https');
        }
    }
}
