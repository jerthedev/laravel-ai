<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * DriverTemplate Function Calling E2E Tests
 *
 * End-to-end tests for function calling functionality with real DriverTemplate API.
 * Tests definition validation, execution, and error scenarios.
 */
#[Group('e2e')]
#[Group('drivertemplate')]
#[Group('function-calling')]
class DriverTemplateFunctionCallingE2ETest extends TestCase
{
    private DriverTemplateDriver $driver;
    private array $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        // Load credentials from E2E credentials file
        $credentialsPath = __DIR__ . '/../credentials/e2e-credentials.json';

        if (!file_exists($credentialsPath)) {
            $this->markTestSkipped('E2E credentials file not found for function calling tests');
        }

        $this->credentials = json_decode(file_get_contents($credentialsPath), true);

        if (empty($this->credentials['drivertemplate']['api_key']) || !$this->credentials['drivertemplate']['enabled']) {
            $this->markTestSkipped('DriverTemplate credentials not configured or disabled for function calling E2E tests');
        }

        $this->driver = new DriverTemplateDriver([
            'api_key' => $this->credentials['drivertemplate']['api_key'],
            'organization' => $this->credentials['drivertemplate']['organization'] ?? null,
            'project' => $this->credentials['drivertemplate']['project'] ?? null,
            'timeout' => 60,
        ]);
    }

    #[Test]
    public function it_can_call_weather_function(): void
    {

        // TODO: Implement test
        } else {
            $this->logTestStep("ℹ️  Model chose not to call function, responded directly");
            $this->assertNotEmpty($response->content, 'Should have content if no function call');
        }
    }

    #[Test]
    public function it_can_call_calculator_function(): void
    {

        // TODO: Implement test
        } else {
            $this->logTestStep("ℹ️  Model chose not to call function, responded directly");
            $this->assertNotEmpty($response->content, 'Should have content if no function call');
        }
    }

    #[Test]
    public function it_can_use_tools_format(): void
    {

        // TODO: Implement test
        } else {
            $this->logTestStep("ℹ️  Model chose not to call tool, responded directly");
            $this->assertNotEmpty($response->content, 'Should have content if no tool call');
        }
    }

    #[Test]
    public function it_can_handle_multiple_functions(): void
    {

        // TODO: Implement test
        } else {
            $this->logTestStep("ℹ️  Model chose not to call function, responded directly");
        }
    }

    #[Test]
    public function it_can_force_specific_function_call(): void
    {

        // TODO: Implement test
    }

    #[Test]
    public function it_handles_invalid_function_definitions(): void
    {

        // TODO: Implement test

        } catch (\Exception $e) {
            $this->logTestStep("✅ Error handled: " . $e->getMessage());
            $this->assertStringContainsIgnoringCase($e->getMessage(), 'name');
        }
    }

    /**
     * Log a test step for debugging.
     */
    private function logTestStep(string $message, array $context = []): void
    {
        $formattedMessage = $message;
        foreach ($context as $key => $value) {
            $formattedMessage = str_replace("{{$key}}", $value, $formattedMessage);
        }

        if (defined('STDOUT')) {
            fwrite(STDOUT, $formattedMessage . "\n");
        }
    }

    /**
     * Case-insensitive string contains check.
     */
    private function assertStringContainsIgnoringCase(string $haystack, string $needle): void
    {
        $this->assertStringContainsString(
            strtolower($needle),
            strtolower($haystack),
            "Failed asserting that '{$haystack}' contains '{$needle}' (case insensitive)"
        );
    }
}
