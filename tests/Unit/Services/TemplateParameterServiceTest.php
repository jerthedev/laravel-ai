<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use JTD\LaravelAI\Services\TemplateParameterService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TemplateParameterServiceTest extends TestCase
{
    protected TemplateParameterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateParameterService;
    }

    #[Test]
    public function it_processes_simple_parameter_substitution(): void
    {
        $content = 'Hello {{name}}, welcome to {{platform}}!';
        $parameters = [
            'name' => 'John',
            'platform' => 'Laravel AI',
        ];

        $result = $this->service->processContent($content, $parameters);

        $this->assertEquals('Hello John, welcome to Laravel AI!', $result);
    }

    #[Test]
    public function it_processes_conditional_blocks(): void
    {
        $content = 'Hello {{name}}{{#if premium}}, you have premium access{{/if}}!';

        // Test with premium = true
        $parameters = ['name' => 'John', 'premium' => true];
        $result = $this->service->processContent($content, $parameters);
        $this->assertEquals('Hello John, you have premium access!', $result);

        // Test with premium = false
        $parameters = ['name' => 'John', 'premium' => false];
        $result = $this->service->processContent($content, $parameters);
        $this->assertEquals('Hello John!', $result);
    }

    #[Test]
    public function it_processes_loops(): void
    {
        $content = 'Items: {{#each items}}{{this}}{{/each}}';
        $parameters = [
            'items' => ['apple', 'banana', 'cherry'],
        ];

        $result = $this->service->processContent($content, $parameters);

        $this->assertEquals('Items: applebananacherry', $result);
    }

    #[Test]
    public function it_processes_loops_with_separators(): void
    {
        $content = 'Items: {{#each items}}{{this}}{{#if last}}{{else}}, {{/if}}{{/each}}';
        $parameters = [
            'items' => ['apple', 'banana', 'cherry'],
        ];

        $result = $this->service->processContent($content, $parameters);

        $this->assertStringContainsString('apple', $result);
        $this->assertStringContainsString('banana', $result);
        $this->assertStringContainsString('cherry', $result);
    }

    #[Test]
    public function it_handles_dot_notation(): void
    {
        $content = 'Hello {{user.name}}, your email is {{user.email}}';
        $parameters = [
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ];

        $result = $this->service->processContent($content, $parameters);

        $this->assertEquals('Hello John Doe, your email is john@example.com', $result);
    }

    #[Test]
    public function it_processes_function_calls(): void
    {
        $content = 'Hello {{upper name}}, welcome to {{lower platform}}!';
        $parameters = [
            'name' => 'john doe',
            'platform' => 'LARAVEL AI',
        ];

        $result = $this->service->processContent($content, $parameters);

        $this->assertEquals('Hello JOHN DOE, welcome to laravel ai!', $result);
    }

    #[Test]
    public function it_handles_default_values(): void
    {
        $content = 'Hello {{name}}, your role is {{role}}';
        $parameters = ['name' => 'John'];
        $parameterDefinitions = [
            'name' => ['type' => 'string', 'required' => true],
            'role' => ['type' => 'string', 'default' => 'user'],
        ];

        $result = $this->service->processContent($content, $parameters, $parameterDefinitions);

        $this->assertEquals('Hello John, your role is user', $result);
    }

    #[Test]
    public function it_validates_parameters_successfully(): void
    {
        $values = [
            'name' => 'John Doe',
            'age' => 25,
            'active' => true,
            'tags' => ['developer', 'php'],
            'level' => 'intermediate',
        ];

        $definitions = [
            'name' => ['type' => 'string', 'required' => true],
            'age' => ['type' => 'integer', 'min' => 18, 'max' => 100],
            'active' => ['type' => 'boolean'],
            'tags' => ['type' => 'array', 'min_items' => 1],
            'level' => ['type' => 'enum', 'options' => ['beginner', 'intermediate', 'advanced']],
        ];

        $errors = $this->service->validateParameters($values, $definitions);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_validates_parameters_with_errors(): void
    {
        $values = [
            'name' => '', // Empty required field
            'age' => 15, // Below minimum
            'active' => 'yes', // Wrong type
            'tags' => [], // Below minimum items
            'level' => 'expert', // Not in enum options
        ];

        $definitions = [
            'name' => ['type' => 'string', 'required' => true],
            'age' => ['type' => 'integer', 'min' => 18, 'max' => 100],
            'active' => ['type' => 'boolean'],
            'tags' => ['type' => 'array', 'min_items' => 1],
            'level' => ['type' => 'enum', 'options' => ['beginner', 'intermediate', 'advanced']],
        ];

        $errors = $this->service->validateParameters($values, $definitions);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('age', $errors);
        $this->assertArrayHasKey('active', $errors);
        $this->assertArrayHasKey('tags', $errors);
        $this->assertArrayHasKey('level', $errors);
    }

    #[Test]
    public function it_gets_parameter_schema(): void
    {
        $definitions = [
            'name' => [
                'type' => 'string',
                'required' => true,
                'description' => 'User full name',
                'placeholder' => 'Enter your name',
            ],
            'age' => [
                'type' => 'integer',
                'min' => 18,
                'max' => 100,
                'description' => 'User age',
            ],
            'level' => [
                'type' => 'enum',
                'options' => ['beginner', 'intermediate', 'advanced'],
                'default' => 'beginner',
            ],
        ];

        $schema = $this->service->getParameterSchema($definitions);

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('age', $schema);
        $this->assertArrayHasKey('level', $schema);

        // Check name parameter schema
        $nameSchema = $schema['name'];
        $this->assertEquals('string', $nameSchema['type']);
        $this->assertTrue($nameSchema['required']);
        $this->assertEquals('User full name', $nameSchema['description']);
        $this->assertEquals('Enter your name', $nameSchema['placeholder']);

        // Check level parameter schema
        $levelSchema = $schema['level'];
        $this->assertEquals('enum', $levelSchema['type']);
        $this->assertEquals(['beginner', 'intermediate', 'advanced'], $levelSchema['options']);
        $this->assertEquals('beginner', $levelSchema['default']);
    }

    #[Test]
    public function it_handles_comparison_operators_in_conditionals(): void
    {
        $content = '{{#if age >= 18}}Adult{{/if}}{{#if score > 80}}Excellent{{/if}}';

        // Test age >= 18 (true) and score > 80 (false)
        $parameters = ['age' => 25, 'score' => 75];
        $result = $this->service->processContent($content, $parameters);
        $this->assertEquals('Adult', $result);

        // Test age >= 18 (true) and score > 80 (true)
        $parameters = ['age' => 25, 'score' => 85];
        $result = $this->service->processContent($content, $parameters);
        $this->assertEquals('AdultExcellent', $result);
    }

    #[Test]
    public function it_handles_array_formatting(): void
    {
        $content = 'Skills: {{skills}}';
        $parameters = ['skills' => ['PHP', 'Laravel', 'JavaScript']];
        $parameterDefinitions = [
            'skills' => ['type' => 'array', 'array_separator' => ' | '],
        ];

        $result = $this->service->processContent($content, $parameters, $parameterDefinitions);

        $this->assertEquals('Skills: PHP | Laravel | JavaScript', $result);
    }

    #[Test]
    public function it_handles_special_formatting_types(): void
    {
        $content = 'Price: {{price}}, Discount: {{discount}}, Date: {{date}}';
        $parameters = [
            'price' => 99.99,
            'discount' => 0.15,
            'date' => '2024-01-15',
        ];
        $parameterDefinitions = [
            'price' => ['type' => 'currency'],
            'discount' => ['type' => 'percentage'],
            'date' => ['type' => 'date', 'date_format' => 'M j, Y'],
        ];

        $result = $this->service->processContent($content, $parameters, $parameterDefinitions);

        $this->assertStringContainsString('$99.99', $result);
        $this->assertStringContainsString('15.0%', $result);
        $this->assertStringContainsString('Jan 15, 2024', $result);
    }

    #[Test]
    public function it_handles_template_functions(): void
    {
        $testCases = [
            ['{{upper name}}', ['name' => 'john'], 'JOHN'],
            ['{{lower name}}', ['name' => 'JOHN'], 'john'],
            ['{{title name}}', ['name' => 'john doe'], 'John Doe'],
            ['{{capitalize name}}', ['name' => 'john'], 'John'],
            ['{{length items}}', ['items' => ['a', 'b', 'c']], '3'],
            ['{{slug title}}', ['title' => 'Hello World!'], 'hello-world'],
        ];

        foreach ($testCases as [$content, $parameters, $expected]) {
            $result = $this->service->processContent($content, $parameters);
            $this->assertEquals($expected, $result, "Failed for content: {$content}");
        }
    }

    #[Test]
    public function it_handles_missing_parameters_gracefully(): void
    {
        $content = 'Hello {{name}}, your role is {{missing_param}}';
        $parameters = ['name' => 'John'];

        $result = $this->service->processContent($content, $parameters);

        $this->assertEquals('Hello John, your role is ', $result);
    }

    #[Test]
    public function it_handles_nested_conditionals(): void
    {
        $content = '{{#if user}}{{#if user.premium}}Premium User{{/if}}{{/if}}';

        // Test with premium user
        $parameters = ['user' => ['premium' => true]];
        $result = $this->service->processContent($content, $parameters);
        $this->assertEquals('Premium User', $result);

        // Test with non-premium user
        $parameters = ['user' => ['premium' => false]];
        $result = $this->service->processContent($content, $parameters);
        $this->assertEquals('', $result);

        // Test with no user
        $parameters = [];
        $result = $this->service->processContent($content, $parameters);
        $this->assertEquals('', $result);
    }
}
