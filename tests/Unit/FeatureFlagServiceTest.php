<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FeatureFlagService;
use LaunchDarkly\LDClient;
use Mockery;

class FeatureFlagServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the LaunchDarkly client for testing
        $this->mockLDClient = Mockery::mock(LDClient::class);
        $this->app->instance(LDClient::class, $this->mockLDClient);
    }

    public function test_is_enabled_returns_boolean()
    {
        $this->mockLDClient
            ->shouldReceive('variation')
            ->once()
            ->with('test-flag', Mockery::any(), false)
            ->andReturn(true);

        $service = app(FeatureFlagService::class);
        $result = $service->isEnabled('test-flag');

        $this->assertTrue($result);
    }

    public function test_get_variation_returns_value()
    {
        $this->mockLDClient
            ->shouldReceive('variation')
            ->once()
            ->with('test-flag', Mockery::any(), 'default')
            ->andReturn('variant-a');

        $service = app(FeatureFlagService::class);
        $result = $service->getVariation('test-flag', 'default');

        $this->assertEquals('variant-a', $result);
    }

    public function test_get_all_flags_returns_array()
    {
        $expectedFlags = ['flag1' => true, 'flag2' => false];
        
        $this->mockLDClient
            ->shouldReceive('allFlags')
            ->once()
            ->with(Mockery::any())
            ->andReturn($expectedFlags);

        $service = app(FeatureFlagService::class);
        $result = $service->getAllFlags();

        $this->assertEquals($expectedFlags, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}