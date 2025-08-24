<?php

namespace JTD\LaravelAI\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use JTD\LaravelAI\Events\BudgetThresholdReached;
use JTD\LaravelAI\Notifications\BudgetThresholdNotification;
use JTD\LaravelAI\Services\BudgetAlertService;

/**
 * Budget Alert Listener
 *
 * Handles BudgetThresholdReached events with configurable thresholds and
 * notification delivery for real-time budget monitoring and alerting.
 */
class BudgetAlertListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'budget-alerts';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 30;

    /**
     * Budget Alert Service.
     */
    protected BudgetAlertService $budgetAlertService;

    /**
     * Create a new listener instance.
     */
    public function __construct(BudgetAlertService $budgetAlertService)
    {
        $this->budgetAlertService = $budgetAlertService;
    }

    /**
     * Handle the BudgetThresholdReached event.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     */
    public function handle(BudgetThresholdReached $event): void
    {
        $startTime = microtime(true);

        try {
            // Check if alert should be sent (rate limiting and threshold configuration)
            if (!$this->shouldSendAlert($event)) {
                Log::debug('Budget alert skipped due to rate limiting or configuration', [
                    'user_id' => $event->userId,
                    'budget_type' => $event->budgetType,
                    'threshold_percentage' => $event->thresholdPercentage,
                ]);
                return;
            }

            // Process the budget alert
            $this->processAlert($event);

            // Track successful alert processing
            $this->trackAlertMetrics($event, 'success', microtime(true) - $startTime);

        } catch (\Exception $e) {
            // Log error and track failure
            Log::error('Budget alert processing failed', [
                'user_id' => $event->userId,
                'budget_type' => $event->budgetType,
                'error' => $e->getMessage(),
                'processing_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            $this->trackAlertMetrics($event, 'failed', microtime(true) - $startTime);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Process the budget alert with appropriate notifications.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     */
    protected function processAlert(BudgetThresholdReached $event): void
    {
        // Get alert configuration for this user and budget type
        $alertConfig = $this->budgetAlertService->getAlertConfiguration(
            $event->userId,
            $event->budgetType,
            $event->projectId,
            $event->organizationId
        );

        if (!$alertConfig['enabled']) {
            Log::debug('Budget alerts disabled for user', [
                'user_id' => $event->userId,
                'budget_type' => $event->budgetType,
            ]);
            return;
        }

        // Determine alert severity and channels
        $alertSeverity = $this->determineAlertSeverity($event->thresholdPercentage);
        $notificationChannels = $this->getNotificationChannels($alertConfig, $alertSeverity);

        // Send notifications through configured channels
        foreach ($notificationChannels as $channel) {
            $this->sendNotification($event, $channel, $alertSeverity, $alertConfig);
        }

        // Record alert in database for tracking
        $this->recordAlert($event, $alertSeverity, $notificationChannels);

        // Update rate limiting cache
        $this->updateRateLimiting($event);

        Log::info('Budget alert processed successfully', [
            'user_id' => $event->userId,
            'budget_type' => $event->budgetType,
            'threshold_percentage' => $event->thresholdPercentage,
            'severity' => $alertSeverity,
            'channels' => $notificationChannels,
        ]);
    }

    /**
     * Check if alert should be sent based on rate limiting and configuration.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     * @return bool Whether alert should be sent
     */
    protected function shouldSendAlert(BudgetThresholdReached $event): bool
    {
        // Check rate limiting to prevent spam
        $rateLimitKey = "budget_alert_rate_limit_{$event->userId}_{$event->budgetType}";
        $lastAlertTime = Cache::get($rateLimitKey);
        
        if ($lastAlertTime) {
            $timeSinceLastAlert = now()->diffInMinutes($lastAlertTime);
            $minInterval = $this->getMinAlertInterval($event->budgetType, $event->thresholdPercentage);
            
            if ($timeSinceLastAlert < $minInterval) {
                return false;
            }
        }

        // Check if threshold percentage meets configured minimum
        $alertConfig = $this->budgetAlertService->getAlertConfiguration(
            $event->userId,
            $event->budgetType,
            $event->projectId,
            $event->organizationId
        );

        $minThreshold = $alertConfig['min_threshold_percentage'] ?? 75;
        
        return $event->thresholdPercentage >= $minThreshold;
    }

    /**
     * Determine alert severity based on threshold percentage.
     *
     * @param  float  $thresholdPercentage  Threshold percentage
     * @return string Alert severity (low, medium, high, critical)
     */
    protected function determineAlertSeverity(float $thresholdPercentage): string
    {
        return match (true) {
            $thresholdPercentage >= 100 => 'critical',
            $thresholdPercentage >= 90 => 'high',
            $thresholdPercentage >= 80 => 'medium',
            default => 'low',
        };
    }

    /**
     * Get notification channels based on configuration and severity.
     *
     * @param  array  $alertConfig  Alert configuration
     * @param  string  $severity  Alert severity
     * @return array Notification channels
     */
    protected function getNotificationChannels(array $alertConfig, string $severity): array
    {
        $channels = [];

        // Email notifications
        if ($alertConfig['email_enabled'] ?? true) {
            $emailSeverities = $alertConfig['email_severities'] ?? ['medium', 'high', 'critical'];
            if (in_array($severity, $emailSeverities)) {
                $channels[] = 'email';
            }
        }

        // Slack notifications
        if ($alertConfig['slack_enabled'] ?? false) {
            $slackSeverities = $alertConfig['slack_severities'] ?? ['high', 'critical'];
            if (in_array($severity, $slackSeverities)) {
                $channels[] = 'slack';
            }
        }

        // SMS notifications (for critical alerts)
        if ($alertConfig['sms_enabled'] ?? false) {
            $smsSeverities = $alertConfig['sms_severities'] ?? ['critical'];
            if (in_array($severity, $smsSeverities)) {
                $channels[] = 'sms';
            }
        }

        // Database notifications (always enabled for tracking)
        $channels[] = 'database';

        return array_unique($channels);
    }

    /**
     * Send notification through specified channel.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     * @param  string  $channel  Notification channel
     * @param  string  $severity  Alert severity
     * @param  array  $alertConfig  Alert configuration
     */
    protected function sendNotification(BudgetThresholdReached $event, string $channel, string $severity, array $alertConfig): void
    {
        try {
            $notification = new BudgetThresholdNotification($event, $severity, $channel);

            switch ($channel) {
                case 'email':
                    $this->sendEmailNotification($event, $notification, $alertConfig);
                    break;

                case 'slack':
                    $this->sendSlackNotification($event, $notification, $alertConfig);
                    break;

                case 'sms':
                    $this->sendSmsNotification($event, $notification, $alertConfig);
                    break;

                case 'database':
                    $this->sendDatabaseNotification($event, $notification);
                    break;
            }

        } catch (\Exception $e) {
            Log::error('Failed to send budget alert notification', [
                'user_id' => $event->userId,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     * @param  BudgetThresholdNotification  $notification  The notification
     * @param  array  $alertConfig  Alert configuration
     */
    protected function sendEmailNotification(BudgetThresholdReached $event, BudgetThresholdNotification $notification, array $alertConfig): void
    {
        $user = $this->budgetAlertService->getUser($event->userId);
        
        if ($user && $user->email) {
            $user->notify($notification);
        }

        // Send to additional email addresses if configured
        $additionalEmails = $alertConfig['additional_emails'] ?? [];
        foreach ($additionalEmails as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    /**
     * Send Slack notification.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     * @param  BudgetThresholdNotification  $notification  The notification
     * @param  array  $alertConfig  Alert configuration
     */
    protected function sendSlackNotification(BudgetThresholdReached $event, BudgetThresholdNotification $notification, array $alertConfig): void
    {
        $slackWebhook = $alertConfig['slack_webhook'] ?? config('ai.budget.slack_webhook');
        
        if ($slackWebhook) {
            Notification::route('slack', $slackWebhook)->notify($notification);
        }
    }

    /**
     * Send SMS notification.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     * @param  BudgetThresholdNotification  $notification  The notification
     * @param  array  $alertConfig  Alert configuration
     */
    protected function sendSmsNotification(BudgetThresholdReached $event, BudgetThresholdNotification $notification, array $alertConfig): void
    {
        $phoneNumber = $alertConfig['sms_phone'] ?? null;
        
        if ($phoneNumber) {
            Notification::route('nexmo', $phoneNumber)->notify($notification);
        }
    }

    /**
     * Send database notification.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     * @param  BudgetThresholdNotification  $notification  The notification
     */
    protected function sendDatabaseNotification(BudgetThresholdReached $event, BudgetThresholdNotification $notification): void
    {
        $user = $this->budgetAlertService->getUser($event->userId);
        
        if ($user) {
            $user->notify($notification);
        }
    }

    /**
     * Record alert in database for tracking and analytics.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     * @param  string  $severity  Alert severity
     * @param  array  $channels  Notification channels used
     */
    protected function recordAlert(BudgetThresholdReached $event, string $severity, array $channels): void
    {
        $this->budgetAlertService->recordAlert([
            'user_id' => $event->userId,
            'budget_type' => $event->budgetType,
            'threshold_percentage' => $event->thresholdPercentage,
            'current_spending' => $event->currentSpending,
            'budget_limit' => $event->budgetLimit,
            'additional_cost' => $event->additionalCost,
            'severity' => $severity,
            'channels' => $channels,
            'project_id' => $event->projectId,
            'organization_id' => $event->organizationId,
            'metadata' => $event->metadata,
            'sent_at' => now(),
        ]);
    }

    /**
     * Update rate limiting cache.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     */
    protected function updateRateLimiting(BudgetThresholdReached $event): void
    {
        $rateLimitKey = "budget_alert_rate_limit_{$event->userId}_{$event->budgetType}";
        Cache::put($rateLimitKey, now(), now()->addHours(1));
    }

    /**
     * Get minimum alert interval based on budget type and threshold.
     *
     * @param  string  $budgetType  Budget type
     * @param  float  $thresholdPercentage  Threshold percentage
     * @return int Minimum interval in minutes
     */
    protected function getMinAlertInterval(string $budgetType, float $thresholdPercentage): int
    {
        // More frequent alerts for critical thresholds
        if ($thresholdPercentage >= 100) {
            return 5; // 5 minutes for exceeded budgets
        }

        if ($thresholdPercentage >= 90) {
            return 15; // 15 minutes for critical thresholds
        }

        // Different intervals based on budget type
        return match ($budgetType) {
            'per_request' => 1, // 1 minute for per-request (immediate)
            'daily' => 30, // 30 minutes for daily
            'monthly' => 60, // 1 hour for monthly
            'project' => 60, // 1 hour for project
            'organization' => 120, // 2 hours for organization
            default => 30,
        };
    }

    /**
     * Track alert processing metrics.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     * @param  string  $outcome  Processing outcome
     * @param  float  $duration  Processing duration in seconds
     */
    protected function trackAlertMetrics(BudgetThresholdReached $event, string $outcome, float $duration): void
    {
        $durationMs = $duration * 1000;

        Cache::increment("budget_alerts_processed_total");
        Cache::increment("budget_alerts_outcome_{$outcome}");
        Cache::increment("budget_alerts_duration_total", $durationMs);
        Cache::increment("budget_alerts_by_type_{$event->budgetType}");
        Cache::increment("budget_alerts_by_severity_{$event->getSeverity()}");

        // Log slow alert processing
        if ($durationMs > 1000) { // 1 second threshold
            Log::warning('Slow budget alert processing detected', [
                'user_id' => $event->userId,
                'budget_type' => $event->budgetType,
                'duration_ms' => round($durationMs, 2),
                'outcome' => $outcome,
            ]);
        }
    }
}
