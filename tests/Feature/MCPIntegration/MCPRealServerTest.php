<?php

namespace JTD\LaravelAI\Tests\Feature\MCPIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Events\MCPToolExecuted;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MCP Real Server E2E Tests
 *
 * End-to-end tests with real MCP servers using actual API credentials.
 * Tests are skipped when credentials are not available.
 */
#[Group('mcp-integration')]
#[Group('mcp-real-servers')]
class MCPRealServerTest extends TestCase
{
    use RefreshDatabase;

    protected MCPManager $mcpManager;

    protected array $credentials = [];

    protected array $availableServers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mcpManager = app(MCPManager::class);
        $this->loadCredentials();
        $this->setupRealServerConfiguration();
        $this->detectAvailableServers();
    }

    #[Test]
    public function it_executes_sequential_thinking_with_real_processing(): void
    {
        if (! $this->isServerAvailable('sequential_thinking')) {
            $this->markTestSkipped('Sequential Thinking MCP server not available');
        }

        Event::fake();

        $startTime = microtime(true);

        $result = $this->mcpManager->executeTool('sequential_thinking', [
            'thought' => 'Let me analyze this complex problem step by step. First, I need to understand the core requirements.',
            'nextThoughtNeeded' => true,
            'thoughtNumber' => 1,
            'totalThoughts' => 3,
        ]);

        $executionTime = (microtime(true) - $startTime) * 1000;

        // Verify successful execution
        $this->assertIsArray($result);
        $this->assertTrue($result['success'], 'Sequential thinking should execute successfully');
        $this->assertArrayHasKey('result', $result);

        // Verify performance target
        $this->assertLessThan(100, $executionTime,
            "Sequential thinking took {$executionTime}ms, exceeding 100ms target");

        // Verify event was fired
        Event::assertDispatched(MCPToolExecuted::class, function ($event) {
            return $event->toolName === 'sequential_thinking' && $event->result['success'];
        });

        // Log successful test
        Log::info('E2E Sequential Thinking Test Passed', [
            'execution_time_ms' => $executionTime,
            'result_size' => strlen(json_encode($result)),
        ]);
    }

    #[Test]
    public function it_executes_brave_search_with_real_api(): void
    {
        if (! $this->isServerAvailable('brave_search')) {
            $this->markTestSkipped('Brave Search MCP server not available or credentials missing');
        }

        Event::fake();

        $startTime = microtime(true);

        $result = $this->mcpManager->executeTool('brave_search', [
            'query' => 'Laravel MCP integration best practices',
            'count' => 5,
        ]);

        $executionTime = (microtime(true) - $startTime) * 1000;

        // Verify successful execution
        $this->assertIsArray($result);
        $this->assertTrue($result['success'], 'Brave Search should execute successfully: ' . ($result['error'] ?? ''));
        $this->assertArrayHasKey('result', $result);

        // Verify search results structure
        $searchResults = $result['result'];
        $this->assertIsArray($searchResults);
        $this->assertArrayHasKey('results', $searchResults);
        $this->assertIsArray($searchResults['results']);
        $this->assertGreaterThan(0, count($searchResults['results']), 'Should return search results');

        // Verify result structure
        foreach (array_slice($searchResults['results'], 0, 3) as $searchResult) {
            $this->assertArrayHasKey('title', $searchResult);
            $this->assertArrayHasKey('url', $searchResult);
            $this->assertArrayHasKey('description', $searchResult);
        }

        // Verify performance target for external server
        $this->assertLessThan(500, $executionTime,
            "Brave Search took {$executionTime}ms, exceeding 500ms target");

        // Verify event was fired
        Event::assertDispatched(MCPToolExecuted::class, function ($event) {
            return $event->toolName === 'brave_search' && $event->result['success'];
        });

        // Log successful test
        Log::info('E2E Brave Search Test Passed', [
            'execution_time_ms' => $executionTime,
            'results_count' => count($searchResults['results']),
            'query' => 'Laravel MCP integration best practices',
        ]);
    }

    #[Test]
    public function it_executes_github_mcp_with_real_api(): void
    {
        if (! $this->isServerAvailable('github')) {
            $this->markTestSkipped('GitHub MCP server not available or credentials missing');
        }

        Event::fake();

        $startTime = microtime(true);

        $result = $this->mcpManager->executeTool('github', [
            'action' => 'search_repositories',
            'query' => 'laravel ai',
            'limit' => 5,
        ]);

        $executionTime = (microtime(true) - $startTime) * 1000;

        // Verify successful execution
        $this->assertIsArray($result);
        $this->assertTrue($result['success'], 'GitHub MCP should execute successfully: ' . ($result['error'] ?? ''));
        $this->assertArrayHasKey('result', $result);

        // Verify GitHub API results structure
        $githubResults = $result['result'];
        $this->assertIsArray($githubResults);
        $this->assertArrayHasKey('repositories', $githubResults);
        $this->assertIsArray($githubResults['repositories']);
        $this->assertGreaterThan(0, count($githubResults['repositories']), 'Should return repository results');

        // Verify repository structure
        foreach (array_slice($githubResults['repositories'], 0, 3) as $repo) {
            $this->assertArrayHasKey('name', $repo);
            $this->assertArrayHasKey('full_name', $repo);
            $this->assertArrayHasKey('html_url', $repo);
            $this->assertArrayHasKey('description', $repo);
        }

        // Verify performance target for external server
        $this->assertLessThan(500, $executionTime,
            "GitHub MCP took {$executionTime}ms, exceeding 500ms target");

        // Verify event was fired
        Event::assertDispatched(MCPToolExecuted::class, function ($event) {
            return $event->toolName === 'github' && $event->result['success'];
        });

        // Log successful test
        Log::info('E2E GitHub MCP Test Passed', [
            'execution_time_ms' => $executionTime,
            'repositories_count' => count($githubResults['repositories']),
            'query' => 'laravel ai',
        ]);
    }

    #[Test]
    public function it_handles_multiple_real_servers_in_sequence(): void
    {
        $availableTools = array_filter([
            'sequential_thinking' => $this->isServerAvailable('sequential_thinking'),
            'brave_search' => $this->isServerAvailable('brave_search'),
            'github' => $this->isServerAvailable('github'),
        ]);

        if (count($availableTools) < 2) {
            $this->markTestSkipped('Need at least 2 MCP servers available for sequence test');
        }

        Event::fake();

        $results = [];
        $totalStartTime = microtime(true);

        // Execute available tools in sequence
        foreach (array_keys($availableTools) as $tool) {
            $startTime = microtime(true);

            $result = $this->mcpManager->executeTool($tool, $this->getToolParameters($tool));

            $executionTime = (microtime(true) - $startTime) * 1000;

            $results[$tool] = [
                'result' => $result,
                'execution_time' => $executionTime,
            ];
        }

        $totalExecutionTime = (microtime(true) - $totalStartTime) * 1000;

        // Verify all tools executed successfully
        foreach ($results as $tool => $data) {
            $this->assertTrue($data['result']['success'], "Tool {$tool} should execute successfully");
        }

        // Verify total execution time is reasonable
        $this->assertLessThan(2000, $totalExecutionTime,
            "Sequential execution took {$totalExecutionTime}ms, exceeding 2000ms target");

        // Verify events were fired for each tool
        Event::assertDispatchedTimes(MCPToolExecuted::class, count($availableTools));

        // Log successful sequence test
        Log::info('E2E Sequential MCP Test Passed', [
            'tools_tested' => array_keys($availableTools),
            'total_execution_time_ms' => $totalExecutionTime,
            'individual_times' => array_map(fn ($data) => $data['execution_time'], $results),
        ]);
    }

    #[Test]
    public function it_handles_real_server_errors_gracefully(): void
    {
        if (! $this->isServerAvailable('brave_search')) {
            $this->markTestSkipped('Brave Search not available for error testing');
        }

        // Test with invalid parameters to trigger server error
        $result = $this->mcpManager->executeTool('brave_search', [
            'query' => '', // Empty query should cause error
            'count' => -1, // Invalid count
        ]);

        $this->assertIsArray($result);

        // Should handle error gracefully
        if (! $result['success']) {
            $this->assertArrayHasKey('error', $result);
            $this->assertArrayHasKey('error_type', $result);
            Log::info('E2E Error Handling Test Passed', [
                'error_type' => $result['error_type'],
                'error_message' => $result['error'],
            ]);
        } else {
            // Some servers might handle empty queries gracefully
            Log::info('E2E Error Handling Test: Server handled invalid input gracefully');
        }
    }

    #[Test]
    public function it_measures_real_world_performance_characteristics(): void
    {
        $performanceData = [];
        $iterations = 3; // Fewer iterations for real API calls

        foreach ($this->availableServers as $server) {
            $executionTimes = [];

            for ($i = 0; $i < $iterations; $i++) {
                $startTime = microtime(true);

                $result = $this->mcpManager->executeTool($server, $this->getToolParameters($server));

                $executionTime = (microtime(true) - $startTime) * 1000;

                if ($result['success']) {
                    $executionTimes[] = $executionTime;
                }
            }

            if (! empty($executionTimes)) {
                $performanceData[$server] = [
                    'avg_ms' => array_sum($executionTimes) / count($executionTimes),
                    'min_ms' => min($executionTimes),
                    'max_ms' => max($executionTimes),
                    'iterations' => count($executionTimes),
                    'success_rate' => (count($executionTimes) / $iterations) * 100,
                ];
            }
        }

        // Verify performance characteristics
        foreach ($performanceData as $server => $data) {
            $expectedTarget = $server === 'sequential_thinking' ? 100 : 500;

            $this->assertLessThan($expectedTarget * 1.5, $data['avg_ms'],
                "Server {$server} average time {$data['avg_ms']}ms exceeds reasonable target");

            $this->assertGreaterThanOrEqual(66, $data['success_rate'],
                "Server {$server} success rate {$data['success_rate']}% is too low");
        }

        // Log performance data
        Log::info('E2E Performance Characteristics', [
            'performance_data' => $performanceData,
            'test_environment' => 'real_servers',
        ]);

        $this->assertNotEmpty($performanceData, 'Should have performance data for at least one server');
    }

    /**
     * Load credentials from the test credentials file.
     */
    protected function loadCredentials(): void
    {
        $credentialsPath = __DIR__ . '/../credentials/e2e-credentials.json';

        if (! file_exists($credentialsPath)) {
            $this->credentials = [];

            return;
        }

        $credentialsContent = file_get_contents($credentialsPath);
        $credentials = json_decode($credentialsContent, true);

        $this->credentials = $credentials['mcp_servers'] ?? [];
    }

    /**
     * Setup real server configuration.
     */
    protected function setupRealServerConfiguration(): void
    {
        $servers = [];

        // Sequential Thinking (built-in)
        $servers['sequential_thinking'] = [
            'type' => 'built-in',
            'enabled' => $this->credentials['sequential_thinking']['enabled'] ?? true,
        ];

        // Brave Search
        if (! empty($this->credentials['brave_search']['api_key'])) {
            $servers['brave_search'] = [
                'type' => 'external',
                'command' => 'npx @modelcontextprotocol/server-brave-search',
                'enabled' => $this->credentials['brave_search']['enabled'] ?? true,
                'env' => [
                    'BRAVE_API_KEY' => $this->credentials['brave_search']['api_key'],
                ],
            ];
        }

        // GitHub
        if (! empty($this->credentials['github']['token'])) {
            $servers['github'] = [
                'type' => 'external',
                'command' => 'npx @modelcontextprotocol/server-github',
                'enabled' => $this->credentials['github']['enabled'] ?? true,
                'env' => [
                    'GITHUB_TOKEN' => $this->credentials['github']['token'],
                ],
            ];
        }

        config(['ai.mcp.servers' => $servers]);
    }

    /**
     * Detect available servers.
     */
    protected function detectAvailableServers(): void
    {
        $servers = config('ai.mcp.servers', []);

        foreach ($servers as $name => $config) {
            if ($config['enabled'] ?? false) {
                $this->availableServers[] = $name;
            }
        }
    }

    /**
     * Check if server is available.
     */
    protected function isServerAvailable(string $server): bool
    {
        return in_array($server, $this->availableServers);
    }

    /**
     * Get tool-specific parameters.
     */
    protected function getToolParameters(string $tool): array
    {
        return match ($tool) {
            'sequential_thinking' => [
                'thought' => 'E2E test thought for real server validation',
                'nextThoughtNeeded' => false,
                'thoughtNumber' => 1,
                'totalThoughts' => 1,
            ],
            'brave_search' => [
                'query' => 'Laravel package development',
                'count' => 3,
            ],
            'github' => [
                'action' => 'search_repositories',
                'query' => 'laravel',
                'limit' => 3,
            ],
            default => ['test' => 'e2e_parameter'],
        };
    }
}
