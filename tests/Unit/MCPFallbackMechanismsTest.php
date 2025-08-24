<?php

namespace JTD\LaravelAI\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Events\MCPServerFailed;
use JTD\LaravelAI\Events\MCPFallbackActivated;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * MCP Fallback Mechanisms Tests
 *
 * Tests for graceful degradation, fallback strategies, and recovery
 * mechanisms when MCP servers fail or become unavailable.
 */
#[Group('mcp-fallback')]
class MCPFallbackMechanismsTest extends TestCase
{
    use RefreshDatabase;

    protected MCPManager $mcpManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mcpManager = app(MCPManager::class);
        $this->setupFallbackConfiguration();
    }

    #[Test]
    public function it_implements_hierarchical_fallback_chain(): void
    {
        Event::fake();

        // Configure fallback chain: primary -> secondary -> tertiary
        config([
            'ai.mcp.servers.primary_tool' => [
                'type' => 'external',
                'command' => 'failing-primary',
                'enabled' => true,
                'fallback' => 'secondary_tool',
            ],
            'ai.mcp.servers.secondary_tool' => [
                'type' => 'external',
                'command' => 'failing-secondary',
                'enabled' => true,
                'fallback' => 'tertiary_tool',
            ],
            'ai.mcp.servers.tertiary_tool' => [
                'type' => 'built-in',
                'handler' => 'BuiltInTool',
                'enabled' => true,
            ],
        ]);

        // Mock all external servers to fail
        \Illuminate\Support\Facades\Process::fake([
            'failing-primary' => \Illuminate\Support\Facades\Process::result('', 'Primary failed', 1),
            'failing-secondary' => \Illuminate\Support\Facades\Process::result('', 'Secondary failed', 1),
        ]);

        $result = $this->mcpManager->executeTool('primary_tool', ['test' => 'fallback_chain']);

        // Verify final fallback succeeded
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('fallback_chain', $result);
        $this->assertEquals(['secondary_tool', 'tertiary_tool'], $result['fallback_chain']);

        // Verify events were fired for each failure
        Event::assertDispatched(MCPServerFailed::class, 2); // Primary and secondary
        Event::assertDispatched(MCPFallbackActivated::class, 2); // Two fallback activations
    }

    #[Test]
    public function it_implements_cached_fallback_responses(): void
    {
        // Configure server with cache fallback
        config([
            'ai.mcp.servers.cached_tool' => [
                'type' => 'external',
                'command' => 'unreliable-server',
                'enabled' => true,
                'cache_fallback' => true,
                'cache_ttl' => 3600,
            ],
        ]);

        // First request succeeds and caches result
        \Illuminate\Support\Facades\Process::fake([
            'unreliable-server' => \Illuminate\Support\Facades\Process::result(
                '{"success": true, "result": "cached_response"}', '', 0
            ),
        ]);

        $firstResult = $this->mcpManager->executeTool('cached_tool', ['query' => 'test']);
        
        $this->assertTrue($firstResult['success']);
        $this->assertEquals('cached_response', $firstResult['result']);

        // Second request fails but uses cached fallback
        \Illuminate\Support\Facades\Process::fake([
            'unreliable-server' => \Illuminate\Support\Facades\Process::result('', 'Server down', 1),
        ]);

        $secondResult = $this->mcpManager->executeTool('cached_tool', ['query' => 'test']);

        $this->assertTrue($secondResult['success']);
        $this->assertEquals('cached_response', $secondResult['result']);
        $this->assertArrayHasKey('from_cache', $secondResult);
        $this->assertTrue($secondResult['from_cache']);
        $this->assertArrayHasKey('cache_age', $secondResult);
    }

    #[Test]
    public function it_implements_degraded_mode_operation(): void
    {
        Event::fake();

        // Configure tool with degraded mode
        config([
            'ai.mcp.servers.degraded_tool' => [
                'type' => 'external',
                'command' => 'failing-server',
                'enabled' => true,
                'degraded_mode' => [
                    'enabled' => true,
                    'response' => [
                        'success' => true,
                        'result' => 'Service temporarily unavailable - using simplified response',
                        'degraded' => true,
                    ],
                ],
            ],
        ]);

        \Illuminate\Support\Facades\Process::fake([
            'failing-server' => \Illuminate\Support\Facades\Process::result('', 'Server error', 1),
        ]);

        $result = $this->mcpManager->executeTool('degraded_tool', ['test' => 'degraded']);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('degraded', $result);
        $this->assertTrue($result['degraded']);
        $this->assertStringContains('temporarily unavailable', $result['result']);

        Event::assertDispatched(MCPServerFailed::class);
        Event::assertDispatched(MCPFallbackActivated::class, function ($event) {
            return $event->fallbackType === 'degraded_mode';
        });
    }

    #[Test]
    public function it_implements_smart_fallback_selection(): void
    {
        // Configure multiple fallback options with priorities
        config([
            'ai.mcp.servers.smart_primary' => [
                'type' => 'external',
                'command' => 'failing-primary',
                'enabled' => true,
                'smart_fallback' => [
                    'enabled' => true,
                    'options' => [
                        [
                            'server' => 'fast_fallback',
                            'priority' => 1,
                            'conditions' => ['response_time' => '<200ms'],
                        ],
                        [
                            'server' => 'reliable_fallback',
                            'priority' => 2,
                            'conditions' => ['success_rate' => '>95%'],
                        ],
                        [
                            'server' => 'basic_fallback',
                            'priority' => 3,
                            'conditions' => [],
                        ],
                    ],
                ],
            ],
            'ai.mcp.servers.fast_fallback' => [
                'type' => 'built-in',
                'handler' => 'FastBuiltIn',
                'enabled' => true,
            ],
            'ai.mcp.servers.reliable_fallback' => [
                'type' => 'built-in',
                'handler' => 'ReliableBuiltIn',
                'enabled' => true,
            ],
            'ai.mcp.servers.basic_fallback' => [
                'type' => 'built-in',
                'handler' => 'BasicBuiltIn',
                'enabled' => true,
            ],
        ]);

        // Mock performance data for smart selection
        Cache::put('mcp_performance_fast_fallback', [
            'avg_response_time' => 150,
            'success_rate' => 98.5,
        ], 3600);

        \Illuminate\Support\Facades\Process::fake([
            'failing-primary' => \Illuminate\Support\Facades\Process::result('', 'Primary failed', 1),
        ]);

        $result = $this->mcpManager->executeTool('smart_primary', ['test' => 'smart_fallback']);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('fallback_server', $result);
        $this->assertEquals('fast_fallback', $result['fallback_server']);
        $this->assertArrayHasKey('fallback_reason', $result);
        $this->assertEquals('best_performance_match', $result['fallback_reason']);
    }

    #[Test]
    public function it_implements_partial_failure_handling(): void
    {
        // Configure tool that can handle partial failures
        config([
            'ai.mcp.servers.partial_tool' => [
                'type' => 'external',
                'command' => 'partial-failure-server',
                'enabled' => true,
                'partial_failure_handling' => true,
            ],
        ]);

        // Mock server that returns partial success
        \Illuminate\Support\Facades\Process::fake([
            'partial-failure-server' => \Illuminate\Support\Facades\Process::result(
                '{"success": false, "partial_success": true, "results": [{"success": true, "data": "item1"}, {"success": false, "error": "item2 failed"}], "error": "Some items failed"}',
                '', 0
            ),
        ]);

        $result = $this->mcpManager->executeTool('partial_tool', ['items' => ['item1', 'item2']]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('partial_success', $result);
        $this->assertTrue($result['partial_success']);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(2, $result['results']);
        
        // Verify partial results are preserved
        $this->assertTrue($result['results'][0]['success']);
        $this->assertFalse($result['results'][1]['success']);
    }

    #[Test]
    public function it_implements_automatic_recovery_detection(): void
    {
        Event::fake();

        config([
            'ai.mcp.servers.recovery_tool' => [
                'type' => 'external',
                'command' => 'recovery-server',
                'enabled' => true,
                'auto_recovery' => [
                    'enabled' => true,
                    'check_interval' => 30, // seconds
                    'max_failures' => 3,
                ],
            ],
        ]);

        // Simulate server failure then recovery
        $callCount = 0;
        \Illuminate\Support\Facades\Process::fake([
            'recovery-server' => function () use (&$callCount) {
                $callCount++;
                if ($callCount <= 2) {
                    return \Illuminate\Support\Facades\Process::result('', 'Server error', 1);
                }
                return \Illuminate\Support\Facades\Process::result(
                    '{"success": true, "result": "server_recovered"}', '', 0
                );
            },
        ]);

        // First two calls should fail
        $result1 = $this->mcpManager->executeTool('recovery_tool', ['test' => 'recovery1']);
        $result2 = $this->mcpManager->executeTool('recovery_tool', ['test' => 'recovery2']);

        $this->assertFalse($result1['success']);
        $this->assertFalse($result2['success']);

        // Third call should succeed and trigger recovery event
        $result3 = $this->mcpManager->executeTool('recovery_tool', ['test' => 'recovery3']);

        $this->assertTrue($result3['success']);
        $this->assertEquals('server_recovered', $result3['result']);

        Event::assertDispatched(MCPServerFailed::class, 2);
        Event::assertDispatched(\JTD\LaravelAI\Events\MCPServerRecovered::class);
    }

    #[Test]
    public function it_implements_load_balancing_fallback(): void
    {
        // Configure load-balanced servers with fallback
        config([
            'ai.mcp.servers.load_balanced' => [
                'type' => 'load_balanced',
                'servers' => ['server1', 'server2', 'server3'],
                'strategy' => 'round_robin',
                'health_check' => true,
            ],
            'ai.mcp.servers.server1' => [
                'type' => 'external',
                'command' => 'server1-cmd',
                'enabled' => true,
            ],
            'ai.mcp.servers.server2' => [
                'type' => 'external',
                'command' => 'server2-cmd',
                'enabled' => true,
            ],
            'ai.mcp.servers.server3' => [
                'type' => 'external',
                'command' => 'server3-cmd',
                'enabled' => true,
            ],
        ]);

        // Mock server1 and server2 to fail, server3 to succeed
        \Illuminate\Support\Facades\Process::fake([
            'server1-cmd' => \Illuminate\Support\Facades\Process::result('', 'Server1 down', 1),
            'server2-cmd' => \Illuminate\Support\Facades\Process::result('', 'Server2 down', 1),
            'server3-cmd' => \Illuminate\Support\Facades\Process::result(
                '{"success": true, "result": "server3_success"}', '', 0
            ),
        ]);

        $result = $this->mcpManager->executeTool('load_balanced', ['test' => 'load_balance']);

        $this->assertTrue($result['success']);
        $this->assertEquals('server3_success', $result['result']);
        $this->assertArrayHasKey('server_used', $result);
        $this->assertEquals('server3', $result['server_used']);
        $this->assertArrayHasKey('failed_servers', $result);
        $this->assertEquals(['server1', 'server2'], $result['failed_servers']);
    }

    #[Test]
    public function it_implements_graceful_degradation_with_user_notification(): void
    {
        Event::fake();

        config([
            'ai.mcp.servers.user_notification_tool' => [
                'type' => 'external',
                'command' => 'notification-server',
                'enabled' => true,
                'user_notification' => [
                    'enabled' => true,
                    'message' => 'Advanced features temporarily unavailable. Using basic functionality.',
                    'level' => 'warning',
                ],
            ],
        ]);

        \Illuminate\Support\Facades\Process::fake([
            'notification-server' => \Illuminate\Support\Facades\Process::result('', 'Server error', 1),
        ]);

        $result = $this->mcpManager->executeTool('user_notification_tool', ['test' => 'notification']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user_notification', $result);
        $this->assertArrayHasKey('message', $result['user_notification']);
        $this->assertArrayHasKey('level', $result['user_notification']);
        $this->assertEquals('warning', $result['user_notification']['level']);
        $this->assertStringContains('temporarily unavailable', $result['user_notification']['message']);

        Event::assertDispatched(\JTD\LaravelAI\Events\MCPUserNotificationRequired::class);
    }

    #[Test]
    public function it_tracks_fallback_usage_metrics(): void
    {
        config([
            'ai.mcp.servers.metrics_tool' => [
                'type' => 'external',
                'command' => 'metrics-server',
                'enabled' => true,
                'fallback' => 'metrics_fallback',
                'track_metrics' => true,
            ],
            'ai.mcp.servers.metrics_fallback' => [
                'type' => 'built-in',
                'handler' => 'MetricsBuiltIn',
                'enabled' => true,
            ],
        ]);

        \Illuminate\Support\Facades\Process::fake([
            'metrics-server' => \Illuminate\Support\Facades\Process::result('', 'Server error', 1),
        ]);

        $result = $this->mcpManager->executeTool('metrics_tool', ['test' => 'metrics']);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('metrics', $result);
        
        $metrics = $result['metrics'];
        $this->assertArrayHasKey('primary_server_failed', $metrics);
        $this->assertArrayHasKey('fallback_server_used', $metrics);
        $this->assertArrayHasKey('fallback_activation_time', $metrics);
        $this->assertArrayHasKey('total_execution_time', $metrics);
        
        $this->assertTrue($metrics['primary_server_failed']);
        $this->assertEquals('metrics_fallback', $metrics['fallback_server_used']);
        $this->assertIsFloat($metrics['fallback_activation_time']);
        $this->assertIsFloat($metrics['total_execution_time']);
    }

    /**
     * Setup fallback testing configuration.
     */
    protected function setupFallbackConfiguration(): void
    {
        config([
            'ai.mcp.enabled' => true,
            'ai.mcp.timeout' => 30,
            'ai.mcp.fallback' => [
                'enabled' => true,
                'cache_enabled' => true,
                'degraded_mode_enabled' => true,
                'smart_selection_enabled' => true,
                'user_notification_enabled' => true,
                'metrics_tracking_enabled' => true,
            ],
        ]);

        // Clear any existing cache
        Cache::flush();
    }
}
