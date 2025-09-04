<?php

namespace JTD\LaravelAI\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Services\EventPerformanceTracker;

/**
 * Performance Monitoring Middleware
 *
 * Monitors middleware execution times and tracks performance metrics
 * for the entire middleware stack with <10ms overhead target.
 */
class PerformanceMonitoringMiddleware
{
    /**
     * Event Performance Tracker.
     */
    protected EventPerformanceTracker $performanceTracker;

    /**
     * Performance overhead threshold in milliseconds.
     */
    protected float $overheadThreshold = 2.0;

    /**
     * Create a new middleware instance.
     */
    public function __construct(EventPerformanceTracker $performanceTracker)
    {
        $this->performanceTracker = $performanceTracker;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  HTTP request
     * @param  Closure  $next  Next middleware
     * @return Response HTTP response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip monitoring if disabled
        if (! config('ai.performance.middleware_monitoring', true)) {
            return $next($request);
        }

        $monitoringStartTime = microtime(true);
        $requestStartTime = $monitoringStartTime;

        // Track request metadata
        $requestMetadata = $this->extractRequestMetadata($request);

        // Execute the request through middleware stack
        $response = $next($request);

        $totalRequestTime = (microtime(true) - $requestStartTime) * 1000;
        $monitoringOverhead = (microtime(true) - $monitoringStartTime - ($totalRequestTime / 1000)) * 1000;

        // Track overall request performance
        $this->trackRequestPerformance($request, $response, $totalRequestTime, $requestMetadata);

        // Track monitoring overhead
        $this->trackMonitoringOverhead($monitoringOverhead);

        // Log performance warnings if needed
        $this->logPerformanceWarnings($request, $totalRequestTime, $monitoringOverhead);

        return $response;
    }

    /**
     * Track individual middleware performance.
     *
     * @param  string  $middlewareName  Middleware class name
     * @param  Request  $request  HTTP request
     * @param  Closure  $next  Next middleware
     * @return Response HTTP response
     */
    public function trackMiddleware(string $middlewareName, Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        try {
            $response = $next($request);
            $duration = (microtime(true) - $startTime) * 1000;

            $this->performanceTracker->trackMiddlewarePerformance($middlewareName, $duration, [
                'success' => true,
                'route' => $request->route()?->getName() ?? $request->path(),
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
                'memory_usage' => memory_get_usage(true),
            ]);

            return $response;
        } catch (\Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->performanceTracker->trackMiddlewarePerformance($middlewareName, $duration, [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'route' => $request->route()?->getName() ?? $request->path(),
                'method' => $request->method(),
            ]);

            throw $e;
        }
    }

    /**
     * Track request performance.
     *
     * @param  Request  $request  HTTP request
     * @param  Response  $response  HTTP response
     * @param  float  $duration  Duration in milliseconds
     * @param  array  $metadata  Request metadata
     */
    protected function trackRequestPerformance(Request $request, Response $response, float $duration, array $metadata): void
    {
        $this->performanceTracker->trackMiddlewarePerformance('request_total', $duration, array_merge($metadata, [
            'status_code' => $response->getStatusCode(),
            'response_size' => strlen($response->getContent()),
            'memory_peak' => memory_get_peak_usage(true),
            'success' => $response->getStatusCode() < 400,
        ]));
    }

    /**
     * Track monitoring overhead.
     *
     * @param  float  $overhead  Overhead in milliseconds
     */
    protected function trackMonitoringOverhead(float $overhead): void
    {
        $this->performanceTracker->trackMiddlewarePerformance('monitoring_overhead', $overhead, [
            'threshold_ms' => $this->overheadThreshold,
            'exceeded_threshold' => $overhead > $this->overheadThreshold,
        ]);
    }

    /**
     * Extract request metadata.
     *
     * @param  Request  $request  HTTP request
     * @return array Request metadata
     */
    protected function extractRequestMetadata(Request $request): array
    {
        return [
            'route' => $request->route()?->getName() ?? $request->path(),
            'method' => $request->method(),
            'path' => $request->path(),
            'query_count' => count($request->query()),
            'payload_size' => strlen($request->getContent()),
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_files' => $request->hasFile('*'),
        ];
    }

    /**
     * Log performance warnings.
     *
     * @param  Request  $request  HTTP request
     * @param  float  $totalTime  Total request time
     * @param  float  $overhead  Monitoring overhead
     */
    protected function logPerformanceWarnings(Request $request, float $totalTime, float $overhead): void
    {
        // Log slow requests
        $slowRequestThreshold = config('ai.performance.slow_request_threshold', 1000); // 1 second
        if ($totalTime > $slowRequestThreshold) {
            Log::warning('Slow request detected', [
                'duration_ms' => $totalTime,
                'threshold_ms' => $slowRequestThreshold,
                'route' => $request->route()?->getName() ?? $request->path(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
            ]);
        }

        // Log high monitoring overhead
        if ($overhead > $this->overheadThreshold) {
            Log::warning('High performance monitoring overhead', [
                'overhead_ms' => $overhead,
                'threshold_ms' => $this->overheadThreshold,
                'route' => $request->route()?->getName() ?? $request->path(),
            ]);
        }
    }
}

/**
 * Middleware Performance Tracker Trait
 *
 * Provides easy performance tracking for middleware classes.
 */
trait TracksMiddlewarePerformance
{
    /**
     * Track middleware execution with performance monitoring.
     *
     * @param  Request  $request  HTTP request
     * @param  Closure  $next  Next middleware
     * @return Response HTTP response
     */
    protected function trackExecution(Request $request, Closure $next): Response
    {
        $performanceMonitor = app(PerformanceMonitoringMiddleware::class);

        return $performanceMonitor->trackMiddleware(static::class, $request, $next);
    }
}
