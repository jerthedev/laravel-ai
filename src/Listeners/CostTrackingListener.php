<?php

namespace JTD\LaravelAI\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Services\PricingService;

/**
 * Cost Tracking Listener Foundation
 *
 * Handles cost calculation and budget monitoring in the background.
 * Processes ResponseGenerated events to calculate costs asynchronously
 * and fires CostCalculated events for further processing.
 */
class CostTrackingListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'ai-analytics';

    /**
     * The time (seconds) before the job should be processed.
     */
    public int $delay = 0;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new listener instance.
     */
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Handle the ResponseGenerated event for background cost calculation.
     */
    public function handle(ResponseGenerated $event): void
    {
        $startTime = microtime(true);

        try {
            // Calculate and record costs
            $cost = $this->calculateMessageCost($event->message, $event->response);

            // Fire cost calculated event
            event(new CostCalculated(
                userId: $this->getUserId($event),
                provider: $event->providerMetadata['provider'] ?? 'unknown',
                model: $event->providerMetadata['model'] ?? 'unknown',
                cost: $cost,
                inputTokens: $this->getInputTokens($event->response),
                outputTokens: $this->getOutputTokens($event->response),
                conversationId: $event->message->conversation_id,
                messageId: $event->message->id
            ));

            // Check budget thresholds (foundation - will be expanded in Sprint 4b)
            $this->checkBudgetThresholds($this->getUserId($event), $cost);
        } catch (\Exception $e) {
            // Log error but don't fail the job
            logger()->error('Cost tracking failed', [
                'event' => class_basename($event),
                'message_id' => $event->message->id ?? null,
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Track performance metrics
            $this->trackPerformance('handle', microtime(true) - $startTime);
        }
    }

    /**
     * Calculate the cost of a message and response using enhanced centralized pricing.
     *
     * @param  \JTD\LaravelAI\Models\AIMessage  $message  The message
     * @param  \JTD\LaravelAI\Models\AIResponse  $response  The response
     * @return float The calculated cost
     */
    protected function calculateMessageCost($message, $response): float
    {
        $inputTokens = $this->getInputTokens($response);
        $outputTokens = $this->getOutputTokens($response);
        $provider = $message->provider ?? 'openai';
        $model = $response->model ?? $message->model ?? 'gpt-4o-mini';

        try {
            // Use enhanced PricingService with database-first fallback
            $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

            // Log pricing source for monitoring
            if (isset($costData['source'])) {
                logger()->debug('Cost calculated using pricing source', [
                    'provider' => $provider,
                    'model' => $model,
                    'source' => $costData['source'],
                    'cost' => $costData['total_cost'] ?? 0.0,
                ]);
            }

            return $costData['total_cost'] ?? 0.0;
        } catch (\Exception $e) {
            // Log pricing service error
            logger()->warning('PricingService failed, using fallback calculation', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ]);

            // Enhanced fallback calculation with better rates
            return ($inputTokens / 1000 * 0.01) + ($outputTokens / 1000 * 0.02);
        }
    }

    /**
     * Get user ID from the event.
     *
     * @param  ResponseGenerated  $event  The event
     * @return int The user ID
     */
    protected function getUserId(ResponseGenerated $event): int
    {
        return $event->message->user_id ?? 0;
    }

    /**
     * Get input tokens from response.
     *
     * @param  \JTD\LaravelAI\Models\AIResponse  $response  The response
     * @return int The input token count
     */
    protected function getInputTokens($response): int
    {
        return $response->tokenUsage?->inputTokens ?? 0;
    }

    /**
     * Get output tokens from response.
     *
     * @param  \JTD\LaravelAI\Models\AIResponse  $response  The response
     * @return int The output token count
     */
    protected function getOutputTokens($response): int
    {
        return $response->tokenUsage?->outputTokens ?? 0;
    }

    /**
     * Check budget thresholds for the user (foundation implementation).
     *
     * @param  int  $userId  The user ID
     * @param  float  $cost  The cost to check
     */
    protected function checkBudgetThresholds(int $userId, float $cost): void
    {
        // Foundation implementation - will be expanded in Sprint 4b
        // For now, just log high-cost interactions
        if ($cost > 0.10) { // $0.10 threshold
            logger()->warning('High cost AI interaction detected', [
                'user_id' => $userId,
                'cost' => $cost,
                'threshold' => 0.10,
            ]);
        }
    }

    /**
     * Track listener performance metrics.
     *
     * @param  string  $method  The method name
     * @param  float  $executionTime  The execution time in seconds
     */
    protected function trackPerformance(string $method, float $executionTime): void
    {
        // Log slow listener execution
        if ($executionTime > 5.0) { // Log if over 5 seconds
            logger()->warning('Slow cost tracking listener', [
                'method' => $method,
                'execution_time' => $executionTime,
                'listener' => static::class,
            ]);
        }

        // Log performance metrics for monitoring
        logger()->debug('Cost tracking listener performance', [
            'method' => $method,
            'execution_time' => $executionTime,
            'queue' => $this->queue,
        ]);
    }
}
