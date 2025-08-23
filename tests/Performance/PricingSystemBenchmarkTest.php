<?php

namespace Tests\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use JTD\LaravelAI\Enums\BillingModel;
use JTD\LaravelAI\Enums\PricingUnit;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Services\PricingValidator;
use Tests\TestCase;

class PricingSystemBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected PricingService $pricingService;

    protected array $benchmarkResults = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingService = app(PricingService::class);
        $this->setupBenchmarkData();
    }

    protected function tearDown(): void
    {
        $this->displayBenchmarkResults();
        parent::tearDown();
    }

    public function test_database_pricing_retrieval_performance()
    {
        $iterations = 1000;

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $iterations; $i++) {
            $this->pricingService->getModelPricing('openai', 'gpt-4o');
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $avgTime = $totalTime / $iterations;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB

        $this->benchmarkResults['database_retrieval'] = [
            'iterations' => $iterations,
            'total_time_ms' => round($totalTime, 2),
            'avg_time_ms' => round($avgTime, 4),
            'memory_used_mb' => round($memoryUsed, 2),
            'ops_per_second' => round($iterations / ($totalTime / 1000), 0),
        ];

        // Performance assertions
        $this->assertLessThan(5.0, $avgTime, 'Average database retrieval should be under 5ms');
        $this->assertLessThan(10.0, $memoryUsed, 'Memory usage should be under 10MB');
    }

    public function test_cached_pricing_retrieval_performance()
    {
        // Warm up cache
        $this->pricingService->getModelPricing('openai', 'gpt-4o');

        $iterations = 10000;

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $iterations; $i++) {
            $this->pricingService->getModelPricing('openai', 'gpt-4o');
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalTime = ($endTime - $startTime) * 1000;
        $avgTime = $totalTime / $iterations;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;

        $this->benchmarkResults['cached_retrieval'] = [
            'iterations' => $iterations,
            'total_time_ms' => round($totalTime, 2),
            'avg_time_ms' => round($avgTime, 4),
            'memory_used_mb' => round($memoryUsed, 2),
            'ops_per_second' => round($iterations / ($totalTime / 1000), 0),
        ];

        // Cached retrieval should be much faster
        $this->assertLessThan(1.0, $avgTime, 'Average cached retrieval should be under 1ms');
        $this->assertLessThan(5.0, $memoryUsed, 'Memory usage should be under 5MB');
    }

    public function test_cost_calculation_performance()
    {
        $iterations = 5000;

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $iterations; $i++) {
            $this->pricingService->calculateCost('openai', 'gpt-4o', 1000, 500);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalTime = ($endTime - $startTime) * 1000;
        $avgTime = $totalTime / $iterations;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;

        $this->benchmarkResults['cost_calculation'] = [
            'iterations' => $iterations,
            'total_time_ms' => round($totalTime, 2),
            'avg_time_ms' => round($avgTime, 4),
            'memory_used_mb' => round($memoryUsed, 2),
            'ops_per_second' => round($iterations / ($totalTime / 1000), 0),
        ];

        // Cost calculation should be fast
        $this->assertLessThan(2.0, $avgTime, 'Average cost calculation should be under 2ms');
        $this->assertLessThan(5.0, $memoryUsed, 'Memory usage should be under 5MB');
    }

    public function test_database_storage_performance()
    {
        $iterations = 100; // Fewer iterations for write operations

        $pricing = [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-01-01',
        ];

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $iterations; $i++) {
            $this->pricingService->storePricingToDatabase('test', "model-{$i}", $pricing);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalTime = ($endTime - $startTime) * 1000;
        $avgTime = $totalTime / $iterations;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;

        $this->benchmarkResults['database_storage'] = [
            'iterations' => $iterations,
            'total_time_ms' => round($totalTime, 2),
            'avg_time_ms' => round($avgTime, 4),
            'memory_used_mb' => round($memoryUsed, 2),
            'ops_per_second' => round($iterations / ($totalTime / 1000), 0),
        ];

        // Database storage should be reasonable
        $this->assertLessThan(50.0, $avgTime, 'Average database storage should be under 50ms');
        $this->assertLessThan(20.0, $memoryUsed, 'Memory usage should be under 20MB');
    }

    public function test_pricing_validation_performance()
    {
        $validator = app(PricingValidator::class);
        $iterations = 2000;

        $pricing = [
            'gpt-4o' => [
                'input' => 0.01,
                'output' => 0.03,
                'unit' => PricingUnit::PER_1K_TOKENS,
                'currency' => 'USD',
                'billing_model' => BillingModel::PAY_PER_USE,
                'effective_date' => '2024-01-01',
            ],
        ];

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $iterations; $i++) {
            $validator->validatePricingArray($pricing);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalTime = ($endTime - $startTime) * 1000;
        $avgTime = $totalTime / $iterations;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;

        $this->benchmarkResults['pricing_validation'] = [
            'iterations' => $iterations,
            'total_time_ms' => round($totalTime, 2),
            'avg_time_ms' => round($avgTime, 4),
            'memory_used_mb' => round($memoryUsed, 2),
            'ops_per_second' => round($iterations / ($totalTime / 1000), 0),
        ];

        // Validation should be fast
        $this->assertLessThan(1.0, $avgTime, 'Average validation should be under 1ms');
        $this->assertLessThan(5.0, $memoryUsed, 'Memory usage should be under 5MB');
    }

    public function test_concurrent_access_performance()
    {
        $iterations = 500;
        $concurrentRequests = 10;

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Simulate concurrent access
        $results = [];
        for ($i = 0; $i < $iterations; $i++) {
            for ($j = 0; $j < $concurrentRequests; $j++) {
                $results[] = $this->pricingService->getModelPricing('openai', 'gpt-4o');
            }
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalOperations = $iterations * $concurrentRequests;
        $totalTime = ($endTime - $startTime) * 1000;
        $avgTime = $totalTime / $totalOperations;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;

        $this->benchmarkResults['concurrent_access'] = [
            'iterations' => $totalOperations,
            'concurrent_requests' => $concurrentRequests,
            'total_time_ms' => round($totalTime, 2),
            'avg_time_ms' => round($avgTime, 4),
            'memory_used_mb' => round($memoryUsed, 2),
            'ops_per_second' => round($totalOperations / ($totalTime / 1000), 0),
        ];

        // Concurrent access should handle well
        $this->assertLessThan(10.0, $avgTime, 'Average concurrent access should be under 10ms');
        $this->assertLessThan(50.0, $memoryUsed, 'Memory usage should be under 50MB');

        // All results should be consistent
        $firstResult = $results[0];
        foreach ($results as $result) {
            $this->assertEquals($firstResult['input'], $result['input']);
            $this->assertEquals($firstResult['output'], $result['output']);
        }
    }

    public function test_large_dataset_performance()
    {
        // Create a large dataset
        $models = [];
        for ($i = 0; $i < 1000; $i++) {
            $models["model-{$i}"] = [
                'input' => 0.001 + ($i * 0.00001),
                'output' => 0.002 + ($i * 0.00002),
                'unit' => PricingUnit::PER_1K_TOKENS,
                'currency' => 'USD',
                'billing_model' => BillingModel::PAY_PER_USE,
            ];
        }

        // Store all models
        $startTime = microtime(true);
        foreach ($models as $model => $pricing) {
            $this->pricingService->storePricingToDatabase('benchmark', $model, $pricing);
        }
        $storageTime = (microtime(true) - $startTime) * 1000;

        // Retrieve all models
        $startTime = microtime(true);
        $retrievedCount = 0;
        foreach (array_keys($models) as $model) {
            $this->pricingService->getModelPricing('benchmark', $model);
            $retrievedCount++;
        }
        $retrievalTime = (microtime(true) - $startTime) * 1000;

        $this->benchmarkResults['large_dataset'] = [
            'model_count' => count($models),
            'storage_time_ms' => round($storageTime, 2),
            'retrieval_time_ms' => round($retrievalTime, 2),
            'avg_storage_ms' => round($storageTime / count($models), 4),
            'avg_retrieval_ms' => round($retrievalTime / count($models), 4),
        ];

        // Large dataset should handle reasonably
        $this->assertLessThan(100.0, $storageTime / count($models), 'Average storage for large dataset should be reasonable');
        $this->assertLessThan(10.0, $retrievalTime / count($models), 'Average retrieval for large dataset should be reasonable');
    }

    protected function setupBenchmarkData(): void
    {
        // Setup test data for benchmarks
        $pricing = [
            'input' => 0.0025,
            'output' => 0.01,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-01-01',
        ];

        $this->pricingService->storePricingToDatabase('openai', 'gpt-4o', $pricing);
    }

    protected function displayBenchmarkResults(): void
    {
        if (empty($this->benchmarkResults)) {
            return;
        }

        echo "\n\n" . str_repeat('=', 80) . "\n";
        echo "PRICING SYSTEM PERFORMANCE BENCHMARK RESULTS\n";
        echo str_repeat('=', 80) . "\n";

        foreach ($this->benchmarkResults as $test => $results) {
            echo "\n" . strtoupper(str_replace('_', ' ', $test)) . ":\n";
            echo str_repeat('-', 40) . "\n";

            foreach ($results as $metric => $value) {
                $label = ucwords(str_replace('_', ' ', $metric));
                echo sprintf("%-25s: %s\n", $label, $value);
            }
        }

        echo "\n" . str_repeat('=', 80) . "\n";
        echo "PERFORMANCE SUMMARY:\n";
        echo str_repeat('=', 80) . "\n";

        // Calculate overall performance metrics
        if (isset($this->benchmarkResults['database_retrieval'])) {
            $dbOps = $this->benchmarkResults['database_retrieval']['ops_per_second'];
            echo sprintf("Database Operations/sec: %s\n", number_format($dbOps));
        }

        if (isset($this->benchmarkResults['cached_retrieval'])) {
            $cacheOps = $this->benchmarkResults['cached_retrieval']['ops_per_second'];
            echo sprintf("Cached Operations/sec: %s\n", number_format($cacheOps));
        }

        if (isset($this->benchmarkResults['cost_calculation'])) {
            $calcOps = $this->benchmarkResults['cost_calculation']['ops_per_second'];
            echo sprintf("Cost Calculations/sec: %s\n", number_format($calcOps));
        }

        echo "\n" . str_repeat('=', 80) . "\n\n";
    }
}
