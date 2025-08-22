<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;

/**
 * Handles API Communication with OpenAI
 *
 * Core API communication methods for sending messages,
 * parsing responses, and handling API routing.
 */
trait HandlesApiCommunication
{
    /**
     * Actually send the message to the provider.
     */
    protected function doSendMessage(array $messages, array $options): AIResponse
    {
        $startTime = microtime(true);

        // Check if we should use the new Responses API for function calling
        if ($this->shouldUseResponsesAPI($options)) {
            return $this->sendMessageWithResponsesAPI($messages, $options, $startTime);
        }

        // Use traditional Chat API
        return $this->sendMessageWithChatAPI($messages, $options, $startTime);
    }

    /**
     * Send message using traditional Chat API.
     */
    protected function sendMessageWithChatAPI(array $messages, array $options, float $startTime): AIResponse
    {
        // Prepare API parameters
        $apiParams = $this->prepareApiParameters($messages, $options);

        // Make the API call with retry logic
        $response = $this->executeWithRetry(function () use ($apiParams) {
            return $this->client->chat()->create($apiParams);
        }, $options);

        // Calculate response time
        $responseTime = (microtime(true) - $startTime) * 1000;

        // Parse and format the response
        return $this->parseResponse($response, $responseTime, $options);
    }

    /**
     * Send message using the new Responses API.
     */
    protected function sendMessageWithResponsesAPI(array $messages, array $options, float $startTime): AIResponse
    {
        // Prepare parameters for Responses API
        $apiParams = $this->prepareResponsesAPIParameters($messages, $options);

        // Make the API call with retry logic
        $response = $this->executeWithRetry(function () use ($apiParams) {
            return $this->client->responses()->create($apiParams);
        }, $options);

        // Calculate response time
        $responseTime = (microtime(true) - $startTime) * 1000;

        // Parse and format the response
        return $this->parseResponsesAPIResponse($response, $responseTime, $options);
    }

    /**
     * Parse OpenAI Chat API response.
     */
    protected function parseResponse($response, float $responseTime, array $options): AIResponse
    {
        $message = $response->choices[0]->message ?? null;

        if (!$message) {
            throw new \RuntimeException('Invalid response format from OpenAI API');
        }

        $content = $message->content ?? '';
        $finishReason = $response->choices[0]->finishReason ?? 'stop';

        // Extract token usage
        $usage = $response->usage ?? null;
        $tokenUsage = new TokenUsage(
            $usage->promptTokens ?? 0,
            $usage->completionTokens ?? 0,
            $usage->totalTokens ?? 0
        );

        // Extract function calls if present
        $functionCalls = isset($message->functionCall) ? (array) $message->functionCall : null;
        $toolCalls = isset($message->toolCalls) ? array_map(fn($call) => (array) $call, $message->toolCalls) : null;

        return new AIResponse(
            $content,
            $tokenUsage,
            $response->model,
            $this->providerName,
            AIMessage::ROLE_ASSISTANT,
            $finishReason,
            $functionCalls,
            $toolCalls,
            $responseTime
        );
    }

    /**
     * Build conversation messages from input.
     */
    protected function buildConversationMessages($message, array $options): array
    {
        $messages = [];

        // Add system message if provided
        if (isset($options['system_message'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $options['system_message'],
            ];
        }

        // Add conversation history if provided
        if (isset($options['conversation_history']) && is_array($options['conversation_history'])) {
            foreach ($options['conversation_history'] as $historyMessage) {
                $messages[] = $this->formatSingleMessage($historyMessage);
            }
        }

        // Add the current message(s)
        $currentMessages = $this->formatMessages($message);
        $messages = array_merge($messages, $currentMessages);

        // Trim context if needed to fit within model limits
        return $this->trimContextIfNeeded($messages, $options);
    }

    /**
     * Format messages for API consumption.
     */
    protected function formatMessages($message): array
    {
        if ($message instanceof AIMessage) {
            return [$this->formatSingleMessage($message)];
        }

        if (is_array($message)) {
            return array_map([$this, 'formatSingleMessage'], $message);
        }

        if (is_string($message)) {
            return [[
                'role' => 'user',
                'content' => $message,
            ]];
        }

        throw new \InvalidArgumentException('Message must be a string, AIMessage, or array of AIMessages');
    }

    /**
     * Format a single message for API consumption.
     */
    protected function formatSingleMessage(AIMessage $message): array
    {
        $formatted = [
            'role' => $message->role,
            'content' => $message->content,
        ];

        // Add function call information if present
        if ($message->functionCalls) {
            $formatted['function_call'] = $message->functionCalls;
        }

        // Add tool calls if present
        if ($message->toolCalls) {
            $formatted['tool_calls'] = $message->toolCalls;
        }

        // Add tool call ID for tool responses
        if (property_exists($message, 'toolCallId') && $message->toolCallId) {
            $formatted['tool_call_id'] = $message->toolCallId;
        }

        return $formatted;
    }

    /**
     * Prepare API parameters for Chat API.
     */
    protected function prepareApiParameters(array $messages, array $options): array
    {
        $params = [
            'model' => $options['model'] ?? $this->defaultModel,
            'messages' => $messages,
        ];

        // Add optional parameters
        $optionalParams = [
            'temperature', 'max_tokens', 'top_p', 'frequency_penalty',
            'presence_penalty', 'stop', 'stream', 'logit_bias', 'user'
        ];

        foreach ($optionalParams as $param) {
            if (isset($options[$param])) {
                $params[$param] = $options[$param];
            }
        }

        // Handle functions and tools
        if (isset($options['functions'])) {
            $params['functions'] = $this->formatFunctions($options['functions']);
        }

        if (isset($options['tools'])) {
            $params['tools'] = $this->formatTools($options['tools']);
        }

        // Handle function/tool choice
        if (isset($options['function_call'])) {
            $params['function_call'] = $options['function_call'];
        }

        if (isset($options['tool_choice'])) {
            $params['tool_choice'] = $options['tool_choice'];
        }

        // Handle response format (JSON mode)
        if (isset($options['response_format'])) {
            $params['response_format'] = $options['response_format'];
        }

        return $params;
    }

    /**
     * Trim conversation context if needed to fit within model limits.
     */
    protected function trimContextIfNeeded(array $messages, array $options): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $maxTokens = $options['max_tokens'] ?? 4000;
        $contextLength = $this->getModelContextLength($model);

        // Reserve tokens for the response
        $availableTokens = $contextLength - $maxTokens - 100; // 100 token buffer

        if ($availableTokens <= 0) {
            return $messages; // Can't trim further
        }

        // Estimate current token usage
        $currentTokens = 0;
        foreach ($messages as $message) {
            $currentTokens += $this->estimateStringTokens($message['content'] ?? '');
        }

        // If we're within limits, return as-is
        if ($currentTokens <= $availableTokens) {
            return $messages;
        }

        // Keep system message and recent messages, trim from the middle
        $systemMessages = [];
        $recentMessages = [];
        $middleMessages = [];

        foreach ($messages as $index => $message) {
            if ($message['role'] === 'system') {
                $systemMessages[] = $message;
            } elseif ($index >= count($messages) - 4) { // Keep last 4 messages
                $recentMessages[] = $message;
            } else {
                $middleMessages[] = $message;
            }
        }

        // Start with system and recent messages
        $trimmedMessages = array_merge($systemMessages, $recentMessages);
        $trimmedTokens = 0;

        foreach ($trimmedMessages as $message) {
            $trimmedTokens += $this->estimateStringTokens($message['content'] ?? '');
        }

        // Add middle messages if there's room
        foreach (array_reverse($middleMessages) as $message) {
            $messageTokens = $this->estimateStringTokens($message['content'] ?? '');
            if ($trimmedTokens + $messageTokens <= $availableTokens) {
                array_splice($trimmedMessages, -count($recentMessages), 0, [$message]);
                $trimmedTokens += $messageTokens;
            } else {
                break;
            }
        }

        return $trimmedMessages;
    }

    /**
     * Update conversation context if needed.
     */
    protected function updateConversationContext($originalMessage, AIResponse $response, array $options): void
    {
        // This method can be extended for conversation persistence
        // Currently, conversation context is handled per-request

        if (isset($options['conversation_id'])) {
            // Fire conversation updated event
            $this->fireConversationUpdatedEvent(
                $originalMessage,
                $response,
                $options['conversation_id'],
                $options['user_id'] ?? null,
                $options
            );
        }
    }
}
