<?php

namespace JTD\LaravelAI\Tests\Unit;

use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIInvalidCredentialsException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Security Tests for JTD Laravel AI Package
 *
 * Tests credential handling, input validation, logging security,
 * and other security-related functionality.
 */
#[Group('unit')]
#[Group('security')]
class SecurityTest extends TestCase
{
    private OpenAIDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new OpenAIDriver([
            'api_key' => 'sk-test-key-for-security-tests-1234567890',
            'timeout' => 30,
        ]);
    }

    #[Test]
    #[Group('credential-security')]
    public function it_masks_api_keys_in_configuration_output(): void
    {
        $config = $this->driver->getConfig();

        $this->assertArrayHasKey('api_key', $config);
        $this->assertStringStartsWith('sk-***', $config['api_key']);
        $this->assertStringEndsWith('7890', $config['api_key']);
        $this->assertStringNotContainsString('test-key-for-security-tests', $config['api_key']);
    }

    #[Test]
    #[Group('credential-security')]
    public function it_validates_api_key_format(): void
    {
        $this->expectException(OpenAIInvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid OpenAI API key format');

        new OpenAIDriver([
            'api_key' => 'invalid-key-format',
        ]);
    }

    #[Test]
    #[Group('credential-security')]
    public function it_requires_api_key_for_initialization(): void
    {
        $this->expectException(OpenAIInvalidCredentialsException::class);
        $this->expectExceptionMessage('OpenAI API key is required');

        new OpenAIDriver([]);
    }

    #[Test]
    #[Group('input-validation')]
    public function it_validates_message_roles(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        new AIMessage('invalid_role', 'Test message');
    }

    #[Test]
    #[Group('input-validation')]
    public function it_validates_message_content_types(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        new AIMessage('user', 'Test message', 'invalid_type');
    }

    #[Test]
    #[DataProvider('validMessageRolesProvider')]
    #[Group('input-validation')]
    public function it_accepts_valid_message_roles(string $role): void
    {
        // Function and tool roles require a name parameter
        if (in_array($role, ['function', 'tool'])) {
            $message = new AIMessage($role, 'Test message', AIMessage::CONTENT_TYPE_TEXT, null, null, null, null, 'test_name');
        } else {
            $message = new AIMessage($role, 'Test message');
        }

        $this->assertEquals($role, $message->role);
    }

    public static function validMessageRolesProvider(): array
    {
        return [
            ['system'],
            ['user'],
            ['assistant'],
            ['function'],
            ['tool'],
        ];
    }

    #[Test]
    #[DataProvider('validContentTypesProvider')]
    #[Group('input-validation')]
    public function it_accepts_valid_content_types(string $contentType): void
    {
        $message = new AIMessage('user', 'Test message', $contentType);

        $this->assertEquals($contentType, $message->contentType);
    }

    public static function validContentTypesProvider(): array
    {
        return [
            ['text'],
            ['image'],
            ['audio'],
            ['file'],
            ['multimodal'],
        ];
    }

    #[Test]
    #[Group('configuration-security')]
    public function it_accepts_valid_configuration_parameters(): void
    {
        $driver = new OpenAIDriver([
            'api_key' => 'sk-test-key-1234567890',
            'timeout' => 60,
            'retry_attempts' => 5,
            'organization' => 'org-test',
            'project' => 'proj-test',
        ]);

        $config = $driver->getConfig();

        $this->assertEquals(60, $config['timeout']);
        $this->assertEquals(5, $config['retry_attempts']);
        $this->assertEquals('org-test', $config['organization']);
        $this->assertEquals('proj-test', $config['project']);
        $this->assertStringStartsWith('sk-***', $config['api_key']);
    }

    #[Test]
    #[Group('error-security')]
    public function it_does_not_expose_sensitive_information_in_errors(): void
    {
        try {
            new OpenAIDriver([
                'api_key' => 'invalid-key-format', // This will cause a validation error
            ]);
            $this->fail('Expected exception was not thrown');
        } catch (OpenAIInvalidCredentialsException $e) {
            // Error message should not contain the full API key
            $this->assertStringNotContainsString('invalid-key-format', $e->getMessage());
            // But should contain helpful information
            $this->assertStringContainsString('Invalid OpenAI API key format', $e->getMessage());
        }
    }

    #[Test]
    #[Group('configuration-security')]
    public function it_uses_secure_defaults(): void
    {
        $driver = new OpenAIDriver([
            'api_key' => 'sk-test-key-1234567890',
        ]);

        $config = $driver->getConfig();

        // Should have secure defaults
        $this->assertEquals(30, $config['timeout']); // Reasonable timeout
        $this->assertEquals(3, $config['retry_attempts']); // Limited retries
        $this->assertEquals(1000, $config['retry_delay']); // Reasonable retry delay
        $this->assertEquals(30000, $config['max_retry_delay']); // Maximum retry delay
    }
}
