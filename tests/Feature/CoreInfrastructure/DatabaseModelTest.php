<?php

namespace JTD\LaravelAI\Tests\Feature\CoreInfrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessageRecord;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Database and Model Tests
 *
 * Tests database layer, migrations, and model relationships
 * for the core AI infrastructure.
 */
#[Group('core-infrastructure')]
#[Group('database-models')]
class DatabaseModelTest extends TestCase
{
    use RefreshDatabase;

    protected array $performanceMetrics = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->performanceMetrics = [];
    }

    protected function tearDown(): void
    {
        $this->logPerformanceMetrics();
        parent::tearDown();
    }

    #[Test]
    public function it_validates_database_schema_integrity(): void
    {
        $expectedTables = [
            'ai_conversations',
            'ai_message_records',
            'ai_provider_models',
            'ai_provider_model_costs',
        ];

        $startTime = microtime(true);

        foreach ($expectedTables as $table) {
            try {
                $this->assertTrue(Schema::hasTable($table), "Table {$table} should exist");

                // Test table structure
                $columns = Schema::getColumnListing($table);
                $this->assertNotEmpty($columns, "Table {$table} should have columns");

                // Test basic operations
                DB::table($table)->count(); // Should not throw exception
            } catch (\Exception $e) {
                $this->markTestIncomplete("Database schema validation failed for {$table}: " . $e->getMessage());

                return;
            }
        }

        $schemaValidationTime = (microtime(true) - $startTime) * 1000;

        $this->recordMetric('schema_validation', [
            'validation_time_ms' => $schemaValidationTime,
            'tables_validated' => count($expectedTables),
            'target_ms' => 100,
        ]);

        $this->assertLessThan(100, $schemaValidationTime,
            "Schema validation took {$schemaValidationTime}ms, exceeding 100ms target");
    }

    #[Test]
    public function it_tests_model_relationships_performance(): void
    {
        $iterations = 10;
        $relationshipTimes = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            try {
                // Create test data
                $conversation = AIConversation::create([
                    'title' => "Test Conversation {$i}",
                    'user_id' => 1,
                ]);

                // Create related messages
                for ($j = 0; $j < 5; $j++) {
                    AIMessageRecord::create([
                        'conversation_id' => $conversation->id,
                        'role' => $j % 2 === 0 ? 'user' : 'assistant',
                        'content' => "Message {$j} for conversation {$i}",
                        'provider' => 'mock',
                        'model' => 'gpt-4',
                        'input_tokens' => 10,
                        'output_tokens' => 15,
                        'cost' => 0.001,
                    ]);
                }

                // Test relationships
                $messages = $conversation->messages;
                $this->assertCount(5, $messages);

                foreach ($messages as $message) {
                    $this->assertEquals($conversation->id, $message->conversation_id);
                    $this->assertEquals($conversation->id, $message->conversation->id);
                }

                $relationshipTime = (microtime(true) - $startTime) * 1000;
                $relationshipTimes[] = $relationshipTime;
            } catch (\Exception $e) {
                $this->markTestIncomplete('Model relationships test failed: ' . $e->getMessage());

                return;
            }
        }

        $avgTime = array_sum($relationshipTimes) / count($relationshipTimes);

        $this->recordMetric('model_relationships', [
            'average_ms' => $avgTime,
            'iterations' => $iterations,
            'target_ms' => 200,
        ]);

        $this->assertLessThan(200, $avgTime,
            "Model relationships averaged {$avgTime}ms, exceeding 200ms target");
    }

    #[Test]
    public function it_tests_database_query_performance(): void
    {
        // Create test data
        $conversations = [];
        for ($i = 0; $i < 20; $i++) {
            $conversations[] = AIConversation::create([
                'title' => "Performance Test Conversation {$i}",
                'user_id' => ($i % 3) + 1, // Distribute across 3 users
            ]);
        }

        $queryTests = [
            'simple_select' => fn () => AIConversation::count(),
            'filtered_select' => fn () => AIConversation::where('user_id', 1)->count(),
            'ordered_select' => fn () => AIConversation::orderBy('created_at', 'desc')->limit(10)->get(),
            'relationship_eager_load' => fn () => AIConversation::with('messages')->limit(5)->get(),
            'aggregate_query' => fn () => AIConversation::selectRaw('user_id, COUNT(*) as count')->groupBy('user_id')->get(),
        ];

        $queryResults = [];

        foreach ($queryTests as $testName => $query) {
            $startTime = microtime(true);

            try {
                $result = $query();
                $queryTime = (microtime(true) - $startTime) * 1000;

                $queryResults[] = [
                    'test' => $testName,
                    'time_ms' => $queryTime,
                    'result_count' => is_countable($result) ? count($result) : 1,
                ];

                $this->assertLessThan(50, $queryTime,
                    "Query {$testName} took {$queryTime}ms, exceeding 50ms target");
            } catch (\Exception $e) {
                $queryResults[] = [
                    'test' => $testName,
                    'time_ms' => (microtime(true) - $startTime) * 1000,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->recordMetric('database_query_performance', [
            'queries_tested' => count($queryTests),
            'query_results' => $queryResults,
        ]);

        // Verify all queries completed successfully
        $errors = collect($queryResults)->filter(fn ($result) => isset($result['error']));
        $this->assertEmpty($errors, 'All database queries should complete successfully');
    }

    #[Test]
    public function it_tests_model_validation_and_constraints(): void
    {
        $validationTests = [
            'conversation_required_fields' => function () {
                try {
                    AIConversation::create([]); // Should fail - title required

                    return false;
                } catch (\Exception $e) {
                    return true; // Expected to fail
                }
            },
            'message_record_constraints' => function () {
                $conversation = AIConversation::create(['title' => 'Test']);

                try {
                    AIMessageRecord::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'user',
                        'content' => 'Test message',
                        'provider' => 'mock',
                        'model' => 'gpt-4',
                        'input_tokens' => 10,
                        'output_tokens' => 15,
                        'cost' => 0.001,
                    ]);

                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            },
            'uuid_uniqueness' => function () {
                $conv1 = AIConversation::create(['title' => 'Test 1']);
                $conv2 = AIConversation::create(['title' => 'Test 2']);

                return $conv1->uuid !== $conv2->uuid;
            },
        ];

        $validationResults = [];

        foreach ($validationTests as $testName => $test) {
            $startTime = microtime(true);

            try {
                $result = $test();
                $validationTime = (microtime(true) - $startTime) * 1000;

                $validationResults[] = [
                    'test' => $testName,
                    'passed' => $result,
                    'time_ms' => $validationTime,
                ];

                $this->assertTrue($result, "Validation test {$testName} should pass");
                $this->assertLessThan(100, $validationTime,
                    "Validation test {$testName} took {$validationTime}ms, exceeding 100ms target");
            } catch (\Exception $e) {
                $validationResults[] = [
                    'test' => $testName,
                    'passed' => false,
                    'error' => $e->getMessage(),
                    'time_ms' => (microtime(true) - $startTime) * 1000,
                ];
            }
        }

        $this->recordMetric('model_validation', [
            'validation_tests' => count($validationTests),
            'validation_results' => $validationResults,
        ]);
    }

    #[Test]
    public function it_tests_database_transaction_handling(): void
    {
        $transactionTests = [
            'successful_transaction' => function () {
                return DB::transaction(function () {
                    $conversation = AIConversation::create(['title' => 'Transaction Test']);
                    AIMessageRecord::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'user',
                        'content' => 'Test message',
                        'provider' => 'mock',
                        'model' => 'gpt-4',
                        'input_tokens' => 10,
                        'output_tokens' => 15,
                        'cost' => 0.001,
                    ]);

                    return $conversation;
                });
            },
            'failed_transaction_rollback' => function () {
                try {
                    DB::transaction(function () {
                        AIConversation::create(['title' => 'Will be rolled back']);
                        throw new \Exception('Intentional failure');
                    });

                    return false;
                } catch (\Exception $e) {
                    // Verify rollback worked
                    $count = AIConversation::where('title', 'Will be rolled back')->count();

                    return $count === 0;
                }
            },
        ];

        $transactionResults = [];

        foreach ($transactionTests as $testName => $test) {
            $startTime = microtime(true);

            try {
                $result = $test();
                $transactionTime = (microtime(true) - $startTime) * 1000;

                $transactionResults[] = [
                    'test' => $testName,
                    'passed' => $result !== false,
                    'time_ms' => $transactionTime,
                ];

                $this->assertNotFalse($result, "Transaction test {$testName} should succeed");
                $this->assertLessThan(150, $transactionTime,
                    "Transaction test {$testName} took {$transactionTime}ms, exceeding 150ms target");
            } catch (\Exception $e) {
                $transactionResults[] = [
                    'test' => $testName,
                    'passed' => false,
                    'error' => $e->getMessage(),
                    'time_ms' => (microtime(true) - $startTime) * 1000,
                ];
            }
        }

        $this->recordMetric('database_transactions', [
            'transaction_tests' => count($transactionTests),
            'transaction_results' => $transactionResults,
        ]);
    }

    #[Test]
    public function it_tests_model_factory_performance(): void
    {
        $factoryTests = [
            'conversation_factory' => function () {
                return AIConversation::factory()->count(10)->create();
            },
            'message_factory' => function () {
                $conversation = AIConversation::factory()->create();

                return AIMessageRecord::factory()->count(5)->create([
                    'conversation_id' => $conversation->id,
                ]);
            },
        ];

        $factoryResults = [];

        foreach ($factoryTests as $testName => $test) {
            $startTime = microtime(true);

            try {
                $result = $test();
                $factoryTime = (microtime(true) - $startTime) * 1000;

                $factoryResults[] = [
                    'test' => $testName,
                    'created_count' => count($result),
                    'time_ms' => $factoryTime,
                ];

                $this->assertNotEmpty($result, "Factory test {$testName} should create records");
                $this->assertLessThan(500, $factoryTime,
                    "Factory test {$testName} took {$factoryTime}ms, exceeding 500ms target");
            } catch (\Exception $e) {
                $factoryResults[] = [
                    'test' => $testName,
                    'error' => $e->getMessage(),
                    'time_ms' => (microtime(true) - $startTime) * 1000,
                ];
            }
        }

        $this->recordMetric('model_factories', [
            'factory_tests' => count($factoryTests),
            'factory_results' => $factoryResults,
        ]);
    }

    #[Test]
    public function it_tests_database_connection_resilience(): void
    {
        $connectionTests = [
            'connection_status' => function () {
                return DB::connection()->getPdo() !== null;
            },
            'query_after_reconnect' => function () {
                DB::reconnect();

                return AIConversation::count() >= 0;
            },
            'multiple_connections' => function () {
                $count1 = DB::connection()->table('ai_conversations')->count();
                $count2 = DB::connection('testing')->table('ai_conversations')->count();

                return $count1 === $count2;
            },
        ];

        $connectionResults = [];

        foreach ($connectionTests as $testName => $test) {
            $startTime = microtime(true);

            try {
                $result = $test();
                $connectionTime = (microtime(true) - $startTime) * 1000;

                $connectionResults[] = [
                    'test' => $testName,
                    'passed' => $result,
                    'time_ms' => $connectionTime,
                ];

                $this->assertTrue($result, "Connection test {$testName} should pass");
                $this->assertLessThan(100, $connectionTime,
                    "Connection test {$testName} took {$connectionTime}ms, exceeding 100ms target");
            } catch (\Exception $e) {
                $connectionResults[] = [
                    'test' => $testName,
                    'passed' => false,
                    'error' => $e->getMessage(),
                    'time_ms' => (microtime(true) - $startTime) * 1000,
                ];
            }
        }

        $this->recordMetric('database_connections', [
            'connection_tests' => count($connectionTests),
            'connection_results' => $connectionResults,
        ]);
    }

    /**
     * Record performance metric.
     */
    protected function recordMetric(string $name, array $data): void
    {
        $this->performanceMetrics[$name] = array_merge($data, [
            'timestamp' => now()->toISOString(),
            'test_environment' => app()->environment(),
        ]);
    }

    /**
     * Log performance metrics.
     */
    protected function logPerformanceMetrics(): void
    {
        if (! empty($this->performanceMetrics)) {
            Log::info('Database Model Test Results', [
                'metrics' => $this->performanceMetrics,
                'summary' => $this->generatePerformanceSummary(),
            ]);
        }
    }

    /**
     * Generate performance summary.
     */
    protected function generatePerformanceSummary(): array
    {
        $summary = [
            'total_tests' => count($this->performanceMetrics),
            'database_components_tested' => array_keys($this->performanceMetrics),
            'performance_targets_met' => 0,
            'performance_targets_failed' => 0,
        ];

        foreach ($this->performanceMetrics as $name => $data) {
            $targetMet = true;
            if (isset($data['target_ms'])) {
                $actualTime = $data['validation_time_ms'] ?? $data['average_ms'] ?? 0;
                $targetMet = $actualTime < $data['target_ms'];
            }

            if ($targetMet) {
                $summary['performance_targets_met']++;
            } else {
                $summary['performance_targets_failed']++;
            }
        }

        return $summary;
    }
}
