<?php

namespace JTD\LaravelAI\Drivers\Gemini\Traits;

/**
 * Validates Health and Credentials for Gemini
 *
 * Handles credential validation, health checks,
 * and API connectivity testing for Google Gemini API.
 */
trait ValidatesHealth
{
    /**
     * Validate Gemini credentials.
     */
    public function validateCredentials(): array
    {
        $startTime = microtime(true);
        $result = [
            'status' => 'invalid',
            'valid' => false,
            'provider' => $this->providerName,
            'response_time_ms' => 0,
            'details' => [],
            'errors' => [],
        ];

        try {
            // Try to list models as a lightweight credential validation
            $response = $this->http
                ->withHeaders($this->getRequestHeaders())
                ->timeout($this->config['timeout'] ?? 30)
                ->get($this->buildModelsEndpoint());

            if ($response->successful()) {
                $data = $response->json();
                $modelsCount = count($data['models'] ?? []);

                $result['status'] = 'valid';
                $result['valid'] = true;
                $result['details'] = [
                    'models_available' => $modelsCount,
                    'api_accessible' => true,
                    'base_url' => $this->config['base_url'],
                    'api_version' => 'v1',
                ];
            } else {
                $this->handleValidationError($response, $result);
            }
        } catch (\Exception $e) {
            $result['errors'][] = 'API error: ' . $e->getMessage();
            $result['details'] = [
                'api_accessible' => false,
                'error_type' => get_class($e),
            ];
        }

        $result['response_time_ms'] = (microtime(true) - $startTime) * 1000;

        return $result;
    }

    /**
     * Handle validation errors from API response.
     */
    protected function handleValidationError($response, array &$result): void
    {
        $statusCode = $response->status();
        $data = $response->json();
        $error = $data['error'] ?? [];
        $message = $error['message'] ?? 'Unknown error';

        switch ($statusCode) {
            case 401:
            case 403:
                $result['errors'][] = 'Invalid credentials: ' . $message;
                $result['details'] = [
                    'api_accessible' => false,
                    'credentials_valid' => false,
                ];
                break;

            case 404:
                $result['errors'][] = 'API endpoint not found: ' . $message;
                $result['details'] = [
                    'api_accessible' => false,
                    'endpoint_exists' => false,
                ];
                break;

            default:
                $result['errors'][] = "API error ({$statusCode}): " . $message;
                $result['details'] = [
                    'api_accessible' => false,
                    'status_code' => $statusCode,
                ];
        }
    }

    /**
     * Get comprehensive health status.
     */
    public function getHealthStatus(): array
    {
        $startTime = microtime(true);
        $status = [
            'status' => 'unknown',
            'provider' => $this->providerName,
            'timestamp' => now()->toISOString(),
            'response_time_ms' => 0,
            'details' => [],
            'issues' => [],
        ];

        try {
            // Test models endpoint
            $modelsStartTime = microtime(true);
            $modelsResponse = $this->http
                ->withHeaders($this->getRequestHeaders())
                ->timeout($this->config['timeout'] ?? 30)
                ->get($this->buildModelsEndpoint());

            if (! $modelsResponse->successful()) {
                $status['status'] = 'unhealthy';
                $status['issues'][] = 'Models endpoint not accessible';
                $status['details'] = [
                    'models_accessible' => false,
                    'status_code' => $modelsResponse->status(),
                ];

                return $status;
            }

            $modelsData = $modelsResponse->json();
            $modelsCount = count($modelsData['models'] ?? []);
            $modelsResponseTime = (microtime(true) - $modelsStartTime) * 1000;

            if ($modelsCount === 0) {
                $status['status'] = 'degraded';
                $status['issues'][] = 'No models available';
                $status['details'] = [
                    'models_available' => 0,
                    'models_response_time_ms' => $modelsResponseTime,
                    'api_accessible' => true,
                ];

                return $status;
            }

            // Test a simple generation to verify full functionality
            $testStartTime = microtime(true);
            try {
                $testResponse = $this->http
                    ->withHeaders($this->getRequestHeaders())
                    ->timeout($this->config['timeout'] ?? 30)
                    ->post($this->buildEndpoint('gemini-pro'), [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [['text' => 'Hi']],
                            ],
                        ],
                        'generationConfig' => [
                            'maxOutputTokens' => 1,
                        ],
                    ]);

                $generationTime = (microtime(true) - $testStartTime) * 1000;

                if ($testResponse->successful()) {
                    $status['status'] = 'healthy';
                    $status['details'] = [
                        'models_available' => $modelsCount,
                        'models_response_time_ms' => $modelsResponseTime,
                        'generation_response_time_ms' => $generationTime,
                        'api_accessible' => true,
                        'generation_working' => true,
                        'test_model' => 'gemini-pro',
                    ];
                } else {
                    $status['status'] = 'degraded';
                    $status['details'] = [
                        'models_available' => $modelsCount,
                        'models_response_time_ms' => $modelsResponseTime,
                        'api_accessible' => true,
                        'generation_working' => false,
                        'generation_error' => $testResponse->json()['error']['message'] ?? 'Unknown error',
                    ];
                    $status['issues'][] = 'Generation endpoint not working properly';
                }
            } catch (\Exception $e) {
                // Models work but generation doesn't
                $status['status'] = 'degraded';
                $status['details'] = [
                    'models_available' => $modelsCount,
                    'models_response_time_ms' => $modelsResponseTime,
                    'api_accessible' => true,
                    'generation_working' => false,
                    'generation_error' => $e->getMessage(),
                ];
                $status['issues'][] = 'Generation endpoint not accessible: ' . $e->getMessage();
            }
        } catch (\Exception $e) {
            $status['status'] = 'unhealthy';
            $status['details'] = [
                'api_accessible' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ];
            $status['issues'][] = 'API not accessible: ' . $e->getMessage();
        }

        $status['response_time_ms'] = (microtime(true) - $startTime) * 1000;

        // Add performance assessment
        if ($status['response_time_ms'] > 5000) {
            $status['issues'][] = 'High response time (' . round($status['response_time_ms']) . 'ms)';
        }

        return $status;
    }

    /**
     * Test API connectivity with minimal request.
     */
    public function testConnectivity(): array
    {
        $startTime = microtime(true);

        try {
            $response = $this->http
                ->withHeaders($this->getRequestHeaders())
                ->timeout($this->config['timeout'] ?? 30)
                ->get($this->buildModelsEndpoint());

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'connected' => true,
                    'response_time_ms' => (microtime(true) - $startTime) * 1000,
                    'models_count' => count($data['models'] ?? []),
                ];
            } else {
                return [
                    'connected' => false,
                    'response_time_ms' => (microtime(true) - $startTime) * 1000,
                    'error' => 'HTTP ' . $response->status(),
                    'status_code' => $response->status(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'response_time_ms' => (microtime(true) - $startTime) * 1000,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ];
        }
    }

    /**
     * Validate configuration without making API calls.
     */
    protected function validateConfiguration(): void
    {
        if (empty($this->config['api_key'])) {
            throw new \JTD\LaravelAI\Exceptions\InvalidCredentialsException(
                'Gemini API key is required'
            );
        }

        if (! filter_var($this->config['base_url'], FILTER_VALIDATE_URL)) {
            throw new \JTD\LaravelAI\Exceptions\InvalidConfigurationException(
                'Invalid base URL provided for Gemini API'
            );
        }
    }

    /**
     * Check if the API is currently experiencing issues.
     */
    public function checkApiStatus(): array
    {
        return $this->testConnectivity();
    }

    /**
     * Get detailed diagnostic information.
     */
    public function getDiagnostics(): array
    {
        $diagnostics = [
            'provider' => $this->providerName,
            'timestamp' => now()->toISOString(),
            'configuration' => $this->getConfigurationDiagnostics(),
            'connectivity' => $this->testConnectivity(),
            'health' => $this->getHealthStatus(),
            'credentials' => $this->validateCredentials(),
        ];

        return $diagnostics;
    }

    /**
     * Perform a comprehensive health check.
     */
    public function performHealthCheck(): array
    {
        $checks = [
            'configuration' => $this->checkConfiguration(),
            'connectivity' => $this->checkConnectivity(),
            'authentication' => $this->checkAuthentication(),
            'models_access' => $this->checkModelsAccess(),
            'generation_access' => $this->checkGenerationAccess(),
        ];

        $overallHealth = 'healthy';
        $issues = [];

        foreach ($checks as $checkName => $result) {
            if (! $result['passed']) {
                $overallHealth = 'unhealthy';
                $issues[] = "{$checkName}: {$result['message']}";
            }
        }

        return [
            'overall_health' => $overallHealth,
            'checks' => $checks,
            'issues' => $issues,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Check configuration validity.
     */
    protected function checkConfiguration(): array
    {
        try {
            $this->validateConfiguration();

            return ['passed' => true, 'message' => 'Configuration is valid'];
        } catch (\Exception $e) {
            return ['passed' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check basic connectivity.
     */
    protected function checkConnectivity(): array
    {
        $result = $this->testConnectivity();

        return [
            'passed' => $result['connected'],
            'message' => $result['connected'] ? 'API is reachable' : $result['error'],
            'response_time_ms' => $result['response_time_ms'],
        ];
    }

    /**
     * Check authentication.
     */
    protected function checkAuthentication(): array
    {
        $result = $this->validateCredentials();

        return [
            'passed' => $result['valid'],
            'message' => $result['valid'] ? 'Credentials are valid' : implode(', ', $result['errors']),
        ];
    }

    /**
     * Check models access.
     */
    protected function checkModelsAccess(): array
    {
        try {
            $models = $this->getAvailableModels();

            return [
                'passed' => count($models) > 0,
                'message' => count($models) > 0 ? count($models) . ' models available' : 'No models accessible',
                'models_count' => count($models),
            ];
        } catch (\Exception $e) {
            return ['passed' => false, 'message' => 'Models endpoint error: ' . $e->getMessage()];
        }
    }

    /**
     * Check generation access.
     */
    protected function checkGenerationAccess(): array
    {
        try {
            $response = $this->http
                ->withHeaders($this->getRequestHeaders())
                ->timeout($this->config['timeout'] ?? 30)
                ->post($this->buildEndpoint('gemini-pro'), [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => 'test']],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 1,
                    ],
                ]);

            if ($response->successful()) {
                return ['passed' => true, 'message' => 'Generation endpoint accessible'];
            } else {
                $error = $response->json()['error']['message'] ?? 'Unknown error';

                return ['passed' => false, 'message' => 'Generation endpoint error: ' . $error];
            }
        } catch (\Exception $e) {
            return ['passed' => false, 'message' => 'Generation endpoint error: ' . $e->getMessage()];
        }
    }

    /**
     * Get usage statistics (placeholder for future implementation).
     */
    public function getUsageStats(string $period = 'day'): array
    {
        // Implementation will be added in next phase
        throw new \BadMethodCallException('Method not yet implemented');
    }
}
