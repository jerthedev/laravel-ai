<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Performance Alert Triggered Event
 *
 * Dispatched when a performance threshold is exceeded or performance
 * degradation is detected in the middleware system.
 */
class PerformanceAlertTriggered
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array  $alertData  Alert information and metrics
     */
    public function __construct(
        public readonly array $alertData
    ) {}

    /**
     * Get alert severity level.
     *
     * @return string Severity (critical, warning, info)
     */
    public function getSeverity(): string
    {
        return $this->alertData['severity'] ?? 'info';
    }

    /**
     * Get affected component.
     *
     * @return string Component name
     */
    public function getComponent(): string
    {
        return $this->alertData['component'] ?? 'unknown';
    }

    /**
     * Get alert message.
     *
     * @return string Alert message
     */
    public function getMessage(): string
    {
        return $this->alertData['message'] ?? 'Performance alert triggered';
    }

    /**
     * Get metric that triggered the alert.
     *
     * @return string Metric name
     */
    public function getMetric(): string
    {
        return $this->alertData['metric'] ?? 'unknown';
    }

    /**
     * Get metric value that triggered the alert.
     *
     * @return mixed Metric value
     */
    public function getValue()
    {
        return $this->alertData['value'] ?? null;
    }

    /**
     * Get threshold that was exceeded.
     *
     * @return mixed Threshold value
     */
    public function getThreshold()
    {
        return $this->alertData['threshold'] ?? null;
    }

    /**
     * Get alert context data.
     *
     * @return array Context information
     */
    public function getContext(): array
    {
        return $this->alertData['context'] ?? [];
    }

    /**
     * Get system information at time of alert.
     *
     * @return array System metrics
     */
    public function getSystemInfo(): array
    {
        return $this->alertData['system_info'] ?? [];
    }

    /**
     * Check if alert is critical severity.
     *
     * @return bool True if critical
     */
    public function isCritical(): bool
    {
        return $this->getSeverity() === 'critical';
    }

    /**
     * Check if alert is warning severity.
     *
     * @return bool True if warning
     */
    public function isWarning(): bool
    {
        return $this->getSeverity() === 'warning';
    }

    /**
     * Get alert as array for serialization.
     *
     * @return array Alert data
     */
    public function toArray(): array
    {
        return $this->alertData;
    }
}
