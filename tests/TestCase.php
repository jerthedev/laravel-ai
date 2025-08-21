<?php

namespace JTD\LaravelAI\Tests;

use JTD\LaravelAI\LaravelAIServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate', ['--database' => 'testing']);
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelAIServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'AI' => \JTD\LaravelAI\Facades\AI::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Load the full AI configuration from the config file
        $aiConfig = include __DIR__ . '/../config/ai.php';
        $app['config']->set('ai', $aiConfig);

        // Override specific settings for testing
        $app['config']->set('ai.default', 'mock');
        $app['config']->set('ai.cost_tracking.enabled', false);
        $app['config']->set('ai.model_sync.enabled', false);
        $app['config']->set('ai.cache.enabled', false);
        $app['config']->set('ai.logging.enabled', false);

        // Setup cache to use array driver
        $app['config']->set('cache.default', 'array');

        // Setup queue to use sync driver
        $app['config']->set('queue.default', 'sync');
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Get application timezone.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getApplicationTimezone($app): ?string
    {
        return 'UTC';
    }

    /**
     * Resolve application Console Kernel implementation.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function resolveApplicationConsoleKernel($app): void
    {
        $app->singleton('Illuminate\Contracts\Console\Kernel', 'Orchestra\Testbench\Console\Kernel');
    }

    /**
     * Create a mock user for testing.
     */
    protected function createMockUser(array $attributes = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes);
    }

    /**
     * Assert that a database table exists.
     */
    protected function assertDatabaseTableExists(string $table): void
    {
        $this->assertTrue(
            \Schema::hasTable($table),
            "Failed asserting that table '{$table}' exists."
        );
    }

    /**
     * Assert that a database table has specific columns.
     */
    protected function assertDatabaseTableHasColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            $this->assertTrue(
                \Schema::hasColumn($table, $column),
                "Failed asserting that table '{$table}' has column '{$column}'."
            );
        }
    }
}
