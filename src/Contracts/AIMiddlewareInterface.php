<?php

namespace JTD\LaravelAI\Contracts;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * AI Middleware Interface
 *
 * TODO: This interface is a placeholder for the future middleware system implementation.
 *
 * The middleware system will provide a Laravel-familiar way to intercept, transform,
 * and enhance AI requests before they reach the AI providers. This enables sophisticated
 * request routing, context injection, pre-processing, and cost optimization.
 *
 * PLANNED FEATURES (to be implemented in future sprint):
 * - Smart routing based on complexity and cost
 * - Context injection using ConversationContextManager
 * - Budget enforcement and cost controls
 * - Pre-processing and request enhancement
 * - Caching and performance optimization
 *
 * INTEGRATION POINTS:
 * - ConversationContextManager.buildContextForMiddleware()
 * - ConversationContextManager.formatContextForInjection()
 * - ConversationContextManager.shouldInjectContext()
 *
 * EXAMPLE USAGE (when implemented):
 *
 * ```php
 * // Global middleware registration
 * AI::addGlobalMiddleware([
 *     SmartRouterMiddleware::class,
 *     ContextInjectionMiddleware::class,
 *     BudgetEnforcementMiddleware::class,
 * ]);
 *
 * // Per-conversation middleware
 * $response = AI::conversation('Enhanced Chat')
 *     ->middleware([ContextInjectionMiddleware::class])
 *     ->message('What was my favorite color?')
 *     ->send();
 * ```
 *
 * The ContextInjectionMiddleware will use the search-enhanced context retrieval
 * implemented in Story 5 to find relevant historical messages (like the "favorite color"
 * discussion) and inject them into the context automatically.
 */
interface AIMiddlewareInterface
{
    /**
     * Handle the AI request through the middleware pipeline.
     *
     * TODO: This method will be called by the middleware system to process
     * AI requests before they reach the providers.
     *
     * The middleware can:
     * - Modify the message content (add context, enhance prompts)
     * - Change routing (select different provider/model)
     * - Add metadata or options
     * - Implement caching or rate limiting
     * - Perform cost checks and budget enforcement
     *
     * @param  AIMessage  $message  The AI message to process
     * @param  \Closure  $next  The next middleware in the pipeline
     * @return AIResponse The processed response
     */
    public function handle(AIMessage $message, \Closure $next): AIResponse;
}

/**
 * TODO: Example ContextInjectionMiddleware implementation (placeholder)
 *
 * This middleware will be implemented in a future sprint and will use the
 * ConversationContextManager methods created in Story 5.
 *
 * ```php
 * class ContextInjectionMiddleware implements AIMiddlewareInterface
 * {
 *     protected ConversationContextManager $contextManager;
 *
 *     public function handle(AIMessage $message, \Closure $next): AIResponse
 *     {
 *         // Check if context injection should be applied
 *         if (!$this->contextManager->shouldInjectContext($message)) {
 *             return $next($message);
 *         }
 *
 *         // Get conversation from message
 *         $conversation = $message->conversation;
 *         if (!$conversation) {
 *             return $next($message);
 *         }
 *
 *         // Build intelligent context using search-enhanced retrieval
 *         $contextResult = $this->contextManager->buildContextForMiddleware(
 *             $conversation,
 *             $message,
 *             $this->getMiddlewareOptions()
 *         );
 *
 *         // Format context for injection
 *         $contextString = $this->contextManager->formatContextForInjection($contextResult);
 *
 *         // Inject context into message
 *         if (!empty($contextString)) {
 *             $originalContent = $message->content;
 *             $message->content = $contextString . "\nCurrent message: " . $originalContent;
 *
 *             Log::debug('Context injected by middleware', [
 *                 'conversation_id' => $conversation->id,
 *                 'context_messages_count' => count($contextResult['messages']),
 *                 'context_tokens' => $contextResult['total_tokens'],
 *                 'search_enhanced' => $contextResult['search_relevant_preserved'] ?? 0,
 *             ]);
 *         }
 *
 *         return $next($message);
 *     }
 * }
 * ```
 */

/**
 * TODO: Example SmartRouterMiddleware implementation (placeholder)
 *
 * ```php
 * class SmartRouterMiddleware implements AIMiddlewareInterface
 * {
 *     public function handle(AIMessage $message, \Closure $next): AIResponse
 *     {
 *         $complexity = $this->analyzeComplexity($message->content);
 *
 *         // Route based on complexity and context requirements
 *         if ($complexity < 0.3) {
 *             $message->provider = 'gemini';
 *             $message->model = 'gemini-pro';
 *         } elseif ($complexity > 0.8 || $this->requiresSearchEnhancedContext($message)) {
 *             $message->provider = 'openai';
 *             $message->model = 'gpt-4';
 *         }
 *
 *         return $next($message);
 *     }
 * }
 * ```
 */

/**
 * TODO: Example BudgetEnforcementMiddleware implementation (placeholder)
 *
 * ```php
 * class BudgetEnforcementMiddleware implements AIMiddlewareInterface
 * {
 *     public function handle(AIMessage $message, \Closure $next): AIResponse
 *     {
 *         $estimatedCost = $this->estimateRequestCost($message);
 *
 *         // Check budget limits
 *         $this->checkBudgetLimits($message->user_id, $estimatedCost);
 *
 *         $response = $next($message);
 *
 *         // Track actual cost
 *         $this->trackCost($message->user_id, $response->cost);
 *
 *         return $response;
 *     }
 * }
 * ```
 */
