<?php

namespace JTD\LaravelAI\Models;

/**
 * Data Transfer Object for AI provider responses.
 *
 * Represents a complete response from an AI provider, including the generated
 * content, token usage statistics, cost information, performance metrics,
 * and metadata. This class provides a unified interface for AI responses
 * across different providers.
 *
 * Key features:
 * - Generated content and role information
 * - Token usage tracking for cost calculation
 * - Performance metrics (response time, etc.)
 * - Function/tool call support
 * - Streaming response support
 * - Provider-specific metadata
 *
 * Finish reasons indicate why the AI stopped generating:
 * - stop: Natural completion
 * - length: Hit maximum token limit
 * - content_filter: Content was filtered
 * - function_call: AI wants to call a function
 * - tool_calls: AI wants to call tools
 * - error: An error occurred
 *
 * @version 1.0.0
 *
 * @since 1.0.0
 *
 * @example
 * ```php
 * $response = new AIResponse(
 *     content: 'Hello! How can I help you today?',
 *     role: 'assistant',
 *     finishReason: AIResponse::FINISH_REASON_STOP,
 *     tokenUsage: new TokenUsage(10, 8, 18),
 *     model: 'gpt-4',
 *     provider: 'openai'
 * );
 *
 * echo $response->content;
 * echo "Cost: $" . number_format($response->cost, 4);
 * echo "Tokens: {$response->tokenUsage->totalTokens}";
 * ```
 */
class AIResponse
{
    /**
     * Finish reason constants.
     */
    public const FINISH_REASON_STOP = 'stop';

    public const FINISH_REASON_LENGTH = 'length';

    public const FINISH_REASON_CONTENT_FILTER = 'content_filter';

    public const FINISH_REASON_FUNCTION_CALL = 'function_call';

    public const FINISH_REASON_TOOL_CALLS = 'tool_calls';

    public const FINISH_REASON_ERROR = 'error';

    /**
     * @var string Response content
     */
    public string $content;

    /**
     * @var string Response role (usually 'assistant')
     */
    public string $role;

    /**
     * @var string Reason why the response finished
     */
    public string $finishReason;

    /**
     * @var TokenUsage Token usage information
     */
    public TokenUsage $tokenUsage;

    /**
     * @var string Model used for the response
     */
    public string $model;

    /**
     * @var string Provider name
     */
    public string $provider;

    /**
     * @var array|null Function calls made by the AI
     */
    public ?array $functionCalls;

    /**
     * @var array|null Tool calls made by the AI
     */
    public ?array $toolCalls;

    /**
     * @var float|null Response time in milliseconds
     */
    public ?float $responseTimeMs;

    /**
     * @var array Response metadata from provider
     */
    public array $metadata;

    /**
     * @var string|null Provider-specific message ID
     */
    public ?string $providerMessageId;

    /**
     * @var \DateTime Response timestamp
     */
    public \DateTime $timestamp;

    /**
     * @var bool Whether this was a streaming response
     */
    public bool $isStreaming;

    /**
     * @var int|null Number of chunks if streaming
     */
    public ?int $streamChunks;

    /**
     * @var array|null Cost breakdown
     */
    public ?array $costBreakdown;

    /**
     * Create a new AIResponse instance.
     *
     * @param  string  $content  Response content
     * @param  TokenUsage  $tokenUsage  Token usage information
     * @param  string  $model  Model used
     * @param  string  $provider  Provider name
     * @param  string  $role  Response role
     * @param  string  $finishReason  Finish reason
     * @param  array|null  $functionCalls  Function calls
     * @param  array|null  $toolCalls  Tool calls
     * @param  float|null  $responseTimeMs  Response time
     * @param  array  $metadata  Additional metadata
     * @param  string|null  $providerMessageId  Provider message ID
     * @param  bool  $isStreaming  Whether streaming
     * @param  int|null  $streamChunks  Stream chunk count
     * @param  array|null  $costBreakdown  Cost breakdown
     */
    public function __construct(
        string $content,
        TokenUsage $tokenUsage,
        string $model,
        string $provider,
        string $role = AIMessage::ROLE_ASSISTANT,
        string $finishReason = self::FINISH_REASON_STOP,
        ?array $functionCalls = null,
        ?array $toolCalls = null,
        ?float $responseTimeMs = null,
        array $metadata = [],
        ?string $providerMessageId = null,
        bool $isStreaming = false,
        ?int $streamChunks = null,
        ?array $costBreakdown = null
    ) {
        $this->content = $content;
        $this->tokenUsage = $tokenUsage;
        $this->model = $model;
        $this->provider = $provider;
        $this->role = $role;
        $this->finishReason = $finishReason;
        $this->functionCalls = $functionCalls;
        $this->toolCalls = $toolCalls;
        $this->responseTimeMs = $responseTimeMs;
        $this->metadata = $metadata;
        $this->providerMessageId = $providerMessageId;
        $this->timestamp = new \DateTime;
        $this->isStreaming = $isStreaming;
        $this->streamChunks = $streamChunks;
        $this->costBreakdown = $costBreakdown;
    }

    /**
     * Create a successful response.
     *
     * @param  string  $content  Response content
     * @param  TokenUsage  $tokenUsage  Token usage
     * @param  string  $model  Model used
     * @param  string  $provider  Provider name
     * @param  array  $metadata  Additional metadata
     */
    public static function success(
        string $content,
        TokenUsage $tokenUsage,
        string $model,
        string $provider,
        array $metadata = []
    ): static {
        return new static(
            $content,
            $tokenUsage,
            $model,
            $provider,
            AIMessage::ROLE_ASSISTANT,
            self::FINISH_REASON_STOP,
            null,
            null,
            null,
            $metadata
        );
    }

    /**
     * Create a streaming response chunk.
     *
     * @param  string  $content  Chunk content
     * @param  string  $model  Model used
     * @param  string  $provider  Provider name
     * @param  bool  $isComplete  Whether this is the final chunk
     */
    public static function streamingChunk(
        string $content,
        string $model,
        string $provider,
        bool $isComplete = false
    ): static {
        $tokenUsage = new TokenUsage(0, 0, 0); // Will be updated when complete

        return new static(
            $content,
            $tokenUsage,
            $model,
            $provider,
            AIMessage::ROLE_ASSISTANT,
            $isComplete ? self::FINISH_REASON_STOP : 'streaming',
            null,
            null,
            null,
            ['is_complete' => $isComplete],
            null,
            true
        );
    }

    /**
     * Create an error response.
     *
     * @param  string  $error  Error message
     * @param  string  $model  Model attempted
     * @param  string  $provider  Provider name
     * @param  array  $metadata  Error metadata
     */
    public static function error(
        string $error,
        string $model,
        string $provider,
        array $metadata = []
    ): static {
        $tokenUsage = new TokenUsage(0, 0, 0);

        return new static(
            $error,
            $tokenUsage,
            $model,
            $provider,
            'error',
            self::FINISH_REASON_ERROR,
            null,
            null,
            null,
            array_merge(['error' => true], $metadata)
        );
    }

    /**
     * Convert the response to an array.
     */
    public function toArray(): array
    {
        $data = [
            'content' => $this->content,
            'role' => $this->role,
            'finish_reason' => $this->finishReason,
            'token_usage' => $this->tokenUsage->toArray(),
            'model' => $this->model,
            'provider' => $this->provider,
            'response_time_ms' => $this->responseTimeMs,
            'metadata' => $this->metadata,
            'provider_message_id' => $this->providerMessageId,
            'timestamp' => $this->timestamp->format('c'),
            'is_streaming' => $this->isStreaming,
            'stream_chunks' => $this->streamChunks,
        ];

        if ($this->functionCalls) {
            $data['function_calls'] = $this->functionCalls;
        }

        if ($this->toolCalls) {
            $data['tool_calls'] = $this->toolCalls;
        }

        if ($this->costBreakdown) {
            $data['cost_breakdown'] = $this->costBreakdown;
        }

        return $data;
    }

    /**
     * Create an AIResponse from an array.
     *
     * @param  array  $data  Response data
     */
    public static function fromArray(array $data): static
    {
        $tokenUsage = TokenUsage::fromArray($data['token_usage'] ?? []);

        $response = new static(
            $data['content'],
            $tokenUsage,
            $data['model'],
            $data['provider'],
            $data['role'] ?? AIMessage::ROLE_ASSISTANT,
            $data['finish_reason'] ?? self::FINISH_REASON_STOP,
            $data['function_calls'] ?? null,
            $data['tool_calls'] ?? null,
            $data['response_time_ms'] ?? null,
            $data['metadata'] ?? [],
            $data['provider_message_id'] ?? null,
            $data['is_streaming'] ?? false,
            $data['stream_chunks'] ?? null,
            $data['cost_breakdown'] ?? null
        );

        if (isset($data['timestamp'])) {
            $response->timestamp = new \DateTime($data['timestamp']);
        }

        return $response;
    }

    /**
     * Convert the response to an AIMessage.
     */
    public function toMessage(): AIMessage
    {
        return new AIMessage(
            $this->role,
            $this->content ?: '', // Ensure content is not null
            AIMessage::CONTENT_TYPE_TEXT,
            null,
            $this->functionCalls,
            $this->toolCalls,
            $this->metadata,
            null,
            $this->timestamp
        );
    }

    /**
     * Check if the response was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->finishReason !== self::FINISH_REASON_ERROR && $this->role !== 'error';
    }

    /**
     * Check if the response has function calls.
     */
    public function hasFunctionCalls(): bool
    {
        return ! empty($this->functionCalls);
    }

    /**
     * Check if the response has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    /**
     * Get the total cost of this response.
     */
    public function getTotalCost(): ?float
    {
        return $this->tokenUsage->totalCost;
    }

    /**
     * Get the response content length.
     */
    public function getContentLength(): int
    {
        return strlen($this->content);
    }
}
