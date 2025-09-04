<?php

namespace JTD\LaravelAI\Tests\Feature\CodeStandards;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Test to enforce PHPUnit attributes instead of deprecated docblock annotations
 * This test extends PHPUnit's base TestCase to avoid Laravel setup overhead
 */
class TestAttributeEnforcementTest extends BaseTestCase
{
    #[Test]
    #[Group('code-standards')]
    public function it_ensures_all_test_files_use_attributes_not_docblock_annotations(): void
    {
        $testDirectory = __DIR__ . '/../../';
        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($testDirectory)
        );
        
        $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i', RegexIterator::GET_MATCH);

        foreach ($phpFiles as $file) {
            $filePath = $file[0];
            
            // Skip this test file itself and non-test files
            if (str_contains($filePath, 'TestAttributeEnforcementTest.php') || 
                !str_contains($filePath, 'Test.php')) {
                continue;
            }

            $content = file_get_contents($filePath);
            
            // Check for deprecated PHPUnit docblock annotations
            $deprecatedAnnotations = [
                '@test',
                '@group',
                '@dataProvider',
                '@depends',
                '@covers',
                '@uses',
                '@coversDefaultClass',
                '@coversNothing',
                '@backupGlobals',
                '@backupStaticAttributes',
                '@preserveGlobalState',
                '@runInSeparateProcess',
                '@runTestsInSeparateProcesses',
                '@expectedException',
                '@expectedExceptionCode',
                '@expectedExceptionMessage',
                '@expectedExceptionMessageRegExp',
            ];

            $foundViolations = [];
            
            foreach ($deprecatedAnnotations as $annotation) {
                if (preg_match('/\*\s*' . preg_quote($annotation, '/') . '\b/', $content)) {
                    $foundViolations[] = $annotation;
                }
            }

            if (!empty($foundViolations)) {
                $violations[$filePath] = $foundViolations;
            }
        }

        $this->assertEmpty(
            $violations,
            "The following test files contain deprecated PHPUnit docblock annotations instead of attributes:\n" .
            $this->formatViolations($violations) .
            "\n\nPlease replace docblock annotations with PHP attributes:\n" .
            "- @test → #[Test]\n" .
            "- @group → #[Group('group-name')]\n" .
            "- @dataProvider → #[DataProvider('methodName')]\n" .
            "- @depends → #[Depends('methodName')]\n" .
            "- @covers → #[CoversClass(ClassName::class)]\n" .
            "And add the appropriate use statements:\n" .
            "use PHPUnit\\Framework\\Attributes\\Test;\n" .
            "use PHPUnit\\Framework\\Attributes\\Group;\n" .
            "etc."
        );
    }

    #[Test]
    #[Group('code-standards')]
    public function it_ensures_test_methods_have_proper_attributes(): void
    {
        $testDirectory = __DIR__ . '/../../';
        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($testDirectory)
        );
        
        $phpFiles = new RegexIterator($iterator, '/^.+Test\.php$/i', RegexIterator::GET_MATCH);

        foreach ($phpFiles as $file) {
            $filePath = $file[0];
            
            // Skip this test file itself
            if (str_contains($filePath, 'TestAttributeEnforcementTest.php')) {
                continue;
            }

            $content = file_get_contents($filePath);
            
            // Find all public methods that look like test methods
            preg_match_all('/public function (test_|it_)[^(]+\([^)]*\)(?:\s*:\s*\w+)?/', $content, $testMethods);
            
            if (!empty($testMethods[0])) {
                // Check if the file uses #[Test] attributes
                $hasTestAttribute = str_contains($content, '#[Test]');
                $hasTestAnnotation = preg_match('/\*\s*@test\b/', $content);
                
                // Check for test methods that start with 'test_' without #[Test] attribute
                preg_match_all('/public function (test_[^(]+)\([^)]*\)(?:\s*:\s*\w+)?/', $content, $testPrefixMethods);
                
                if (!empty($testPrefixMethods[0]) && !$hasTestAttribute && !$hasTestAnnotation) {
                    // Methods with test_ prefix don't need #[Test] attribute, this is fine
                    continue;
                }
                
                // Check for methods starting with 'it_' that should have #[Test] attribute
                preg_match_all('/public function (it_[^(]+)\([^)]*\)(?:\s*:\s*\w+)?/', $content, $itMethods);
                
                if (!empty($itMethods[0]) && !$hasTestAttribute) {
                    $violations[$filePath] = [
                        'issue' => 'Methods starting with "it_" found but no #[Test] attributes detected',
                        'methods' => $itMethods[1]
                    ];
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "The following test files have test methods that need proper #[Test] attributes:\n" . 
            $this->formatMethodViolations($violations)
        );
    }

    #[Test]
    #[Group('code-standards')]
    public function it_ensures_test_files_import_required_attribute_classes(): void
    {
        $testDirectory = __DIR__ . '/../../';
        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($testDirectory)
        );
        
        $phpFiles = new RegexIterator($iterator, '/^.+Test\.php$/i', RegexIterator::GET_MATCH);

        foreach ($phpFiles as $file) {
            $filePath = $file[0];
            
            // Skip this test file itself
            if (str_contains($filePath, 'TestAttributeEnforcementTest.php')) {
                continue;
            }

            $content = file_get_contents($filePath);
            
            $requiredImports = [];
            $missingImports = [];
            
            // Check which attributes are used and which imports are needed
            if (str_contains($content, '#[Test]')) {
                $requiredImports['Test'] = 'use PHPUnit\\Framework\\Attributes\\Test;';
            }
            
            if (str_contains($content, '#[Group(')) {
                $requiredImports['Group'] = 'use PHPUnit\\Framework\\Attributes\\Group;';
            }
            
            if (str_contains($content, '#[DataProvider(')) {
                $requiredImports['DataProvider'] = 'use PHPUnit\\Framework\\Attributes\\DataProvider;';
            }
            
            if (str_contains($content, '#[Depends(')) {
                $requiredImports['Depends'] = 'use PHPUnit\\Framework\\Attributes\\Depends;';
            }
            
            if (str_contains($content, '#[CoversClass(')) {
                $requiredImports['CoversClass'] = 'use PHPUnit\\Framework\\Attributes\\CoversClass;';
            }
            
            // Check if required imports are present
            foreach ($requiredImports as $attribute => $importStatement) {
                if (!str_contains($content, $importStatement)) {
                    $missingImports[] = $importStatement;
                }
            }
            
            if (!empty($missingImports)) {
                $violations[$filePath] = $missingImports;
            }
        }

        $this->assertEmpty(
            $violations,
            "The following test files are missing required import statements for PHPUnit attributes:\n" .
            $this->formatImportViolations($violations)
        );
    }

    private function formatViolations(array $violations): string
    {
        $output = [];
        foreach ($violations as $file => $annotations) {
            $output[] = "  - " . basename($file) . ": " . implode(', ', $annotations);
        }
        return implode("\n", $output);
    }

    private function formatMethodViolations(array $violations): string
    {
        $output = [];
        foreach ($violations as $file => $data) {
            $output[] = "  - " . basename($file) . ": " . $data['issue'];
            if (!empty($data['methods'])) {
                foreach ($data['methods'] as $method) {
                    $output[] = "    → " . $method . "()";
                }
            }
        }
        return implode("\n", $output);
    }

    private function formatImportViolations(array $violations): string
    {
        $output = [];
        foreach ($violations as $file => $imports) {
            $output[] = "  - " . basename($file) . ":";
            foreach ($imports as $import) {
                $output[] = "    Missing: " . $import;
            }
        }
        return implode("\n", $output);
    }
}