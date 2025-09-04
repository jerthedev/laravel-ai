<?php

namespace JTD\LaravelAI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use JTD\LaravelAI\Services\EventPerformanceTracker;
use JTD\LaravelAI\Services\PerformanceAlertManager;
use JTD\LaravelAI\Services\PerformanceOptimizationEngine;
use JTD\LaravelAI\Services\QueuePerformanceMonitor;

/**
 * Performance Dashboard Controller
 *
 * Provides real-time performance monitoring dashboard with comprehensive
 * metrics, analytics, and optimization recommendations.
 */
class PerformanceDashboardController extends Controller
{
    /**
     * Event Performance Tracker.
     */
    protected EventPerformanceTracker $performanceTracker;

    /**
     * Queue Performance Monitor.
     */
    protected QueuePerformanceMonitor $queueMonitor;

    /**
     * Performance Alert Manager.
     */
    protected PerformanceAlertManager $alertManager;

    /**
     * Performance Optimization Engine.
     */
    protected PerformanceOptimizationEngine $optimizationEngine;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        EventPerformanceTracker $performanceTracker,
        QueuePerformanceMonitor $queueMonitor,
        PerformanceAlertManager $alertManager,
        PerformanceOptimizationEngine $optimizationEngine
    ) {
        $this->performanceTracker = $performanceTracker;
        $this->queueMonitor = $queueMonitor;
        $this->alertManager = $alertManager;
        $this->optimizationEngine = $optimizationEngine;
    }

    /**
     * Get comprehensive dashboard overview.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Dashboard overview
     */
    public function overview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'string|in:hour,day,week,month',
            'refresh' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $timeframe = $request->input('timeframe', 'hour');
        $refresh = $request->boolean('refresh', false);

        $cacheKey = "performance_dashboard_overview_{$timeframe}";
        $cacheTtl = 300; // 5 minutes

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        $overview = Cache::remember($cacheKey, $cacheTtl, function () use ($timeframe) {
            return [
                'system_health' => $this->getSystemHealthOverview($timeframe),
                'performance_metrics' => $this->getPerformanceMetricsOverview($timeframe),
                'queue_health' => $this->queueMonitor->getQueueHealth(),
                'bottlenecks' => $this->performanceTracker->getPerformanceBottlenecks(10),
                'alerts' => $this->getActiveAlerts(),
                'trends' => $this->getPerformanceTrends($timeframe),
                'recommendations' => $this->getTopRecommendations(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => array_merge($overview, [
                'timeframe' => $timeframe,
                'last_updated' => now()->toISOString(),
                'cache_hit' => ! $refresh,
            ]),
        ]);
    }

    /**
     * Get detailed component performance.
     *
     * @param  Request  $request  HTTP request
     * @param  string  $component  Component name
     * @return JsonResponse Component performance
     */
    public function componentPerformance(Request $request, string $component): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['component' => $component]), [
            'component' => 'required|string|in:event_processing,listener_execution,queue_job,middleware_execution',
            'timeframe' => 'string|in:hour,day,week,month',
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $timeframe = $request->input('timeframe', 'hour');
        $limit = $request->input('limit', 20);

        $analytics = $this->performanceTracker->getPerformanceAnalytics($component, $timeframe);
        $bottlenecks = $this->performanceTracker->getPerformanceBottlenecks($limit);
        $componentBottlenecks = array_filter($bottlenecks, fn ($b) => $b['component'] === $component);

        return response()->json([
            'success' => true,
            'data' => [
                'component' => $component,
                'timeframe' => $timeframe,
                'analytics' => $analytics,
                'bottlenecks' => array_values($componentBottlenecks),
                'recommendations' => $this->getComponentRecommendations($component, $analytics),
                'last_updated' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get queue performance dashboard.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Queue performance
     */
    public function queuePerformance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'connection' => 'nullable|string',
            'queue' => 'nullable|string',
            'timeframe' => 'string|in:hour,day,week,month',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $connection = $request->input('connection');
        $queue = $request->input('queue');
        $timeframe = $request->input('timeframe', 'hour');

        $queueHealth = $this->queueMonitor->getQueueHealth();
        $queueMetrics = $this->queueMonitor->getQueueMetrics($connection, $queue, $timeframe);
        $throughputAnalysis = $this->queueMonitor->getThroughputAnalysis($timeframe);
        $recommendations = $this->queueMonitor->getPerformanceRecommendations();

        return response()->json([
            'success' => true,
            'data' => [
                'queue_health' => $queueHealth,
                'queue_metrics' => $queueMetrics,
                'throughput_analysis' => $throughputAnalysis,
                'recommendations' => $recommendations,
                'filters' => [
                    'connection' => $connection,
                    'queue' => $queue,
                    'timeframe' => $timeframe,
                ],
                'last_updated' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get real-time performance metrics.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Real-time metrics
     */
    public function realTimeMetrics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'components' => 'array',
            'components.*' => 'string|in:event_processing,listener_execution,queue_job,middleware_execution',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $components = $request->input('components', [
            'event_processing',
            'listener_execution',
            'queue_job',
            'middleware_execution',
        ]);

        $realTimeData = [];
        foreach ($components as $component) {
            $realTimeData[$component] = $this->getRealTimeComponentData($component);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'components' => $realTimeData,
                'system_load' => $this->getSystemLoadMetrics(),
                'active_alerts' => $this->getActiveAlerts(),
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get performance trends.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Performance trends
     */
    public function trends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'string|in:day,week,month',
            'component' => 'nullable|string|in:event_processing,listener_execution,queue_job,middleware_execution',
            'granularity' => 'string|in:hour,day',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $timeframe = $request->input('timeframe', 'day');
        $component = $request->input('component');
        $granularity = $request->input('granularity', 'hour');

        $trends = $this->getDetailedPerformanceTrends($timeframe, $component, $granularity);

        return response()->json([
            'success' => true,
            'data' => [
                'trends' => $trends,
                'timeframe' => $timeframe,
                'component' => $component,
                'granularity' => $granularity,
                'last_updated' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get optimization recommendations.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Optimization recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'severity' => 'nullable|string|in:low,medium,high,critical',
            'component' => 'nullable|string|in:event_processing,listener_execution,queue_job,middleware_execution',
            'limit' => 'integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $severity = $request->input('severity');
        $component = $request->input('component');
        $limit = $request->input('limit', 20);

        $recommendations = $this->optimizationEngine->generateOptimizationRecommendations([
            'timeframe' => 'day',
            'include_queue' => true,
            'min_priority' => $severity ? $this->getSeverityPriority($severity) : 1,
        ]);
        $queueRecommendations = $this->queueMonitor->getPerformanceRecommendations();

        // Filter recommendations
        $filteredRecommendations = $this->filterRecommendations(
            $recommendations['recommendations'],
            $severity,
            $component,
            $limit
        );

        return response()->json([
            'success' => true,
            'data' => [
                'performance_recommendations' => $filteredRecommendations,
                'queue_recommendations' => $queueRecommendations,
                'summary' => $this->generateRecommendationsSummary($filteredRecommendations, $queueRecommendations),
                'filters' => [
                    'severity' => $severity,
                    'component' => $component,
                    'limit' => $limit,
                ],
                'last_updated' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Export performance report.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Export result
     */
    public function exportReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:json,csv,pdf',
            'timeframe' => 'string|in:hour,day,week,month',
            'components' => 'array',
            'components.*' => 'string|in:event_processing,listener_execution,queue_job,middleware_execution',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $format = $request->input('format');
        $timeframe = $request->input('timeframe', 'day');
        $components = $request->input('components', [
            'event_processing',
            'listener_execution',
            'queue_job',
            'middleware_execution',
        ]);

        try {
            $reportData = $this->generatePerformanceReport($timeframe, $components);
            $exportResult = $this->exportReportData($reportData, $format);

            return response()->json([
                'success' => true,
                'data' => $exportResult,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Export failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system health overview.
     */
    protected function getSystemHealthOverview(string $timeframe): array
    {
        $dashboardData = $this->performanceTracker->getDashboardData();
        $queueHealth = $this->queueMonitor->getQueueHealth();

        return [
            'overall_score' => $dashboardData['overall_health']['score'],
            'status' => $dashboardData['overall_health']['status'],
            'component_health' => array_map(fn ($comp) => [
                'score' => $this->calculateComponentHealthScore($comp),
                'status' => $this->getHealthStatus($this->calculateComponentHealthScore($comp)),
            ], $dashboardData['components']),
            'queue_health' => $queueHealth['overall_health'],
            'active_alerts' => count($this->getActiveAlerts()),
        ];
    }

    /**
     * Get performance metrics overview.
     */
    protected function getPerformanceMetricsOverview(string $timeframe): array
    {
        $components = ['event_processing', 'listener_execution', 'queue_job', 'middleware_execution'];
        $metrics = [];

        foreach ($components as $component) {
            $analytics = $this->performanceTracker->getPerformanceAnalytics($component, $timeframe);
            $metrics[$component] = [
                'total_executions' => $analytics['total_executions'],
                'avg_duration_ms' => $analytics['avg_duration_ms'],
                'violation_rate' => $analytics['violation_rate'],
                'performance_score' => $analytics['performance_score'],
            ];
        }

        return $metrics;
    }

    /**
     * Get performance trends.
     */
    protected function getPerformanceTrends(string $timeframe): array
    {
        // This would typically query historical data
        // For now, return placeholder structure
        return [
            'duration_trend' => 'stable',
            'throughput_trend' => 'increasing',
            'error_rate_trend' => 'decreasing',
            'trend_data' => [],
        ];
    }

    /**
     * Get top recommendations.
     */
    protected function getTopRecommendations(): array
    {
        $recommendations = $this->performanceTracker->getOptimizationRecommendations();

        return array_slice($recommendations['recommendations'], 0, 5);
    }

    /**
     * Get active alerts.
     */
    protected function getActiveAlerts(): array
    {
        return $this->alertManager->getActiveAlerts();
    }

    /**
     * Get component recommendations.
     */
    protected function getComponentRecommendations(string $component, array $analytics): array
    {
        $recommendations = [];

        if ($analytics['violation_rate'] > 10) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'message' => "High violation rate ({$analytics['violation_rate']}%) - review component performance",
            ];
        }

        if ($analytics['avg_duration_ms'] > 100) {
            $recommendations[] = [
                'type' => 'optimization',
                'priority' => 'medium',
                'message' => "Average duration ({$analytics['avg_duration_ms']}ms) exceeds optimal range",
            ];
        }

        return $recommendations;
    }

    /**
     * Get real-time component data.
     */
    protected function getRealTimeComponentData(string $component): array
    {
        $cacheKey = "realtime_metrics_{$component}";

        return [
            'executions_last_5min' => Cache::get("{$cacheKey}_count", 0),
            'avg_duration_ms' => $this->calculateRealtimeAverage($component),
            'current_load' => $this->getCurrentComponentLoad($component),
            'status' => $this->getComponentStatus($component),
        ];
    }

    /**
     * Get system load metrics.
     */
    protected function getSystemLoadMetrics(): array
    {
        return [
            'cpu_usage' => 0, // Would integrate with system monitoring
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'active_processes' => 0,
        ];
    }

    /**
     * Get detailed performance trends.
     */
    protected function getDetailedPerformanceTrends(string $timeframe, ?string $component, string $granularity): array
    {
        // This would query historical performance data
        // For now, return placeholder structure
        return [
            'timeframe' => $timeframe,
            'granularity' => $granularity,
            'data_points' => [],
            'trend_analysis' => [
                'direction' => 'stable',
                'strength' => 'weak',
                'confidence' => 0.75,
            ],
        ];
    }

    /**
     * Filter recommendations.
     */
    protected function filterRecommendations(array $recommendations, ?string $severity, ?string $component, int $limit): array
    {
        $filtered = $recommendations;

        if ($severity) {
            $filtered = array_filter($filtered, fn ($rec) => $rec['severity'] === $severity);
        }

        if ($component) {
            $filtered = array_filter($filtered, fn ($rec) => $rec['component'] === $component);
        }

        return array_slice(array_values($filtered), 0, $limit);
    }

    /**
     * Generate recommendations summary.
     */
    protected function generateRecommendationsSummary(array $performanceRecs, array $queueRecs): array
    {
        return [
            'total_recommendations' => count($performanceRecs) + count($queueRecs['queue_recommendations']),
            'critical_count' => count(array_filter($performanceRecs, fn ($rec) => $rec['severity'] === 'critical')),
            'high_priority_count' => count(array_filter($performanceRecs, fn ($rec) => $rec['priority'] === 4)),
            'top_priority' => $performanceRecs[0]['message'] ?? 'No recommendations',
        ];
    }

    /**
     * Generate performance report.
     */
    protected function generatePerformanceReport(string $timeframe, array $components): array
    {
        $report = [
            'metadata' => [
                'generated_at' => now()->toISOString(),
                'timeframe' => $timeframe,
                'components' => $components,
            ],
            'system_overview' => $this->getSystemHealthOverview($timeframe),
            'component_analytics' => [],
            'queue_performance' => $this->queueMonitor->getQueueHealth(),
            'recommendations' => $this->performanceTracker->getOptimizationRecommendations(),
        ];

        foreach ($components as $component) {
            $report['component_analytics'][$component] = $this->performanceTracker->getPerformanceAnalytics($component, $timeframe);
        }

        return $report;
    }

    /**
     * Export report data.
     */
    protected function exportReportData(array $reportData, string $format): array
    {
        $filename = 'performance_report_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        // This would implement actual export logic
        return [
            'filename' => $filename,
            'format' => $format,
            'size' => strlen(json_encode($reportData)),
            'download_url' => "/api/ai/performance/download/{$filename}",
        ];
    }

    /**
     * Calculate component health score.
     */
    protected function calculateComponentHealthScore(array $componentData): int
    {
        // Simplified health score calculation
        $recentPerf = $componentData['recent_performance'];
        $violations = $componentData['threshold_violations'];

        $score = 100;
        if ($violations > 0) {
            $score -= min($violations * 5, 50);
        }
        if ($recentPerf->avg_duration > 100) {
            $score -= 20;
        }

        return max(0, $score);
    }

    /**
     * Get health status from score.
     */
    protected function getHealthStatus(int $score): string
    {
        if ($score >= 90) {
            return 'excellent';
        }
        if ($score >= 80) {
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
     * Calculate realtime average.
     */
    protected function calculateRealtimeAverage(string $component): float
    {
        $count = Cache::get("realtime_metrics_{$component}_count", 0);
        $total = Cache::get("realtime_metrics_{$component}_total_duration", 0);

        return $count > 0 ? round($total / $count, 2) : 0;
    }

    /**
     * Get current component load.
     */
    protected function getCurrentComponentLoad(string $component): string
    {
        $count = Cache::get("realtime_metrics_{$component}_count", 0);

        if ($count > 100) {
            return 'high';
        }
        if ($count > 50) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get component status.
     */
    protected function getComponentStatus(string $component): string
    {
        $violations = Cache::get("performance_violations_{$component}", 0);

        if ($violations > 10) {
            return 'critical';
        }
        if ($violations > 5) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Get priority from severity.
     */
    protected function getSeverityPriority(string $severity): int
    {
        return match ($severity) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 1,
        };
    }
}
