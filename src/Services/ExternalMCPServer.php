<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Contracts\MCPServerInterface;
use JTD\LaravelAI\Exceptions\MCPException;
use JTD\LaravelAI\Exceptions\MCPToolException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * External MCP Server Implementation
 *
 * Handles communication with external MCP servers via command execution.
 * Supports npm packages and other external MCP server implementations.
 */
class ExternalMCPServer implements MCPServerInterface
{
    /**
     * Server name/identifier.
     */
    protected string $name;

    /**
     * Server configuration.
     */
    protected array $config;

    /**
     * Server display name.
     */
    protected string $displayName;

    /**
     * Server description.
     */
    protected string $description;

    /**
     * Performance metrics cache.
     */
    protected array $metrics = [];

    /**
     * Create a new external MCP server instance.
     */
    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->config = $config;
        $this->displayName = $config['display_name'] ?? ucfirst(str_replace('-', ' ', $name));
        $this->description = $config['description'] ?? "External MCP server: {$name}";
    }

    /**
     * Get the unique identifier for this MCP server.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the display name for this MCP server.
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * Get the description of what this MCP server provides.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Check if the MCP server is properly configured and ready to use.
     */
    public function isConfigured(): bool
    {
        // Check if command is specified
        if (empty($this->config['command'])) {
            return false;
        }

        // Check if required environment variables are set
        if (! empty($this->config['env'])) {
            foreach ($this->config['env'] as $key => $value) {
                // Skip if value is a placeholder
                if (str_starts_with($value, '${') && str_ends_with($value, '}')) {
                    $envVar = substr($value, 2, -1);
                    if (empty(env($envVar))) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check if the MCP server is currently enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Get the server configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Process an AI message through this MCP server.
     */
    public function processMessage(AIMessage $message): AIMessage
    {
        if (! $this->isConfigured() || ! $this->isEnabled()) {
            return $message;
        }

        try {
            $startTime = microtime(true);

            // For now, external servers don't modify messages directly
            // This could be extended to support message preprocessing
            $processedMessage = $message;

            $this->recordMetric('message_processing_time', microtime(true) - $startTime);

            return $processedMessage;
        } catch (\Exception $e) {
            $this->recordMetric('message_processing_errors', 1);
            throw new MCPException("Failed to process message: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Process an AI response through this MCP server.
     */
    public function processResponse(AIResponse $response): AIResponse
    {
        if (! $this->isConfigured() || ! $this->isEnabled()) {
            return $response;
        }

        try {
            $startTime = microtime(true);

            // For now, external servers don't modify responses directly
            // This could be extended to support response postprocessing
            $processedResponse = $response;

            // Add metadata about MCP processing
            $metadata = $response->metadata ?? [];
            $metadata["mcp_{$this->name}"] = true;
            $processedResponse->metadata = $metadata;

            $this->recordMetric('response_processing_time', microtime(true) - $startTime);

            return $processedResponse;
        } catch (\Exception $e) {
            $this->recordMetric('response_processing_errors', 1);
            throw new MCPException("Failed to process response: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the tools available from this MCP server.
     */
    public function getAvailableTools(): array
    {
        if (! $this->isConfigured() || ! $this->isEnabled()) {
            return [];
        }

        $cacheKey = "mcp_tools_{$this->name}";
        $cacheTtl = config('ai.mcp.tool_discovery_cache_ttl', 3600);

        return Cache::remember($cacheKey, $cacheTtl, function () {
            try {
                $result = $this->executeCommand(['--list-tools']);

                if ($result['success'] && isset($result['output']['tools'])) {
                    return $result['output']['tools'];
                }

                return [];
            } catch (\Exception $e) {
                Log::error("Failed to get tools from MCP server {$this->name}", [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Execute a tool provided by this MCP server.
     */
    public function executeTool(string $toolName, array $parameters = []): array
    {
        if (! $this->isConfigured() || ! $this->isEnabled()) {
            throw new MCPToolException("MCP server {$this->name} is not configured or enabled");
        }

        try {
            $startTime = microtime(true);

            $command = [
                '--tool', $toolName,
                '--params', json_encode($parameters),
            ];

            $result = $this->executeCommand($command);

            $executionTime = microtime(true) - $startTime;
            $this->recordMetric('tool_execution_time', $executionTime);

            if (! $result['success']) {
                $this->recordMetric('tool_execution_errors', 1);
                throw new MCPToolException("Tool execution failed: {$result['error']}");
            }

            $this->recordMetric('tool_executions', 1);

            return $result['output'] ?? [];
        } catch (MCPToolException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->recordMetric('tool_execution_errors', 1);
            throw new MCPToolException("Failed to execute tool {$toolName}: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Test the connectivity and functionality of this MCP server.
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'error',
                'message' => 'Server is not properly configured',
            ];
        }

        if (! $this->isEnabled()) {
            return [
                'status' => 'disabled',
                'message' => 'Server is disabled',
            ];
        }

        try {
            $startTime = microtime(true);
            $result = $this->executeCommand(['--health']);
            $responseTime = (microtime(true) - $startTime) * 1000;

            if ($result['success']) {
                return [
                    'status' => 'healthy',
                    'message' => 'Server is responding normally',
                    'response_time_ms' => round($responseTime, 2),
                    'version' => $result['output']['version'] ?? 'unknown',
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => $result['error'] ?? 'Health check failed',
                    'response_time_ms' => round($responseTime, 2),
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => "Connection test failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Get performance metrics for this MCP server.
     */
    public function getMetrics(): array
    {
        return [
            'server_name' => $this->name,
            'server_type' => $this->getType(),
            'is_enabled' => $this->isEnabled(),
            'is_configured' => $this->isConfigured(),
            'metrics' => $this->metrics,
            'collected_at' => now()->toISOString(),
        ];
    }

    /**
     * Get the server type.
     */
    public function getType(): string
    {
        return 'external';
    }

    /**
     * Get the server version.
     */
    public function getVersion(): string
    {
        return $this->config['version'] ?? '1.0.0';
    }

    /**
     * Execute a command on the external MCP server.
     */
    protected function executeCommand(array $args = []): array
    {
        $command = $this->config['command'];
        $commandArgs = array_merge($this->config['args'] ?? [], $args);
        $timeout = $this->config['timeout'] ?? config('ai.mcp.external_server_timeout', 30);

        // Prepare environment variables
        $env = [];
        if (! empty($this->config['env'])) {
            foreach ($this->config['env'] as $key => $value) {
                // Resolve environment variable placeholders
                if (str_starts_with($value, '${') && str_ends_with($value, '}')) {
                    $envVar = substr($value, 2, -1);
                    $env[$key] = env($envVar, '');
                } else {
                    $env[$key] = $value;
                }
            }
        }

        try {
            $process = Process::timeout($timeout)
                ->env($env)
                ->run(array_merge([$command], $commandArgs));

            if ($process->successful()) {
                $output = $process->output();

                // Try to parse JSON output
                $parsedOutput = null;
                if (! empty($output)) {
                    try {
                        $parsedOutput = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException $e) {
                        // If not JSON, return as plain text
                        $parsedOutput = ['text' => $output];
                    }
                }

                return [
                    'success' => true,
                    'output' => $parsedOutput,
                    'exit_code' => $process->exitCode(),
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $process->errorOutput() ?: 'Command execution failed',
                    'exit_code' => $process->exitCode(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Process execution failed: {$e->getMessage()}",
                'exit_code' => -1,
            ];
        }
    }

    /**
     * Record a performance metric.
     */
    protected function recordMetric(string $key, float $value): void
    {
        if (! isset($this->metrics[$key])) {
            $this->metrics[$key] = [
                'count' => 0,
                'total' => 0,
                'average' => 0,
                'min' => null,
                'max' => null,
            ];
        }

        $metric = &$this->metrics[$key];
        $metric['count']++;
        $metric['total'] += $value;
        $metric['average'] = $metric['total'] / $metric['count'];
        $metric['min'] = $metric['min'] === null ? $value : min($metric['min'], $value);
        $metric['max'] = $metric['max'] === null ? $value : max($metric['max'], $value);
    }
}
