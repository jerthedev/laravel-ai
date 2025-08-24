<?php

namespace JTD\LaravelAI\Services;

use Closure;
use JTD\LaravelAI\Contracts\ConversationBuilderInterface;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Conversation Builder
 *
 * Provides a fluent interface for building AI conversations with method chaining,
 * conditional logic, and callback support.
 */
class ConversationBuilder implements ConversationBuilderInterface
{
    /**
     * The AI manager instance.
     */
    protected AIManager $manager;

    /**
     * The conversation messages.
     */
    protected array $messages = [];

    /**
     * The selected provider.
     */
    protected ?string $provider = null;

    /**
     * The selected model.
     */
    protected ?string $model = null;

    /**
     * Request options.
     */
    protected array $options = [];

    /**
     * Conversation metadata.
     */
    protected array $metadata = [];

    /**
     * Event callbacks.
     */
    protected array $callbacks = [];

    /**
     * Conversation title.
     */
    protected ?string $title = null;

    /**
     * User association.
     *
     * @var mixed
     */
    protected $user = null;

    /**
     * Session ID for anonymous conversations.
     */
    protected ?string $sessionId = null;

    /**
     * Feature flags.
     */
    protected array $features = [
        'streaming' => false,
        'cost_tracking' => true,
        'performance_tracking' => true,
        'debug' => false,
        'provider_switching' => false,
        'auto_fallback' => false,
    ];

    /**
     * Provider fallback configuration.
     */
    protected array $fallbackConfig = [
        'enabled' => false,
        'strategy' => 'auto',
        'providers' => [],
        'max_attempts' => 3,
        'preserve_context' => true,
    ];

    /**
     * Middleware to apply to this conversation.
     */
    protected array $middleware = [];

    /**
     * Create a new conversation builder.
     */
    public function __construct(AIManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Set the AI provider to use.
     */
    public function provider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set the model to use.
     */
    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the temperature for response generation.
     */
    public function temperature(float $temperature): self
    {
        $this->options['temperature'] = $temperature;

        return $this;
    }

    /**
     * Set the maximum number of tokens to generate.
     */
    public function maxTokens(int $maxTokens): self
    {
        $this->options['max_tokens'] = $maxTokens;

        return $this;
    }

    /**
     * Set the top-p value for nucleus sampling.
     */
    public function topP(float $topP): self
    {
        $this->options['top_p'] = $topP;

        return $this;
    }

    /**
     * Set the frequency penalty.
     */
    public function frequencyPenalty(float $penalty): self
    {
        $this->options['frequency_penalty'] = $penalty;

        return $this;
    }

    /**
     * Set the presence penalty.
     */
    public function presencePenalty(float $penalty): self
    {
        $this->options['presence_penalty'] = $penalty;

        return $this;
    }

    /**
     * Add a system prompt to the conversation.
     */
    public function systemPrompt(string $prompt): self
    {
        $this->messages[] = AIMessage::system($prompt);

        return $this;
    }

    /**
     * Add a user message to the conversation.
     */
    public function message($content, ?array $attachments = null): self
    {
        $contentType = $attachments ? AIMessage::CONTENT_TYPE_MULTIMODAL : AIMessage::CONTENT_TYPE_TEXT;
        $this->messages[] = AIMessage::user($content, $contentType, $attachments);

        return $this;
    }

    /**
     * Add multiple messages to the conversation.
     */
    public function messages(array $messages): self
    {
        foreach ($messages as $message) {
            if ($message instanceof AIMessage) {
                $this->messages[] = $message;
            } elseif (is_array($message)) {
                $this->messages[] = AIMessage::fromArray($message);
            }
        }

        return $this;
    }

    /**
     * Add context data to the conversation.
     */
    public function context(array $context): self
    {
        $this->metadata['context'] = array_merge($this->metadata['context'] ?? [], $context);

        return $this;
    }

    /**
     * Enable streaming for the response.
     */
    public function streaming(bool $enabled = true): self
    {
        $this->features['streaming'] = $enabled;

        return $this;
    }

    /**
     * Enable function calling with provided functions.
     */
    public function functions(array $functions): self
    {
        $this->options['functions'] = $functions;

        return $this;
    }

    /**
     * Enable tool calling with provided tools.
     */
    public function tools(array $tools): self
    {
        $this->options['tools'] = $tools;

        return $this;
    }

    /**
     * Apply middleware to this conversation.
     *
     * @param  array|string  $middleware  The middleware to apply
     */
    public function middleware($middleware): self
    {
        if (is_string($middleware)) {
            $this->middleware[] = $middleware;
        } elseif (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        }

        return $this;
    }

    /**
     * Disable middleware for this conversation.
     *
     * @param  array|null  $middleware  Specific middleware to disable, or null for all
     */
    public function withoutMiddleware(?array $middleware = null): self
    {
        if ($middleware === null) {
            $this->middleware = [];
        } else {
            $this->middleware = array_diff($this->middleware, $middleware);
        }

        return $this;
    }

    /**
     * Set custom options for the request.
     */
    public function options(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Set a timeout for the request.
     */
    public function timeout(int $seconds): self
    {
        $this->options['timeout'] = $seconds;

        return $this;
    }

    /**
     * Set the number of retry attempts.
     */
    public function retries(int $attempts): self
    {
        $this->options['retries'] = $attempts;

        return $this;
    }

    /**
     * Conditionally execute a callback.
     */
    public function when($condition, Closure $callback, ?Closure $default = null): self
    {
        $conditionResult = $condition instanceof Closure ? $condition($this) : $condition;

        if ($conditionResult) {
            $callback($this);
        } elseif ($default) {
            $default($this);
        }

        return $this;
    }

    /**
     * Conditionally execute a callback when condition is false.
     */
    public function unless($condition, Closure $callback): self
    {
        return $this->when(! $condition, $callback);
    }

    /**
     * Set a callback to execute on successful response.
     */
    public function onSuccess(Closure $callback): self
    {
        $this->callbacks['success'] = $callback;

        return $this;
    }

    /**
     * Set a callback to execute on error.
     */
    public function onError(Closure $callback): self
    {
        $this->callbacks['error'] = $callback;

        return $this;
    }

    /**
     * Set a callback to execute on each streaming chunk.
     */
    public function onProgress(Closure $callback): self
    {
        $this->callbacks['progress'] = $callback;

        return $this;
    }

    /**
     * Set a callback to execute before sending the request.
     */
    public function beforeSend(Closure $callback): self
    {
        $this->callbacks['before_send'] = $callback;

        return $this;
    }

    /**
     * Set a callback to execute after receiving the response.
     */
    public function afterReceive(Closure $callback): self
    {
        $this->callbacks['after_receive'] = $callback;

        return $this;
    }

    /**
     * Enable debug mode for detailed logging.
     */
    public function debug(bool $enabled = true): self
    {
        $this->features['debug'] = $enabled;

        return $this;
    }

    /**
     * Set tags for the conversation.
     */
    public function tags(array $tags): self
    {
        $this->metadata['tags'] = $tags;

        return $this;
    }

    /**
     * Set metadata for the conversation.
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Set the conversation title.
     */
    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Associate the conversation with a user.
     */
    public function user($user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set a session ID for anonymous conversations.
     */
    public function session(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    /**
     * Enable cost tracking for the conversation.
     */
    public function trackCosts(bool $enabled = true): self
    {
        $this->features['cost_tracking'] = $enabled;

        return $this;
    }

    /**
     * Enable performance tracking for the conversation.
     */
    public function trackPerformance(bool $enabled = true): self
    {
        $this->features['performance_tracking'] = $enabled;

        return $this;
    }

    /**
     * Enable provider switching for the conversation.
     */
    public function enableProviderSwitching(bool $enabled = true): self
    {
        $this->features['provider_switching'] = $enabled;

        return $this;
    }

    /**
     * Configure fallback providers.
     */
    public function fallback(array $providers, array $options = []): self
    {
        $this->fallbackConfig = array_merge($this->fallbackConfig, [
            'enabled' => true,
            'providers' => $providers,
        ], $options);

        $this->features['auto_fallback'] = true;

        return $this;
    }

    /**
     * Set fallback strategy.
     */
    public function fallbackStrategy(string $strategy): self
    {
        $this->fallbackConfig['strategy'] = $strategy;

        return $this;
    }

    /**
     * Set maximum fallback attempts.
     */
    public function maxFallbackAttempts(int $attempts): self
    {
        $this->fallbackConfig['max_attempts'] = $attempts;

        return $this;
    }

    /**
     * Configure context preservation during provider switches.
     */
    public function preserveContext(bool $preserve = true): self
    {
        $this->fallbackConfig['preserve_context'] = $preserve;

        return $this;
    }

    /**
     * Set provider priority for fallback.
     */
    public function providerPriority(array $providers): self
    {
        return $this->fallback($providers);
    }

    /**
     * Enable cost-optimized fallback.
     */
    public function costOptimizedFallback(): self
    {
        return $this->fallbackStrategy('cost_optimized');
    }

    /**
     * Enable performance-optimized fallback.
     */
    public function performanceOptimizedFallback(): self
    {
        return $this->fallbackStrategy('performance_optimized');
    }

    /**
     * Enable capability-matched fallback.
     */
    public function capabilityMatchedFallback(): self
    {
        return $this->fallbackStrategy('capability_matched');
    }

    /**
     * Send the conversation and get a response.
     */
    public function send(): AIResponse
    {
        $this->executeCallback('before_send');

        try {
            $response = $this->attemptSendWithFallback();

            $this->executeCallback('success', $response);
            $this->executeCallback('after_receive', $response);

            return $response;
        } catch (\Exception $e) {
            $this->executeCallback('error', $e);
            throw $e;
        }
    }

    /**
     * Attempt to send with fallback support.
     */
    protected function attemptSendWithFallback(): AIResponse
    {
        // Try primary provider first
        try {
            return $this->sendWithProvider($this->provider);
        } catch (\Exception $primaryException) {
            // If fallback is not enabled, rethrow the original exception
            if (! $this->fallbackConfig['enabled'] || ! $this->features['auto_fallback']) {
                throw $primaryException;
            }

            // Attempt fallback providers
            return $this->attemptFallbackProviders($primaryException);
        }
    }

    /**
     * Attempt fallback providers.
     */
    protected function attemptFallbackProviders(\Exception $originalException): AIResponse
    {
        $attempts = 0;
        $maxAttempts = $this->fallbackConfig['max_attempts'];
        $fallbackProviders = $this->getFallbackProviders();

        foreach ($fallbackProviders as $fallbackProvider) {
            if ($attempts >= $maxAttempts) {
                break;
            }

            try {
                $attempts++;

                // Log fallback attempt
                if ($this->features['debug']) {
                    \Log::info('Attempting fallback provider', [
                        'original_provider' => $this->provider,
                        'fallback_provider' => $fallbackProvider,
                        'attempt' => $attempts,
                    ]);
                }

                return $this->sendWithProvider($fallbackProvider);
            } catch (\Exception $fallbackException) {
                if ($this->features['debug']) {
                    \Log::warning('Fallback provider failed', [
                        'provider' => $fallbackProvider,
                        'error' => $fallbackException->getMessage(),
                    ]);
                }

                continue;
            }
        }

        // All fallback attempts failed
        throw new \Exception(
            'Primary provider and all fallback providers failed. Original error: ' . $originalException->getMessage(),
            0,
            $originalException
        );
    }

    /**
     * Send message with a specific provider.
     */
    protected function sendWithProvider(?string $providerName): AIResponse
    {
        // Create AIMessage for middleware processing
        $messages = $this->getMessages();
        $lastMessage = end($messages);

        if (! $lastMessage instanceof AIMessage) {
            throw new \InvalidArgumentException('No valid message to send');
        }

        // Set provider and model on the message
        $lastMessage->provider = $providerName ?? $this->provider;
        $lastMessage->model = $this->model;
        $lastMessage->user_id = $this->getUserId();
        $lastMessage->metadata = array_merge($lastMessage->metadata ?? [], $this->metadata);
        $lastMessage->metadata['processing_start_time'] = microtime(true);

        // Process through middleware if any are configured
        if (! empty($this->middleware) || config('ai.middleware.enabled', false)) {
            $middlewareManager = app('laravel-ai.middleware');

            return $middlewareManager->process($lastMessage, $this->middleware);
        }

        // Fallback to direct provider call if no middleware
        return $this->sendDirectToProvider($lastMessage, $providerName);
    }

    /**
     * Send message directly to provider (bypassing middleware).
     *
     * @param  AIMessage  $message  The message to send
     * @param  string|null  $providerName  The provider name
     * @return AIResponse The response
     */
    protected function sendDirectToProvider(AIMessage $message, ?string $providerName): AIResponse
    {
        $provider = $providerName
            ? $this->manager->driver($providerName)
            : $this->getProviderInstance();

        if ($this->model) {
            $provider->setModel($this->model);
        }

        $provider->setOptions($this->options);

        // Send message to provider (events will be fired at provider level)
        $response = $provider->sendMessage([$message], $this->options);

        return $response;
    }

    /**
     * Get the user ID for the conversation.
     *
     * @return int The user ID
     */
    protected function getUserId(): int
    {
        if (is_object($this->user) && method_exists($this->user, 'getKey')) {
            return $this->user->getKey();
        }

        if (is_numeric($this->user)) {
            return (int) $this->user;
        }

        // Fallback to authenticated user or 0
        return auth()->id() ?? 0;
    }

    /**
     * Get fallback providers based on strategy.
     */
    protected function getFallbackProviders(): array
    {
        if (! empty($this->fallbackConfig['providers'])) {
            return $this->fallbackConfig['providers'];
        }

        // Auto-generate fallback providers based on strategy
        return match ($this->fallbackConfig['strategy']) {
            'cost_optimized' => $this->getCostOptimizedProviders(),
            'performance_optimized' => $this->getPerformanceOptimizedProviders(),
            'capability_matched' => $this->getCapabilityMatchedProviders(),
            default => $this->getDefaultFallbackProviders(),
        };
    }

    /**
     * Get cost-optimized fallback providers.
     */
    protected function getCostOptimizedProviders(): array
    {
        // This would typically query the database for cost information
        // For now, return a reasonable default order
        return ['gemini', 'xai', 'openai'];
    }

    /**
     * Get performance-optimized fallback providers.
     */
    protected function getPerformanceOptimizedProviders(): array
    {
        // This would typically query performance metrics
        // For now, return a reasonable default order
        return ['openai', 'xai', 'gemini'];
    }

    /**
     * Get capability-matched fallback providers.
     */
    protected function getCapabilityMatchedProviders(): array
    {
        // This would match capabilities of the current provider
        // For now, return all available providers
        return ['openai', 'gemini', 'xai'];
    }

    /**
     * Get default fallback providers.
     */
    protected function getDefaultFallbackProviders(): array
    {
        $currentProvider = $this->provider;
        $allProviders = ['openai', 'gemini', 'xai'];

        // Remove current provider from fallback list
        return array_values(array_filter($allProviders, fn ($p) => $p !== $currentProvider));
    }

    /**
     * Send the conversation as a streaming request.
     */
    public function stream(): \Generator
    {
        $this->executeCallback('before_send');

        try {
            $provider = $this->getProviderInstance();

            if ($this->model) {
                $provider->setModel($this->model);
            }

            $provider->setOptions($this->options);

            $messages = $this->getMessages();
            $stream = $provider->sendStreamingMessage($messages, $this->options);

            foreach ($stream as $chunk) {
                $this->executeCallback('progress', $chunk);
                yield $chunk;
            }

            $this->executeCallback('after_receive');
        } catch (\Exception $e) {
            $this->executeCallback('error', $e);
            throw $e;
        }
    }

    /**
     * Get the conversation as an array of messages.
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the current configuration options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get the selected provider.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Get the conversation title.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Get the temperature setting.
     */
    public function getTemperature(): ?float
    {
        return $this->options['temperature'] ?? null;
    }

    /**
     * Get the max tokens setting.
     */
    public function getMaxTokens(): ?int
    {
        return $this->options['max_tokens'] ?? null;
    }

    /**
     * Get the selected model.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Clone the builder for reuse.
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Reset the builder to initial state.
     */
    public function reset(): self
    {
        $this->messages = [];
        $this->provider = null;
        $this->model = null;
        $this->options = [];
        $this->metadata = [];
        $this->callbacks = [];
        $this->title = null;
        $this->user = null;
        $this->sessionId = null;
        $this->features = [
            'streaming' => false,
            'cost_tracking' => true,
            'performance_tracking' => true,
            'debug' => false,
            'provider_switching' => false,
            'auto_fallback' => false,
        ];

        $this->fallbackConfig = [
            'enabled' => false,
            'strategy' => 'auto',
            'providers' => [],
            'max_attempts' => 3,
            'preserve_context' => true,
        ];

        return $this;
    }

    /**
     * Get the provider instance.
     *
     * @return \JTD\LaravelAI\Contracts\AIProviderInterface
     */
    protected function getProviderInstance()
    {
        return $this->provider
            ? $this->manager->driver($this->provider)
            : $this->manager->driver();
    }

    /**
     * Execute a callback if it exists.
     *
     * @param  mixed  ...$args
     */
    protected function executeCallback(string $event, ...$args): void
    {
        if (isset($this->callbacks[$event])) {
            $this->callbacks[$event](...$args);
        }
    }
}
