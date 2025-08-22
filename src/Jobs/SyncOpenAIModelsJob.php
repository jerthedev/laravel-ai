<?php

namespace JTD\LaravelAI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIException;

/**
 * Job to synchronize OpenAI models with local database/cache.
 *
 * This job fetches the latest model information from OpenAI API
 * and updates the local cache with model capabilities, pricing,
 * and availability information.
 */
class SyncOpenAIModelsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Whether to force refresh even if cache is valid.
     */
    protected bool $forceRefresh;

    /**
     * Create a new job instance.
     */
    public function __construct(bool $forceRefresh = false)
    {
        $this->forceRefresh = $forceRefresh;
        $this->onQueue('ai-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(DriverManager $driverManager): void
    {
        try {
            Log::info('Starting OpenAI models synchronization', [
                'force_refresh' => $this->forceRefresh,
                'job_id' => $this->job->getJobId(),
            ]);

            // Get OpenAI driver
            $driver = $driverManager->driver('openai');

            // Check if we need to refresh
            if (!$this->shouldRefresh()) {
                Log::info('OpenAI models cache is still valid, skipping sync');
                return;
            }

            // Fetch models from OpenAI API
            $models = $driver->getAvailableModels(true);

            // Store in cache with 24-hour expiration
            $cacheKey = 'laravel-ai:openai:models';
            Cache::put($cacheKey, $models, now()->addHours(24));

            // Store last sync timestamp
            Cache::put($cacheKey . ':last_sync', now(), now()->addDays(7));

            // Log success
            Log::info('OpenAI models synchronization completed', [
                'models_count' => count($models),
                'cached_until' => now()->addHours(24)->toISOString(),
            ]);

            // Store model statistics
            $this->storeModelStatistics($models);

        } catch (OpenAIException $e) {
            Log::error('OpenAI models synchronization failed', [
                'error' => $e->getMessage(),
                'error_type' => $e->getOpenAIErrorType(),
                'request_id' => $e->getRequestId(),
            ]);

            throw $e;

        } catch (\Exception $e) {
            Log::error('OpenAI models synchronization failed with unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('OpenAI models synchronization job failed permanently', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'max_tries' => $this->tries,
        ]);

        // Store failure information in cache for monitoring
        Cache::put(
            'laravel-ai:openai:models:last_failure',
            [
                'error' => $exception->getMessage(),
                'failed_at' => now()->toISOString(),
                'attempts' => $this->attempts(),
            ],
            now()->addHours(24)
        );
    }

    /**
     * Determine if we should refresh the models cache.
     */
    protected function shouldRefresh(): bool
    {
        if ($this->forceRefresh) {
            return true;
        }

        $cacheKey = 'laravel-ai:openai:models';
        $lastSync = Cache::get($cacheKey . ':last_sync');

        // Refresh if no last sync time or if it's been more than 12 hours
        return !$lastSync || $lastSync->diffInHours(now()) >= 12;
    }

    /**
     * Store model statistics for monitoring.
     */
    protected function storeModelStatistics(array $models): void
    {
        $stats = [
            'total_models' => count($models),
            'gpt_3_5_models' => 0,
            'gpt_4_models' => 0,
            'gpt_4o_models' => 0,
            'function_calling_models' => 0,
            'vision_models' => 0,
            'updated_at' => now()->toISOString(),
        ];

        foreach ($models as $model) {
            $modelId = $model['id'];
            $capabilities = $model['capabilities'] ?? [];

            // Count model types
            if (str_contains($modelId, 'gpt-3.5')) {
                $stats['gpt_3_5_models']++;
            } elseif (str_contains($modelId, 'gpt-4o')) {
                $stats['gpt_4o_models']++;
            } elseif (str_contains($modelId, 'gpt-4')) {
                $stats['gpt_4_models']++;
            }

            // Count capabilities
            if (in_array('function_calling', $capabilities)) {
                $stats['function_calling_models']++;
            }

            if (in_array('vision', $capabilities)) {
                $stats['vision_models']++;
            }
        }

        // Store statistics
        Cache::put('laravel-ai:openai:models:stats', $stats, now()->addDays(7));

        Log::info('OpenAI model statistics updated', $stats);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['openai', 'model-sync', 'ai-maintenance'];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 180, 300]; // 1 min, 3 min, 5 min
    }

    /**
     * Schedule this job to run regularly.
     *
     * Add this to your App\Console\Kernel::schedule() method:
     * $schedule->job(new SyncOpenAIModelsJob())->twiceDaily(2, 14);
     */
    public static function scheduleDaily(): void
    {
        // This is a helper method for documentation
        // The actual scheduling should be done in the application's Kernel
    }
}
