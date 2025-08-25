<?php

namespace JTD\LaravelAI\Tests\Unit\Drivers;

use JTD\LaravelAI\Drivers\Gemini\GeminiDriver;
use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;
use JTD\LaravelAI\Drivers\XAI\XAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Services\AIFunctionEvent;
use JTD\LaravelAI\Tests\Support\TestEmailSenderListener;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test unified tool system compatibility across all drivers.
 */
class UnifiedToolSystemCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register a test function event
        AIFunctionEvent::listen(
            'test_compatibility_function',
            TestEmailSenderListener::class,
            [
                'description' => 'Test function for compatibility testing',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string'],
                    ],
                    'required' => ['message'],
                ],
            ]
        );
    }

    #[Test]
    public function openai_driver_has_formatToolsForAPI_method()
    {
        $driver = new OpenAIDriver(['api_key' => 'sk-test-key-for-testing']);

        $this->assertTrue(method_exists($driver, 'formatToolsForAPI'));
    }

    #[Test]
    public function gemini_driver_has_formatToolsForAPI_method()
    {
        $driver = new GeminiDriver(['api_key' => 'test-key']);

        $this->assertTrue(method_exists($driver, 'formatToolsForAPI'));
    }

    #[Test]
    public function xai_driver_has_formatToolsForAPI_method()
    {
        $driver = new XAIDriver(['api_key' => 'test-key']);

        $this->assertTrue(method_exists($driver, 'formatToolsForAPI'));
    }

    #[Test]
    public function openai_driver_supports_function_calling()
    {
        $driver = new OpenAIDriver(['api_key' => 'sk-test-key-for-testing']);

        $this->assertTrue($driver->supportsFunctionCalling());
    }

    #[Test]
    public function gemini_driver_supports_function_calling()
    {
        $driver = new GeminiDriver(['api_key' => 'test-key']);

        $this->assertTrue($driver->supportsFunctionCalling());
    }

    #[Test]
    public function xai_driver_supports_function_calling()
    {
        $driver = new XAIDriver(['api_key' => 'test-key']);

        $this->assertTrue($driver->supportsFunctionCalling());
    }

    #[Test]
    public function openai_driver_formats_tools_correctly()
    {
        $driver = new OpenAIDriver(['api_key' => 'sk-test-key-for-testing']);

        $resolvedTools = [
            'test_tool' => [
                'name' => 'test_tool',
                'description' => 'A test tool',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'param1' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('formatToolsForAPI');
        $method->setAccessible(true);

        $formatted = $method->invoke($driver, $resolvedTools);

        $this->assertIsArray($formatted);
        $this->assertCount(1, $formatted);
        $this->assertEquals('function', $formatted[0]['type']);
        $this->assertEquals('test_tool', $formatted[0]['function']['name']);
        $this->assertEquals('A test tool', $formatted[0]['function']['description']);
    }

    #[Test]
    public function gemini_driver_formats_tools_correctly()
    {
        $driver = new GeminiDriver(['api_key' => 'test-key']);

        $resolvedTools = [
            'test_tool' => [
                'name' => 'test_tool',
                'description' => 'A test tool',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'param1' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('formatToolsForAPI');
        $method->setAccessible(true);

        $formatted = $method->invoke($driver, $resolvedTools);

        $this->assertIsArray($formatted);
        $this->assertCount(1, $formatted);
        $this->assertArrayHasKey('function_declarations', $formatted[0]);
        $this->assertEquals('test_tool', $formatted[0]['function_declarations'][0]['name']);
        $this->assertEquals('A test tool', $formatted[0]['function_declarations'][0]['description']);
    }

    #[Test]
    public function xai_driver_formats_tools_correctly()
    {
        $driver = new XAIDriver(['api_key' => 'test-key']);

        $resolvedTools = [
            'test_tool' => [
                'name' => 'test_tool',
                'description' => 'A test tool',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'param1' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('formatToolsForAPI');
        $method->setAccessible(true);

        $formatted = $method->invoke($driver, $resolvedTools);

        $this->assertIsArray($formatted);
        $this->assertCount(1, $formatted);
        $this->assertEquals('function', $formatted[0]['type']);
        $this->assertEquals('test_tool', $formatted[0]['function']['name']);
        $this->assertEquals('A test tool', $formatted[0]['function']['description']);
    }

    #[Test]
    public function all_drivers_inherit_processToolOptions_from_abstract_provider()
    {
        $openaiDriver = new OpenAIDriver(['api_key' => 'sk-test-key-for-testing']);
        $geminiDriver = new GeminiDriver(['api_key' => 'test-key']);
        $xaiDriver = new XAIDriver(['api_key' => 'test-key']);

        $this->assertTrue(method_exists($openaiDriver, 'processToolOptions'));
        $this->assertTrue(method_exists($geminiDriver, 'processToolOptions'));
        $this->assertTrue(method_exists($xaiDriver, 'processToolOptions'));
    }

    #[Test]
    public function all_drivers_can_process_tool_options()
    {
        $drivers = [
            'openai' => new OpenAIDriver(['api_key' => 'sk-test-key-for-testing']),
            'gemini' => new GeminiDriver(['api_key' => 'test-key']),
            'xai' => new XAIDriver(['api_key' => 'test-key']),
        ];

        foreach ($drivers as $name => $driver) {
            $options = [
                'withTools' => ['test_compatibility_function'],
                'model' => 'test-model',
            ];

            $reflection = new \ReflectionClass($driver);
            $method = $reflection->getMethod('processToolOptions');
            $method->setAccessible(true);

            $processedOptions = $method->invoke($driver, $options);

            $this->assertIsArray($processedOptions, "Driver {$name} should process tool options");
            $this->assertArrayHasKey('resolved_tools', $processedOptions, "Driver {$name} should resolve tools");
            $this->assertArrayHasKey('tools', $processedOptions, "Driver {$name} should format tools for API");
        }
    }
}
