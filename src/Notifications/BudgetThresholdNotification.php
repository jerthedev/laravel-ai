<?php

namespace JTD\LaravelAI\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use JTD\LaravelAI\Events\BudgetThresholdReached;

/**
 * Budget Threshold Notification
 *
 * Multi-channel notification for budget threshold alerts with customizable
 * content based on severity and budget type.
 */
class BudgetThresholdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The budget threshold event.
     */
    protected BudgetThresholdReached $event;

    /**
     * The alert severity.
     */
    protected string $severity;

    /**
     * The notification channel.
     */
    protected string $channel;

    /**
     * Create a new notification instance.
     *
     * @param  BudgetThresholdReached  $event  The budget threshold event
     * @param  string  $severity  Alert severity
     * @param  string  $channel  Notification channel
     */
    public function __construct(BudgetThresholdReached $event, string $severity, string $channel)
    {
        $this->event = $event;
        $this->severity = $severity;
        $this->channel = $channel;
        $this->queue = 'notifications';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable  The notifiable entity
     * @return array Delivery channels
     */
    public function via($notifiable): array
    {
        return [$this->channel];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable  The notifiable entity
     * @return MailMessage Mail message
     */
    public function toMail($notifiable): MailMessage
    {
        $subject = $this->getEmailSubject();
        $greeting = $this->getEmailGreeting();
        $message = $this->getEmailMessage();
        $actionText = $this->getEmailActionText();
        $actionUrl = $this->getEmailActionUrl();

        $mailMessage = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($message)
            ->line($this->getBudgetDetails());

        if ($actionUrl) {
            $mailMessage->action($actionText, $actionUrl);
        }

        $mailMessage->line($this->getRecommendations())
                   ->line('Thank you for using our AI services responsibly.');

        // Add severity-based styling
        if ($this->severity === 'critical') {
            $mailMessage->error();
        } elseif ($this->severity === 'high') {
            $mailMessage->warning();
        }

        return $mailMessage;
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  mixed  $notifiable  The notifiable entity
     * @return SlackMessage Slack message
     */
    public function toSlack($notifiable): SlackMessage
    {
        $color = $this->getSlackColor();
        $title = $this->getSlackTitle();
        $message = $this->getSlackMessage();

        return (new SlackMessage)
            ->$color()
            ->content($title)
            ->attachment(function ($attachment) use ($message) {
                $attachment->title('Budget Alert Details')
                          ->content($message)
                          ->fields($this->getSlackFields())
                          ->footer('AI Budget Monitor')
                          ->timestamp($this->event->metadata['triggered_at'] ?? now());
            });
    }

    /**
     * Get the SMS representation of the notification.
     *
     * @param  mixed  $notifiable  The notifiable entity
     * @return string SMS message
     */
    public function toNexmo($notifiable): string
    {
        return $this->getSmsMessage();
    }

    /**
     * Get the database representation of the notification.
     *
     * @param  mixed  $notifiable  The notifiable entity
     * @return array Database data
     */
    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'budget_threshold',
            'severity' => $this->severity,
            'budget_type' => $this->event->budgetType,
            'threshold_percentage' => $this->event->thresholdPercentage,
            'current_spending' => $this->event->currentSpending,
            'budget_limit' => $this->event->budgetLimit,
            'additional_cost' => $this->event->additionalCost,
            'project_id' => $this->event->projectId,
            'organization_id' => $this->event->organizationId,
            'message' => $this->getNotificationMessage(),
            'action_url' => $this->getEmailActionUrl(),
            'recommendations' => $this->getRecommendationsList(),
        ];
    }

    /**
     * Get email subject based on severity and budget type.
     *
     * @return string Email subject
     */
    protected function getEmailSubject(): string
    {
        $budgetTypeDisplay = $this->getBudgetTypeDisplay();
        $severityIcon = $this->getSeverityIcon();
        
        return match ($this->severity) {
            'critical' => "{$severityIcon} URGENT: {$budgetTypeDisplay} Budget Exceeded",
            'high' => "{$severityIcon} WARNING: {$budgetTypeDisplay} Budget Alert",
            'medium' => "{$severityIcon} NOTICE: {$budgetTypeDisplay} Budget Threshold",
            default => "{$severityIcon} {$budgetTypeDisplay} Budget Alert",
        };
    }

    /**
     * Get email greeting.
     *
     * @return string Email greeting
     */
    protected function getEmailGreeting(): string
    {
        return match ($this->severity) {
            'critical' => 'Urgent Budget Alert!',
            'high' => 'Important Budget Warning',
            'medium' => 'Budget Threshold Notice',
            default => 'Budget Alert',
        };
    }

    /**
     * Get email message content.
     *
     * @return string Email message
     */
    protected function getEmailMessage(): string
    {
        $budgetTypeDisplay = $this->getBudgetTypeDisplay();
        $percentage = number_format($this->event->thresholdPercentage, 1);
        
        if ($this->event->thresholdPercentage >= 100) {
            return "Your {$budgetTypeDisplay} budget has been exceeded by {$percentage}%. Immediate action is required to prevent service interruption.";
        }
        
        return "Your {$budgetTypeDisplay} budget has reached {$percentage}% of the limit. Please review your usage and consider adjusting your budget if needed.";
    }

    /**
     * Get budget details for email.
     *
     * @return string Budget details
     */
    protected function getBudgetDetails(): string
    {
        $current = '$' . number_format($this->event->currentSpending, 2);
        $limit = '$' . number_format($this->event->budgetLimit, 2);
        $additional = '$' . number_format($this->event->additionalCost, 2);
        
        $details = "Current spending: {$current} | Budget limit: {$limit}";
        
        if ($this->event->additionalCost > 0) {
            $details .= " | Additional cost: {$additional}";
        }
        
        return $details;
    }

    /**
     * Get email action text.
     *
     * @return string Action text
     */
    protected function getEmailActionText(): string
    {
        return match ($this->severity) {
            'critical' => 'Manage Budget Now',
            'high' => 'Review Budget Settings',
            'medium' => 'View Budget Dashboard',
            default => 'View Details',
        };
    }

    /**
     * Get email action URL.
     *
     * @return string|null Action URL
     */
    protected function getEmailActionUrl(): ?string
    {
        // This would typically link to your budget management dashboard
        $baseUrl = config('app.url');
        
        if ($this->event->projectId) {
            return "{$baseUrl}/projects/{$this->event->projectId}/budget";
        }
        
        if ($this->event->organizationId) {
            return "{$baseUrl}/organizations/{$this->event->organizationId}/budget";
        }
        
        return "{$baseUrl}/budget";
    }

    /**
     * Get recommendations text.
     *
     * @return string Recommendations
     */
    protected function getRecommendations(): string
    {
        $recommendations = $this->getRecommendationsList();
        return 'Recommended actions: ' . implode(', ', array_slice($recommendations, 0, 3));
    }

    /**
     * Get recommendations list.
     *
     * @return array Recommendations
     */
    protected function getRecommendationsList(): array
    {
        $recommendations = [];
        
        if ($this->event->thresholdPercentage >= 100) {
            $recommendations[] = 'Increase budget limit immediately';
            $recommendations[] = 'Review recent high-cost operations';
            $recommendations[] = 'Consider optimizing AI usage patterns';
        } else {
            $recommendations[] = 'Monitor usage closely';
            $recommendations[] = 'Review cost optimization opportunities';
            $recommendations[] = 'Consider increasing budget if needed';
        }
        
        switch ($this->event->budgetType) {
            case 'daily':
                $recommendations[] = 'Budget will reset tomorrow';
                break;
            case 'monthly':
                $recommendations[] = 'Budget will reset next month';
                break;
            case 'per_request':
                $recommendations[] = 'Reduce request complexity';
                break;
        }
        
        return $recommendations;
    }

    /**
     * Get Slack color based on severity.
     *
     * @return string Slack color method
     */
    protected function getSlackColor(): string
    {
        return match ($this->severity) {
            'critical' => 'error',
            'high' => 'warning',
            'medium' => 'good',
            default => 'good',
        };
    }

    /**
     * Get Slack title.
     *
     * @return string Slack title
     */
    protected function getSlackTitle(): string
    {
        $icon = $this->getSeverityIcon();
        $budgetType = $this->getBudgetTypeDisplay();
        $percentage = number_format($this->event->thresholdPercentage, 1);
        
        return "{$icon} {$budgetType} Budget Alert: {$percentage}%";
    }

    /**
     * Get Slack message content.
     *
     * @return string Slack message
     */
    protected function getSlackMessage(): string
    {
        $budgetType = $this->getBudgetTypeDisplay();
        $current = number_format($this->event->currentSpending, 2);
        $limit = number_format($this->event->budgetLimit, 2);
        
        return "Your {$budgetType} budget has reached {$this->event->thresholdPercentage}% of the limit.\n" .
               "Current: \${$current} | Limit: \${$limit}";
    }

    /**
     * Get Slack fields.
     *
     * @return array Slack fields
     */
    protected function getSlackFields(): array
    {
        $fields = [
            'Budget Type' => $this->getBudgetTypeDisplay(),
            'Current Spending' => '$' . number_format($this->event->currentSpending, 2),
            'Budget Limit' => '$' . number_format($this->event->budgetLimit, 2),
            'Threshold' => number_format($this->event->thresholdPercentage, 1) . '%',
        ];
        
        if ($this->event->additionalCost > 0) {
            $fields['Additional Cost'] = '$' . number_format($this->event->additionalCost, 2);
        }
        
        if ($this->event->projectId) {
            $fields['Project ID'] = $this->event->projectId;
        }
        
        return $fields;
    }

    /**
     * Get SMS message.
     *
     * @return string SMS message
     */
    protected function getSmsMessage(): string
    {
        $budgetType = $this->getBudgetTypeDisplay();
        $percentage = number_format($this->event->thresholdPercentage, 1);
        $limit = number_format($this->event->budgetLimit, 2);
        
        return "BUDGET ALERT: Your {$budgetType} budget has reached {$percentage}% (limit: \${$limit}). " .
               "Please review your usage. Reply STOP to opt out.";
    }

    /**
     * Get notification message for database storage.
     *
     * @return string Notification message
     */
    protected function getNotificationMessage(): string
    {
        $budgetType = $this->getBudgetTypeDisplay();
        $percentage = number_format($this->event->thresholdPercentage, 1);
        
        return "Your {$budgetType} budget has reached {$percentage}% of the limit.";
    }

    /**
     * Get budget type display name.
     *
     * @return string Budget type display name
     */
    protected function getBudgetTypeDisplay(): string
    {
        return match ($this->event->budgetType) {
            'daily' => 'Daily',
            'monthly' => 'Monthly',
            'per_request' => 'Per-Request',
            'project' => 'Project',
            'organization' => 'Organization',
            default => ucfirst($this->event->budgetType),
        };
    }

    /**
     * Get severity icon.
     *
     * @return string Severity icon
     */
    protected function getSeverityIcon(): string
    {
        return match ($this->severity) {
            'critical' => '🚨',
            'high' => '⚠️',
            'medium' => '📊',
            default => 'ℹ️',
        };
    }
}
