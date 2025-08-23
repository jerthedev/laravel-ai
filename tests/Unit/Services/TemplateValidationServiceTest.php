<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use JTD\LaravelAI\Models\ConversationTemplate;
use JTD\LaravelAI\Services\TemplateValidationService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TemplateValidationServiceTest extends TestCase
{
    protected TemplateValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateValidationService;
    }

    #[Test]
    public function it_validates_complete_template_successfully(): void
    {
        $validTemplate = [
            'name' => 'Valid Template',
            'description' => 'A valid template for testing',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => [
                'system_prompt' => 'You are a helpful assistant.',
                'initial_messages' => [
                    ['role' => 'user', 'content' => 'Hello!'],
                    ['role' => 'assistant', 'content' => 'Hi there!'],
                ],
            ],
            'parameters' => [
                'name' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'User name',
                ],
            ],
            'default_configuration' => [
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ],
            'tags' => ['test', 'valid'],
            'language' => 'en',
        ];

        $errors = $this->service->validateTemplate($validTemplate);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_validates_structure_with_errors(): void
    {
        $invalidTemplate = [
            // Missing required 'name' field
            'category' => 'invalid_category',
            'template_data' => [],
            'tags' => 'not_an_array', // Should be array
            'language' => 'invalid', // Should be 2 characters
        ];

        $errors = $this->service->validateTemplate($invalidTemplate);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('structure', $errors);

        $structureErrors = $errors['structure'];
        $this->assertContains('Missing required field: name', $structureErrors);
        $this->assertContains('Invalid category. Must be one of: general, business, creative, technical, educational, analysis, support', $structureErrors);
        $this->assertContains('Tags must be an array', $structureErrors);
        $this->assertContains('Language must be a 2-character ISO code', $structureErrors);
    }

    #[Test]
    public function it_validates_parameter_definitions(): void
    {
        $templateWithInvalidParams = [
            'name' => 'Test Template',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => ['system_prompt' => 'Test'],
            'parameters' => [
                '' => ['type' => 'string'], // Empty parameter name
                'valid_param' => ['type' => 'invalid_type'], // Invalid type
                'enum_param' => [
                    'type' => 'enum',
                    // Missing 'options' for enum type
                ],
                'string_param' => [
                    'type' => 'string',
                    'max_length' => 'not_a_number', // Invalid max_length
                ],
                'number_param' => [
                    'type' => 'integer',
                    'min' => 10,
                    'max' => 5, // min > max
                ],
            ],
        ];

        $errors = $this->service->validateTemplate($templateWithInvalidParams);

        $this->assertArrayHasKey('parameters', $errors);
        $paramErrors = $errors['parameters'];

        $this->assertArrayHasKey('', $paramErrors); // Empty name error
        $this->assertArrayHasKey('valid_param', $paramErrors); // Invalid type error
        $this->assertArrayHasKey('enum_param', $paramErrors); // Missing options error
        $this->assertArrayHasKey('string_param', $paramErrors); // Invalid max_length error
        $this->assertArrayHasKey('number_param', $paramErrors); // min > max error
    }

    #[Test]
    public function it_validates_template_data_content(): void
    {
        $templateWithInvalidData = [
            'name' => 'Test Template',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => [
                'system_prompt' => 123, // Should be string
                'initial_messages' => [
                    ['role' => 'invalid_role', 'content' => 'Test'], // Invalid role
                    ['content' => 'Missing role'], // Missing role
                    ['role' => 'user'], // Missing content
                ],
                'title' => ['not_a_string'], // Should be string
            ],
            'parameters' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ];

        $errors = $this->service->validateTemplate($templateWithInvalidData);

        $this->assertArrayHasKey('template_data', $errors);
        $templateDataErrors = $errors['template_data'];

        $this->assertContains('System prompt must be a string', $templateDataErrors);
        $this->assertContains('Title must be a string', $templateDataErrors);

        // Check for message validation errors
        $messageErrors = array_filter($templateDataErrors, function ($error) {
            return str_contains($error, 'Message');
        });
        $this->assertNotEmpty($messageErrors);
    }

    #[Test]
    public function it_validates_parameter_references_in_content(): void
    {
        $templateWithInvalidRefs = [
            'name' => 'Test Template',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => [
                'system_prompt' => 'You are {{undefined_param}}.',
                'initial_messages' => [
                    ['role' => 'user', 'content' => 'Hello {{another_undefined}}!'],
                ],
            ],
            'parameters' => [
                'defined_param' => ['type' => 'string', 'required' => true],
            ],
        ];

        $errors = $this->service->validateTemplate($templateWithInvalidRefs);

        $this->assertArrayHasKey('template_data', $errors);
        $templateDataErrors = $errors['template_data'];

        $undefinedParamErrors = array_filter($templateDataErrors, function ($error) {
            return str_contains($error, 'undefined_param') || str_contains($error, 'another_undefined');
        });
        $this->assertNotEmpty($undefinedParamErrors);
    }

    #[Test]
    public function it_validates_configuration_settings(): void
    {
        $templateWithInvalidConfig = [
            'name' => 'Test Template',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => ['system_prompt' => 'Test'],
            'default_configuration' => [
                'temperature' => 3.0, // Too high (max 2.0)
                'max_tokens' => -100, // Negative
                'top_p' => 1.5, // Too high (max 1.0)
            ],
        ];

        $errors = $this->service->validateTemplate($templateWithInvalidConfig);

        $this->assertArrayHasKey('configuration', $errors);
        $configErrors = $errors['configuration'];

        $this->assertContains('Temperature must be between 0 and 2', $configErrors);
        $this->assertContains('Max tokens must be a positive integer', $configErrors);
        $this->assertContains('Top P must be between 0 and 1', $configErrors);
    }

    #[Test]
    public function it_validates_enum_parameter_with_valid_default(): void
    {
        $templateWithValidEnum = [
            'name' => 'Test Template',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => ['system_prompt' => 'Test'],
            'parameters' => [
                'level' => [
                    'type' => 'enum',
                    'options' => ['beginner', 'intermediate', 'advanced'],
                    'default' => 'beginner', // Valid default
                ],
            ],
        ];

        $errors = $this->service->validateTemplate($templateWithValidEnum);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_validates_enum_parameter_with_invalid_default(): void
    {
        $templateWithInvalidEnum = [
            'name' => 'Test Template',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => ['system_prompt' => 'Test'],
            'parameters' => [
                'level' => [
                    'type' => 'enum',
                    'options' => ['beginner', 'intermediate', 'advanced'],
                    'default' => 'expert', // Invalid default
                ],
            ],
        ];

        $errors = $this->service->validateTemplate($templateWithInvalidEnum);

        $this->assertArrayHasKey('parameters', $errors);
        $this->assertArrayHasKey('level', $errors['parameters']);
        $this->assertContains('Invalid default value: Value must be one of: beginner, intermediate, advanced', $errors['parameters']['level']);
    }

    #[Test]
    public function it_checks_template_compatibility(): void
    {
        $templateData = [
            'name' => 'Test Template',
            'provider_name' => 'OpenAI',
            'model_name' => 'gpt-4',
            'template_data' => [
                'deprecated_field' => 'some_value',
            ],
        ];

        $issues = $this->service->checkCompatibility($templateData);

        $this->assertNotEmpty($issues);
        $this->assertContains('Template uses deprecated features that may not work in current version', $issues);
    }

    #[Test]
    public function it_validates_string_parameter_constraints(): void
    {
        $templateWithStringConstraints = [
            'name' => 'Test Template',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => ['system_prompt' => 'Test'],
            'parameters' => [
                'valid_string' => [
                    'type' => 'string',
                    'min_length' => 5,
                    'max_length' => 100,
                ],
                'invalid_string' => [
                    'type' => 'string',
                    'min_length' => -1, // Invalid
                    'max_length' => 'not_a_number', // Invalid
                ],
            ],
        ];

        $errors = $this->service->validateTemplate($templateWithStringConstraints);

        $this->assertArrayHasKey('parameters', $errors);
        $this->assertArrayHasKey('invalid_string', $errors['parameters']);

        $stringErrors = $errors['parameters']['invalid_string'];
        $this->assertContains('min_length must be a non-negative integer', $stringErrors);
        $this->assertContains('max_length must be a positive integer', $stringErrors);
    }

    #[Test]
    public function it_validates_array_parameter_constraints(): void
    {
        $templateWithArrayConstraints = [
            'name' => 'Test Template',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => ['system_prompt' => 'Test'],
            'parameters' => [
                'valid_array' => [
                    'type' => 'array',
                    'min_items' => 1,
                    'max_items' => 10,
                ],
                'invalid_array' => [
                    'type' => 'array',
                    'min_items' => -1, // Invalid
                    'max_items' => 0, // Invalid
                ],
            ],
        ];

        $errors = $this->service->validateTemplate($templateWithArrayConstraints);

        $this->assertArrayHasKey('parameters', $errors);
        $this->assertArrayHasKey('invalid_array', $errors['parameters']);

        $arrayErrors = $errors['parameters']['invalid_array'];
        $this->assertContains('min_items must be a non-negative integer', $arrayErrors);
        $this->assertContains('max_items must be a positive integer', $arrayErrors);
    }

    #[Test]
    public function it_validates_message_structure(): void
    {
        $validMessage = ['role' => 'user', 'content' => 'Hello!'];
        $invalidMessages = [
            [], // Missing both role and content
            ['role' => 'user'], // Missing content
            ['content' => 'Hello!'], // Missing role
            ['role' => 'invalid', 'content' => 'Hello!'], // Invalid role
            ['role' => 'user', 'content' => 123], // Invalid content type
        ];

        // Valid message should pass
        $errors = $this->service->validateTemplate([
            'name' => 'Test',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => [
                'initial_messages' => [$validMessage],
            ],
        ]);
        $this->assertEmpty($errors);

        // Invalid messages should fail
        foreach ($invalidMessages as $index => $invalidMessage) {
            $errors = $this->service->validateTemplate([
                'name' => 'Test',
                'category' => ConversationTemplate::CATEGORY_GENERAL,
                'template_data' => [
                    'initial_messages' => [$invalidMessage],
                ],
            ]);

            $this->assertNotEmpty($errors, "Invalid message {$index} should have errors");
            $this->assertArrayHasKey('template_data', $errors);
        }
    }
}
