<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Services\IntelligentPricingDiscovery;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Services\PricingValidator;

/**
 * Enhanced command to synchronize AI models and pricing from all configured providers.
 *
 * This command automatically discovers all configured AI providers with valid
 * credentials and synchronizes their models and pricing data to the local cache/database.
 * Includes support for AI-powered pricing discovery and comprehensive validation.
 */
class SyncModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:sync-models
                            {--provider= : Sync models from specific provider only}
                            {--force : Force refresh even if recently synced}
                            {--dry-run : Show what would be synced without actually syncing}
                            {--timeout=30 : API timeout in seconds}
                            {--skip-pricing : Skip pricing synchronization}
                            {--enable-ai-discovery : Enable AI-powered pricing discovery}
                            {--show-pricing : Display pricing information during sync}
                            {--validate-only : Only validate pricing without storing}
                            {--force-pricing : Force pricing update even if current}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize AI models and pricing from all configured providers';

    /**
     * The driver manager instance.
     */
    protected DriverManager $driverManager;

    /**
     * The pricing service instance.
     */
    protected PricingService $pricingService;

    /**
     * The pricing validator instance.
     */
    protected PricingValidator $pricingValidator;

    /**
     * The intelligent pricing discovery instance.
     */
    protected IntelligentPricingDiscovery $intelligentPricingDiscovery;

    /**
     * Create a new command instance.
     */
    public function __construct(
        DriverManager $driverManager,
        PricingService $pricingService,
        PricingValidator $pricingValidator,
        IntelligentPricingDiscovery $intelligentPricingDiscovery
    ) {
        parent::__construct();
        $this->driverManager = $driverManager;
        $this->pricingService = $pricingService;
        $this->pricingValidator = $pricingValidator;
        $this->intelligentPricingDiscovery = $intelligentPricingDiscovery;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $provider = $this->option('provider');
        $force = $this->option('force');
        $verbose = $this->getOutput()->isVerbose();
        $dryRun = $this->option('dry-run');
        $timeout = (int) $this->option('timeout');

        // Pricing options
        $skipPricing = $this->option('skip-pricing');
        $enableAiDiscovery = $this->option('enable-ai-discovery');
        $showPricing = $this->option('show-pricing');
        $validateOnly = $this->option('validate-only');
        $forcePricing = $this->option('force-pricing');

        // Validate options
        if ($validationError = $this->validateOptions($skipPricing, $enableAiDiscovery, $validateOnly)) {
            $this->error($validationError);

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
            $this->newLine();
        }

        if ($validateOnly) {
            $this->warn('VALIDATION ONLY - Pricing will be validated but not stored');
            $this->newLine();
        }

        $this->info('Syncing models and pricing from AI providers...');
        if (! $skipPricing) {
            $this->line('Pricing synchronization: <info>enabled</info>');
            if ($enableAiDiscovery) {
                $this->line('AI-powered discovery: <info>enabled</info>');
            }
            if ($forcePricing) {
                $this->line('Force pricing update: <info>enabled</info>');
            }
        } else {
            $this->line('Pricing synchronization: <comment>skipped</comment>');
        }
        $this->newLine();

        // Get providers to sync
        $providers = $provider ? [$provider] : $this->getAvailableProviders();

        if (empty($providers)) {
            $this->error('No providers available for synchronization.');

            return self::FAILURE;
        }

        $totalSynced = 0;
        $totalProviders = 0;
        $results = [];

        foreach ($providers as $providerName) {
            try {
                $syncOptions = [
                    'force' => $force,
                    'verbose' => $verbose,
                    'dry_run' => $dryRun,
                    'timeout' => $timeout,
                    'skip_pricing' => $skipPricing,
                    'enable_ai_discovery' => $enableAiDiscovery,
                    'show_pricing' => $showPricing,
                    'validate_only' => $validateOnly,
                    'force_pricing' => $forcePricing,
                ];

                $result = $this->syncProvider($providerName, $syncOptions);
                $results[$providerName] = $result;

                if ($result['status'] === 'success') {
                    $totalSynced += $result['models_synced'] ?? 0;
                    $totalProviders++;
                }
            } catch (\Exception $e) {
                $this->error("Failed to sync {$providerName}: " . $e->getMessage());
                $results[$providerName] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->newLine();
        $this->displaySummary($results, $totalSynced, $totalProviders, $dryRun);

        return $totalProviders > 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Get available providers for synchronization.
     */
    protected function getAvailableProviders(): array
    {
        $providers = [];
        $availableProviders = $this->driverManager->getAvailableProviders();

        foreach ($availableProviders as $providerName) {
            try {
                $driver = $this->driverManager->driver($providerName);

                // Skip mock provider in production
                if ($providerName === 'mock' && app()->environment('production')) {
                    continue;
                }

                // Check if provider has valid credentials
                if ($driver->hasValidCredentials()) {
                    $providers[] = $providerName;
                }
            } catch (\Exception $e) {
                // Skip providers that can't be instantiated
                if ($this->getOutput()->isVerbose()) {
                    $this->warn("Skipping {$providerName}: " . $e->getMessage());
                }
            }
        }

        return $providers;
    }

    /**
     * Sync models and pricing for a specific provider.
     */
    protected function syncProvider(string $providerName, array $options): array
    {
        $this->line("<info>{$providerName}:</info>");

        try {
            $driver = $this->driverManager->driver($providerName);

            if ($options['dry_run']) {
                return $this->performDryRun($driver, $providerName, $options);
            }

            // Set timeout if supported
            if (method_exists($driver, 'setTimeout')) {
                $driver->setTimeout($options['timeout']);
            }

            // Sync models first
            $result = $driver->syncModels($options['force']);

            // Sync pricing if not skipped
            if (! $options['skip_pricing']) {
                $pricingResult = $this->syncProviderPricing($providerName, $driver, $options);
                $result['pricing'] = $pricingResult;
            }

            $this->displayProviderResult($providerName, $result, $options);

            return $result;
        } catch (\Exception $e) {
            $this->error('  ❌ Synchronization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform a dry run for a provider.
     */
    protected function performDryRun($driver, string $providerName, array $options): array
    {
        try {
            $syncableModels = $driver->getSyncableModels();
            $lastSync = $driver->getLastSyncTime();

            $this->line('  Would sync: ' . count($syncableModels) . ' models');

            if ($lastSync) {
                $this->line('  Last synced: ' . $lastSync->diffForHumans());
            } else {
                $this->line('  Last synced: Never');
            }

            // Show pricing dry run if not skipped
            if (! $options['skip_pricing']) {
                $pricingDryRun = $this->performPricingDryRun($providerName, $syncableModels, $options);
                $this->line('  Would sync pricing for: ' . $pricingDryRun['models_with_pricing'] . ' models');
            }

            if ($options['verbose'] && ! empty($syncableModels)) {
                $this->line('  Models:');
                foreach (array_slice($syncableModels, 0, 5) as $model) {
                    $this->line('    - ' . $model['id']);
                }
                if (count($syncableModels) > 5) {
                    $this->line('    ... and ' . (count($syncableModels) - 5) . ' more');
                }
            }

            return [
                'status' => 'dry_run',
                'models_synced' => count($syncableModels),
                'would_sync' => true,
            ];
        } catch (\Exception $e) {
            $this->error('  ❌ Failed to get syncable models: ' . $e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync pricing for a specific provider.
     */
    protected function syncProviderPricing(string $providerName, $driver, array $options): array
    {
        try {
            // Get pricing class for this provider
            $pricingClass = $this->getDriverPricingClass($providerName);

            if (! $pricingClass) {
                return [
                    'status' => 'skipped',
                    'reason' => 'No pricing class available',
                ];
            }

            // Get all model pricing from driver
            $allPricing = $pricingClass->getAllModelPricing();

            if (empty($allPricing)) {
                return [
                    'status' => 'skipped',
                    'reason' => 'No pricing data available',
                ];
            }

            // Validate pricing data
            $validationResult = $this->validatePricingData($allPricing, $options);

            if (! $validationResult['valid'] && $this->getConfigValue('validation.strict_mode', false)) {
                return [
                    'status' => 'error',
                    'error' => 'Pricing validation failed in strict mode',
                    'validation_errors' => $validationResult['errors'],
                ];
            }

            if (! empty($validationResult['errors'])) {
                $this->warn('  ⚠ Pricing validation warnings:');
                foreach (array_slice($validationResult['errors'], 0, 3) as $error) {
                    $this->line('    - ' . $error);
                }
                if (count($validationResult['errors']) > 3) {
                    $this->line('    ... and ' . (count($validationResult['errors']) - 3) . ' more warnings');
                }
            }

            if (! empty($validationResult['warnings'])) {
                $this->line('  ℹ Pricing consistency warnings:');
                foreach (array_slice($validationResult['warnings'], 0, 2) as $warning) {
                    $this->line('    - ' . $warning);
                }
                if (count($validationResult['warnings']) > 2) {
                    $this->line('    ... and ' . (count($validationResult['warnings']) - 2) . ' more');
                }
            }

            if ($options['validate_only']) {
                return [
                    'status' => 'validated',
                    'models_validated' => count($allPricing),
                    'validation_errors' => count($validationResult['errors']),
                    'validation_warnings' => count($validationResult['warnings']),
                ];
            }

            // Store pricing to database
            $storedCount = 0;
            $discoveredCount = 0;

            foreach ($allPricing as $model => $pricing) {
                $finalPricing = $pricing;

                // Try AI discovery if enabled and pricing seems incomplete
                if ($options['enable_ai_discovery'] && $this->shouldTryAiDiscovery($pricing)) {
                    $discoveryResult = $this->tryAiDiscovery($providerName, $model, $options);
                    if ($discoveryResult['status'] === 'success') {
                        $finalPricing = $discoveryResult['pricing'];
                        $discoveredCount++;
                        $this->line("    ✓ AI discovery successful for {$model}");
                    } else {
                        $this->line("    ⚠ AI discovery failed for {$model}: " . $discoveryResult['message']);
                    }
                }

                if ($this->pricingService->storePricingToDatabase($providerName, $model, $finalPricing)) {
                    $storedCount++;

                    if ($options['show_pricing']) {
                        $this->displayModelPricing($model, $finalPricing);
                    }
                }
            }

            return [
                'status' => 'success',
                'models_synced' => $storedCount,
                'models_discovered' => $discoveredCount,
                'validation_errors' => count($validationResult['errors']),
                'validation_warnings' => count($validationResult['warnings']),
            ];
        } catch (\Exception $e) {
            $this->error('  ❌ Pricing sync failed: ' . $e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate pricing data with comprehensive checks.
     */
    private function validatePricingData(array $allPricing, array $options): array
    {
        // Basic validation using PricingValidator
        $errors = $this->pricingValidator->validatePricingArray($allPricing);
        $warnings = $this->pricingValidator->validatePricingConsistency($allPricing);

        // Additional validation based on configuration
        $config = config('ai.model_sync.validation', []);

        if ($config['require_effective_date'] ?? true) {
            foreach ($allPricing as $model => $pricing) {
                if (! isset($pricing['effective_date'])) {
                    $errors[] = "Model '{$model}' missing required effective_date";
                }
            }
        }

        if ($config['max_cost_per_token'] ?? false) {
            $maxCost = $config['max_cost_per_token'];
            foreach ($allPricing as $model => $pricing) {
                if (isset($pricing['input']) && $pricing['input'] > $maxCost) {
                    $warnings[] = "Model '{$model}' input cost ({$pricing['input']}) exceeds maximum ({$maxCost})";
                }
                if (isset($pricing['output']) && $pricing['output'] > $maxCost) {
                    $warnings[] = "Model '{$model}' output cost ({$pricing['output']}) exceeds maximum ({$maxCost})";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get driver pricing class instance.
     */
    private function getDriverPricingClass(string $provider): ?object
    {
        $pricingClass = '\\JTD\\LaravelAI\\Drivers\\' . ucfirst($provider) . '\\Support\\ModelPricing';

        if (class_exists($pricingClass)) {
            return new $pricingClass;
        }

        return null;
    }

    /**
     * Perform pricing dry run.
     */
    private function performPricingDryRun(string $providerName, array $models, array $options): array
    {
        $pricingClass = $this->getDriverPricingClass($providerName);

        if (! $pricingClass) {
            return ['models_with_pricing' => 0];
        }

        $allPricing = $pricingClass->getAllModelPricing();
        $modelsWithPricing = 0;

        foreach ($models as $model) {
            if (isset($allPricing[$model['id']])) {
                $modelsWithPricing++;
            }
        }

        return ['models_with_pricing' => $modelsWithPricing];
    }

    /**
     * Display pricing information for a model.
     */
    private function displayModelPricing(string $model, array $pricing): void
    {
        $unit = $pricing['unit']?->value ?? 'unknown';
        $currency = $pricing['currency'] ?? 'USD';
        $source = $pricing['source'] ?? 'unknown';

        if (isset($pricing['input']) && isset($pricing['output'])) {
            $this->line("    {$model}: {$pricing['input']}/{$pricing['output']} {$currency} per {$unit} ({$source})");
        } elseif (isset($pricing['cost'])) {
            $this->line("    {$model}: {$pricing['cost']} {$currency} per {$unit} ({$source})");
        }
    }

    /**
     * Check if AI discovery should be attempted for pricing.
     */
    private function shouldTryAiDiscovery(array $pricing): bool
    {
        // Try AI discovery if pricing seems incomplete or outdated
        if (empty($pricing)) {
            return true;
        }

        // Check if pricing has both input and output for token-based models
        if (isset($pricing['unit']) &&
            in_array($pricing['unit'], [\JTD\LaravelAI\Enums\PricingUnit::PER_1K_TOKENS, \JTD\LaravelAI\Enums\PricingUnit::PER_1M_TOKENS])) {
            if (! isset($pricing['input']) || ! isset($pricing['output'])) {
                return true;
            }
        }

        // Check if pricing is from fallback source
        if (isset($pricing['source']) && $pricing['source'] === 'fallback') {
            return true;
        }

        // Check if pricing is old (more than 6 months)
        if (isset($pricing['effective_date'])) {
            $effectiveDate = \Carbon\Carbon::parse($pricing['effective_date']);
            if ($effectiveDate->diffInMonths(now()) > 6) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attempt AI discovery for a model.
     */
    private function tryAiDiscovery(string $provider, string $model, array $options): array
    {
        try {
            $discoveryOptions = [
                'confirmed' => true, // Auto-confirm for sync command
                'force' => $options['force_pricing'] ?? false,
            ];

            return $this->intelligentPricingDiscovery->discoverPricing($provider, $model, $discoveryOptions);
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Discovery failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Populate database from all static pricing files.
     */
    public function populateDatabaseFromStaticPricing(?array $providers = null): array
    {
        $providers = $providers ?? $this->getAvailableProviders();
        $results = [];

        $this->info('Populating database from static pricing files...');
        $this->newLine();

        foreach ($providers as $providerName) {
            try {
                $this->line("<info>{$providerName}:</info>");

                $pricingClass = $this->getDriverPricingClass($providerName);
                if (! $pricingClass) {
                    $this->line('  ⏭ No pricing class available');
                    $results[$providerName] = ['status' => 'skipped', 'reason' => 'No pricing class'];

                    continue;
                }

                $allPricing = $pricingClass->getAllModelPricing();
                if (empty($allPricing)) {
                    $this->line('  ⏭ No pricing data available');
                    $results[$providerName] = ['status' => 'skipped', 'reason' => 'No pricing data'];

                    continue;
                }

                $storedCount = 0;
                $errorCount = 0;

                foreach ($allPricing as $model => $pricing) {
                    if ($this->pricingService->storePricingToDatabase($providerName, $model, $pricing)) {
                        $storedCount++;
                    } else {
                        $errorCount++;
                    }
                }

                $this->line("  ✓ Stored pricing for {$storedCount} models");
                if ($errorCount > 0) {
                    $this->warn("  ⚠ Failed to store {$errorCount} models");
                }

                $results[$providerName] = [
                    'status' => 'success',
                    'models_stored' => $storedCount,
                    'errors' => $errorCount,
                ];
            } catch (\Exception $e) {
                $this->error('  ❌ Failed: ' . $e->getMessage());
                $results[$providerName] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Display the result for a provider.
     */
    protected function displayProviderResult(string $providerName, array $result, array $options): void
    {
        switch ($result['status']) {
            case 'success':
                $this->line('  ✓ Found ' . $result['models_synced'] . ' models');

                // Display pricing results if available
                if (isset($result['pricing'])) {
                    $pricingResult = $result['pricing'];
                    switch ($pricingResult['status']) {
                        case 'success':
                            $this->line('  ✓ Synced pricing for ' . $pricingResult['models_synced'] . ' models');
                            if (isset($pricingResult['models_discovered']) && $pricingResult['models_discovered'] > 0) {
                                $this->line('  🔍 AI discovery used for ' . $pricingResult['models_discovered'] . ' models');
                            }
                            if ($pricingResult['validation_errors'] > 0) {
                                $this->line('  ⚠ ' . $pricingResult['validation_errors'] . ' validation warnings');
                            }
                            break;
                        case 'validated':
                            $this->line('  ✓ Validated pricing for ' . $pricingResult['models_validated'] . ' models');
                            if ($pricingResult['validation_errors'] > 0) {
                                $this->line('  ⚠ ' . $pricingResult['validation_errors'] . ' validation errors');
                            }
                            break;
                        case 'skipped':
                            $this->line('  ⏭ Pricing skipped (' . $pricingResult['reason'] . ')');
                            break;
                        case 'error':
                            $this->error('  ❌ Pricing sync failed: ' . $pricingResult['error']);
                            break;
                    }
                }

                if (isset($result['statistics'])) {
                    $stats = $result['statistics'];
                    if ($options['verbose']) {
                        $this->line('  ✓ Statistics updated');
                        $this->line('    - Total: ' . $stats['total_models']);
                        if (isset($stats['gpt_4_models'])) {
                            $this->line('    - GPT-4: ' . $stats['gpt_4_models']);
                        }
                        if (isset($stats['function_calling_models'])) {
                            $this->line('    - Function calling: ' . $stats['function_calling_models']);
                        }
                    }
                }
                break;

            case 'skipped':
                $this->line('  ⏭ Skipped (' . $result['reason'] . ')');
                if ($options['verbose'] && isset($result['last_sync'])) {
                    $this->line('    Last sync: ' . $result['last_sync']->diffForHumans());
                }
                break;

            default:
                $this->error('  ❌ Unknown status: ' . $result['status']);
        }
    }

    /**
     * Display the final summary with pricing information.
     */
    protected function displaySummary(array $results, int $totalSynced, int $totalProviders, bool $dryRun): void
    {
        if ($dryRun) {
            $this->info('Dry run completed!');
        } else {
            $this->info('Sync completed successfully!');
        }

        $this->line("Total: {$totalSynced} models synced across {$totalProviders} providers");

        // Calculate pricing statistics
        $pricingStats = $this->calculatePricingStats($results);
        if ($pricingStats['total_pricing_synced'] > 0) {
            $this->line("Pricing: {$pricingStats['total_pricing_synced']} models with pricing data");
            if ($pricingStats['validation_errors'] > 0) {
                $this->line("Validation: {$pricingStats['validation_errors']} errors, {$pricingStats['validation_warnings']} warnings");
            }
        }

        // Show provider breakdown if verbose
        if ($this->getOutput()->isVerbose()) {
            $this->newLine();
            $this->line('<comment>Provider Breakdown:</comment>');
            foreach ($results as $provider => $result) {
                $status = $result['status'] ?? 'unknown';
                $models = $result['models_synced'] ?? 0;
                $this->line("  {$provider}: {$models} models ({$status})");

                if (isset($result['pricing'])) {
                    $pricingResult = $result['pricing'];
                    $pricingModels = $pricingResult['models_synced'] ?? $pricingResult['models_validated'] ?? 0;
                    $pricingStatus = $pricingResult['status'] ?? 'unknown';
                    $this->line("    Pricing: {$pricingModels} models ({$pricingStatus})");
                }
            }
        }

        // Show any errors
        $errors = array_filter($results, fn ($result) => $result['status'] === 'error');
        if (! empty($errors)) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($errors as $provider => $result) {
                $this->error("- {$provider}: " . $result['error']);
            }
        }

        // Show pricing errors separately
        $pricingErrors = [];
        foreach ($results as $provider => $result) {
            if (isset($result['pricing']) && $result['pricing']['status'] === 'error') {
                $pricingErrors[$provider] = $result['pricing']['error'];
            }
        }

        if (! empty($pricingErrors)) {
            $this->newLine();
            $this->error('Pricing sync errors:');
            foreach ($pricingErrors as $provider => $error) {
                $this->error("- {$provider}: {$error}");
            }
        }
    }

    /**
     * Calculate pricing statistics from results.
     */
    private function calculatePricingStats(array $results): array
    {
        $stats = [
            'total_pricing_synced' => 0,
            'validation_errors' => 0,
            'validation_warnings' => 0,
        ];

        foreach ($results as $result) {
            if (isset($result['pricing'])) {
                $pricing = $result['pricing'];
                $stats['total_pricing_synced'] += $pricing['models_synced'] ?? $pricing['models_validated'] ?? 0;
                $stats['validation_errors'] += $pricing['validation_errors'] ?? 0;
                $stats['validation_warnings'] += $pricing['validation_warnings'] ?? 0;
            }
        }

        return $stats;
    }

    /**
     * Validate command options.
     */
    private function validateOptions(bool $skipPricing, bool $enableAiDiscovery, bool $validateOnly): ?string
    {
        // AI discovery requires pricing sync to be enabled
        if ($enableAiDiscovery && $skipPricing) {
            return 'Cannot enable AI discovery while skipping pricing sync. Use --enable-ai-discovery without --skip-pricing.';
        }

        // Validate AI discovery is configured if enabled
        if ($enableAiDiscovery) {
            $aiDiscoveryConfig = config('ai.model_sync.ai_discovery', []);
            if (! ($aiDiscoveryConfig['enabled'] ?? false)) {
                return 'AI discovery is not enabled in configuration. Set AI_PRICING_DISCOVERY_ENABLED=true or remove --enable-ai-discovery flag.';
            }
        }

        // Validate only makes sense with pricing sync
        if ($validateOnly && $skipPricing) {
            return 'Cannot validate pricing while skipping pricing sync. Use --validate-only without --skip-pricing.';
        }

        return null;
    }

    /**
     * Get configuration value with fallback.
     */
    private function getConfigValue(string $key, $default = null)
    {
        return config("ai.model_sync.{$key}", $default);
    }

    /**
     * Display command help information.
     */
    public function displayHelp(): void
    {
        $this->info('Enhanced Model Sync Command');
        $this->newLine();

        $this->line('This command synchronizes AI models and pricing data from configured providers.');
        $this->line('It supports database-first pricing with intelligent fallbacks and validation.');
        $this->newLine();

        $this->line('<comment>Pricing Options:</comment>');
        $this->line('  --skip-pricing         Skip pricing synchronization entirely');
        $this->line('  --enable-ai-discovery  Enable AI-powered pricing discovery (requires configuration)');
        $this->line('  --show-pricing         Display pricing information during sync');
        $this->line('  --validate-only        Validate pricing without storing to database');
        $this->line('  --force-pricing        Force pricing update even if current');
        $this->newLine();

        $this->line('<comment>Examples:</comment>');
        $this->line('  php artisan ai:sync-models --provider=openai --show-pricing');
        $this->line('  php artisan ai:sync-models --dry-run --skip-pricing');
        $this->line('  php artisan ai:sync-models --validate-only --verbose');
        $this->line('  php artisan ai:sync-models --enable-ai-discovery --force-pricing');
    }
}
