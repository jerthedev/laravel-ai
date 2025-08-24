<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cost Tracking Failed Event
 *
 * Fired when cost tracking operations fail, enabling monitoring and alerting.
 */
class CostTrackingFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array  $context  Error context with details
     */
    public function __construct(
        public array $context
    ) {
        $this->context = array_merge([
            'failed_at' => now()->toISOString(),
            'severity' => $this->determineSeverity(),
        ], $context);
    }

    /**
     * Determine error severity based on context.
     *
     * @return string
     */
    protected function determineSeverity(): string
    {
        $error = $this->context['error'] ?? '';
        
        return match (true) {
            str_contains($error, 'database') => 'high',
            str_contains($error, 'timeout') => 'medium',
            str_contains($error, 'validation') => 'low',
            default => 'medium',
        };
    }

    /**
     * Get formatted error information.
     *
     * @return array
     */
    public function getErrorInfo(): array
    {
        return [
            'event_type' => $this->context['event'] ?? 'unknown',
            'message_id' => $this->context['message_id'] ?? null,
            'user_id' => $this->context['user_id'] ?? null,
            'provider' => $this->context['provider'] ?? 'unknown',
            'model' => $this->context['model'] ?? 'unknown',
            'error_message' => $this->context['error'] ?? 'Unknown error',
            'severity' => $this->context['severity'],
            'failed_at' => $this->context['failed_at'],
        ];
    }
}
