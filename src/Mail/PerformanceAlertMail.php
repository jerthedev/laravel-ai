<?php

namespace JTD\LaravelAI\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Performance Alert Mail
 *
 * Email notification for performance threshold breaches.
 */
class PerformanceAlertMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Alert data.
     */
    public array $alert;

    /**
     * Create a new message instance.
     *
     * @param  array  $alert  Alert data
     */
    public function __construct(array $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $severity = strtoupper($this->alert['severity']);
        $component = $this->alert['component_name'];

        return new Envelope(
            subject: "[{$severity}] Performance Alert: {$component}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'laravel-ai::emails.performance-alert',
            with: [
                'alert' => $this->alert,
                'severity' => strtoupper($this->alert['severity']),
                'component' => $this->alert['component_name'],
                'duration' => round($this->alert['duration_ms'], 1),
                'threshold' => round($this->alert['threshold_ms'], 1),
                'exceededBy' => round($this->alert['threshold_exceeded_percentage'], 1),
                'dashboardUrl' => config('app.url') . '/api/ai/performance/dashboard',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
