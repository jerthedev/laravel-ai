<?php

namespace JTD\LaravelAI\Drivers\Gemini\Traits;

use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\FunctionCall;
use JTD\LaravelAI\Models\FunctionDefinition;

/**
 * Handles function calling for Gemini API.
 */
trait HandlesFunctionCalling
{
    /**
     * Send a message with function calling capabilities.
     */
    public function sendMessageWithFunctions(
        AIMessage $message,
        array $functions,
        array $options = []
    ): AIResponse {
        if (! $this->supportsFunctionCalling()) {
            throw new \JTD\LaravelAI\Exceptions\UnsupportedFeatureException(
                'Function calling is not supported for the current model: ' . $this->getCurrentModel()
            );
        }

        // Add functions to the request payload
        $options['tools'] = $this->formatFunctionsForGemini($functions);
        $options['tool_config'] = $this->buildToolConfig($options);

        return $this->sendMessage($message, $options);
    }

    /**
     * Format function definitions for Gemini API.
     */
    protected function formatFunctionsForGemini(array $functions): array
    {
        $tools = [];

        foreach ($functions as $function) {
            if ($function instanceof FunctionDefinition) {
                $tools[] = [
                    'function_declarations' => [$this->formatFunctionDefinition($function)],
                ];
            } elseif (is_array($function)) {
                $tools[] = [
                    'function_declarations' => [$function],
                ];
            }
        }

        return $tools;
    }

    /**
     * Format a single function definition.
     */
    protected function formatFunctionDefinition(FunctionDefinition $function): array
    {
        return [
            'name' => $function->name,
            'description' => $function->description,
            'parameters' => [
                'type' => 'object',
                'properties' => $function->parameters,
                'required' => $function->required ?? [],
            ],
        ];
    }

    /**
     * Build tool configuration for function calling.
     */
    protected function buildToolConfig(array $options): array
    {
        $config = [];

        // Set function calling mode
        if (isset($options['function_calling_mode'])) {
            $config['function_calling_config'] = [
                'mode' => strtoupper($options['function_calling_mode']),
            ];

            // Add allowed function names if specified
            if (isset($options['allowed_functions'])) {
                $config['function_calling_config']['allowed_function_names'] = $options['allowed_functions'];
            }
        }

        return $config;
    }

    /**
     * Extract function calls from Gemini response.
     */
    protected function extractFunctionCalls(array $responseData): array
    {
        $functionCalls = [];

        if (! isset($responseData['candidates'][0]['content']['parts'])) {
            return $functionCalls;
        }

        foreach ($responseData['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['functionCall'])) {
                $functionCalls[] = new FunctionCall(
                    name: $part['functionCall']['name'],
                    arguments: $part['functionCall']['args'] ?? [],
                    id: $part['functionCall']['id'] ?? uniqid('func_')
                );
            }
        }

        return $functionCalls;
    }

    /**
     * Execute function calls and return results.
     */
    public function executeFunctionCalls(array $functionCalls, array $availableFunctions): array
    {
        $results = [];

        foreach ($functionCalls as $call) {
            if (! isset($availableFunctions[$call->name])) {
                $results[] = [
                    'name' => $call->name,
                    'id' => $call->id,
                    'error' => "Function '{$call->name}' not found",
                ];

                continue;
            }

            try {
                $function = $availableFunctions[$call->name];
                $result = $this->callFunction($function, $call->arguments);

                $results[] = [
                    'name' => $call->name,
                    'id' => $call->id,
                    'result' => $result,
                ];

                Log::info('Function executed successfully', [
                    'function' => $call->name,
                    'arguments' => $call->arguments,
                    'result_type' => gettype($result),
                ]);
            } catch (\Exception $e) {
                $results[] = [
                    'name' => $call->name,
                    'id' => $call->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Function execution failed', [
                    'function' => $call->name,
                    'arguments' => $call->arguments,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Call a function with given arguments.
     */
    protected function callFunction($function, array $arguments)
    {
        if (is_callable($function)) {
            return call_user_func($function, ...$arguments);
        }

        if (is_array($function) && isset($function['callback'])) {
            return call_user_func($function['callback'], ...$arguments);
        }

        throw new \InvalidArgumentException('Invalid function definition');
    }

    /**
     * Create function response message for continuing conversation.
     */
    public function createFunctionResponseMessage(array $functionResults): AIMessage
    {
        $parts = [];

        foreach ($functionResults as $result) {
            $parts[] = [
                'function_response' => [
                    'name' => $result['name'],
                    'response' => [
                        'result' => $result['result'] ?? null,
                        'error' => $result['error'] ?? null,
                    ],
                ],
            ];
        }

        return new AIMessage(
            role: 'user',
            content: '', // Function responses don't have text content
            contentType: AIMessage::CONTENT_TYPE_FUNCTION_RESPONSE,
            metadata: ['parts' => $parts]
        );
    }

    /**
     * Handle automatic function calling workflow.
     */
    public function handleAutomaticFunctionCalling(
        AIMessage $message,
        array $functions,
        array $options = []
    ): AIResponse {
        $maxIterations = $options['max_function_iterations'] ?? 5;
        $iteration = 0;
        $conversationHistory = [$message];

        while ($iteration < $maxIterations) {
            $response = $this->sendMessageWithFunctions(
                $conversationHistory[count($conversationHistory) - 1],
                $functions,
                $options
            );

            // Check if response contains function calls
            $functionCalls = $response->functionCalls ?? [];

            if (empty($functionCalls)) {
                // No more function calls, return final response
                return $response;
            }

            // Execute function calls
            $functionResults = $this->executeFunctionCalls($functionCalls, $functions);

            // Create function response message
            $functionResponseMessage = $this->createFunctionResponseMessage($functionResults);
            $conversationHistory[] = $functionResponseMessage;

            $iteration++;
        }

        throw new \JTD\LaravelAI\Exceptions\ProviderException(
            "Maximum function calling iterations ({$maxIterations}) exceeded"
        );
    }

    /**
     * Check if function calling is supported for the current model.
     */
    public function supportsFunctionCalling(): bool
    {
        $model = $this->getCurrentModel();

        return $this->getModelCapabilities($model)['function_calling'] ?? false;
    }

    /**
     * Get function calling configuration.
     */
    public function getFunctionCallingConfig(): array
    {
        return [
            'max_functions_per_request' => 20,
            'max_function_iterations' => 5,
            'supported_modes' => ['AUTO', 'ANY', 'NONE'],
            'parallel_calling' => true,
            'compositional_calling' => true,
        ];
    }

    /**
     * Validate function definitions.
     */
    protected function validateFunctionDefinitions(array $functions): void
    {
        foreach ($functions as $function) {
            if (is_array($function)) {
                $this->validateFunctionArray($function);
            } elseif ($function instanceof FunctionDefinition) {
                $this->validateFunctionDefinition($function);
            } else {
                throw new \InvalidArgumentException('Invalid function definition type');
            }
        }
    }

    /**
     * Validate function array format.
     */
    protected function validateFunctionArray(array $function): void
    {
        if (! isset($function['name'])) {
            throw new \InvalidArgumentException('Function must have a name');
        }

        if (! isset($function['description'])) {
            throw new \InvalidArgumentException('Function must have a description');
        }

        if (isset($function['parameters']) && ! is_array($function['parameters'])) {
            throw new \InvalidArgumentException('Function parameters must be an array');
        }
    }

    /**
     * Validate FunctionDefinition object.
     */
    protected function validateFunctionDefinition(FunctionDefinition $function): void
    {
        if (empty($function->name)) {
            throw new \InvalidArgumentException('Function must have a name');
        }

        if (empty($function->description)) {
            throw new \InvalidArgumentException('Function must have a description');
        }
    }

    /**
     * Create a function definition from a callable.
     */
    public function createFunctionFromCallable(callable $callable, string $name, string $description): FunctionDefinition
    {
        $reflection = new \ReflectionFunction($callable);
        $parameters = [];
        $required = [];

        foreach ($reflection->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType();

            $parameters[$paramName] = [
                'type' => $this->mapPhpTypeToJsonSchema($paramType),
                'description' => "Parameter {$paramName}",
            ];

            if (! $param->isOptional()) {
                $required[] = $paramName;
            }
        }

        return new FunctionDefinition(
            name: $name,
            description: $description,
            parameters: $parameters,
            required: $required
        );
    }

    /**
     * Map PHP types to JSON Schema types.
     */
    protected function mapPhpTypeToJsonSchema(?\ReflectionType $type): string
    {
        if (! $type) {
            return 'string';
        }

        $typeName = $type->getName();

        return match ($typeName) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }
}
