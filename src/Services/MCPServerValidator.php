<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Contracts\MCPServerInterface;
use JTD\LaravelAI\Exceptions\MCPException;

/**
 * MCP Server Validator Service
 *
 * Provides comprehensive validation and testing capabilities for MCP servers
 * including connectivity tests, configuration validation, and troubleshooting guidance.
 */
class MCPServerValidator
{
    /**
     * MCP Configuration service.
     */
    protected MCPConfigurationService $configService;

    /**
     * MCP Server Installer service.
     */
    protected MCPServerInstaller $installer;

    /**
     * Validation test timeout in seconds.
     */
    protected int $testTimeout = 30;

    /**
     * Create a new validator instance.
     */
    public function __construct(
        MCPConfigurationService $configService,
        MCPServerInstaller $installer
    ) {
        $this->configService = $configService;
        $this->installer = $installer;
    }

    /**
     * Perform comprehensive validation of an MCP server.
     */
    public function validateServer(string $serverName, MCPServerInterface $server = null): array
    {
        $results = [
            'server_name' => $serverName,
            'overall_status' => 'unknown',
            'tests' => [],
            'errors' => [],
            'warnings' => [],
            'recommendations' => [],
            'validated_at' => now()->toISOString(),
        ];

        try {
            // Test 1: Configuration validation
            $results['tests']['configuration'] = $this->validateConfiguration($serverName);

            // Test 2: Installation validation
            $results['tests']['installation'] = $this->validateInstallation($serverName);

            // Test 3: Connectivity test
            if ($server) {
                $results['tests']['connectivity'] = $this->validateConnectivity($server);
            }

            // Test 4: Environment validation
            $results['tests']['environment'] = $this->validateEnvironment($serverName);

            // Test 5: Performance test
            if ($server) {
                $results['tests']['performance'] = $this->validatePerformance($server);
            }

            // Determine overall status
            $results['overall_status'] = $this->determineOverallStatus($results['tests']);

            // Generate recommendations
            $results['recommendations'] = $this->generateRecommendations($results['tests']);

            // Collect errors and warnings
            foreach ($results['tests'] as $test) {
                if (isset($test['errors'])) {
                    $results['errors'] = array_merge($results['errors'], $test['errors']);
                }
                if (isset($test['warnings'])) {
                    $results['warnings'] = array_merge($results['warnings'], $test['warnings']);
                }
            }

        } catch (\Exception $e) {
            $results['overall_status'] = 'error';
            $results['errors'][] = "Validation failed: {$e->getMessage()}";
            
            Log::error("MCP server validation failed", [
                'server' => $serverName,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Validate server configuration.
     */
    protected function validateConfiguration(string $serverName): array
    {
        $result = [
            'test_name' => 'Configuration Validation',
            'status' => 'unknown',
            'errors' => [],
            'warnings' => [],
            'details' => [],
        ];

        try {
            $config = $this->configService->loadConfiguration();
            $serverConfig = $config['servers'][$serverName] ?? null;

            if (!$serverConfig) {
                $result['status'] = 'failed';
                $result['errors'][] = "Server '{$serverName}' not found in configuration";
                return $result;
            }

            // Validate configuration structure
            $validation = $this->configService->validateConfiguration($config);
            
            if (!empty($validation['errors'])) {
                $result['status'] = 'failed';
                $result['errors'] = $validation['errors'];
            } else {
                $result['status'] = 'passed';
            }

            if (!empty($validation['warnings'])) {
                $result['warnings'] = $validation['warnings'];
            }

            $result['details'] = [
                'server_type' => $serverConfig['type'] ?? 'unknown',
                'enabled' => $serverConfig['enabled'] ?? false,
                'has_command' => !empty($serverConfig['command']),
                'has_env_vars' => !empty($serverConfig['env']),
                'timeout' => $serverConfig['timeout'] ?? 'default',
            ];

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = "Configuration validation error: {$e->getMessage()}";
        }

        return $result;
    }

    /**
     * Validate server installation.
     */
    protected function validateInstallation(string $serverName): array
    {
        $result = [
            'test_name' => 'Installation Validation',
            'status' => 'unknown',
            'errors' => [],
            'warnings' => [],
            'details' => [],
        ];

        try {
            // Check if server template exists
            $template = $this->installer->getServerTemplate($serverName);
            
            if (!$template) {
                $result['status'] = 'skipped';
                $result['warnings'][] = "No installation template found for server '{$serverName}'";
                return $result;
            }

            // Check if server is installed
            $installStatus = $this->installer->isServerInstalled($serverName);
            
            if (!$installStatus['installed']) {
                $result['status'] = 'failed';
                $result['errors'][] = "Server package '{$template['package']}' is not installed";
                $result['details']['package'] = $template['package'];
                $result['details']['install_command'] = $template['install_command'] ?? 'unknown';
            } else {
                $result['status'] = 'passed';
                $result['details']['package'] = $template['package'];
                $result['details']['version'] = $installStatus['version'] ?? 'unknown';
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = "Installation validation error: {$e->getMessage()}";
        }

        return $result;
    }

    /**
     * Validate server connectivity.
     */
    protected function validateConnectivity(MCPServerInterface $server): array
    {
        $result = [
            'test_name' => 'Connectivity Test',
            'status' => 'unknown',
            'errors' => [],
            'warnings' => [],
            'details' => [],
        ];

        try {
            $startTime = microtime(true);
            $testResult = $server->testConnection();
            $responseTime = (microtime(true) - $startTime) * 1000;

            $result['details']['response_time_ms'] = round($responseTime, 2);
            $result['details']['server_type'] = $server->getType();
            $result['details']['server_version'] = $server->getVersion();

            switch ($testResult['status']) {
                case 'healthy':
                    $result['status'] = 'passed';
                    break;

                case 'error':
                    $result['status'] = 'failed';
                    $result['errors'][] = $testResult['message'] ?? 'Connection test failed';
                    break;

                case 'disabled':
                    $result['status'] = 'skipped';
                    $result['warnings'][] = 'Server is disabled';
                    break;

                default:
                    $result['status'] = 'warning';
                    $result['warnings'][] = "Unknown status: {$testResult['status']}";
                    break;
            }

            // Check response time
            if ($responseTime > 5000) { // 5 seconds
                $result['warnings'][] = "Slow response time: {$result['details']['response_time_ms']}ms";
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = "Connectivity test error: {$e->getMessage()}";
        }

        return $result;
    }

    /**
     * Validate server environment.
     */
    protected function validateEnvironment(string $serverName): array
    {
        $result = [
            'test_name' => 'Environment Validation',
            'status' => 'unknown',
            'errors' => [],
            'warnings' => [],
            'details' => [],
        ];

        try {
            $config = $this->configService->loadConfiguration();
            $serverConfig = $config['servers'][$serverName] ?? null;

            if (!$serverConfig) {
                $result['status'] = 'skipped';
                return $result;
            }

            $envVars = $serverConfig['env'] ?? [];
            $missingVars = [];
            $presentVars = [];

            foreach ($envVars as $key => $value) {
                // Check if it's a placeholder
                if (str_starts_with($value, '${') && str_ends_with($value, '}')) {
                    $envVar = substr($value, 2, -1);
                    $envValue = env($envVar);
                    
                    if (empty($envValue)) {
                        $missingVars[] = $envVar;
                    } else {
                        $presentVars[] = $envVar;
                    }
                }
            }

            $result['details']['required_env_vars'] = array_keys($envVars);
            $result['details']['present_vars'] = $presentVars;
            $result['details']['missing_vars'] = $missingVars;

            if (!empty($missingVars)) {
                $result['status'] = 'failed';
                $result['errors'][] = "Missing environment variables: " . implode(', ', $missingVars);
            } else {
                $result['status'] = 'passed';
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = "Environment validation error: {$e->getMessage()}";
        }

        return $result;
    }

    /**
     * Validate server performance.
     */
    protected function validatePerformance(MCPServerInterface $server): array
    {
        $result = [
            'test_name' => 'Performance Test',
            'status' => 'unknown',
            'errors' => [],
            'warnings' => [],
            'details' => [],
        ];

        try {
            // Get server metrics
            $metrics = $server->getMetrics();
            $result['details']['metrics'] = $metrics;

            // Test tool discovery performance
            $startTime = microtime(true);
            $tools = $server->getAvailableTools();
            $discoveryTime = (microtime(true) - $startTime) * 1000;

            $result['details']['tool_discovery_time_ms'] = round($discoveryTime, 2);
            $result['details']['tool_count'] = count($tools);

            // Performance thresholds
            $slowThreshold = 2000; // 2 seconds
            $verySlowThreshold = 5000; // 5 seconds

            if ($discoveryTime > $verySlowThreshold) {
                $result['status'] = 'failed';
                $result['errors'][] = "Very slow tool discovery: {$result['details']['tool_discovery_time_ms']}ms";
            } elseif ($discoveryTime > $slowThreshold) {
                $result['status'] = 'warning';
                $result['warnings'][] = "Slow tool discovery: {$result['details']['tool_discovery_time_ms']}ms";
            } else {
                $result['status'] = 'passed';
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = "Performance test error: {$e->getMessage()}";
        }

        return $result;
    }

    /**
     * Determine overall validation status.
     */
    protected function determineOverallStatus(array $tests): string
    {
        $statuses = array_column($tests, 'status');

        if (in_array('error', $statuses)) {
            return 'error';
        }

        if (in_array('failed', $statuses)) {
            return 'failed';
        }

        if (in_array('warning', $statuses)) {
            return 'warning';
        }

        if (in_array('passed', $statuses)) {
            return 'passed';
        }

        return 'unknown';
    }

    /**
     * Generate recommendations based on test results.
     */
    protected function generateRecommendations(array $tests): array
    {
        $recommendations = [];

        foreach ($tests as $test) {
            if ($test['status'] === 'failed') {
                $recommendations[] = $this->getFailureRecommendation($test);
            } elseif ($test['status'] === 'warning') {
                $recommendations[] = $this->getWarningRecommendation($test);
            }
        }

        return array_filter($recommendations);
    }

    /**
     * Get recommendation for failed test.
     */
    protected function getFailureRecommendation(array $test): ?string
    {
        switch ($test['test_name']) {
            case 'Configuration Validation':
                return 'Check your .mcp.json file and ensure all required fields are present and valid.';

            case 'Installation Validation':
                return 'Install the required npm package using: php artisan ai:mcp:setup';

            case 'Connectivity Test':
                return 'Check if the server process is running and accessible. Verify network connectivity.';

            case 'Environment Validation':
                return 'Set the required environment variables in your .env file.';

            case 'Performance Test':
                return 'Consider optimizing server configuration or checking system resources.';

            default:
                return 'Review the test errors and consult the documentation.';
        }
    }

    /**
     * Get recommendation for warning test.
     */
    protected function getWarningRecommendation(array $test): ?string
    {
        switch ($test['test_name']) {
            case 'Connectivity Test':
                return 'Monitor server response times and consider optimization if consistently slow.';

            case 'Performance Test':
                return 'Consider caching or optimizing server configuration for better performance.';

            default:
                return null;
        }
    }
}
