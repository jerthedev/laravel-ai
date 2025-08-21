# Background Jobs and Scheduling

## Overview

JTD Laravel AI uses Laravel's queue system for background processing of time-intensive operations like model syncing, cost calculations, analytics generation, and maintenance tasks. This ensures the main application remains responsive while handling AI-related operations efficiently.

## Core Background Jobs

### Model Synchronization Jobs

#### SyncModelsJob

```php
<?php

namespace JTD\LaravelAI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelAI\Services\ModelSyncService;

class SyncModelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $timeout = 300; // 5 minutes
    public $tries = 3;
    
    public function __construct(
        public ?string $provider = null,
        public bool $force = false
    ) {}
    
    public function handle(ModelSyncService $syncService): void
    {
        if ($this->provider) {
            $syncService->syncProvider($this->provider, $this->force);
        } else {
            $syncService->syncAllProviders($this->force);
        }
    }
    
    public function failed(\Throwable $exception): void
    {
        Log::error('Model sync job failed', [
            'provider' => $this->provider,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

#### SyncModelPricingJob

```php
<?php

namespace JTD\LaravelAI\Jobs;

class SyncModelPricingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public ?string $provider = null
    ) {}
    
    public function handle(): void
    {
        $providers = $this->provider 
            ? [$this->provider] 
            : config('ai.providers', []);
            
        foreach ($providers as $providerName => $config) {
            $driver = AI::provider($providerName);
            
            if (method_exists($driver, 'syncPricing')) {
                $driver->syncPricing();
            }
        }
    }
}
```

### Cost Calculation Jobs

#### CalculateConversationCostsJob

```php
<?php

namespace JTD\LaravelAI\Jobs;

class CalculateConversationCostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public int $conversationId
    ) {}
    
    public function handle(): void
    {
        $conversation = AIConversation::find($this->conversationId);
        
        if (!$conversation) {
            return;
        }
        
        $totalCost = $conversation->messages()
            ->whereNull('cost')
            ->get()
            ->sum(function ($message) {
                return $this->calculateMessageCost($message);
            });
            
        $conversation->update([
            'total_cost' => $conversation->total_cost + $totalCost,
        ]);
    }
    
    private function calculateMessageCost($message): float
    {
        $driver = AI::provider($message->provider);
        
        $usage = new TokenUsage(
            $message->input_tokens,
            $message->output_tokens
        );
        
        $cost = $driver->calculateCost($usage, $message->model);
        
        $message->update(['cost' => $cost]);
        
        return $cost;
    }
}
```

#### BatchCostCalculationJob

```php
<?php

namespace JTD\LaravelAI\Jobs;

class BatchCostCalculationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $timeout = 600; // 10 minutes
    
    public function __construct(
        public array $messageIds
    ) {}
    
    public function handle(): void
    {
        $messages = AIMessage::whereIn('id', $this->messageIds)
            ->whereNull('cost')
            ->get();
            
        foreach ($messages as $message) {
            try {
                $this->calculateMessageCost($message);
            } catch (\Exception $e) {
                Log::error('Cost calculation failed', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

### Analytics Jobs

#### GenerateAnalyticsJob

```php
<?php

namespace JTD\LaravelAI\Jobs;

class GenerateAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $timeout = 900; // 15 minutes
    
    public function __construct(
        public string $period = 'daily',
        public ?string $date = null
    ) {}
    
    public function handle(): void
    {
        $date = $this->date ?? now()->format('Y-m-d');
        
        match ($this->period) {
            'daily' => $this->generateDailyAnalytics($date),
            'weekly' => $this->generateWeeklyAnalytics($date),
            'monthly' => $this->generateMonthlyAnalytics($date),
        };
    }
    
    private function generateDailyAnalytics(string $date): void
    {
        $analytics = [
            'date' => $date,
            'total_requests' => $this->getTotalRequests($date),
            'total_cost' => $this->getTotalCost($date),
            'total_tokens' => $this->getTotalTokens($date),
            'unique_users' => $this->getUniqueUsers($date),
            'provider_breakdown' => $this->getProviderBreakdown($date),
            'model_breakdown' => $this->getModelBreakdown($date),
        ];
        
        AIUsageAnalytics::updateOrCreate(
            ['date' => $date, 'period' => 'daily'],
            $analytics
        );
    }
}
```

#### GenerateUserAnalyticsJob

```php
<?php

namespace JTD\LaravelAI\Jobs;

class GenerateUserAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public int $userId,
        public string $period = 'monthly'
    ) {}
    
    public function handle(): void
    {
        $user = User::find($this->userId);
        
        if (!$user) {
            return;
        }
        
        $analytics = $this->calculateUserAnalytics($user);
        
        AIUserAnalytics::updateOrCreate([
            'user_id' => $this->userId,
            'period' => $this->period,
            'date' => now()->format('Y-m-d'),
        ], $analytics);
    }
}
```

### Maintenance Jobs

#### CleanupOldConversationsJob

```php
<?php

namespace JTD\LaravelAI\Jobs;

class CleanupOldConversationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public int $retentionDays = 90
    ) {}
    
    public function handle(): void
    {
        $cutoffDate = now()->subDays($this->retentionDays);
        
        // Archive old conversations
        $oldConversations = AIConversation::where('updated_at', '<', $cutoffDate)
            ->where('archived', false)
            ->get();
            
        foreach ($oldConversations as $conversation) {
            $this->archiveConversation($conversation);
        }
        
        // Delete very old archived conversations
        $veryOldDate = now()->subDays($this->retentionDays * 2);
        
        AIConversation::where('updated_at', '<', $veryOldDate)
            ->where('archived', true)
            ->delete();
    }
    
    private function archiveConversation($conversation): void
    {
        // Export conversation data
        $exportData = $conversation->toArray();
        $exportData['messages'] = $conversation->messages->toArray();
        
        // Store in archive storage
        Storage::disk('archive')->put(
            "conversations/{$conversation->id}.json",
            json_encode($exportData)
        );
        
        // Mark as archived
        $conversation->update(['archived' => true]);
        
        // Delete messages to save space
        $conversation->messages()->delete();
    }
}
```

#### OptimizeDatabaseJob

```php
<?php

namespace JTD\LaravelAI\Jobs;

class OptimizeDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function handle(): void
    {
        // Optimize tables
        DB::statement('OPTIMIZE TABLE ai_conversations');
        DB::statement('OPTIMIZE TABLE ai_messages');
        DB::statement('OPTIMIZE TABLE ai_usage_analytics');
        
        // Update statistics
        DB::statement('ANALYZE TABLE ai_conversations');
        DB::statement('ANALYZE TABLE ai_messages');
        
        // Clean up orphaned records
        $this->cleanupOrphanedRecords();
    }
    
    private function cleanupOrphanedRecords(): void
    {
        // Delete messages without conversations
        AIMessage::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('ai_conversations')
                ->whereColumn('ai_conversations.id', 'ai_messages.conversation_id');
        })->delete();
        
        // Delete analytics for deleted users
        AIUserAnalytics::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('users')
                ->whereColumn('users.id', 'ai_user_analytics.user_id');
        })->delete();
    }
}
```

## Job Scheduling

### Scheduled Commands

```php
<?php

namespace JTD\LaravelAI\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Model syncing
        $schedule->job(new SyncModelsJob())
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
            
        $schedule->job(new SyncModelPricingJob())
            ->daily()
            ->at('02:00')
            ->onOneServer();
        
        // Analytics generation
        $schedule->job(new GenerateAnalyticsJob('daily'))
            ->daily()
            ->at('01:00')
            ->onOneServer();
            
        $schedule->job(new GenerateAnalyticsJob('weekly'))
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->onOneServer();
            
        $schedule->job(new GenerateAnalyticsJob('monthly'))
            ->monthly()
            ->at('04:00')
            ->onOneServer();
        
        // Cost calculations
        $schedule->call(function () {
            $uncalculatedMessages = AIMessage::whereNull('cost')
                ->limit(1000)
                ->pluck('id')
                ->toArray();
                
            if (!empty($uncalculatedMessages)) {
                dispatch(new BatchCostCalculationJob($uncalculatedMessages));
            }
        })->everyFifteenMinutes();
        
        // Maintenance
        $schedule->job(new CleanupOldConversationsJob())
            ->weekly()
            ->saturdays()
            ->at('05:00')
            ->onOneServer();
            
        $schedule->job(new OptimizeDatabaseJob())
            ->weekly()
            ->sundays()
            ->at('06:00')
            ->onOneServer();
        
        // Budget monitoring
        $schedule->call(function () {
            dispatch(new CheckBudgetAlertsJob());
        })->hourly();
        
        // Health checks
        $schedule->call(function () {
            dispatch(new HealthCheckJob());
        })->everyFiveMinutes();
    }
}
```

### Dynamic Job Scheduling

```php
// Schedule jobs dynamically based on configuration
class DynamicJobScheduler
{
    public function scheduleJobs(): void
    {
        $config = config('ai.scheduling', []);
        
        foreach ($config as $jobConfig) {
            $this->scheduleJob($jobConfig);
        }
    }
    
    private function scheduleJob(array $config): void
    {
        $jobClass = $config['job'];
        $frequency = $config['frequency'];
        $parameters = $config['parameters'] ?? [];
        
        $job = new $jobClass(...$parameters);
        
        match ($frequency) {
            'hourly' => dispatch($job)->delay(now()->addHour()),
            'daily' => dispatch($job)->delay(now()->addDay()),
            'weekly' => dispatch($job)->delay(now()->addWeek()),
            default => dispatch($job),
        };
    }
}
```

## Queue Configuration

### Queue Setup

```php
// config/queue.php
'connections' => [
    'ai-high-priority' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'ai-high',
        'retry_after' => 300,
        'block_for' => null,
    ],
    
    'ai-low-priority' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'ai-low',
        'retry_after' => 600,
        'block_for' => null,
    ],
    
    'ai-analytics' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'ai-analytics',
        'retry_after' => 900,
        'block_for' => null,
    ],
],
```

### Job Prioritization

```php
// High priority jobs (real-time cost calculations)
dispatch(new CalculateMessageCostJob($messageId))
    ->onQueue('ai-high-priority');

// Medium priority jobs (model syncing)
dispatch(new SyncModelsJob())
    ->onQueue('default');

// Low priority jobs (analytics, cleanup)
dispatch(new GenerateAnalyticsJob())
    ->onQueue('ai-low-priority');

// Delayed jobs
dispatch(new CleanupOldConversationsJob())
    ->delay(now()->addHours(2))
    ->onQueue('ai-low-priority');
```

## Job Monitoring

### Job Status Tracking

```php
<?php

namespace JTD\LaravelAI\Jobs;

class TrackableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function handle(): void
    {
        $this->updateStatus('processing');
        
        try {
            $this->performWork();
            $this->updateStatus('completed');
        } catch (\Exception $e) {
            $this->updateStatus('failed', $e->getMessage());
            throw $e;
        }
    }
    
    private function updateStatus(string $status, ?string $message = null): void
    {
        AIJobStatus::updateOrCreate([
            'job_id' => $this->job->getJobId(),
        ], [
            'status' => $status,
            'message' => $message,
            'updated_at' => now(),
        ]);
    }
}
```

### Job Metrics

```php
// Track job performance
class JobMetricsCollector
{
    public function collectMetrics(): array
    {
        return [
            'jobs_processed_today' => $this->getJobsProcessedToday(),
            'average_processing_time' => $this->getAverageProcessingTime(),
            'failed_jobs_count' => $this->getFailedJobsCount(),
            'queue_sizes' => $this->getQueueSizes(),
            'worker_status' => $this->getWorkerStatus(),
        ];
    }
    
    private function getJobsProcessedToday(): int
    {
        return AIJobStatus::whereDate('created_at', today())
            ->where('status', 'completed')
            ->count();
    }
    
    private function getAverageProcessingTime(): float
    {
        return AIJobStatus::whereDate('created_at', today())
            ->where('status', 'completed')
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, created_at, updated_at)'));
    }
}
```

## Error Handling and Recovery

### Job Retry Logic

```php
abstract class RetryableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $tries = 3;
    public $maxExceptions = 2;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min
    
    public function retryUntil(): DateTime
    {
        return now()->addHours(2);
    }
    
    public function failed(\Throwable $exception): void
    {
        Log::error('Job failed permanently', [
            'job' => static::class,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
        
        // Notify administrators
        $this->notifyFailure($exception);
    }
    
    protected function notifyFailure(\Throwable $exception): void
    {
        // Send notification to administrators
        Notification::route('mail', config('ai.admin_email'))
            ->notify(new JobFailedNotification(static::class, $exception));
    }
}
```

### Dead Letter Queue

```php
// Handle permanently failed jobs
class DeadLetterQueueHandler
{
    public function handleFailedJob(array $jobData): void
    {
        // Store failed job for analysis
        AIFailedJob::create([
            'job_class' => $jobData['displayName'],
            'payload' => json_encode($jobData),
            'exception' => $jobData['exception'] ?? null,
            'failed_at' => now(),
        ]);
        
        // Attempt recovery if possible
        if ($this->canRecover($jobData)) {
            $this->attemptRecovery($jobData);
        }
    }
    
    private function canRecover(array $jobData): bool
    {
        // Determine if job can be recovered
        return in_array($jobData['displayName'], [
            'SyncModelsJob',
            'CalculateConversationCostsJob',
        ]);
    }
}
```

## Performance Optimization

### Job Batching

```php
// Batch similar jobs together
$batch = Bus::batch([
    new SyncModelsJob('openai'),
    new SyncModelsJob('gemini'),
    new SyncModelsJob('xai'),
])->then(function (Batch $batch) {
    // All providers synced successfully
    Log::info('All model sync jobs completed');
})->catch(function (Batch $batch, Throwable $e) {
    // One or more jobs failed
    Log::error('Model sync batch failed', ['error' => $e->getMessage()]);
})->finally(function (Batch $batch) {
    // Cleanup or notification
})->dispatch();
```

### Job Chunking

```php
// Process large datasets in chunks
class ChunkedAnalyticsJob implements ShouldQueue
{
    public function handle(): void
    {
        AIMessage::whereNull('cost')
            ->chunk(1000, function ($messages) {
                dispatch(new BatchCostCalculationJob($messages->pluck('id')->toArray()));
            });
    }
}
```

### Resource Management

```php
// Manage memory usage in long-running jobs
class MemoryEfficientJob implements ShouldQueue
{
    public function handle(): void
    {
        $processed = 0;
        
        AIConversation::lazy()->each(function ($conversation) use (&$processed) {
            $this->processConversation($conversation);
            
            $processed++;
            
            // Clear memory every 100 records
            if ($processed % 100 === 0) {
                gc_collect_cycles();
            }
        });
    }
}
```
