<?php

namespace JTD\LaravelAI\Drivers\Gemini\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;

/**
 * Handles Streaming for Gemini API
 *
 * Provides streaming message functionality using Server-Sent Events (SSE)
 * for real-time response generation. Follows the same patterns as OpenAI streaming.
 */
trait HandlesStreaming
{
    /**
     * Send a streaming message to Gemini API.
     */
    public function sendStreamingMessage($message, array $options = []): \Generator
    {
        // Use the original generator-based implementation for compatibility
        $messages = is_array($message) ? $message : [$message];
        $mergedOptions = array_merge($this->options ?? [], $options);

        return $this->doSendStreamingMessage($messages, $mergedOptions);
    }

    /**
     * Send a streaming message with callback support.
     */
    public function sendStreamingMessageWithCallback($message, array $options = [], ?callable $callback = null): AIResponse
    {
        $messages = is_array($message) ? $message : [$message];
        $mergedOptions = array_merge($this->options ?? [], $options);

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
            $mergedOptions['model'] ?? $this->getCurrentModel(),
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

            // Prepare API parameters
            $apiParams = $this->prepareApiParameters($messages, $options);

            // Build streaming URL
            $model = $options['model'] ?? $this->getCurrentModel();
            $baseUrl = rtrim($this->config['base_url'], '/');
            $url = "{$baseUrl}/models/{$model}:streamGenerateContent?alt=sse&key=" . $this->config['api_key'];

            // Make the streaming API call
            $response = Http::withHeaders($this->getRequestHeaders())
                ->timeout($this->config['timeout'] ?? 30)
                ->withOptions(['stream' => true])
                ->post($url, $apiParams);

            if (! $response->successful()) {
                $this->handleApiResponseError($response, $response->json() ?? []);
            }

            // Track streaming state
            $fullContent = '';
            $totalTokens = 0;
            $chunkCount = 0;
            $lastChunk = null;

            // Process each chunk from the streaming response
            foreach ($this->parseStreamingResponse($response->body()) as $chunk) {
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

            // Fire events for background processing if method exists
            if (method_exists($this, 'fireEvents')) {
                $this->fireEvents($messages[0] ?? AIMessage::user(''), $finalResponse, $options);
            }

            // Yield final response
            yield $finalResponse;
        } catch (\Exception $e) {
            $this->handleApiError($e);
        }
    }

    /**
     * Process the streaming response from Gemini API.
     */
    protected function processStreamingResponse($response, string $model): \Generator
    {
        $buffer = '';
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $fullContent = '';

        // Read the response stream
        $stream = $response->getBody();

        while (! $stream->eof()) {
            $chunk = $stream->read(1024);
            $buffer .= $chunk;

            // Process complete SSE events
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $chunk = $this->parseSSEEvent($event);
                if ($chunk) {
                    // Extract content and token usage
                    $content = $this->extractStreamingContent($chunk);
                    $tokenUsage = $this->extractStreamingTokenUsage($chunk);

                    if ($content) {
                        $fullContent .= $content;

                        // Yield streaming chunk
                        yield new AIResponse(
                            content: $content,
                            tokenUsage: new TokenUsage(
                                inputTokens: $tokenUsage['input'] ?? 0,
                                outputTokens: $tokenUsage['output'] ?? 0,
                                totalTokens: ($tokenUsage['input'] ?? 0) + ($tokenUsage['output'] ?? 0)
                            ),
                            model: $model,
                            provider: $this->providerName,
                            role: AIMessage::ROLE_ASSISTANT,
                            finishReason: $chunk['candidates'][0]['finishReason'] ?? null,
                            functionCalls: null,
                            toolCalls: null,
                            responseTimeMs: 0,
                            metadata: [
                                'safety_ratings' => $chunk['candidates'][0]['safetyRatings'] ?? [],
                                'chunk_index' => $chunk['index'] ?? 0,
                                'is_streaming' => true,
                            ]
                        );
                    }

                    // Update token counters
                    if (isset($tokenUsage['input'])) {
                        $totalInputTokens = max($totalInputTokens, $tokenUsage['input']);
                    }
                    if (isset($tokenUsage['output'])) {
                        $totalOutputTokens = max($totalOutputTokens, $tokenUsage['output']);
                    }
                }
            }
        }

        // Yield final response with complete content and final token usage
        if ($fullContent) {
            yield new AIResponse(
                content: $fullContent,
                tokenUsage: new TokenUsage(
                    inputTokens: $totalInputTokens,
                    outputTokens: $totalOutputTokens,
                    totalTokens: $totalInputTokens + $totalOutputTokens
                ),
                model: $model,
                provider: $this->providerName,
                role: AIMessage::ROLE_ASSISTANT,
                finishReason: 'STOP',
                functionCalls: null,
                toolCalls: null,
                responseTimeMs: 0,
                metadata: [
                    'is_final' => true,
                    'total_chunks' => 0, // Could track this if needed
                    'is_streaming' => false,
                ]
            );
        }
    }

    /**
     * Parse Server-Sent Events (SSE) format.
     */
    protected function parseSSEEvent(string $event): ?array
    {
        $lines = explode("\n", $event);
        $data = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, 'data: ')) {
                $data .= substr($line, 6);
            }
        }

        if (empty($data) || $data === '[DONE]') {
            return null;
        }

        try {
            return json_decode($data, true);
        } catch (\Exception $e) {
            Log::warning('Failed to parse SSE data', ['data' => $data, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Extract content from streaming chunk.
     */
    protected function extractStreamingContent(array $chunk): ?string
    {
        if (! isset($chunk['candidates'][0]['content']['parts'][0]['text'])) {
            return null;
        }

        return $chunk['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Extract token usage from streaming chunk.
     */
    protected function extractStreamingTokenUsage(array $chunk): array
    {
        $usage = $chunk['usageMetadata'] ?? [];

        return [
            'input' => $usage['promptTokenCount'] ?? 0,
            'output' => $usage['candidatesTokenCount'] ?? 0,
            'total' => $usage['totalTokenCount'] ?? 0,
        ];
    }

    /**
     * Check if streaming is supported for the current model.
     */
    public function supportsStreaming(): bool
    {
        $model = $this->getCurrentModel();

        return $this->getModelCapabilities($model)['streaming'] ?? true;
    }

    /**
     * Get streaming configuration options.
     */
    public function getStreamingConfig(): array
    {
        return [
            'chunk_size' => 1024,
            'timeout' => 120, // 2 minutes for streaming
            'buffer_size' => 8192,
            'max_chunks' => 1000,
        ];
    }

    /**
     * Validate streaming options.
     */
    protected function validateStreamingOptions(array $options): void
    {
        if (! $this->supportsStreaming()) {
            throw new \JTD\LaravelAI\Exceptions\UnsupportedFeatureException(
                'Streaming is not supported for the current model: ' . $this->getCurrentModel()
            );
        }

        // Validate streaming-specific options
        if (isset($options['stream_config']['chunk_size'])) {
            $chunkSize = $options['stream_config']['chunk_size'];
            if ($chunkSize < 1 || $chunkSize > 65536) {
                throw new \InvalidArgumentException('Chunk size must be between 1 and 65536 bytes');
            }
        }

        if (isset($options['stream_config']['max_chunks'])) {
            $maxChunks = $options['stream_config']['max_chunks'];
            if ($maxChunks < 1 || $maxChunks > 10000) {
                throw new \InvalidArgumentException('Max chunks must be between 1 and 10000');
            }
        }
    }

    /**
     * Create a streaming conversation.
     */
    public function createStreamingConversation(array $options = []): array
    {
        $this->validateStreamingOptions($options);

        return [
            'id' => uniqid('gemini_stream_'),
            'model' => $options['model'] ?? $this->getCurrentModel(),
            'config' => array_merge($this->getStreamingConfig(), $options['stream_config'] ?? []),
            'created_at' => time(),
            'messages' => [],
        ];
    }
}
