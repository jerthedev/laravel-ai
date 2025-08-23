<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\XAI\XAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive E2E Test for xAI Driver
 *
 * This test suite covers all major functionality of the xAI driver
 * with real API calls to ensure complete integration works correctly.
 */
#[Group('e2e')]
#[Group('xai')]
#[Group('comprehensive')]
class XAIComprehensiveE2ETest extends E2ETestCase
{
    protected XAIDriver $driver;

    protected array $testResults = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (! $this->hasE2ECredentials('xai')) {
            $this->markTestSkipped('xAI E2E credentials not available');
        }

        // Create xAI driver with real credentials
        $credentials = $this->getE2ECredentials();
        $config = [
            'api_key' => $credentials['xai']['api_key'],
            'base_url' => $credentials['xai']['base_url'] ?? 'https://api.x.ai/v1',
            'timeout' => 60,
            'retry_attempts' => 2,
        ];

        $this->driver = new XAIDriver($config);
    }

    #[Test]
    public function it_performs_comprehensive_driver_test(): void
    {
        $this->logTestStart('🚀 Starting comprehensive xAI driver test suite');

        // Test 1: Basic functionality
        $this->testBasicFunctionality();

        // Test 2: Model management
        $this->testModelManagement();

        // Test 3: Cost calculation
        $this->testCostCalculation();

        // Test 4: Health and validation
        $this->testHealthAndValidation();

        // Test 5: Different message types
        $this->testDifferentMessageTypes();

        // Test 6: Error handling
        $this->testErrorHandling();

        // Test 7: Performance characteristics
        $this->testPerformanceCharacteristics();

        // Generate summary report
        $this->generateTestSummary();

        $this->logTestEnd('✅ Comprehensive test suite completed successfully');
    }

    protected function test_basic_functionality(): void
    {
        $this->logTestStep('📋 Testing basic functionality...');

        try {
            $message = AIMessage::user('Say "Hello from xAI" and nothing else.');
            $response = $this->driver->sendMessage($message, [
                'model' => 'grok-3-mini',
                'max_tokens' => 20,
                'temperature' => 0,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content);
            $this->assertEquals('xai', $response->provider);

            $this->testResults['basic_functionality'] = [
                'status' => 'passed',
                'response_length' => strlen($response->content),
                'model_used' => $response->model,
                'response_time' => $response->responseTimeMs,
            ];

            $this->logTestStep('✅ Basic functionality test passed');
        } catch (\Exception $e) {
            $this->testResults['basic_functionality'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $this->logTestStep('❌ Basic functionality test failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function test_model_management(): void
    {
        $this->logTestStep('📋 Testing model management...');

        try {
            $models = $this->driver->getAvailableModels();
            $this->assertIsArray($models);
            $this->assertGreaterThan(0, count($models));

            $defaultModel = $this->driver->getDefaultModel();
            $this->assertNotEmpty($defaultModel);

            $modelInfo = $this->driver->getModelInfo($defaultModel);
            $this->assertIsArray($modelInfo);
            $this->assertArrayHasKey('id', $modelInfo);

            $this->testResults['model_management'] = [
                'status' => 'passed',
                'available_models' => count($models),
                'default_model' => $defaultModel,
                'model_info_available' => ! empty($modelInfo),
            ];

            $this->logTestStep('✅ Model management test passed');
        } catch (\Exception $e) {
            $this->testResults['model_management'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $this->logTestStep('❌ Model management test failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function test_cost_calculation(): void
    {
        $this->logTestStep('📋 Testing cost calculation...');

        try {
            // Test with string input
            $costData = $this->driver->calculateCost('This is a test message for cost calculation.', 'grok-3-mini');
            $this->assertIsArray($costData);
            $this->assertArrayHasKey('total_cost', $costData);
            $this->assertGreaterThan(0, $costData['total_cost']);

            // Test with TokenUsage
            $tokenUsage = new TokenUsage(100, 50, 150);
            $costData2 = $this->driver->calculateCost($tokenUsage, 'grok-3-mini');
            $this->assertIsArray($costData2);
            $this->assertArrayHasKey('total_cost', $costData2);

            $this->testResults['cost_calculation'] = [
                'status' => 'passed',
                'string_cost' => $costData['total_cost'],
                'token_usage_cost' => $costData2['total_cost'],
                'pricing_available' => $costData['pricing_available'] ?? false,
            ];

            $this->logTestStep('✅ Cost calculation test passed');
        } catch (\Exception $e) {
            $this->testResults['cost_calculation'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $this->logTestStep('❌ Cost calculation test failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function test_health_and_validation(): void
    {
        $this->logTestStep('📋 Testing health and validation...');

        try {
            $validation = $this->driver->validateCredentials();
            $this->assertIsArray($validation);
            $this->assertArrayHasKey('valid', $validation);

            $health = $this->driver->healthCheck();
            $this->assertIsArray($health);
            $this->assertArrayHasKey('status', $health);

            $this->testResults['health_validation'] = [
                'status' => 'passed',
                'credentials_valid' => $validation['valid'],
                'health_status' => $health['status'],
                'api_connectivity' => $validation['api_connectivity'] ?? false,
            ];

            $this->logTestStep('✅ Health and validation test passed');
        } catch (\Exception $e) {
            $this->testResults['health_validation'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $this->logTestStep('❌ Health and validation test failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function test_different_message_types(): void
    {
        $this->logTestStep('📋 Testing different message types...');

        try {
            // Test conversation with system message
            $messages = [
                AIMessage::system('You are a helpful math tutor.'),
                AIMessage::user('What is 5 + 3?'),
            ];

            $response = $this->driver->sendMessage($messages, [
                'model' => 'grok-3-mini',
                'max_tokens' => 50,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content);

            // Test single user message
            $singleMessage = AIMessage::user('Tell me a fun fact about space.');
            $response2 = $this->driver->sendMessage($singleMessage, [
                'model' => 'grok-3-mini',
                'max_tokens' => 100,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response2);

            $this->testResults['message_types'] = [
                'status' => 'passed',
                'conversation_response_length' => strlen($response->content),
                'single_message_response_length' => strlen($response2->content),
            ];

            $this->logTestStep('✅ Different message types test passed');
        } catch (\Exception $e) {
            $this->testResults['message_types'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $this->logTestStep('❌ Different message types test failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function test_error_handling(): void
    {
        $this->logTestStep('📋 Testing error handling...');

        try {
            // Test with invalid model (should throw exception)
            $exceptionThrown = false;
            try {
                $this->driver->sendMessage(AIMessage::user('Test'), [
                    'model' => 'invalid-model-name',
                    'max_tokens' => 10,
                ]);
            } catch (\Exception $e) {
                $exceptionThrown = true;
                $this->logTestStep('✅ Exception thrown for invalid model as expected');
            }

            $this->assertTrue($exceptionThrown, 'Should throw exception for invalid model');

            $this->testResults['error_handling'] = [
                'status' => 'passed',
                'invalid_model_handled' => $exceptionThrown,
            ];

            $this->logTestStep('✅ Error handling test passed');
        } catch (\Exception $e) {
            $this->testResults['error_handling'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $this->logTestStep('❌ Error handling test failed: ' . $e->getMessage());
            // Don't re-throw this one as it's testing error conditions
        }
    }

    protected function test_performance_characteristics(): void
    {
        $this->logTestStep('📋 Testing performance characteristics...');

        try {
            $startTime = microtime(true);

            $response = $this->driver->sendMessage(AIMessage::user('Count from 1 to 3.'), [
                'model' => 'grok-3-mini',
                'max_tokens' => 50,
            ]);

            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->assertLessThan(30000, $responseTime, 'Response should be reasonably fast');
            $this->assertGreaterThan(0, $response->responseTimeMs);

            $this->testResults['performance'] = [
                'status' => 'passed',
                'measured_response_time' => $responseTime,
                'reported_response_time' => $response->responseTimeMs,
            ];

            $this->logTestStep('✅ Performance test passed');
        } catch (\Exception $e) {
            $this->testResults['performance'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $this->logTestStep('❌ Performance test failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function generateTestSummary(): void
    {
        $this->logTestStep('📊 Generating test summary...');

        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults, fn ($result) => $result['status'] === 'passed'));
        $failedTests = $totalTests - $passedTests;

        $this->logTestStep('📈 Test Results Summary:');
        $this->logTestStep("  Total tests: {$totalTests}");
        $this->logTestStep("  Passed: {$passedTests}");
        $this->logTestStep("  Failed: {$failedTests}");
        $this->logTestStep('  Success rate: ' . round(($passedTests / $totalTests) * 100, 1) . '%');

        foreach ($this->testResults as $testName => $result) {
            $status = $result['status'] === 'passed' ? '✅' : '❌';
            $this->logTestStep("  {$status} {$testName}");
        }

        // Assert overall success
        $this->assertEquals($totalTests, $passedTests, 'All tests should pass');
    }

    /**
     * Log test step for better visibility.
     */
    protected function logTestStep(string $message): void
    {
        echo "\n  " . $message;
    }

    /**
     * Log test start.
     */
    protected function logTestStart(string $testName): void
    {
        echo "\n🧪 " . $testName;
    }

    /**
     * Log test end.
     */
    protected function logTestEnd(string $message): void
    {
        echo "\n✅ " . $message . "\n";
    }
}
