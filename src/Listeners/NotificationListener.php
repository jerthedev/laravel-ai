<?php

namespace JTD\LaravelAI\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use JTD\LaravelAI\Events\BudgetThresholdReached;
use JTD\LaravelAI\Events\ResponseGenerated;

/**
 * Notification Listener Foundation
 *
 * Handles budget alerts and notification processing in the background.
 * Processes budget threshold events and other notification triggers
 * to send alerts to users and administrators.
 */
class NotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'ai-notifications';

    /**
     * The time (seconds) before the job should be processed.
     */
    public int $delay = 0;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5; // More retries for critical notifications

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Handle the BudgetThresholdReached event for budget alerts.
     */
    public function handle(BudgetThresholdReached $event): void
    {
        $startTime = microtime(true);

        try {
            // Send budget threshold notification
            $this->sendBudgetAlert($event);

            // Log budget threshold event
            $this->logBudgetThreshold($event);

            // Check if administrative action is needed
            if ($event->severity === 'exceeded') {
                $this->handleBudgetExceeded($event);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the job
            logger()->error('Budget notification failed', [
                'event' => class_basename($event),
                'user_id' => $event->userId,
                'budget_type' => $event->budgetType,
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Track performance metrics
            $this->trackListenerPerformance('handle', microtime(true) - $startTime);
        }
    }

    /**
     * Handle ResponseGenerated events for general notifications.
     */
    public function handleResponseGenerated(ResponseGenerated $event): void
    {
        try {
            // Check for notification triggers
            $this->checkNotificationTriggers($event);
        } catch (\Exception $e) {
            logger()->error('Response notification check failed', [
                'event' => class_basename($event),
                'message_id' => $event->message->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send budget alert notification.
     *
     * @param  BudgetThresholdReached  $event  The event
     */
    protected function sendBudgetAlert(BudgetThresholdReached $event): void
    {
        // Foundation implementation - will be expanded in Sprint 4b
        $message = $this->buildBudgetAlertMessage($event);

        // For now, just log the alert (will be replaced with actual notifications)
        logger()->warning('Budget Alert', [
            'user_id' => $event->userId,
            'budget_type' => $event->budgetType,
            'severity' => $event->severity,
            'threshold_percentage' => $event->threshold_percentage,
            'current_spending' => $event->current_spending,
            'budget_limit' => $event->budget_limit,
            'message' => $message,
        ]);
    }

    /**
     * Build budget alert message.
     *
     * @param  BudgetThresholdReached  $event  The event
     * @return string The alert message
     */
    protected function buildBudgetAlertMessage(BudgetThresholdReached $event): string
    {
        $percentage = round($event->threshold_percentage, 1);

        return match ($event->severity) {
            'warning' => "You've reached {$percentage}% of your {$event->budgetType} AI budget (\${$event->current_spending} of \${$event->budget_limit})",
            'critical' => "CRITICAL: You've reached {$percentage}% of your {$event->budgetType} AI budget (\${$event->current_spending} of \${$event->budget_limit})",
            'exceeded' => "BUDGET EXCEEDED: Your {$event->budgetType} AI budget has been exceeded (\${$event->current_spending} of \${$event->budget_limit})",
            default => "Budget threshold reached: {$percentage}% of {$event->budgetType} budget"
        };
    }

    /**
     * Log budget threshold event for audit trail.
     *
     * @param  BudgetThresholdReached  $event  The event
     */
    protected function logBudgetThreshold(BudgetThresholdReached $event): void
    {
        logger()->info('Budget threshold logged', [
            'user_id' => $event->userId,
            'budget_type' => $event->budgetType,
            'severity' => $event->severity,
            'threshold_percentage' => $event->threshold_percentage,
            'current_spending' => $event->current_spending,
            'budget_limit' => $event->budget_limit,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle budget exceeded scenarios.
     *
     * @param  BudgetThresholdReached  $event  The event
     */
    protected function handleBudgetExceeded(BudgetThresholdReached $event): void
    {
        // Foundation implementation - will be expanded in Sprint 4b
        logger()->critical('Budget exceeded - administrative action may be required', [
            'user_id' => $event->userId,
            'budget_type' => $event->budgetType,
            'overage' => $event->current_spending - $event->budget_limit,
            'timestamp' => now()->toISOString(),
        ]);

        // TODO: In Sprint 4b, implement:
        // - Disable AI access for user
        // - Send admin notification
        // - Create support ticket
        // - Log security event
    }

    /**
     * Check for notification triggers in response events.
     *
     * @param  ResponseGenerated  $event  The event
     */
    protected function checkNotificationTriggers(ResponseGenerated $event): void
    {
        // Foundation implementation - will be expanded in Sprint 4b

        // Check for slow responses
        if ($event->total_processing_time > 30.0) {
            logger()->info('Slow response notification trigger', [
                'user_id' => $event->message->user_id ?? 0,
                'processing_time' => $event->total_processing_time,
                'provider' => $event->provider_metadata['provider'] ?? 'unknown',
            ]);
        }

        // Check for high token usage
        $tokens = $event->provider_metadata['tokens_used'] ?? 0;
        if ($tokens > 10000) {
            logger()->info('High token usage notification trigger', [
                'user_id' => $event->message->user_id ?? 0,
                'tokens_used' => $tokens,
                'provider' => $event->provider_metadata['provider'] ?? 'unknown',
            ]);
        }
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
        if ($executionTime > 2.0) { // Log if over 2 seconds (notifications should be fast)
            logger()->warning('Slow notification listener', [
                'method' => $method,
                'execution_time' => $executionTime,
                'listener' => static::class,
            ]);
        }

        // Log performance metrics for monitoring
        logger()->debug('Notification listener performance', [
            'method' => $method,
            'execution_time' => $executionTime,
            'queue' => $this->queue,
        ]);
    }
}
