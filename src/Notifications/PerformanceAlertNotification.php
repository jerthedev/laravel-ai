<?php

namespace JTD\LaravelAI\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use JTD\LaravelAI\Mail\PerformanceAlertMail;

/**
 * Performance Alert Notification
 *
 * Sends performance threshold breach notifications via multiple channels
 * with detailed context and recommended actions.
 */
class PerformanceAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Alert data.
     */
    protected array $alert;

    /**
     * Create a new notification instance.
     *
     * @param  array  $alert  Alert data
     */
    public function __construct(array $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable  Notifiable entity
     * @return array Delivery channels
     */
    public function via($notifiable): array
    {
        $channels = ['mail'];

        if (config('ai.performance.alerts.slack.enabled', false)) {
            $channels[] = 'slack';
        }

        if (config('ai.performance.alerts.database.enabled', true)) {
            $channels[] = 'database';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable  Notifiable entity
     * @return PerformanceAlertMail Mail message
     */
    public function toMail($notifiable): PerformanceAlertMail
    {
        return new PerformanceAlertMail($this->alert);
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  mixed  $notifiable  Notifiable entity
     * @return SlackMessage Slack message
     */
    public function toSlack($notifiable): SlackMessage
    {
        $severity = strtoupper($this->alert['severity']);
        $component = $this->alert['component_name'];
        $duration = round($this->alert['duration_ms'], 1);
        $threshold = round($this->alert['threshold_ms'], 1);
        $exceededBy = round($this->alert['threshold_exceeded_percentage'], 1);

        $color = $this->getSlackColor($this->alert['severity']);
        $emoji = $this->getSlackEmoji($this->alert['severity']);

        $message = (new SlackMessage)
            ->from('Laravel AI Performance Monitor', ':chart_with_upwards_trend:')
            ->to(config('ai.performance.alerts.slack.channel', '#alerts'))
            ->content("{$emoji} **{$severity} Performance Alert**")
            ->attachment(function ($attachment) use ($component, $duration, $threshold, $exceededBy, $color) {
                $attachment->title("Performance Threshold Exceeded: {$component}")
                    ->color($color)
                    ->fields([
                        'Component' => $component,
                        'Duration' => "{$duration}ms",
                        'Threshold' => "{$threshold}ms",
                        'Exceeded By' => "{$exceededBy}%",
                    ])
                    ->markdown(['text', 'fields']);

                // Add recommended actions
                if (!empty($this->alert['recommended_actions'])) {
                    $actions = implode("\n• ", $this->alert['recommended_actions']);
                    $attachment->field('Recommended Actions', "• {$actions}");
                }

                // Add timestamp
                $attachment->timestamp($this->alert['timestamp']);
            });

        return $message;
    }

    /**
     * Get the database representation of the notification.
     *
     * @param  mixed  $notifiable  Notifiable entity
     * @return array Database data
     */
    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'performance_alert',
            'severity' => $this->alert['severity'],
            'component' => $this->alert['component'],
            'component_name' => $this->alert['component_name'],
            'message' => $this->alert['message'],
            'duration_ms' => $this->alert['duration_ms'],
            'threshold_ms' => $this->alert['threshold_ms'],
            'threshold_exceeded_percentage' => $this->alert['threshold_exceeded_percentage'],
            'context' => $this->alert['context'],
            'recommended_actions' => $this->alert['recommended_actions'],
            'timestamp' => $this->alert['timestamp'],
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable  Notifiable entity
     * @return array Array representation
     */
    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Determine if the notification should be sent.
     *
     * @param  mixed  $notifiable  Notifiable entity
     * @param  string  $channel  Notification channel
     * @return bool Should send
     */
    public function shouldSend($notifiable, string $channel): bool
    {
        // Don't send test alerts in production unless explicitly enabled
        if (isset($this->alert['context']['test']) &&
            $this->alert['context']['test'] === true &&
            app()->environment('production') &&
            !config('ai.performance.alerts.send_test_in_production', false)) {
            return false;
        }

        return true;
    }

    /**
     * Get Slack color for severity.
     *
     * @param  string  $severity  Severity level
     * @return string Slack color
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
     * Get Slack emoji for severity.
     *
     * @param  string  $severity  Severity level
     * @return string Slack emoji
     */
    protected function getSlackEmoji(string $severity): string
    {
        return match ($severity) {
            'critical' => ':rotating_light:',
            'high' => ':warning:',
            'medium' => ':yellow_circle:',
            'low' => ':information_source:',
            default => ':warning:',
        };
    }

    /**
     * Get the notification's unique identifier.
     *
     * @return string Unique identifier
     */
    public function uniqueId(): string
    {
        return "performance_alert_{$this->alert['component']}_{$this->alert['component_name']}_" .
               md5($this->alert['message'] . $this->alert['timestamp']);
    }

    /**
     * Get the notification's tags for queue management.
     *
     * @return array Tags
     */
    public function tags(): array
    {
        return [
            'performance_alert',
            "severity:{$this->alert['severity']}",
            "component:{$this->alert['component']}",
        ];
    }

    /**
     * Determine the time at which the notification should be sent.
     *
     * @return \DateTimeInterface|null Send time
     */
    public function delay(): ?\DateTimeInterface
    {
        // Critical alerts are sent immediately
        if ($this->alert['severity'] === 'critical') {
            return null;
        }

        // Other alerts can have a small delay to allow for batching
        return now()->addSeconds(30);
    }

    /**
     * Get the number of times the notification may be attempted.
     *
     * @return int Max attempts
     */
    public function tries(): int
    {
        return match ($this->alert['severity']) {
            'critical' => 5,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 2,
        };
    }

    /**
     * Calculate the number of seconds to wait before retrying.
     *
     * @param  int  $attempt  Attempt number
     * @return int Backoff seconds
     */
    public function backoff(int $attempt): int
    {
        return match ($this->alert['severity']) {
            'critical' => 30,  // 30 seconds
            'high' => 60,      // 1 minute
            'medium' => 120,   // 2 minutes
            'low' => 300,      // 5 minutes
            default => 60,
        };
    }
}
