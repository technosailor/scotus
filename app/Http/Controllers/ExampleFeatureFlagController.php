<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FeatureFlagService;
use App\Facades\FeatureFlag;

class ExampleFeatureFlagController extends Controller
{
    public function index(Request $request, FeatureFlagService $featureFlags)
    {
        // Example 1: Check if a feature is enabled
        $newDashboardEnabled = $featureFlags->isEnabled('new-dashboard');
        
        // Example 2: Get a variation with default value
        $theme = $featureFlags->getVariation('theme-variant', 'default');
        
        // Example 3: Using with user context
        $userContext = [
            'key' => $request->user()?->id ?? 'anonymous',
            'email' => $request->user()?->email,
            'custom' => [
                'plan' => 'premium',
                'region' => 'us-east'
            ]
        ];
        
        $premiumFeatureEnabled = $featureFlags->isEnabled('premium-features', $userContext);
        
        // Example 4: Using the facade
        $analyticsEnabled = FeatureFlag::isEnabled('analytics-tracking');
        
        // Example 5: Track custom events
        $featureFlags->track('page-viewed', $userContext, [
            'page' => 'dashboard',
            'timestamp' => now()->toISOString()
        ]);
        
        return response()->json([
            'new_dashboard_enabled' => $newDashboardEnabled,
            'theme' => $theme,
            'premium_feature_enabled' => $premiumFeatureEnabled,
            'analytics_enabled' => $analyticsEnabled,
        ]);
    }
}