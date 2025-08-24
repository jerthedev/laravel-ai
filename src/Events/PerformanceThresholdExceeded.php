<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Performance Threshold Exceeded Event
 *
 * Fired when a component exceeds its performance threshold,
 * enabling automated alerts and optimization responses.
 */
class PerformanceThresholdExceeded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Component that exceeded threshold.
     */
    public string $component;

    /**
     * Performance data.
     */
    public array $performanceData;

    /**
     * Create a new event instance.
     *
     * @param  string  $component  Component type
     * @param  array  $performanceData  Performance data
     */
    public function __construct(string $component, array $performanceData)
    {
        $this->component = $component;
        $this->performanceData = $performanceData;
    }

    /**
     * Get component name.
     *
     * @return string Component name
     */
    public function getComponentName(): string
    {
        return $this->performanceData['listener_name'] 
            ?? $this->performanceData['event_name'] 
            ?? $this->performanceData['job_name'] 
            ?? $this->performanceData['middleware_name'] 
            ?? 'unknown';
    }

    /**
     * Get duration in milliseconds.
     *
     * @return float Duration
     */
    public function getDuration(): float
    {
        return $this->performanceData['duration_ms'];
    }

    /**
     * Get threshold in milliseconds.
     *
     * @return float Threshold
     */
    public function getThreshold(): float
    {
        return $this->performanceData['threshold_ms'];
    }

    /**
     * Get threshold exceeded percentage.
     *
     * @return float Percentage over threshold
     */
    public function getThresholdExceededPercentage(): float
    {
        $threshold = $this->getThreshold();
        if ($threshold == 0) {
            return 0;
        }
        
        return (($this->getDuration() - $threshold) / $threshold) * 100;
    }

    /**
     * Get severity level.
     *
     * @return string Severity level
     */
    public function getSeverity(): string
    {
        $exceededPercentage = $this->getThresholdExceededPercentage();
        
        if ($exceededPercentage >= 200) return 'critical';
        if ($exceededPercentage >= 100) return 'high';
        if ($exceededPercentage >= 50) return 'medium';
        return 'low';
    }

    /**
     * Check if this is a critical performance issue.
     *
     * @return bool Is critical
     */
    public function isCritical(): bool
    {
        return $this->getSeverity() === 'critical';
    }

    /**
     * Get context data.
     *
     * @return array Context
     */
    public function getContext(): array
    {
        return $this->performanceData['context'] ?? [];
    }

    /**
     * Get alert data for notifications.
     *
     * @return array Alert data
     */
    public function getAlertData(): array
    {
        return [
            'component' => $this->component,
            'component_name' => $this->getComponentName(),
            'duration_ms' => $this->getDuration(),
            'threshold_ms' => $this->getThreshold(),
            'exceeded_percentage' => round($this->getThresholdExceededPercentage(), 1),
            'severity' => $this->getSeverity(),
            'timestamp' => $this->performanceData['timestamp'] ?? now(),
            'context' => $this->getContext(),
        ];
    }

    /**
     * Get formatted alert message.
     *
     * @return string Alert message
     */
    public function getAlertMessage(): string
    {
        $componentName = $this->getComponentName();
        $duration = round($this->getDuration(), 1);
        $threshold = round($this->getThreshold(), 1);
        $severity = strtoupper($this->getSeverity());
        
        return "[{$severity}] Performance threshold exceeded: {$componentName} took {$duration}ms (threshold: {$threshold}ms)";
    }

    /**
     * Get detailed alert description.
     *
     * @return string Detailed description
     */
    public function getDetailedDescription(): string
    {
        $exceededPercentage = round($this->getThresholdExceededPercentage(), 1);
        $context = $this->getContext();
        
        $description = $this->getAlertMessage() . "\n";
        $description .= "Exceeded threshold by {$exceededPercentage}%\n";
        
        if (!empty($context)) {
            $description .= "Context: " . json_encode($context, JSON_PRETTY_PRINT);
        }
        
        return $description;
    }

    /**
     * Check if alert should be sent.
     *
     * @return bool Should send alert
     */
    public function shouldSendAlert(): bool
    {
        // Send alerts for medium severity and above
        return in_array($this->getSeverity(), ['medium', 'high', 'critical']);
    }

    /**
     * Get recommended actions.
     *
     * @return array Recommended actions
     */
    public function getRecommendedActions(): array
    {
        $component = $this->component;
        $severity = $this->getSeverity();
        
        $actions = [
            'event_processing' => [
                'critical' => [
                    'Break down complex event handlers',
                    'Move heavy processing to queued jobs',
                    'Review event listener priorities',
                ],
                'high' => [
                    'Optimize event handler logic',
                    'Consider caching frequently accessed data',
                    'Review database queries in handlers',
                ],
                'medium' => [
                    'Monitor event processing patterns',
                    'Consider async processing for non-critical handlers',
                ],
            ],
            'listener_execution' => [
                'critical' => [
                    'Move processing to background jobs',
                    'Optimize database queries',
                    'Reduce external API calls',
                ],
                'high' => [
                    'Cache frequently accessed data',
                    'Optimize listener logic',
                    'Consider lazy loading',
                ],
                'medium' => [
                    'Review listener necessity',
                    'Monitor execution patterns',
                ],
            ],
            'queue_job' => [
                'critical' => [
                    'Break job into smaller chunks',
                    'Optimize processing logic',
                    'Review job dependencies',
                ],
                'high' => [
                    'Optimize database operations',
                    'Reduce external service calls',
                    'Consider parallel processing',
                ],
                'medium' => [
                    'Monitor job execution patterns',
                    'Review job retry logic',
                ],
            ],
            'middleware_execution' => [
                'critical' => [
                    'Minimize middleware processing',
                    'Move complex logic elsewhere',
                    'Optimize database queries',
                ],
                'high' => [
                    'Implement caching',
                    'Optimize middleware order',
                    'Reduce external calls',
                ],
                'medium' => [
                    'Review middleware necessity',
                    'Monitor execution patterns',
                ],
            ],
        ];

        return $actions[$component][$severity] ?? [
            'Review component performance',
            'Consider optimization opportunities',
        ];
    }
}
