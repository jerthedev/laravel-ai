<?php

namespace JTD\LaravelAI\Tests\Unit\Middleware;

use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Exceptions\BudgetExceededException;
use JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Services\BudgetService;
use JTD\LaravelAI\Services\MiddlewareManager;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;

class MiddlewareSystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable middleware for testing
        config(['ai.middleware.enabled' => true]);
        config(['ai.middleware.global.budget_enforcement.enabled' => true]);
    }

    public function test_middleware_manager_registration()
    {
        $manager = new MiddlewareManager();

        // Test middleware registration
        $manager->register('test', TestMiddleware::class);

        $registeredMiddleware = $manager->getRegisteredMiddleware();
        $this->assertArrayHasKey('test', $registeredMiddleware);
        $this->assertEquals(TestMiddleware::class, $registeredMiddleware['test']);
    }

    public function test_middleware_manager_global_middleware()
    {
        $manager = new MiddlewareManager();

        // Test global middleware registration
        $manager->registerGlobal('test');

        $globalMiddleware = $manager->getGlobalMiddleware();
        $this->assertContains('test', $globalMiddleware);
    }

    public function test_middleware_stack_execution()
    {
        // Mock the AI manager to return a test response
        $mockAIManager = Mockery::mock();
        $mockAIManager->shouldReceive('send')
            ->once()
            ->with('Test message')
            ->andReturn(AIResponse::fromArray([
                'content' => 'Test response',
                'provider' => 'test',
                'model' => 'test-model',
            ]));

        $this->app->instance('laravel-ai', $mockAIManager);

        $manager = new MiddlewareManager();
        $manager->register('test', TestMiddleware::class);

        $message = AIMessage::user('Test message');
        $message->user_id = 1;

        // Process through middleware
        $response = $manager->process($message, ['test']);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Test response', $response->content);
    }

    public function test_budget_enforcement_middleware_creation()
    {
        $budgetService = Mockery::mock(BudgetService::class);
        $pricingService = Mockery::mock(PricingService::class);

        $middleware = new BudgetEnforcementMiddleware($budgetService, $pricingService);

        $this->assertInstanceOf(AIMiddlewareInterface::class, $middleware);
        $this->assertInstanceOf(BudgetEnforcementMiddleware::class, $middleware);
    }

    public function test_budget_enforcement_middleware_allows_within_budget()
    {
        $budgetService = Mockery::mock(BudgetService::class);
        $pricingService = Mockery::mock(PricingService::class);

        // Mock budget check to pass
        $budgetService->shouldReceive('checkBudgetLimits')
            ->once()
            ->andReturn(null); // No exception means within budget

        // Mock pricing estimation
        $pricingService->shouldReceive('calculateCost')
            ->once()
            ->andReturn(['total_cost' => 0.001]);

        $middleware = new BudgetEnforcementMiddleware($budgetService, $pricingService);

        $message = AIMessage::user('Test message');
        $message->user_id = 1;
        $message->provider = 'openai';
        $message->model = 'gpt-4o-mini';

        $next = function ($message) {
            return AIResponse::fromArray([
                'content' => 'Test response',
                'provider' => 'openai',
                'model' => 'test-model',
            ]);
        };

        $response = $middleware->handle($message, $next);

        $this->assertInstanceOf(AIResponse::class, $response);
    }

    public function test_budget_enforcement_middleware_blocks_over_budget()
    {
        $budgetService = Mockery::mock(BudgetService::class);
        $pricingService = Mockery::mock(PricingService::class);

        // Mock budget check to fail
        $budgetService->shouldReceive('checkBudgetLimits')
            ->once()
            ->andThrow(new BudgetExceededException('Daily budget exceeded'));

        // Mock pricing estimation
        $pricingService->shouldReceive('calculateCost')
            ->once()
            ->andReturn(['total_cost' => 10.0]); // High cost

        $middleware = new BudgetEnforcementMiddleware($budgetService, $pricingService);

        $message = AIMessage::user('Test message');
        $message->user_id = 1;
        $message->provider = 'openai';
        $message->model = 'gpt-4o-mini';

        $next = function ($message) {
            return AIResponse::fromArray([
                'content' => 'Test response',
                'provider' => 'openai',
                'model' => 'test-model',
            ]);
        };

        $this->expectException(BudgetExceededException::class);
        $this->expectExceptionMessage('Daily budget exceeded');

        $middleware->handle($message, $next);
    }

    public function test_middleware_performance_tracking()
    {
        $manager = new MiddlewareManager();
        $manager->register('slow', SlowTestMiddleware::class);

        $message = AIMessage::user('Test message');
        $message->user_id = 1;

        $startTime = microtime(true);
        $response = $manager->process($message, ['slow']);
        $executionTime = microtime(true) - $startTime;

        // Should complete but track performance
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertArrayHasKey('middleware_applied', $message->metadata);
    }

    public function test_middleware_error_handling()
    {
        $manager = new MiddlewareManager();
        $manager->register('error', ErrorTestMiddleware::class);

        $message = AIMessage::user('Test message');
        $message->user_id = 1;

        // Should handle middleware errors gracefully
        $response = $manager->process($message, ['error']);

        $this->assertInstanceOf(AIResponse::class, $response);
    }

    public function test_middleware_chain_order()
    {
        // Mock the AI manager
        $mockAIManager = Mockery::mock();
        $mockAIManager->shouldReceive('send')
            ->once()
            ->with('Test message')
            ->andReturn(AIResponse::fromArray([
                'content' => 'Test response',
                'provider' => 'test',
                'model' => 'test-model',
            ]));

        $this->app->instance('laravel-ai', $mockAIManager);

        $manager = new MiddlewareManager();
        $manager->register('first', FirstTestMiddleware::class);
        $manager->register('second', SecondTestMiddleware::class);

        $message = AIMessage::user('Test message');
        $message->user_id = 1;
        $message->metadata = [];

        $response = $manager->process($message, ['first', 'second']);

        // Check that both middleware were applied in the correct order
        $this->assertArrayHasKey('test_middleware_order', $message->metadata);
        $testOrder = $message->metadata['test_middleware_order'];

        // The middleware should execute in the order they appear in the array
        $this->assertCount(2, $testOrder);
        $this->assertEquals('first', $testOrder[0], 'First middleware should execute first');
        $this->assertEquals('second', $testOrder[1], 'Second middleware should execute second');
    }

    public function test_budget_enforcement_cost_estimation()
    {
        $budgetService = Mockery::mock(BudgetService::class);
        $pricingService = Mockery::mock(PricingService::class);

        $budgetService->shouldReceive('checkBudgetLimits')->once();

        // Test cost estimation with different providers
        $pricingService->shouldReceive('calculateCost')
            ->with('openai', 'gpt-4o-mini', Mockery::type('int'), Mockery::type('int'))
            ->once()
            ->andReturn(['total_cost' => 0.001]);

        $middleware = new BudgetEnforcementMiddleware($budgetService, $pricingService);

        $message = AIMessage::user('Test message with some content to estimate tokens');
        $message->user_id = 1;
        $message->provider = 'openai';
        $message->model = 'gpt-4o-mini';

        $next = function ($message) {
            return AIResponse::fromArray(['content' => 'Response', 'provider' => 'test', 'model' => 'test']);
        };

        $response = $middleware->handle($message, $next);
        $this->assertInstanceOf(AIResponse::class, $response);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

// Test middleware classes
class TestMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $message->metadata['middleware_applied'] = $message->metadata['middleware_applied'] ?? [];
        $message->metadata['middleware_applied'][] = 'test';

        return $next($message);
    }
}

class SlowTestMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        usleep(50000); // 50ms delay

        $message->metadata['middleware_applied'] = $message->metadata['middleware_applied'] ?? [];
        $message->metadata['middleware_applied'][] = 'slow';

        return $next($message);
    }
}

class ErrorTestMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        throw new \Exception('Test middleware error');
    }
}

class FirstTestMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $message->metadata['test_middleware_order'] = $message->metadata['test_middleware_order'] ?? [];
        $message->metadata['test_middleware_order'][] = 'first';

        return $next($message);
    }
}

class SecondTestMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $message->metadata['test_middleware_order'] = $message->metadata['test_middleware_order'] ?? [];
        $message->metadata['test_middleware_order'][] = 'second';

        return $next($message);
    }
}
