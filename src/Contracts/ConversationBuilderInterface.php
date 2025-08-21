<?php

namespace JTD\LaravelAI\Contracts;

use Closure;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Interface for fluent conversation building with method chaining.
 *
 * Provides a Laravel-style fluent interface for building AI conversations
 * with support for method chaining, conditional logic, and callback handling.
 *
 * Example usage:
 * AI::conversation('My Chat')
 *   ->provider('openai')
 *   ->model('gpt-4')
 *   ->temperature(0.7)
 *   ->message('Hello, how are you?')
 *   ->when($condition, fn($builder) => $builder->systemPrompt('Be helpful'))
 *   ->onSuccess(fn($response) => Log::info('Success'))
 *   ->send();
 */
interface ConversationBuilderInterface
{
    /**
     * Set the AI provider to use.
     *
     * @param  string  $provider  Provider name (openai, xai, gemini, etc.)
     *
     * @throws \JTD\LaravelAI\Exceptions\ProviderNotFoundException
     */
    public function provider(string $provider): self;

    /**
     * Set the model to use.
     *
     * @param  string  $model  Model identifier
     *
     * @throws \JTD\LaravelAI\Exceptions\ModelNotFoundException
     */
    public function model(string $model): self;

    /**
     * Set the temperature for response generation.
     *
     * @param  float  $temperature  Temperature value (0.0 to 2.0)
     */
    public function temperature(float $temperature): self;

    /**
     * Set the maximum number of tokens to generate.
     *
     * @param  int  $maxTokens  Maximum tokens
     */
    public function maxTokens(int $maxTokens): self;

    /**
     * Set the top-p value for nucleus sampling.
     *
     * @param  float  $topP  Top-p value (0.0 to 1.0)
     */
    public function topP(float $topP): self;

    /**
     * Set the frequency penalty.
     *
     * @param  float  $penalty  Frequency penalty (-2.0 to 2.0)
     */
    public function frequencyPenalty(float $penalty): self;

    /**
     * Set the presence penalty.
     *
     * @param  float  $penalty  Presence penalty (-2.0 to 2.0)
     */
    public function presencePenalty(float $penalty): self;

    /**
     * Add a system prompt to the conversation.
     *
     * @param  string  $prompt  System prompt content
     */
    public function systemPrompt(string $prompt): self;

    /**
     * Add a user message to the conversation.
     *
     * @param  string|array  $content  Message content
     * @param  array|null  $attachments  File attachments
     */
    public function message($content, ?array $attachments = null): self;

    /**
     * Add multiple messages to the conversation.
     *
     * @param  array  $messages  Array of AIMessage objects or message arrays
     */
    public function messages(array $messages): self;

    /**
     * Add context data to the conversation.
     *
     * @param  array  $context  Context data
     */
    public function context(array $context): self;

    /**
     * Enable streaming for the response.
     *
     * @param  bool  $enabled  Whether to enable streaming
     */
    public function streaming(bool $enabled = true): self;

    /**
     * Enable function calling with provided functions.
     *
     * @param  array  $functions  Available functions
     */
    public function functions(array $functions): self;

    /**
     * Enable tool calling with provided tools.
     *
     * @param  array  $tools  Available tools
     */
    public function tools(array $tools): self;

    /**
     * Set custom options for the request.
     *
     * @param  array  $options  Custom options
     */
    public function options(array $options): self;

    /**
     * Set a timeout for the request.
     *
     * @param  int  $seconds  Timeout in seconds
     */
    public function timeout(int $seconds): self;

    /**
     * Set the number of retry attempts.
     *
     * @param  int  $attempts  Number of retry attempts
     */
    public function retries(int $attempts): self;

    /**
     * Conditionally execute a callback.
     *
     * @param  bool|Closure  $condition  Condition to check
     * @param  Closure  $callback  Callback to execute if condition is true
     * @param  Closure|null  $default  Callback to execute if condition is false
     */
    public function when($condition, Closure $callback, ?Closure $default = null): self;

    /**
     * Conditionally execute a callback when condition is false.
     *
     * @param  bool|Closure  $condition  Condition to check
     * @param  Closure  $callback  Callback to execute if condition is false
     */
    public function unless($condition, Closure $callback): self;

    /**
     * Set a callback to execute on successful response.
     *
     * @param  Closure  $callback  Callback receiving AIResponse
     */
    public function onSuccess(Closure $callback): self;

    /**
     * Set a callback to execute on error.
     *
     * @param  Closure  $callback  Callback receiving exception
     */
    public function onError(Closure $callback): self;

    /**
     * Set a callback to execute on each streaming chunk.
     *
     * @param  Closure  $callback  Callback receiving AIResponse chunk
     */
    public function onProgress(Closure $callback): self;

    /**
     * Set a callback to execute before sending the request.
     *
     * @param  Closure  $callback  Callback receiving the builder instance
     */
    public function beforeSend(Closure $callback): self;

    /**
     * Set a callback to execute after receiving the response.
     *
     * @param  Closure  $callback  Callback receiving AIResponse and builder
     */
    public function afterReceive(Closure $callback): self;

    /**
     * Enable debug mode for detailed logging.
     *
     * @param  bool  $enabled  Whether to enable debug mode
     */
    public function debug(bool $enabled = true): self;

    /**
     * Set tags for the conversation.
     *
     * @param  array  $tags  Tags to associate with the conversation
     */
    public function tags(array $tags): self;

    /**
     * Set metadata for the conversation.
     *
     * @param  array  $metadata  Metadata to associate with the conversation
     */
    public function metadata(array $metadata): self;

    /**
     * Set the conversation title.
     *
     * @param  string  $title  Conversation title
     */
    public function title(string $title): self;

    /**
     * Associate the conversation with a user.
     *
     * @param  mixed  $user  User instance or ID
     */
    public function user($user): self;

    /**
     * Set a session ID for anonymous conversations.
     *
     * @param  string  $sessionId  Session identifier
     */
    public function session(string $sessionId): self;

    /**
     * Enable cost tracking for the conversation.
     *
     * @param  bool  $enabled  Whether to enable cost tracking
     */
    public function trackCosts(bool $enabled = true): self;

    /**
     * Enable performance tracking for the conversation.
     *
     * @param  bool  $enabled  Whether to enable performance tracking
     */
    public function trackPerformance(bool $enabled = true): self;

    /**
     * Send the conversation and get a response.
     *
     *
     * @throws \JTD\LaravelAI\Exceptions\RateLimitException
     * @throws \JTD\LaravelAI\Exceptions\InvalidCredentialsException
     * @throws \JTD\LaravelAI\Exceptions\ProviderException
     * @throws \JTD\LaravelAI\Exceptions\InvalidConfigurationException
     */
    public function send(): AIResponse;

    /**
     * Send the conversation as a streaming request.
     *
     * @return \Generator<AIResponse>
     *
     * @throws \JTD\LaravelAI\Exceptions\StreamingNotSupportedException
     */
    public function stream(): \Generator;

    /**
     * Get the conversation as an array of messages.
     *
     * @return array Array of AIMessage objects
     */
    public function getMessages(): array;

    /**
     * Get the current configuration options.
     *
     * @return array Configuration options
     */
    public function getOptions(): array;

    /**
     * Get the selected provider.
     *
     * @return string|null Provider name
     */
    public function getProvider(): ?string;

    /**
     * Get the selected model.
     *
     * @return string|null Model identifier
     */
    public function getModel(): ?string;

    /**
     * Clone the builder for reuse.
     *
     * @return self New builder instance with same configuration
     */
    public function clone(): self;

    /**
     * Reset the builder to initial state.
     */
    public function reset(): self;
}
