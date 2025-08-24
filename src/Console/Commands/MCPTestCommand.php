<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\MCPServerValidator;

/**
 * MCP Test Command
 *
 * Tests connectivity and functionality of configured MCP servers.
 */
class MCPTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:mcp:test 
                            {server? : Specific server to test}
                            {--comprehensive : Run comprehensive validation tests}
                            {--json : Output results as JSON}
                            {--quiet : Only show summary}';

    /**
     * The console command description.
     */
    protected $description = 'Test connectivity and functionality of MCP servers';

    /**
     * MCP Manager service.
     */
    protected MCPManager $mcpManager;

    /**
     * MCP Server Validator service.
     */
    protected MCPServerValidator $validator;

    /**
     * Create a new command instance.
     */
    public function __construct(MCPManager $mcpManager, MCPServerValidator $validator)
    {
        parent::__construct();
        
        $this->mcpManager = $mcpManager;
        $this->validator = $validator;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $serverName = $this->argument('server');
        $comprehensive = $this->option('comprehensive');
        $jsonOutput = $this->option('json');
        $quiet = $this->option('quiet');

        try {
            if ($serverName) {
                return $this->testServer($serverName, $comprehensive, $jsonOutput, $quiet);
            } else {
                return $this->testAllServers($comprehensive, $jsonOutput, $quiet);
            }
        } catch (\Exception $e) {
            $this->error("Server testing failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Test all configured servers.
     */
    protected function testAllServers(bool $comprehensive, bool $jsonOutput, bool $quiet): int
    {
        if (!$jsonOutput && !$quiet) {
            $this->info('🧪 Testing all MCP servers...');
        }

        $servers = $this->mcpManager->getEnabledServers();
        
        if (empty($servers)) {
            if (!$jsonOutput) {
                $this->warn('No enabled MCP servers found.');
            }
            return 0;
        }

        $results = [];
        $overallStatus = 'passed';

        foreach ($servers as $name => $server) {
            if ($comprehensive) {
                $result = $this->validator->validateServer($name, $server);
            } else {
                $result = $this->performBasicTest($name, $server);
            }
            
            $results[$name] = $result;
            
            // Update overall status
            if ($result['overall_status'] === 'error' || $result['overall_status'] === 'failed') {
                $overallStatus = 'failed';
            } elseif ($result['overall_status'] === 'warning' && $overallStatus === 'passed') {
                $overallStatus = 'warning';
            }
        }

        if ($jsonOutput) {
            $this->line(json_encode([
                'overall_status' => $overallStatus,
                'servers_tested' => count($results),
                'results' => $results,
            ], JSON_PRETTY_PRINT));
            return 0;
        }

        if (!$quiet) {
            $this->displayAllTestResults($results);
        }

        $this->displaySummary($results, $overallStatus);

        return $overallStatus === 'failed' ? 1 : 0;
    }

    /**
     * Test a specific server.
     */
    protected function testServer(string $serverName, bool $comprehensive, bool $jsonOutput, bool $quiet): int
    {
        $server = $this->mcpManager->getServer($serverName);
        
        if (!$server) {
            if (!$jsonOutput) {
                $this->error("Server '{$serverName}' not found or not enabled.");
            }
            return 1;
        }

        if (!$jsonOutput && !$quiet) {
            $this->info("🧪 Testing server '{$serverName}'...");
        }

        if ($comprehensive) {
            $result = $this->validator->validateServer($serverName, $server);
        } else {
            $result = $this->performBasicTest($serverName, $server);
        }

        if ($jsonOutput) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return 0;
        }

        if (!$quiet) {
            $this->displayServerTestResult($result);
        } else {
            $this->displayServerSummary($result);
        }

        return in_array($result['overall_status'], ['error', 'failed']) ? 1 : 0;
    }

    /**
     * Perform basic connectivity test.
     */
    protected function performBasicTest(string $serverName, $server): array
    {
        $result = [
            'server_name' => $serverName,
            'overall_status' => 'unknown',
            'tests' => [],
            'errors' => [],
            'warnings' => [],
            'tested_at' => now()->toISOString(),
        ];

        try {
            $startTime = microtime(true);
            $testResult = $server->testConnection();
            $responseTime = (microtime(true) - $startTime) * 1000;

            $result['tests']['connectivity'] = [
                'test_name' => 'Basic Connectivity',
                'status' => $testResult['status'] === 'healthy' ? 'passed' : 'failed',
                'response_time_ms' => round($responseTime, 2),
                'details' => $testResult,
            ];

            $result['overall_status'] = $result['tests']['connectivity']['status'];

            if ($testResult['status'] !== 'healthy') {
                $result['errors'][] = $testResult['message'] ?? 'Connection test failed';
            }

        } catch (\Exception $e) {
            $result['overall_status'] = 'error';
            $result['errors'][] = "Test failed: {$e->getMessage()}";
        }

        return $result;
    }

    /**
     * Display test results for all servers.
     */
    protected function displayAllTestResults(array $results): void
    {
        $this->line('');
        $this->info('📊 Test Results:');

        foreach ($results as $serverName => $result) {
            $this->line('');
            $this->displayServerTestResult($result, false);
        }
    }

    /**
     * Display test result for a single server.
     */
    protected function displayServerTestResult(array $result, bool $showHeader = true): void
    {
        $serverName = $result['server_name'];
        $status = $result['overall_status'];

        if ($showHeader) {
            $this->line('');
        }

        // Server header with status
        $statusIcon = $this->getStatusIcon($status);
        $statusColor = $this->getStatusColor($status);
        
        $this->line("  {$statusIcon} <fg={$statusColor}>{$serverName}</> - " . ucfirst($status));

        // Show test details if available
        if (!empty($result['tests'])) {
            foreach ($result['tests'] as $test) {
                $testIcon = $this->getStatusIcon($test['status']);
                $testColor = $this->getStatusColor($test['status']);
                
                $this->line("    {$testIcon} <fg={$testColor}>{$test['test_name']}</>");
                
                if (isset($test['response_time_ms'])) {
                    $this->line("      Response time: {$test['response_time_ms']}ms");
                }
            }
        }

        // Show errors
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $this->line("    <fg=red>✗</> {$error}");
            }
        }

        // Show warnings
        if (!empty($result['warnings'])) {
            foreach ($result['warnings'] as $warning) {
                $this->line("    <fg=yellow>⚠</> {$warning}");
            }
        }
    }

    /**
     * Display server summary.
     */
    protected function displayServerSummary(array $result): void
    {
        $serverName = $result['server_name'];
        $status = $result['overall_status'];
        $statusIcon = $this->getStatusIcon($status);
        $statusColor = $this->getStatusColor($status);
        
        $this->line("{$statusIcon} <fg={$statusColor}>{$serverName}</> - " . ucfirst($status));
    }

    /**
     * Display overall summary.
     */
    protected function displaySummary(array $results, string $overallStatus): void
    {
        $this->line('');
        $this->info('📋 Summary:');
        
        $statusCounts = [
            'passed' => 0,
            'warning' => 0,
            'failed' => 0,
            'error' => 0,
        ];

        foreach ($results as $result) {
            $status = $result['overall_status'];
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        $total = count($results);
        $this->line("   Total servers tested: {$total}");
        $this->line("   Passed: {$statusCounts['passed']}");
        
        if ($statusCounts['warning'] > 0) {
            $this->line("   <fg=yellow>Warnings: {$statusCounts['warning']}</>");
        }
        
        if ($statusCounts['failed'] > 0) {
            $this->line("   <fg=red>Failed: {$statusCounts['failed']}</>");
        }
        
        if ($statusCounts['error'] > 0) {
            $this->line("   <fg=red>Errors: {$statusCounts['error']}</>");
        }

        $this->line('');
        $overallIcon = $this->getStatusIcon($overallStatus);
        $overallColor = $this->getStatusColor($overallStatus);
        $this->line("   {$overallIcon} <fg={$overallColor}>Overall Status: " . ucfirst($overallStatus) . "</>");
    }

    /**
     * Get status icon.
     */
    protected function getStatusIcon(string $status): string
    {
        return match ($status) {
            'passed' => '✅',
            'warning' => '⚠️',
            'failed' => '❌',
            'error' => '💥',
            default => '❓',
        };
    }

    /**
     * Get status color.
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'passed' => 'green',
            'warning' => 'yellow',
            'failed', 'error' => 'red',
            default => 'gray',
        };
    }
}
