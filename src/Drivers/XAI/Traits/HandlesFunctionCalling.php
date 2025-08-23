<?php

namespace JTD\LaravelAI\Drivers\XAI\Traits;

use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Handles Function Calling for xAI
 *
 * Manages function calling capabilities for xAI Grok models.
 * Supports both single and parallel function calls with proper
 * validation and error handling.
 */
trait HandlesFunctionCalling
{
    /**
     * Validate function definitions.
     */
    protected function validateFunctions(array $functions): void
    {
        foreach ($functions as $index => $function) {
            if (! isset($function['name'])) {
                throw new \InvalidArgumentException("Function at index {$index} is missing 'name' field");
            }

            if (! isset($function['description'])) {
                throw new \InvalidArgumentException("Function '{$function['name']}' is missing 'description' field");
            }

            if (isset($function['parameters'])) {
                $this->validateFunctionParameters($function['parameters'], $function['name']);
            }

            // Validate function name format
            if (! preg_match('/^[a-zA-Z0-9_-]+$/', $function['name'])) {
                throw new \InvalidArgumentException("Function name '{$function['name']}' contains invalid characters");
            }

            // Check name length
            if (strlen($function['name']) > 64) {
                throw new \InvalidArgumentException("Function name '{$function['name']}' is too long (max 64 characters)");
            }
        }
    }

    /**
     * Validate function parameters schema.
     */
    protected function validateFunctionParameters(array $parameters, string $functionName): void
    {
        if (! isset($parameters['type'])) {
            throw new \InvalidArgumentException("Function '{$functionName}' parameters missing 'type' field");
        }

        if ($parameters['type'] !== 'object') {
            throw new \InvalidArgumentException("Function '{$functionName}' parameters type must be 'object'");
        }

        if (isset($parameters['properties'])) {
            foreach ($parameters['properties'] as $propName => $propSchema) {
                if (! isset($propSchema['type'])) {
                    throw new \InvalidArgumentException("Function '{$functionName}' property '{$propName}' missing 'type' field");
                }

                $validTypes = ['string', 'number', 'integer', 'boolean', 'array', 'object'];
                if (! in_array($propSchema['type'], $validTypes)) {
                    throw new \InvalidArgumentException("Function '{$functionName}' property '{$propName}' has invalid type");
                }
            }
        }
    }

    /**
     * Process function call results.
     */
    protected function processFunctionCallResults(array $toolCalls, array $results): array
    {
        $messages = [];

        foreach ($toolCalls as $index => $toolCall) {
            $result = $results[$index] ?? null;

            if ($result === null) {
                Log::warning('Missing result for function call', [
                    'provider' => $this->providerName,
                    'function_name' => $toolCall->function->name,
                    'tool_call_id' => $toolCall->id,
                ]);

                $result = ['error' => 'Function result not provided'];
            }

            // Ensure result is JSON serializable
            if (! is_string($result)) {
                $result = json_encode($result);
            }

            $messages[] = AIMessage::tool($toolCall->id, $result);
        }

        return $messages;
    }

    /**
     * Execute function calls automatically (if callback provided).
     */
    protected function executeFunctionCalls(array $toolCalls, ?callable $functionExecutor = null): array
    {
        if (! $functionExecutor) {
            return [];
        }

        $results = [];

        foreach ($toolCalls as $toolCall) {
            try {
                $functionName = $toolCall->function->name;
                $arguments = json_decode($toolCall->function->arguments, true) ?? [];

                Log::info('Executing function call', [
                    'provider' => $this->providerName,
                    'function_name' => $functionName,
                    'tool_call_id' => $toolCall->id,
                    'arguments' => $arguments,
                ]);

                $result = $functionExecutor($functionName, $arguments, $toolCall->id);
                $results[] = $result;
            } catch (\Exception $e) {
                Log::error('Function execution failed', [
                    'provider' => $this->providerName,
                    'function_name' => $toolCall->function->name,
                    'tool_call_id' => $toolCall->id,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'error' => $e->getMessage(),
                    'function' => $toolCall->function->name,
                ];
            }
        }

        return $results;
    }

    /**
     * Handle function calling workflow.
     */
    protected function handleFunctionCallingWorkflow(
        AIResponse $response,
        array $originalMessages,
        array $options,
        ?callable $functionExecutor = null
    ): AIResponse {
        if (! $response->hasToolCalls()) {
            return $response;
        }

        // Execute functions if executor provided
        $results = $this->executeFunctionCalls($response->toolCalls, $functionExecutor);

        if (empty($results)) {
            // No executor provided, return response with tool calls
            return $response;
        }

        // Build conversation with function results
        $messages = $originalMessages;
        $messages[] = AIMessage::assistant(
            content: $response->content,
            toolCalls: $response->toolCalls
        );

        // Add function results
        $functionMessages = $this->processFunctionCallResults($response->toolCalls, $results);
        $messages = array_merge($messages, $functionMessages);

        // Get final response
        return $this->doSendMessage($messages, $options);
    }

    /**
     * Validate tool choice parameter.
     */
    protected function validateToolChoice($toolChoice): void
    {
        if ($toolChoice === null || $toolChoice === 'auto' || $toolChoice === 'none') {
            return;
        }

        if (is_array($toolChoice)) {
            if (! isset($toolChoice['type']) || $toolChoice['type'] !== 'function') {
                throw new \InvalidArgumentException('Tool choice type must be "function"');
            }

            if (! isset($toolChoice['function']['name'])) {
                throw new \InvalidArgumentException('Tool choice function must specify name');
            }

            return;
        }

        throw new \InvalidArgumentException('Invalid tool_choice format');
    }

    /**
     * Check if model supports function calling.
     */
    protected function modelSupportsFunctionCalling(string $model): bool
    {
        // All current xAI models support function calling
        return in_array($model, [
            'grok-beta',
            'grok-2',
            'grok-2-mini',
            'grok-2-1212',
            'grok-2-vision-1212',
        ]);
    }

    /**
     * Get function calling limits for model.
     */
    protected function getFunctionCallingLimits(string $model): array
    {
        return [
            'max_functions' => 100,
            'max_function_name_length' => 64,
            'max_function_description_length' => 1000,
            'max_parallel_calls' => 10,
            'supports_parallel_calls' => true,
        ];
    }

    /**
     * Optimize function definitions for the model.
     */
    protected function optimizeFunctionDefinitions(array $functions): array
    {
        $optimized = [];

        foreach ($functions as $function) {
            $optimizedFunction = $function;

            // Truncate long descriptions
            if (isset($optimizedFunction['description']) &&
                strlen($optimizedFunction['description']) > 1000) {
                $optimizedFunction['description'] = substr($optimizedFunction['description'], 0, 997) . '...';
            }

            // Simplify complex parameter schemas
            if (isset($optimizedFunction['parameters']['properties'])) {
                $optimizedFunction['parameters']['properties'] = $this->simplifyParameterProperties(
                    $optimizedFunction['parameters']['properties']
                );
            }

            $optimized[] = $optimizedFunction;
        }

        return $optimized;
    }

    /**
     * Simplify parameter properties for better model understanding.
     */
    protected function simplifyParameterProperties(array $properties): array
    {
        $simplified = [];

        foreach ($properties as $name => $property) {
            $simplifiedProperty = [
                'type' => $property['type'],
                'description' => $property['description'] ?? '',
            ];

            // Keep essential fields
            if (isset($property['enum'])) {
                $simplifiedProperty['enum'] = $property['enum'];
            }

            if (isset($property['items']) && $property['type'] === 'array') {
                $simplifiedProperty['items'] = [
                    'type' => $property['items']['type'] ?? 'string',
                ];
            }

            $simplified[$name] = $simplifiedProperty;
        }

        return $simplified;
    }

    /**
     * Create function calling examples for better model understanding.
     */
    protected function createFunctionExamples(array $functions): array
    {
        $examples = [];

        foreach ($functions as $function) {
            $example = [
                'function_name' => $function['name'],
                'description' => $function['description'],
                'example_call' => $this->generateExampleCall($function),
            ];

            $examples[] = $example;
        }

        return $examples;
    }

    /**
     * Generate example function call.
     */
    protected function generateExampleCall(array $function): array
    {
        $example = [];
        $properties = $function['parameters']['properties'] ?? [];

        foreach ($properties as $name => $property) {
            $example[$name] = $this->generateExampleValue($property);
        }

        return $example;
    }

    /**
     * Generate example value for parameter type.
     */
    protected function generateExampleValue(array $property): mixed
    {
        return match ($property['type']) {
            'string' => $property['enum'][0] ?? 'example_string',
            'number' => 42.0,
            'integer' => 42,
            'boolean' => true,
            'array' => ['example_item'],
            'object' => ['key' => 'value'],
            default => 'example_value',
        };
    }

    /**
     * Log function calling metrics.
     */
    protected function logFunctionCallingMetrics(array $toolCalls, float $executionTime): void
    {
        Log::info('Function calling completed', [
            'provider' => $this->providerName,
            'function_count' => count($toolCalls),
            'functions' => array_map(fn ($tc) => $tc->function->name, $toolCalls),
            'execution_time_ms' => round($executionTime * 1000, 2),
            'parallel_execution' => count($toolCalls) > 1,
        ]);
    }
}
