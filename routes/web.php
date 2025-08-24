<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Laravel AI Package Web Routes
|--------------------------------------------------------------------------
|
| Here are the web routes for the Laravel AI package. These routes provide
| web-based dashboard interfaces for performance monitoring, cost tracking,
| and administrative functions.
|
| Routes are automatically prefixed and can be disabled via configuration.
| Web routes are disabled by default and require authentication.
|
*/

Route::group([
    'prefix' => config('ai.routes.web.prefix', 'ai-dashboard'),
    'middleware' => config('ai.routes.web.middleware', ['web', 'auth']),
    'as' => config('ai.routes.web.name_prefix', 'ai.web.'),
], function () {
    /*
    |--------------------------------------------------------------------------
    | Dashboard Home
    |--------------------------------------------------------------------------
    */

    Route::get('/', function () {
        return view('laravel-ai::dashboard.index', [
            'title' => 'Laravel AI Dashboard',
            'version' => '1.0.0',
        ]);
    })->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Performance Dashboard Routes
    |--------------------------------------------------------------------------
    */

    if (config('ai.routes.performance.enabled', true)) {
        Route::prefix('performance')->name('performance.')->group(function () {
            Route::get('/', function () {
                return view('laravel-ai::dashboard.performance.index');
            })->name('index');

            Route::get('/alerts', function () {
                return view('laravel-ai::dashboard.performance.alerts');
            })->name('alerts');

            Route::get('/analytics', function () {
                return view('laravel-ai::dashboard.performance.analytics');
            })->name('analytics');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Cost Dashboard Routes
    |--------------------------------------------------------------------------
    */

    if (config('ai.routes.costs.enabled', true)) {
        Route::prefix('costs')->name('costs.')->group(function () {
            Route::get('/', function () {
                return view('laravel-ai::dashboard.costs.index');
            })->name('index');

            Route::get('/analytics', function () {
                return view('laravel-ai::dashboard.costs.analytics');
            })->name('analytics');

            Route::get('/budgets', function () {
                return view('laravel-ai::dashboard.costs.budgets');
            })->name('budgets');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | MCP Management Dashboard Routes
    |--------------------------------------------------------------------------
    */

    if (config('ai.routes.mcp.enabled', true)) {
        Route::prefix('mcp')->name('mcp.')->group(function () {
            Route::get('/', function () {
                return view('laravel-ai::dashboard.mcp.index');
            })->name('index');

            Route::get('/servers', function () {
                return view('laravel-ai::dashboard.mcp.servers');
            })->name('servers');

            Route::get('/tools', function () {
                return view('laravel-ai::dashboard.mcp.tools');
            })->name('tools');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Settings Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', function () {
            return view('laravel-ai::dashboard.settings.index');
        })->name('index');

        Route::get('/providers', function () {
            return view('laravel-ai::dashboard.settings.providers');
        })->name('providers');

        Route::get('/alerts', function () {
            return view('laravel-ai::dashboard.settings.alerts');
        })->name('alerts');
    });
});
