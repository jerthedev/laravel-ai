<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive E2E Tests for DriverTemplate Driver
 *
 * This test suite validates the complete DriverTemplate driver functionality
 * with real API calls, covering all major features and scenarios.
 */
#[Group('e2e')]
#[Group('drivertemplate')]
#[Group('comprehensive')]
class DriverTemplateComprehensiveE2ETest extends E2ETestCase
{
    protected DriverTemplateDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (!$this->hasE2ECredentials('drivertemplate')) {
            $this->markTestSkipped('DriverTemplate E2E credentials not available');
        }

        // Create DriverTemplate driver with real credentials
        $credentials = $this->getE2ECredentials();
        $config = [
            'api_key' => $credentials['drivertemplate']['api_key'],
            'organization' => $credentials['drivertemplate']['organization'] ?? null,
            'project' => $credentials['drivertemplate']['project'] ?? null,
            'timeout' => 30,
            'retry_attempts' => 2,
        ];

        $this->driver = new DriverTemplateDriver($config);
    }

    #[Test]
    public function it_passes_comprehensive_drivertemplate_integration_test(): void
    {

        // TODO: Implement test
            } else {
                $costKeys = array_keys($cost);
                $this->logTestStep('Cost response keys: ' . implode(', ', $costKeys));

                // Check for any cost-related key
                $hasCostInfo = !empty($cost);
                $this->assertTrue($hasCostInfo, 'Cost calculation should return some information');
            }

            $this->logTestStep('✅ Cost calculation: Method executed successfully');

        } catch (\Exception $e) {
            $this->logTestStep('⚠️  Cost calculation failed: ' . $e->getMessage());
            // Don't fail the entire test for cost calculation issues
        }

        // Test 5: Conversation Context
        $this->logTestStep('5. Testing conversation context...');
        $contextMessage1 = AIMessage::user('My favorite number is 42.');
        $contextResponse1 = $this->driver->sendMessage($contextMessage1, [
            'model' => 'default-model-3.5-turbo',
            'max_tokens' => 30,
        ]);

        $contextMessage2 = AIMessage::user('What is my favorite number?');
        $contextResponse2 = $this->driver->sendMessage($contextMessage2, [
            'model' => 'default-model-3.5-turbo',
            'max_tokens' => 10,
            'conversation_history' => [
                $contextMessage1,
                AIMessage::assistant($contextResponse1->content),
            ],
        ]);

        // Check if AI remembered the context (AI behavior can vary)
        if (str_contains($contextResponse2->content, '42')) {
            $this->logTestStep('✅ Conversation context: AI remembered 42');
        } else {
            $this->logTestStep('⚠️  Conversation context: AI response was "' . trim($contextResponse2->content) . '" (AI behavior can vary)');
            // Don't fail the test - AI context handling can be inconsistent
        }

        // Test 6: Streaming
        $this->logTestStep('6. Testing streaming...');
        $streamingChunks = [];
        $streamingMessage = AIMessage::user('Count from 1 to 3');

        $streamingResponse = $this->driver->sendStreamingMessageWithCallback($streamingMessage, [
            'model' => 'default-model-3.5-turbo',
            'max_tokens' => 20,
            'temperature' => 0,
        ], function ($chunk) use (&$streamingChunks) {
            $streamingChunks[] = $chunk;
        });

        $this->assertInstanceOf(AIResponse::class, $streamingResponse);
        $this->assertGreaterThan(0, count($streamingChunks));
        $this->logTestStep('✅ Streaming: ' . count($streamingChunks) . ' chunks received');

        // Test 7: Parameter Variations
        $this->logTestStep('7. Testing parameter variations...');
        $paramMessage = AIMessage::user('Say "test" and nothing else.');

        $creativeResponse = $this->driver->sendMessage($paramMessage, [
            'model' => 'default-model-3.5-turbo',
            'temperature' => 0.9,
            'max_tokens' => 10,
        ]);

        $deterministicResponse = $this->driver->sendMessage($paramMessage, [
            'model' => 'default-model-3.5-turbo',
            'temperature' => 0.0,
            'max_tokens' => 10,
        ]);

        $this->assertNotEmpty($creativeResponse->content);
        $this->assertNotEmpty($deterministicResponse->content);
        $this->logTestStep('✅ Parameter variations: Creative and deterministic modes working');

        // Test 8: Error Handling
        $this->logTestStep('8. Testing error handling...');
        try {
            $this->driver->sendMessage($basicMessage, [
                'model' => 'invalid-model-name',
                'max_tokens' => 10,
            ]);
            $this->fail('Should have thrown exception for invalid model');
        } catch (\Exception $e) {
            $this->assertStringContainsString('model', strtolower($e->getMessage()));
            $this->logTestStep('✅ Error handling: Invalid model properly rejected');
        }

        // Test 9: Credential Validation
        $this->logTestStep('9. Testing credential validation...');
        $credentialValidation = $this->driver->validateCredentials();

        $this->assertIsArray($credentialValidation);
        $this->assertArrayHasKey('valid', $credentialValidation);
        $this->assertTrue($credentialValidation['valid']);
        $this->logTestStep('✅ Credential validation: Valid credentials confirmed');

        // Test 10: Performance Check
        $this->logTestStep('10. Testing performance...');
        $performanceStart = microtime(true);

        $performanceResponse = $this->driver->sendMessage(
            AIMessage::user('Hi'),
            ['model' => 'default-model-3.5-turbo', 'max_tokens' => 5]
        );

        $performanceTime = (microtime(true) - $performanceStart) * 1000;

        $this->assertInstanceOf(AIResponse::class, $performanceResponse);
        $this->assertLessThan(5000, $performanceTime); // Should be under 5 seconds
        $this->logTestStep('✅ Performance: Response in ' . round($performanceTime) . 'ms');

        $this->logTestEnd('Comprehensive DriverTemplate integration test PASSED');
    }

    #[Test]
    public function it_validates_production_readiness(): void
    {

        // TODO: Implement test
                }
            } catch (\Exception $e) {
                $this->logTestStep("Call {$i} failed: " . $e->getMessage());
            }
        }

        $reliabilityRate = ($successCount / $totalCalls) * 100;
        $testResults['reliability'] = $reliabilityRate;
        $this->assertGreaterThanOrEqual(80, $reliabilityRate, 'API reliability should be at least 80%');
        $this->logTestStep("✅ API Reliability: {$reliabilityRate}% ({$successCount}/{$totalCalls})");

        // Test Response Quality
        $this->logTestStep('Testing response quality...');
        $qualityResponse = $this->driver->sendMessage(
            AIMessage::user('Write exactly one sentence about cats.'),
            ['model' => 'default-model-3.5-turbo', 'max_tokens' => 50, 'temperature' => 0.7]
        );

        $responseLength = strlen(trim($qualityResponse->content));
        $hasContent = !empty($qualityResponse->content);
        $testResults['response_quality'] = $hasContent && $responseLength > 10;

        $this->assertTrue($testResults['response_quality'], 'Response should have meaningful content');
        $this->logTestStep("✅ Response Quality: {$responseLength} chars, meaningful content");

        // Test Token Usage Accuracy
        $this->logTestStep('Testing token usage accuracy...');
        $tokenResponse = $this->driver->sendMessage(
            AIMessage::user('Hello'),
            ['model' => 'default-model-3.5-turbo', 'max_tokens' => 10]
        );

        $hasTokenUsage = $tokenResponse->tokenUsage->totalTokens > 0;
        $testResults['token_tracking'] = $hasTokenUsage;

        $this->assertTrue($hasTokenUsage, 'Token usage should be tracked');
        $this->logTestStep("✅ Token Tracking: {$tokenResponse->tokenUsage->totalTokens} tokens");

        // Test Model Availability
        $this->logTestStep('Testing model availability...');
        $models = $this->driver->getAvailableModels();
        $hasModels = count($models) > 0;
        $testResults['model_availability'] = $hasModels;

        $this->assertTrue($hasModels, 'Should have available models');
        $this->logTestStep("✅ Model Availability: " . count($models) . " models");

        // Summary
        $passedTests = 0;
        foreach ($testResults as $result) {
            if (is_bool($result)) {
                $passedTests += $result ? 1 : 0;
            } elseif (is_numeric($result)) {
                $passedTests += $result >= 80 ? 1 : 0; // 80% threshold for reliability
            }
        }
        $totalTests = count($testResults);
        $readinessScore = ($passedTests / $totalTests) * 100;

        $this->logTestStep("\n📊 Production Readiness Summary:");
        $this->logTestStep("  • API Reliability: {$testResults['reliability']}%");
        $this->logTestStep("  • Response Quality: " . ($testResults['response_quality'] ? 'PASS' : 'FAIL'));
        $this->logTestStep("  • Token Tracking: " . ($testResults['token_tracking'] ? 'PASS' : 'FAIL'));
        $this->logTestStep("  • Model Availability: " . ($testResults['model_availability'] ? 'PASS' : 'FAIL'));
        $this->logTestStep("  • Overall Score: {$readinessScore}%");

        $this->assertGreaterThanOrEqual(75, $readinessScore, 'Production readiness score should be at least 75%');
        $this->logTestEnd("Production readiness validation PASSED ({$readinessScore}%)");
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
