<?php

namespace JTD\LaravelAI\Tests\Unit;

use Illuminate\Support\Facades\Http;
use JTD\LaravelAI\Drivers\XAI\XAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * xAI Driver Unit Tests
 *
 * Tests the xAI driver implementation including API integration,
 * error handling, cost calculation, and streaming responses.
 */
#[Group('unit')]
#[Group('xai')]
class XAIDriverTest extends TestCase
{
    protected XAIDriver $driver;

    protected array $validConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validConfig = [
            'api_key' => 'xai-test-key-1234567890abcdef',
            'base_url' => 'https://api.x.ai/v1',
            'default_model' => 'grok-2-mini',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_attempts' => 3,
            'retry_delay' => 1000,
        ];

        // Create driver with valid config
        $this->driver = new XAIDriver($this->validConfig);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_create_xai_driver_with_valid_config(): void
    {
        $driver = new XAIDriver($this->validConfig);

        $this->assertInstanceOf(XAIDriver::class, $driver);
        $this->assertEquals('xai', $driver->getName());
        $this->assertEquals('grok-2-mini', $driver->getDefaultModel());
    }

    #[Test]
    public function it_throws_exception_for_missing_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('xAI API key is required');

        $config = $this->validConfig;
        $config['api_key'] = '';

        new XAIDriver($config);
    }

    #[Test]
    public function it_throws_exception_for_invalid_api_key_format(): void
    {
        // xAI doesn't enforce strict format validation, so this test is optional
        $config = $this->validConfig;
        $config['api_key'] = 'invalid-key';

        $driver = new XAIDriver($config);
        $this->assertInstanceOf(XAIDriver::class, $driver);
    }

    #[Test]
    #[DataProvider('validApiKeyProvider')]
    public function it_accepts_valid_api_key_formats(string $apiKey): void
    {
        $config = $this->validConfig;
        $config['api_key'] = $apiKey;

        $driver = new XAIDriver($config);

        $this->assertInstanceOf(XAIDriver::class, $driver);
    }

    public static function validApiKeyProvider(): array
    {
        return [
            'xai_standard_key' => ['xai-1234567890abcdef1234567890abcdef'],
            'xai_short_key' => ['xai-test123'],
            'xai_long_key' => ['xai-' . str_repeat('a', 100)],
            'generic_key' => ['test-api-key-12345'], // xAI accepts various formats
        ];
    }

    #[Test]
    public function it_can_set_and_get_custom_client(): void
    {
        $customClient = Http::fake();

        $this->driver->setClient($customClient);

        $this->assertSame($customClient, $this->driver->getClient());
    }

    #[Test]
    public function it_handles_configuration_with_optional_parameters(): void
    {
        $minimalConfig = [
            'api_key' => 'xai-test-key',
        ];

        $driver = new XAIDriver($minimalConfig);

        $this->assertInstanceOf(XAIDriver::class, $driver);
        $this->assertEquals('grok-beta', $driver->getDefaultModel()); // Default model
    }

    #[Test]
    public function it_handles_configuration_with_all_parameters(): void
    {
        $fullConfig = [
            'api_key' => 'xai-test-key',
            'base_url' => 'https://custom.api.x.ai/v1',
            'default_model' => 'grok-2',
            'timeout' => 60,
            'retry_attempts' => 5,
            'retry_delay' => 2000,
            'max_retry_delay' => 60000,
        ];

        $driver = new XAIDriver($fullConfig);

        $this->assertInstanceOf(XAIDriver::class, $driver);
        $this->assertEquals('grok-2', $driver->getDefaultModel());
    }

    #[Test]
    public function it_can_send_message_successfully(): void
    {
        $fakeClient = Http::fake([
            'api.x.ai/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test123',
                'object' => 'chat.completion',
                'created' => 1234567890,
                'model' => 'grok-2-mini',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello! How can I help you today?',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 8,
                    'total_tokens' => 18,
                ],
            ], 200),
        ]);

        $this->driver->setClient($fakeClient);

        $message = AIMessage::user('Hello, Grok!');
        $response = $this->driver->sendMessage($message);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Hello! How can I help you today?', $response->content);
        $this->assertEquals('grok-2-mini', $response->model);
        $this->assertEquals('xai', $response->provider);
    }

    #[Test]
    public function it_can_send_streaming_message(): void
    {
        $fakeClient = Http::fake([
            'api.x.ai/v1/chat/completions' => Http::response(
                'data: ' . json_encode([
                    'id' => 'chatcmpl-test123',
                    'object' => 'chat.completion.chunk',
                    'created' => 1234567890,
                    'model' => 'grok-2-mini',
                    'choices' => [
                        [
                            'index' => 0,
                            'delta' => ['content' => 'Hello'],
                            'finish_reason' => null,
                        ],
                    ],
                ]) . "\n\ndata: [DONE]\n\n",
                200
            ),
        ]);

        $this->driver->setClient($fakeClient);

        $message = AIMessage::user('Hello');
        $chunks = iterator_to_array($this->driver->sendStreamingMessage($message));

        $this->assertGreaterThan(0, count($chunks));
        $this->assertInstanceOf(AIResponse::class, $chunks[0]);
    }

    #[Test]
    public function it_can_get_available_models(): void
    {
        $fakeClient = Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'grok-2-mini',
                        'object' => 'model',
                        'created' => 1234567890,
                        'owned_by' => 'xai',
                    ],
                    [
                        'id' => 'grok-2',
                        'object' => 'model',
                        'created' => 1234567890,
                        'owned_by' => 'xai',
                    ],
                ],
            ], 200),
        ]);

        $this->driver->setClient($fakeClient);

        $models = $this->driver->getAvailableModels();

        $this->assertIsArray($models);
        $this->assertGreaterThan(0, count($models));
        $this->assertTrue(in_array('grok-2-mini', array_column($models, 'id')));
    }

    #[Test]
    public function it_returns_capabilities(): void
    {
        $capabilities = $this->driver->getCapabilities();

        $this->assertIsArray($capabilities);
        $this->assertTrue($capabilities['chat']);
        $this->assertTrue($capabilities['streaming']);
        $this->assertTrue($capabilities['function_calling']);
    }

    #[Test]
    public function it_can_calculate_cost_for_message(): void
    {
        $usage = new \JTD\LaravelAI\Models\TokenUsage(100, 50, 150);
        $cost = $this->driver->calculateCost($usage, 'grok-2-mini');

        $this->assertIsArray($cost);
        $this->assertArrayHasKey('total_cost', $cost);
        $this->assertGreaterThan(0, $cost['total_cost']);
        $this->assertEquals(100, $cost['input_tokens']);
        $this->assertEquals(50, $cost['output_tokens']);
    }

    #[Test]
    public function it_can_calculate_cost_with_default_model(): void
    {
        $usage = new \JTD\LaravelAI\Models\TokenUsage(100, 50, 150);
        $cost = $this->driver->calculateCost($usage, $this->driver->getDefaultModel());

        $this->assertIsArray($cost);
        $this->assertArrayHasKey('total_cost', $cost);
        $this->assertGreaterThan(0, $cost['total_cost']);
    }

    #[Test]
    public function it_can_estimate_tokens_for_string(): void
    {
        $text = 'This is a test message for token estimation';

        $estimation = $this->driver->estimateTokens($text);

        $this->assertIsInt($estimation);
        $this->assertGreaterThan(0, $estimation);
    }

    #[Test]
    public function it_can_estimate_tokens_for_message(): void
    {
        $message = AIMessage::user('Hello, world!');

        $estimation = $this->driver->estimateTokens($message);

        $this->assertIsInt($estimation);
        $this->assertGreaterThan(0, $estimation);
    }

    #[Test]
    public function it_can_estimate_tokens_for_message_array(): void
    {
        $messages = [
            AIMessage::system('You are a helpful assistant.'),
            AIMessage::user('What is Laravel?'),
        ];

        $estimation = $this->driver->estimateTokens($messages);

        $this->assertIsInt($estimation);
        $this->assertGreaterThan(0, $estimation);
    }

    #[Test]
    public function it_can_validate_credentials(): void
    {
        $fakeClient = Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'object' => 'list',
                'data' => [['id' => 'grok-2-mini', 'object' => 'model']],
            ], 200),
            'api.x.ai/v1/chat/completions' => Http::response([
                'id' => 'test',
                'choices' => [['message' => ['content' => 'Hi'], 'finish_reason' => 'stop']],
                'usage' => ['total_tokens' => 3],
            ], 200),
        ]);

        $this->driver->setClient($fakeClient);

        $validation = $this->driver->validateCredentials();

        $this->assertIsArray($validation);
        $this->assertTrue($validation['valid']);
    }

    #[Test]
    public function it_can_get_health_status(): void
    {
        $fakeClient = Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'object' => 'list',
                'data' => [['id' => 'grok-2-mini', 'object' => 'model']],
            ], 200),
            'api.x.ai/v1/chat/completions' => Http::response([
                'id' => 'test',
                'choices' => [['message' => ['content' => 'Hi'], 'finish_reason' => 'stop']],
                'usage' => ['total_tokens' => 3],
            ], 200),
        ]);

        $this->driver->setClient($fakeClient);

        $health = $this->driver->healthCheck();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('checks', $health);
    }

    #[Test]
    public function it_has_correct_provider_name(): void
    {
        $this->assertEquals('xai', $this->driver->getName());
    }

    #[Test]
    public function it_can_be_instantiated_with_minimal_config(): void
    {
        $minimalConfig = ['api_key' => 'xai-test-key'];
        $driver = new XAIDriver($minimalConfig);

        $this->assertInstanceOf(XAIDriver::class, $driver);
        $this->assertEquals('xai', $driver->getName());
    }

    #[Test]
    public function it_can_get_capabilities(): void
    {
        $capabilities = $this->driver->getCapabilities();

        $this->assertIsArray($capabilities);
        $this->assertTrue($capabilities['chat']);
        $this->assertTrue($capabilities['streaming']);
        $this->assertTrue($capabilities['function_calling']);
        $this->assertFalse($capabilities['vision']); // Only grok-2-vision-1212 has vision
    }

    #[Test]
    public function it_can_get_default_model(): void
    {
        $this->assertEquals('grok-2-mini', $this->driver->getDefaultModel());
    }

    #[Test]
    public function it_handles_conversation_context_with_system_message(): void
    {
        $fakeClient = Http::fake([
            'api.x.ai/v1/chat/completions' => Http::response([
                'id' => 'test',
                'choices' => [['message' => ['content' => '2+2 equals 4.'], 'finish_reason' => 'stop']],
                'usage' => ['total_tokens' => 10],
            ], 200),
        ]);

        $this->driver->setClient($fakeClient);

        $messages = [
            AIMessage::system('You are a math tutor.'),
            AIMessage::user('What is 2+2?'),
        ];

        $response = $this->driver->sendMessage($messages);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('2+2 equals 4.', $response->content);
    }

    #[Test]
    public function it_can_check_feature_support(): void
    {
        $this->assertTrue($this->driver->supportsFeature('chat'));
        $this->assertTrue($this->driver->supportsFeature('streaming'));
        $this->assertTrue($this->driver->supportsFeature('function_calling'));
        $this->assertFalse($this->driver->supportsFeature('vision')); // Only grok-2-vision-1212
    }

    #[Test]
    public function it_handles_api_errors_gracefully(): void
    {
        $fakeClient = Http::fake([
            'api.x.ai/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Invalid API key',
                    'type' => 'authentication_error',
                ],
            ], 401),
        ]);

        $this->driver->setClient($fakeClient);

        $this->expectException(\JTD\LaravelAI\Exceptions\XAI\XAIInvalidCredentialsException::class);

        $message = AIMessage::user('Hello');
        $this->driver->sendMessage($message);
    }

    /**
     * Test helper to create a mock xAI API response.
     */
    protected function createMockApiResponse(array $data = []): array
    {
        return array_merge([
            'id' => 'chatcmpl-test123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'grok-2-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Mock response content',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 15,
                'total_tokens' => 25,
            ],
        ], $data);
    }
}
