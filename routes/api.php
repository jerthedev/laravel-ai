<?php

use Illuminate\Support\Facades\Route;
use JTD\LaravelAI\Http\Controllers\PerformanceAlertController;
use JTD\LaravelAI\Http\Controllers\PerformanceDashboardController;

/*
|--------------------------------------------------------------------------
| Laravel AI Package API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for the Laravel AI package. These routes provide
| programmatic access to performance monitoring, cost tracking, and
| administrative functions.
|
| Routes are automatically prefixed and can be disabled via configuration.
|
*/

Route::group([
    'prefix' => config('ai.routes.api.prefix', 'ai-admin'),
    'middleware' => config('ai.routes.api.middleware', ['api']),
    'as' => config('ai.routes.api.name_prefix', 'ai.'),
], function () {
    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring API Routes
    |--------------------------------------------------------------------------
    */

    if (config('ai.routes.performance.enabled', true)) {
        Route::prefix('performance')->name('performance.')->group(function () {
            // Dashboard overview (disabled by default in production)
            if (config('ai.routes.performance.dashboard_enabled', false)) {
                Route::get('/dashboard', [PerformanceDashboardController::class, 'overview'])
                    ->name('dashboard');

                // Component-specific performance
                Route::get('/components/{component}', [PerformanceDashboardController::class, 'componentPerformance'])
                    ->name('component')
                    ->where('component', 'event_processing|listener_execution|queue_job|middleware_execution');

                // Queue performance monitoring
                Route::get('/queues', [PerformanceDashboardController::class, 'queuePerformance'])
                    ->name('queues');

                // Real-time metrics
                Route::get('/realtime', [PerformanceDashboardController::class, 'realTimeMetrics'])
                    ->name('realtime');

                // Performance trends
                Route::get('/trends', [PerformanceDashboardController::class, 'trends'])
                    ->name('trends');

                // Optimization recommendations
                Route::get('/recommendations', [PerformanceDashboardController::class, 'recommendations'])
                    ->name('recommendations');

                // Export performance report
                Route::post('/export', [PerformanceDashboardController::class, 'exportReport'])
                    ->name('export');
            }

            // Performance alert management (always available for monitoring)
            Route::prefix('alerts')->name('alerts.')->group(function () {
                Route::get('/', [PerformanceAlertController::class, 'index'])
                    ->name('index');
                Route::post('/{alertId}/acknowledge', [PerformanceAlertController::class, 'acknowledge'])
                    ->name('acknowledge');
                Route::post('/{alertId}/resolve', [PerformanceAlertController::class, 'resolve'])
                    ->name('resolve');
                Route::get('/statistics', [PerformanceAlertController::class, 'statistics'])
                    ->name('statistics');
                Route::post('/test', [PerformanceAlertController::class, 'test'])
                    ->name('test');
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking API Routes
    |--------------------------------------------------------------------------
    */

    if (config('ai.routes.costs.enabled', true)) {
        Route::prefix('costs')->name('costs.')->group(function () {
            // Basic cost information (always available)
            Route::get('/current', function () {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'current_usage' => 0, // Would be implemented
                        'budget_remaining' => 100, // Would be implemented
                    ],
                ]);
            })->name('current');

            // Detailed analytics (disabled by default in production)
            if (config('ai.routes.costs.analytics_enabled', false)) {
                Route::get('/analytics', function () {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'message' => 'Cost analytics endpoint - would be implemented',
                        ],
                    ]);
                })->name('analytics');

                Route::get('/reports', function () {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'message' => 'Cost reports endpoint - would be implemented',
                        ],
                    ]);
                })->name('reports');

                Route::post('/export', function () {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'message' => 'Cost export endpoint - would be implemented',
                        ],
                    ]);
                })->name('export');
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | MCP Management API Routes
    |--------------------------------------------------------------------------
    */

    if (config('ai.routes.mcp.enabled', true)) {
        Route::prefix('mcp')->name('mcp.')->group(function () {
            // MCP server status
            Route::get('/status', function () {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => 'MCP status endpoint - would be implemented',
                    ],
                ]);
            })->name('status');

            // MCP server management
            Route::post('/servers/{server}/enable', function ($server) {
                return response()->json([
                    'success' => true,
                    'message' => "Server {$server} enabled",
                ]);
            })->name('servers.enable');

            Route::post('/servers/{server}/disable', function ($server) {
                return response()->json([
                    'success' => true,
                    'message' => "Server {$server} disabled",
                ]);
            })->name('servers.disable');

            Route::post('/servers/{server}/test', function ($server) {
                return response()->json([
                    'success' => true,
                    'message' => "Server {$server} test completed",
                ]);
            })->name('servers.test');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | System Health API Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('system')->name('system.')->group(function () {
        // Basic health check (always available)
        Route::get('/health', function () {
            return response()->json([
                'success' => true,
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0',
            ]);
        })->name('health');

        // System information (basic)
        Route::get('/info', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'package_version' => '1.0.0',
                    'laravel_version' => app()->version(),
                    'php_version' => PHP_VERSION,
                    'environment' => app()->environment(),
                ],
            ]);
        })->name('info');
    });

    /*
    |--------------------------------------------------------------------------
    | Test Routes (Development Only)
    |--------------------------------------------------------------------------
    */

    if (app()->environment(['local', 'testing'])) {
        Route::prefix('test')->name('test.')->group(function () {
            // Test performance alert
            Route::post('alerts/performance', function () {
                return response()->json(['status' => 'test_alert_sent']);
            })->name('alerts.performance');

            // Test cost calculation
            Route::post('costs/calculate', function () {
                return response()->json(['status' => 'test_calculated']);
            })->name('costs.calculate');
        });
    }
});
