<?php

namespace JTD\LaravelAI;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use JTD\LaravelAI\Contracts\ConversationBuilderInterface;
use JTD\LaravelAI\Services\AIManager;
use JTD\LaravelAI\Services\BudgetService;
use JTD\LaravelAI\Services\ConfigurationValidator;
use JTD\LaravelAI\Services\ConversationBuilder;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Services\EventPerformanceTracker;
use JTD\LaravelAI\Services\MiddlewareManager;
use JTD\LaravelAI\Services\PerformanceAlertManager;
use JTD\LaravelAI\Services\PerformanceOptimizationEngine;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Services\QueuePerformanceMonitor;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\MCPToolDiscoveryService;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\UnifiedToolRegistry;
use JTD\LaravelAI\Services\UnifiedToolExecutor;
use JTD\LaravelAI\Contracts\MCPManagerInterface;

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
        MCPManagerInterface::class => MCPManager::class,
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
        'laravel-ai.middleware' => MiddlewareManager::class,
        'laravel-ai.budget' => BudgetService::class,
        'laravel-ai.pricing' => PricingService::class,
        'laravel-ai.mcp.manager' => MCPManager::class,
        'laravel-ai.mcp.discovery' => MCPToolDiscoveryService::class,
        'laravel-ai.mcp.config' => MCPConfigurationService::class,
        'laravel-ai.tools.registry' => UnifiedToolRegistry::class,
        'laravel-ai.tools.executor' => UnifiedToolExecutor::class,
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

        // Register middleware manager
        $this->app->singleton('laravel-ai.middleware', function ($app) {
            return new MiddlewareManager;
        });

        // Register budget service
        $this->app->singleton('laravel-ai.budget', function ($app) {
            return new BudgetService;
        });

        // Register pricing service
        $this->app->singleton('laravel-ai.pricing', function ($app) {
            return new PricingService;
        });

        // Register conversation builder
        $this->app->bind(ConversationBuilderInterface::class, function ($app) {
            return new ConversationBuilder($app['laravel-ai']);
        });

        // Register performance monitoring services
        $this->app->singleton(EventPerformanceTracker::class);
        $this->app->singleton(QueuePerformanceMonitor::class);
        $this->app->singleton(PerformanceAlertManager::class);
        $this->app->singleton(PerformanceOptimizationEngine::class);

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

        // Register routes
        $this->registerRoutes();

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-ai');

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
        // Only register listeners if events are enabled
        if (! config('ai.events.enabled', true)) {
            return;
        }

        // Cost tracking listeners
        if (config('ai.events.listeners.cost_tracking.enabled', true)) {
            Event::listen(\JTD\LaravelAI\Events\ResponseGenerated::class, \JTD\LaravelAI\Listeners\CostTrackingListener::class);
            Event::listen(\JTD\LaravelAI\Events\CostCalculated::class, \JTD\LaravelAI\Listeners\CostTrackingListener::class . '@handleCostCalculated');
        }

        // Analytics listeners
        if (config('ai.events.listeners.analytics.enabled', true)) {
            Event::listen(\JTD\LaravelAI\Events\ResponseGenerated::class, \JTD\LaravelAI\Listeners\AnalyticsListener::class);
            Event::listen(\JTD\LaravelAI\Events\CostCalculated::class, \JTD\LaravelAI\Listeners\AnalyticsListener::class . '@handleCostCalculated');
        }

        // Notification listeners
        if (config('ai.events.listeners.notifications.enabled', true)) {
            Event::listen(\JTD\LaravelAI\Events\BudgetThresholdReached::class, \JTD\LaravelAI\Listeners\NotificationListener::class);
            Event::listen(\JTD\LaravelAI\Events\ResponseGenerated::class, \JTD\LaravelAI\Listeners\NotificationListener::class . '@handleResponseGenerated');
        }

        // Performance monitoring listeners
        if (config('ai.performance.alerts.enabled', true)) {
            Event::listen(\JTD\LaravelAI\Events\PerformanceThresholdExceeded::class, \JTD\LaravelAI\Listeners\PerformanceAlertListener::class);
        }

        // Performance tracking listeners
        if (config('ai.performance.tracking.enabled', true)) {
            Event::subscribe(\JTD\LaravelAI\Listeners\PerformanceTrackingListener::class);
        }

        // Conversation events (existing)
        Event::listen(\JTD\LaravelAI\Events\ConversationCreated::class, function ($event) {
            // Log conversation creation for analytics
            logger()->info('AI Conversation created', [
                'conversation_id' => $event->conversation->id,
                'user_id' => $event->conversation->user_id,
            ]);
        });

        Event::listen(\JTD\LaravelAI\Events\MessageAdded::class, function ($event) {
            // Log message addition for analytics
            logger()->debug('AI Message added', [
                'message_id' => $event->message->id,
                'conversation_id' => $event->message->conversation_id,
                'role' => $event->message->role,
            ]);
        });
    }

    /**
     * Register artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Core sync commands
                \JTD\LaravelAI\Console\Commands\SyncModelsCommand::class,
                \JTD\LaravelAI\Console\Commands\SyncPricingCommand::class,
                \JTD\LaravelAI\Console\Commands\SetupE2ECommand::class,

                // MCP commands
                \JTD\LaravelAI\Console\Commands\MCPSetupCommand::class,
                \JTD\LaravelAI\Console\Commands\MCPDiscoverCommand::class,
                \JTD\LaravelAI\Console\Commands\MCPListCommand::class,
                \JTD\LaravelAI\Console\Commands\MCPRemoveCommand::class,
                \JTD\LaravelAI\Console\Commands\MCPTestCommand::class,

                // Performance and migration commands
                \JTD\LaravelAI\Console\Commands\RunPerformanceTestsCommand::class,
                \JTD\LaravelAI\Console\Commands\MigratePricingSystemCommand::class,
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
     * Register package routes.
     */
    protected function registerRoutes(): void
    {
        // Only load routes if globally enabled
        if (!config('ai.routes.enabled', true)) {
            return;
        }

        // Load API routes if enabled
        if (config('ai.routes.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

        // Load web routes if enabled
        if (config('ai.routes.web.enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
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
            'laravel-ai.middleware',
            'laravel-ai.budget',
            'laravel-ai.pricing',
            ConversationBuilderInterface::class,
        ];
    }
}
