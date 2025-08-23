<?php

namespace JTD\LaravelAI\Drivers\XAI\Traits;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Models\ToolCall;
use JTD\LaravelAI\Models\ToolFunction;

/**
 * Handles API Communication with xAI
 *
 * Core API communication methods for sending messages,
 * parsing responses, and handling API routing.
 */
trait HandlesApiCommunication
{
    /**
     * Actually send the message to xAI API.
     */
    protected function doSendMessage(array $messages, array $options): AIResponse
    {
        $startTime = microtime(true);

        // Format messages for xAI API
        $formattedMessages = [];
        foreach ($messages as $message) {
            if ($message instanceof AIMessage) {
                $formattedMessages[] = $this->formatSingleMessage($message);
            } else {
                // Already formatted
                $formattedMessages[] = $message;
            }
        }

        // Use xAI Chat Completions API (OpenAI-compatible)
        return $this->sendMessageWithChatAPI($formattedMessages, $options, $startTime);
    }

    /**
     * Send message using xAI Chat Completions API.
     */
    protected function sendMessageWithChatAPI(array $messages, array $options, float $startTime): AIResponse
    {
        // Prepare API parameters
        $params = $this->prepareApiParameters($messages, $options);

        // Make API request
        $response = $this->client->post($this->config['base_url'] . '/chat/completions', $params);

        // Calculate response time
        $responseTime = (microtime(true) - $startTime) * 1000;

        // Handle errors
        if (! $response->successful()) {
            $this->handleApiError($response, $params);
        }

        // Parse and return response
        return $this->parseResponse($response->json(), $responseTime, $options);
    }

    /**
     * Parse xAI Chat API response.
     */
    protected function parseResponse($response, float $responseTime, array $options): AIResponse
    {
        // Extract basic response data
        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $usage = $response['usage'] ?? [];

        // Extract content
        $content = $message['content'] ?? '';

        // Extract tool calls if present
        $toolCalls = [];
        if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $toolCall) {
                $toolCalls[] = new ToolCall(
                    id: $toolCall['id'] ?? '',
                    type: $toolCall['type'] ?? 'function',
                    function: new ToolFunction(
                        name: $toolCall['function']['name'] ?? '',
                        arguments: $toolCall['function']['arguments'] ?? '{}'
                    )
                );
            }
        }

        // Create token usage
        $tokenUsage = new TokenUsage(
            inputTokens: $usage['prompt_tokens'] ?? 0,
            outputTokens: $usage['completion_tokens'] ?? 0,
            totalTokens: $usage['total_tokens'] ?? 0
        );

        // Calculate cost
        $model = $options['model'] ?? $this->getDefaultModel();
        $costData = $this->calculateCost($tokenUsage, $model);
        $cost = $costData['total_cost'] ?? 0.0;

        // Create AI response
        return new AIResponse(
            content: $content,
            tokenUsage: $tokenUsage,
            model: $response['model'] ?? $model,
            provider: $this->providerName,
            finishReason: $choice['finish_reason'] ?? null,
            toolCalls: $toolCalls,
            responseTimeMs: $responseTime,
            costBreakdown: $costData,
            metadata: [
                'id' => $response['id'] ?? null,
                'object' => $response['object'] ?? null,
                'created' => $response['created'] ?? null,
                'system_fingerprint' => $response['system_fingerprint'] ?? null,
            ]
        );
    }

    /**
     * Format a single message for xAI API consumption.
     */
    protected function formatSingleMessage(AIMessage $message): array
    {
        $formatted = [
            'role' => $this->mapRole($message->role),
            'content' => $message->content,
        ];

        // Add tool call ID for tool messages
        if ($message->role === 'tool' && $message->toolCallId) {
            $formatted['tool_call_id'] = $message->toolCallId;
        }

        // Add tool calls for assistant messages
        if ($message->role === 'assistant' && ! empty($message->toolCalls)) {
            $formatted['tool_calls'] = [];
            foreach ($message->toolCalls as $toolCall) {
                $formatted['tool_calls'][] = [
                    'id' => $toolCall->id,
                    'type' => $toolCall->type,
                    'function' => [
                        'name' => $toolCall->function->name,
                        'arguments' => $toolCall->function->arguments,
                    ],
                ];
            }
        }

        // Add name if present
        if ($message->name) {
            $formatted['name'] = $message->name;
        }

        return $formatted;
    }

    /**
     * Map internal roles to xAI API roles.
     */
    protected function mapRole(string $role): string
    {
        return match ($role) {
            'user' => 'user',
            'assistant' => 'assistant',
            'system' => 'system',
            'tool' => 'tool',
            default => 'user',
        };
    }

    /**
     * Prepare API parameters for Chat API.
     */
    protected function prepareApiParameters(array $messages, array $options): array
    {
        $params = [
            'model' => $options['model'] ?? $this->getDefaultModel(),
            'messages' => $messages,
        ];

        // Add optional parameters
        if (isset($options['temperature'])) {
            $params['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $params['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['top_p'])) {
            $params['top_p'] = (float) $options['top_p'];
        }

        if (isset($options['frequency_penalty'])) {
            $params['frequency_penalty'] = (float) $options['frequency_penalty'];
        }

        if (isset($options['presence_penalty'])) {
            $params['presence_penalty'] = (float) $options['presence_penalty'];
        }

        if (isset($options['stop'])) {
            $params['stop'] = $options['stop'];
        }

        if (isset($options['stream'])) {
            $params['stream'] = (bool) $options['stream'];
        }

        // Add function calling parameters
        if (isset($options['functions']) && ! empty($options['functions'])) {
            $params['tools'] = [];
            foreach ($options['functions'] as $function) {
                $params['tools'][] = [
                    'type' => 'function',
                    'function' => $function,
                ];
            }
        }

        if (isset($options['tool_choice'])) {
            $params['tool_choice'] = $options['tool_choice'];
        }

        if (isset($options['parallel_tool_calls'])) {
            $params['parallel_tool_calls'] = (bool) $options['parallel_tool_calls'];
        }

        // Add response format if specified
        if (isset($options['response_format'])) {
            $params['response_format'] = $options['response_format'];
        }

        // Add seed for reproducible outputs
        if (isset($options['seed'])) {
            $params['seed'] = (int) $options['seed'];
        }

        // Add user identifier
        if (isset($options['user'])) {
            $params['user'] = $options['user'];
        }

        return $params;
    }

    /**
     * Trim conversation context if needed to fit within model limits.
     */
    protected function trimContextIfNeeded(array $messages, array $options): array
    {
        $model = $options['model'] ?? $this->getDefaultModel();
        $maxTokens = $this->getModelContextLength($model);

        // Estimate current token count
        $estimatedTokens = $this->estimateTokenCount($messages);

        // If within limits, return as-is
        if ($estimatedTokens <= $maxTokens * 0.8) { // Use 80% of limit for safety
            return $messages;
        }

        // Keep system message and recent messages
        $trimmedMessages = [];
        $systemMessages = array_filter($messages, fn ($msg) => $msg['role'] === 'system');
        $otherMessages = array_filter($messages, fn ($msg) => $msg['role'] !== 'system');

        // Add system messages first
        $trimmedMessages = array_merge($trimmedMessages, $systemMessages);

        // Add recent messages until we approach the limit
        $recentMessages = array_reverse($otherMessages);
        $currentTokens = $this->estimateTokenCount($trimmedMessages);

        foreach ($recentMessages as $message) {
            $messageTokens = $this->estimateTokenCount([$message]);
            if ($currentTokens + $messageTokens > $maxTokens * 0.8) {
                break;
            }
            array_unshift($trimmedMessages, $message);
            $currentTokens += $messageTokens;
        }

        return $trimmedMessages;
    }

    /**
     * Get model context length.
     */
    protected function getModelContextLength(string $model): int
    {
        return match ($model) {
            'grok-beta', 'grok-2', 'grok-2-mini', 'grok-2-1212', 'grok-2-vision-1212' => 131072,
            default => 131072, // Default for Grok models
        };
    }

    /**
     * Estimate token count for messages.
     */
    protected function estimateTokenCount(array $messages): int
    {
        $totalTokens = 0;

        foreach ($messages as $message) {
            // Rough estimation: ~4 characters per token
            $content = is_array($message) ? ($message['content'] ?? '') : $message;
            $totalTokens += ceil(strlen($content) / 4);

            // Add overhead for message structure
            $totalTokens += 10;
        }

        return $totalTokens;
    }

    /**
     * Update conversation context if needed.
     */
    protected function updateConversationContext($originalMessage, AIResponse $response, array $options): void
    {
        // Implementation for conversation context management
        // This could be used for maintaining conversation state
        // For now, this is a placeholder
    }
}
