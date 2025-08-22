<?php

namespace JTD\LaravelAI;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use JTD\LaravelAI\Contracts\ConversationBuilderInterface;
use JTD\LaravelAI\Services\AIManager;
use JTD\LaravelAI\Services\ConfigurationValidator;
use JTD\LaravelAI\Services\ConversationBuilder;
use JTD\LaravelAI\Services\DriverManager;

/**
 * Laravel AI Service Provider
 *
 * Registers all services, bindings, and configurations for the JTD Laravel AI package.
 * Provides auto-discovery support and publishes configuration and migration files.
 */
class LaravelAIServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array
     */
    public $bindings = [
        ConversationBuilderInterface::class => ConversationBuilder::class,
    ];

    /**
     * All of the container singletons that should be registered.
     *
     * @var array
     */
    public $singletons = [
        'laravel-ai' => AIManager::class,
        'laravel-ai.driver' => DriverManager::class,
        'laravel-ai.config.validator' => ConfigurationValidator::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration from package
        $this->mergeConfigFrom(__DIR__ . '/../config/ai.php', 'ai');

        // Register the main AI manager
        $this->app->singleton('laravel-ai', function ($app) {
            return new AIManager($app);
        });

        // Register the driver manager
        $this->app->singleton('laravel-ai.driver', function ($app) {
            return new DriverManager($app);
        });

        // Register configuration validator
        $this->app->singleton('laravel-ai.config.validator', function ($app) {
            return new ConfigurationValidator;
        });

        // Register conversation builder
        $this->app->bind(ConversationBuilderInterface::class, function ($app) {
            return new ConversationBuilder($app['laravel-ai']);
        });

        // Register facade alias
        $this->app->alias('laravel-ai', 'AI');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Validate configuration on boot
        if ($this->app->environment('production')) {
            $this->validateConfiguration();
        }

        // Register publishing groups
        $this->registerPublishing();

        // Load package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register event listeners
        $this->registerEventListeners();

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }

        // Boot the AI manager to register default providers
        $this->bootAIManager();
    }

    /**
     * Register publishing groups for configuration and migrations.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration file
            $this->publishes([
                __DIR__ . '/../config/ai.php' => config_path('ai.php'),
            ], 'laravel-ai-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'laravel-ai-migrations');

            // Publish everything
            $this->publishes([
                __DIR__ . '/../config/ai.php' => config_path('ai.php'),
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'laravel-ai');
        }
    }

    /**
     * Register event listeners for AI operations.
     */
    protected function registerEventListeners(): void
    {
        // Core system events
        Event::listen(\JTD\LaravelAI\Events\ResponseGenerated::class, \JTD\LaravelAI\Listeners\CostTrackingListener::class . '@handleResponseGenerated');
        Event::listen(\JTD\LaravelAI\Events\CostCalculated::class, \JTD\LaravelAI\Listeners\CostTrackingListener::class);

        // Additional event listeners will be added as we implement more features
    }

    /**
     * Register artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \JTD\LaravelAI\Console\Commands\SyncOpenAIModelsCommand::class,
                \JTD\LaravelAI\Console\Commands\SetupE2ECommand::class,
            ]);
        }
    }

    /**
     * Boot the AI manager and register default providers.
     */
    protected function bootAIManager(): void
    {
        $manager = $this->app->make('laravel-ai');

        // Register built-in providers
        // This will be expanded when we implement providers
    }

    /**
     * Validate the AI configuration.
     */
    protected function validateConfiguration(): void
    {
        try {
            $validator = $this->app->make('laravel-ai.config.validator');
            $config = config('ai', []);
            $validator->validate($config);
        } catch (\Exception $e) {
            // Log configuration validation errors in production
            logger()->error('AI configuration validation failed', [
                'error' => $e->getMessage(),
                'config' => config('ai'),
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'laravel-ai',
            'laravel-ai.driver',
            'laravel-ai.config.validator',
            ConversationBuilderInterface::class,
        ];
    }
}
