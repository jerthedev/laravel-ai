<?php

namespace JTD\LaravelAI\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Event Processing Performance Tracker
 *
 * Monitors event execution times, identifies bottlenecks, and provides
 * performance analytics for the event-driven AI system.
 */
class EventPerformanceTracker
{
    /**
     * Performance thresholds in milliseconds.
     */
    protected array $thresholds = [
        'event_processing' => 100,
        'listener_execution' => 50,
        'queue_job' => 500,
        'middleware' => 10,
    ];

    /**
     * Cache TTL for performance metrics (5 minutes).
     */
    protected int $cacheTtl = 300;

    /**
     * Track event processing performance.
     *
     * @param  string  $eventName  Event class name
     * @param  float  $duration  Duration in milliseconds
     * @param  array  $context  Additional context
     */
    public function trackEventProcessing(string $eventName, float $duration, array $context = []): void
    {
        $performanceData = [
            'event_name' => $eventName,
            'duration_ms' => $duration,
            'threshold_ms' => $this->thresholds['event_processing'],
            'exceeded_threshold' => $duration > $this->thresholds['event_processing'],
            'context' => $context,
            'timestamp' => now(),
        ];

        // Store detailed performance data
        $this->storePerformanceData('event_processing', $performanceData);

        // Update real-time metrics
        $this->updateRealTimeMetrics('event_processing', $eventName, $duration);

        // Check for performance issues
        if ($duration > $this->thresholds['event_processing']) {
            $this->handlePerformanceIssue('event_processing', $performanceData);
        }
    }

    /**
     * Track listener execution performance.
     *
     * @param  string  $listenerName  Listener class name
     * @param  string  $eventName  Event being handled
     * @param  float  $duration  Duration in milliseconds
     * @param  array  $context  Additional context
     */
    public function trackListenerExecution(string $listenerName, string $eventName, float $duration, array $context = []): void
    {
        $performanceData = [
            'listener_name' => $listenerName,
            'event_name' => $eventName,
            'duration_ms' => $duration,
            'threshold_ms' => $this->thresholds['listener_execution'],
            'exceeded_threshold' => $duration > $this->thresholds['listener_execution'],
            'context' => $context,
            'timestamp' => now(),
        ];

        // Store detailed performance data
        $this->storePerformanceData('listener_execution', $performanceData);

        // Update real-time metrics
        $this->updateRealTimeMetrics('listener_execution', $listenerName, $duration);

        // Check for performance issues
        if ($duration > $this->thresholds['listener_execution']) {
            $this->handlePerformanceIssue('listener_execution', $performanceData);
        }
    }

    /**
     * Track queue job performance.
     *
     * @param  string  $jobName  Job class name
     * @param  float  $duration  Duration in milliseconds
     * @param  array  $context  Additional context
     */
    public function trackQueueJobPerformance(string $jobName, float $duration, array $context = []): void
    {
        $performanceData = [
            'job_name' => $jobName,
            'duration_ms' => $duration,
            'threshold_ms' => $this->thresholds['queue_job'],
            'exceeded_threshold' => $duration > $this->thresholds['queue_job'],
            'context' => $context,
            'timestamp' => now(),
        ];

        // Store detailed performance data
        $this->storePerformanceData('queue_job', $performanceData);

        // Update real-time metrics
        $this->updateRealTimeMetrics('queue_job', $jobName, $duration);

        // Check for performance issues
        if ($duration > $this->thresholds['queue_job']) {
            $this->handlePerformanceIssue('queue_job', $performanceData);
        }
    }

    /**
     * Track middleware execution performance.
     *
     * @param  string  $middlewareName  Middleware class name
     * @param  float  $duration  Duration in milliseconds
     * @param  array  $context  Additional context
     */
    public function trackMiddlewarePerformance(string $middlewareName, float $duration, array $context = []): void
    {
        $performanceData = [
            'middleware_name' => $middlewareName,
            'duration_ms' => $duration,
            'threshold_ms' => $this->thresholds['middleware'],
            'exceeded_threshold' => $duration > $this->thresholds['middleware'],
            'context' => $context,
            'timestamp' => now(),
        ];

        // Store detailed performance data
        $this->storePerformanceData('middleware_execution', $performanceData);

        // Update real-time metrics
        $this->updateRealTimeMetrics('middleware_execution', $middlewareName, $duration);

        // Check for performance issues
        if ($duration > $this->thresholds['middleware']) {
            $this->handlePerformanceIssue('middleware_execution', $performanceData);
        }
    }

    /**
     * Get performance analytics for a specific component.
     *
     * @param  string  $component  Component type
     * @param  string  $timeframe  Timeframe (hour, day, week)
     * @return array Performance analytics
     */
    public function getPerformanceAnalytics(string $component, string $timeframe = 'hour'): array
    {
        $cacheKey = "performance_analytics_{$component}_{$timeframe}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($component, $timeframe) {
            $startTime = $this->getStartTime($timeframe);

            $metrics = DB::table('ai_performance_metrics')
                ->where('component', $component)
                ->where('created_at', '>=', $startTime)
                ->get();

            if ($metrics->isEmpty()) {
                return $this->getEmptyAnalytics($component);
            }

            return $this->calculateAnalytics($metrics, $component);
        });
    }

    /**
     * Get real-time performance dashboard data.
     *
     * @return array Dashboard data
     */
    public function getDashboardData(): array
    {
        $components = ['event_processing', 'listener_execution', 'queue_job', 'middleware_execution'];
        $dashboardData = [];

        foreach ($components as $component) {
            $dashboardData[$component] = [
                'current_metrics' => $this->getCurrentMetrics($component),
                'recent_performance' => $this->getRecentPerformance($component),
                'threshold_violations' => $this->getThresholdViolations($component),
                'trends' => $this->getPerformanceTrends($component),
            ];
        }

        return [
            'components' => $dashboardData,
            'overall_health' => $this->calculateOverallHealth($dashboardData),
            'alerts' => $this->getActiveAlerts(),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Get performance bottlenecks.
     *
     * @param  int  $limit  Number of bottlenecks to return
     * @return array Bottlenecks
     */
    public function getPerformanceBottlenecks(int $limit = 10): array
    {
        $cacheKey = "performance_bottlenecks_{$limit}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($limit) {
            $bottlenecks = DB::table('ai_performance_metrics')
                ->select([
                    'component',
                    'component_name',
                    DB::raw('AVG(duration_ms) as avg_duration'),
                    DB::raw('MAX(duration_ms) as max_duration'),
                    DB::raw('COUNT(*) as execution_count'),
                    DB::raw('SUM(CASE WHEN exceeded_threshold = 1 THEN 1 ELSE 0 END) as threshold_violations'),
                ])
                ->where('created_at', '>=', now()->subHour())
                ->groupBy(['component', 'component_name'])
                ->orderByDesc('avg_duration')
                ->limit($limit)
                ->get();

            return $bottlenecks->map(function ($bottleneck) {
                return [
                    'component' => $bottleneck->component,
                    'component_name' => $bottleneck->component_name,
                    'avg_duration_ms' => round($bottleneck->avg_duration, 2),
                    'max_duration_ms' => round($bottleneck->max_duration, 2),
                    'execution_count' => $bottleneck->execution_count,
                    'threshold_violations' => $bottleneck->threshold_violations,
                    'violation_rate' => round(($bottleneck->threshold_violations / $bottleneck->execution_count) * 100, 1),
                    'severity' => $this->calculateBottleneckSeverity($bottleneck),
                ];
            })->toArray();
        });
    }

    /**
     * Generate performance optimization recommendations.
     *
     * @return array Recommendations
     */
    public function getOptimizationRecommendations(): array
    {
        $bottlenecks = $this->getPerformanceBottlenecks(20);
        $recommendations = [];

        foreach ($bottlenecks as $bottleneck) {
            $recommendation = $this->generateRecommendation($bottleneck);
            if ($recommendation) {
                $recommendations[] = $recommendation;
            }
        }

        // Sort by priority
        usort($recommendations, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return [
            'recommendations' => array_slice($recommendations, 0, 10),
            'summary' => $this->generateRecommendationSummary($recommendations),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Store performance data.
     *
     * @param  string  $component  Component type
     * @param  array  $data  Performance data
     */
    protected function storePerformanceData(string $component, array $data): void
    {
        try {
            DB::table('ai_performance_metrics')->insert([
                'component' => $component,
                'component_name' => $data['listener_name'] ?? $data['event_name'] ?? $data['job_name'] ?? $data['middleware_name'] ?? 'unknown',
                'duration_ms' => $data['duration_ms'],
                'threshold_ms' => $data['threshold_ms'],
                'exceeded_threshold' => $data['exceeded_threshold'],
                'context_data' => json_encode($data['context']),
                'created_at' => $data['timestamp'],
                'updated_at' => $data['timestamp'],
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to store performance data', [
                'component' => $component,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update real-time metrics.
     *
     * @param  string  $component  Component type
     * @param  string  $name  Component name
     * @param  float  $duration  Duration in milliseconds
     */
    protected function updateRealTimeMetrics(string $component, string $name, float $duration): void
    {
        $metricsKey = "realtime_metrics_{$component}";
        $componentKey = "realtime_metrics_{$component}_{$name}";

        // Update component-level metrics
        Cache::increment("{$metricsKey}_count");
        Cache::increment("{$metricsKey}_total_duration", $duration);

        // Update specific component metrics
        Cache::increment("{$componentKey}_count");
        Cache::increment("{$componentKey}_total_duration", $duration);

        // Track max duration
        $currentMax = Cache::get("{$componentKey}_max_duration", 0);
        if ($duration > $currentMax) {
            Cache::put("{$componentKey}_max_duration", $duration, $this->cacheTtl);
        }

        // Set expiration for metrics (only if cache driver supports expire method)
        if (method_exists(Cache::getStore(), 'expire')) {
            Cache::expire("{$metricsKey}_count", $this->cacheTtl);
            Cache::expire("{$metricsKey}_total_duration", $this->cacheTtl);
            Cache::expire("{$componentKey}_count", $this->cacheTtl);
            Cache::expire("{$componentKey}_total_duration", $this->cacheTtl);
        }
    }

    /**
     * Handle performance issues.
     *
     * @param  string  $component  Component type
     * @param  array  $data  Performance data
     */
    protected function handlePerformanceIssue(string $component, array $data): void
    {
        // Log performance issue
        Log::warning('Performance threshold exceeded', [
            'component' => $component,
            'duration_ms' => $data['duration_ms'],
            'threshold_ms' => $data['threshold_ms'],
            'component_name' => $data['listener_name'] ?? $data['event_name'] ?? $data['job_name'] ?? $data['middleware_name'] ?? 'unknown',
            'context' => $data['context'],
        ]);

        // Increment violation counter
        Cache::increment("performance_violations_{$component}");
        if (method_exists(Cache::getStore(), 'expire')) {
            Cache::expire("performance_violations_{$component}", $this->cacheTtl);
        }

        // Fire performance alert event if configured
        if (config('ai.performance.alerts_enabled', true)) {
            event(new \JTD\LaravelAI\Events\PerformanceThresholdExceeded($component, $data));
        }
    }

    /**
     * Get start time for timeframe.
     *
     * @param  string  $timeframe  Timeframe
     * @return Carbon Start time
     */
    protected function getStartTime(string $timeframe): Carbon
    {
        return match ($timeframe) {
            'hour' => now()->subHour(),
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subHour(),
        };
    }

    /**
     * Get empty analytics structure.
     *
     * @param  string  $component  Component type
     * @return array Empty analytics
     */
    protected function getEmptyAnalytics(string $component): array
    {
        return [
            'component' => $component,
            'total_executions' => 0,
            'avg_duration_ms' => 0,
            'max_duration_ms' => 0,
            'min_duration_ms' => 0,
            'threshold_violations' => 0,
            'violation_rate' => 0,
            'performance_score' => 100,
        ];
    }

    /**
     * Calculate analytics from metrics.
     *
     * @param  \Illuminate\Support\Collection  $metrics  Metrics data
     * @param  string  $component  Component type
     * @return array Analytics
     */
    protected function calculateAnalytics($metrics, string $component): array
    {
        $durations = $metrics->pluck('duration_ms');
        $violations = $metrics->where('exceeded_threshold', true)->count();

        return [
            'component' => $component,
            'total_executions' => $metrics->count(),
            'avg_duration_ms' => round($durations->avg(), 2),
            'max_duration_ms' => round($durations->max(), 2),
            'min_duration_ms' => round($durations->min(), 2),
            'threshold_violations' => $violations,
            'violation_rate' => round(($violations / $metrics->count()) * 100, 1),
            'performance_score' => $this->calculatePerformanceScore($durations, $violations, $metrics->count()),
            'p95_duration_ms' => round($this->calculatePercentile($durations->toArray(), 95), 2),
            'p99_duration_ms' => round($this->calculatePercentile($durations->toArray(), 99), 2),
        ];
    }

    /**
     * Calculate performance score.
     *
     * @param  \Illuminate\Support\Collection  $durations  Duration data
     * @param  int  $violations  Number of violations
     * @param  int  $total  Total executions
     * @return int Performance score (0-100)
     */
    protected function calculatePerformanceScore($durations, int $violations, int $total): int
    {
        $violationPenalty = ($violations / $total) * 50; // Up to 50 point penalty
        $avgDurationPenalty = min(($durations->avg() / 100) * 25, 25); // Up to 25 point penalty

        return max(0, 100 - $violationPenalty - $avgDurationPenalty);
    }

    /**
     * Calculate percentile.
     *
     * @param  array  $values  Values array
     * @param  int  $percentile  Percentile (0-100)
     * @return float Percentile value
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);

        if (floor($index) == $index) {
            return $values[$index];
        }

        $lower = $values[floor($index)];
        $upper = $values[ceil($index)];

        return $lower + ($upper - $lower) * ($index - floor($index));
    }

    /**
     * Get current metrics for component.
     *
     * @param  string  $component  Component type
     * @return array Current metrics
     */
    protected function getCurrentMetrics(string $component): array
    {
        $count = Cache::get("realtime_metrics_{$component}_count", 0);
        $totalDuration = Cache::get("realtime_metrics_{$component}_total_duration", 0);

        return [
            'executions_last_5min' => $count,
            'avg_duration_ms' => $count > 0 ? round($totalDuration / $count, 2) : 0,
            'executions_per_minute' => round($count / 5, 1),
        ];
    }

    /**
     * Get recent performance data.
     *
     * @param  string  $component  Component type
     * @return array Recent performance
     */
    protected function getRecentPerformance(string $component): array
    {
        return DB::table('ai_performance_metrics')
            ->where('component', $component)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->selectRaw('
                AVG(duration_ms) as avg_duration,
                MAX(duration_ms) as max_duration,
                COUNT(*) as execution_count,
                SUM(CASE WHEN exceeded_threshold = 1 THEN 1 ELSE 0 END) as violations
            ')
            ->first() ?: (object) [
                'avg_duration' => 0,
                'max_duration' => 0,
                'execution_count' => 0,
                'violations' => 0,
            ];
    }

    /**
     * Get threshold violations.
     *
     * @param  string  $component  Component type
     * @return int Violation count
     */
    protected function getThresholdViolations(string $component): int
    {
        return Cache::get("performance_violations_{$component}", 0);
    }

    /**
     * Get performance trends.
     *
     * @param  string  $component  Component type
     * @return array Trends data
     */
    protected function getPerformanceTrends(string $component): array
    {
        $hourlyData = DB::table('ai_performance_metrics')
            ->where('component', $component)
            ->where('created_at', '>=', now()->subHours(24))
            ->selectRaw('
                HOUR(created_at) as hour,
                AVG(duration_ms) as avg_duration,
                COUNT(*) as execution_count
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return [
            'hourly_avg_duration' => $hourlyData->pluck('avg_duration', 'hour')->toArray(),
            'hourly_execution_count' => $hourlyData->pluck('execution_count', 'hour')->toArray(),
        ];
    }

    /**
     * Calculate overall system health.
     *
     * @param  array  $dashboardData  Dashboard data
     * @return array Overall health
     */
    protected function calculateOverallHealth(array $dashboardData): array
    {
        $totalViolations = 0;
        $totalExecutions = 0;
        $avgDurations = [];

        foreach ($dashboardData as $component => $data) {
            $recent = $data['recent_performance'];
            $totalViolations += $recent->violations;
            $totalExecutions += $recent->execution_count;

            if ($recent->avg_duration > 0) {
                $avgDurations[] = $recent->avg_duration;
            }
        }

        $overallViolationRate = $totalExecutions > 0 ? ($totalViolations / $totalExecutions) * 100 : 0;
        $overallAvgDuration = ! empty($avgDurations) ? array_sum($avgDurations) / count($avgDurations) : 0;

        $healthScore = max(0, 100 - ($overallViolationRate * 2) - min($overallAvgDuration / 10, 20));

        return [
            'score' => round($healthScore),
            'status' => $this->getHealthStatus($healthScore),
            'violation_rate' => round($overallViolationRate, 1),
            'avg_duration_ms' => round($overallAvgDuration, 2),
            'total_executions' => $totalExecutions,
        ];
    }

    /**
     * Get health status from score.
     *
     * @param  float  $score  Health score
     * @return string Health status
     */
    protected function getHealthStatus(float $score): string
    {
        if ($score >= 90) {
            return 'excellent';
        }
        if ($score >= 75) {
            return 'good';
        }
        if ($score >= 60) {
            return 'fair';
        }
        if ($score >= 40) {
            return 'poor';
        }

        return 'critical';
    }

    /**
     * Get active alerts.
     *
     * @return array Active alerts
     */
    protected function getActiveAlerts(): array
    {
        // This would typically query an alerts table or cache
        // For now, return empty array
        return [];
    }

    /**
     * Calculate bottleneck severity.
     *
     * @param  object  $bottleneck  Bottleneck data
     * @return string Severity level
     */
    protected function calculateBottleneckSeverity($bottleneck): string
    {
        $violationRate = ($bottleneck->threshold_violations / $bottleneck->execution_count) * 100;

        if ($violationRate >= 50 || $bottleneck->avg_duration >= 500) {
            return 'critical';
        }
        if ($violationRate >= 25 || $bottleneck->avg_duration >= 200) {
            return 'high';
        }
        if ($violationRate >= 10 || $bottleneck->avg_duration >= 100) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Generate recommendation for bottleneck.
     *
     * @param  array  $bottleneck  Bottleneck data
     * @return array|null Recommendation
     */
    protected function generateRecommendation(array $bottleneck): ?array
    {
        $component = $bottleneck['component'];
        $severity = $bottleneck['severity'];

        $recommendations = [
            'event_processing' => [
                'critical' => 'Consider breaking down complex event handlers into smaller, focused handlers',
                'high' => 'Review event handler logic for optimization opportunities',
                'medium' => 'Monitor event processing patterns for potential improvements',
            ],
            'listener_execution' => [
                'critical' => 'Move heavy processing to queued jobs to avoid blocking event handling',
                'high' => 'Optimize database queries and external API calls in listeners',
                'medium' => 'Consider caching frequently accessed data in listeners',
            ],
            'queue_job' => [
                'critical' => 'Break down large jobs into smaller, parallel jobs',
                'high' => 'Optimize job processing logic and reduce external dependencies',
                'medium' => 'Review job retry logic and failure handling',
            ],
            'middleware_execution' => [
                'critical' => 'Minimize middleware processing - move complex logic elsewhere',
                'high' => 'Optimize middleware database queries and caching',
                'medium' => 'Review middleware order and necessity',
            ],
        ];

        $message = $recommendations[$component][$severity] ?? null;

        if (! $message) {
            return null;
        }

        return [
            'component' => $component,
            'component_name' => $bottleneck['component_name'],
            'severity' => $severity,
            'priority' => $this->getSeverityPriority($severity),
            'message' => $message,
            'metrics' => [
                'avg_duration_ms' => $bottleneck['avg_duration_ms'],
                'violation_rate' => $bottleneck['violation_rate'],
                'execution_count' => $bottleneck['execution_count'],
            ],
        ];
    }

    /**
     * Get priority from severity.
     *
     * @param  string  $severity  Severity level
     * @return int Priority (higher = more important)
     */
    protected function getSeverityPriority(string $severity): int
    {
        return match ($severity) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }

    /**
     * Generate recommendation summary.
     *
     * @param  array  $recommendations  All recommendations
     * @return array Summary
     */
    protected function generateRecommendationSummary(array $recommendations): array
    {
        $severityCounts = array_count_values(array_column($recommendations, 'severity'));
        $componentCounts = array_count_values(array_column($recommendations, 'component'));

        return [
            'total_recommendations' => count($recommendations),
            'by_severity' => $severityCounts,
            'by_component' => $componentCounts,
            'top_priority' => $recommendations[0]['message'] ?? 'No recommendations',
        ];
    }
}
