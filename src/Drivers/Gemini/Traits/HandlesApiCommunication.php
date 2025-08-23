<?php

namespace JTD\LaravelAI\Drivers\Gemini\Traits;

use Illuminate\Http\Client\Response;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;

/**
 * Handles API Communication with Google Gemini
 *
 * Core API communication methods for sending messages,
 * parsing responses, and handling API routing for Gemini.
 */
trait HandlesApiCommunication
{
    /**
     * Actually send the message to the provider.
     */
    protected function doSendMessage(array $messages, array $options): AIResponse
    {
        $startTime = microtime(true);

        // Format messages for Gemini API
        $formattedMessages = [];
        foreach ($messages as $message) {
            if ($message instanceof \JTD\LaravelAI\Models\AIMessage) {
                $formattedMessages[] = $this->formatSingleMessageForGemini($message);
            } else {
                // Already formatted
                $formattedMessages[] = $message;
            }
        }

        // Use Gemini generateContent API
        return $this->sendMessageWithGenerateContentAPI($formattedMessages, $options, $startTime);
    }

    /**
     * Send message using Gemini generateContent API.
     */
    protected function sendMessageWithGenerateContentAPI(array $messages, array $options, float $startTime): AIResponse
    {
        // Prepare API parameters
        $apiParams = $this->prepareApiParameters($messages, $options);

        // Make the API call with retry logic
        $response = $this->executeWithRetry(function () use ($apiParams, $options) {
            return $this->makeApiRequest($apiParams, $options);
        }, $options);

        // Calculate response time
        $responseTime = (microtime(true) - $startTime) * 1000;

        // Parse and format the response
        return $this->parseResponse($response, $responseTime, $options);
    }

    /**
     * Make the actual API request to Gemini.
     */
    protected function makeApiRequest(array $params, array $options): Response
    {
        $model = $options['model'] ?? $this->getCurrentModel();
        $endpoint = $this->buildEndpoint($model);

        return $this->http
            ->withHeaders($this->getRequestHeaders())
            ->timeout($this->config['timeout'] ?? 30)
            ->post($endpoint, $params);
    }

    /**
     * Build the API endpoint URL.
     */
    protected function buildEndpoint(string $model): string
    {
        $baseUrl = rtrim($this->config['base_url'], '/');
        $apiKey = $this->config['api_key'];

        return "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";
    }

    /**
     * Get request headers for API calls.
     */
    protected function getRequestHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'User-Agent' => 'JTD-Laravel-AI-Gemini/1.0',
        ];
    }

    /**
     * Parse Gemini API response.
     */
    protected function parseResponse(Response $response, float $responseTime, array $options): AIResponse
    {
        $data = $response->json();

        if (! $response->successful()) {
            $this->handleApiResponseError($response, $data);
        }

        $candidates = $data['candidates'] ?? [];
        if (empty($candidates)) {
            throw new \RuntimeException('No candidates returned from Gemini API');
        }

        $candidate = $candidates[0];
        $content = $this->extractContentFromCandidate($candidate);
        $finishReason = $this->mapFinishReason($candidate['finishReason'] ?? 'STOP');

        // Extract token usage
        $tokenUsage = $this->extractTokenUsage($data);

        // Extract safety ratings if present
        $safetyRatings = $candidate['safetyRatings'] ?? [];

        // Extract function calls if present
        $functionCalls = $this->extractFunctionCallsFromCandidate($candidate);

        return new AIResponse(
            $content,
            $tokenUsage,
            $options['model'] ?? $this->getCurrentModel(),
            $this->providerName,
            AIMessage::ROLE_ASSISTANT,
            $finishReason,
            $functionCalls,
            null, // tool calls (same as function calls for Gemini)
            $responseTime,
            [
                'safety_ratings' => $safetyRatings,
                'candidate_count' => count($candidates),
            ]
        );
    }

    /**
     * Extract content from Gemini candidate.
     */
    protected function extractContentFromCandidate(array $candidate): string
    {
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        $textParts = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textParts[] = $part['text'];
            }
        }

        return implode('', $textParts);
    }

    /**
     * Extract function calls from candidate.
     */
    protected function extractFunctionCallsFromCandidate(array $candidate): ?array
    {
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        $functionCalls = [];
        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $functionCall = $part['functionCall'];
                $functionCalls[] = [
                    'name' => $functionCall['name'],
                    'args' => $functionCall['args'] ?? [],
                    'id' => $functionCall['id'] ?? uniqid('func_'),
                ];
            }
        }

        // Return single function call for backward compatibility, or array for multiple
        if (empty($functionCalls)) {
            return null;
        } elseif (count($functionCalls) === 1) {
            return $functionCalls[0];
        } else {
            return $functionCalls;
        }
    }

    /**
     * Extract token usage from response.
     */
    protected function extractTokenUsage(array $data): TokenUsage
    {
        $usageMetadata = $data['usageMetadata'] ?? [];

        return new TokenUsage(
            $usageMetadata['promptTokenCount'] ?? 0,
            $usageMetadata['candidatesTokenCount'] ?? 0,
            $usageMetadata['totalTokenCount'] ?? 0
        );
    }

    /**
     * Map Gemini finish reason to standard format.
     */
    protected function mapFinishReason(string $geminiReason): string
    {
        $mapping = [
            'STOP' => 'stop',
            'MAX_TOKENS' => 'length',
            'SAFETY' => 'content_filter',
            'RECITATION' => 'content_filter',
            'OTHER' => 'stop',
        ];

        return $mapping[$geminiReason] ?? 'stop';
    }

    /**
     * Build conversation messages from input.
     */
    protected function buildConversationMessages($message, array $options): array
    {
        $contents = [];

        // Add conversation history if provided
        if (isset($options['conversation_history']) && is_array($options['conversation_history'])) {
            foreach ($options['conversation_history'] as $historyMessage) {
                $contents[] = $this->formatSingleMessageForGemini($historyMessage);
            }
        }

        // Add the current message(s)
        $currentMessages = $this->formatMessages($message);
        $contents = array_merge($contents, $currentMessages);

        return $contents;
    }

    /**
     * Format messages for Gemini API consumption.
     */
    protected function formatMessages($message): array
    {
        if ($message instanceof AIMessage) {
            return [$this->formatSingleMessageForGemini($message)];
        }

        if (is_array($message)) {
            return array_map([$this, 'formatSingleMessageForGemini'], $message);
        }

        if (is_string($message)) {
            return [[
                'role' => 'user',
                'parts' => [['text' => $message]],
            ]];
        }

        throw new \InvalidArgumentException('Message must be a string, AIMessage, or array of AIMessages');
    }

    /**
     * Format a single message for Gemini API consumption.
     */
    protected function formatSingleMessageForGemini(AIMessage $message): array
    {
        $role = $this->mapRoleForGemini($message->role);
        $parts = [];

        // Add text content
        if (! empty($message->content)) {
            $parts[] = ['text' => $message->content];
        }

        // Add image attachments if present (multimodal support)
        if (! empty($message->attachments)) {
            foreach ($message->attachments as $attachment) {
                if ($attachment['type'] === 'image') {
                    $parts[] = [
                        'inlineData' => [
                            'mimeType' => $attachment['mime_type'],
                            'data' => base64_encode($attachment['data']),
                        ],
                    ];
                }
            }
        }

        return [
            'role' => $role,
            'parts' => $parts,
        ];
    }

    /**
     * Map AI message role to Gemini role.
     */
    protected function mapRoleForGemini(string $role): string
    {
        $mapping = [
            'user' => 'user',
            'assistant' => 'model',
            'system' => 'user', // Gemini doesn't have system role, treat as user
        ];

        return $mapping[$role] ?? 'user';
    }

    /**
     * Prepare API parameters for Gemini generateContent API.
     */
    protected function prepareApiParameters(array $messages, array $options): array
    {
        $params = [
            'contents' => $messages,
        ];

        // Add generation configuration
        $generationConfig = [];

        if (isset($options['temperature'])) {
            $generationConfig['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $generationConfig['maxOutputTokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['top_p'])) {
            $generationConfig['topP'] = (float) $options['top_p'];
        }

        if (isset($options['top_k'])) {
            $generationConfig['topK'] = (int) $options['top_k'];
        }

        if (! empty($generationConfig)) {
            $params['generationConfig'] = $generationConfig;
        }

        // Add safety settings
        $safetySettings = $this->prepareSafetySettings($options);
        if (! empty($safetySettings)) {
            $params['safetySettings'] = $safetySettings;
        }

        return $params;
    }

    /**
     * Handle API errors from Gemini response.
     */
    protected function handleApiResponseError(Response $response, array $data): void
    {
        $error = $data['error'] ?? [];
        $message = $error['message'] ?? 'Unknown Gemini API error';
        $code = $error['code'] ?? $response->status();

        // Map to appropriate exception types
        if ($code === 401 || $code === 403) {
            throw new \JTD\LaravelAI\Exceptions\InvalidCredentialsException($message);
        }

        if ($code === 429) {
            throw new \JTD\LaravelAI\Exceptions\RateLimitException($message);
        }

        if ($code === 400) {
            throw new \JTD\LaravelAI\Exceptions\InvalidRequestException($message);
        }

        throw new \JTD\LaravelAI\Exceptions\ProviderException(
            "Gemini API error: {$message}",
            $code
        );
    }
}
