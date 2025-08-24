<?php

namespace JTD\LaravelAI\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use JTD\LaravelAI\Services\EventPerformanceTracker;

/**
 * Performance Tracking Listener
 *
 * Automatically tracks performance metrics for events, listeners, and jobs
 * throughout the AI system for comprehensive performance monitoring.
 */
class PerformanceTrackingListener
{
    /**
     * Event Performance Tracker.
     */
    protected EventPerformanceTracker $performanceTracker;

    /**
     * Active event tracking data.
     */
    protected array $activeEvents = [];

    /**
     * Active job tracking data.
     */
    protected array $activeJobs = [];

    /**
     * Create a new listener instance.
     */
    public function __construct(EventPerformanceTracker $performanceTracker)
    {
        $this->performanceTracker = $performanceTracker;
    }

    /**
     * Register event listeners.
     *
     * @param  Dispatcher  $events  Event dispatcher
     */
    public function subscribe(Dispatcher $events): void
    {
        // Track all events
        $events->listen('*', [$this, 'handleEvent']);

        // Track queue jobs
        $events->listen(JobProcessing::class, [$this, 'handleJobProcessing']);
        $events->listen(JobProcessed::class, [$this, 'handleJobProcessed']);
    }

    /**
     * Handle any event for performance tracking.
     *
     * @param  string  $eventName  Event name
     * @param  array  $payload  Event payload
     */
    public function handleEvent(string $eventName, array $payload): void
    {
        // Skip performance tracking events to avoid recursion
        if ($this->shouldSkipEvent($eventName)) {
            return;
        }

        $eventId = uniqid('event_', true);
        $startTime = microtime(true);

        // Store event start time
        $this->activeEvents[$eventId] = [
            'name' => $eventName,
            'start_time' => $startTime,
            'payload_size' => $this->calculatePayloadSize($payload),
        ];

        // Track event completion after all listeners have run
        app()->terminating(function () use ($eventId, $eventName) {
            if (isset($this->activeEvents[$eventId])) {
                $duration = (microtime(true) - $this->activeEvents[$eventId]['start_time']) * 1000;

                $this->performanceTracker->trackEventProcessing($eventName, $duration, [
                    'payload_size' => $this->activeEvents[$eventId]['payload_size'],
                    'event_id' => $eventId,
                ]);

                unset($this->activeEvents[$eventId]);
            }
        });
    }

    /**
     * Handle job processing start.
     *
     * @param  JobProcessing  $event  Job processing event
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        $jobId = $event->job->getJobId();
        $jobName = $event->job->getName();

        $this->activeJobs[$jobId] = [
            'name' => $jobName,
            'start_time' => microtime(true),
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
        ];
    }

    /**
     * Handle job processing completion.
     *
     * @param  JobProcessed  $event  Job processed event
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        $jobId = $event->job->getJobId();

        if (!isset($this->activeJobs[$jobId])) {
            return;
        }

        $jobData = $this->activeJobs[$jobId];
        $duration = (microtime(true) - $jobData['start_time']) * 1000;

        $this->performanceTracker->trackQueueJobPerformance($jobData['name'], $duration, [
            'connection' => $jobData['connection'],
            'queue' => $jobData['queue'],
            'job_id' => $jobId,
        ]);

        unset($this->activeJobs[$jobId]);
    }

    /**
     * Track listener execution performance.
     *
     * @param  string  $listenerClass  Listener class name
     * @param  string  $eventName  Event name
     * @param  callable  $listenerMethod  Listener method
     * @param  array  $parameters  Method parameters
     * @return mixed Listener result
     */
    public function trackListenerExecution(string $listenerClass, string $eventName, callable $listenerMethod, array $parameters)
    {
        $startTime = microtime(true);

        try {
            $result = call_user_func_array($listenerMethod, $parameters);

            $duration = (microtime(true) - $startTime) * 1000;

            $this->performanceTracker->trackListenerExecution($listenerClass, $eventName, $duration, [
                'success' => true,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ]);

            return $result;

        } catch (\Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->performanceTracker->trackListenerExecution($listenerClass, $eventName, $duration, [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);

            throw $e;
        }
    }

    /**
     * Check if event should be skipped for performance tracking.
     *
     * @param  string  $eventName  Event name
     * @return bool Should skip
     */
    protected function shouldSkipEvent(string $eventName): bool
    {
        $skipPatterns = [
            'JTD\LaravelAI\Events\PerformanceThresholdExceeded',
            'Illuminate\Log\Events\MessageLogged',
            'Illuminate\Cache\Events\*',
            'Illuminate\Database\Events\QueryExecuted',
        ];

        foreach ($skipPatterns as $pattern) {
            if (fnmatch($pattern, $eventName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate payload size safely without serializing closures.
     *
     * @param  array  $payload  Event payload
     * @return int Estimated payload size
     */
    protected function calculatePayloadSize(array $payload): int
    {
        try {
            // Try to serialize the payload
            return strlen(serialize($payload));
        } catch (\Exception $e) {
            // If serialization fails (due to closures), estimate size
            return $this->estimatePayloadSize($payload);
        }
    }

    /**
     * Estimate payload size without serialization.
     *
     * @param  mixed  $data  Data to estimate
     * @return int Estimated size
     */
    protected function estimatePayloadSize($data): int
    {
        if (is_string($data)) {
            return strlen($data);
        }

        if (is_array($data)) {
            $size = 0;
            foreach ($data as $key => $value) {
                $size += strlen((string) $key);
                $size += $this->estimatePayloadSize($value);
            }
            return $size;
        }

        if (is_object($data)) {
            if ($data instanceof \Closure) {
                return 100; // Estimate closure size
            }
            return strlen(get_class($data)) + 50; // Estimate object size
        }

        if (is_numeric($data)) {
            return strlen((string) $data);
        }

        if (is_bool($data)) {
            return 1;
        }

        return 10; // Default estimate
    }
}
