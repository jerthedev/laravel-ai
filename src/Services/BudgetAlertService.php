<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Budget Alert Service
 *
 * Manages budget alert configurations, user preferences, and alert tracking
 * with configurable thresholds and notification preferences.
 */
class BudgetAlertService
{
    /**
     * Cache TTL for alert configurations (30 minutes).
     */
    protected int $configCacheTtl = 1800;

    /**
     * Get alert configuration for user and budget type.
     *
     * @param  int  $userId  User ID
     * @param  string  $budgetType  Budget type
     * @param  string|null  $projectId  Project ID
     * @param  string|null  $organizationId  Organization ID
     * @return array Alert configuration
     */
    public function getAlertConfiguration(int $userId, string $budgetType, ?string $projectId = null, ?string $organizationId = null): array
    {
        $cacheKey = "budget_alert_config_{$userId}_{$budgetType}_{$projectId}_{$organizationId}";
        
        return Cache::remember($cacheKey, $this->configCacheTtl, function () use ($userId, $budgetType, $projectId, $organizationId) {
            // Get user-specific configuration
            $userConfig = $this->getUserAlertConfig($userId, $budgetType);
            
            // Get project-specific configuration if applicable
            $projectConfig = $projectId ? $this->getProjectAlertConfig($projectId, $budgetType) : [];
            
            // Get organization-specific configuration if applicable
            $orgConfig = $organizationId ? $this->getOrganizationAlertConfig($organizationId, $budgetType) : [];
            
            // Merge configurations with precedence: user > project > organization > default
            return array_merge(
                $this->getDefaultAlertConfig($budgetType),
                $orgConfig,
                $projectConfig,
                $userConfig
            );
        });
    }

    /**
     * Get user-specific alert configuration.
     *
     * @param  int  $userId  User ID
     * @param  string  $budgetType  Budget type
     * @return array User alert configuration
     */
    protected function getUserAlertConfig(int $userId, string $budgetType): array
    {
        $config = DB::table('ai_budget_alert_configs')
            ->where('user_id', $userId)
            ->where('budget_type', $budgetType)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return [];
        }

        return [
            'enabled' => $config->enabled ?? true,
            'min_threshold_percentage' => $config->min_threshold_percentage ?? 75,
            'email_enabled' => $config->email_enabled ?? true,
            'email_severities' => json_decode($config->email_severities ?? '["medium","high","critical"]', true),
            'slack_enabled' => $config->slack_enabled ?? false,
            'slack_severities' => json_decode($config->slack_severities ?? '["high","critical"]', true),
            'slack_webhook' => $config->slack_webhook,
            'sms_enabled' => $config->sms_enabled ?? false,
            'sms_severities' => json_decode($config->sms_severities ?? '["critical"]', true),
            'sms_phone' => $config->sms_phone,
            'additional_emails' => json_decode($config->additional_emails ?? '[]', true),
        ];
    }

    /**
     * Get project-specific alert configuration.
     *
     * @param  string  $projectId  Project ID
     * @param  string  $budgetType  Budget type
     * @return array Project alert configuration
     */
    protected function getProjectAlertConfig(string $projectId, string $budgetType): array
    {
        $config = DB::table('ai_budget_alert_configs')
            ->where('project_id', $projectId)
            ->where('budget_type', $budgetType)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return [];
        }

        return [
            'enabled' => $config->enabled ?? true,
            'min_threshold_percentage' => $config->min_threshold_percentage ?? 80,
            'slack_enabled' => $config->slack_enabled ?? true,
            'slack_webhook' => $config->slack_webhook,
            'additional_emails' => json_decode($config->additional_emails ?? '[]', true),
        ];
    }

    /**
     * Get organization-specific alert configuration.
     *
     * @param  string  $organizationId  Organization ID
     * @param  string  $budgetType  Budget type
     * @return array Organization alert configuration
     */
    protected function getOrganizationAlertConfig(string $organizationId, string $budgetType): array
    {
        $config = DB::table('ai_budget_alert_configs')
            ->where('organization_id', $organizationId)
            ->where('budget_type', $budgetType)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return [];
        }

        return [
            'enabled' => $config->enabled ?? true,
            'min_threshold_percentage' => $config->min_threshold_percentage ?? 85,
            'slack_enabled' => $config->slack_enabled ?? true,
            'slack_webhook' => $config->slack_webhook,
            'additional_emails' => json_decode($config->additional_emails ?? '[]', true),
        ];
    }

    /**
     * Get default alert configuration.
     *
     * @param  string  $budgetType  Budget type
     * @return array Default alert configuration
     */
    protected function getDefaultAlertConfig(string $budgetType): array
    {
        $defaults = [
            'enabled' => true,
            'email_enabled' => true,
            'email_severities' => ['medium', 'high', 'critical'],
            'slack_enabled' => false,
            'slack_severities' => ['high', 'critical'],
            'sms_enabled' => false,
            'sms_severities' => ['critical'],
            'additional_emails' => [],
        ];

        // Budget type specific defaults
        $typeDefaults = match ($budgetType) {
            'per_request' => [
                'min_threshold_percentage' => 100, // Only alert when exceeded
                'email_severities' => ['critical'],
            ],
            'daily' => [
                'min_threshold_percentage' => 75,
                'email_severities' => ['medium', 'high', 'critical'],
            ],
            'monthly' => [
                'min_threshold_percentage' => 80,
                'email_severities' => ['high', 'critical'],
            ],
            'project' => [
                'min_threshold_percentage' => 85,
                'slack_enabled' => true,
            ],
            'organization' => [
                'min_threshold_percentage' => 90,
                'slack_enabled' => true,
                'sms_enabled' => true,
            ],
            default => ['min_threshold_percentage' => 75],
        };

        return array_merge($defaults, $typeDefaults);
    }

    /**
     * Update alert configuration for user.
     *
     * @param  int  $userId  User ID
     * @param  string  $budgetType  Budget type
     * @param  array  $config  Configuration updates
     * @return bool Success status
     */
    public function updateUserAlertConfig(int $userId, string $budgetType, array $config): bool
    {
        try {
            $data = [
                'user_id' => $userId,
                'budget_type' => $budgetType,
                'enabled' => $config['enabled'] ?? true,
                'min_threshold_percentage' => $config['min_threshold_percentage'] ?? 75,
                'email_enabled' => $config['email_enabled'] ?? true,
                'email_severities' => json_encode($config['email_severities'] ?? ['medium', 'high', 'critical']),
                'slack_enabled' => $config['slack_enabled'] ?? false,
                'slack_severities' => json_encode($config['slack_severities'] ?? ['high', 'critical']),
                'slack_webhook' => $config['slack_webhook'] ?? null,
                'sms_enabled' => $config['sms_enabled'] ?? false,
                'sms_severities' => json_encode($config['sms_severities'] ?? ['critical']),
                'sms_phone' => $config['sms_phone'] ?? null,
                'additional_emails' => json_encode($config['additional_emails'] ?? []),
                'is_active' => true,
                'updated_at' => now(),
            ];

            DB::table('ai_budget_alert_configs')
                ->updateOrInsert(
                    ['user_id' => $userId, 'budget_type' => $budgetType],
                    array_merge($data, ['created_at' => now()])
                );

            // Clear cache
            $this->clearAlertConfigCache($userId, $budgetType);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update budget alert configuration', [
                'user_id' => $userId,
                'budget_type' => $budgetType,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Record alert in database for tracking.
     *
     * @param  array  $alertData  Alert data
     * @return bool Success status
     */
    public function recordAlert(array $alertData): bool
    {
        try {
            DB::table('ai_budget_alerts')->insert([
                'user_id' => $alertData['user_id'],
                'budget_type' => $alertData['budget_type'],
                'threshold_percentage' => $alertData['threshold_percentage'],
                'current_spending' => $alertData['current_spending'],
                'budget_limit' => $alertData['budget_limit'],
                'additional_cost' => $alertData['additional_cost'],
                'severity' => $alertData['severity'],
                'channels' => json_encode($alertData['channels']),
                'project_id' => $alertData['project_id'] ?? null,
                'organization_id' => $alertData['organization_id'] ?? null,
                'metadata' => json_encode($alertData['metadata'] ?? []),
                'sent_at' => $alertData['sent_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to record budget alert', [
                'alert_data' => $alertData,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get user model for notifications.
     *
     * @param  int  $userId  User ID
     * @return mixed User model or null
     */
    public function getUser(int $userId)
    {
        // This would typically use your User model
        // For now, we'll use a generic approach
        return DB::table('users')->where('id', $userId)->first();
    }

    /**
     * Get alert history for user.
     *
     * @param  int  $userId  User ID
     * @param  string|null  $budgetType  Budget type filter
     * @param  int  $limit  Number of alerts to retrieve
     * @return array Alert history
     */
    public function getAlertHistory(int $userId, ?string $budgetType = null, int $limit = 50): array
    {
        $query = DB::table('ai_budget_alerts')
            ->where('user_id', $userId)
            ->orderBy('sent_at', 'desc')
            ->limit($limit);

        if ($budgetType) {
            $query->where('budget_type', $budgetType);
        }

        $alerts = $query->get();

        return $alerts->map(function ($alert) {
            return [
                'id' => $alert->id,
                'budget_type' => $alert->budget_type,
                'threshold_percentage' => (float) $alert->threshold_percentage,
                'current_spending' => (float) $alert->current_spending,
                'budget_limit' => (float) $alert->budget_limit,
                'additional_cost' => (float) $alert->additional_cost,
                'severity' => $alert->severity,
                'channels' => json_decode($alert->channels, true),
                'project_id' => $alert->project_id,
                'organization_id' => $alert->organization_id,
                'sent_at' => $alert->sent_at,
            ];
        })->toArray();
    }

    /**
     * Get alert statistics for user.
     *
     * @param  int  $userId  User ID
     * @param  string|null  $dateRange  Date range (week, month, quarter, year)
     * @return array Alert statistics
     */
    public function getAlertStatistics(int $userId, ?string $dateRange = 'month'): array
    {
        $query = DB::table('ai_budget_alerts')
            ->where('user_id', $userId);

        // Apply date range filter
        if ($dateRange) {
            $startDate = match ($dateRange) {
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                'quarter' => now()->startOfQuarter(),
                'year' => now()->startOfYear(),
                default => now()->startOfMonth(),
            };

            $query->where('sent_at', '>=', $startDate);
        }

        $alerts = $query->get();

        return [
            'total_alerts' => $alerts->count(),
            'by_budget_type' => $alerts->groupBy('budget_type')->map->count()->toArray(),
            'by_severity' => $alerts->groupBy('severity')->map->count()->toArray(),
            'by_channel' => $this->getChannelStatistics($alerts),
            'recent_alerts' => $alerts->sortByDesc('sent_at')->take(10)->values()->toArray(),
        ];
    }

    /**
     * Get channel statistics from alerts.
     *
     * @param  \Illuminate\Support\Collection  $alerts  Alert collection
     * @return array Channel statistics
     */
    protected function getChannelStatistics($alerts): array
    {
        $channelStats = [];

        foreach ($alerts as $alert) {
            $channels = json_decode($alert->channels, true) ?? [];
            foreach ($channels as $channel) {
                $channelStats[$channel] = ($channelStats[$channel] ?? 0) + 1;
            }
        }

        return $channelStats;
    }

    /**
     * Clear alert configuration cache.
     *
     * @param  int  $userId  User ID
     * @param  string  $budgetType  Budget type
     */
    protected function clearAlertConfigCache(int $userId, string $budgetType): void
    {
        // Clear user-specific cache
        $patterns = [
            "budget_alert_config_{$userId}_{$budgetType}_*",
        ];

        foreach ($patterns as $pattern) {
            // In a real implementation with Redis, you'd use KEYS command
            // For now, we'll clear specific known keys
            Cache::forget($pattern);
        }
    }

    /**
     * Test alert configuration by sending a test notification.
     *
     * @param  int  $userId  User ID
     * @param  string  $budgetType  Budget type
     * @param  string  $channel  Channel to test
     * @return bool Success status
     */
    public function testAlertConfiguration(int $userId, string $budgetType, string $channel): bool
    {
        try {
            // Create a test event
            $testEvent = new \JTD\LaravelAI\Events\BudgetThresholdReached(
                userId: $userId,
                budgetType: $budgetType,
                currentSpending: 75.0,
                budgetLimit: 100.0,
                additionalCost: 5.0,
                thresholdPercentage: 80.0,
                metadata: ['test' => true]
            );

            // Get alert configuration
            $alertConfig = $this->getAlertConfiguration($userId, $budgetType);

            // Create test notification
            $notification = new \JTD\LaravelAI\Notifications\BudgetThresholdNotification($testEvent, 'medium', $channel);

            // Send test notification based on channel
            switch ($channel) {
                case 'email':
                    $user = $this->getUser($userId);
                    if ($user && $user->email) {
                        $user->notify($notification);
                    }
                    break;

                case 'slack':
                    $slackWebhook = $alertConfig['slack_webhook'] ?? config('ai.budget.slack_webhook');
                    if ($slackWebhook) {
                        \Illuminate\Support\Facades\Notification::route('slack', $slackWebhook)->notify($notification);
                    }
                    break;

                case 'sms':
                    $phoneNumber = $alertConfig['sms_phone'] ?? null;
                    if ($phoneNumber) {
                        \Illuminate\Support\Facades\Notification::route('nexmo', $phoneNumber)->notify($notification);
                    }
                    break;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Budget alert test failed', [
                'user_id' => $userId,
                'budget_type' => $budgetType,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
