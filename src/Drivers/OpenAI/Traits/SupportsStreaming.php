<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;

/**
 * Supports Streaming Responses
 *
 * Handles streaming message delivery, chunk processing,
 * and real-time response generation.
 */
trait SupportsStreaming
{
    /**
     * Send a streaming message to OpenAI.
     */
    public function sendStreamingMessage($message, array $options = []): \Generator
    {
        // Use the original generator-based implementation for compatibility
        $messages = is_array($message) ? $message : [$message];
        $mergedOptions = array_merge($this->options, $options);

        $this->checkRateLimit();

        return $this->doSendStreamingMessage($messages, $mergedOptions);
    }

    /**
     * Send a streaming message with callback support.
     */
    public function sendStreamingMessageWithCallback($message, array $options = [], ?callable $callback = null): AIResponse
    {
        $messages = is_array($message) ? $message : [$message];
        $mergedOptions = array_merge($this->options, $options);

        $this->checkRateLimit();

        // Use the generator and process with callback
        $fullContent = '';
        $finalResponse = null;

        foreach ($this->doSendStreamingMessage($messages, $mergedOptions) as $chunk) {
            if ($chunk instanceof AIResponse) {
                // This is the final response
                $finalResponse = $chunk;
            } else {
                // This is a streaming chunk
                $fullContent .= $chunk;
                if ($callback) {
                    $callback($chunk);
                }
            }
        }

        return $finalResponse ?? new AIResponse(
            $fullContent,
            new TokenUsage(0, 0, 0),
            $mergedOptions['model'] ?? $this->defaultModel,
            $this->providerName,
            'assistant',
            'stop',
            null,
            null,
            0
        );
    }

    /**
     * Actually send the streaming message and return a generator.
     */
    protected function doSendStreamingMessage(array $messages, array $options): \Generator
    {
        try {
            $startTime = microtime(true);

            // Prepare API parameters with streaming enabled
            $apiParams = $this->prepareApiParameters($messages, $options);
            $apiParams['stream'] = true;

            // Make the streaming API call
            $stream = $this->client->chat()->createStreamed($apiParams);

            // Track streaming state
            $fullContent = '';
            $totalTokens = 0;
            $chunkCount = 0;
            $lastChunk = null;

            // Process each chunk
            foreach ($stream as $chunk) {
                $chunkCount++;
                $lastChunk = $chunk;

                // Parse the chunk
                $parsedChunk = $this->parseStreamChunk($chunk, $chunkCount, $startTime, $options);

                if ($parsedChunk) {
                    if ($parsedChunk->content) {
                        $fullContent .= $parsedChunk->content;
                    }

                    // Yield the parsed chunk as AIResponse
                    yield $parsedChunk;

                    // Update token count if available
                    if ($parsedChunk->tokenUsage && $parsedChunk->tokenUsage->totalTokens > 0) {
                        $totalTokens = $parsedChunk->tokenUsage->totalTokens;
                    }
                }
            }

            // Calculate final response time
            $responseTime = (microtime(true) - $startTime) * 1000;

            // Create final response
            $finalResponse = $this->createFinalStreamResponse(
                $fullContent,
                $totalTokens,
                $responseTime,
                $options,
                $lastChunk
            );

            // Fire events for background processing
            $this->fireEvents($messages[0] ?? AIMessage::user(''), $finalResponse, $options);

            // Yield final response
            yield $finalResponse;
        } catch (\Exception $e) {
            $this->handleApiError($e);
        }
    }

    /**
     * Parse a streaming chunk.
     */
    protected function parseStreamChunk($chunk, int $chunkIndex, float $startTime, array $options): ?AIResponse
    {
        if (! isset($chunk->choices) || empty($chunk->choices)) {
            return null;
        }

        $choice = $chunk->choices[0];
        $delta = $choice->delta ?? null;

        if (! $delta) {
            return null;
        }

        $content = $delta->content ?? '';
        $role = $delta->role ?? 'assistant';
        $finishReason = $choice->finishReason ?? null;

        // Calculate elapsed time
        $elapsedTime = (microtime(true) - $startTime) * 1000;

        // Extract token usage if available (usually only in final chunk)
        $tokenUsage = new TokenUsage(0, 0, 0);
        if (isset($chunk->usage)) {
            $tokenUsage = new TokenUsage(
                $chunk->usage->promptTokens ?? 0,
                $chunk->usage->completionTokens ?? 0,
                $chunk->usage->totalTokens ?? 0
            );
        }

        // Handle function calls in streaming
        $functionCalls = null;
        $toolCalls = null;

        if (isset($delta->functionCall)) {
            $functionCalls = (array) $delta->functionCall;
        }

        if (isset($delta->toolCalls)) {
            $toolCalls = array_map(fn ($call) => (array) $call, $delta->toolCalls);
        }

        return new AIResponse(
            $content,
            $tokenUsage,
            $chunk->model ?? ($options['model'] ?? $this->defaultModel),
            $this->providerName,
            $role,
            $finishReason ?? 'streaming',
            $functionCalls,
            $toolCalls,
            $elapsedTime,
            [
                'chunk_index' => $chunkIndex,
                'is_streaming' => true,
                'is_final' => ! empty($finishReason),
            ]
        );
    }

    /**
     * Create final streaming response.
     */
    protected function createFinalStreamResponse(
        string $fullContent,
        int $totalTokens,
        float $responseTime,
        array $options,
        $lastChunk = null
    ): AIResponse {
        $model = $options['model'] ?? $this->defaultModel;
        $finishReason = 'stop';

        // Extract finish reason from last chunk if available
        if ($lastChunk && isset($lastChunk->choices[0]->finishReason)) {
            $finishReason = $lastChunk->choices[0]->finishReason;
        }

        // Create token usage (may be estimated for streaming)
        $tokenUsage = new TokenUsage(0, 0, $totalTokens);

        // Try to get actual usage from last chunk
        if ($lastChunk && isset($lastChunk->usage)) {
            $tokenUsage = new TokenUsage(
                $lastChunk->usage->promptTokens ?? 0,
                $lastChunk->usage->completionTokens ?? 0,
                $lastChunk->usage->totalTokens ?? $totalTokens
            );
        }

        return new AIResponse(
            $fullContent,
            $tokenUsage,
            $model,
            $this->providerName,
            'assistant',
            $finishReason,
            null, // Function calls handled separately in streaming
            null, // Tool calls handled separately in streaming
            $responseTime,
            [
                'is_streaming' => true,
                'is_final' => true,
                'stream_complete' => true,
            ]
        );
    }

    /**
     * Check rate limit before streaming (placeholder).
     */
    protected function checkRateLimit(): void
    {
        // This could be extended to implement rate limiting
        // For now, we'll rely on OpenAI's built-in rate limiting
    }

    /**
     * Stream with progress tracking.
     */
    public function streamWithProgress($message, array $options = [], ?callable $progressCallback = null): AIResponse
    {
        $startTime = microtime(true);
        $chunkCount = 0;
        $totalContent = '';
        $estimatedTokens = $this->estimateTokens($message);

        $callback = function ($chunk) use (&$chunkCount, &$totalContent, $startTime, $estimatedTokens, $progressCallback) {
            $chunkCount++;
            $totalContent .= $chunk;
            $elapsedTime = microtime(true) - $startTime;

            if ($progressCallback) {
                $progress = [
                    'chunk_count' => $chunkCount,
                    'content_length' => strlen($totalContent),
                    'elapsed_time_ms' => $elapsedTime * 1000,
                    'estimated_progress' => min(strlen($totalContent) / max($estimatedTokens * 4, 1), 1.0),
                    'current_chunk' => $chunk,
                ];

                $progressCallback($progress);
            }
        };

        return $this->sendStreamingMessageWithCallback($message, $options, $callback);
    }

    /**
     * Stream with timeout handling.
     */
    public function streamWithTimeout($message, array $options = [], int $timeoutMs = 30000): AIResponse
    {
        $startTime = microtime(true);
        $timeoutSeconds = $timeoutMs / 1000;

        $callback = function ($chunk) use ($startTime, $timeoutSeconds) {
            $elapsedTime = microtime(true) - $startTime;

            if ($elapsedTime > $timeoutSeconds) {
                throw new \RuntimeException("Streaming timeout exceeded ({$timeoutSeconds}s)");
            }
        };

        return $this->sendStreamingMessageWithCallback($message, $options, $callback);
    }

    /**
     * Stream with content filtering.
     */
    public function streamWithFilter($message, array $options = [], ?callable $filter = null): AIResponse
    {
        $filteredContent = '';

        $callback = function ($chunk) use (&$filteredContent, $filter) {
            if ($filter) {
                $filteredChunk = $filter($chunk);
                if ($filteredChunk !== null) {
                    $filteredContent .= $filteredChunk;
                }
            } else {
                $filteredContent .= $chunk;
            }
        };

        $response = $this->sendStreamingMessageWithCallback($message, $options, $callback);

        // Update response content with filtered content
        return new AIResponse(
            $filteredContent,
            $response->tokenUsage,
            $response->model,
            $response->provider,
            $response->role,
            $response->finishReason,
            $response->functionCalls,
            $response->toolCalls,
            $response->responseTimeMs
        );
    }

    /**
     * Get streaming statistics.
     */
    public function getStreamingStats($message, array $options = []): array
    {
        $startTime = microtime(true);
        $chunkCount = 0;
        $totalBytes = 0;
        $chunkTimes = [];
        $lastChunkTime = $startTime;

        $callback = function ($chunk) use (&$chunkCount, &$totalBytes, &$chunkTimes, &$lastChunkTime) {
            $currentTime = microtime(true);
            $chunkCount++;
            $totalBytes += strlen($chunk);
            $chunkTimes[] = ($currentTime - $lastChunkTime) * 1000; // ms between chunks
            $lastChunkTime = $currentTime;
        };

        $response = $this->sendStreamingMessageWithCallback($message, $options, $callback);
        $totalTime = (microtime(true) - $startTime) * 1000;

        return [
            'total_time_ms' => $totalTime,
            'chunk_count' => $chunkCount,
            'total_bytes' => $totalBytes,
            'average_chunk_interval_ms' => $chunkCount > 1 ? array_sum($chunkTimes) / count($chunkTimes) : 0,
            'chunks_per_second' => $totalTime > 0 ? ($chunkCount / ($totalTime / 1000)) : 0,
            'bytes_per_second' => $totalTime > 0 ? ($totalBytes / ($totalTime / 1000)) : 0,
            'response' => $response,
        ];
    }
}
