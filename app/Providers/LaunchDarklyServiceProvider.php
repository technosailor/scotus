<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use LaunchDarkly\LDClient;
use LaunchDarkly\ClientBuilder;

class LaunchDarklyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LDClient::class, function ($app) {
            $sdkKey = config('services.launchdarkly.sdk_key');
            
            if (empty($sdkKey)) {
                throw new \Exception('LaunchDarkly SDK key is not configured');
            }
            
            return ClientBuilder::init($sdkKey)->build();
        });
    }

    public function boot(): void
    {
        //
    }
}