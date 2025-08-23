<?php

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Template Generator using PHP Reflection
 *
 * This script uses PHP reflection to properly analyze OpenAI classes
 * and generate clean, accurate template files.
 */
class TemplateGenerator
{
    private array $sourceFiles = [
        // Source files
        'src/Drivers/OpenAI/OpenAIDriver.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\OpenAIDriver',
        'src/Drivers/OpenAI/Support/ErrorMapper.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Support\\ErrorMapper',
        'src/Drivers/OpenAI/Support/ModelCapabilities.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Support\\ModelCapabilities',
        'src/Drivers/OpenAI/Support/ModelPricing.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Support\\ModelPricing',
        'src/Drivers/OpenAI/Traits/CalculatesCosts.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Traits\\CalculatesCosts',
        'src/Drivers/OpenAI/Traits/HandlesApiCommunication.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Traits\\HandlesApiCommunication',
        'src/Drivers/OpenAI/Traits/HandlesErrors.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Traits\\HandlesErrors',
        'src/Drivers/OpenAI/Traits/HandlesFunctionCalling.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Traits\\HandlesFunctionCalling',
        'src/Drivers/OpenAI/Traits/IntegratesResponsesAPI.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Traits\\IntegratesResponsesAPI',
        'src/Drivers/OpenAI/Traits/ManagesModels.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Traits\\ManagesModels',
        'src/Drivers/OpenAI/Traits/SupportsStreaming.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Traits\\SupportsStreaming',
        'src/Drivers/OpenAI/Traits/ValidatesHealth.php' => 'JTD\\LaravelAI\\Drivers\\OpenAI\\Traits\\ValidatesHealth',
    ];

    private array $exceptionFiles = [
        // Exception files
        'src/Exceptions/OpenAI/OpenAIException.php' => 'JTD\\LaravelAI\\Exceptions\\OpenAI\\OpenAIException',
        'src/Exceptions/OpenAI/OpenAIInvalidCredentialsException.php' => 'JTD\\LaravelAI\\Exceptions\\OpenAI\\OpenAIInvalidCredentialsException',
        'src/Exceptions/OpenAI/OpenAIQuotaExceededException.php' => 'JTD\\LaravelAI\\Exceptions\\OpenAI\\OpenAIQuotaExceededException',
        'src/Exceptions/OpenAI/OpenAIRateLimitException.php' => 'JTD\\LaravelAI\\Exceptions\\OpenAI\\OpenAIRateLimitException',
        'src/Exceptions/OpenAI/OpenAIServerException.php' => 'JTD\\LaravelAI\\Exceptions\\OpenAI\\OpenAIServerException',
    ];

    private array $testFiles = [
        'tests/Unit/Drivers/OpenAI/OpenAIDriverTest.php',
        'tests/Unit/Drivers/OpenAI/OpenAIErrorHandlingAndRetryTest.php',
        'tests/Unit/Drivers/OpenAI/OpenAIFunctionCallingErrorTest.php',
        'tests/Unit/Drivers/OpenAI/OpenAIFunctionCallingTest.php',
        'tests/Unit/Drivers/OpenAI/OpenAIStreamingErrorTest.php',
        'tests/Unit/Drivers/OpenAI/OpenAIStreamingTest.php',
        'tests/Unit/Drivers/OpenAI/Traits/ManagesModelsSyncTest.php',
        'tests/Performance/Drivers/OpenAI/OpenAIDriverPerformanceTest.php',
        'tests/E2E/Drivers/OpenAI/OpenAIAdvancedIntegrationTest.php',
        'tests/E2E/Drivers/OpenAI/OpenAIComprehensiveE2ETest.php',
        'tests/E2E/Drivers/OpenAI/OpenAIFunctionCallingE2ETest.php',
        'tests/E2E/Drivers/OpenAI/OpenAIFunctionCallingTest.php',
        'tests/E2E/Drivers/OpenAI/OpenAIQuotaErrorTest.php',
        'tests/E2E/Drivers/OpenAI/OpenAIResponsesAPIDriverTest.php',
        'tests/E2E/Drivers/OpenAI/OpenAIResponsesAPITest.php',
        'tests/E2E/Drivers/OpenAI/OpenAIStreamingE2ETest.php',
        'tests/E2E/Drivers/OpenAI/OpenAIStreamingTest.php',
        'tests/E2E/Drivers/OpenAI/OpenAISuccessfulCallsTest.php',
    ];

    public function generateTemplates(): void
    {
        echo "Starting template generation using PHP reflection...\n\n";

        // Clean up existing templates
        if (is_dir('docs/templates/drivers/src')) {
            $this->removeDirectory('docs/templates/drivers/src');
        }
        if (is_dir('docs/templates/drivers/tests')) {
            $this->removeDirectory('docs/templates/drivers/tests');
        }

        // Generate source templates using reflection
        echo "Generating source templates...\n";
        foreach ($this->sourceFiles as $filePath => $className) {
            $this->generateSourceTemplate($filePath, $className);
        }

        // Generate exception templates using reflection
        echo "\nGenerating exception templates...\n";
        foreach ($this->exceptionFiles as $filePath => $className) {
            $this->generateExceptionTemplate($filePath, $className);
        }

        // Generate test templates using file parsing (since they're not loaded classes)
        echo "\nGenerating test templates...\n";
        foreach ($this->testFiles as $filePath) {
            $this->generateTestTemplate($filePath);
        }

        echo "\n✅ Template generation completed successfully!\n";
        echo 'Generated ' . (count($this->sourceFiles) + count($this->exceptionFiles) + count($this->testFiles)) . " template files.\n";
    }

    private function generateSourceTemplate(string $filePath, string $className): void
    {
        if (! file_exists($filePath)) {
            echo "❌ Source file not found: $filePath\n";

            return;
        }

        try {
            $reflection = new ReflectionClass($className);
            $templatePath = $this->getTemplatePath($filePath);

            // Create directory
            $dir = dirname($templatePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $content = $this->generateClassTemplate($reflection, $filePath);
            file_put_contents($templatePath, $content);

            echo "✅ Generated: $templatePath\n";
        } catch (ReflectionException $e) {
            echo "⚠️  Could not reflect class $className: " . $e->getMessage() . "\n";
            // Fall back to file-based generation
            $this->generateTestTemplate($filePath);
        }
    }

    private function generateExceptionTemplate(string $filePath, string $className): void
    {
        if (! file_exists($filePath)) {
            echo "❌ Exception file not found: $filePath\n";

            return;
        }

        try {
            $reflection = new ReflectionClass($className);
            $templatePath = $this->getExceptionTemplatePath($filePath);

            // Create directory
            $dir = dirname($templatePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $content = $this->generateClassTemplate($reflection, $filePath);
            file_put_contents($templatePath, $content);

            echo "✅ Generated: $templatePath\n";
        } catch (ReflectionException $e) {
            echo "⚠️  Could not reflect exception class $className: " . $e->getMessage() . "\n";
            // Fall back to file-based generation
            $this->generateTestTemplate($filePath);
        }
    }

    private function generateClassTemplate(ReflectionClass $reflection, string $originalFile): string
    {
        $originalContent = file_get_contents($originalFile);

        // Extract the file header (up to class declaration)
        $lines = explode("\n", $originalContent);
        $headerLines = [];

        foreach ($lines as $line) {
            $headerLines[] = $line;
            if (preg_match('/^(class|trait|interface|abstract class)\s+\w+/', trim($line))) {
                $headerLines[] = '{';
                break;
            }
        }

        // Transform the header
        $header = implode("\n", $headerLines);
        $header = $this->transformContent($header);

        $content = $header . "\n";

        // Add properties
        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === $reflection->getName()) {
                $content .= $this->generatePropertyTemplate($property);
            }
        }

        // Add methods
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $reflection->getName()) {
                $content .= $this->generateMethodTemplate($method);
            }
        }

        $content .= "}\n";

        return $content;
    }

    private function generatePropertyTemplate(ReflectionProperty $property): string
    {
        $modifiers = Reflection::getModifierNames($property->getModifiers());
        $modifierStr = implode(' ', $modifiers);

        $docComment = $property->getDocComment();
        $propertyLine = '';

        if ($docComment) {
            $propertyLine .= '    ' . $docComment . "\n";
        }

        $propertyLine .= "    {$modifierStr} \${$property->getName()}";

        // Try to get default value if it's accessible
        if ($property->isPublic() || $property->isProtected()) {
            try {
                if ($property->hasDefaultValue()) {
                    $defaultValue = $property->getDefaultValue();
                    if (is_string($defaultValue)) {
                        $defaultValue = "'" . str_replace('openai', 'drivertemplate', $defaultValue) . "'";
                    } elseif (is_array($defaultValue)) {
                        $defaultValue = '[]';
                    } elseif (is_null($defaultValue)) {
                        $defaultValue = 'null';
                    } else {
                        $defaultValue = var_export($defaultValue, true);
                    }
                    $propertyLine .= " = {$defaultValue}";
                }
            } catch (Exception $e) {
                // Skip default value if we can't get it
            }
        }

        $propertyLine .= ";\n\n";

        return $propertyLine;
    }

    private function generateMethodTemplate(ReflectionMethod $method): string
    {
        $modifiers = Reflection::getModifierNames($method->getModifiers());
        $modifierStr = implode(' ', $modifiers);

        $docComment = $method->getDocComment();
        $methodTemplate = '';

        if ($docComment) {
            $transformedDoc = $this->transformContent($docComment);
            $methodTemplate .= '    ' . $transformedDoc . "\n";
        }

        // Build method signature
        $params = [];
        foreach ($method->getParameters() as $param) {
            $paramStr = '';

            if ($param->hasType()) {
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType) {
                    $paramStr .= $type->getName() . ' ';
                }
            }

            if ($param->isPassedByReference()) {
                $paramStr .= '&';
            }

            if ($param->isVariadic()) {
                $paramStr .= '...';
            }

            $paramStr .= '$' . $param->getName();

            if ($param->isDefaultValueAvailable()) {
                try {
                    $defaultValue = $param->getDefaultValue();
                    if (is_null($defaultValue)) {
                        $paramStr .= ' = null';
                    } elseif (is_array($defaultValue)) {
                        $paramStr .= ' = []';
                    } elseif (is_string($defaultValue)) {
                        $paramStr .= " = '" . $defaultValue . "'";
                    } else {
                        $paramStr .= ' = ' . var_export($defaultValue, true);
                    }
                } catch (Exception $e) {
                    // Skip default value if we can't get it
                }
            }

            $params[] = $paramStr;
        }

        $returnType = '';
        if ($method->hasReturnType()) {
            $type = $method->getReturnType();
            if ($type instanceof ReflectionNamedType) {
                $returnType = ': ' . $type->getName();
            }
        }

        $methodTemplate .= "    {$modifierStr} function {$method->getName()}(" . implode(', ', $params) . "){$returnType}\n";
        $methodTemplate .= "    {\n";
        $methodTemplate .= "        // TODO: Implement {$method->getName()}\n";
        $methodTemplate .= "    }\n\n";

        return $methodTemplate;
    }

    private function generateTestTemplate(string $filePath): void
    {
        if (! file_exists($filePath)) {
            echo "❌ Test file not found: $filePath\n";

            return;
        }

        $templatePath = $this->getTemplatePath($filePath);

        // Create directory
        $dir = dirname($templatePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = file_get_contents($filePath);
        $transformedContent = $this->transformTestContent($content);

        file_put_contents($templatePath, $transformedContent);
        echo "✅ Generated: $templatePath\n";
    }

    private function transformContent(string $content): string
    {
        // Replace namespaces
        $content = str_replace('JTD\\LaravelAI\\Drivers\\OpenAI', 'JTD\\LaravelAI\\Drivers\\DriverTemplate', $content);
        $content = str_replace('JTD\\LaravelAI\\Exceptions\\OpenAI', 'JTD\\LaravelAI\\Exceptions\\DriverTemplate', $content);

        // Replace class names and references
        $content = str_replace('OpenAI', 'DriverTemplate', $content);
        $content = str_replace('openai', 'drivertemplate', $content);

        // Replace specific references
        $content = str_replace('platform.openai.com', 'platform.drivertemplate.com', $content);
        $content = str_replace('OPENAI_DRIVER.md', 'DRIVERTEMPLATE_DRIVER.md', $content);
        $content = str_replace('gpt-', 'default-model-', $content);
        $content = str_replace('sk-', 'api-key-', $content);

        // Fix import statements
        $content = str_replace('use OpenAI;', '// TODO: Add appropriate client import', $content);

        return $content;
    }

    private function transformTestContent(string $content): string
    {
        $content = $this->transformContent($content);

        // Replace method bodies in test methods with TODO comments
        $content = preg_replace_callback(
            '/^(\s*)(#\[Test\]\s*\n\s*public function \w+\([^)]*\):\s*void\s*\n\s*\{)(.*?)(\n\s*\})/ms',
            function ($matches) {
                $indent = $matches[1];
                $signature = $matches[2];
                $closing = $matches[4];

                return $indent . $signature . "\n" . $indent . '    // TODO: Implement test' . $closing;
            },
            $content
        );

        return $content;
    }

    private function getTemplatePath(string $originalPath): string
    {
        $templatePath = str_replace(
            ['src/Drivers/OpenAI/', 'tests/Unit/Drivers/OpenAI/', 'tests/Performance/Drivers/OpenAI/', 'tests/E2E/Drivers/OpenAI/'],
            ['docs/templates/drivers/src/Drivers/DriverTemplate/', 'docs/templates/drivers/tests/Unit/Drivers/DriverTemplate/', 'docs/templates/drivers/tests/Performance/Drivers/DriverTemplate/', 'docs/templates/drivers/tests/E2E/Drivers/DriverTemplate/'],
            $originalPath
        );

        return str_replace('OpenAI', 'DriverTemplate', $templatePath);
    }

    private function getExceptionTemplatePath(string $originalPath): string
    {
        $templatePath = str_replace(
            'src/Exceptions/OpenAI/',
            'docs/templates/drivers/src/Exceptions/DriverTemplate/',
            $originalPath
        );

        return str_replace('OpenAI', 'DriverTemplate', $templatePath);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}

// Run the generator
$generator = new TemplateGenerator;
$generator->generateTemplates();
