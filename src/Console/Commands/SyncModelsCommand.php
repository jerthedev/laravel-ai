<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use JTD\LaravelAI\Services\DriverManager;

/**
 * Generic command to synchronize AI models from all configured providers.
 *
 * This command automatically discovers all configured AI providers with valid
 * credentials and synchronizes their models to the local cache/database.
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
                            {--timeout=30 : API timeout in seconds}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize AI models from all configured providers';

    /**
     * The driver manager instance.
     */
    protected DriverManager $driverManager;

    /**
     * Create a new command instance.
     */
    public function __construct(DriverManager $driverManager)
    {
        parent::__construct();
        $this->driverManager = $driverManager;
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

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
            $this->newLine();
        }

        $this->info('Syncing models from AI providers...');
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
                $result = $this->syncProvider($providerName, $force, $verbose, $dryRun, $timeout);
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
     * Sync models for a specific provider.
     */
    protected function syncProvider(string $providerName, bool $force, bool $verbose, bool $dryRun, int $timeout): array
    {
        $this->line("<info>{$providerName}:</info>");

        try {
            $driver = $this->driverManager->driver($providerName);

            if ($dryRun) {
                return $this->performDryRun($driver, $providerName, $verbose);
            }

            // Set timeout if supported
            if (method_exists($driver, 'setTimeout')) {
                $driver->setTimeout($timeout);
            }

            $result = $driver->syncModels($force);

            $this->displayProviderResult($providerName, $result, $verbose);

            return $result;
        } catch (\Exception $e) {
            $this->error('  ❌ Synchronization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform a dry run for a provider.
     */
    protected function performDryRun($driver, string $providerName, bool $verbose): array
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

            if ($verbose && ! empty($syncableModels)) {
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
     * Display the result for a provider.
     */
    protected function displayProviderResult(string $providerName, array $result, bool $verbose): void
    {
        switch ($result['status']) {
            case 'success':
                $this->line('  ✓ Found ' . $result['models_synced'] . ' models');
                if (isset($result['statistics'])) {
                    $stats = $result['statistics'];
                    if ($verbose) {
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
                if ($verbose && isset($result['last_sync'])) {
                    $this->line('    Last sync: ' . $result['last_sync']->diffForHumans());
                }
                break;

            default:
                $this->error('  ❌ Unknown status: ' . $result['status']);
        }
    }

    /**
     * Display the final summary.
     */
    protected function displaySummary(array $results, int $totalSynced, int $totalProviders, bool $dryRun): void
    {
        if ($dryRun) {
            $this->info('Dry run completed!');
        } else {
            $this->info('Sync completed successfully!');
        }

        $this->line("Total: {$totalSynced} models synced across {$totalProviders} providers");

        // Show any errors
        $errors = array_filter($results, fn ($result) => $result['status'] === 'error');
        if (! empty($errors)) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($errors as $provider => $result) {
                $this->error("- {$provider}: " . $result['error']);
            }
        }
    }
}
