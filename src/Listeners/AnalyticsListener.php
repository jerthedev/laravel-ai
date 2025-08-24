<?php

namespace JTD\LaravelAI\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\UsageAnalyticsRecorded;

/**
 * Enhanced Analytics Listener
 *
 * Processes usage events with <100ms per event processing time and comprehensive
 * reporting capabilities. Handles real-time analytics, aggregated metrics,
 * performance tracking, and usage pattern analysis.
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
     * Performance target in milliseconds.
     */
    protected int $performanceTargetMs = 100;

    /**
     * Cache TTL for analytics data (5 minutes).
     */
    protected int $analyticsCacheTtl = 300;

    /**
     * Handle the ResponseGenerated event for enhanced analytics tracking.
     */
    public function handle(ResponseGenerated $event): void
    {
        $startTime = microtime(true);

        try {
            // Process analytics with performance optimization
            $this->processAnalyticsOptimized($event);

            // Track performance against target
            $this->trackEnhancedPerformance('handle', microtime(true) - $startTime, $event);

        } catch (\Exception $e) {
            // Enhanced error handling with context
            $this->handleAnalyticsError($e, $event, microtime(true) - $startTime);
        }
    }

    /**
     * Process analytics with optimized performance.
     *
     * @param  ResponseGenerated  $event  The event
     */
    protected function processAnalyticsOptimized(ResponseGenerated $event): void
    {
        // Batch operations for performance
        $analyticsData = $this->prepareAnalyticsData($event);

        // Store real-time analytics
        $this->storeRealTimeAnalytics($analyticsData);

        // Update aggregated metrics (cached)
        $this->updateAggregatedMetrics($analyticsData);

        // Track usage patterns
        $this->trackUsagePatterns($analyticsData);

        // Fire analytics recorded event
        $this->fireAnalyticsEvent($analyticsData);
    }

    /**
     * Handle the CostCalculated event for enhanced cost analytics.
     */
    public function handleCostCalculated(CostCalculated $event): void
    {
        $startTime = microtime(true);

        try {
            // Process cost analytics with optimization
            $this->processCostAnalyticsOptimized($event);

            // Track performance
            $this->trackEnhancedPerformance('handleCostCalculated', microtime(true) - $startTime, $event);

        } catch (\Exception $e) {
            Log::error('Enhanced cost analytics tracking failed', [
                'event' => class_basename($event),
                'user_id' => $event->userId,
                'error' => $e->getMessage(),
                'processing_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);
        }
    }

    /**
     * Process cost analytics with optimization.
     *
     * @param  CostCalculated  $event  The event
     */
    protected function processCostAnalyticsOptimized(CostCalculated $event): void
    {
        // Prepare cost analytics data
        $costData = $this->prepareCostAnalyticsData($event);

        // Store cost analytics
        $this->storeCostAnalytics($costData);

        // Update cost aggregations
        $this->updateCostAggregations($costData);

        // Track spending patterns
        $this->trackSpendingPatterns($costData);
    }

    /**
     * Prepare comprehensive analytics data from event.
     *
     * @param  ResponseGenerated  $event  The event
     * @return array Analytics data
     */
    protected function prepareAnalyticsData(ResponseGenerated $event): array
    {
        $message = $event->message;
        $response = $event->response;
        $metadata = $event->providerMetadata;

        return [
            'user_id' => $message->user_id,
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
            'response_id' => $response->id,
            'provider' => $metadata['provider'] ?? 'unknown',
            'model' => $metadata['model'] ?? 'unknown',
            'input_tokens' => $this->getTokenCount($response, 'input'),
            'output_tokens' => $this->getTokenCount($response, 'output'),
            'total_tokens' => $this->getTokenCount($response, 'total'),
            'processing_time_ms' => $metadata['processing_time'] ?? 0,
            'response_time_ms' => $metadata['response_time'] ?? 0,
            'success' => $response->success ?? true,
            'error_type' => $response->error_type ?? null,
            'content_length' => strlen($message->content ?? ''),
            'response_length' => strlen($response->content ?? ''),
            'context' => $event->context ?? [],
            'timestamp' => now(),
            'date' => now()->toDateString(),
            'hour' => now()->hour,
            'day_of_week' => now()->dayOfWeek,
            'week_of_year' => now()->weekOfYear,
            'month' => now()->month,
            'quarter' => now()->quarter,
            'year' => now()->year,
        ];
    }

    /**
     * Store real-time analytics data.
     *
     * @param  array  $analyticsData  Analytics data
     */
    protected function storeRealTimeAnalytics(array $analyticsData): void
    {
        try {
            // Store in ai_usage_analytics table
            DB::table('ai_usage_analytics')->insert([
                'user_id' => $analyticsData['user_id'],
                'conversation_id' => $analyticsData['conversation_id'],
                'message_id' => $analyticsData['message_id'],
                'response_id' => $analyticsData['response_id'],
                'provider' => $analyticsData['provider'],
                'model' => $analyticsData['model'],
                'input_tokens' => $analyticsData['input_tokens'],
                'output_tokens' => $analyticsData['output_tokens'],
                'total_tokens' => $analyticsData['total_tokens'],
                'processing_time_ms' => $analyticsData['processing_time_ms'],
                'response_time_ms' => $analyticsData['response_time_ms'],
                'success' => $analyticsData['success'],
                'error_type' => $analyticsData['error_type'],
                'content_length' => $analyticsData['content_length'],
                'response_length' => $analyticsData['response_length'],
                'context_data' => json_encode($analyticsData['context']),
                'created_at' => $analyticsData['timestamp'],
                'updated_at' => $analyticsData['timestamp'],
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to store real-time analytics', [
                'error' => $e->getMessage(),
                'user_id' => $analyticsData['user_id'],
            ]);
        }
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

    // Enhanced analytics methods

    /**
     * Update aggregated metrics with caching.
     *
     * @param  array  $analyticsData  Analytics data
     */
    protected function updateAggregatedMetrics(array $analyticsData): void
    {
        // Update daily aggregations
        $this->updateDailyAggregations($analyticsData);

        // Update hourly aggregations
        $this->updateHourlyAggregations($analyticsData);

        // Update provider/model aggregations
        $this->updateProviderModelAggregations($analyticsData);

        // Update user aggregations
        $this->updateUserAggregations($analyticsData);
    }

    /**
     * Track usage patterns for insights.
     *
     * @param  array  $analyticsData  Analytics data
     */
    protected function trackUsagePatterns(array $analyticsData): void
    {
        // Track peak usage times
        $this->trackPeakUsageTimes($analyticsData);

        // Track model preferences
        $this->trackModelPreferences($analyticsData);

        // Track conversation patterns
        $this->trackConversationPatterns($analyticsData);

        // Track performance patterns
        $this->trackPerformancePatterns($analyticsData);
    }

    /**
     * Fire analytics recorded event.
     *
     * @param  array  $analyticsData  Analytics data
     */
    protected function fireAnalyticsEvent(array $analyticsData): void
    {
        event(new UsageAnalyticsRecorded($analyticsData));
    }

    /**
     * Prepare cost analytics data.
     *
     * @param  CostCalculated  $event  The event
     * @return array Cost analytics data
     */
    protected function prepareCostAnalyticsData(CostCalculated $event): array
    {
        return [
            'user_id' => $event->userId,
            'provider' => $event->provider,
            'model' => $event->model,
            'input_tokens' => $event->inputTokens,
            'output_tokens' => $event->outputTokens,
            'total_tokens' => $event->totalTokens,
            'input_cost' => $event->inputCost,
            'output_cost' => $event->outputCost,
            'total_cost' => $event->totalCost,
            'currency' => $event->currency,
            'cost_per_token' => $event->totalTokens > 0 ? $event->totalCost / $event->totalTokens : 0,
            'timestamp' => now(),
            'date' => now()->toDateString(),
            'hour' => now()->hour,
            'month' => now()->month,
            'year' => now()->year,
        ];
    }

    /**
     * Store cost analytics data.
     *
     * @param  array  $costData  Cost analytics data
     */
    protected function storeCostAnalytics(array $costData): void
    {
        try {
            DB::table('ai_cost_analytics')->insert([
                'user_id' => $costData['user_id'],
                'provider' => $costData['provider'],
                'model' => $costData['model'],
                'input_tokens' => $costData['input_tokens'],
                'output_tokens' => $costData['output_tokens'],
                'total_tokens' => $costData['total_tokens'],
                'input_cost' => $costData['input_cost'],
                'output_cost' => $costData['output_cost'],
                'total_cost' => $costData['total_cost'],
                'currency' => $costData['currency'],
                'cost_per_token' => $costData['cost_per_token'],
                'created_at' => $costData['timestamp'],
                'updated_at' => $costData['timestamp'],
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to store cost analytics', [
                'error' => $e->getMessage(),
                'user_id' => $costData['user_id'],
            ]);
        }
    }

    /**
     * Update cost aggregations.
     *
     * @param  array  $costData  Cost data
     */
    protected function updateCostAggregations(array $costData): void
    {
        // Update daily cost aggregations
        $this->incrementCachedMetric("daily_cost_{$costData['user_id']}_{$costData['date']}", $costData['total_cost']);

        // Update monthly cost aggregations
        $monthKey = $costData['year'] . '-' . str_pad($costData['month'], 2, '0', STR_PAD_LEFT);
        $this->incrementCachedMetric("monthly_cost_{$costData['user_id']}_{$monthKey}", $costData['total_cost']);

        // Update provider cost aggregations
        $this->incrementCachedMetric("provider_cost_{$costData['provider']}_{$costData['date']}", $costData['total_cost']);

        // Update model cost aggregations
        $this->incrementCachedMetric("model_cost_{$costData['model']}_{$costData['date']}", $costData['total_cost']);
    }

    // Helper methods for enhanced analytics

    /**
     * Get token count from response.
     *
     * @param  mixed  $response  Response object
     * @param  string  $type  Token type (input, output, total)
     * @return int Token count
     */
    protected function getTokenCount($response, string $type): int
    {
        return $response->token_usage[$type] ?? 0;
    }

    /**
     * Update daily aggregations.
     *
     * @param  array  $data  Analytics data
     */
    protected function updateDailyAggregations(array $data): void
    {
        $key = "daily_analytics_{$data['date']}";
        $this->incrementCachedMetric($key . '_requests', 1);
        $this->incrementCachedMetric($key . '_tokens', $data['total_tokens']);
        $this->updateCachedAverage($key . '_avg_processing_time', $data['processing_time_ms']);
    }

    /**
     * Update hourly aggregations.
     *
     * @param  array  $data  Analytics data
     */
    protected function updateHourlyAggregations(array $data): void
    {
        $key = "hourly_analytics_{$data['date']}_{$data['hour']}";
        $this->incrementCachedMetric($key . '_requests', 1);
        $this->incrementCachedMetric($key . '_tokens', $data['total_tokens']);
    }

    /**
     * Update provider/model aggregations.
     *
     * @param  array  $data  Analytics data
     */
    protected function updateProviderModelAggregations(array $data): void
    {
        // Provider aggregations
        $providerKey = "provider_analytics_{$data['provider']}_{$data['date']}";
        $this->incrementCachedMetric($providerKey . '_requests', 1);
        $this->incrementCachedMetric($providerKey . '_tokens', $data['total_tokens']);

        // Model aggregations
        $modelKey = "model_analytics_{$data['model']}_{$data['date']}";
        $this->incrementCachedMetric($modelKey . '_requests', 1);
        $this->incrementCachedMetric($modelKey . '_tokens', $data['total_tokens']);
        $this->updateCachedAverage($modelKey . '_avg_processing_time', $data['processing_time_ms']);
    }

    /**
     * Update user aggregations.
     *
     * @param  array  $data  Analytics data
     */
    protected function updateUserAggregations(array $data): void
    {
        $key = "user_analytics_{$data['user_id']}_{$data['date']}";
        $this->incrementCachedMetric($key . '_requests', 1);
        $this->incrementCachedMetric($key . '_tokens', $data['total_tokens']);
        $this->incrementCachedMetric($key . '_conversations', 1, $data['conversation_id']);
    }

    /**
     * Track peak usage times.
     *
     * @param  array  $data  Analytics data
     */
    protected function trackPeakUsageTimes(array $data): void
    {
        $hourKey = "peak_usage_hour_{$data['hour']}";
        $dayKey = "peak_usage_day_{$data['day_of_week']}";

        $this->incrementCachedMetric($hourKey, 1);
        $this->incrementCachedMetric($dayKey, 1);
    }

    /**
     * Track model preferences.
     *
     * @param  array  $data  Analytics data
     */
    protected function trackModelPreferences(array $data): void
    {
        $userModelKey = "user_model_preference_{$data['user_id']}_{$data['model']}";
        $this->incrementCachedMetric($userModelKey, 1);
    }

    /**
     * Track conversation patterns.
     *
     * @param  array  $data  Analytics data
     */
    protected function trackConversationPatterns(array $data): void
    {
        if ($data['conversation_id']) {
            $convKey = "conversation_length_{$data['conversation_id']}";
            $this->incrementCachedMetric($convKey, 1);
        }
    }

    /**
     * Track performance patterns.
     *
     * @param  array  $data  Analytics data
     */
    protected function trackPerformancePatterns(array $data): void
    {
        $processingTime = $data['processing_time_ms'];

        if ($processingTime > 5000) {
            $this->incrementCachedMetric('slow_requests_count', 1);
        }

        $this->updateCachedAverage("avg_processing_time_{$data['provider']}", $processingTime);
    }

    /**
     * Increment cached metric.
     *
     * @param  string  $key  Cache key
     * @param  float  $value  Value to increment
     * @param  string|null  $uniqueId  Unique identifier for deduplication
     */
    protected function incrementCachedMetric(string $key, float $value, ?string $uniqueId = null): void
    {
        if ($uniqueId) {
            $uniqueKey = $key . '_unique_' . md5($uniqueId);
            if (Cache::has($uniqueKey)) {
                return; // Already counted
            }
            Cache::put($uniqueKey, true, $this->analyticsCacheTtl);
        }

        Cache::increment($key, $value);
        Cache::expire($key, $this->analyticsCacheTtl);
    }

    /**
     * Update cached average.
     *
     * @param  string  $key  Cache key
     * @param  float  $value  New value
     */
    protected function updateCachedAverage(string $key, float $value): void
    {
        $countKey = $key . '_count';
        $sumKey = $key . '_sum';

        Cache::increment($countKey, 1);
        Cache::increment($sumKey, $value);

        Cache::expire($countKey, $this->analyticsCacheTtl);
        Cache::expire($sumKey, $this->analyticsCacheTtl);
    }

    /**
     * Track enhanced performance metrics.
     *
     * @param  string  $operation  Operation name
     * @param  float  $duration  Duration in seconds
     * @param  mixed  $event  Event context
     */
    protected function trackEnhancedPerformance(string $operation, float $duration, $event): void
    {
        $durationMs = $duration * 1000;

        // Performance tracking with context
        $performanceData = [
            'operation' => $operation,
            'duration_ms' => round($durationMs, 2),
            'target_ms' => $this->performanceTargetMs,
            'user_id' => $this->getUserIdFromEvent($event),
            'provider' => $this->getProviderFromEvent($event),
        ];

        // Log slow operations
        if ($durationMs > $this->performanceTargetMs) {
            Log::warning('Analytics processing exceeded performance target', array_merge($performanceData, [
                'performance_impact' => 'high',
                'optimization_needed' => true,
            ]));
        }

        // Track performance metrics
        Cache::increment("analytics_operations_total");
        Cache::increment("analytics_duration_total", $durationMs);
        Cache::increment("analytics_operation_{$operation}");
    }

    /**
     * Handle analytics processing errors.
     *
     * @param  \Exception  $e  The exception
     * @param  mixed  $event  Event context
     * @param  float  $duration  Processing duration
     */
    protected function handleAnalyticsError(\Exception $e, $event, float $duration): void
    {
        $context = [
            'event' => class_basename($event),
            'user_id' => $this->getUserIdFromEvent($event),
            'provider' => $this->getProviderFromEvent($event),
            'error' => $e->getMessage(),
            'processing_time_ms' => $duration * 1000,
        ];

        Log::error('Enhanced analytics processing failed', $context);

        // Track error metrics
        Cache::increment("analytics_errors_total");
        Cache::increment("analytics_error_" . class_basename($e));
    }

    /**
     * Get user ID from event.
     *
     * @param  mixed  $event  Event
     * @return int|null User ID
     */
    protected function getUserIdFromEvent($event): ?int
    {
        if (isset($event->message->user_id)) {
            return $event->message->user_id;
        }

        if (isset($event->userId)) {
            return $event->userId;
        }

        return null;
    }

    /**
     * Get provider from event.
     *
     * @param  mixed  $event  Event
     * @return string|null Provider
     */
    protected function getProviderFromEvent($event): ?string
    {
        if (isset($event->providerMetadata['provider'])) {
            return $event->providerMetadata['provider'];
        }

        if (isset($event->provider)) {
            return $event->provider;
        }

        return null;
    }

    /**
     * Store performance metric for analysis.
     *
     * @param  array  $performanceData  Performance data
     */
    protected function storePerformanceMetric(array $performanceData): void
    {
        try {
            DB::table('ai_performance_metrics')->insert([
                'operation' => $performanceData['operation'],
                'duration_ms' => $performanceData['duration_ms'],
                'target_ms' => $performanceData['target_ms'],
                'user_id' => $performanceData['user_id'],
                'provider' => $performanceData['provider'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail to avoid impacting main analytics flow
        }
    }
}
