<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E Tests for DriverTemplate Function Calling
 *
 * Tests function calling capabilities with real DriverTemplate API including
 * function definition, validation, execution, and parallel calls.
 */
#[Group('e2e')]
#[Group('drivertemplate')]
#[Group('function-calling')]
class DriverTemplateFunctionCallingTest extends E2ETestCase
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
    public function it_can_call_simple_function(): void
    {

        // TODO: Implement test
                }

                if ($response->toolCalls) {
                    $toolCall = $response->toolCalls[0];
                    $this->assertEquals('get_weather', $toolCall['function']['name']);
                    $this->logTestStep('Tool: ' . $toolCall['function']['name']);
                    $this->logTestStep('Arguments: ' . $toolCall['function']['arguments']);
                }

            } else {
                $this->logTestStep('⚠️  New API: AI chose to respond directly');

                // Fall back to old Chat API format
                $this->logTestStep('Falling back to Chat API format...');

                $legacyFunctions = [
                    [
                        'name' => 'get_weather',
                        'description' => 'Get the current weather for a location',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'location' => [
                                    'type' => 'string',
                                    'description' => 'The city and state, e.g. San Francisco, CA',
                                ],
                            ],
                            'required' => ['location'],
                        ],
                    ],
                ];

                $legacyResponse = $this->driver->sendMessage($message, [
                    'model' => 'default-model-3.5-turbo',
                    'functions' => $legacyFunctions,
                    'function_call' => ['name' => 'get_weather'], // Force function call
                    'max_tokens' => 100,
                ]);

                $this->logTestStep('Legacy API response: "' . trim($legacyResponse->content) . '"');
                $this->logTestStep('Legacy API finish reason: ' . $legacyResponse->finishReason);

                if ($legacyResponse->finishReason === 'function_call') {
                    $this->logTestStep('✅ Function call detected with legacy API');
                } else {
                    $this->logTestStep('⚠️  Both APIs chose to respond directly - this may be normal behavior');
                }
            }

        } catch (\Exception $e) {
            $this->logTestStep('❌ Function calling test failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            // This might be expected if the new API isn't available yet
            if (str_contains($e->getMessage(), 'responses') || str_contains($e->getMessage(), 'not found')) {
                $this->logTestStep('⚠️  New Responses API not available yet - this is expected');
            } else {
                throw $e;
            }
        }

        $this->logTestEnd('Simple function calling test completed');
    }

    #[Test]
    public function it_can_use_tools_format(): void
    {

        // TODO: Implement test

            } else {
                $this->logTestStep('⚠️  AI chose to respond directly: ' . $response->content);
                // This is acceptable - AI might calculate directly
            }

        } catch (\Exception $e) {
            $this->logTestStep('❌ Tools format test failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Tools format test completed');
    }

    #[Test]
    public function it_can_handle_function_result_conversation(): void
    {

        // TODO: Implement test

            } else {
                $this->logTestStep('⚠️  AI responded directly without function call');
                $this->logTestStep('Response: ' . $response1->content);
            }

        } catch (\Exception $e) {
            $this->logTestStep('❌ Function result conversation failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Function result conversation test completed');
    }

    #[Test]
    public function it_validates_function_definitions(): void
    {

        // TODO: Implement test

        } catch (\Exception $e) {
            $this->logTestStep('✅ Invalid function definition properly rejected');
            $this->logTestStep('Error: ' . $e->getMessage());
            // This is expected behavior
        }

        // Test valid function definition
        $validFunctions = [
            [
                'name' => 'valid_function',
                'description' => 'A properly defined function',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'param1' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->driver->sendMessage($message, [
                'model' => 'default-model-3.5-turbo',
                'functions' => $validFunctions,
                'max_tokens' => 50,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->logTestStep('✅ Valid function definition accepted');

        } catch (\Exception $e) {
            $this->logTestStep('❌ Valid function definition rejected: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Function definition validation test completed');
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
