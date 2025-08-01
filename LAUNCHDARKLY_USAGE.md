# LaunchDarkly Integration Guide

This guide shows how to use LaunchDarkly feature flags in your Laravel application.

## Setup

1. Add your LaunchDarkly credentials to your `.env` file:
```bash
LAUNCHDARKLY_SDK_KEY=sdk-key-123456789abcdef
LAUNCHDARKLY_CLIENT_SIDE_ID=123456789abcdef
```

2. The LaunchDarkly SDK is already configured and ready to use.

## Usage Examples

### Basic Feature Flag Check

```php
use App\Services\FeatureFlagService;

public function index(FeatureFlagService $featureFlags)
{
    if ($featureFlags->isEnabled('new-dashboard')) {
        return view('dashboard.new');
    }
    
    return view('dashboard.legacy');
}
```

### Using the Facade

```php
use App\Facades\FeatureFlag;

if (FeatureFlag::isEnabled('analytics-tracking')) {
    // Track analytics
}
```

### Feature Flag Variations

```php
$theme = $featureFlags->getVariation('theme-variant', 'default');
// Returns 'dark', 'light', or 'default'
```

### User Context

```php
$userContext = [
    'key' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'custom' => [
        'plan' => 'premium',
        'region' => 'us-east'
    ]
];

$isPremiumFeatureEnabled = $featureFlags->isEnabled('premium-features', $userContext);
```

### Blade Templates

Use the custom Blade directive:

```blade
@feature('new-ui')
    <div class="new-interface">
        <!-- New UI components -->
    </div>
@else
    <div class="legacy-interface">
        <!-- Legacy UI components -->
    </div>
@endif
```

### Tracking Events

```php
$featureFlags->track('button-clicked', $userContext, [
    'button' => 'checkout',
    'page' => 'product-detail'
]);
```

## Common Use Cases

1. **A/B Testing**: Use variations to test different UI/UX approaches
2. **Feature Rollouts**: Gradually enable features for specific user segments
3. **Kill Switches**: Quickly disable features if issues arise
4. **User Segmentation**: Show different features based on user attributes
5. **Regional Features**: Enable features based on user location

## Best Practices

1. Always provide sensible defaults
2. Use descriptive flag names
3. Keep user context minimal but useful
4. Monitor flag usage in LaunchDarkly dashboard
5. Clean up unused flags regularly