<?php

namespace JTD\LaravelAI\Middleware;

use Closure;
use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Services\BudgetService;
use JTD\LaravelAI\Services\PricingService;

/**
 * Budget Enforcement Middleware
 *
 * Enforces spending limits and provides cost controls at the request level.
 * Checks budgets before API calls and fires threshold events for monitoring.
 */
class BudgetEnforcementMiddleware implements AIMiddlewareInterface
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected BudgetService $budgetService,
        protected PricingService $pricingService
    ) {}

    /**
     * Handle the AI request through budget enforcement.
     *
     * @param  AIMessage  $message  The AI message to process
     * @param  Closure  $next  The next middleware in the pipeline
     * @return AIResponse The processed response
     */
    public function handle(AIMessage $message, Closure $next): AIResponse
    {
        // Estimate cost before making the request
        $estimatedCost = $this->estimateRequestCost($message);

        // Check all applicable budgets
        $this->budgetService->checkBudgetLimits($message->user_id, $estimatedCost, [
            'project_id' => $message->metadata['project_id'] ?? null,
        ]);

        // Proceed with request
        $response = $next($message);

        // The actual cost tracking will happen via events in background
        return $response;
    }

    /**
     * Estimate the cost of a request before sending to AI provider.
     * Uses centralized PricingService for accurate cost estimation.
     *
     * @param  AIMessage  $message  The message to estimate cost for
     * @return float The estimated cost in USD
     */
    protected function estimateRequestCost(AIMessage $message): float
    {
        $estimatedTokens = $this->estimateTokens($message->content);
        $provider = $message->provider ?? 'openai';
        $model = $message->model ?? $this->getDefaultModel($provider);

        // Use centralized pricing service for cost calculation
        $inputTokens = (int) ($estimatedTokens * 0.75); // Estimate 75% input
        $outputTokens = (int) ($estimatedTokens * 0.25); // Estimate 25% output

        try {
            $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

            return $costData['total_cost'] ?? $estimatedTokens * 0.00001;
        } catch (\Exception $e) {
            // Fallback to simple estimation if pricing service fails
            return $estimatedTokens * 0.00001;
        }
    }

    /**
     * Estimate token count from content.
     *
     * @param  string  $content  The content to estimate
     * @return int The estimated token count
     */
    protected function estimateTokens(string $content): int
    {
        // Rough estimation: 1 token ≈ 4 characters for English text
        return (int) ceil(strlen($content) / 4);
    }

    /**
     * Get cost estimate using the enhanced centralized PricingService.
     *
     * @param  string  $provider  The provider name
     * @param  int  $tokens  The estimated token count
     * @param  string  $model  The model name
     * @return float The estimated cost
     */
    protected function getProviderCostEstimate(string $provider, int $tokens, string $model): float
    {
        try {
            // Use the enhanced PricingService with database-first fallback
            $inputTokens = (int) ($tokens * 0.75); // Estimate 75% input
            $outputTokens = (int) ($tokens * 0.25); // Estimate 25% output

            $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

            return $costData['total_cost'] ?? $tokens * 0.00001;
        } catch (\Exception $e) {
            // Fallback on error
            return $tokens * 0.00001;
        }
    }

    /**
     * Get default model for a provider.
     *
     * @param  string  $provider  The provider name
     * @return string The default model
     */
    protected function getDefaultModel(string $provider): string
    {
        return match ($provider) {
            'openai' => 'gpt-4o-mini',
            'gemini' => 'gemini-2.0-flash',
            'xai' => 'grok-2-1212',
            default => 'gpt-4o-mini',
        };
    }
}
