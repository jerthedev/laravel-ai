<?php

namespace JTD\LaravelAI\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Events\CostCalculated;

/**
 * Analytics Listener Foundation
 *
 * Handles usage analytics and metrics collection in the background.
 * Processes AI events to track usage patterns, performance metrics,
 * and user behavior analytics.
 */
class AnalyticsListener implements ShouldQueue
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
     * Handle the ResponseGenerated event for analytics tracking.
     */
    public function handle(ResponseGenerated $event): void
    {
        $startTime = microtime(true);

        try {
            // Track usage metrics
            $this->trackUsageMetrics($event);

            // Track performance metrics
            $this->trackPerformanceMetrics($event);

            // Track provider usage
            $this->trackProviderUsage($event);

            // Track model usage
            $this->trackModelUsage($event);

        } catch (\Exception $e) {
            // Log error but don't fail the job
            logger()->error('Analytics tracking failed', [
                'event' => class_basename($event),
                'message_id' => $event->message->id ?? null,
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Track performance metrics
            $this->trackListenerPerformance('handle', microtime(true) - $startTime);
        }
    }

    /**
     * Handle the CostCalculated event for cost analytics.
     */
    public function handleCostCalculated(CostCalculated $event): void
    {
        try {
            // Track cost analytics
            $this->trackCostAnalytics($event);

            // Track spending patterns
            $this->trackSpendingPatterns($event);

        } catch (\Exception $e) {
            logger()->error('Cost analytics tracking failed', [
                'event' => class_basename($event),
                'user_id' => $event->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track usage metrics from response events.
     *
     * @param  ResponseGenerated  $event  The event
     */
    protected function trackUsageMetrics(ResponseGenerated $event): void
    {
        // Foundation implementation - will be expanded in Sprint 4b
        logger()->info('AI Usage Tracked', [
            'user_id' => $event->message->user_id ?? 0,
            'provider' => $event->providerMetadata['provider'] ?? 'unknown',
            'model' => $event->providerMetadata['model'] ?? 'unknown',
            'tokens_used' => $event->providerMetadata['tokens_used'] ?? 0,
            'processing_time' => $event->totalProcessingTime,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track performance metrics.
     *
     * @param  ResponseGenerated  $event  The event
     */
    protected function trackPerformanceMetrics(ResponseGenerated $event): void
    {
        // Foundation implementation - will be expanded in Sprint 4b
        $processingTime = $event->totalProcessingTime;

        if ($processingTime > 10.0) { // Log slow responses (>10 seconds)
            logger()->warning('Slow AI response detected', [
                'processing_time' => $processingTime,
                'provider' => $event->providerMetadata['provider'] ?? 'unknown',
                'model' => $event->providerMetadata['model'] ?? 'unknown',
                'user_id' => $event->message->user_id ?? 0,
            ]);
        }
    }

    /**
     * Track provider usage statistics.
     *
     * @param  ResponseGenerated  $event  The event
     */
    protected function trackProviderUsage(ResponseGenerated $event): void
    {
        // Foundation implementation - will be expanded in Sprint 4b
        logger()->debug('Provider usage tracked', [
            'provider' => $event->providerMetadata['provider'] ?? 'unknown',
            'success' => true,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track model usage statistics.
     *
     * @param  ResponseGenerated  $event  The event
     */
    protected function trackModelUsage(ResponseGenerated $event): void
    {
        // Foundation implementation - will be expanded in Sprint 4b
        logger()->debug('Model usage tracked', [
            'model' => $event->providerMetadata['model'] ?? 'unknown',
            'provider' => $event->providerMetadata['provider'] ?? 'unknown',
            'tokens' => $event->providerMetadata['tokens_used'] ?? 0,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track cost analytics from cost events.
     *
     * @param  CostCalculated  $event  The event
     */
    protected function trackCostAnalytics(CostCalculated $event): void
    {
        // Foundation implementation - will be expanded in Sprint 4b
        logger()->info('Cost analytics tracked', [
            'user_id' => $event->userId,
            'provider' => $event->provider,
            'model' => $event->model,
            'cost' => $event->cost,
            'input_tokens' => $event->inputTokens,
            'output_tokens' => $event->outputTokens,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track spending patterns for budget optimization.
     *
     * @param  CostCalculated  $event  The event
     */
    protected function trackSpendingPatterns(CostCalculated $event): void
    {
        // Foundation implementation - will be expanded in Sprint 4b
        logger()->debug('Spending pattern tracked', [
            'user_id' => $event->userId,
            'cost' => $event->cost,
            'hour' => now()->hour,
            'day_of_week' => now()->dayOfWeek,
            'provider' => $event->provider,
        ]);
    }

    /**
     * Track listener performance metrics.
     *
     * @param  string  $method  The method name
     * @param  float  $executionTime  The execution time in seconds
     */
    protected function trackListenerPerformance(string $method, float $executionTime): void
    {
        // Log slow listener execution
        if ($executionTime > 3.0) { // Log if over 3 seconds
            logger()->warning('Slow analytics listener', [
                'method' => $method,
                'execution_time' => $executionTime,
                'listener' => static::class,
            ]);
        }

        // Log performance metrics for monitoring
        logger()->debug('Analytics listener performance', [
            'method' => $method,
            'execution_time' => $executionTime,
            'queue' => $this->queue,
        ]);
    }
}
