<?php

namespace JTD\LaravelAI\Tests\Feature\Documentation;

use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware;
use JTD\LaravelAI\Services\MiddlewareManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;

/**
 * Middleware Documentation Validation Test
 *
 * Validates that all middleware classes and methods have comprehensive
 * documentation following Laravel and PSR-5 PHPDoc standards.
 */
#[Group('documentation')]
#[Group('middleware')]
#[Group('validation')]
class MiddlewareDocumentationValidationTest extends TestCase
{
    /**
     * Test that AIMiddlewareInterface has comprehensive documentation.
     */
    #[Test]
    public function test_ai_middleware_interface_documentation(): void
    {
        $reflection = new ReflectionClass(AIMiddlewareInterface::class);

        // Check class-level documentation
        $docComment = $reflection->getDocComment();
        $this->assertNotFalse($docComment, 'AIMiddlewareInterface must have class documentation');
        $this->assertStringContainsString('@package', $docComment, 'Interface must have @package tag');
        $this->assertStringContainsString('@author', $docComment, 'Interface must have @author tag');
        $this->assertStringContainsString('@since', $docComment, 'Interface must have @since tag');

        // Check for comprehensive description
        $this->assertStringContainsString('middleware', $docComment, 'Documentation must explain middleware concept');
        $this->assertStringContainsString('pipeline', $docComment, 'Documentation must explain pipeline pattern');
        $this->assertStringContainsString('Usage Examples:', $docComment, 'Documentation must include usage examples');

        // Check handle method documentation
        $handleMethod = $reflection->getMethod('handle');
        $handleDocComment = $handleMethod->getDocComment();
        $this->assertNotFalse($handleDocComment, 'handle method must have documentation');
        $this->assertStringContainsString('@param', $handleDocComment, 'handle method must document parameters');
        $this->assertStringContainsString('@return', $handleDocComment, 'handle method must document return type');
        $this->assertStringContainsString('@throws', $handleDocComment, 'handle method must document exceptions');
        $this->assertStringContainsString('@since', $handleDocComment, 'handle method must have @since tag');
    }

    /**
     * Test that BudgetEnforcementMiddleware has comprehensive documentation.
     */
    #[Test]
    public function test_budget_enforcement_middleware_documentation(): void
    {
        $reflection = new ReflectionClass(BudgetEnforcementMiddleware::class);

        // Check class-level documentation
        $docComment = $reflection->getDocComment();
        $this->assertNotFalse($docComment, 'BudgetEnforcementMiddleware must have class documentation');
        $this->assertStringContainsString('@package', $docComment, 'Middleware must have @package tag');
        $this->assertStringContainsString('@author', $docComment, 'Middleware must have @author tag');
        $this->assertStringContainsString('@since', $docComment, 'Middleware must have @since tag');

        // Check for comprehensive description
        $this->assertStringContainsString('budget', $docComment, 'Documentation must explain budget functionality');
        $this->assertStringContainsString('enforcement', $docComment, 'Documentation must explain enforcement');
        $this->assertStringContainsString('Configuration:', $docComment, 'Documentation must include configuration section');
        $this->assertStringContainsString('Usage Examples:', $docComment, 'Documentation must include usage examples');
        $this->assertStringContainsString('Performance Targets:', $docComment, 'Documentation must include performance info');

        // Check constructor documentation
        $constructor = $reflection->getConstructor();
        if ($constructor) {
            $constructorDoc = $constructor->getDocComment();
            $this->assertNotFalse($constructorDoc, 'Constructor must have documentation');
            $this->assertStringContainsString('@param', $constructorDoc, 'Constructor must document all parameters');
            $this->assertStringContainsString('@since', $constructorDoc, 'Constructor must have @since tag');
        }

        // Check handle method documentation
        $handleMethod = $reflection->getMethod('handle');
        $handleDocComment = $handleMethod->getDocComment();
        $this->assertNotFalse($handleDocComment, 'handle method must have comprehensive documentation');
        $this->assertStringContainsString('@param', $handleDocComment, 'handle method must document parameters');
        $this->assertStringContainsString('@return', $handleDocComment, 'handle method must document return type');
        $this->assertStringContainsString('@throws', $handleDocComment, 'handle method must document exceptions');
        $this->assertStringContainsString('@since', $handleDocComment, 'handle method must have @since tag');

        // Validate that documentation explains the process flow
        $this->assertStringContainsString('enforcement process', $handleDocComment, 'Documentation must explain process flow');
        $this->assertStringContainsString('budget validation', $handleDocComment, 'Documentation must explain validation');
    }

    /**
     * Test that MiddlewareManager has comprehensive documentation.
     */
    #[Test]
    public function test_middleware_manager_documentation(): void
    {
        $reflection = new ReflectionClass(MiddlewareManager::class);

        // Check class-level documentation
        $docComment = $reflection->getDocComment();
        $this->assertNotFalse($docComment, 'MiddlewareManager must have class documentation');
        $this->assertStringContainsString('@package', $docComment, 'MiddlewareManager must have @package tag');
        $this->assertStringContainsString('@author', $docComment, 'MiddlewareManager must have @author tag');
        $this->assertStringContainsString('@since', $docComment, 'MiddlewareManager must have @since tag');

        // Check for comprehensive description
        $this->assertStringContainsString('middleware', $docComment, 'Documentation must explain middleware management');
        $this->assertStringContainsString('pipeline', $docComment, 'Documentation must explain pipeline architecture');
        $this->assertStringContainsString('Registration:', $docComment, 'Documentation must include registration examples');
        $this->assertStringContainsString('Usage:', $docComment, 'Documentation must include usage examples');
        $this->assertStringContainsString('Performance Targets:', $docComment, 'Documentation must include performance info');

        // Check public method documentation
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($publicMethods as $method) {
            if ($method->class === MiddlewareManager::class && ! $method->isConstructor()) {
                $methodDoc = $method->getDocComment();
                $this->assertNotFalse($methodDoc, "Public method {$method->getName()} must have documentation");

                // Check for required documentation elements
                if ($method->getNumberOfParameters() > 0) {
                    $this->assertStringContainsString('@param', $methodDoc, "Method {$method->getName()} must document parameters");
                }

                if (! $method->getReturnType() || $method->getReturnType()->getName() !== 'void') {
                    $this->assertStringContainsString('@return', $methodDoc, "Method {$method->getName()} must document return type");
                }

                $this->assertStringContainsString('@since', $methodDoc, "Method {$method->getName()} must have @since tag");
            }
        }
    }

    /**
     * Test that all middleware property documentation follows standards.
     */
    #[Test]
    public function test_middleware_property_documentation(): void
    {
        $reflection = new ReflectionClass(BudgetEnforcementMiddleware::class);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            if ($property->class === BudgetEnforcementMiddleware::class) {
                $propertyDoc = $property->getDocComment();
                $this->assertNotFalse($propertyDoc, "Property {$property->getName()} must have documentation");

                // Check for @var tag
                $this->assertStringContainsString('@var', $propertyDoc, "Property {$property->getName()} must have @var tag");

                // Check for descriptive comment
                $docLines = explode("\n", $propertyDoc);
                $hasDescription = false;
                foreach ($docLines as $line) {
                    $line = trim($line, ' /*');
                    if (! empty($line) && ! str_starts_with($line, '@')) {
                        $hasDescription = true;
                        break;
                    }
                }
                $this->assertTrue($hasDescription, "Property {$property->getName()} must have descriptive documentation");
            }
        }
    }

    /**
     * Test that middleware configuration documentation exists in config file.
     */
    #[Test]
    public function test_middleware_configuration_documentation(): void
    {
        $configPath = config_path('ai.php');
        $this->assertFileExists($configPath, 'AI configuration file must exist');

        $configContent = file_get_contents($configPath);

        // Check for middleware section
        $this->assertStringContainsString('AI Middleware System', $configContent, 'Config must have middleware section');
        $this->assertStringContainsString('pipeline pattern', $configContent, 'Config must explain pipeline pattern');
        $this->assertStringContainsString('Usage Examples:', $configContent, 'Config must include usage examples');

        // Check for global middleware documentation
        $this->assertStringContainsString('Global Middleware Configuration', $configContent, 'Config must document global middleware');
        $this->assertStringContainsString('Available Middleware Registry', $configContent, 'Config must document available middleware');

        // Check for performance documentation
        $this->assertStringContainsString('Performance Configuration', $configContent, 'Config must document performance settings');
        $this->assertStringContainsString('Error Handling Configuration', $configContent, 'Config must document error handling');

        // Check for environment variable documentation
        $this->assertStringContainsString('AI_MIDDLEWARE_ENABLED', $configContent, 'Config must document environment variables');
        $this->assertStringContainsString('AI_BUDGET_ENFORCEMENT_ENABLED', $configContent, 'Config must document budget enforcement variables');
    }

    /**
     * Test that README contains comprehensive middleware documentation.
     */
    #[Test]
    public function test_readme_middleware_documentation(): void
    {
        $readmePath = base_path('README.md');
        $this->assertFileExists($readmePath, 'README.md file must exist');

        $readmeContent = file_get_contents($readmePath);

        // Check for middleware section
        $this->assertStringContainsString('## Middleware System', $readmeContent, 'README must have middleware section');
        $this->assertStringContainsString('Budget Enforcement Middleware', $readmeContent, 'README must document budget enforcement');
        $this->assertStringContainsString('Cost Tracking Middleware', $readmeContent, 'README must document cost tracking');

        // Check for usage patterns
        $this->assertStringContainsString('ConversationBuilder Pattern', $readmeContent, 'README must document ConversationBuilder usage');
        $this->assertStringContainsString('Direct SendMessage Pattern', $readmeContent, 'README must document Direct SendMessage usage');

        // Check for configuration examples
        $this->assertStringContainsString('### Configuration', $readmeContent, 'README must include configuration section');
        $this->assertStringContainsString('Budget Management', $readmeContent, 'README must document budget management');

        // Check for error handling examples
        $this->assertStringContainsString('BudgetExceededException', $readmeContent, 'README must document exception handling');

        // Check for performance information
        $this->assertStringContainsString('Performance Features', $readmeContent, 'README must document performance features');
        $this->assertStringContainsString('<10ms execution overhead', $readmeContent, 'README must mention performance targets');
    }

    /**
     * Test that specification document has implementation details.
     */
    #[Test]
    public function test_specification_implementation_details(): void
    {
        $specPath = base_path('docs/BUDGET_COST_TRACKING_SPECIFICATION.md');
        $this->assertFileExists($specPath, 'Budget cost tracking specification must exist');

        $specContent = file_get_contents($specPath);

        // Check for implementation details section
        $this->assertStringContainsString('## Implementation Details', $specContent, 'Specification must have implementation details');
        $this->assertStringContainsString('Middleware System Architecture', $specContent, 'Specification must document middleware architecture');

        // Check for middleware components documentation
        $this->assertStringContainsString('BudgetEnforcementMiddleware', $specContent, 'Specification must document budget enforcement');
        $this->assertStringContainsString('CostTrackingMiddleware', $specContent, 'Specification must document cost tracking');
        $this->assertStringContainsString('MiddlewareManager', $specContent, 'Specification must document middleware manager');

        // Check for service layer documentation
        $this->assertStringContainsString('Service Layer Implementation', $specContent, 'Specification must document service layer');
        $this->assertStringContainsString('BudgetService', $specContent, 'Specification must document BudgetService');
        $this->assertStringContainsString('PricingService', $specContent, 'Specification must document PricingService');

        // Check for event system documentation
        $this->assertStringContainsString('Event System Implementation', $specContent, 'Specification must document event system');
        $this->assertStringContainsString('CostCalculated', $specContent, 'Specification must document CostCalculated event');
        $this->assertStringContainsString('BudgetThresholdReached', $specContent, 'Specification must document BudgetThresholdReached event');
    }

    /**
     * Test PSR-5 PHPDoc compliance for middleware classes.
     */
    #[Test]
    public function test_phpdoc_psr5_compliance(): void
    {
        $middlewareClasses = [
            AIMiddlewareInterface::class,
            BudgetEnforcementMiddleware::class,
            MiddlewareManager::class,
        ];

        foreach ($middlewareClasses as $className) {
            $reflection = new ReflectionClass($className);
            $docComment = $reflection->getDocComment();

            if ($docComment) {
                // Check for valid PHPDoc structure
                $this->assertStringStartsWith('/**', $docComment, "PHPDoc for {$className} must start with /**");
                $this->assertStringEndsWith('*/', $docComment, "PHPDoc for {$className} must end with */");

                // Check for proper tag formatting
                if (str_contains($docComment, '@param')) {
                    $this->assertMatchesRegularExpression('/@param\s+\S+\s+\$\w+\s+.+/', $docComment,
                        "PHPDoc @param tags for {$className} must follow PSR-5 format");
                }

                if (str_contains($docComment, '@return')) {
                    $this->assertMatchesRegularExpression('/@return\s+\S+\s+.+/', $docComment,
                        "PHPDoc @return tags for {$className} must follow PSR-5 format");
                }

                if (str_contains($docComment, '@throws')) {
                    $this->assertMatchesRegularExpression('/@throws\s+\S+\s+.+/', $docComment,
                        "PHPDoc @throws tags for {$className} must follow PSR-5 format");
                }
            }
        }
    }

    /**
     * Test that all middleware documentation includes required sections.
     */
    #[Test]
    public function test_middleware_documentation_completeness(): void
    {
        $requiredSections = [
            'Purpose/Description',
            'Configuration',
            'Usage Examples',
            'Performance Information',
            'Error Handling',
        ];

        $reflection = new ReflectionClass(BudgetEnforcementMiddleware::class);
        $docComment = $reflection->getDocComment();

        // Check that documentation covers all required aspects
        $this->assertStringContainsString('budget', $docComment, 'Must explain purpose');
        $this->assertStringContainsString('Configuration:', $docComment, 'Must include configuration section');
        $this->assertStringContainsString('Usage Examples:', $docComment, 'Must include usage examples');
        $this->assertStringContainsString('Performance', $docComment, 'Must include performance information');
        $this->assertStringContainsString('<10ms', $docComment, 'Must specify performance targets');

        // Verify code examples are present
        $this->assertStringContainsString('```php', $docComment, 'Must include code examples');
        $this->assertStringContainsString('AI::', $docComment, 'Must show AI facade usage');
    }
}
