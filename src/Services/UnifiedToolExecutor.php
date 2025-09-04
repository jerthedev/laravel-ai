<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelAI\Contracts\MCPManagerInterface;
use JTD\LaravelAI\Events\AIFunctionCalled;
use JTD\LaravelAI\Events\AIFunctionCompleted;
use JTD\LaravelAI\Events\AIFunctionFailed;
use JTD\LaravelAI\Exceptions\ToolExecutionException;
use JTD\LaravelAI\Jobs\ProcessFunctionCallJob;

/**
 * Unified Tool Executor Service
 *
 * Routes tool calls to appropriate execution systems:
 * - MCP tools: Execute immediately and return results
 * - Function Events: Queue for background processing
 */
class UnifiedToolExecutor
{
    /**
     * Unified Tool Registry.
     */
    protected UnifiedToolRegistry $toolRegistry;

    /**
     * MCP Manager for immediate tool execution.
     */
    protected MCPManagerInterface $mcpManager;

    /**
     * Create a new unified tool executor instance.
     */
    public function __construct(
        UnifiedToolRegistry $toolRegistry,
        MCPManagerInterface $mcpManager
    ) {
        $this->toolRegistry = $toolRegistry;
        $this->mcpManager = $mcpManager;
    }

    /**
     * Process multiple tool calls from AI response.
     */
    public function processToolCalls(array $toolCalls, array $context = []): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            try {
                $result = $this->executeToolCall(
                    $toolCall['name'] ?? $toolCall['function']['name'] ?? '',
                    $toolCall['arguments'] ?? $toolCall['function']['arguments'] ?? [],
                    $context
                );

                $results[] = [
                    'tool_call_id' => $toolCall['id'] ?? uniqid('tool_'),
                    'name' => $toolCall['name'] ?? $toolCall['function']['name'] ?? '',
                    'result' => $result,
                    'status' => 'success',
                ];
            } catch (\Exception $e) {
                Log::error('Tool execution failed', [
                    'tool_name' => $toolCall['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'context' => $context,
                ]);

                $results[] = [
                    'tool_call_id' => $toolCall['id'] ?? uniqid('tool_'),
                    'name' => $toolCall['name'] ?? $toolCall['function']['name'] ?? '',
                    'result' => null,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Execute a single tool call.
     */
    public function executeToolCall(string $toolName, array $parameters, array $context = []): mixed
    {
        $tool = $this->toolRegistry->getTool($toolName);

        if (! $tool) {
            throw new ToolExecutionException("Tool '{$toolName}' not found in registry");
        }

        $startTime = microtime(true);

        // Fire tool called event
        event(new AIFunctionCalled(
            functionName: $toolName,
            parameters: $parameters,
            userId: $context['user_id'] ?? 0,
            conversationId: $context['conversation_id'] ?? null,
            messageId: $context['message_id'] ?? null,
            context: $context
        ));

        try {
            $result = match ($tool['type']) {
                'mcp_tool' => $this->routeToMCP($toolName, $parameters, $tool, $context),
                'function_event' => $this->routeToFunctionEvent($toolName, $parameters, $tool, $context),
                default => throw new ToolExecutionException("Unknown tool type: {$tool['type']}")
            };

            $executionTime = microtime(true) - $startTime;

            // Fire success event
            event(new AIFunctionCompleted(
                functionName: $toolName,
                parameters: $parameters,
                result: $result,
                executionTime: $executionTime,
                userId: $context['user_id'] ?? 0,
                conversationId: $context['conversation_id'] ?? null,
                messageId: $context['message_id'] ?? null,
                context: $context
            ));

            return $result;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            // Fire failure event
            event(new AIFunctionFailed(
                functionName: $toolName,
                parameters: $parameters,
                error: $e,
                executionTime: $executionTime,
                userId: $context['user_id'] ?? 0,
                conversationId: $context['conversation_id'] ?? null,
                messageId: $context['message_id'] ?? null,
                context: $context
            ));

            throw $e;
        }
    }

    /**
     * Route tool call to MCP system for immediate execution.
     */
    public function routeToMCP(string $toolName, array $parameters, array $tool, array $context = []): array
    {
        $serverName = $tool['server'] ?? null;

        if (! $serverName) {
            throw new ToolExecutionException("MCP tool '{$toolName}' has no server specified");
        }

        Log::debug('Executing MCP tool', [
            'tool_name' => $toolName,
            'server' => $serverName,
            'parameters' => $parameters,
        ]);

        try {
            $result = $this->mcpManager->executeTool($serverName, $toolName, $parameters);

            return [
                'type' => 'mcp_result',
                'tool_name' => $toolName,
                'server' => $serverName,
                'result' => $result,
                'execution_mode' => 'immediate',
            ];
        } catch (\Exception $e) {
            throw new ToolExecutionException(
                "MCP tool execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Route tool call to Function Event system for background processing.
     */
    public function routeToFunctionEvent(string $toolName, array $parameters, array $tool, array $context = []): array
    {
        Log::debug('Queuing Function Event', [
            'function_name' => $toolName,
            'parameters' => $parameters,
            'context' => $context,
        ]);

        try {
            // Queue the function call for background processing
            $job = new ProcessFunctionCallJob(
                functionName: $toolName,
                parameters: $parameters,
                userId: $context['user_id'] ?? 0,
                conversationId: $context['conversation_id'] ?? null,
                messageId: $context['message_id'] ?? null,
                context: $context
            );

            Queue::push($job);

            return [
                'type' => 'function_event_queued',
                'function_name' => $toolName,
                'status' => 'queued',
                'execution_mode' => 'background',
                'message' => "Function '{$toolName}' has been queued for background processing",
            ];
        } catch (\Exception $e) {
            throw new ToolExecutionException(
                "Function Event queuing failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Get execution statistics.
     */
    public function getExecutionStats(): array
    {
        // This would typically pull from cache or database
        // For now, return basic structure
        return [
            'total_executions' => 0,
            'mcp_executions' => 0,
            'function_event_executions' => 0,
            'successful_executions' => 0,
            'failed_executions' => 0,
            'average_execution_time' => 0.0,
        ];
    }

    /**
     * Validate tool parameters against tool schema.
     */
    public function validateToolParameters(string $toolName, array $parameters): bool
    {
        $tool = $this->toolRegistry->getTool($toolName);

        if (! $tool) {
            return false;
        }

        $schema = $tool['parameters'] ?? [];

        // Basic validation - in production, use a proper JSON schema validator
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                if (! isset($parameters[$requiredField])) {
                    throw new ToolExecutionException(
                        "Missing required parameter '{$requiredField}' for tool '{$toolName}'"
                    );
                }
            }
        }

        return true;
    }
}
