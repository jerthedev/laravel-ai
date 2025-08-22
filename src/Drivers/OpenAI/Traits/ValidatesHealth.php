<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Exceptions\OpenAI\OpenAIInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIQuotaExceededException;

/**
 * Validates Health and Credentials
 *
 * Handles credential validation, health checks,
 * and API connectivity testing.
 */
trait ValidatesHealth
{
    /**
     * Validate OpenAI credentials.
     */
    public function validateCredentials(): array
    {
        $startTime = microtime(true);
        $result = [
            'valid' => false,
            'provider' => $this->providerName,
            'response_time_ms' => 0,
            'details' => [],
            'errors' => [],
        ];

        try {
            // Try to list models as a lightweight credential validation
            $response = $this->executeWithRetry(function () {
                return $this->client->models()->list();
            }, ['retry_attempts' => 1]); // Single attempt for validation

            $result['valid'] = true;
            $result['response_time_ms'] = (microtime(true) - $startTime) * 1000;
            $result['details'] = [
                'models_available' => count($response->data ?? []),
                'api_accessible' => true,
                'organization' => $this->config['organization'] ?? null,
                'project' => $this->config['project'] ?? null,
            ];

        } catch (OpenAIInvalidCredentialsException $e) {
            $result['errors'][] = 'Invalid credentials: ' . $e->getMessage();
            $result['details'] = [
                'api_accessible' => false,
                'credentials_valid' => false,
            ];

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
            $modelsResponse = $this->executeWithRetry(function () {
                return $this->client->models()->list();
            }, ['retry_attempts' => 1]);

            $modelsCount = count($modelsResponse->data ?? []);
            $responseTime = (microtime(true) - $modelsStartTime) * 1000;

            if ($modelsCount === 0) {
                $status['status'] = 'degraded';
                $status['issues'][] = 'No models available';
                $status['details'] = [
                    'models_available' => 0,
                    'models_response_time_ms' => $responseTime,
                    'api_accessible' => true,
                ];
                return $status;
            }

            // Test a simple completion to verify full functionality
            $testStartTime = microtime(true);
            try {
                $testResponse = $this->executeWithRetry(function () {
                    return $this->client->chat()->create([
                        'model' => 'gpt-3.5-turbo',
                        'messages' => [['role' => 'user', 'content' => 'Hi']],
                        'max_tokens' => 1,
                    ]);
                }, ['retry_attempts' => 1]);

                $completionTime = (microtime(true) - $testStartTime) * 1000;

                $status['status'] = 'healthy';
                $status['details'] = [
                    'models_available' => $modelsCount,
                    'models_response_time_ms' => $responseTime,
                    'completion_response_time_ms' => $completionTime,
                    'api_accessible' => true,
                    'completions_working' => true,
                    'test_model' => 'gpt-3.5-turbo',
                ];

            } catch (OpenAIQuotaExceededException $e) {
                // Quota exceeded means API is healthy but no balance
                $status['status'] = 'degraded';
                $status['details'] = [
                    'models_available' => $modelsCount,
                    'models_response_time_ms' => $responseTime,
                    'api_accessible' => true,
                    'completions_working' => false,
                    'quota_exceeded' => true,
                ];
                $status['issues'][] = 'Insufficient quota for completions';

            } catch (\Exception $e) {
                // Models work but completions don't
                $status['status'] = 'degraded';
                $status['details'] = [
                    'models_available' => $modelsCount,
                    'models_response_time_ms' => $responseTime,
                    'api_accessible' => true,
                    'completions_working' => false,
                    'completion_error' => $e->getMessage(),
                ];
                $status['issues'][] = 'Completions endpoint not accessible: ' . $e->getMessage();
            }

        } catch (OpenAIInvalidCredentialsException $e) {
            $status['status'] = 'unhealthy';
            $status['details'] = [
                'api_accessible' => false,
                'credentials_valid' => false,
                'error' => $e->getMessage(),
            ];
            $status['issues'][] = 'Invalid credentials';

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
     * Get usage statistics (placeholder for future implementation).
     */
    public function getUsageStats(string $period = 'day'): array
    {
        // Implementation will be added in next phase
        throw new \BadMethodCallException('Method not yet implemented');
    }

    /**
     * Test API connectivity with minimal request.
     */
    public function testConnectivity(): array
    {
        $startTime = microtime(true);
        
        try {
            $response = $this->executeWithRetry(function () {
                return $this->client->models()->list();
            }, ['retry_attempts' => 1]);

            return [
                'connected' => true,
                'response_time_ms' => (microtime(true) - $startTime) * 1000,
                'models_count' => count($response->data ?? []),
            ];

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
            throw new OpenAIInvalidCredentialsException(
                'OpenAI API key is required',
                null,
                null,
                'invalid_api_key'
            );
        }

        // Validate API key format
        if (!str_starts_with($this->config['api_key'], 'sk-')) {
            throw new OpenAIInvalidCredentialsException(
                'Invalid OpenAI API key format. API key should start with "sk-"',
                null,
                null,
                'invalid_api_key'
            );
        }
    }

    /**
     * Check if the API is currently experiencing issues.
     */
    public function checkApiStatus(): array
    {
        // This could be extended to check OpenAI's status page
        // For now, we'll do a simple connectivity test
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
     * Get configuration diagnostics (without sensitive data).
     */
    protected function getConfigurationDiagnostics(): array
    {
        return [
            'api_key_configured' => !empty($this->config['api_key']),
            'api_key_format_valid' => !empty($this->config['api_key']) && str_starts_with($this->config['api_key'], 'sk-'),
            'organization_configured' => !empty($this->config['organization']),
            'project_configured' => !empty($this->config['project']),
            'base_url_configured' => !empty($this->config['base_url']),
            'timeout' => $this->config['timeout'] ?? 30,
            'retry_attempts' => $this->config['retry_attempts'] ?? 3,
            'default_model' => $this->config['default_model'] ?? $this->defaultModel,
        ];
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
            'completions_access' => $this->checkCompletionsAccess(),
        ];

        $overallHealth = 'healthy';
        $issues = [];

        foreach ($checks as $checkName => $result) {
            if (!$result['passed']) {
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
     * Check completions access.
     */
    protected function checkCompletionsAccess(): array
    {
        try {
            $response = $this->executeWithRetry(function () {
                return $this->client->chat()->create([
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [['role' => 'user', 'content' => 'test']],
                    'max_tokens' => 1,
                ]);
            }, ['retry_attempts' => 1]);

            return ['passed' => true, 'message' => 'Completions endpoint accessible'];

        } catch (OpenAIQuotaExceededException $e) {
            return ['passed' => false, 'message' => 'Quota exceeded: ' . $e->getMessage()];
        } catch (\Exception $e) {
            return ['passed' => false, 'message' => 'Completions endpoint error: ' . $e->getMessage()];
        }
    }
}
