<?php

namespace App\Services;

use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;

class FeatureFlagService
{
    public function __construct(
        private LDClient $client
    ) {}

    public function isEnabled(string $flagKey, ?array $userContext = null): bool
    {
        $context = $this->createContext($userContext);
        
        return $this->client->variation($flagKey, $context, false);
    }

    public function getVariation(string $flagKey, $defaultValue = null, ?array $userContext = null)
    {
        $context = $this->createContext($userContext);
        
        return $this->client->variation($flagKey, $context, $defaultValue);
    }

    public function getAllFlags(?array $userContext = null): array
    {
        $context = $this->createContext($userContext);
        
        return $this->client->allFlags($context);
    }

    public function track(string $eventName, ?array $userContext = null, ?array $data = null): void
    {
        $context = $this->createContext($userContext);
        
        $this->client->track($eventName, $context, $data);
    }

    private function createContext(?array $userContext = null): LDContext
    {
        $userKey = $userContext['key'] ?? 'anonymous-' . session()->getId();
        
        $contextBuilder = LDContext::builder($userKey);
        
        if (isset($userContext['name'])) {
            $contextBuilder->name($userContext['name']);
        }
        
        if (isset($userContext['email'])) {
            $contextBuilder->set('email', $userContext['email']);
        }
        
        if (isset($userContext['custom'])) {
            foreach ($userContext['custom'] as $key => $value) {
                $contextBuilder->set($key, $value);
            }
        }
        
        return $contextBuilder->build();
    }
}