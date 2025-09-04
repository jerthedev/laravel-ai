<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Queue Performance Monitor
 *
 * Monitors queue job performance with <500ms completion targets,
 * tracks job throughput, failure rates, and queue health metrics.
 */
class QueuePerformanceMonitor
{
    /**
     * Event Performance Tracker.
     */
    protected EventPerformanceTracker $performanceTracker;

    /**
     * Performance thresholds.
     */
    protected array $thresholds = [
        'job_completion' => 500,    // 500ms
        'queue_wait_time' => 1000,  // 1 second
        'failure_rate' => 5,        // 5%
        'throughput_min' => 10,     // 10 jobs/minute
    ];

    /**
     * Active job tracking.
     */
    protected array $activeJobs = [];

    /**
     * Queue metrics cache TTL (5 minutes).
     */
    protected int $cacheTtl = 300;

    /**
     * Create a new monitor instance.
     */
    public function __construct(EventPerformanceTracker $performanceTracker)
    {
        $this->performanceTracker = $performanceTracker;
    }

    /**
     * Handle job queued event.
     *
     * @param  JobQueued  $event  Job queued event
     */
    public function handleJobQueued(JobQueued $event): void
    {
        $jobId = $this->getJobId($event);
        $jobName = $event->job->displayName();

        $this->trackJobQueued($jobId, $jobName, $event->connectionName, $event->queue);
        $this->updateQueueMetrics($event->connectionName, $event->queue, 'queued');
    }

    /**
     * Handle job processing start.
     *
     * @param  JobProcessing  $event  Job processing event
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        $jobId = $event->job->getJobId();
        $jobName = $event->job->getName();

        $this->activeJobs[$jobId] = [
            'name' => $jobName,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'start_time' => microtime(true),
            'queued_at' => $this->getJobQueuedTime($jobId),
            'attempts' => $event->job->attempts(),
        ];

        // Calculate wait time
        $queuedAt = $this->activeJobs[$jobId]['queued_at'];
        if ($queuedAt) {
            $waitTime = (microtime(true) - $queuedAt) * 1000;
            $this->trackQueueWaitTime($jobName, $waitTime, $event->connectionName, $event->job->getQueue());
        }

        $this->updateQueueMetrics($event->connectionName, $event->job->getQueue(), 'processing');
    }

    /**
     * Handle job processing completion.
     *
     * @param  JobProcessed  $event  Job processed event
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        $jobId = $event->job->getJobId();

        if (! isset($this->activeJobs[$jobId])) {
            return;
        }

        $jobData = $this->activeJobs[$jobId];
        $duration = (microtime(true) - $jobData['start_time']) * 1000;

        // Track job completion performance
        $this->performanceTracker->trackQueueJobPerformance($jobData['name'], $duration, [
            'connection' => $jobData['connection'],
            'queue' => $jobData['queue'],
            'attempts' => $jobData['attempts'],
            'success' => true,
            'memory_usage' => memory_get_usage(true),
        ]);

        $this->updateQueueMetrics($jobData['connection'], $jobData['queue'], 'completed');
        $this->updateJobThroughput($jobData['connection'], $jobData['queue']);

        unset($this->activeJobs[$jobId]);
    }

    /**
     * Handle job failure.
     *
     * @param  JobFailed  $event  Job failed event
     */
    public function handleJobFailed(JobFailed $event): void
    {
        $jobId = $event->job->getJobId();
        $jobName = $event->job->getName();

        $duration = 0;
        if (isset($this->activeJobs[$jobId])) {
            $jobData = $this->activeJobs[$jobId];
            $duration = (microtime(true) - $jobData['start_time']) * 1000;
            unset($this->activeJobs[$jobId]);
        }

        // Track job failure
        $this->performanceTracker->trackQueueJobPerformance($jobName, $duration, [
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
            'success' => false,
            'error' => $event->exception->getMessage(),
            'error_type' => get_class($event->exception),
        ]);

        $this->updateQueueMetrics($event->connectionName, $event->job->getQueue(), 'failed');
        $this->trackJobFailure($jobName, $event->connectionName, $event->job->getQueue(), $event->exception);
    }

    /**
     * Get queue performance metrics.
     *
     * @param  string|null  $connection  Connection name
     * @param  string|null  $queue  Queue name
     * @param  string  $timeframe  Timeframe (hour, day, week)
     * @return array Performance metrics
     */
    public function getQueueMetrics(?string $connection = null, ?string $queue = null, string $timeframe = 'hour'): array
    {
        $cacheKey = "queue_metrics_{$connection}_{$queue}_{$timeframe}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($connection, $queue, $timeframe) {
            $startTime = $this->getStartTime($timeframe);

            $query = DB::table('ai_performance_metrics')
                ->where('component', 'queue_job')
                ->where('created_at', '>=', $startTime);

            if ($connection) {
                $query->whereJsonContains('context_data->connection', $connection);
            }

            if ($queue) {
                $query->whereJsonContains('context_data->queue', $queue);
            }

            $metrics = $query->get();

            return $this->calculateQueueMetrics($metrics, $connection, $queue, $timeframe);
        });
    }

    /**
     * Get queue health status.
     *
     * @return array Health status
     */
    public function getQueueHealth(): array
    {
        $connections = $this->getActiveConnections();
        $healthData = [];

        foreach ($connections as $connection) {
            $queues = $this->getActiveQueues($connection);

            foreach ($queues as $queue) {
                $metrics = $this->getQueueMetrics($connection, $queue, 'hour');
                $health = $this->calculateQueueHealth($metrics);

                $healthData["{$connection}:{$queue}"] = $health;
            }
        }

        return [
            'queues' => $healthData,
            'overall_health' => $this->calculateOverallQueueHealth($healthData),
            'alerts' => $this->getQueueAlerts($healthData),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Get queue throughput analysis.
     *
     * @param  string  $timeframe  Timeframe
     * @return array Throughput analysis
     */
    public function getThroughputAnalysis(string $timeframe = 'hour'): array
    {
        $startTime = $this->getStartTime($timeframe);

        $throughputData = DB::table('ai_performance_metrics')
            ->select([
                DB::raw('JSON_EXTRACT(context_data, "$.connection") as connection'),
                DB::raw('JSON_EXTRACT(context_data, "$.queue") as queue'),
                DB::raw('COUNT(*) as job_count'),
                DB::raw('AVG(duration_ms) as avg_duration'),
                DB::raw('SUM(CASE WHEN JSON_EXTRACT(context_data, "$.success") = true THEN 1 ELSE 0 END) as successful_jobs'),
            ])
            ->where('component', 'queue_job')
            ->where('created_at', '>=', $startTime)
            ->groupBy(['connection', 'queue'])
            ->get();

        $analysis = [];
        foreach ($throughputData as $data) {
            $connection = trim($data->connection, '"');
            $queue = trim($data->queue, '"');
            $key = "{$connection}:{$queue}";

            $timeframeMinutes = $this->getTimeframeMinutes($timeframe);
            $throughputPerMinute = $data->job_count / $timeframeMinutes;
            $successRate = ($data->successful_jobs / $data->job_count) * 100;

            $analysis[$key] = [
                'connection' => $connection,
                'queue' => $queue,
                'total_jobs' => $data->job_count,
                'throughput_per_minute' => round($throughputPerMinute, 2),
                'avg_duration_ms' => round($data->avg_duration, 2),
                'success_rate' => round($successRate, 1),
                'health_score' => $this->calculateThroughputHealthScore($throughputPerMinute, $successRate, $data->avg_duration),
            ];
        }

        return [
            'timeframe' => $timeframe,
            'queues' => $analysis,
            'summary' => $this->generateThroughputSummary($analysis),
        ];
    }

    /**
     * Get queue performance recommendations.
     *
     * @return array Recommendations
     */
    public function getPerformanceRecommendations(): array
    {
        $queueHealth = $this->getQueueHealth();
        $throughputAnalysis = $this->getThroughputAnalysis('hour');
        $recommendations = [];

        foreach ($queueHealth['queues'] as $queueKey => $health) {
            $queueRecommendations = $this->generateQueueRecommendations($queueKey, $health, $throughputAnalysis['queues'][$queueKey] ?? []);

            if (! empty($queueRecommendations)) {
                $recommendations[$queueKey] = $queueRecommendations;
            }
        }

        return [
            'queue_recommendations' => $recommendations,
            'general_recommendations' => $this->generateGeneralRecommendations($queueHealth, $throughputAnalysis),
            'priority_actions' => $this->getPriorityActions($recommendations),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Track job queued.
     */
    protected function trackJobQueued(string $jobId, string $jobName, string $connection, ?string $queue): void
    {
        Cache::put("job_queued_{$jobId}", microtime(true), 3600); // 1 hour TTL
        Cache::increment("queue_jobs_queued_{$connection}_{$queue}");
    }

    /**
     * Track queue wait time.
     */
    protected function trackQueueWaitTime(string $jobName, float $waitTime, string $connection, ?string $queue): void
    {
        $this->performanceTracker->trackQueueJobPerformance("{$jobName}_wait_time", $waitTime, [
            'connection' => $connection,
            'queue' => $queue,
            'metric_type' => 'wait_time',
            'exceeded_threshold' => $waitTime > $this->thresholds['queue_wait_time'],
        ]);
    }

    /**
     * Update queue metrics.
     */
    protected function updateQueueMetrics(string $connection, ?string $queue, string $action): void
    {
        $key = "queue_metrics_{$connection}_{$queue}";
        Cache::increment("{$key}_{$action}");
        Cache::expire("{$key}_{$action}", $this->cacheTtl);
    }

    /**
     * Update job throughput.
     */
    protected function updateJobThroughput(string $connection, ?string $queue): void
    {
        $key = "queue_throughput_{$connection}_{$queue}";
        $minute = now()->format('Y-m-d H:i');
        Cache::increment("{$key}_{$minute}");
        Cache::expire("{$key}_{$minute}", 3600); // 1 hour TTL
    }

    /**
     * Track job failure.
     */
    protected function trackJobFailure(string $jobName, string $connection, ?string $queue, \Throwable $exception): void
    {
        Log::warning('Queue job failed', [
            'job_name' => $jobName,
            'connection' => $connection,
            'queue' => $queue,
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception),
        ]);

        Cache::increment("queue_failures_{$connection}_{$queue}");
        Cache::expire("queue_failures_{$connection}_{$queue}", $this->cacheTtl);
    }

    /**
     * Get job ID from event.
     */
    protected function getJobId($event): string
    {
        return method_exists($event->job, 'getJobId')
            ? $event->job->getJobId()
            : uniqid('job_', true);
    }

    /**
     * Get job queued time.
     */
    protected function getJobQueuedTime(string $jobId): ?float
    {
        return Cache::get("job_queued_{$jobId}");
    }

    /**
     * Get start time for timeframe.
     */
    protected function getStartTime(string $timeframe): \Carbon\Carbon
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
     * Get timeframe in minutes.
     */
    protected function getTimeframeMinutes(string $timeframe): int
    {
        return match ($timeframe) {
            'hour' => 60,
            'day' => 1440,
            'week' => 10080,
            'month' => 43200,
            default => 60,
        };
    }

    /**
     * Calculate queue metrics.
     */
    protected function calculateQueueMetrics($metrics, ?string $connection, ?string $queue, string $timeframe): array
    {
        if ($metrics->isEmpty()) {
            return $this->getEmptyQueueMetrics($connection, $queue);
        }

        $totalJobs = $metrics->count();
        $successfulJobs = $metrics->where('context_data->success', true)->count();
        $failedJobs = $totalJobs - $successfulJobs;
        $durations = $metrics->pluck('duration_ms');

        return [
            'connection' => $connection,
            'queue' => $queue,
            'timeframe' => $timeframe,
            'total_jobs' => $totalJobs,
            'successful_jobs' => $successfulJobs,
            'failed_jobs' => $failedJobs,
            'success_rate' => $totalJobs > 0 ? round(($successfulJobs / $totalJobs) * 100, 1) : 0,
            'failure_rate' => $totalJobs > 0 ? round(($failedJobs / $totalJobs) * 100, 1) : 0,
            'avg_duration_ms' => round($durations->avg(), 2),
            'max_duration_ms' => round($durations->max(), 2),
            'min_duration_ms' => round($durations->min(), 2),
            'throughput_per_minute' => round($totalJobs / $this->getTimeframeMinutes($timeframe), 2),
        ];
    }

    /**
     * Calculate queue health.
     */
    protected function calculateQueueHealth(array $metrics): array
    {
        $healthScore = 100;
        $issues = [];

        // Check failure rate
        if ($metrics['failure_rate'] > $this->thresholds['failure_rate']) {
            $healthScore -= 30;
            $issues[] = "High failure rate: {$metrics['failure_rate']}%";
        }

        // Check average duration
        if ($metrics['avg_duration_ms'] > $this->thresholds['job_completion']) {
            $healthScore -= 25;
            $issues[] = "Slow job completion: {$metrics['avg_duration_ms']}ms";
        }

        // Check throughput
        if ($metrics['throughput_per_minute'] < $this->thresholds['throughput_min']) {
            $healthScore -= 20;
            $issues[] = "Low throughput: {$metrics['throughput_per_minute']} jobs/min";
        }

        return [
            'score' => max(0, $healthScore),
            'status' => $this->getHealthStatus($healthScore),
            'issues' => $issues,
            'metrics' => $metrics,
        ];
    }

    /**
     * Calculate overall queue health.
     */
    protected function calculateOverallQueueHealth(array $queueHealthData): array
    {
        if (empty($queueHealthData)) {
            return ['score' => 100, 'status' => 'excellent', 'total_queues' => 0];
        }

        $scores = array_column($queueHealthData, 'score');
        $avgScore = array_sum($scores) / count($scores);

        return [
            'score' => round($avgScore),
            'status' => $this->getHealthStatus($avgScore),
            'total_queues' => count($queueHealthData),
            'healthy_queues' => count(array_filter($scores, fn ($score) => $score >= 80)),
            'unhealthy_queues' => count(array_filter($scores, fn ($score) => $score < 60)),
        ];
    }

    /**
     * Get health status from score.
     */
    protected function getHealthStatus(float $score): string
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
     * Get active connections.
     */
    protected function getActiveConnections(): array
    {
        return ['database', 'redis', 'sync']; // Default connections
    }

    /**
     * Get active queues for connection.
     */
    protected function getActiveQueues(string $connection): array
    {
        return ['default', 'high', 'low']; // Default queues
    }

    /**
     * Get queue alerts.
     */
    protected function getQueueAlerts(array $healthData): array
    {
        $alerts = [];

        foreach ($healthData as $queueKey => $health) {
            if ($health['score'] < 60) {
                $alerts[] = [
                    'queue' => $queueKey,
                    'severity' => $health['score'] < 40 ? 'critical' : 'warning',
                    'message' => "Queue {$queueKey} health score is {$health['score']}",
                    'issues' => $health['issues'],
                ];
            }
        }

        return $alerts;
    }

    /**
     * Calculate throughput health score.
     */
    protected function calculateThroughputHealthScore(float $throughput, float $successRate, float $avgDuration): int
    {
        $score = 100;

        if ($throughput < $this->thresholds['throughput_min']) {
            $score -= 30;
        }
        if ($successRate < 95) {
            $score -= 25;
        }
        if ($avgDuration > $this->thresholds['job_completion']) {
            $score -= 20;
        }

        return max(0, $score);
    }

    /**
     * Generate throughput summary.
     */
    protected function generateThroughputSummary(array $analysis): array
    {
        if (empty($analysis)) {
            return ['total_queues' => 0, 'avg_throughput' => 0, 'avg_success_rate' => 0];
        }

        $throughputs = array_column($analysis, 'throughput_per_minute');
        $successRates = array_column($analysis, 'success_rate');

        return [
            'total_queues' => count($analysis),
            'avg_throughput' => round(array_sum($throughputs) / count($throughputs), 2),
            'avg_success_rate' => round(array_sum($successRates) / count($successRates), 1),
            'highest_throughput' => max($throughputs),
            'lowest_throughput' => min($throughputs),
        ];
    }

    /**
     * Generate queue recommendations.
     */
    protected function generateQueueRecommendations(string $queueKey, array $health, array $throughput): array
    {
        $recommendations = [];

        if ($health['score'] < 60) {
            foreach ($health['issues'] as $issue) {
                if (str_contains($issue, 'failure rate')) {
                    $recommendations[] = [
                        'type' => 'failure_rate',
                        'priority' => 'high',
                        'message' => 'Investigate job failures and improve error handling',
                    ];
                } elseif (str_contains($issue, 'completion')) {
                    $recommendations[] = [
                        'type' => 'performance',
                        'priority' => 'medium',
                        'message' => 'Optimize job processing logic to reduce execution time',
                    ];
                } elseif (str_contains($issue, 'throughput')) {
                    $recommendations[] = [
                        'type' => 'throughput',
                        'priority' => 'medium',
                        'message' => 'Consider increasing worker processes or optimizing job distribution',
                    ];
                }
            }
        }

        return $recommendations;
    }

    /**
     * Generate general recommendations.
     */
    protected function generateGeneralRecommendations(array $queueHealth, array $throughputAnalysis): array
    {
        $recommendations = [];

        $overallHealth = $queueHealth['overall_health'];
        if ($overallHealth['score'] < 80) {
            $recommendations[] = 'Review queue configuration and worker allocation';
        }

        if ($overallHealth['unhealthy_queues'] > 0) {
            $recommendations[] = 'Focus on improving unhealthy queues first';
        }

        return $recommendations;
    }

    /**
     * Get priority actions.
     */
    protected function getPriorityActions(array $recommendations): array
    {
        $priorityActions = [];

        foreach ($recommendations as $queueKey => $queueRecs) {
            foreach ($queueRecs as $rec) {
                if ($rec['priority'] === 'high') {
                    $priorityActions[] = [
                        'queue' => $queueKey,
                        'action' => $rec['message'],
                        'type' => $rec['type'],
                    ];
                }
            }
        }

        return array_slice($priorityActions, 0, 5); // Top 5 priority actions
    }

    /**
     * Get empty queue metrics.
     */
    protected function getEmptyQueueMetrics(?string $connection, ?string $queue): array
    {
        return [
            'connection' => $connection,
            'queue' => $queue,
            'total_jobs' => 0,
            'successful_jobs' => 0,
            'failed_jobs' => 0,
            'success_rate' => 0,
            'failure_rate' => 0,
            'avg_duration_ms' => 0,
            'throughput_per_minute' => 0,
        ];
    }
}
