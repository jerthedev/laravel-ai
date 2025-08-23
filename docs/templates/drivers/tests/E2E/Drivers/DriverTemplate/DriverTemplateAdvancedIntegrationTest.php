<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Advanced E2E Integration Tests for DriverTemplate Driver
 *
 * Tests advanced scenarios including different models, conversation context,
 * parameter variations, and edge cases with real DriverTemplate API.
 */
#[Group('e2e')]
#[Group('drivertemplate')]
#[Group('integration')]
class DriverTemplateAdvancedIntegrationTest extends E2ETestCase
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
    public function it_can_use_different_models(): void
    {

        // TODO: Implement test

            } catch (\Exception $e) {
                $this->logTestStep("❌ {$model} failed: " . $e->getMessage());
                throw $e;
            }
        }

        $this->logTestEnd('Different models test completed');
    }

    #[Test]
    public function it_can_handle_conversation_context(): void
    {

        // TODO: Implement test
    }

    #[Test]
    public function it_can_handle_different_parameters(): void
    {

        // TODO: Implement test

            } catch (\Exception $e) {
                $this->logTestStep("❌ {$name} failed: " . $e->getMessage());
                throw $e;
            }
        }

        $this->logTestEnd('Parameter configuration test completed');
    }

    #[Test]
    public function it_can_handle_system_messages(): void
    {

        // TODO: Implement test
            } else {
                $this->logTestStep('⚠️  System message partially followed (AI behavior can vary)');
            }

        } catch (\Exception $e) {
            $this->logTestStep('❌ System message test failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('System message test completed');
    }

    #[Test]
    public function it_can_handle_edge_cases(): void
    {

        // TODO: Implement test
            }

        } catch (\Exception $e) {
            // This is acceptable - empty messages might be rejected
            $this->logTestStep('⚠️  Empty message rejected (acceptable): ' . $e->getMessage());
        }

        // Test very long message (should handle or fail gracefully)
        try {
            $longMessage = AIMessage::user(str_repeat('This is a very long message. ', 100));
            $response = $this->driver->sendMessage($longMessage, [
                'model' => 'default-model-3.5-turbo',
                'max_tokens' => 10,
            ]);

            if ($response) {
                $this->assertInstanceOf(AIResponse::class, $response);
                $this->logTestStep('✅ Long message handled successfully');
            }

        } catch (\Exception $e) {
            // This is acceptable - very long messages might exceed context limits
            $this->logTestStep('⚠️  Long message rejected (acceptable): ' . $e->getMessage());
        }

        // Test minimal token limit
        try {
            $message = AIMessage::user('Hi');
            $response = $this->driver->sendMessage($message, [
                'model' => 'default-model-3.5-turbo',
                'max_tokens' => 1,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertLessThanOrEqual(3, $response->tokenUsage->outputTokens); // Very small response
            $this->logTestStep('✅ Minimal token limit handled: "' . $response->content . '"');

        } catch (\Exception $e) {
            $this->logTestStep('❌ Minimal token limit failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Edge cases test completed');
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
