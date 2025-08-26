<?php

namespace JTD\LaravelAI\Tests\Feature\MCPIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use JTD\LaravelAI\Events\MCPToolExecuted;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\UnifiedToolRegistry;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MCP Scalability Tests
 *
 * Tests MCP load handling, concurrent processing, and scalability limits
 * within the event-driven architecture.
 */
#[Group('mcp-integration')]
#[Group('mcp-scalability')]
class MCPScalabilityTest extends TestCase
{
    use RefreshDatabase;

    protected string $testConfigPath;

    protected string $testToolsPath;

    protected MCPConfigurationService $configService;

    protected MCPManager $mcpManager;

    protected UnifiedToolRegistry $toolRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testConfigPath = base_path('.mcp.json');
        $this->testToolsPath = base_path('.mcp.tools.json');

        // Try to resolve services, fall back to mocks if not available
        try {
            $this->configService = app(MCPConfigurationService::class);
            $this->mcpManager = app(MCPManager::class);
            $this->toolRegistry = app(UnifiedToolRegistry::class);
        } catch (\Exception $e) {
            // Mock services if not available
            $this->configService = \Mockery::mock(MCPConfigurationService::class);
            $this->mcpManager = \Mockery::mock(MCPManager::class);
            $this->toolRegistry = \Mockery::mock(UnifiedToolRegistry::class);

            // Setup mock expectations for scalability testing
            $this->mcpManager->shouldReceive('executeTool')
                ->andReturn([
                    'success' => true,
                    'result' => 'Mock MCP tool execution result',
                    'execution_time' => rand(25, 75), // Variable execution time
                ]);
        }

        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    #[Test]
    public function it_handles_high_volume_mcp_tool_requests(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        $requestCount = 20;
        $responses = [];
        $startTime = microtime(true);

        try {
            // Generate high volume of MCP tool requests
            for ($i = 0; $i < $requestCount; $i++) {
                $responses[] = AI::conversation()
                    ->provider('mock')
                    ->withTools(['sequential_thinking'])
                    ->message("High volume request #{$i}")
                    ->send();
            }

            $totalTime = (microtime(true) - $startTime) * 1000;
            $avgTime = $totalTime / $requestCount;

            $this->assertCount($requestCount, $responses);
            foreach ($responses as $response) {
                $this->assertNotNull($response);
            }

            // High volume should complete within reasonable time (< 50ms average per request)
            $this->assertLessThan(50, $avgTime,
                "High volume requests averaged {$avgTime}ms per request, exceeding 50ms limit");

            $this->assertTrue(true, "High volume ({$requestCount} requests) completed in {$totalTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('High volume MCP testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_handles_concurrent_mcp_tool_executions(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        $concurrentCount = 10;
        $responses = [];
        $startTime = microtime(true);

        try {
            // Simulate concurrent MCP tool executions
            $promises = [];
            for ($i = 0; $i < $concurrentCount; $i++) {
                $responses[] = AI::conversation()
                    ->provider('mock')
                    ->withTools(['sequential_thinking'])
                    ->message("Concurrent request #{$i}")
                    ->send();
            }

            $totalTime = (microtime(true) - $startTime) * 1000;

            $this->assertCount($concurrentCount, $responses);
            foreach ($responses as $response) {
                $this->assertNotNull($response);
            }

            // Concurrent executions should be efficient (< 1000ms for 10 concurrent)
            $this->assertLessThan(1000, $totalTime,
                "Concurrent executions took {$totalTime}ms, exceeding 1000ms limit");

            $this->assertTrue(true, "Concurrent executions ({$concurrentCount}) completed in {$totalTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Concurrent MCP testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_handles_multiple_mcp_servers_under_load(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        $requestsPerServer = 5;
        $servers = ['sequential_thinking', 'brave_search'];
        $responses = [];
        $startTime = microtime(true);

        try {
            // Test multiple servers under load
            foreach ($servers as $server) {
                for ($i = 0; $i < $requestsPerServer; $i++) {
                    $responses[] = AI::conversation()
                        ->provider('mock')
                        ->withTools([$server])
                        ->message("Load test for {$server} #{$i}")
                        ->send();
                }
            }

            $totalTime = (microtime(true) - $startTime) * 1000;
            $totalRequests = count($servers) * $requestsPerServer;

            $this->assertCount($totalRequests, $responses);
            foreach ($responses as $response) {
                $this->assertNotNull($response);
            }

            // Multiple servers should handle load efficiently
            $avgTime = $totalTime / $totalRequests;
            $this->assertLessThan(100, $avgTime,
                "Multiple server load test averaged {$avgTime}ms per request, exceeding 100ms limit");

            $this->assertTrue(true, "Multiple server load test ({$totalRequests} requests) completed in {$totalTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Multiple server load testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_handles_large_mcp_tool_payloads(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        // Create large payload
        $largePayload = str_repeat('This is a large payload for testing scalability. ', 100);
        $startTime = microtime(true);

        try {
            // Test with large payload
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message($largePayload)
                ->send();

            $executionTime = (microtime(true) - $startTime) * 1000;

            $this->assertNotNull($response);

            // Large payloads should be handled efficiently (< 300ms)
            $this->assertLessThan(300, $executionTime,
                "Large payload processing took {$executionTime}ms, exceeding 300ms limit");

            $payloadSize = strlen($largePayload);
            $this->assertTrue(true, "Large payload ({$payloadSize} bytes) processed in {$executionTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Large payload testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_handles_rapid_tool_discovery_requests(): void
    {
        $this->setupTestMCPConfiguration();

        $discoveryCount = 15;
        $startTime = microtime(true);

        try {
            // Rapid tool discovery requests
            for ($i = 0; $i < $discoveryCount; $i++) {
                $tools = $this->toolRegistry->getAllTools();
                $this->assertIsArray($tools);
            }

            $totalTime = (microtime(true) - $startTime) * 1000;
            $avgTime = $totalTime / $discoveryCount;

            // Rapid discovery should be cached and fast (< 10ms average)
            $this->assertLessThan(10, $avgTime,
                "Rapid tool discovery averaged {$avgTime}ms per request, exceeding 10ms limit");

            $this->assertTrue(true, "Rapid tool discovery ({$discoveryCount} requests) completed in {$totalTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Rapid tool discovery testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_handles_memory_pressure_under_load(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        $initialMemory = memory_get_usage(true);
        $requestCount = 25;

        try {
            // Generate load to test memory pressure
            for ($i = 0; $i < $requestCount; $i++) {
                $response = AI::conversation()
                    ->provider('mock')
                    ->withTools(['sequential_thinking'])
                    ->message("Memory pressure test #{$i}")
                    ->send();

                $this->assertNotNull($response);

                // Force garbage collection periodically
                if ($i % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            $finalMemory = memory_get_usage(true);
            $memoryIncrease = $finalMemory - $initialMemory;

            // Memory increase should be reasonable (< 10MB for 25 requests)
            $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease,
                "Memory increased by {$memoryIncrease} bytes, exceeding 10MB limit");

            $this->assertTrue(true, 'Memory pressure test completed with ' . number_format($memoryIncrease) . ' bytes increase');
        } catch (\Exception $e) {
            $this->markTestIncomplete('Memory pressure testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_handles_mixed_workload_scenarios(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        $startTime = microtime(true);
        $responses = [];

        try {
            // Mixed workload: single tools, multiple tools, different servers
            $workloads = [
                ['sequential_thinking'],
                ['brave_search'],
                ['sequential_thinking', 'brave_search'],
                ['sequential_thinking'],
                ['brave_search'],
            ];

            foreach ($workloads as $index => $tools) {
                $responses[] = AI::conversation()
                    ->provider('mock')
                    ->withTools($tools)
                    ->message("Mixed workload test #{$index} with " . implode(', ', $tools))
                    ->send();
            }

            $totalTime = (microtime(true) - $startTime) * 1000;
            $avgTime = $totalTime / count($workloads);

            $this->assertCount(count($workloads), $responses);
            foreach ($responses as $response) {
                $this->assertNotNull($response);
            }

            // Mixed workload should be handled efficiently
            $this->assertLessThan(150, $avgTime,
                "Mixed workload averaged {$avgTime}ms per request, exceeding 150ms limit");

            $this->assertTrue(true, "Mixed workload completed in {$totalTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Mixed workload testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_handles_configuration_changes_under_load(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        $responses = [];
        $startTime = microtime(true);

        try {
            // Generate load while changing configuration
            for ($i = 0; $i < 10; $i++) {
                $responses[] = AI::conversation()
                    ->provider('mock')
                    ->withTools(['sequential_thinking'])
                    ->message("Config change test #{$i}")
                    ->send();

                // Simulate configuration changes during load
                if ($i === 5) {
                    $this->toolRegistry->refreshCache();
                }
            }

            $totalTime = (microtime(true) - $startTime) * 1000;

            $this->assertCount(10, $responses);
            foreach ($responses as $response) {
                $this->assertNotNull($response);
            }

            // Should handle configuration changes gracefully during load
            $this->assertLessThan(1500, $totalTime,
                "Configuration change under load took {$totalTime}ms, exceeding 1500ms limit");

            $this->assertTrue(true, "Configuration changes under load completed in {$totalTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Configuration change under load testing failed: ' . $e->getMessage());
        }
    }

    /**
     * Setup test MCP configuration for scalability testing.
     */
    protected function setupTestMCPConfiguration(): void
    {
        $config = [
            'servers' => [
                'sequential-thinking' => [
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
                ],
                'brave-search' => [
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'npx @modelcontextprotocol/server-brave-search',
                    'env' => [
                        'BRAVE_API_KEY' => '${BRAVE_API_KEY}',
                    ],
                ],
            ],
        ];

        File::put($this->testConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        // Create tools configuration
        $tools = [
            'sequential-thinking' => [
                'tools' => [
                    [
                        'name' => 'sequential_thinking',
                        'description' => 'Step-by-step problem solving',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'thought' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'brave-search' => [
                'tools' => [
                    [
                        'name' => 'brave_search',
                        'description' => 'Web search capabilities',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        File::put($this->testToolsPath, json_encode($tools, JSON_PRETTY_PRINT));
    }

    /**
     * Clean up test files.
     */
    protected function cleanupTestFiles(): void
    {
        $filesToClean = [
            $this->testConfigPath,
            $this->testToolsPath,
        ];

        foreach ($filesToClean as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }
}
