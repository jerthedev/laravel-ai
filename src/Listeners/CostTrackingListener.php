<?php

namespace JTD\LaravelAI\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\ResponseGenerated;

/**
 * Listener for tracking AI usage costs in the background.
 *
 * This listener processes cost-related events and updates cost tracking
 * records, budget monitoring, and usage analytics.
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
     * Handle the CostCalculated event.
     */
    public function handle(CostCalculated $event): void
    {
        try {
            // Store cost tracking record
            $this->storeCostRecord($event);

            // Update user/conversation totals
            $this->updateCostTotals($event);

            // Check budget thresholds
            $this->checkBudgetThresholds($event);

            // Update analytics
            $this->updateCostAnalytics($event);

        } catch (\Exception $e) {
            // Log error but don't fail the job
            logger()->error('Cost tracking failed', [
                'event' => class_basename($event),
                'conversation_id' => $event->conversationId,
                'provider' => $event->provider,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the ResponseGenerated event for cost calculation.
     */
    public function handleResponseGenerated(ResponseGenerated $event): void
    {
        try {
            // Calculate cost if not already calculated
            if (!$this->hasCostCalculated($event)) {
                $this->calculateAndFireCostEvent($event);
            }

        } catch (\Exception $e) {
            logger()->error('Response cost calculation failed', [
                'event' => class_basename($event),
                'conversation_id' => $event->conversationId,
                'provider' => $event->provider,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Store cost tracking record in database.
     */
    protected function storeCostRecord(CostCalculated $event): void
    {
        // This would typically insert into ai_cost_tracking table
        // For now, we'll log the cost information
        logger()->info('AI Cost Tracked', [
            'conversation_id' => $event->conversationId,
            'user_id' => $event->userId,
            'provider' => $event->provider,
            'model' => $event->model,
            'total_cost' => $event->totalCost,
            'input_cost' => $event->inputCost,
            'output_cost' => $event->outputCost,
            'input_tokens' => $event->response->tokenUsage->inputTokens,
            'output_tokens' => $event->response->tokenUsage->outputTokens,
            'calculated_at' => $event->calculatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update cost totals for user and conversation.
     */
    protected function updateCostTotals(CostCalculated $event): void
    {
        // Update conversation total cost
        if ($event->conversationId) {
            // This would typically update ai_conversations table
            logger()->debug('Updating conversation cost total', [
                'conversation_id' => $event->conversationId,
                'additional_cost' => $event->totalCost,
            ]);
        }

        // Update user total cost
        if ($event->userId) {
            // This would typically update user cost tracking
            logger()->debug('Updating user cost total', [
                'user_id' => $event->userId,
                'additional_cost' => $event->totalCost,
            ]);
        }
    }

    /**
     * Check if cost exceeds budget thresholds.
     */
    protected function checkBudgetThresholds(CostCalculated $event): void
    {
        // Check daily/monthly budget limits
        $dailyLimit = config('ai.cost_tracking.daily_limit', 10.0);
        $monthlyLimit = config('ai.cost_tracking.monthly_limit', 100.0);

        // This would typically check against actual usage
        if ($event->totalCost > 1.0) { // Example threshold
            logger()->warning('High cost AI interaction', [
                'conversation_id' => $event->conversationId,
                'cost' => $event->totalCost,
                'threshold' => 1.0,
            ]);
        }
    }

    /**
     * Update cost analytics.
     */
    protected function updateCostAnalytics(CostCalculated $event): void
    {
        // Update analytics tables for reporting
        logger()->debug('Updating cost analytics', [
            'provider' => $event->provider,
            'model' => $event->model,
            'cost' => $event->totalCost,
            'date' => $event->calculatedAt->format('Y-m-d'),
        ]);
    }

    /**
     * Check if cost has already been calculated for this response.
     */
    protected function hasCostCalculated(ResponseGenerated $event): bool
    {
        // This would typically check if a CostCalculated event was already fired
        // For now, assume it hasn't been calculated
        return false;
    }

    /**
     * Calculate cost and fire CostCalculated event.
     */
    protected function calculateAndFireCostEvent(ResponseGenerated $event): void
    {
        // This would typically use a cost calculation service
        // For now, we'll create a mock cost calculation
        $mockCost = $event->response->tokenUsage->totalTokens * 0.00002; // Mock pricing

        $costEvent = new CostCalculated(
            $event->message,
            $event->response,
            $event->provider,
            $event->model,
            $mockCost,
            $mockCost * 0.6, // Mock input cost
            $mockCost * 0.4, // Mock output cost
            ['mock' => true],
            $event->conversationId,
            $event->userId
        );

        event($costEvent);
    }
}
