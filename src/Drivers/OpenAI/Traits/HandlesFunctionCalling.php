<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Handles Function Calling Features
 *
 * Manages function definitions, tool calls,
 * and function execution workflows.
 */
trait HandlesFunctionCalling
{
    /**
     * Format functions for OpenAI API.
     */
    protected function formatFunctions(array $functions): array
    {
        return array_map([$this, 'validateAndFormatFunction'], $functions);
    }

    /**
     * Format tools for OpenAI API.
     */
    protected function formatTools(array $tools): array
    {
        return array_map([$this, 'validateAndFormatTool'], $tools);
    }

    /**
     * Validate and format a single function definition.
     */
    protected function validateAndFormatFunction(array $function): array
    {
        if (! isset($function['name'])) {
            throw new \InvalidArgumentException('Function must have a name');
        }

        if (! isset($function['description'])) {
            throw new \InvalidArgumentException('Function must have a description');
        }

        $formatted = [
            'name' => $function['name'],
            'description' => $function['description'],
        ];

        if (isset($function['parameters'])) {
            $formatted['parameters'] = $this->validateFunctionParameters($function['parameters']);
        }

        return $formatted;
    }

    /**
     * Validate and format a single tool definition.
     */
    protected function validateAndFormatTool(array $tool): array
    {
        if (! isset($tool['type'])) {
            throw new \InvalidArgumentException('Tool must have a type');
        }

        if ($tool['type'] === 'function') {
            if (! isset($tool['function'])) {
                throw new \InvalidArgumentException('Function tool must have a function definition');
            }

            return [
                'type' => 'function',
                'function' => $this->validateAndFormatFunction($tool['function']),
            ];
        }

        throw new \InvalidArgumentException('Unsupported tool type: ' . $tool['type']);
    }

    /**
     * Validate function parameters schema.
     */
    protected function validateFunctionParameters(array $parameters): array
    {
        if (! isset($parameters['type'])) {
            throw new \InvalidArgumentException('Function parameters must have a type');
        }

        if ($parameters['type'] !== 'object') {
            throw new \InvalidArgumentException('Function parameters type must be "object"');
        }

        return $parameters;
    }

    /**
     * Check if response has function calls.
     */
    protected function hasFunctionCalls(AIResponse $response): bool
    {
        return ! empty($response->functionCalls) || ! empty($response->toolCalls);
    }

    /**
     * Extract function calls from response.
     */
    protected function extractFunctionCalls(AIResponse $response): array
    {
        $calls = [];

        // Handle legacy function_call format
        if ($response->functionCalls) {
            $calls[] = [
                'type' => 'function',
                'name' => $response->functionCalls['name'] ?? '',
                'arguments' => $response->functionCalls['arguments'] ?? '{}',
                'id' => null,
            ];
        }

        // Handle new tool_calls format
        if ($response->toolCalls) {
            foreach ($response->toolCalls as $toolCall) {
                if ($toolCall['type'] === 'function') {
                    $calls[] = [
                        'type' => 'function',
                        'name' => $toolCall['function']['name'] ?? '',
                        'arguments' => $toolCall['function']['arguments'] ?? '{}',
                        'id' => $toolCall['id'] ?? null,
                    ];
                }
            }
        }

        return $calls;
    }

    /**
     * Create a function result message.
     */
    public function createFunctionResultMessage(string $functionName, string $result): AIMessage
    {
        $validatedResult = $this->validateFunctionResult($result);

        return new AIMessage(
            'function',
            $validatedResult ?: 'Function executed successfully', // Ensure non-empty content
            AIMessage::CONTENT_TYPE_TEXT,
            null, // attachments
            null, // functionCalls
            null, // toolCalls
            ['function_name' => $functionName], // metadata
            $functionName // name
        );
    }

    /**
     * Create a tool result message.
     */
    public function createToolResultMessage(string $toolCallId, string $result): AIMessage
    {
        return new AIMessage(
            'tool',
            $this->validateFunctionResult($result),
            AIMessage::CONTENT_TYPE_TEXT,
            null, // attachments
            null, // functionCalls
            null, // toolCalls
            ['tool_call_id' => $toolCallId], // metadata
            $toolCallId, // name (required for tool role)
            null // timestamp
        );
    }

    /**
     * Validate function result.
     */
    protected function validateFunctionResult($result): string
    {
        if (is_string($result)) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return json_encode($result);
        }

        return (string) $result;
    }

    /**
     * Execute function calls (placeholder for user implementation).
     */
    public function executeFunctionCalls(array $functionCalls, ?callable $executor = null): array
    {
        if (! $executor) {
            throw new \InvalidArgumentException('Function executor callback is required');
        }

        $results = [];

        foreach ($functionCalls as $call) {
            try {
                $arguments = json_decode($call['arguments'], true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Invalid JSON in function arguments: ' . json_last_error_msg());
                }

                $arguments = $arguments ?? [];
                $result = $executor($call['name'], $arguments);

                $results[] = [
                    'call' => $call,
                    'result' => $this->validateFunctionResult($result),
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'call' => $call,
                    'result' => 'Error: ' . $e->getMessage(),
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Create conversation with function calling workflow.
     *
     * @deprecated This method is deprecated and will be removed in the next version.
     *             Use the new unified tool system with withTools() or allTools() instead.
     */
    public function conversationWithFunctions(
        $message,
        array $functions,
        ?callable $functionExecutor = null,
        array $options = []
    ): AIResponse {
        // Add functions to options
        $options['functions'] = $functions;

        // Send initial message
        $response = $this->sendMessage($message, $options);

        // Handle function calls if present
        if ($this->hasFunctionCalls($response)) {
            $functionCalls = $this->extractFunctionCalls($response);
            $functionResults = $this->executeFunctionCalls($functionCalls, $functionExecutor);

            // Build conversation history with function results
            $conversationHistory = [
                $message instanceof AIMessage ? $message : AIMessage::user($message),
                $response->toMessage(),
            ];

            // Add function results to conversation
            foreach ($functionResults as $result) {
                if ($result['call']['id']) {
                    // Tool call format
                    $conversationHistory[] = $this->createToolResultMessage(
                        $result['call']['id'],
                        $result['result']
                    );
                } else {
                    // Legacy function call format
                    $conversationHistory[] = $this->createFunctionResultMessage(
                        $result['call']['name'],
                        $result['result']
                    );
                }
            }

            // Send follow-up message with function results
            $followUpOptions = array_merge($options, [
                'conversation_history' => $conversationHistory,
            ]);

            return $this->sendMessage('', $followUpOptions);
        }

        return $response;
    }

    /**
     * Validate function definition schema.
     */
    public function validateFunctionDefinition(array $function): array
    {
        $errors = [];

        if (! isset($function['name'])) {
            $errors[] = 'Function name is required';
        } elseif (! is_string($function['name']) || empty($function['name'])) {
            $errors[] = 'Function name must be a non-empty string';
        } elseif (! preg_match('/^[a-zA-Z0-9_-]+$/', $function['name'])) {
            $errors[] = 'Function name can only contain letters, numbers, underscores, and hyphens';
        }

        if (! isset($function['description'])) {
            $errors[] = 'Function description is required';
        } elseif (! is_string($function['description']) || empty($function['description'])) {
            $errors[] = 'Function description must be a non-empty string';
        }

        if (isset($function['parameters'])) {
            if (! is_array($function['parameters'])) {
                $errors[] = 'Function parameters must be an array';
            } else {
                $paramErrors = $this->validateParametersSchema($function['parameters']);
                $errors = array_merge($errors, $paramErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate parameters schema.
     */
    protected function validateParametersSchema(array $parameters): array
    {
        $errors = [];

        if (! isset($parameters['type'])) {
            $errors[] = 'Parameters must have a type';
        } elseif ($parameters['type'] !== 'object') {
            $errors[] = 'Parameters type must be "object"';
        }

        if (isset($parameters['properties']) && ! is_array($parameters['properties'])) {
            $errors[] = 'Parameters properties must be an array';
        }

        if (isset($parameters['required']) && ! is_array($parameters['required'])) {
            $errors[] = 'Parameters required must be an array';
        }

        return $errors;
    }

    /**
     * Get function calling examples.
     */
    public function getFunctionCallingExamples(): array
    {
        return [
            'weather_function' => [
                'name' => 'get_weather',
                'description' => 'Get current weather for a location',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                            'description' => 'The city and country, e.g. San Francisco, CA',
                        ],
                        'unit' => [
                            'type' => 'string',
                            'enum' => ['celsius', 'fahrenheit'],
                            'description' => 'The temperature unit',
                        ],
                    ],
                    'required' => ['location'],
                ],
            ],
            'calculator_function' => [
                'name' => 'calculate',
                'description' => 'Perform mathematical calculations',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'expression' => [
                            'type' => 'string',
                            'description' => 'Mathematical expression to evaluate',
                        ],
                    ],
                    'required' => ['expression'],
                ],
            ],
        ];
    }
}
