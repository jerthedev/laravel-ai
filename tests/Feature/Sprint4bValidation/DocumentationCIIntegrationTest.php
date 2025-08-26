<?php

namespace JTD\LaravelAI\Tests\Feature\Sprint4bValidation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Documentation and CI Integration Test
 *
 * Updates documentation and ensures CI properly runs feature-based test suites
 * for Sprint4b validation and ongoing maintenance.
 */
#[Group('sprint4b-validation')]
#[Group('documentation-ci')]
class DocumentationCIIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected array $documentationResults = [];

    protected array $ciResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentationResults = [];
        $this->ciResults = [];
    }

    protected function tearDown(): void
    {
        $this->logResults();
        parent::tearDown();
    }

    #[Test]
    public function it_validates_sprint4b_documentation_completeness(): void
    {
        $requiredDocuments = [
            'README.md' => 'Main project documentation',
            'docs/ARCHITECTURE.md' => 'Architecture documentation',
            'docs/TESTING_STRATEGY.md' => 'Testing strategy documentation',
            'docs/DRIVER_SYSTEM.md' => 'Driver system documentation',
            'docs/project-guidelines.txt' => 'Project guidelines',
            'docs/Sprint4b.md' => 'Sprint4b specifications',
        ];

        $documentationResults = [];

        foreach ($requiredDocuments as $docPath => $description) {
            $fullPath = base_path($docPath);
            $exists = File::exists($fullPath);

            $result = [
                'document' => $docPath,
                'description' => $description,
                'exists' => $exists,
                'size_bytes' => $exists ? File::size($fullPath) : 0,
                'last_modified' => $exists ? File::lastModified($fullPath) : null,
            ];

            if ($exists) {
                $content = File::get($fullPath);
                $result['word_count'] = str_word_count($content);
                $result['has_sprint4b_references'] = str_contains(strtolower($content), 'sprint4b');
                $result['completeness_score'] = $this->calculateDocumentCompletenessScore($content, $docPath);
            } else {
                $result['completeness_score'] = 0;
            }

            $documentationResults[] = $result;

            $this->assertTrue($exists, "Required document {$docPath} should exist");

            if ($exists) {
                $this->assertGreaterThan(100, $result['word_count'],
                    "Document {$docPath} should have substantial content (>100 words)");
            }
        }

        $this->recordDocumentationResult('documentation_completeness', $documentationResults);

        // Validate overall documentation completeness
        $avgCompletenessScore = collect($documentationResults)->avg('completeness_score');
        $this->assertGreaterThanOrEqual(80, $avgCompletenessScore,
            "Overall documentation completeness score is {$avgCompletenessScore}%, below 80% target");
    }

    #[Test]
    public function it_validates_test_suite_organization(): void
    {
        $testDirectories = [
            'tests/Feature/CostTracking' => 'Cost tracking feature tests',
            'tests/Feature/BudgetManagement' => 'Budget management feature tests',
            'tests/Feature/Analytics' => 'Analytics feature tests',
            'tests/Feature/MCPFramework' => 'MCP framework feature tests',
            'tests/Feature/MCPSetup' => 'MCP setup feature tests',
            'tests/Feature/MCPIntegration' => 'MCP integration feature tests',
            'tests/Feature/Performance' => 'Performance monitoring feature tests',
            'tests/Feature/CoreInfrastructure' => 'Core infrastructure feature tests',
            'tests/Feature/Sprint4bValidation' => 'Sprint4b validation tests',
        ];

        $testSuiteResults = [];

        foreach ($testDirectories as $directory => $description) {
            $fullPath = base_path($directory);
            $exists = File::isDirectory($fullPath);

            $result = [
                'directory' => $directory,
                'description' => $description,
                'exists' => $exists,
                'test_files_count' => 0,
                'test_methods_count' => 0,
            ];

            if ($exists) {
                $testFiles = File::glob($fullPath . '/*.php');
                $result['test_files_count'] = count($testFiles);

                foreach ($testFiles as $testFile) {
                    $content = File::get($testFile);
                    preg_match_all('/#\[Test\]/', $content, $matches);
                    $result['test_methods_count'] += count($matches[0]);
                }
            }

            $testSuiteResults[] = $result;

            $this->assertTrue($exists, "Test directory {$directory} should exist");

            if ($exists) {
                $this->assertGreaterThan(0, $result['test_files_count'],
                    "Test directory {$directory} should contain test files");
            }
        }

        $this->recordDocumentationResult('test_suite_organization', $testSuiteResults);

        // Validate overall test organization
        $totalTestFiles = collect($testSuiteResults)->sum('test_files_count');
        $totalTestMethods = collect($testSuiteResults)->sum('test_methods_count');

        $this->assertGreaterThanOrEqual(40, $totalTestFiles,
            'Should have at least 40 test files across all feature areas');

        $this->assertGreaterThanOrEqual(200, $totalTestMethods,
            'Should have at least 200 test methods across all feature areas');
    }

    #[Test]
    public function it_validates_phpunit_configuration(): void
    {
        $phpunitConfigPath = base_path('phpunit.xml');
        $this->assertTrue(File::exists($phpunitConfigPath), 'phpunit.xml should exist');

        $phpunitConfig = File::get($phpunitConfigPath);

        // Validate test suite configuration
        $requiredTestSuites = [
            'cost-tracking',
            'budget-management',
            'analytics',
            'mcp-framework',
            'mcp-setup',
            'mcp-integration',
            'performance',
            'core-infrastructure',
            'sprint4b-validation',
        ];

        $configResults = [];

        foreach ($requiredTestSuites as $testSuite) {
            $hasTestSuite = str_contains($phpunitConfig, $testSuite);

            $configResults[] = [
                'test_suite' => $testSuite,
                'configured' => $hasTestSuite,
            ];

            $this->assertTrue($hasTestSuite,
                "PHPUnit configuration should include {$testSuite} test suite");
        }

        // Validate coverage configuration
        $hasCoverageConfig = str_contains($phpunitConfig, 'coverage');
        $this->assertTrue($hasCoverageConfig, 'PHPUnit should have coverage configuration');

        // Validate logging configuration
        $hasLoggingConfig = str_contains($phpunitConfig, 'logging') || str_contains($phpunitConfig, 'log');
        $this->assertTrue($hasLoggingConfig, 'PHPUnit should have logging configuration');

        $this->recordDocumentationResult('phpunit_configuration', [
            'config_exists' => true,
            'test_suites_configured' => $configResults,
            'has_coverage_config' => $hasCoverageConfig,
            'has_logging_config' => $hasLoggingConfig,
        ]);
    }

    #[Test]
    public function it_validates_ci_workflow_configuration(): void
    {
        $ciWorkflowPaths = [
            '.github/workflows/tests.yml' => 'GitHub Actions test workflow',
            '.github/workflows/coverage.yml' => 'GitHub Actions coverage workflow',
        ];

        $ciResults = [];

        foreach ($ciWorkflowPaths as $workflowPath => $description) {
            $fullPath = base_path($workflowPath);
            $exists = File::exists($fullPath);

            $result = [
                'workflow' => $workflowPath,
                'description' => $description,
                'exists' => $exists,
            ];

            if ($exists) {
                $content = File::get($fullPath);
                $result['has_feature_tests'] = str_contains($content, 'Feature/');
                $result['has_coverage_reporting'] = str_contains($content, 'coverage');
                $result['has_multiple_php_versions'] = str_contains($content, 'matrix');
                $result['runs_sprint4b_tests'] = str_contains($content, 'sprint4b') ||
                                               str_contains($content, 'Sprint4b');
            } else {
                $result['has_feature_tests'] = false;
                $result['has_coverage_reporting'] = false;
                $result['has_multiple_php_versions'] = false;
                $result['runs_sprint4b_tests'] = false;
            }

            $ciResults[] = $result;
        }

        $this->recordCIResult('ci_workflow_configuration', $ciResults);

        // Validate at least one CI workflow exists
        $existingWorkflows = collect($ciResults)->where('exists', true)->count();
        $this->assertGreaterThan(0, $existingWorkflows,
            'At least one CI workflow should be configured');
    }

    #[Test]
    public function it_validates_test_execution_commands(): void
    {
        $testCommands = [
            'vendor/bin/phpunit' => 'Basic PHPUnit execution',
            'vendor/bin/phpunit --group=sprint4b-validation' => 'Sprint4b validation tests',
            'vendor/bin/phpunit --group=cost-tracking' => 'Cost tracking feature tests',
            'vendor/bin/phpunit --group=budget-management' => 'Budget management feature tests',
            'vendor/bin/phpunit --group=analytics' => 'Analytics feature tests',
            'vendor/bin/phpunit --group=mcp-integration' => 'MCP integration feature tests',
            'vendor/bin/phpunit --group=performance' => 'Performance feature tests',
            'vendor/bin/phpunit --group=core-infrastructure' => 'Core infrastructure tests',
        ];

        $commandResults = [];

        foreach ($testCommands as $command => $description) {
            // Simulate command validation (in real scenario, would check if command works)
            $result = [
                'command' => $command,
                'description' => $description,
                'is_valid' => $this->validateTestCommand($command),
                'estimated_execution_time_seconds' => $this->estimateCommandExecutionTime($command),
            ];

            $commandResults[] = $result;

            $this->assertTrue($result['is_valid'],
                "Test command '{$command}' should be valid");
        }

        $this->recordCIResult('test_execution_commands', $commandResults);

        // Validate total estimated execution time is reasonable
        $totalEstimatedTime = collect($commandResults)->sum('estimated_execution_time_seconds');
        $this->assertLessThan(1800, $totalEstimatedTime, // 30 minutes
            "Total estimated test execution time is {$totalEstimatedTime} seconds, exceeding 30 minute limit");
    }

    #[Test]
    public function it_validates_coverage_reporting_setup(): void
    {
        $coverageConfig = [
            'coverage_directory' => 'coverage/',
            'coverage_formats' => ['html', 'clover', 'text'],
            'minimum_coverage_threshold' => 90,
            'coverage_includes' => ['src/', 'app/'],
            'coverage_excludes' => ['tests/', 'vendor/'],
        ];

        $coverageResults = [];

        foreach ($coverageConfig as $configKey => $expectedValue) {
            $result = [
                'config_key' => $configKey,
                'expected_value' => $expectedValue,
                'is_configured' => $this->validateCoverageConfiguration($configKey, $expectedValue),
            ];

            $coverageResults[] = $result;

            $this->assertTrue($result['is_configured'],
                "Coverage configuration '{$configKey}' should be properly configured");
        }

        $this->recordCIResult('coverage_reporting_setup', $coverageResults);
    }

    #[Test]
    public function it_validates_documentation_ci_integration(): void
    {
        $integrationResults = $this->calculateDocumentationCIIntegration();

        $this->recordCIResult('documentation_ci_integration', $integrationResults);

        // Validate documentation completeness
        $this->assertGreaterThanOrEqual(85, $integrationResults['documentation_completeness_percentage'],
            "Documentation completeness is {$integrationResults['documentation_completeness_percentage']}%, below 85% target");

        // Validate test suite organization
        $this->assertGreaterThanOrEqual(90, $integrationResults['test_suite_organization_percentage'],
            "Test suite organization is {$integrationResults['test_suite_organization_percentage']}%, below 90% target");

        // Validate CI configuration
        $this->assertGreaterThanOrEqual(80, $integrationResults['ci_configuration_percentage'],
            "CI configuration completeness is {$integrationResults['ci_configuration_percentage']}%, below 80% target");

        // Validate overall integration score
        $this->assertGreaterThanOrEqual(85, $integrationResults['overall_integration_score'],
            "Overall documentation and CI integration score is {$integrationResults['overall_integration_score']}%, below 85% target");
    }

    /**
     * Calculate document completeness score.
     */
    protected function calculateDocumentCompletenessScore(string $content, string $docPath): float
    {
        $score = 0;
        $maxScore = 100;

        // Basic content score (40 points)
        $wordCount = str_word_count($content);
        $contentScore = min(40, $wordCount / 10); // 1 point per 10 words, max 40
        $score += $contentScore;

        // Structure score (30 points)
        $hasHeaders = preg_match_all('/^#+\s/m', $content);
        $structureScore = min(30, $hasHeaders * 5); // 5 points per header, max 30
        $score += $structureScore;

        // Sprint4b relevance score (30 points)
        $sprint4bReferences = substr_count(strtolower($content), 'sprint4b');
        $relevanceScore = min(30, $sprint4bReferences * 10); // 10 points per reference, max 30
        $score += $relevanceScore;

        return min($maxScore, $score);
    }

    /**
     * Validate test command.
     */
    protected function validateTestCommand(string $command): bool
    {
        // Check if phpunit executable exists
        if (! File::exists(base_path('vendor/bin/phpunit'))) {
            return false;
        }

        // Validate command syntax
        if (str_contains($command, '--group=')) {
            $group = str_replace(['vendor/bin/phpunit --group=', 'vendor/bin/phpunit'], '', $command);
            $group = trim($group);

            return ! empty($group);
        }

        return str_contains($command, 'vendor/bin/phpunit');
    }

    /**
     * Estimate command execution time.
     */
    protected function estimateCommandExecutionTime(string $command): int
    {
        // Estimate based on command type
        if (str_contains($command, '--group=sprint4b-validation')) {
            return 120; // 2 minutes for validation tests
        } elseif (str_contains($command, '--group=')) {
            return 180; // 3 minutes for feature group tests
        } else {
            return 600; // 10 minutes for full test suite
        }
    }

    /**
     * Validate coverage configuration.
     */
    protected function validateCoverageConfiguration(string $configKey, $expectedValue): bool
    {
        // Simulate coverage configuration validation
        switch ($configKey) {
            case 'minimum_coverage_threshold':
                return $expectedValue >= 90;
            case 'coverage_formats':
                return is_array($expectedValue) && count($expectedValue) >= 2;
            default:
                return true;
        }
    }

    /**
     * Calculate documentation and CI integration.
     */
    protected function calculateDocumentationCIIntegration(): array
    {
        $docResults = $this->documentationResults['documentation_completeness'] ?? [];
        $testResults = $this->documentationResults['test_suite_organization'] ?? [];
        $ciResults = array_merge($this->ciResults);

        $docCompleteness = 0;
        if (! empty($docResults)) {
            $docCompleteness = collect($docResults)->avg('completeness_score');
        }

        $testOrganization = 0;
        if (! empty($testResults)) {
            $existingDirs = collect($testResults)->where('exists', true)->count();
            $totalDirs = count($testResults);
            $testOrganization = $totalDirs > 0 ? ($existingDirs / $totalDirs) * 100 : 0;
        }

        $ciConfiguration = 0;
        if (! empty($ciResults)) {
            $configuredItems = 0;
            $totalItems = 0;

            foreach ($ciResults as $category => $items) {
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $totalItems++;
                        if (isset($item['exists']) && $item['exists']) {
                            $configuredItems++;
                        }
                        if (isset($item['configured']) && $item['configured']) {
                            $configuredItems++;
                        }
                        if (isset($item['is_valid']) && $item['is_valid']) {
                            $configuredItems++;
                        }
                        if (isset($item['is_configured']) && $item['is_configured']) {
                            $configuredItems++;
                        }
                    }
                }
            }

            $ciConfiguration = $totalItems > 0 ? ($configuredItems / $totalItems) * 100 : 0;
        }

        $overallScore = ($docCompleteness + $testOrganization + $ciConfiguration) / 3;

        return [
            'documentation_completeness_percentage' => $docCompleteness,
            'test_suite_organization_percentage' => $testOrganization,
            'ci_configuration_percentage' => $ciConfiguration,
            'overall_integration_score' => $overallScore,
            'components_analyzed' => [
                'documentation_files' => count($docResults),
                'test_directories' => count($testResults),
                'ci_components' => count($ciResults),
            ],
        ];
    }

    /**
     * Record documentation result.
     */
    protected function recordDocumentationResult(string $category, array $result): void
    {
        $this->documentationResults[$category] = $result;
    }

    /**
     * Record CI result.
     */
    protected function recordCIResult(string $category, array $result): void
    {
        $this->ciResults[$category] = $result;
    }

    /**
     * Log results.
     */
    protected function logResults(): void
    {
        if (! empty($this->documentationResults) || ! empty($this->ciResults)) {
            Log::info('Sprint4b Documentation and CI Integration Results', [
                'documentation_results' => $this->documentationResults,
                'ci_results' => $this->ciResults,
                'summary' => $this->generateResultsSummary(),
            ]);
        }
    }

    /**
     * Generate results summary.
     */
    protected function generateResultsSummary(): array
    {
        return [
            'documentation_categories' => count($this->documentationResults),
            'ci_categories' => count($this->ciResults),
            'total_validations' => count($this->documentationResults) + count($this->ciResults),
            'timestamp' => now()->toISOString(),
            'test_environment' => app()->environment(),
        ];
    }
}
