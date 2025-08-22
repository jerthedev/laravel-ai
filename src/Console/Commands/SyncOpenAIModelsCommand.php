<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use JTD\LaravelAI\Jobs\SyncOpenAIModelsJob;

/**
 * Command to synchronize OpenAI models.
 *
 * This command can be used to manually trigger model synchronization
 * or to schedule regular updates via cron.
 */
class SyncOpenAIModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:sync-openai-models 
                            {--force : Force refresh even if cache is valid}
                            {--sync : Run synchronously instead of queuing}
                            {--show-stats : Show current model statistics}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize OpenAI models with local cache';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('show-stats')) {
            return $this->showStatistics();
        }

        $force = $this->option('force');
        $sync = $this->option('sync');

        $this->info('Starting OpenAI models synchronization...');

        if ($sync) {
            // Run synchronously
            try {
                $job = new SyncOpenAIModelsJob($force);
                $job->handle(app('ai.driver-manager'));
                
                $this->info('✅ OpenAI models synchronized successfully');
                return self::SUCCESS;
                
            } catch (\Exception $e) {
                $this->error('❌ Synchronization failed: ' . $e->getMessage());
                return self::FAILURE;
            }
        } else {
            // Queue the job
            SyncOpenAIModelsJob::dispatch($force);
            $this->info('🚀 OpenAI models synchronization job queued');
            
            if ($force) {
                $this->warn('⚠️  Force refresh enabled - cache will be updated regardless of age');
            }
            
            return self::SUCCESS;
        }
    }

    /**
     * Show current model statistics.
     */
    protected function showStatistics(): int
    {
        $this->info('OpenAI Models Statistics');
        $this->line('========================');

        // Get cached models
        $models = Cache::get('laravel-ai:openai:models');
        $stats = Cache::get('laravel-ai:openai:models:stats');
        $lastSync = Cache::get('laravel-ai:openai:models:last_sync');
        $lastFailure = Cache::get('laravel-ai:openai:models:last_failure');

        if (!$models) {
            $this->warn('No cached models found. Run sync first.');
            return self::SUCCESS;
        }

        // Display basic info
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Models', count($models)],
                ['Last Sync', $lastSync ? $lastSync->diffForHumans() : 'Never'],
                ['Cache Expires', $lastSync ? $lastSync->addHours(24)->diffForHumans() : 'N/A'],
            ]
        );

        // Display statistics if available
        if ($stats) {
            $this->newLine();
            $this->info('Model Breakdown:');
            $this->table(
                ['Model Type', 'Count'],
                [
                    ['GPT-3.5 Models', $stats['gpt_3_5_models']],
                    ['GPT-4 Models', $stats['gpt_4_models']],
                    ['GPT-4o Models', $stats['gpt_4o_models']],
                    ['Function Calling', $stats['function_calling_models']],
                    ['Vision Capable', $stats['vision_models']],
                ]
            );
        }

        // Show recent models
        $this->newLine();
        $this->info('Available Models:');
        $modelTable = [];
        foreach (array_slice($models, 0, 10) as $model) {
            $capabilities = implode(', ', $model['capabilities'] ?? []);
            $modelTable[] = [
                $model['id'],
                $model['name'] ?? $model['id'],
                $capabilities ?: 'Basic',
                number_format($model['context_length'] ?? 0),
            ];
        }

        $this->table(
            ['ID', 'Name', 'Capabilities', 'Context Length'],
            $modelTable
        );

        if (count($models) > 10) {
            $this->info('... and ' . (count($models) - 10) . ' more models');
        }

        // Show last failure if any
        if ($lastFailure) {
            $this->newLine();
            $this->error('Last Failure: ' . $lastFailure['error']);
            $this->line('Failed at: ' . $lastFailure['failed_at']);
        }

        return self::SUCCESS;
    }
}
