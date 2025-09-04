<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIBudgetAlert;
use JTD\LaravelAI\Models\AIBudgetAlertConfig;
use JTD\LaravelAI\Models\User;

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
        $config = AIBudgetAlertConfig::forUser($userId)
            ->byBudgetType($budgetType)
            ->active()
            ->first();

        if (! $config) {
            return [];
        }

        return [
            'enabled' => $config->enabled,
            'min_threshold_percentage' => $config->min_threshold_percentage,
            'email_enabled' => $config->email_enabled,
            'email_severities' => $config->email_severities ?? ['medium', 'high', 'critical'],
            'slack_enabled' => $config->slack_enabled,
            'slack_severities' => $config->slack_severities ?? ['high', 'critical'],
            'slack_webhook' => $config->slack_webhook,
            'sms_enabled' => $config->sms_enabled,
            'sms_severities' => $config->sms_severities ?? ['critical'],
            'sms_phone' => $config->sms_phone,
            'additional_emails' => $config->additional_emails ?? [],
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
        $config = AIBudgetAlertConfig::forProject($projectId)
            ->byBudgetType($budgetType)
            ->active()
            ->first();

        if (! $config) {
            return [];
        }

        return [
            'enabled' => $config->enabled,
            'min_threshold_percentage' => $config->min_threshold_percentage ?? 80,
            'slack_enabled' => $config->slack_enabled,
            'slack_webhook' => $config->slack_webhook,
            'additional_emails' => $config->additional_emails ?? [],
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
        $config = AIBudgetAlertConfig::forOrganization($organizationId)
            ->byBudgetType($budgetType)
            ->active()
            ->first();

        if (! $config) {
            return [];
        }

        return [
            'enabled' => $config->enabled,
            'min_threshold_percentage' => $config->min_threshold_percentage ?? 85,
            'slack_enabled' => $config->slack_enabled,
            'slack_webhook' => $config->slack_webhook,
            'additional_emails' => $config->additional_emails ?? [],
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
        return AIBudgetAlertConfig::getDefaults($budgetType);
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
            $alertConfig = AIBudgetAlertConfig::forUser($userId)
                ->byBudgetType($budgetType)
                ->first();

            if ($alertConfig) {
                $alertConfig->update($config);
            } else {
                AIBudgetAlertConfig::createForUser($userId, $budgetType, $config);
            }

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
            AIBudgetAlert::createAlert($alertData);

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
        return User::find($userId);
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
        $query = AIBudgetAlert::forUser($userId)
            ->orderBy('sent_at', 'desc')
            ->limit($limit);

        if ($budgetType) {
            $query->byBudgetType($budgetType);
        }

        return $query->get()->map(function ($alert) {
            return [
                'id' => $alert->id,
                'budget_type' => $alert->budget_type,
                'threshold_percentage' => (float) $alert->threshold_percentage,
                'current_spending' => (float) $alert->current_spending,
                'budget_limit' => (float) $alert->budget_limit,
                'additional_cost' => (float) $alert->additional_cost,
                'severity' => $alert->severity,
                'channels' => $alert->channels,
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
        return AIBudgetAlert::getAlertStatistics($userId, $dateRange);
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
                current_spending: 75.0,
                budget_limit: 100.0,
                additionalCost: 5.0,
                threshold_percentage: 80.0,
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
