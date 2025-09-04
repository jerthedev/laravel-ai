<?php

namespace JTD\LaravelAI\Drivers\DriverTemplate\Traits;

/**
 * Supports Streaming Responses
 *
 * Handles streaming message delivery, chunk processing,
 * and real-time response generation.
 */
trait SupportsStreaming
{
    /**
     * Send a streaming message to DriverTemplate.
     */
    public function sendStreamingMessage($message, array $options = []): Generator
    {
        // TODO: Implement sendStreamingMessage
    }

    /**
     * Send a streaming message with callback support.
     */
    public function sendStreamingMessageWithCallback($message, array $options = [], ?callable $callback = null): JTD\LaravelAI\Models\AIResponse
    {
        // TODO: Implement sendStreamingMessageWithCallback
    }

    /**
     * Actually send the streaming message and return a generator.
     */
    protected function doSendStreamingMessage(array $messages, array $options): Generator
    {
        // TODO: Implement doSendStreamingMessage
    }

    /**
     * Parse a streaming chunk.
     */
    protected function parseStreamChunk($chunk, int $chunkIndex, float $startTime, array $options): JTD\LaravelAI\Models\AIResponse
    {
        // TODO: Implement parseStreamChunk
    }

    /**
     * Create final streaming response.
     */
    protected function createFinalStreamResponse(string $fullContent, int $totalTokens, float $responseTime, array $options, $lastChunk = null): JTD\LaravelAI\Models\AIResponse
    {
        // TODO: Implement createFinalStreamResponse
    }

    /**
     * Check rate limit before streaming (placeholder).
     */
    protected function checkRateLimit(): void
    {
        // TODO: Implement checkRateLimit
    }

    /**
     * Stream with progress tracking.
     */
    public function streamWithProgress($message, array $options = [], ?callable $progressCallback = null): JTD\LaravelAI\Models\AIResponse
    {
        // TODO: Implement streamWithProgress
    }

    /**
     * Stream with timeout handling.
     */
    public function streamWithTimeout($message, array $options = [], int $timeoutMs = 30000): JTD\LaravelAI\Models\AIResponse
    {
        // TODO: Implement streamWithTimeout
    }

    /**
     * Stream with content filtering.
     */
    public function streamWithFilter($message, array $options = [], ?callable $filter = null): JTD\LaravelAI\Models\AIResponse
    {
        // TODO: Implement streamWithFilter
    }

    /**
     * Get streaming statistics.
     */
    public function getStreamingStats($message, array $options = []): array
    {
        // TODO: Implement getStreamingStats
    }
}
