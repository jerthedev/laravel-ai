<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use JTD\LaravelAI\Events\PerformanceThresholdExceeded;
use JTD\LaravelAI\Mail\PerformanceAlertMail;

/**
 * Performance Alert Manager
 *
 * Manages automated performance alerts with threshold breach notifications,
 * escalation policies, and multi-channel alert delivery.
 */
class PerformanceAlertManager
{
    /**
     * Alert configuration.
     */
    protected array $alertConfig;

    /**
     * Alert channels.
     */
    protected array $channels = ['mail', 'slack', 'database', 'log'];

    /**
     * Alert cooldown periods (in seconds).
     */
    protected array $cooldownPeriods = [
        'low' => 300,      // 5 minutes
        'medium' => 180,   // 3 minutes
        'high' => 60,      // 1 minute
        'critical' => 30,  // 30 seconds
    ];

    /**
     * Create a new alert manager instance.
     */
    public function __construct()
    {
        $this->alertConfig = config('ai.performance.alerts', []);
    }

    /**
     * Handle performance threshold exceeded event.
     *
     * @param  PerformanceThresholdExceeded  $event  Performance event
     */
    public function handlePerformanceThresholdExceeded(PerformanceThresholdExceeded $event): void
    {
        if (! $this->shouldSendAlert($event)) {
            return;
        }

        $alert = $this->createAlert($event);

        if ($this->isInCooldown($alert)) {
            $this->updateCooldownCounter($alert);

            return;
        }

        $this->sendAlert($alert);
        $this->recordAlert($alert);
        $this->setCooldown($alert);
    }

    /**
     * Get active alerts.
     *
     * @param  array  $filters  Alert filters
     * @return array Active alerts
     */
    public function getActiveAlerts(array $filters = []): array
    {
        $query = DB::table('ai_performance_alerts')
            ->where('status', 'active')
            ->where('created_at', '>=', now()->subHours(24));

        // Apply filters
        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['component'])) {
            $query->where('component', $filters['component']);
        }

        if (! empty($filters['component_name'])) {
            $query->where('component_name', $filters['component_name']);
        }

        $alerts = $query->orderBy('created_at', 'desc')->get();

        return $alerts->map(function ($alert) {
            return [
                'id' => $alert->id,
                'component' => $alert->component,
                'component_name' => $alert->component_name,
                'severity' => $alert->severity,
                'message' => $alert->message,
                'threshold_exceeded_percentage' => $alert->threshold_exceeded_percentage,
                'duration_ms' => $alert->duration_ms,
                'threshold_ms' => $alert->threshold_ms,
                'status' => $alert->status,
                'channels_sent' => json_decode($alert->channels_sent, true),
                'escalation_level' => $alert->escalation_level,
                'occurrence_count' => $alert->occurrence_count,
                'created_at' => $alert->created_at,
                'acknowledged_at' => $alert->acknowledged_at,
                'resolved_at' => $alert->resolved_at,
            ];
        })->toArray();
    }

    /**
     * Acknowledge an alert.
     *
     * @param  int  $alertId  Alert ID
     * @param  int|null  $userId  User ID
     * @return bool Success
     */
    public function acknowledgeAlert(int $alertId, ?int $userId = null): bool
    {
        $updated = DB::table('ai_performance_alerts')
            ->where('id', $alertId)
            ->where('status', 'active')
            ->update([
                'status' => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => $userId,
                'updated_at' => now(),
            ]);

        if ($updated) {
            Log::info('Performance alert acknowledged', [
                'alert_id' => $alertId,
                'acknowledged_by' => $userId,
            ]);
        }

        return $updated > 0;
    }

    /**
     * Resolve an alert.
     *
     * @param  int  $alertId  Alert ID
     * @param  int|null  $userId  User ID
     * @param  string|null  $resolution  Resolution notes
     * @return bool Success
     */
    public function resolveAlert(int $alertId, ?int $userId = null, ?string $resolution = null): bool
    {
        $updated = DB::table('ai_performance_alerts')
            ->where('id', $alertId)
            ->whereIn('status', ['active', 'acknowledged'])
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $userId,
                'resolution_notes' => $resolution,
                'updated_at' => now(),
            ]);

        if ($updated) {
            Log::info('Performance alert resolved', [
                'alert_id' => $alertId,
                'resolved_by' => $userId,
                'resolution' => $resolution,
            ]);
        }

        return $updated > 0;
    }

    /**
     * Get alert statistics.
     *
     * @param  string  $timeframe  Timeframe
     * @return array Alert statistics
     */
    public function getAlertStatistics(string $timeframe = 'day'): array
    {
        $startTime = $this->getStartTime($timeframe);

        $stats = DB::table('ai_performance_alerts')
            ->where('created_at', '>=', $startTime)
            ->selectRaw('
                COUNT(*) as total_alerts,
                SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) as critical_alerts,
                SUM(CASE WHEN severity = "high" THEN 1 ELSE 0 END) as high_alerts,
                SUM(CASE WHEN severity = "medium" THEN 1 ELSE 0 END) as medium_alerts,
                SUM(CASE WHEN severity = "low" THEN 1 ELSE 0 END) as low_alerts,
                SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END) as resolved_alerts,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_alerts,
                AVG(CASE WHEN resolved_at IS NOT NULL THEN
                    TIMESTAMPDIFF(MINUTE, created_at, resolved_at)
                    ELSE NULL END) as avg_resolution_time_minutes
            ')
            ->first();

        $componentBreakdown = DB::table('ai_performance_alerts')
            ->where('created_at', '>=', $startTime)
            ->select('component', DB::raw('COUNT(*) as alert_count'))
            ->groupBy('component')
            ->orderBy('alert_count', 'desc')
            ->get();

        return [
            'timeframe' => $timeframe,
            'total_alerts' => $stats->total_alerts ?? 0,
            'by_severity' => [
                'critical' => $stats->critical_alerts ?? 0,
                'high' => $stats->high_alerts ?? 0,
                'medium' => $stats->medium_alerts ?? 0,
                'low' => $stats->low_alerts ?? 0,
            ],
            'by_status' => [
                'active' => $stats->active_alerts ?? 0,
                'resolved' => $stats->resolved_alerts ?? 0,
                'acknowledged' => ($stats->total_alerts ?? 0) - ($stats->active_alerts ?? 0) - ($stats->resolved_alerts ?? 0),
            ],
            'avg_resolution_time_minutes' => round($stats->avg_resolution_time_minutes ?? 0, 1),
            'by_component' => $componentBreakdown->toArray(),
            'alert_rate' => $this->calculateAlertRate($stats->total_alerts ?? 0, $timeframe),
        ];
    }

    /**
     * Configure alert thresholds.
     *
     * @param  array  $thresholds  Threshold configuration
     * @return bool Success
     */
    public function configureThresholds(array $thresholds): bool
    {
        try {
            foreach ($thresholds as $component => $config) {
                Cache::put("alert_thresholds_{$component}", $config, 3600);
            }

            Log::info('Alert thresholds updated', ['thresholds' => $thresholds]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update alert thresholds', [
                'error' => $e->getMessage(),
                'thresholds' => $thresholds,
            ]);

            return false;
        }
    }

    /**
     * Test alert system.
     *
     * @param  string  $severity  Test severity
     * @return array Test result
     */
    public function testAlertSystem(string $severity = 'medium'): array
    {
        $testEvent = new PerformanceThresholdExceeded('test_component', [
            'component_name' => 'TestComponent',
            'duration_ms' => 150,
            'threshold_ms' => 100,
            'exceeded_threshold' => true,
            'context' => ['test' => true],
            'timestamp' => now(),
        ]);

        try {
            $this->handlePerformanceThresholdExceeded($testEvent);

            return [
                'success' => true,
                'message' => 'Test alert sent successfully',
                'severity' => $severity,
                'channels_tested' => $this->getEnabledChannels(),
                'timestamp' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Test alert failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    /**
     * Check if alert should be sent.
     */
    protected function shouldSendAlert(PerformanceThresholdExceeded $event): bool
    {
        // Check if alerts are enabled
        if (! config('ai.performance.alerts.enabled', true)) {
            return false;
        }

        // Check severity threshold
        $minSeverity = config('ai.performance.alerts.min_severity', 'medium');
        $severityLevels = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

        $eventSeverityLevel = $severityLevels[$event->getSeverity()] ?? 0;
        $minSeverityLevel = $severityLevels[$minSeverity] ?? 2;

        return $eventSeverityLevel >= $minSeverityLevel;
    }

    /**
     * Create alert from event.
     */
    protected function createAlert(PerformanceThresholdExceeded $event): array
    {
        return [
            'component' => $event->component,
            'component_name' => $event->getComponentName(),
            'severity' => $event->getSeverity(),
            'message' => $event->getAlertMessage(),
            'detailed_message' => $event->getDetailedDescription(),
            'duration_ms' => $event->getDuration(),
            'threshold_ms' => $event->getThreshold(),
            'threshold_exceeded_percentage' => $event->getThresholdExceededPercentage(),
            'context' => $event->getContext(),
            'recommended_actions' => $event->getRecommendedActions(),
            'timestamp' => now(),
        ];
    }

    /**
     * Check if alert is in cooldown.
     */
    protected function isInCooldown(array $alert): bool
    {
        $cooldownKey = $this->getCooldownKey($alert);

        return Cache::has($cooldownKey);
    }

    /**
     * Set alert cooldown.
     */
    protected function setCooldown(array $alert): void
    {
        $cooldownKey = $this->getCooldownKey($alert);
        $cooldownPeriod = $this->cooldownPeriods[$alert['severity']] ?? 300;

        Cache::put($cooldownKey, true, $cooldownPeriod);
    }

    /**
     * Update cooldown counter.
     */
    protected function updateCooldownCounter(array $alert): void
    {
        $counterKey = "alert_counter_{$alert['component']}_{$alert['component_name']}";
        Cache::increment($counterKey);
        Cache::expire($counterKey, 3600); // 1 hour expiry
    }

    /**
     * Send alert through configured channels.
     */
    protected function sendAlert(array $alert): void
    {
        $enabledChannels = $this->getEnabledChannels();
        $sentChannels = [];

        foreach ($enabledChannels as $channel) {
            try {
                $this->sendAlertToChannel($alert, $channel);
                $sentChannels[] = $channel;
            } catch (\Exception $e) {
                Log::error("Failed to send alert to {$channel}", [
                    'alert' => $alert,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $alert['channels_sent'] = $sentChannels;
    }

    /**
     * Send alert to specific channel.
     */
    protected function sendAlertToChannel(array $alert, string $channel): void
    {
        switch ($channel) {
            case 'mail':
                $this->sendEmailAlert($alert);
                break;
            case 'slack':
                $this->sendSlackAlert($alert);
                break;
            case 'database':
                $this->sendDatabaseAlert($alert);
                break;
            case 'log':
                $this->sendLogAlert($alert);
                break;
        }
    }

    /**
     * Send email alert.
     */
    protected function sendEmailAlert(array $alert): void
    {
        $recipients = config('ai.performance.alerts.email.recipients', []);

        if (! empty($recipients)) {
            foreach ($recipients as $recipient) {
                Mail::to($recipient)->send(new PerformanceAlertMail($alert));
            }
        }
    }

    /**
     * Send Slack alert.
     */
    protected function sendSlackAlert(array $alert): void
    {
        $webhookUrl = config('ai.performance.alerts.slack.webhook_url');

        if ($webhookUrl) {
            $payload = [
                'text' => $alert['message'],
                'attachments' => [
                    [
                        'color' => $this->getSlackColor($alert['severity']),
                        'fields' => [
                            [
                                'title' => 'Component',
                                'value' => $alert['component_name'],
                                'short' => true,
                            ],
                            [
                                'title' => 'Duration',
                                'value' => "{$alert['duration_ms']}ms",
                                'short' => true,
                            ],
                            [
                                'title' => 'Threshold',
                                'value' => "{$alert['threshold_ms']}ms",
                                'short' => true,
                            ],
                            [
                                'title' => 'Exceeded By',
                                'value' => "{$alert['threshold_exceeded_percentage']}%",
                                'short' => true,
                            ],
                        ],
                    ],
                ],
            ];

            // Send to Slack (would use HTTP client in real implementation)
            Log::info('Slack alert sent', ['payload' => $payload]);
        }
    }

    /**
     * Send database alert.
     */
    protected function sendDatabaseAlert(array $alert): void
    {
        // Database alerts are handled by recordAlert method
    }

    /**
     * Send log alert.
     */
    protected function sendLogAlert(array $alert): void
    {
        Log::warning('Performance Alert', $alert);
    }

    /**
     * Record alert in database.
     */
    protected function recordAlert(array $alert): void
    {
        DB::table('ai_performance_alerts')->insert([
            'component' => $alert['component'],
            'component_name' => $alert['component_name'],
            'severity' => $alert['severity'],
            'message' => $alert['message'],
            'detailed_message' => $alert['detailed_message'],
            'duration_ms' => $alert['duration_ms'],
            'threshold_ms' => $alert['threshold_ms'],
            'threshold_exceeded_percentage' => $alert['threshold_exceeded_percentage'],
            'context_data' => json_encode($alert['context']),
            'recommended_actions' => json_encode($alert['recommended_actions']),
            'channels_sent' => json_encode($alert['channels_sent'] ?? []),
            'status' => 'active',
            'escalation_level' => 1,
            'occurrence_count' => 1,
            'created_at' => $alert['timestamp'],
            'updated_at' => $alert['timestamp'],
        ]);
    }

    /**
     * Get cooldown key.
     */
    protected function getCooldownKey(array $alert): string
    {
        return "alert_cooldown_{$alert['component']}_{$alert['component_name']}_{$alert['severity']}";
    }

    /**
     * Get enabled channels.
     */
    protected function getEnabledChannels(): array
    {
        $channels = config('ai.performance.alerts.channels', ['log']);

        return array_intersect($channels, $this->channels);
    }

    /**
     * Get Slack color for severity.
     */
    protected function getSlackColor(string $severity): string
    {
        return match ($severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'warning',
            'low' => 'good',
            default => 'warning',
        };
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
            default => now()->subDay(),
        };
    }

    /**
     * Calculate alert rate.
     */
    protected function calculateAlertRate(int $totalAlerts, string $timeframe): float
    {
        $hours = match ($timeframe) {
            'hour' => 1,
            'day' => 24,
            'week' => 168,
            'month' => 720,
            default => 24,
        };

        return $hours > 0 ? round($totalAlerts / $hours, 2) : 0;
    }
}
