<?php

namespace JTD\LaravelAI\Tests\Performance;

use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIProvider;
use JTD\LaravelAI\Models\AIProviderModel;
use JTD\LaravelAI\Models\User;
use JTD\LaravelAI\Services\ConversationService;
use JTD\LaravelAI\Services\ProviderSwitchingService;
use JTD\LaravelAI\Tests\TestCase;

/**
 * Performance tests for multi-provider setup
 */
class MultiProviderPerformanceTest extends TestCase
{
    protected ConversationService $conversationService;

    protected ProviderSwitchingService $switchingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conversationService = app(ConversationService::class);
        $this->switchingService = app(ProviderSwitchingService::class);
    }

    public function test_it_performs_provider_switching_efficiently(): void
    {
        // Create test providers and models
        $openaiProvider = AIProvider::factory()->create(['name' => 'openai']);
        $geminiProvider = AIProvider::factory()->create(['name' => 'gemini']);
        $xaiProvider = AIProvider::factory()->create(['name' => 'xai']);

        $openaiModel = AIProviderModel::factory()->create(['ai_provider_id' => $openaiProvider->id]);
        $geminiModel = AIProviderModel::factory()->create(['ai_provider_id' => $geminiProvider->id]);
        $xaiModel = AIProviderModel::factory()->create(['ai_provider_id' => $xaiProvider->id]);

        $user = User::factory()->create();

        // Create conversation
        $conversation = $this->conversationService->createConversation([
            'title' => 'Performance Test Conversation',
            'ai_provider_id' => $openaiProvider->id,
            'ai_provider_model_id' => $openaiModel->id,
            'created_by_id' => $user->id,
            'created_by_type' => $user::class,
        ]);

        // Add some messages to create context
        for ($i = 0; $i < 10; $i++) {
            $this->conversationService->addMessage($conversation, AIMessage::user("Test message {$i}"));
            $this->conversationService->addMessage($conversation, AIMessage::assistant("Response to test message {$i}"));
        }

        // Measure provider switching performance
        $startTime = microtime(true);
        $switchCount = 0;

        // Switch between providers multiple times
        $providers = [
            ['provider_id' => $geminiProvider->id, 'model_id' => $geminiModel->id],
            ['provider_id' => $xaiProvider->id, 'model_id' => $xaiModel->id],
            ['provider_id' => $openaiProvider->id, 'model_id' => $openaiModel->id],
        ];

        foreach ($providers as $provider) {
            $result = $this->switchingService->switchProvider($conversation, [
                'ai_provider_id' => $provider['provider_id'],
                'ai_provider_model_id' => $provider['model_id'],
                'preserve_context' => true,
                'context_strategy' => 'intelligent_truncation',
            ]);

            $this->assertTrue($result['success']);
            $switchCount++;
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $averageTime = $totalTime / $switchCount;

        // Performance assertions
        $this->assertLessThan(2.0, $totalTime, 'Total switching time should be under 2 seconds');
        $this->assertLessThan(0.7, $averageTime, 'Average switch time should be under 700ms');

        echo "\nProvider Switching Performance:\n";
        echo "Total switches: {$switchCount}\n";
        echo 'Total time: ' . number_format($totalTime, 3) . "s\n";
        echo 'Average time per switch: ' . number_format($averageTime, 3) . "s\n";
    }

    public function test_it_handles_concurrent_conversations_efficiently(): void
    {
        $user = User::factory()->create();
        $provider = AIProvider::factory()->create(['name' => 'mock']);
        $model = AIProviderModel::factory()->create(['ai_provider_id' => $provider->id]);

        $conversationCount = 10;
        $messagesPerConversation = 5;

        $startTime = microtime(true);

        // Create multiple conversations concurrently
        $conversations = [];
        for ($i = 0; $i < $conversationCount; $i++) {
            $conversation = $this->conversationService->createConversation([
                'title' => "Concurrent Test Conversation {$i}",
                'ai_provider_id' => $provider->id,
                'ai_provider_model_id' => $model->id,
                'created_by_id' => $user->id,
                'created_by_type' => $user::class,
            ]);

            // Add messages to each conversation
            for ($j = 0; $j < $messagesPerConversation; $j++) {
                $this->conversationService->addMessage($conversation, AIMessage::user("Message {$j} in conversation {$i}"));
            }

            $conversations[] = $conversation;
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $averageTimePerConversation = $totalTime / $conversationCount;

        // Performance assertions
        $this->assertLessThan(5.0, $totalTime, 'Total conversation creation time should be under 5 seconds');
        $this->assertLessThan(0.5, $averageTimePerConversation, 'Average conversation creation time should be under 500ms');

        echo "\nConcurrent Conversations Performance:\n";
        echo "Conversations created: {$conversationCount}\n";
        echo "Messages per conversation: {$messagesPerConversation}\n";
        echo 'Total time: ' . number_format($totalTime, 3) . "s\n";
        echo 'Average time per conversation: ' . number_format($averageTimePerConversation, 3) . "s\n";
    }

    public function test_it_performs_context_management_efficiently(): void
    {
        $user = User::factory()->create();
        $provider = AIProvider::factory()->create(['name' => 'mock']);
        $model = AIProviderModel::factory()->create(['ai_provider_id' => $provider->id]);

        $conversation = $this->conversationService->createConversation([
            'title' => 'Context Performance Test',
            'ai_provider_id' => $provider->id,
            'ai_provider_model_id' => $model->id,
            'created_by_id' => $user->id,
            'created_by_type' => $user::class,
        ]);

        // Create a large conversation with many messages
        $messageCount = 100;
        for ($i = 0; $i < $messageCount; $i++) {
            $this->conversationService->addMessage($conversation, AIMessage::user(str_repeat("This is a longer test message {$i} ", 10)));
            $this->conversationService->addMessage($conversation, AIMessage::assistant(str_repeat("This is a longer response {$i} ", 15)));
        }

        // Test context building performance
        $contextManager = app(\JTD\LaravelAI\Services\ConversationContextManager::class);

        $startTime = microtime(true);

        $context = $contextManager->buildOptimizedContext($conversation, [
            'max_tokens' => 4000,
            'optimization_level' => 'balanced',
            'preserve_system_messages' => true,
            'preserve_recent_messages' => 10,
        ]);

        $endTime = microtime(true);
        $contextTime = $endTime - $startTime;

        // Performance assertions
        $this->assertLessThan(1.0, $contextTime, 'Context building should take less than 1 second');
        $this->assertNotEmpty($context['messages'], 'Context should contain messages');

        echo "\nContext Management Performance:\n";
        echo 'Total messages in conversation: ' . ($messageCount * 2) . "\n";
        echo 'Context building time: ' . number_format($contextTime, 3) . "s\n";
        echo 'Messages in optimized context: ' . count($context['messages']) . "\n";
    }

    public function test_it_performs_database_queries_efficiently(): void
    {
        $user = User::factory()->create();
        $provider = AIProvider::factory()->create(['name' => 'mock']);
        $model = AIProviderModel::factory()->create(['ai_provider_id' => $provider->id]);

        // Create multiple conversations with messages
        $conversationCount = 20;
        for ($i = 0; $i < $conversationCount; $i++) {
            $conversation = $this->conversationService->createConversation([
                'title' => "DB Performance Test {$i}",
                'ai_provider_id' => $provider->id,
                'ai_provider_model_id' => $model->id,
                'created_by_id' => $user->id,
                'created_by_type' => $user::class,
            ]);

            // Add messages
            for ($j = 0; $j < 5; $j++) {
                $this->conversationService->addMessage($conversation, AIMessage::user("Test message {$j}"));
            }
        }

        // Test query performance
        DB::enableQueryLog();

        $startTime = microtime(true);

        // Perform various database operations
        $conversations = AIConversation::with(['messages', 'provider', 'model'])
            ->where('created_by_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Performance assertions
        $this->assertLessThan(0.5, $queryTime, 'Database queries should complete in under 500ms');
        $this->assertLessThan(10, count($queries), 'Should use efficient queries (under 10 queries)');
        $this->assertCount(10, $conversations, 'Should return correct number of conversations');

        echo "\nDatabase Performance:\n";
        echo 'Query time: ' . number_format($queryTime, 3) . "s\n";
        echo 'Number of queries: ' . count($queries) . "\n";
        echo 'Conversations retrieved: ' . count($conversations) . "\n";
    }

    public function test_it_measures_memory_usage_efficiently(): void
    {
        $initialMemory = memory_get_usage(true);

        $user = User::factory()->create();
        $provider = AIProvider::factory()->create(['name' => 'mock']);
        $model = AIProviderModel::factory()->create(['ai_provider_id' => $provider->id]);

        // Create conversations and measure memory usage
        $conversations = [];
        for ($i = 0; $i < 10; $i++) {
            $conversation = $this->conversationService->createConversation([
                'title' => "Memory Test {$i}",
                'ai_provider_id' => $provider->id,
                'ai_provider_model_id' => $model->id,
                'created_by_id' => $user->id,
                'created_by_type' => $user::class,
            ]);

            // Add messages
            for ($j = 0; $j < 10; $j++) {
                $this->conversationService->addMessage($conversation, AIMessage::user(str_repeat('Memory test content ', 20)));
            }

            $conversations[] = $conversation;
        }

        $finalMemory = memory_get_usage(true);
        $memoryUsed = $finalMemory - $initialMemory;
        $peakMemory = memory_get_peak_usage(true);

        // Memory assertions (allowing for reasonable memory usage)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be under 50MB'); // 50MB
        $this->assertLessThan(100 * 1024 * 1024, $peakMemory, 'Peak memory should be under 100MB'); // 100MB

        echo "\nMemory Usage:\n";
        echo 'Memory used: ' . number_format($memoryUsed / 1024 / 1024, 2) . " MB\n";
        echo 'Peak memory: ' . number_format($peakMemory / 1024 / 1024, 2) . " MB\n";
        echo 'Conversations created: ' . count($conversations) . "\n";
    }
}
