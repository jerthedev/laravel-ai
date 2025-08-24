<?php

namespace JTD\LaravelAI\Listeners;

use JTD\LaravelAI\Events\PerformanceThresholdExceeded;
use JTD\LaravelAI\Services\PerformanceAlertManager;

/**
 * Performance Alert Listener
 *
 * Listens for performance threshold exceeded events and triggers
 * automated alert notifications through the alert manager.
 */
class PerformanceAlertListener
{
    /**
     * Performance Alert Manager.
     */
    protected PerformanceAlertManager $alertManager;

    /**
     * Create the event listener.
     */
    public function __construct(PerformanceAlertManager $alertManager)
    {
        $this->alertManager = $alertManager;
    }

    /**
     * Handle the event.
     *
     * @param  PerformanceThresholdExceeded  $event  Performance event
     */
    public function handle(PerformanceThresholdExceeded $event): void
    {
        $this->alertManager->handlePerformanceThresholdExceeded($event);
    }

    /**
     * Determine if events should be queued.
     *
     * @return bool Should queue
     */
    public function shouldQueue(): bool
    {
        return config('ai.performance.alerts.queue_notifications', true);
    }

    /**
     * Get the name of the queue the listener should be dispatched to.
     *
     * @return string Queue name
     */
    public function viaQueue(): string
    {
        return config('ai.performance.alerts.queue_name', 'default');
    }
}
