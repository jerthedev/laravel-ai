<?php

namespace JTD\LaravelAI\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Exceptions\MCPServerException;
use JTD\LaravelAI\Exceptions\MCPTimeoutException;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * MCP Error Handling and Fallback Tests
 *
 * Comprehensive tests for MCP server failures, timeouts, and graceful
 * degradation with proper fallback mechanisms and error recovery.
 */
#[Group('mcp-error-handling')]
class MCPErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected MCPManager $mcpManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mcpManager = app(MCPManager::class);
        $this->setupErrorTestConfiguration();
    }

    #[Test]
    public function it_handles_server_connection_failures_gracefully(): void
    {
        // Configure non-existent server
        config([
            'ai.mcp.servers.failing_server' => [
                'type' => 'external',
                'command' => 'non-existent-command',
                'enabled' => true,
                'timeout' => 5,
            ],
        ]);

        $startTime = microtime(true);

        $result = $this->mcpManager->executeTool('failing_server', 'test_tool', ['test' => 'parameter']);

        $executionTime = (microtime(true) - $startTime) * 1000;

        // Verify graceful failure
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('error_type', $result);
        $this->assertEquals('connection_failed', $result['error_type']);

        // Verify fast failure (should not wait for full timeout)
        $this->assertLessThan(2000, $executionTime,
            "Connection failure should fail fast, took {$executionTime}ms");

        // Verify error is logged
        Log::shouldReceive('error')
            ->once()
            ->with('MCP server connection failed', \Mockery::type('array'));
    }

    #[Test]
    public function it_handles_server_timeout_scenarios(): void
    {
        // Mock a slow server response
        Process::fake([
            'slow-mcp-server' => Process::result('', '', 1, 'timeout'),
        ]);

        config([
            'ai.mcp.servers.slow_server' => [
                'type' => 'external',
                'command' => 'slow-mcp-server',
                'enabled' => true,
                'timeout' => 1, // 1 second timeout
            ],
        ]);

        $startTime = microtime(true);

        $result = $this->mcpManager->executeTool('slow_server', 'test_tool', ['test' => 'timeout']);

        $executionTime = (microtime(true) - $startTime) * 1000;

        // Verify timeout handling
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('timeout', $result['error_type']);
        $this->assertStringContains('timeout', strtolower($result['error']));

        // Verify timeout was respected (should be close to 1 second)
        $this->assertGreaterThan(900, $executionTime); // At least 900ms
        $this->assertLessThan(1500, $executionTime); // But not much more than timeout
    }

    #[Test]
    public function it_implements_retry_mechanism_for_transient_failures(): void
    {
        $attemptCount = 0;

        // Mock server that fails twice then succeeds
        Process::fake([
            'flaky-server' => function () use (&$attemptCount) {
                $attemptCount++;
                if ($attemptCount <= 2) {
                    return Process::result('', 'Connection refused', 1);
                }
                return Process::result('{"success": true, "result": "retry_success"}', '', 0);
            },
        ]);

        config([
            'ai.mcp.servers.flaky_server' => [
                'type' => 'external',
                'command' => 'flaky-server',
                'enabled' => true,
                'retry_attempts' => 3,
                'retry_delay' => 100, // 100ms delay
            ],
        ]);

        $startTime = microtime(true);

        $result = $this->mcpManager->executeTool('flaky_server', 'test_tool', ['test' => 'retry']);

        $executionTime = (microtime(true) - $startTime) * 1000;

        // Verify successful retry
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('retry_success', $result['result']);
        $this->assertEquals(3, $attemptCount); // Should have tried 3 times

        // Verify retry delays were applied
        $this->assertGreaterThan(200, $executionTime); // At least 2 * 100ms delay
    }

    #[Test]
    public function it_falls_back_to_alternative_servers(): void
    {
        // Configure primary and fallback servers
        config([
            'ai.mcp.servers.primary_search' => [
                'type' => 'external',
                'command' => 'failing-search-server',
                'enabled' => true,
                'fallback' => 'backup_search',
            ],
            'ai.mcp.servers.backup_search' => [
                'type' => 'external',
                'command' => 'working-search-server',
                'enabled' => true,
            ],
        ]);

        Process::fake([
            'failing-search-server' => Process::result('', 'Server error', 1),
            'working-search-server' => Process::result('{"success": true, "result": "fallback_success"}', '', 0),
        ]);

        $result = $this->mcpManager->executeTool('primary_search', 'search_tool', ['query' => 'test']);

        // Verify fallback was used
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('fallback_success', $result['result']);
        $this->assertArrayHasKey('fallback_used', $result);
        $this->assertTrue($result['fallback_used']);
        $this->assertEquals('backup_search', $result['fallback_server']);
    }

    #[Test]
    #[DataProvider('errorScenarioProvider')]
    public function it_handles_various_error_scenarios(string $scenario, array $serverConfig, array $processResponse, array $expectedResult): void
    {
        config(['ai.mcp.servers.test_server' => $serverConfig]);

        if (!empty($processResponse)) {
            Process::fake(['test-command' => Process::result(
                $processResponse['stdout'] ?? '',
                $processResponse['stderr'] ?? '',
                $processResponse['exitCode'] ?? 0
            )]);
        }

        $result = $this->mcpManager->executeTool('test_server', 'test_tool', ['test' => $scenario]);

        $this->assertIsArray($result);
        $this->assertEquals($expectedResult['success'], $result['success']);

        if (!$expectedResult['success']) {
            $this->assertArrayHasKey('error', $result);
            $this->assertArrayHasKey('error_type', $result);
            $this->assertEquals($expectedResult['error_type'], $result['error_type']);
        }
    }

    #[Test]
    public function it_implements_circuit_breaker_pattern(): void
    {
        config([
            'ai.mcp.servers.circuit_test' => [
                'type' => 'external',
                'command' => 'failing-circuit-server',
                'enabled' => true,
                'circuit_breaker' => [
                    'failure_threshold' => 3,
                    'timeout' => 60, // 60 seconds
                    'recovery_timeout' => 30, // 30 seconds
                ],
            ],
        ]);

        Process::fake([
            'failing-circuit-server' => Process::result('', 'Server error', 1),
        ]);

        // Trigger circuit breaker with multiple failures
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            $result = $this->mcpManager->executeTool('circuit_test', 'test_tool', ['attempt' => $i]);
            $executionTime = (microtime(true) - $startTime) * 1000;

            $results[] = [
                'result' => $result,
                'execution_time' => $executionTime,
                'attempt' => $i,
            ];
        }

        // First 3 attempts should fail normally
        for ($i = 0; $i < 3; $i++) {
            $this->assertFalse($results[$i]['result']['success']);
            $this->assertEquals('server_error', $results[$i]['result']['error_type']);
        }

        // Subsequent attempts should be circuit breaker failures (fast)
        for ($i = 3; $i < 5; $i++) {
            $this->assertFalse($results[$i]['result']['success']);
            $this->assertEquals('circuit_breaker_open', $results[$i]['result']['error_type']);
            $this->assertLessThan(50, $results[$i]['execution_time'],
                "Circuit breaker should fail fast, attempt {$i} took {$results[$i]['execution_time']}ms");
        }
    }

    #[Test]
    public function it_handles_malformed_server_responses(): void
    {
        config([
            'ai.mcp.servers.malformed_server' => [
                'type' => 'external',
                'command' => 'malformed-response-server',
                'enabled' => true,
            ],
        ]);

        $malformedResponses = [
            'invalid_json' => 'This is not JSON',
            'missing_fields' => '{"incomplete": true}',
            'wrong_structure' => '{"success": "not_boolean", "result": 123}',
            'empty_response' => '',
            'null_response' => 'null',
        ];

        foreach ($malformedResponses as $type => $response) {
            Process::fake([
                'malformed-response-server' => Process::result($response, '', 0),
            ]);

            $result = $this->mcpManager->executeTool('malformed_server', 'test_tool', ['test' => $type]);

            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertEquals('invalid_response', $result['error_type']);
            $this->assertStringContains('malformed', strtolower($result['error']));
        }
    }

    #[Test]
    public function it_provides_detailed_error_context(): void
    {
        config([
            'ai.mcp.servers.context_server' => [
                'type' => 'external',
                'command' => 'context-error-server',
                'enabled' => true,
            ],
        ]);

        Process::fake([
            'context-error-server' => Process::result('', 'Detailed error message', 1),
        ]);

        $result = $this->mcpManager->executeTool('context_server', 'test_tool', [
            'complex_param' => 'test_value',
            'nested' => ['data' => 'structure'],
        ]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);

        // Verify detailed error context
        $this->assertArrayHasKey('error_context', $result);
        $this->assertArrayHasKey('server_name', $result['error_context']);
        $this->assertArrayHasKey('command', $result['error_context']);
        $this->assertArrayHasKey('parameters', $result['error_context']);
        $this->assertArrayHasKey('timestamp', $result['error_context']);

        $this->assertEquals('context_server', $result['error_context']['server_name']);
        $this->assertEquals('context-error-server', $result['error_context']['command']);
    }

    #[Test]
    public function it_handles_resource_exhaustion_gracefully(): void
    {
        // Simulate resource exhaustion
        config([
            'ai.mcp.max_concurrent' => 2,
            'ai.mcp.servers.resource_server' => [
                'type' => 'external',
                'command' => 'resource-heavy-server',
                'enabled' => true,
            ],
        ]);

        Process::fake([
            'resource-heavy-server' => function () {
                // Simulate slow response to test concurrency limits
                usleep(100000); // 100ms
                return Process::result('{"success": true, "result": "resource_success"}', '', 0);
            },
        ]);

        // Start multiple concurrent requests
        $promises = [];
        for ($i = 0; $i < 5; $i++) {
            $promises[] = function () use ($i) {
                return $this->mcpManager->executeTool('resource_server', 'test_tool', ['request' => $i]);
            };
        }

        $startTime = microtime(true);
        $results = array_map(fn($promise) => $promise(), $promises);
        $totalTime = (microtime(true) - $startTime) * 1000;

        // Some requests should succeed, others should be rate limited
        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $rateLimitedCount = count(array_filter($results, fn($r) =>
            !$r['success'] && $r['error_type'] === 'rate_limited'
        ));

        $this->assertGreaterThan(0, $successCount, 'Some requests should succeed');
        $this->assertGreaterThan(0, $rateLimitedCount, 'Some requests should be rate limited');
        $this->assertEquals(5, $successCount + $rateLimitedCount, 'All requests should be handled');
    }

    /**
     * Data provider for error scenarios.
     */
    public static function errorScenarioProvider(): array
    {
        return [
            'server_crash' => [
                'server_crash',
                [
                    'type' => 'external',
                    'command' => 'test-command',
                    'enabled' => true,
                ],
                [
                    'stdout' => '',
                    'stderr' => 'Segmentation fault',
                    'exitCode' => 139,
                ],
                [
                    'success' => false,
                    'error_type' => 'server_crash',
                ],
            ],
            'permission_denied' => [
                'permission_denied',
                [
                    'type' => 'external',
                    'command' => 'test-command',
                    'enabled' => true,
                ],
                [
                    'stdout' => '',
                    'stderr' => 'Permission denied',
                    'exitCode' => 126,
                ],
                [
                    'success' => false,
                    'error_type' => 'permission_denied',
                ],
            ],
            'command_not_found' => [
                'command_not_found',
                [
                    'type' => 'external',
                    'command' => 'test-command',
                    'enabled' => true,
                ],
                [
                    'stdout' => '',
                    'stderr' => 'command not found',
                    'exitCode' => 127,
                ],
                [
                    'success' => false,
                    'error_type' => 'command_not_found',
                ],
            ],
        ];
    }

    /**
     * Setup error testing configuration.
     */
    protected function setupErrorTestConfiguration(): void
    {
        config([
            'ai.mcp.enabled' => true,
            'ai.mcp.timeout' => 30,
            'ai.mcp.max_concurrent' => 10,
            'ai.mcp.retry_attempts' => 3,
            'ai.mcp.retry_delay' => 100,
            'ai.mcp.error_handling' => [
                'log_errors' => true,
                'include_context' => true,
                'circuit_breaker_enabled' => true,
                'fallback_enabled' => true,
            ],
        ]);
    }
}
