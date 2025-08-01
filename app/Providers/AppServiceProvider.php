<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\Services\FeatureFlagService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FeatureFlagService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerBladeDirectives();
    }

    protected function registerBladeDirectives(): void
    {
        Blade::if('feature', function ($flagKey, $userContext = null) {
            return app(FeatureFlagService::class)->isEnabled($flagKey, $userContext);
        });
    }
}
