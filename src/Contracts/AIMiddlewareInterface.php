<?php

namespace JTD\LaravelAI\Contracts;

use Closure;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * AI Middleware Interface
 *
 * Defines the contract for AI middleware components that intercept and process
 * AI requests in a Laravel-style pipeline. Middleware can transform requests,
 * enforce policies, track costs, inject context, and optimize routing before
 * requests reach AI providers.
 *
 * The middleware system provides enterprise-grade features including:
 * - Budget enforcement with real-time cost tracking
 * - Smart routing based on request complexity and cost optimization
 * - Context injection using conversation history and search-enhanced retrieval
 * - Performance monitoring with <10ms execution overhead
 * - Request preprocessing and response enhancement
 * - Caching and rate limiting capabilities
 *
 * Configuration:
 * Configure middleware in config/ai.php:
 * ```php
 * 'middleware' => [
 *     'enabled' => true,
 *     'global' => ['cost-tracking', 'budget-enforcement'],
 *     'available' => [
 *         'budget-enforcement' => BudgetEnforcementMiddleware::class,
 *         'cost-tracking' => CostTrackingMiddleware::class,
 *     ],
 * ],
 * ```
 *
 * Usage Examples:
 * ```php
 * // Via ConversationBuilder
 * $response = AI::conversation()
 *     ->middleware(['budget-enforcement', 'cost-tracking'])
 *     ->message('Generate a report')
 *     ->send();
 *
 * // Via Direct SendMessage
 * $response = AI::provider('openai')->sendMessage('Hello', [
 *     'middleware' => ['budget-enforcement']
 * ]);
 *
 * // Global middleware (applies to all requests)
 * AI::addGlobalMiddleware([
 *     BudgetEnforcementMiddleware::class,
 *     CostTrackingMiddleware::class,
 * ]);
 * ```
 *
 * @author JTD Laravel AI Package
 *
 * @since 1.0.0
 */
interface AIMiddlewareInterface
{
    /**
     * Handle the AI request through the middleware pipeline.
     *
     * Processes AI requests before they reach the providers, allowing for
     * request transformation, policy enforcement, cost tracking, context injection,
     * and routing optimization. The middleware must call $next($message) to continue
     * the pipeline or return an AIResponse to short-circuit execution.
     *
     * Middleware capabilities include:
     * - Message content modification (context injection, prompt enhancement)
     * - Provider and model routing decisions
     * - Budget and cost limit enforcement
     * - Request metadata and option manipulation
     * - Caching and performance optimization
     * - Rate limiting and throttling
     * - Logging and analytics tracking
     *
     * @param  AIMessage  $message  The AI message to process through middleware
     * @param  Closure  $next  The next middleware in the pipeline
     * @return AIResponse The processed AI response
     *
     * @throws \JTD\LaravelAI\Exceptions\BudgetExceededException When budget limits exceeded
     * @throws \JTD\LaravelAI\Exceptions\RateLimitException When rate limits exceeded
     * @throws \InvalidArgumentException When message data is invalid
     *
     * @since 1.0.0
     */
    public function handle(AIMessage $message, Closure $next): AIResponse;
}
