<?php

namespace JTD\LaravelAI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Events\FunctionCallRequested;
use JTD\LaravelAI\Services\AIFunctionEvent;

/**
 * Process Function Call Job
 *
 * Laravel job for processing Function Event calls in background queue.
 * Integrates with the existing FunctionCallRequested event system.
 */
class ProcessFunctionCallJob implements ShouldQueue
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
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $functionName,
        public array $parameters,
        public int $userId,
        public ?int $conversationId = null,
        public ?int $messageId = null,
        public array $context = []
    ) {
        // Set queue from context if provided, default to 'ai-functions'
        $queueName = $context['queue'] ?? 'ai-functions';
        $this->onQueue($queueName);

        // Set timeout from context if provided
        if (isset($context['timeout'])) {
            $this->timeout = $context['timeout'];
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing Function Event call', [
            'function_name' => $this->functionName,
            'user_id' => $this->userId,
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Use the existing AIFunctionEvent system to process the call
            AIFunctionEvent::processFunctionCall(
                $this->functionName,
                $this->parameters,
                array_merge($this->context, [
                    'user_id' => $this->userId,
                    'conversation_id' => $this->conversationId,
                    'message_id' => $this->messageId,
                    'job_id' => $this->job->getJobId(),
                    'processed_at' => now()->toISOString(),
                ])
            );

            Log::info('Function Event call processed successfully', [
                'function_name' => $this->functionName,
                'user_id' => $this->userId,
                'job_id' => $this->job->getJobId(),
            ]);
        } catch (\Exception $e) {
            Log::error('Function Event call processing failed', [
                'function_name' => $this->functionName,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'job_id' => $this->job->getJobId(),
            ]);

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Function Event call job failed permanently', [
            'function_name' => $this->functionName,
            'user_id' => $this->userId,
            'conversation_id' => $this->conversationId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Fire a failure event that can be handled by listeners
        event(new FunctionCallRequested(
            functionName: $this->functionName,
            parameters: $this->parameters,
            userId: $this->userId,
            conversationId: $this->conversationId,
            messageId: $this->messageId,
            context: array_merge($this->context, [
                'job_failed' => true,
                'failure_reason' => $exception->getMessage(),
                'attempts' => $this->attempts(),
            ])
        ));
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'ai-function',
            'function:' . $this->functionName,
            'user:' . $this->userId,
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 seconds, 1 minute, 2 minutes
    }

    /**
     * Determine if the job should be retried based on the exception.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }
}
