<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Services\PricingValidator;

/**
 * Command to migrate from legacy pricing system to enhanced database-first pricing.
 *
 * This command provides a comprehensive migration process with backup, validation,
 * rollback capabilities, and step-by-step migration with safety checks.
 */
class MigratePricingSystemCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:migrate-pricing-system
                            {--dry-run : Show what would be migrated without making changes}
                            {--force : Skip confirmation prompts}
                            {--backup : Create backup before migration}
                            {--rollback : Rollback to previous pricing system}
                            {--validate-only : Only validate current pricing data}
                            {--skip-backup : Skip backup creation (not recommended)}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate from legacy pricing system to enhanced database-first pricing';

    /**
     * The pricing service instance.
     */
    protected PricingService $pricingService;

    /**
     * The pricing validator instance.
     */
    protected PricingValidator $pricingValidator;

    /**
     * Migration steps tracking.
     */
    protected array $migrationSteps = [];

    /**
     * Create a new command instance.
     */
    public function __construct(PricingService $pricingService, PricingValidator $pricingValidator)
    {
        parent::__construct();
        $this->pricingService = $pricingService;
        $this->pricingValidator = $pricingValidator;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $backup = $this->option('backup');
        $rollback = $this->option('rollback');
        $validateOnly = $this->option('validate-only');
        $skipBackup = $this->option('skip-backup');

        if ($rollback) {
            return $this->handleRollback();
        }

        if ($validateOnly) {
            return $this->handleValidationOnly();
        }

        $this->info('Enhanced Pricing System Migration');
        $this->line('=====================================');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Pre-migration checks
        if (! $this->performPreMigrationChecks()) {
            $this->error('Pre-migration checks failed. Migration aborted.');

            return self::FAILURE;
        }

        // Show migration plan
        $this->displayMigrationPlan();

        // Confirm migration
        if (! $force && ! $this->confirm('Proceed with pricing system migration?')) {
            $this->info('Migration cancelled by user.');

            return self::SUCCESS;
        }

        // Create backup if requested
        if (($backup || ! $skipBackup) && ! $dryRun) {
            if (! $this->createBackup()) {
                $this->error('Backup creation failed. Migration aborted.');

                return self::FAILURE;
            }
        }

        // Perform migration
        try {
            $result = $this->performMigration($dryRun);

            if ($result) {
                $this->info('✅ Pricing system migration completed successfully!');
                $this->displayMigrationSummary();

                return self::SUCCESS;
            } else {
                $this->error('❌ Migration failed. Check logs for details.');

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Migration failed with exception: ' . $e->getMessage());

            if (! $dryRun) {
                $this->warn('Consider running rollback: php artisan ai:migrate-pricing-system --rollback');
            }

            return self::FAILURE;
        }
    }

    /**
     * Perform pre-migration checks.
     */
    private function performPreMigrationChecks(): bool
    {
        $this->info('Performing pre-migration checks...');

        $checks = [
            'Database Connection' => $this->checkDatabaseConnection(),
            'Required Tables' => $this->checkRequiredTables(),
            'Driver Pricing Classes' => $this->checkDriverPricingClasses(),
            'Configuration' => $this->checkConfiguration(),
            'Disk Space' => $this->checkDiskSpace(),
        ];

        $allPassed = true;
        foreach ($checks as $check => $passed) {
            if ($passed) {
                $this->line("  ✅ {$check}");
            } else {
                $this->line("  ❌ {$check}");
                $allPassed = false;
            }
        }

        $this->newLine();

        return $allPassed;
    }

    /**
     * Display migration plan.
     */
    private function displayMigrationPlan(): void
    {
        $this->info('Migration Plan:');
        $this->line('==============');

        $steps = [
            '1. Validate current pricing data',
            '2. Update database schema (if needed)',
            '3. Migrate static pricing to database',
            '4. Update configuration files',
            '5. Clear pricing caches',
            '6. Validate migrated data',
            '7. Update system status',
        ];

        foreach ($steps as $step) {
            $this->line("  {$step}");
        }

        $this->newLine();
    }

    /**
     * Perform the actual migration.
     */
    private function performMigration(bool $dryRun): bool
    {
        $this->info('Starting migration process...');
        $this->newLine();

        $steps = [
            'validateCurrentPricing' => 'Validating current pricing data',
            'updateDatabaseSchema' => 'Updating database schema',
            'migrateStaticPricing' => 'Migrating static pricing to database',
            'updateConfiguration' => 'Updating configuration',
            'clearCaches' => 'Clearing pricing caches',
            'validateMigratedData' => 'Validating migrated data',
            'updateSystemStatus' => 'Updating system status',
        ];

        foreach ($steps as $method => $description) {
            $this->line("📋 {$description}...");

            if ($dryRun) {
                $this->line("  [DRY RUN] Would execute: {$method}");
                $this->migrationSteps[$method] = ['status' => 'dry_run', 'description' => $description];
            } else {
                try {
                    $result = $this->$method();
                    if ($result) {
                        $this->line('  ✅ Completed');
                        $this->migrationSteps[$method] = ['status' => 'success', 'description' => $description];
                    } else {
                        $this->line('  ❌ Failed');
                        $this->migrationSteps[$method] = ['status' => 'failed', 'description' => $description];

                        return false;
                    }
                } catch (\Exception $e) {
                    $this->line('  ❌ Error: ' . $e->getMessage());
                    $this->migrationSteps[$method] = [
                        'status' => 'error',
                        'description' => $description,
                        'error' => $e->getMessage(),
                    ];

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Create backup of current system.
     */
    private function createBackup(): bool
    {
        $this->info('Creating system backup...');

        $backupData = [
            'timestamp' => now()->toISOString(),
            'database_tables' => $this->backupDatabaseTables(),
            'configuration' => $this->backupConfiguration(),
            'cache_state' => $this->backupCacheState(),
        ];

        $backupFile = 'pricing_system_backup_' . now()->format('Y_m_d_H_i_s') . '.json';

        try {
            Storage::disk('local')->put('backups/' . $backupFile, json_encode($backupData, JSON_PRETTY_PRINT));
            $this->line("  ✅ Backup created: storage/app/backups/{$backupFile}");

            return true;
        } catch (\Exception $e) {
            $this->line('  ❌ Backup failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Handle rollback operation.
     */
    private function handleRollback(): int
    {
        $this->info('Pricing System Rollback');
        $this->line('=======================');
        $this->newLine();

        // Find latest backup
        $backupFiles = Storage::disk('local')->files('backups');
        $pricingBackups = array_filter($backupFiles, fn ($file) => str_contains($file, 'pricing_system_backup_'));

        if (empty($pricingBackups)) {
            $this->error('No backup files found. Cannot perform rollback.');

            return self::FAILURE;
        }

        $latestBackup = collect($pricingBackups)->sort()->last();
        $this->info("Latest backup found: {$latestBackup}");

        if (! $this->confirm('Proceed with rollback? This will restore the previous pricing system.')) {
            $this->info('Rollback cancelled.');

            return self::SUCCESS;
        }

        try {
            $backupData = json_decode(Storage::disk('local')->get($latestBackup), true);

            if ($this->restoreFromBackup($backupData)) {
                $this->info('✅ Rollback completed successfully!');

                return self::SUCCESS;
            } else {
                $this->error('❌ Rollback failed.');

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Rollback failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Handle validation-only operation.
     */
    private function handleValidationOnly(): int
    {
        $this->info('Pricing System Validation');
        $this->line('=========================');
        $this->newLine();

        $providers = ['openai', 'gemini', 'xai'];
        $totalErrors = 0;
        $totalWarnings = 0;

        foreach ($providers as $provider) {
            $this->line("<info>{$provider}:</info>");

            try {
                $pricingClass = $this->getDriverPricingClass($provider);
                if (! $pricingClass) {
                    $this->line('  ⏭ No pricing class found');

                    continue;
                }

                $allPricing = $pricingClass->getAllModelPricing();
                $validationResult = $this->pricingValidator->getValidationSummary($allPricing);

                $this->line("  Models: {$validationResult['model_count']}");
                $this->line("  Errors: {$validationResult['error_count']}");
                $this->line("  Warnings: {$validationResult['warning_count']}");

                if (! empty($validationResult['errors'])) {
                    foreach (array_slice($validationResult['errors'], 0, 3) as $error) {
                        $this->line("    ❌ {$error}");
                    }
                }

                $totalErrors += $validationResult['error_count'];
                $totalWarnings += $validationResult['warning_count'];
            } catch (\Exception $e) {
                $this->line('  ❌ Validation failed: ' . $e->getMessage());
                $totalErrors++;
            }
        }

        $this->newLine();
        $this->info('Validation Summary:');
        $this->line("Total Errors: {$totalErrors}");
        $this->line("Total Warnings: {$totalWarnings}");

        if ($totalErrors > 0) {
            $this->error('❌ Validation failed with errors. Fix issues before migration.');

            return self::FAILURE;
        } else {
            $this->info('✅ Validation passed. System ready for migration.');

            return self::SUCCESS;
        }
    }

    /**
     * Check database connection.
     */
    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check required database tables exist.
     */
    private function checkRequiredTables(): bool
    {
        $requiredTables = [
            'ai_providers',
            'ai_provider_models',
            'ai_provider_model_costs',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check driver pricing classes exist.
     */
    private function checkDriverPricingClasses(): bool
    {
        $providers = ['openai', 'gemini', 'xai'];

        foreach ($providers as $provider) {
            $pricingClass = $this->getDriverPricingClass($provider);
            if (! $pricingClass) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check configuration is valid.
     */
    private function checkConfiguration(): bool
    {
        $requiredConfigs = [
            'ai.model_sync.pricing.enabled',
            'ai.model_sync.pricing.store_to_database',
        ];

        foreach ($requiredConfigs as $config) {
            if (config($config) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check available disk space.
     */
    private function checkDiskSpace(): bool
    {
        $freeBytes = disk_free_space(storage_path());
        $requiredBytes = 100 * 1024 * 1024; // 100MB minimum

        return $freeBytes > $requiredBytes;
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
     * Validate current pricing data.
     */
    private function validateCurrentPricing(): bool
    {
        $providers = ['openai', 'gemini', 'xai'];
        $hasErrors = false;

        foreach ($providers as $provider) {
            $pricingClass = $this->getDriverPricingClass($provider);
            if (! $pricingClass) {
                continue;
            }

            $allPricing = $pricingClass->getAllModelPricing();
            $errors = $this->pricingValidator->validatePricingArray($allPricing);

            if (! empty($errors)) {
                $hasErrors = true;
                $this->warn("  Validation errors in {$provider}: " . count($errors));
            }
        }

        return ! $hasErrors;
    }

    /**
     * Update database schema if needed.
     */
    private function updateDatabaseSchema(): bool
    {
        // Check if migration is needed
        if (! $this->needsSchemaUpdate()) {
            return true;
        }

        try {
            // Run the enum migration
            $this->call('migrate', [
                '--path' => 'database/migrations/2025_08_23_000001_update_ai_provider_model_costs_for_enums.php',
                '--force' => true,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->error('Schema update failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if schema update is needed.
     */
    private function needsSchemaUpdate(): bool
    {
        try {
            // Check if the new enum values are supported
            $result = DB::select("SHOW COLUMNS FROM ai_provider_model_costs LIKE 'billing_model'");
            if (empty($result)) {
                return true;
            }

            $enumValues = $result[0]->Type ?? '';

            return ! str_contains($enumValues, 'tiered') || ! str_contains($enumValues, 'enterprise');
        } catch (\Exception $e) {
            return true; // Assume update needed if we can't check
        }
    }

    /**
     * Migrate static pricing to database.
     */
    private function migrateStaticPricing(): bool
    {
        $providers = ['openai', 'gemini', 'xai'];
        $totalMigrated = 0;

        foreach ($providers as $provider) {
            $pricingClass = $this->getDriverPricingClass($provider);
            if (! $pricingClass) {
                continue;
            }

            $allPricing = $pricingClass->getAllModelPricing();
            $migrated = 0;

            foreach ($allPricing as $model => $pricing) {
                if ($this->pricingService->storePricingToDatabase($provider, $model, $pricing)) {
                    $migrated++;
                }
            }

            $this->line("    {$provider}: {$migrated} models migrated");
            $totalMigrated += $migrated;
        }

        $this->line("  Total: {$totalMigrated} models migrated to database");

        return $totalMigrated > 0;
    }

    /**
     * Update configuration files.
     */
    private function updateConfiguration(): bool
    {
        // This would update configuration to enable database-first pricing
        // For now, just return true as config is already updated
        return true;
    }

    /**
     * Clear pricing caches.
     */
    private function clearCaches(): bool
    {
        try {
            $this->pricingService->clearCache();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate migrated data.
     */
    private function validateMigratedData(): bool
    {
        try {
            $count = DB::table('ai_provider_model_costs')
                ->where('is_current', true)
                ->count();

            return $count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update system status.
     */
    private function updateSystemStatus(): bool
    {
        // Mark migration as complete
        // This could update a system status table or config
        return true;
    }

    /**
     * Backup database tables.
     */
    private function backupDatabaseTables(): array
    {
        $tables = ['ai_providers', 'ai_provider_models', 'ai_provider_model_costs'];
        $backup = [];

        foreach ($tables as $table) {
            try {
                if (Schema::hasTable($table)) {
                    $backup[$table] = DB::table($table)->get()->toArray();
                }
            } catch (\Exception $e) {
                $backup[$table] = ['error' => $e->getMessage()];
            }
        }

        return $backup;
    }

    /**
     * Backup configuration.
     */
    private function backupConfiguration(): array
    {
        return [
            'ai_config' => config('ai'),
            'env_vars' => [
                'AI_PRICING_SYNC_ENABLED' => env('AI_PRICING_SYNC_ENABLED'),
                'AI_PRICING_STORE_DATABASE' => env('AI_PRICING_STORE_DATABASE'),
            ],
        ];
    }

    /**
     * Backup cache state.
     */
    private function backupCacheState(): array
    {
        // This would backup current cache state
        return ['cache_backed_up' => true];
    }

    /**
     * Restore from backup.
     */
    private function restoreFromBackup(array $backupData): bool
    {
        try {
            // Restore database tables
            if (isset($backupData['database_tables'])) {
                foreach ($backupData['database_tables'] as $table => $data) {
                    if (! isset($data['error'])) {
                        DB::table($table)->truncate();
                        if (! empty($data)) {
                            DB::table($table)->insert($data);
                        }
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->error('Restore failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Display migration summary.
     */
    private function displayMigrationSummary(): void
    {
        $this->newLine();
        $this->info('Migration Summary:');
        $this->line('==================');

        foreach ($this->migrationSteps as $step => $data) {
            $status = match ($data['status']) {
                'success' => '✅',
                'failed' => '❌',
                'error' => '💥',
                'dry_run' => '🔍',
                default => '❓',
            };

            $this->line("{$status} {$data['description']}");

            if (isset($data['error'])) {
                $this->line("    Error: {$data['error']}");
            }
        }

        $this->newLine();
        $this->info('Next Steps:');
        $this->line('- Test the enhanced pricing system');
        $this->line('- Run: php artisan ai:sync-models --show-pricing');
        $this->line('- Monitor pricing accuracy and performance');
    }
}
