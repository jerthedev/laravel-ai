<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Events\BudgetThresholdReached;
use JTD\LaravelAI\Exceptions\BudgetExceededException;
use JTD\LaravelAI\Services\BudgetService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Budget Service Tests
 *
 * Comprehensive tests for budget management functionality with performance
 * benchmarks and threshold validation.
 */
#[Group('budget-management')]
class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BudgetService $budgetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->budgetService = app(BudgetService::class);
        $this->seedTestData();
    }

    #[Test]
    public function it_creates_budget_successfully(): void
    {
        $budgetData = [
            'user_id' => 1,
            'type' => 'monthly',
            'limit_amount' => 100.00,
            'currency' => 'USD',
            'warning_threshold' => 75.0,
            'critical_threshold' => 90.0,
            'is_active' => true,
        ];

        $budget = $this->budgetService->createBudget($budgetData);

        $this->assertIsArray($budget);
        $this->assertArrayHasKey('id', $budget);
        $this->assertEquals($budgetData['user_id'], $budget['user_id']);
        $this->assertEquals($budgetData['type'], $budget['type']);
        $this->assertEquals($budgetData['limit_amount'], $budget['limit_amount']);

        // Verify database record
        $this->assertDatabaseHas('ai_budgets', [
            'user_id' => $budgetData['user_id'],
            'type' => $budgetData['type'],
            'limit_amount' => $budgetData['limit_amount'],
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_budget_creation(): void
    {
        $budgetData = [
            'user_id' => 1,
            'type' => 'monthly',
            'limit_amount' => 100.00,
            'currency' => 'USD',
        ];

        // Create first budget
        $this->budgetService->createBudget($budgetData);

        // Attempt to create duplicate
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Budget already exists');
        
        $this->budgetService->createBudget($budgetData);
    }

    #[Test]
    public function it_updates_budget_successfully(): void
    {
        $budget = $this->createTestBudget();
        
        $updateData = [
            'limit_amount' => 200.00,
            'warning_threshold' => 80.0,
            'critical_threshold' => 95.0,
        ];

        $updatedBudget = $this->budgetService->updateBudget($budget['id'], $updateData);

        $this->assertEquals($updateData['limit_amount'], $updatedBudget['limit_amount']);
        $this->assertEquals($updateData['warning_threshold'], $updatedBudget['warning_threshold']);
        $this->assertEquals($updateData['critical_threshold'], $updatedBudget['critical_threshold']);
    }

    #[Test]
    public function it_gets_budget_status_accurately(): void
    {
        $budget = $this->createTestBudget();
        $userId = $budget['user_id'];
        $budgetType = $budget['type'];

        // Add some spending
        $this->addTestSpending($userId, 75.00);

        $status = $this->budgetService->getBudgetStatus($userId, $budgetType);

        $this->assertIsArray($status);
        $this->assertArrayHasKey('limit', $status);
        $this->assertArrayHasKey('spent', $status);
        $this->assertArrayHasKey('remaining', $status);
        $this->assertArrayHasKey('percentage_used', $status);

        $this->assertEquals(100.00, $status['limit']);
        $this->assertEquals(75.00, $status['spent']);
        $this->assertEquals(25.00, $status['remaining']);
        $this->assertEquals(75.0, $status['percentage_used']);
    }

    #[Test]
    public function it_enforces_budget_limits(): void
    {
        $budget = $this->createTestBudget();
        $userId = $budget['user_id'];
        $budgetType = $budget['type'];

        // Add spending up to limit
        $this->addTestSpending($userId, 100.00);

        // Attempt to exceed budget
        $this->expectException(BudgetExceededException::class);
        
        $this->budgetService->checkBudgetCompliance($userId, $budgetType, 10.00);
    }

    #[Test]
    public function it_fires_threshold_events(): void
    {
        Event::fake();

        $budget = $this->createTestBudget();
        $userId = $budget['user_id'];
        $budgetType = $budget['type'];

        // Add spending to trigger warning threshold (75%)
        $this->addTestSpending($userId, 75.00);
        
        $this->budgetService->checkBudgetCompliance($userId, $budgetType, 0.00);

        Event::assertDispatched(BudgetThresholdReached::class, function ($event) use ($userId, $budgetType) {
            return $event->userId === $userId 
                && $event->budgetType === $budgetType
                && $event->thresholdPercentage >= 75.0;
        });
    }

    #[Test]
    public function it_performs_budget_checks_within_performance_target(): void
    {
        $budget = $this->createTestBudget();
        $userId = $budget['user_id'];
        $budgetType = $budget['type'];

        $startTime = microtime(true);
        $this->budgetService->checkBudgetCompliance($userId, $budgetType, 10.00);
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Performance target: <10ms for budget checks
        $this->assertLessThan(10, $executionTime, 
            "Budget check took {$executionTime}ms, exceeding 10ms target");
    }

    #[Test]
    public function it_caches_budget_status(): void
    {
        $budget = $this->createTestBudget();
        $userId = $budget['user_id'];
        $budgetType = $budget['type'];

        // Clear cache
        Cache::flush();

        // First call should hit database
        $startTime = microtime(true);
        $firstResult = $this->budgetService->getBudgetStatus($userId, $budgetType);
        $firstCallTime = (microtime(true) - $startTime) * 1000;

        // Second call should hit cache
        $startTime = microtime(true);
        $secondResult = $this->budgetService->getBudgetStatus($userId, $budgetType);
        $secondCallTime = (microtime(true) - $startTime) * 1000;

        // Cache hit should be significantly faster
        $this->assertLessThan($firstCallTime / 2, $secondCallTime, 
            "Cached call should be at least 50% faster");

        // Results should be identical
        $this->assertEquals($firstResult, $secondResult);
    }

    #[Test]
    #[DataProvider('budgetTypeProvider')]
    public function it_handles_different_budget_types(string $budgetType): void
    {
        $budgetData = [
            'user_id' => 1,
            'type' => $budgetType,
            'limit_amount' => 100.00,
            'currency' => 'USD',
        ];

        $budget = $this->budgetService->createBudget($budgetData);

        $this->assertEquals($budgetType, $budget['type']);
        
        $status = $this->budgetService->getBudgetStatus($budget['user_id'], $budgetType);
        $this->assertIsArray($status);
    }

    #[Test]
    public function it_handles_budget_resets(): void
    {
        $budget = $this->createTestBudget(['type' => 'daily']);
        $userId = $budget['user_id'];

        // Add spending
        $this->addTestSpending($userId, 50.00);

        // Verify spending is recorded
        $status = $this->budgetService->getBudgetStatus($userId, 'daily');
        $this->assertEquals(50.00, $status['spent']);

        // Reset budget
        $resetResult = $this->budgetService->resetBudget($budget['id']);

        $this->assertTrue($resetResult);

        // Verify spending is reset
        $statusAfterReset = $this->budgetService->getBudgetStatus($userId, 'daily');
        $this->assertEquals(0.00, $statusAfterReset['spent']);
    }

    #[Test]
    public function it_calculates_budget_projections(): void
    {
        $budget = $this->createTestBudget();
        $userId = $budget['user_id'];
        $budgetType = $budget['type'];

        // Add historical spending data
        $this->addHistoricalSpending($userId, 30); // 30 days of data

        $projections = $this->budgetService->getBudgetProjections($userId, $budgetType, 30);

        $this->assertIsArray($projections);
        $this->assertArrayHasKey('projected_spending', $projections);
        $this->assertArrayHasKey('days_remaining', $projections);
        $this->assertArrayHasKey('projected_overage', $projections);
        $this->assertArrayHasKey('confidence_level', $projections);

        // Projections should be reasonable
        $this->assertGreaterThanOrEqual(0, $projections['projected_spending']);
        $this->assertGreaterThanOrEqual(0, $projections['days_remaining']);
    }

    #[Test]
    public function it_handles_concurrent_budget_operations(): void
    {
        $budget = $this->createTestBudget();
        $userId = $budget['user_id'];
        $budgetType = $budget['type'];

        // Simulate concurrent budget checks
        $promises = [];
        for ($i = 0; $i < 5; $i++) {
            $promises[] = function () use ($userId, $budgetType) {
                return $this->budgetService->getBudgetStatus($userId, $budgetType);
            };
        }

        // Execute all promises
        $results = array_map(fn($promise) => $promise(), $promises);

        // All results should be consistent
        $firstResult = $results[0];
        foreach ($results as $result) {
            $this->assertEquals($firstResult['limit'], $result['limit']);
            $this->assertEquals($firstResult['spent'], $result['spent']);
        }
    }

    #[Test]
    public function it_validates_budget_hierarchy(): void
    {
        // Create monthly budget
        $monthlyBudget = $this->createTestBudget([
            'type' => 'monthly',
            'limit_amount' => 1000.00,
        ]);

        // Create daily budget that would exceed monthly when multiplied
        $dailyBudgetData = [
            'user_id' => $monthlyBudget['user_id'],
            'type' => 'daily',
            'limit_amount' => 50.00, // 50 * 31 = 1550, exceeds monthly
            'currency' => 'USD',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Daily budget would exceed monthly budget');
        
        $this->budgetService->createBudget($dailyBudgetData);
    }

    #[Test]
    public function it_generates_budget_recommendations(): void
    {
        $budget = $this->createTestBudget();
        $userId = $budget['user_id'];

        // Add spending pattern data
        $this->addHistoricalSpending($userId, 30);

        $recommendations = $this->budgetService->getBudgetRecommendations($userId);

        $this->assertIsArray($recommendations);
        $this->assertArrayHasKey('recommendations', $recommendations);
        $this->assertArrayHasKey('optimization_opportunities', $recommendations);
        $this->assertArrayHasKey('suggested_limits', $recommendations);

        foreach ($recommendations['recommendations'] as $recommendation) {
            $this->assertArrayHasKey('type', $recommendation);
            $this->assertArrayHasKey('message', $recommendation);
            $this->assertArrayHasKey('priority', $recommendation);
        }
    }

    /**
     * Data provider for budget types.
     */
    public static function budgetTypeProvider(): array
    {
        return [
            ['daily'],
            ['monthly'],
            ['per_request'],
            ['project'],
            ['organization'],
        ];
    }

    /**
     * Create a test budget.
     */
    protected function createTestBudget(array $overrides = []): array
    {
        $budgetData = array_merge([
            'user_id' => 1,
            'type' => 'monthly',
            'limit_amount' => 100.00,
            'currency' => 'USD',
            'warning_threshold' => 75.0,
            'critical_threshold' => 90.0,
            'is_active' => true,
        ], $overrides);

        return $this->budgetService->createBudget($budgetData);
    }

    /**
     * Add test spending data.
     */
    protected function addTestSpending(int $userId, float $amount): void
    {
        DB::table('ai_usage_costs')->insert([
            'user_id' => $userId,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'total_cost' => $amount,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Add historical spending data.
     */
    protected function addHistoricalSpending(int $userId, int $days): void
    {
        for ($i = 0; $i < $days; $i++) {
            DB::table('ai_usage_costs')->insert([
                'user_id' => $userId,
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'total_cost' => rand(1, 10),
                'currency' => 'USD',
                'created_at' => now()->subDays($i),
                'updated_at' => now()->subDays($i),
            ]);
        }
    }

    /**
     * Seed test data.
     */
    protected function seedTestData(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
