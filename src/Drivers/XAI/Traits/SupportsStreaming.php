<?php

namespace JTD\LaravelAI\Drivers\XAI\Traits;

use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Models\ToolCall;
use JTD\LaravelAI\Models\ToolFunction;

/**
 * Supports Streaming for xAI
 *
 * Handles streaming responses from xAI API using Server-Sent Events (SSE).
 * Provides real-time response streaming with proper chunk parsing and
 * token usage tracking.
 */
trait SupportsStreaming
{
    /**
     * Actually send streaming message to xAI API.
     */
    protected function doSendStreamingMessage(array $messages, array $options): \Generator
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

        // Enable streaming in options
        $options['stream'] = true;

        // Prepare API parameters
        $params = $this->prepareApiParameters($formattedMessages, $options);

        // Make streaming request
        $response = $this->client->post($this->config['base_url'] . '/chat/completions', $params);

        // Handle errors
        if (! $response->successful()) {
            $this->handleApiError($response, $params);
        }

        // Parse streaming response
        yield from $this->parseStreamingResponse($response->body(), $startTime, $options);
    }

    /**
     * Parse streaming response from xAI API.
     */
    protected function parseStreamingResponse(string $body, float $startTime, array $options): \Generator
    {
        $lines = explode("\n", $body);
        $buffer = '';
        $totalTokens = 0;
        $inputTokens = 0;
        $outputTokens = 0;
        $model = $options['model'] ?? $this->getDefaultModel();
        $fullContent = '';
        $toolCalls = [];
        $finishReason = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || ! str_starts_with($line, 'data: ')) {
                continue;
            }

            // Extract JSON data
            $data = substr($line, 6); // Remove 'data: ' prefix

            // Check for end of stream
            if ($data === '[DONE]') {
                break;
            }

            try {
                $chunk = json_decode($data, true);
                if (! $chunk) {
                    continue;
                }

                // Extract choice data
                $choice = $chunk['choices'][0] ?? [];
                $delta = $choice['delta'] ?? [];
                $usage = $chunk['usage'] ?? [];

                // Extract content delta
                $content = $delta['content'] ?? '';
                $fullContent .= $content;

                // Extract tool calls if present
                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $index => $toolCallDelta) {
                        if (! isset($toolCalls[$index])) {
                            $toolCalls[$index] = [
                                'id' => $toolCallDelta['id'] ?? '',
                                'type' => $toolCallDelta['type'] ?? 'function',
                                'function' => [
                                    'name' => '',
                                    'arguments' => '',
                                ],
                            ];
                        }

                        if (isset($toolCallDelta['function']['name'])) {
                            $toolCalls[$index]['function']['name'] .= $toolCallDelta['function']['name'];
                        }

                        if (isset($toolCallDelta['function']['arguments'])) {
                            $toolCalls[$index]['function']['arguments'] .= $toolCallDelta['function']['arguments'];
                        }
                    }
                }

                // Update token usage if provided
                if (! empty($usage)) {
                    $inputTokens = $usage['prompt_tokens'] ?? $inputTokens;
                    $outputTokens = $usage['completion_tokens'] ?? $outputTokens;
                    $totalTokens = $usage['total_tokens'] ?? $totalTokens;
                }

                // Update finish reason
                if (isset($choice['finish_reason'])) {
                    $finishReason = $choice['finish_reason'];
                }

                // Create token usage
                $tokenUsage = new TokenUsage(
                    input_tokens: $inputTokens,
                    output_tokens: $outputTokens,
                    totalTokens: $totalTokens
                );

                // Calculate cost
                $costData = $this->calculateCost($tokenUsage, $model);
                $cost = $costData['total_cost'] ?? 0.0;

                // Calculate response time
                $responseTime = (microtime(true) - $startTime) * 1000;

                // Convert tool calls to ToolCall objects
                $toolCallObjects = [];
                foreach ($toolCalls as $toolCall) {
                    $toolCallObjects[] = new ToolCall(
                        id: $toolCall['id'],
                        type: $toolCall['type'],
                        function: new ToolFunction(
                            name: $toolCall['function']['name'],
                            arguments: $toolCall['function']['arguments']
                        )
                    );
                }

                // Create streaming response chunk
                $aiResponse = new AIResponse(
                    content: $content, // Delta content for this chunk
                    tokenUsage: $tokenUsage,
                    model: $chunk['model'] ?? $model,
                    provider: $this->providerName,
                    finishReason: $finishReason ?? 'stop',
                    toolCalls: $toolCallObjects,
                    responseTimeMs: $responseTime,
                    costBreakdown: $costData,
                    metadata: [
                        'id' => $chunk['id'] ?? null,
                        'object' => $chunk['object'] ?? null,
                        'created' => $chunk['created'] ?? null,
                        'system_fingerprint' => $chunk['system_fingerprint'] ?? null,
                        'is_streaming' => true,
                        'full_content' => $fullContent, // Full content so far
                    ]
                );

                yield $aiResponse;

                // Break if we've reached the end
                if ($finishReason) {
                    break;
                }
            } catch (\JsonException $e) {
                Log::warning('Failed to parse streaming chunk', [
                    'provider' => $this->providerName,
                    'data' => $data,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }
    }

    /**
     * Create a streaming HTTP request.
     */
    protected function createStreamingRequest(array $params): \Illuminate\Http\Client\Response
    {
        return $this->client
            ->withOptions([
                'stream' => true,
                'read_timeout' => $this->config['timeout'] ?? 30,
            ])
            ->post($this->config['base_url'] . '/chat/completions', $params);
    }

    /**
     * Handle streaming errors.
     */
    protected function handleStreamingError(\Exception $e, array $params): void
    {
        Log::error('Streaming request failed', [
            'provider' => $this->providerName,
            'error' => $e->getMessage(),
            'model' => $params['model'] ?? 'unknown',
            'message_count' => count($params['messages'] ?? []),
        ]);

        // Re-throw with appropriate exception type
        $this->handleApiError(null, $params, $e);
    }

    /**
     * Validate streaming parameters.
     */
    protected function validateStreamingParams(array $options): void
    {
        // Ensure model supports streaming
        $model = $options['model'] ?? $this->getDefaultModel();
        if (! $this->modelSupportsStreaming($model)) {
            throw new \InvalidArgumentException("Model {$model} does not support streaming");
        }

        // Validate streaming-specific options
        if (isset($options['n']) && $options['n'] > 1) {
            throw new \InvalidArgumentException('Streaming does not support n > 1');
        }
    }

    /**
     * Check if model supports streaming.
     */
    protected function modelSupportsStreaming(string $model): bool
    {
        // All current xAI models support streaming
        return in_array($model, [
            'grok-beta',
            'grok-2',
            'grok-2-mini',
            'grok-2-1212',
            'grok-2-vision-1212',
        ]);
    }

    /**
     * Get streaming configuration.
     */
    protected function getStreamingConfig(): array
    {
        return [
            'chunk_size' => 8192,
            'timeout' => $this->config['timeout'] ?? 30,
            'buffer_size' => 1024 * 1024, // 1MB buffer
            'max_chunks' => 10000,
        ];
    }

    /**
     * Process streaming chunk for debugging.
     */
    protected function debugStreamingChunk(array $chunk, int $chunkIndex): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug('Streaming chunk received', [
            'provider' => $this->providerName,
            'chunk_index' => $chunkIndex,
            'chunk_id' => $chunk['id'] ?? null,
            'model' => $chunk['model'] ?? null,
            'choices_count' => count($chunk['choices'] ?? []),
            'has_usage' => isset($chunk['usage']),
        ]);
    }

    /**
     * Calculate streaming metrics.
     */
    protected function calculateStreamingMetrics(float $startTime, int $chunkCount, int $totalTokens): array
    {
        $totalTime = (microtime(true) - $startTime) * 1000;

        return [
            'total_time_ms' => $totalTime,
            'chunk_count' => $chunkCount,
            'total_tokens' => $totalTokens,
            'tokens_per_second' => $totalTokens > 0 ? ($totalTokens / ($totalTime / 1000)) : 0,
            'chunks_per_second' => $chunkCount > 0 ? ($chunkCount / ($totalTime / 1000)) : 0,
            'average_chunk_time_ms' => $chunkCount > 0 ? ($totalTime / $chunkCount) : 0,
        ];
    }
}
