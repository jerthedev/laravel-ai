<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test DriverTemplate Responses API Integration through our Driver
 *
 * Tests that our driver wrapper correctly integrates with the new
 * DriverTemplate Responses API for GPT-5 and function calling.
 */
#[Group('e2e')]
#[Group('drivertemplate')]
#[Group('responses-api')]
#[Group('driver-integration')]
class DriverTemplateResponsesAPIDriverTest extends E2ETestCase
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
    public function it_can_use_gpt5_through_driver(): void
    {

        // TODO: Implement test
            } else {
                $this->assertNotEmpty($response->content, 'Response should have content');
                $this->logTestStep('✅ GPT-5 response: "' . trim($response->content) . '"');
            }

            $this->assertEquals('default-model-5-2025-08-07', $response->model, 'Should use GPT-5 model');
            $this->assertEquals('drivertemplate', $response->provider);

            $this->logTestStep('✅ GPT-5 response: "' . trim($response->content) . '"');
            $this->logTestStep('✅ Model: ' . $response->model);
            $this->logTestStep('✅ Provider: ' . $response->provider);
            $this->logTestStep('✅ Response time: ' . round($response->responseTimeMs ?? 0) . 'ms');

            // Check if it contains expected content
            $responseContent = strtolower($response->content);
            if (str_contains($responseContent, 'default-model-5') || str_contains($responseContent, 'working')) {
                $this->logTestStep('✅ Response content matches expectation');
            } else {
                $this->logTestStep('⚠️  Response content differs but GPT-5 is working');
            }

        } catch (\Exception $e) {
            $this->logTestStep('❌ GPT-5 test failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));
            throw $e;
        }

        $this->logTestEnd('GPT-5 driver integration test completed');
    }

    #[Test]
    public function it_can_use_responses_api_explicitly(): void
    {

        // TODO: Implement test

        } catch (\Exception $e) {
            $this->logTestStep('❌ Explicit Responses API test failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));
            throw $e;
        }

        $this->logTestEnd('Explicit Responses API test completed');
    }

    #[Test]
    public function it_can_handle_function_calling_with_responses_api(): void
    {

        // TODO: Implement test
            } elseif ($response->toolCalls) {
                $this->logTestStep('✅ Tool call detected!');
                $this->logTestStep('Tool: ' . $response->toolCalls[0]['function']['name']);
                $this->logTestStep('Arguments: ' . $response->toolCalls[0]['function']['arguments']);
            } else {
                $this->logTestStep('⚠️  No function/tool calls - AI responded directly');
                $this->logTestStep('This is acceptable AI behavior');
            }

        } catch (\Exception $e) {
            $this->logTestStep('❌ Function calling test failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            // Check if it's a known integration issue
            if (str_contains($e->getMessage(), 'tools') || str_contains($e->getMessage(), 'function')) {
                $this->logTestStep('⚠️  This appears to be the integration issue we need to fix');
            }

            throw $e;
        }

        $this->logTestEnd('Function calling with Responses API test completed');
    }

    #[Test]
    public function it_can_compare_chat_vs_responses_api(): void
    {

        // TODO: Implement test

        } catch (\Exception $e) {
            $this->logTestStep('❌ Chat API failed: ' . $e->getMessage());
            throw $e;
        }

        // Test with Responses API
        $this->logTestStep('Testing with Responses API...');
        try {
            $responsesResponse = $this->driver->sendMessage($message, [
                'model' => 'default-model-5', // This will use Responses API
                'max_tokens' => 30,
                'temperature' => 0.5,
            ]);

            $this->assertInstanceOf(AIResponse::class, $responsesResponse);
            $this->logTestStep('✅ Responses API: "' . trim($responsesResponse->content) . '"');
            $this->logTestStep('Model: ' . $responsesResponse->model);
            $this->logTestStep('Response time: ' . round($responsesResponse->responseTimeMs ?? 0) . 'ms');

            // Compare responses
            $this->logTestStep('📊 Comparison:');
            $this->logTestStep('  Chat API length: ' . strlen($chatResponse->content) . ' chars');
            $this->logTestStep('  Responses API length: ' . strlen($responsesResponse->content) . ' chars');
            $this->logTestStep('  Both APIs working successfully!');

        } catch (\Exception $e) {
            $this->logTestStep('❌ Responses API failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('API comparison test completed');
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
