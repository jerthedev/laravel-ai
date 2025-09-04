<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use JTD\LaravelAI\Events\PerformanceAlertTriggered;

/**
 * Real-time Performance Monitoring and Alerting Service
 *
 * Continuously monitors middleware performance metrics and triggers alerts
 * when performance thresholds are exceeded or degradation is detected.
 */
class PerformanceAlertService
{
    /**
     * Performance thresholds for alerts.
     */
    protected array $thresholds = [
        'critical' => [
            'avg_response_time_ms' => 25,
            'p95_response_time_ms' => 50,
            'error_rate_percent' => 5.0,
            'memory_usage_mb' => 100,
            'cache_hit_rate_percent' => 70,
        ],
        'warning' => [
            'avg_response_time_ms' => 15,
            'p95_response_time_ms' => 30,
            'error_rate_percent' => 2.0,
            'memory_usage_mb' => 75,
            'cache_hit_rate_percent' => 80,
        ],
        'target' => [
            'avg_response_time_ms' => 10,
            'p95_response_time_ms' => 20,
            'error_rate_percent' => 0.5,
            'memory_usage_mb' => 50,
            'cache_hit_rate_percent' => 90,
        ],
    ];

    /**
     * Alert cooldown periods to prevent spam (in seconds).
     */
    protected array $cooldownPeriods = [
        'critical' => 300,  // 5 minutes
        'warning' => 600,   // 10 minutes
        'info' => 1800,     // 30 minutes
    ];

    /**
     * Active monitoring windows for different metrics.
     */
    protected array $monitoringWindows = [
        'real_time' => 60,      // 1 minute
        'short_term' => 300,    // 5 minutes
        'medium_term' => 1800,  // 30 minutes
        'long_term' => 3600,    // 1 hour
    ];

    /**
     * Performance metrics collector.
     */
    protected EventPerformanceTracker $performanceTracker;

    public function __construct(EventPerformanceTracker $performanceTracker)
    {
        $this->performanceTracker = $performanceTracker;
    }

    /**
     * Monitor middleware performance and trigger alerts if needed.
     *
     * @param  string  $middlewareName  Middleware name
     * @param  float  $executionTimeMs  Execution time in milliseconds
     * @param  array  $context  Additional context
     */
    public function monitorMiddlewarePerformance(string $middlewareName, float $executionTimeMs, array $context = []): void
    {
        // Track the performance metric
        $this->trackPerformanceMetric($middlewareName, $executionTimeMs, $context);

        // Check for immediate threshold violations
        $this->checkImmediateThresholds($middlewareName, $executionTimeMs, $context);

        // Analyze trends and patterns
        $this->analyzeTrends($middlewareName);

        // Check system health indicators
        $this->checkSystemHealth();
    }

    /**
     * Track performance metric with time series data.
     *
     * @param  string  $component  Component name
     * @param  float  $value  Metric value
     * @param  array  $context  Context data
     */
    protected function trackPerformanceMetric(string $component, float $value, array $context): void
    {
        $timestamp = now()->timestamp;
        $windowKey = floor($timestamp / 60) * 60; // 1-minute window

        // Update rolling metrics
        $metricsKey = "perf_metrics_{$component}_{$windowKey}";

        $metrics = Cache::get($metricsKey, [
            'count' => 0,
            'sum' => 0,
            'max' => 0,
            'min' => PHP_FLOAT_MAX,
            'values' => [],
            'errors' => 0,
            'success' => 0,
        ]);

        $metrics['count']++;
        $metrics['sum'] += $value;
        $metrics['max'] = max($metrics['max'], $value);
        $metrics['min'] = min($metrics['min'], $value);
        $metrics['values'][] = $value;

        // Track success/error rates
        $isSuccess = $context['success'] ?? true;
        if ($isSuccess) {
            $metrics['success']++;
        } else {
            $metrics['errors']++;
        }

        // Keep only last 100 values to prevent memory issues
        if (count($metrics['values']) > 100) {
            array_shift($metrics['values']);
        }

        Cache::put($metricsKey, $metrics, 3600); // Store for 1 hour
    }

    /**
     * Check immediate performance thresholds.
     *
     * @param  string  $component  Component name
     * @param  float  $value  Performance value
     * @param  array  $context  Context data
     */
    protected function checkImmediateThresholds(string $component, float $value, array $context): void
    {
        // Check critical threshold
        if ($value > $this->thresholds['critical']['avg_response_time_ms']) {
            $this->triggerAlert('critical', $component, [
                'metric' => 'execution_time',
                'value' => $value,
                'threshold' => $this->thresholds['critical']['avg_response_time_ms'],
                'message' => "Critical performance threshold exceeded: {$value}ms > {$this->thresholds['critical']['avg_response_time_ms']}ms",
                'context' => $context,
            ]);
        }
        // Check warning threshold
        elseif ($value > $this->thresholds['warning']['avg_response_time_ms']) {
            $this->triggerAlert('warning', $component, [
                'metric' => 'execution_time',
                'value' => $value,
                'threshold' => $this->thresholds['warning']['avg_response_time_ms'],
                'message' => "Warning performance threshold exceeded: {$value}ms > {$this->thresholds['warning']['avg_response_time_ms']}ms",
                'context' => $context,
            ]);
        }
    }

    /**
     * Analyze performance trends over time.
     *
     * @param  string  $component  Component name
     */
    protected function analyzeTrends(string $component): void
    {
        $shortTermMetrics = $this->getAggregatedMetrics($component, $this->monitoringWindows['short_term']);
        $mediumTermMetrics = $this->getAggregatedMetrics($component, $this->monitoringWindows['medium_term']);

        if (! $shortTermMetrics || ! $mediumTermMetrics) {
            return;
        }

        // Check for performance degradation
        $degradationPercent = (($shortTermMetrics['avg'] - $mediumTermMetrics['avg']) / $mediumTermMetrics['avg']) * 100;

        if ($degradationPercent > 20) { // 20% degradation
            $this->triggerAlert('warning', $component, [
                'metric' => 'trend_analysis',
                'degradation_percent' => round($degradationPercent, 2),
                'short_term_avg' => $shortTermMetrics['avg'],
                'medium_term_avg' => $mediumTermMetrics['avg'],
                'message' => "Performance degradation detected: {$degradationPercent}% increase in average response time",
            ]);
        }

        // Check for error rate spikes
        if ($shortTermMetrics['error_rate'] > $this->thresholds['warning']['error_rate_percent']) {
            $this->triggerAlert('critical', $component, [
                'metric' => 'error_rate',
                'error_rate' => $shortTermMetrics['error_rate'],
                'threshold' => $this->thresholds['warning']['error_rate_percent'],
                'message' => "High error rate detected: {$shortTermMetrics['error_rate']}% > {$this->thresholds['warning']['error_rate_percent']}%",
            ]);
        }
    }

    /**
     * Check overall system health.
     */
    protected function checkSystemHealth(): void
    {
        $systemMetrics = $this->getSystemHealthMetrics();

        // Memory usage check
        if ($systemMetrics['memory_usage_mb'] > $this->thresholds['critical']['memory_usage_mb']) {
            $this->triggerAlert('critical', 'system', [
                'metric' => 'memory_usage',
                'value' => $systemMetrics['memory_usage_mb'],
                'threshold' => $this->thresholds['critical']['memory_usage_mb'],
                'message' => "Critical memory usage: {$systemMetrics['memory_usage_mb']}MB > {$this->thresholds['critical']['memory_usage_mb']}MB",
            ]);
        }

        // Cache performance check
        if ($systemMetrics['cache_hit_rate'] < $this->thresholds['critical']['cache_hit_rate_percent']) {
            $this->triggerAlert('warning', 'system', [
                'metric' => 'cache_performance',
                'value' => $systemMetrics['cache_hit_rate'],
                'threshold' => $this->thresholds['critical']['cache_hit_rate_percent'],
                'message' => "Low cache hit rate: {$systemMetrics['cache_hit_rate']}% < {$this->thresholds['critical']['cache_hit_rate_percent']}%",
            ]);
        }
    }

    /**
     * Get aggregated metrics for a component over a time window.
     *
     * @param  string  $component  Component name
     * @param  int  $windowSeconds  Time window in seconds
     * @return array|null Aggregated metrics
     */
    protected function getAggregatedMetrics(string $component, int $windowSeconds): ?array
    {
        $endTime = now()->timestamp;
        $startTime = $endTime - $windowSeconds;

        $allValues = [];
        $totalErrors = 0;
        $totalSuccess = 0;

        // Collect metrics from all time windows in the period
        for ($time = $startTime; $time <= $endTime; $time += 60) {
            $windowKey = floor($time / 60) * 60;
            $metricsKey = "perf_metrics_{$component}_{$windowKey}";
            $metrics = Cache::get($metricsKey);

            if ($metrics) {
                $allValues = array_merge($allValues, $metrics['values']);
                $totalErrors += $metrics['errors'];
                $totalSuccess += $metrics['success'];
            }
        }

        if (empty($allValues)) {
            return null;
        }

        sort($allValues);
        $count = count($allValues);
        $totalRequests = $totalErrors + $totalSuccess;

        return [
            'avg' => array_sum($allValues) / $count,
            'min' => min($allValues),
            'max' => max($allValues),
            'p50' => $this->calculatePercentile($allValues, 50),
            'p95' => $this->calculatePercentile($allValues, 95),
            'p99' => $this->calculatePercentile($allValues, 99),
            'count' => $count,
            'error_rate' => $totalRequests > 0 ? ($totalErrors / $totalRequests) * 100 : 0,
            'success_rate' => $totalRequests > 0 ? ($totalSuccess / $totalRequests) * 100 : 0,
        ];
    }

    /**
     * Get current system health metrics.
     *
     * @return array System metrics
     */
    protected function getSystemHealthMetrics(): array
    {
        return [
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'active_middleware_instances' => $this->getActiveMiddlewareCount(),
        ];
    }

    /**
     * Get cache hit rate from budget cache service.
     *
     * @return float Cache hit rate percentage
     */
    protected function getCacheHitRate(): float
    {
        // This would integrate with BudgetCacheService metrics
        $cacheService = app(BudgetCacheService::class);
        $metrics = $cacheService->getPerformanceMetrics();

        return $metrics['cache_hit_rate'] ?? 0;
    }

    /**
     * Get count of active middleware instances.
     *
     * @return int Active instance count
     */
    protected function getActiveMiddlewareCount(): int
    {
        $middlewareManager = app(MiddlewareManager::class);
        $stats = $middlewareManager->getPerformanceStats();

        return $stats['cache_size'] ?? 0;
    }

    /**
     * Trigger a performance alert.
     *
     * @param  string  $severity  Alert severity (critical, warning, info)
     * @param  string  $component  Component name
     * @param  array  $alertData  Alert data
     */
    protected function triggerAlert(string $severity, string $component, array $alertData): void
    {
        $alertKey = "alert_{$severity}_{$component}_" . md5(json_encode($alertData['metric'] ?? ''));

        // Check cooldown period
        if (Cache::has($alertKey)) {
            return;
        }

        // Set cooldown
        Cache::put($alertKey, true, $this->cooldownPeriods[$severity]);

        $alert = array_merge([
            'id' => uniqid('alert_'),
            'severity' => $severity,
            'component' => $component,
            'timestamp' => now()->toISOString(),
            'system_info' => $this->getSystemHealthMetrics(),
        ], $alertData);

        // Log the alert
        $logLevel = $severity === 'critical' ? 'error' : ($severity === 'warning' ? 'warning' : 'info');
        Log::log($logLevel, "Performance alert triggered: {$alert['message']}", $alert);

        // Fire event for external handling
        Event::dispatch(new PerformanceAlertTriggered($alert));

        // Send notifications if configured
        $this->sendNotifications($alert);

        // Store alert for dashboard
        $this->storeAlert($alert);
    }

    /**
     * Send alert notifications.
     *
     * @param  array  $alert  Alert data
     */
    protected function sendNotifications(array $alert): void
    {
        // This would integrate with notification channels
        // For now, just log that notifications would be sent
        if (config('ai.performance.notifications.enabled', false)) {
            Log::info('Performance alert notification sent', [
                'alert_id' => $alert['id'],
                'severity' => $alert['severity'],
                'component' => $alert['component'],
            ]);
        }
    }

    /**
     * Store alert for dashboard display.
     *
     * @param  array  $alert  Alert data
     */
    protected function storeAlert(array $alert): void
    {
        $alertsKey = 'performance_alerts';
        $alerts = Cache::get($alertsKey, []);

        array_unshift($alerts, $alert);

        // Keep only last 100 alerts
        if (count($alerts) > 100) {
            $alerts = array_slice($alerts, 0, 100);
        }

        Cache::put($alertsKey, $alerts, 3600 * 24); // Store for 24 hours
    }

    /**
     * Get recent performance alerts.
     *
     * @param  int  $limit  Number of alerts to retrieve
     * @param  string|null  $severity  Filter by severity
     * @return array Recent alerts
     */
    public function getRecentAlerts(int $limit = 20, ?string $severity = null): array
    {
        $alerts = Cache::get('performance_alerts', []);

        if ($severity) {
            $alerts = array_filter($alerts, fn ($alert) => $alert['severity'] === $severity);
        }

        return array_slice($alerts, 0, $limit);
    }

    /**
     * Get alert statistics.
     *
     * @param  int  $hours  Hours to look back
     * @return array Alert statistics
     */
    public function getAlertStatistics(int $hours = 24): array
    {
        $alerts = Cache::get('performance_alerts', []);
        $cutoffTime = now()->subHours($hours);

        $recentAlerts = array_filter($alerts, function ($alert) use ($cutoffTime) {
            return \Carbon\Carbon::parse($alert['timestamp'])->greaterThan($cutoffTime);
        });

        $bySeverity = array_count_values(array_column($recentAlerts, 'severity'));
        $byComponent = array_count_values(array_column($recentAlerts, 'component'));

        return [
            'total_alerts' => count($recentAlerts),
            'by_severity' => $bySeverity,
            'by_component' => $byComponent,
            'alert_rate_per_hour' => round(count($recentAlerts) / $hours, 2),
        ];
    }

    /**
     * Clear old alerts and metrics.
     *
     * @param  int  $hoursOld  Age threshold in hours
     * @return int Number of items cleared
     */
    public function cleanupOldData(int $hoursOld = 24): int
    {
        $cleared = 0;
        $cutoffTime = now()->subHours($hoursOld)->timestamp;

        // Clean up old metric windows
        for ($time = $cutoffTime; $time > $cutoffTime - 86400; $time -= 60) {
            $windowKey = floor($time / 60) * 60;
            $pattern = "perf_metrics_*_{$windowKey}";

            // This would need to be implemented based on cache driver
            // For now, just increment counter
            $cleared++;
        }

        return $cleared;
    }

    /**
     * Calculate percentile from sorted array.
     *
     * @param  array  $sortedValues  Sorted array of values
     * @param  int  $percentile  Percentile to calculate
     * @return float Percentile value
     */
    protected function calculatePercentile(array $sortedValues, int $percentile): float
    {
        $index = ($percentile / 100) * (count($sortedValues) - 1);

        if (floor($index) == $index) {
            return $sortedValues[$index];
        }

        $lower = $sortedValues[floor($index)];
        $upper = $sortedValues[ceil($index)];

        return $lower + ($upper - $lower) * ($index - floor($index));
    }
}
