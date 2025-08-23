<?php

namespace JTD\LaravelAI\Drivers\XAI\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIMessage;

/**
 * Validates Health for xAI
 *
 * Provides health check and validation methods for xAI driver.
 * Includes credential validation, API connectivity tests,
 * and service health monitoring.
 */
trait ValidatesHealth
{
    /**
     * Validate xAI credentials and configuration.
     */
    public function validateCredentials(): array
    {
        $startTime = microtime(true);
        $result = [
            'valid' => false,
            'provider' => $this->providerName,
            'api_key_format' => 'invalid',
            'api_connectivity' => false,
            'available_models' => 0,
            'default_model_available' => false,
            'response_time_ms' => 0,
            'errors' => [],
            'warnings' => [],
            'api_version' => null,
        ];

        try {
            // Check API key format
            $apiKey = $this->config['api_key'] ?? '';
            if (empty($apiKey)) {
                $result['errors'][] = 'API key is missing';

                return $result;
            }

            if (! str_starts_with($apiKey, 'xai-')) {
                $result['warnings'][] = 'API key format may be incorrect (should start with "xai-")';
                $result['api_key_format'] = 'warning';
            } else {
                $result['api_key_format'] = 'valid';
            }

            // Test API connectivity with models endpoint
            $modelsResponse = $this->client->get($this->config['base_url'] . '/models');

            if ($modelsResponse->successful()) {
                $result['api_connectivity'] = true;
                $modelsData = $modelsResponse->json();

                $result['available_models'] = count($modelsData['data'] ?? []);
                $result['api_version'] = $modelsData['api_version'] ?? 'v1';

                // Check if default model is available
                $defaultModel = $this->getDefaultModel();
                $availableModelIds = array_column($modelsData['data'] ?? [], 'id');
                $result['default_model_available'] = in_array($defaultModel, $availableModelIds);

                if (! $result['default_model_available']) {
                    $result['warnings'][] = "Default model '{$defaultModel}' not found in available models";
                }

                // Test with a simple chat completion
                $testResponse = $this->client->post($this->config['base_url'] . '/chat/completions', [
                    'model' => $availableModelIds[0] ?? $defaultModel,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello'],
                    ],
                    'max_tokens' => 5,
                ]);

                if ($testResponse->successful()) {
                    $result['valid'] = true;
                } else {
                    $result['errors'][] = 'Chat completion test failed: ' . $testResponse->body();
                }
            } else {
                $result['errors'][] = 'Failed to connect to xAI API: ' . $modelsResponse->body();
            }
        } catch (\Exception $e) {
            $result['errors'][] = 'Validation error: ' . $e->getMessage();

            Log::error('xAI credential validation failed', [
                'provider' => $this->providerName,
                'error' => $e->getMessage(),
            ]);
        }

        $result['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return $result;
    }

    /**
     * Perform comprehensive health check.
     */
    public function healthCheck(): array
    {
        $startTime = microtime(true);
        $health = [
            'status' => 'healthy',
            'provider' => $this->providerName,
            'timestamp' => now()->toISOString(),
            'response_time_ms' => 0,
            'checks' => [],
            'metrics' => [],
            'issues' => [],
        ];

        // Check 1: Credential validation
        $credentialCheck = $this->validateCredentials();
        $health['checks']['credentials'] = [
            'status' => $credentialCheck['valid'] ? 'pass' : 'fail',
            'details' => $credentialCheck,
        ];

        if (! $credentialCheck['valid']) {
            $health['status'] = 'unhealthy';
            $health['issues'][] = 'Invalid credentials';
        }

        // Check 2: API connectivity
        $connectivityCheck = $this->checkApiConnectivity();
        $health['checks']['connectivity'] = [
            'status' => $connectivityCheck['connected'] ? 'pass' : 'fail',
            'details' => $connectivityCheck,
        ];

        if (! $connectivityCheck['connected']) {
            $health['status'] = 'unhealthy';
            $health['issues'][] = 'API connectivity issues';
        }

        // Check 3: Model availability
        $modelCheck = $this->checkModelAvailability();
        $health['checks']['models'] = [
            'status' => $modelCheck['available'] > 0 ? 'pass' : 'fail',
            'details' => $modelCheck,
        ];

        if ($modelCheck['available'] === 0) {
            $health['status'] = 'unhealthy';
            $health['issues'][] = 'No models available';
        }

        // Check 4: Rate limiting status
        $rateLimitCheck = $this->checkRateLimitStatus();
        $health['checks']['rate_limits'] = [
            'status' => $rateLimitCheck['healthy'] ? 'pass' : 'warn',
            'details' => $rateLimitCheck,
        ];

        if (! $rateLimitCheck['healthy']) {
            $health['status'] = $health['status'] === 'healthy' ? 'degraded' : $health['status'];
            $health['issues'][] = 'Rate limiting concerns';
        }

        // Collect metrics
        $health['metrics'] = [
            'total_response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'api_response_time_ms' => $credentialCheck['response_time_ms'] ?? 0,
            'available_models' => $modelCheck['available'] ?? 0,
            'default_model_available' => $credentialCheck['default_model_available'] ?? false,
        ];

        $health['response_time_ms'] = $health['metrics']['total_response_time_ms'];

        return $health;
    }

    /**
     * Check API connectivity.
     */
    protected function checkApiConnectivity(): array
    {
        $startTime = microtime(true);
        $result = [
            'connected' => false,
            'response_time_ms' => 0,
            'base_url' => $this->config['base_url'],
            'error' => null,
        ];

        try {
            $response = $this->client->get($this->config['base_url'] . '/models');
            $result['connected'] = $response->successful();
            $result['status_code'] = $response->status();

            if (! $result['connected']) {
                $result['error'] = $response->body();
            }
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        $result['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return $result;
    }

    /**
     * Check model availability.
     */
    protected function checkModelAvailability(): array
    {
        try {
            $models = $this->getAvailableModels();
            $defaultModel = $this->getDefaultModel();

            return [
                'available' => count($models),
                'models' => array_column($models, 'id'),
                'default_model' => $defaultModel,
                'default_available' => in_array($defaultModel, array_column($models, 'id')),
            ];
        } catch (\Exception $e) {
            return [
                'available' => 0,
                'models' => [],
                'default_model' => $this->getDefaultModel(),
                'default_available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check rate limiting status.
     */
    protected function checkRateLimitStatus(): array
    {
        $cacheKey = "xai_rate_limit_status_{$this->providerName}";
        $cached = Cache::get($cacheKey, []);

        return [
            'healthy' => true, // xAI doesn't provide rate limit headers typically
            'requests_remaining' => null,
            'tokens_remaining' => null,
            'reset_time' => null,
            'last_rate_limit' => $cached['last_rate_limit'] ?? null,
            'rate_limit_count_24h' => $cached['count_24h'] ?? 0,
        ];
    }

    /**
     * Get service status from xAI.
     */
    public function getServiceStatus(): array
    {
        $cacheKey = 'xai_service_status';

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $status = [
            'status' => 'unknown',
            'last_checked' => now()->toISOString(),
            'incidents' => [],
            'maintenance' => [],
        ];

        try {
            // xAI doesn't have a public status page API yet
            // This is a placeholder for future implementation
            $status['status'] = 'operational';

            // Cache for 5 minutes
            Cache::put($cacheKey, $status, 300);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch xAI service status', [
                'error' => $e->getMessage(),
            ]);
        }

        return $status;
    }

    /**
     * Test driver with a simple request.
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);
        $result = [
            'success' => false,
            'response_time_ms' => 0,
            'model_used' => null,
            'tokens_used' => 0,
            'cost' => 0.0,
            'error' => null,
        ];

        try {
            $response = $this->sendMessage(
                AIMessage::user('Hello'),
                [
                    'model' => $this->getDefaultModel(),
                    'max_tokens' => 5,
                ]
            );

            $result['success'] = true;
            $result['model_used'] = $response->model;
            $result['tokens_used'] = $response->tokenUsage->totalTokens;
            $result['cost'] = $response->cost;
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        $result['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return $result;
    }

    /**
     * Get driver diagnostics.
     */
    public function getDiagnostics(): array
    {
        return [
            'provider' => $this->providerName,
            'driver_version' => '1.0.0',
            'configuration' => $this->getConfig(),
            'capabilities' => $this->getCapabilities(),
            'health_check' => $this->healthCheck(),
            'service_status' => $this->getServiceStatus(),
            'last_sync' => $this->getLastSyncTime()?->toISOString(),
        ];
    }

    /**
     * Monitor driver performance.
     */
    public function getPerformanceMetrics(): array
    {
        $cacheKey = 'xai_performance_metrics';

        return Cache::get($cacheKey, [
            'average_response_time_ms' => 0,
            'success_rate' => 100,
            'error_rate' => 0,
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0.0,
            'last_24h' => [
                'requests' => 0,
                'tokens' => 0,
                'cost' => 0.0,
                'errors' => 0,
            ],
        ]);
    }

    /**
     * Reset health check cache.
     */
    public function resetHealthCache(): void
    {
        $keys = [
            'xai_service_status',
            'xai_performance_metrics',
            "xai_rate_limit_status_{$this->providerName}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
