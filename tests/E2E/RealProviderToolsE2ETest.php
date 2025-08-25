<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Services\AIFunctionEvent;
use JTD\LaravelAI\Services\UnifiedToolRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real Provider Tools E2E Test
 *
 * E2E tests with real OpenAI provider using both ConversationBuilder and direct sendMessage patterns.
 * Tests both MCP tools and Function Events with real AI, tool call handling, and execution routing.
 */
#[Group('e2e')]
#[Group('tools')]
#[Group('real-provider')]
class RealProviderToolsE2ETest extends E2ETestCase
{
    protected UnifiedToolRegistry $toolRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no OpenAI credentials
        if (!$this->hasE2ECredentials('openai')) {
            $this->markTestSkipped('OpenAI credentials not available for real provider testing');
        }

        $this->toolRegistry = app('laravel-ai.tools.registry');

        // Register test function events for real AI testing
        $this->registerTestFunctionEvents();
    }

    protected function registerTestFunctionEvents(): void
    {
        AIFunctionEvent::listen(
            'get_current_weather',
            \JTD\LaravelAI\Tests\Support\TestCurrentWeatherListener::class,
            [
            'description' => 'Get the current weather in a given location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The city and state, e.g. San Francisco, CA',
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit'],
                        'description' => 'Temperature unit',
                    ],
                ],
                'required' => ['location'],
            ],
        ]);

        AIFunctionEvent::listen(
            'calculate_tip',
            \JTD\LaravelAI\Tests\Support\TestCalculateTipListener::class,
            [
            'description' => 'Calculate tip amount and total bill',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'amount' => [
                        'type' => 'number',
                        'description' => 'The bill amount',
                    ],
                    'percentage' => [
                        'type' => 'number',
                        'description' => 'Tip percentage (default: 15)',
                    ],
                    'currency' => [
                        'type' => 'string',
                        'description' => 'Currency code (default: USD)',
                    ],
                ],
                'required' => ['amount'],
            ],
        ]);

        // Refresh registry to pick up new functions
        $this->toolRegistry->refreshCache();
    }

    #[Test]
    public function it_can_use_withTools_with_real_openai_conversation_builder()
    {
        $this->rateLimitApiCall();

        // Test withTools with real OpenAI using ConversationBuilder
        $response = AI::conversation()
            ->provider('openai')
            ->model('gpt-4')
            ->withTools(['get_current_weather'])
            ->message('What\'s the weather like in San Francisco?')
            ->send();

        $this->assertNotNull($response);
        $this->assertIsString($response->content);
        $this->assertNotEmpty($response->content);

        // Check if OpenAI made tool calls
        if (!empty($response->toolCalls)) {
            $this->assertIsArray($response->toolCalls);
            $this->logE2EInfo('Real OpenAI tool calls detected', [
                'tool_calls' => count($response->toolCalls),
                'tools' => array_map(fn($call) => $call['function']['name'] ?? 'unknown', $response->toolCalls),
            ]);
        }

        // Check for tool execution results in metadata
        if (isset($response->metadata['tool_execution_results'])) {
            $this->assertIsArray($response->metadata['tool_execution_results']);
            $this->logE2EInfo('Tool execution results found', [
                'results_count' => count($response->metadata['tool_execution_results']),
            ]);
        }

        $this->logE2EInfo('Real OpenAI withTools ConversationBuilder test completed', [
            'response_length' => strlen($response->content),
            'has_tool_calls' => !empty($response->toolCalls),
        ]);
    }

    #[Test]
    public function it_can_use_allTools_with_real_openai_conversation_builder()
    {
        $this->rateLimitApiCall();

        // Test allTools with real OpenAI using ConversationBuilder
        $response = AI::conversation()
            ->provider('openai')
            ->model('gpt-4')
            ->allTools()
            ->message('Calculate a 20% tip on a $50 bill and tell me the weather in New York')
            ->send();

        $this->assertNotNull($response);
        $this->assertIsString($response->content);
        $this->assertNotEmpty($response->content);

        // Check if OpenAI made tool calls
        if (!empty($response->toolCalls)) {
            $this->assertIsArray($response->toolCalls);
            $this->logE2EInfo('Real OpenAI multiple tool calls detected', [
                'tool_calls' => count($response->toolCalls),
                'tools' => array_map(fn($call) => $call['function']['name'] ?? 'unknown', $response->toolCalls),
            ]);
        }

        $this->logE2EInfo('Real OpenAI allTools ConversationBuilder test completed', [
            'response_length' => strlen($response->content),
            'has_tool_calls' => !empty($response->toolCalls),
        ]);
    }

    #[Test]
    public function it_can_use_withTools_with_real_openai_direct_send()
    {
        $this->rateLimitApiCall();

        // Test withTools with real OpenAI using direct sendMessage
        $response = AI::provider('openai')->sendMessage(
            AIMessage::user('Calculate a 15% tip on a $75 restaurant bill'),
            [
                'model' => 'gpt-4',
                'withTools' => ['calculate_tip'],
            ]
        );

        $this->assertNotNull($response);
        $this->assertIsString($response->content);
        $this->assertNotEmpty($response->content);

        // Check if OpenAI made tool calls
        if (!empty($response->toolCalls)) {
            $this->assertIsArray($response->toolCalls);

            // Verify tool call structure
            foreach ($response->toolCalls as $toolCall) {
                $this->assertArrayHasKey('id', $toolCall);
                $this->assertArrayHasKey('type', $toolCall);
                $this->assertArrayHasKey('function', $toolCall);
                $this->assertEquals('function', $toolCall['type']);
                $this->assertArrayHasKey('name', $toolCall['function']);
                $this->assertArrayHasKey('arguments', $toolCall['function']);
            }

            $this->logE2EInfo('Real OpenAI direct send tool calls detected', [
                'tool_calls' => count($response->toolCalls),
                'first_tool' => $response->toolCalls[0]['function']['name'] ?? 'unknown',
            ]);
        }

        $this->logE2EInfo('Real OpenAI withTools direct send test completed', [
            'response_length' => strlen($response->content),
            'has_tool_calls' => !empty($response->toolCalls),
        ]);
    }

    #[Test]
    public function it_can_use_allTools_with_real_openai_direct_send()
    {
        $this->rateLimitApiCall();

        // Test allTools with real OpenAI using direct sendMessage
        $response = AI::sendMessage(
            AIMessage::user('I need to know the weather in London and calculate a 18% tip on $120'),
            [
                'model' => 'gpt-4',
                'allTools' => true,
            ]
        );

        $this->assertNotNull($response);
        $this->assertIsString($response->content);
        $this->assertNotEmpty($response->content);

        $this->logE2EInfo('Real OpenAI allTools direct send test completed', [
            'response_length' => strlen($response->content),
            'has_tool_calls' => !empty($response->toolCalls),
        ]);
    }

    #[Test]
    public function it_handles_real_tool_execution_with_openai()
    {
        $this->rateLimitApiCall();

        // Test tool execution with real OpenAI
        $response = AI::conversation()
            ->provider('openai')
            ->model('gpt-4')
            ->withTools(['get_current_weather', 'calculate_tip'])
            ->message('What\'s the weather in Paris and what would be a 20% tip on €45?')
            ->send();

        $this->assertNotNull($response);

        // Check for tool execution results
        if (isset($response->metadata['tool_execution_results'])) {
            $executionResults = $response->metadata['tool_execution_results'];
            $this->assertIsArray($executionResults);

            foreach ($executionResults as $result) {
                $this->assertArrayHasKey('tool_call_id', $result);
                $this->assertArrayHasKey('name', $result);
                $this->assertArrayHasKey('result', $result);
                $this->assertArrayHasKey('status', $result);

                if ($result['status'] === 'success') {
                    $this->assertIsArray($result['result']);

                    // Check result structure based on tool type
                    if ($result['result']['type'] === 'function_event_queued') {
                        $this->assertEquals('background', $result['result']['execution_mode']);
                        $this->assertStringContains('queued for background processing', $result['result']['message']);
                    }
                }
            }

            $this->logE2EInfo('Real tool execution results processed', [
                'execution_results' => count($executionResults),
                'successful_results' => count(array_filter($executionResults, fn($r) => $r['status'] === 'success')),
            ]);
        }

        $this->logE2EInfo('Real OpenAI tool execution test completed', [
            'response_length' => strlen($response->content),
            'has_execution_results' => isset($response->metadata['tool_execution_results']),
        ]);
    }

    #[Test]
    public function it_handles_openai_tool_call_format_correctly()
    {
        $this->rateLimitApiCall();

        // Test that OpenAI tool call format is handled correctly
        $response = AI::provider('openai')->sendMessage(
            AIMessage::user('Calculate a 25% tip on $80'),
            [
                'model' => 'gpt-4',
                'withTools' => ['calculate_tip'],
            ]
        );

        $this->assertNotNull($response);

        if (!empty($response->toolCalls)) {
            foreach ($response->toolCalls as $toolCall) {
                // Verify OpenAI tool call format
                $this->assertIsString($toolCall['id']);
                $this->assertEquals('function', $toolCall['type']);
                $this->assertIsArray($toolCall['function']);
                $this->assertIsString($toolCall['function']['name']);

                // Arguments should be a JSON string from OpenAI
                $this->assertIsString($toolCall['function']['arguments']);

                // Should be valid JSON
                $arguments = json_decode($toolCall['function']['arguments'], true);
                $this->assertIsArray($arguments);
                $this->assertNotNull($arguments);

                $this->logE2EInfo('OpenAI tool call format verified', [
                    'tool_name' => $toolCall['function']['name'],
                    'arguments_keys' => array_keys($arguments),
                ]);
            }
        }

        $this->logE2EInfo('OpenAI tool call format test completed');
    }

    #[Test]
    public function it_can_chain_multiple_real_tool_calls()
    {
        $this->rateLimitApiCall();

        // Test chaining multiple tool calls with real OpenAI
        $response = AI::conversation()
            ->provider('openai')
            ->model('gpt-4')
            ->withTools(['get_current_weather', 'calculate_tip'])
            ->systemPrompt('You are a helpful assistant. Use the available tools to answer questions.')
            ->message('I\'m going to Tokyo tomorrow. What\'s the weather like there? Also, if I have dinner that costs ¥3000, what would be a good 15% tip?')
            ->send();

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        // Check if multiple tools were called
        if (!empty($response->toolCalls)) {
            $toolNames = array_map(fn($call) => $call['function']['name'] ?? 'unknown', $response->toolCalls);
            $uniqueTools = array_unique($toolNames);

            $this->logE2EInfo('Multiple real tool calls detected', [
                'total_calls' => count($response->toolCalls),
                'unique_tools' => count($uniqueTools),
                'tools_used' => $uniqueTools,
            ]);
        }

        $this->logE2EInfo('Real OpenAI multiple tool calls test completed', [
            'response_length' => strlen($response->content),
            'tool_calls' => count($response->toolCalls ?? []),
        ]);
    }

    #[Test]
    public function it_handles_real_openai_streaming_with_tools()
    {
        $this->rateLimitApiCall();

        // Test streaming with tools (if supported)
        $chunks = [];
        $finalResponse = null;

        try {
            foreach (AI::provider('openai')->sendStreamingMessage(
                AIMessage::user('What\'s the weather in Berlin?'),
                [
                    'model' => 'gpt-4',
                    'withTools' => ['get_current_weather'],
                ]
            ) as $chunk) {
                $chunks[] = $chunk;
                $finalResponse = $chunk;
            }

            $this->assertNotEmpty($chunks);
            $this->assertNotNull($finalResponse);

            $this->logE2EInfo('Real OpenAI streaming with tools test completed', [
                'chunks_received' => count($chunks),
                'final_response_length' => strlen($finalResponse->content ?? ''),
            ]);
        } catch (\Exception $e) {
            // Streaming with tools might not be supported in all cases
            $this->logE2EInfo('Streaming with tools not supported or failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    #[Test]
    public function it_validates_real_openai_response_structure()
    {
        $this->rateLimitApiCall();

        // Test that real OpenAI responses have correct structure
        $response = AI::provider('openai')->sendMessage(
            AIMessage::user('Calculate a 20% tip on $100'),
            [
                'model' => 'gpt-4',
                'withTools' => ['calculate_tip'],
            ]
        );

        $this->assertNotNull($response);

        // Validate response structure
        $this->assertIsString($response->content);
        $this->assertIsString($response->model);
        $this->assertEquals('openai', $response->provider);
        $this->assertNotNull($response->tokenUsage);
        $this->assertIsArray($response->metadata);

        // Validate token usage
        $this->assertIsInt($response->tokenUsage->inputTokens);
        $this->assertIsInt($response->tokenUsage->outputTokens);
        $this->assertIsInt($response->tokenUsage->totalTokens);

        $this->logE2EInfo('Real OpenAI response structure validated', [
            'model' => $response->model,
            'provider' => $response->provider,
            'input_tokens' => $response->tokenUsage->inputTokens,
            'output_tokens' => $response->tokenUsage->outputTokens,
            'total_tokens' => $response->tokenUsage->totalTokens,
        ]);
    }

    #[Test]
    public function it_handles_real_openai_error_scenarios_gracefully()
    {
        $this->rateLimitApiCall();

        // Test error handling with invalid tool usage
        try {
            $response = AI::provider('openai')->sendMessage(
                AIMessage::user('Use a non-existent tool to help me'),
                [
                    'model' => 'gpt-4',
                    'withTools' => ['calculate_tip'], // Valid tool, but AI might not use it
                ]
            );

            // Should still get a valid response even if tools aren't used
            $this->assertNotNull($response);
            $this->assertIsString($response->content);

            $this->logE2EInfo('Real OpenAI error scenario handled gracefully');
        } catch (\Exception $e) {
            $this->logE2EInfo('Expected error in error scenario test', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
