<?php

namespace JTD\LaravelAI\Tests\Feature\Sprint4bValidation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criteria Validation Test
 *
 * Verifies all Sprint4b user story acceptance criteria are met through tests
 * by analyzing test coverage against specific acceptance criteria.
 */
#[Group('sprint4b-validation')]
#[Group('acceptance-criteria')]
class AcceptanceCriteriaValidationTest extends TestCase
{
    use RefreshDatabase;

    protected array $acceptanceCriteria = [];

    protected array $validationResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->acceptanceCriteria = $this->defineAcceptanceCriteria();
        $this->validationResults = [];
    }

    protected function tearDown(): void
    {
        $this->logValidationResults();
        parent::tearDown();
    }

    #[Test]
    public function it_validates_story1_cost_tracking_acceptance_criteria(): void
    {
        $storyId = 'Story1_CostTracking';
        $criteria = $this->acceptanceCriteria[$storyId];

        $validationResults = [];

        foreach ($criteria as $criterionId => $criterion) {
            $result = $this->validateAcceptanceCriterion($storyId, $criterionId, $criterion);
            $validationResults[$criterionId] = $result;

            $this->assertTrue($result['is_covered'],
                "Story 1 acceptance criterion '{$criterion['description']}' is not adequately covered by tests");
        }

        $this->recordValidationResult($storyId, $validationResults);

        // Verify all criteria are covered
        $coveredCount = collect($validationResults)->where('is_covered', true)->count();
        $totalCount = count($criteria);

        $this->assertEquals($totalCount, $coveredCount,
            "Story 1 has {$coveredCount}/{$totalCount} acceptance criteria covered");
    }

    #[Test]
    public function it_validates_story2_budget_management_acceptance_criteria(): void
    {
        $storyId = 'Story2_BudgetManagement';
        $criteria = $this->acceptanceCriteria[$storyId];

        $validationResults = [];

        foreach ($criteria as $criterionId => $criterion) {
            $result = $this->validateAcceptanceCriterion($storyId, $criterionId, $criterion);
            $validationResults[$criterionId] = $result;

            $this->assertTrue($result['is_covered'],
                "Story 2 acceptance criterion '{$criterion['description']}' is not adequately covered by tests");
        }

        $this->recordValidationResult($storyId, $validationResults);

        // Verify all criteria are covered
        $coveredCount = collect($validationResults)->where('is_covered', true)->count();
        $totalCount = count($criteria);

        $this->assertEquals($totalCount, $coveredCount,
            "Story 2 has {$coveredCount}/{$totalCount} acceptance criteria covered");
    }

    #[Test]
    public function it_validates_story3_analytics_acceptance_criteria(): void
    {
        $storyId = 'Story3_Analytics';
        $criteria = $this->acceptanceCriteria[$storyId];

        $validationResults = [];

        foreach ($criteria as $criterionId => $criterion) {
            $result = $this->validateAcceptanceCriterion($storyId, $criterionId, $criterion);
            $validationResults[$criterionId] = $result;

            $this->assertTrue($result['is_covered'],
                "Story 3 acceptance criterion '{$criterion['description']}' is not adequately covered by tests");
        }

        $this->recordValidationResult($storyId, $validationResults);

        // Verify all criteria are covered
        $coveredCount = collect($validationResults)->where('is_covered', true)->count();
        $totalCount = count($criteria);

        $this->assertEquals($totalCount, $coveredCount,
            "Story 3 has {$coveredCount}/{$totalCount} acceptance criteria covered");
    }

    #[Test]
    public function it_validates_story4_mcp_framework_acceptance_criteria(): void
    {
        $storyId = 'Story4_MCPFramework';
        $criteria = $this->acceptanceCriteria[$storyId];

        $validationResults = [];

        foreach ($criteria as $criterionId => $criterion) {
            $result = $this->validateAcceptanceCriterion($storyId, $criterionId, $criterion);
            $validationResults[$criterionId] = $result;

            $this->assertTrue($result['is_covered'],
                "Story 4 acceptance criterion '{$criterion['description']}' is not adequately covered by tests");
        }

        $this->recordValidationResult($storyId, $validationResults);

        // Verify all criteria are covered
        $coveredCount = collect($validationResults)->where('is_covered', true)->count();
        $totalCount = count($criteria);

        $this->assertEquals($totalCount, $coveredCount,
            "Story 4 has {$coveredCount}/{$totalCount} acceptance criteria covered");
    }

    #[Test]
    public function it_validates_story5_mcp_setup_acceptance_criteria(): void
    {
        $storyId = 'Story5_MCPSetup';
        $criteria = $this->acceptanceCriteria[$storyId];

        $validationResults = [];

        foreach ($criteria as $criterionId => $criterion) {
            $result = $this->validateAcceptanceCriterion($storyId, $criterionId, $criterion);
            $validationResults[$criterionId] = $result;

            $this->assertTrue($result['is_covered'],
                "Story 5 acceptance criterion '{$criterion['description']}' is not adequately covered by tests");
        }

        $this->recordValidationResult($storyId, $validationResults);

        // Verify all criteria are covered
        $coveredCount = collect($validationResults)->where('is_covered', true)->count();
        $totalCount = count($criteria);

        $this->assertEquals($totalCount, $coveredCount,
            "Story 5 has {$coveredCount}/{$totalCount} acceptance criteria covered");
    }

    #[Test]
    public function it_validates_story6_mcp_integration_acceptance_criteria(): void
    {
        $storyId = 'Story6_MCPIntegration';
        $criteria = $this->acceptanceCriteria[$storyId];

        $validationResults = [];

        foreach ($criteria as $criterionId => $criterion) {
            $result = $this->validateAcceptanceCriterion($storyId, $criterionId, $criterion);
            $validationResults[$criterionId] = $result;

            $this->assertTrue($result['is_covered'],
                "Story 6 acceptance criterion '{$criterion['description']}' is not adequately covered by tests");
        }

        $this->recordValidationResult($storyId, $validationResults);

        // Verify all criteria are covered
        $coveredCount = collect($validationResults)->where('is_covered', true)->count();
        $totalCount = count($criteria);

        $this->assertEquals($totalCount, $coveredCount,
            "Story 6 has {$coveredCount}/{$totalCount} acceptance criteria covered");
    }

    #[Test]
    public function it_validates_story7_performance_acceptance_criteria(): void
    {
        $storyId = 'Story7_Performance';
        $criteria = $this->acceptanceCriteria[$storyId];

        $validationResults = [];

        foreach ($criteria as $criterionId => $criterion) {
            $result = $this->validateAcceptanceCriterion($storyId, $criterionId, $criterion);
            $validationResults[$criterionId] = $result;

            $this->assertTrue($result['is_covered'],
                "Story 7 acceptance criterion '{$criterion['description']}' is not adequately covered by tests");
        }

        $this->recordValidationResult($storyId, $validationResults);

        // Verify all criteria are covered
        $coveredCount = collect($validationResults)->where('is_covered', true)->count();
        $totalCount = count($criteria);

        $this->assertEquals($totalCount, $coveredCount,
            "Story 7 has {$coveredCount}/{$totalCount} acceptance criteria covered");
    }

    #[Test]
    public function it_validates_story8_infrastructure_acceptance_criteria(): void
    {
        $storyId = 'Story8_Infrastructure';
        $criteria = $this->acceptanceCriteria[$storyId];

        $validationResults = [];

        foreach ($criteria as $criterionId => $criterion) {
            $result = $this->validateAcceptanceCriterion($storyId, $criterionId, $criterion);
            $validationResults[$criterionId] = $result;

            $this->assertTrue($result['is_covered'],
                "Story 8 acceptance criterion '{$criterion['description']}' is not adequately covered by tests");
        }

        $this->recordValidationResult($storyId, $validationResults);

        // Verify all criteria are covered
        $coveredCount = collect($validationResults)->where('is_covered', true)->count();
        $totalCount = count($criteria);

        $this->assertEquals($totalCount, $coveredCount,
            "Story 8 has {$coveredCount}/{$totalCount} acceptance criteria covered");
    }

    #[Test]
    public function it_validates_overall_sprint4b_acceptance_criteria(): void
    {
        $overallResults = $this->calculateOverallAcceptanceCoverage();

        $this->recordValidationResult('Overall', $overallResults);

        // Validate overall acceptance criteria coverage
        $this->assertGreaterThanOrEqual(95, $overallResults['coverage_percentage'],
            "Overall acceptance criteria coverage is {$overallResults['coverage_percentage']}%, below 95% target");

        // Validate all stories have adequate coverage
        foreach ($this->acceptanceCriteria as $storyId => $criteria) {
            $storyResults = $this->validationResults[$storyId] ?? null;
            $this->assertNotNull($storyResults, "Story {$storyId} should have validation results");

            $coveredCount = collect($storyResults)->where('is_covered', true)->count();
            $totalCount = count($criteria);
            $coveragePercentage = ($coveredCount / $totalCount) * 100;

            $this->assertGreaterThanOrEqual(90, $coveragePercentage,
                "Story {$storyId} acceptance criteria coverage is {$coveragePercentage}%, below 90% minimum");
        }
    }

    /**
     * Validate a specific acceptance criterion.
     */
    protected function validateAcceptanceCriterion(string $storyId, string $criterionId, array $criterion): array
    {
        $testPatterns = $criterion['test_patterns'] ?? [];
        $testDirectories = $criterion['test_directories'] ?? [];

        $coverageScore = 0;
        $maxScore = 100;
        $evidenceFound = [];

        // Search for test evidence in specified directories
        foreach ($testDirectories as $directory) {
            $testDirectory = base_path("tests/Feature/{$directory}");
            if (File::isDirectory($testDirectory)) {
                $testFiles = File::glob($testDirectory . '/*.php');

                foreach ($testFiles as $testFile) {
                    $content = File::get($testFile);

                    // Check for test patterns
                    foreach ($testPatterns as $pattern) {
                        if (str_contains(strtolower($content), strtolower($pattern))) {
                            $coverageScore += 20; // Each pattern match adds 20 points
                            $evidenceFound[] = [
                                'pattern' => $pattern,
                                'file' => basename($testFile),
                                'directory' => $directory,
                            ];
                        }
                    }
                }
            }
        }

        $coverageScore = min($maxScore, $coverageScore);
        $isCovered = $coverageScore >= 60; // 60% threshold for coverage

        return [
            'criterion_id' => $criterionId,
            'description' => $criterion['description'],
            'coverage_score' => $coverageScore,
            'is_covered' => $isCovered,
            'evidence_found' => $evidenceFound,
            'test_patterns' => $testPatterns,
            'test_directories' => $testDirectories,
        ];
    }

    /**
     * Calculate overall acceptance criteria coverage.
     */
    protected function calculateOverallAcceptanceCoverage(): array
    {
        $totalCriteria = 0;
        $coveredCriteria = 0;
        $totalScore = 0;

        foreach ($this->validationResults as $storyId => $storyResults) {
            if ($storyId === 'Overall') {
                continue;
            }

            foreach ($storyResults as $result) {
                $totalCriteria++;
                $totalScore += $result['coverage_score'];

                if ($result['is_covered']) {
                    $coveredCriteria++;
                }
            }
        }

        return [
            'total_criteria' => $totalCriteria,
            'covered_criteria' => $coveredCriteria,
            'coverage_percentage' => $totalCriteria > 0 ? ($coveredCriteria / $totalCriteria) * 100 : 0,
            'average_score' => $totalCriteria > 0 ? $totalScore / $totalCriteria : 0,
            'stories_analyzed' => count($this->validationResults) - (isset($this->validationResults['Overall']) ? 1 : 0),
        ];
    }

    /**
     * Define Sprint4b acceptance criteria.
     */
    protected function defineAcceptanceCriteria(): array
    {
        return [
            'Story1_CostTracking' => [
                'real_time_tracking' => [
                    'description' => 'Real-time cost tracking with events',
                    'test_patterns' => ['cost tracking', 'real-time', 'CostCalculated', 'cost calculation'],
                    'test_directories' => ['CostTracking'],
                ],
                'token_cost_calculation' => [
                    'description' => 'Accurate token-based cost calculation',
                    'test_patterns' => ['token cost', 'input tokens', 'output tokens', 'cost calculation'],
                    'test_directories' => ['CostTracking'],
                ],
                'provider_specific_rates' => [
                    'description' => 'Provider-specific pricing rates',
                    'test_patterns' => ['provider rates', 'pricing', 'model costs', 'rate calculation'],
                    'test_directories' => ['CostTracking'],
                ],
                'cost_aggregation' => [
                    'description' => 'Cost aggregation and reporting',
                    'test_patterns' => ['cost aggregation', 'cost reporting', 'total cost', 'cost summary'],
                    'test_directories' => ['CostTracking'],
                ],
            ],
            'Story2_BudgetManagement' => [
                'budget_enforcement' => [
                    'description' => 'Budget enforcement via middleware',
                    'test_patterns' => ['budget enforcement', 'BudgetEnforcementMiddleware', 'budget limit'],
                    'test_directories' => ['BudgetManagement'],
                ],
                'threshold_alerts' => [
                    'description' => 'Budget threshold alerts',
                    'test_patterns' => ['budget threshold', 'BudgetThresholdReached', 'budget alert'],
                    'test_directories' => ['BudgetManagement'],
                ],
                'spending_limits' => [
                    'description' => 'Configurable spending limits',
                    'test_patterns' => ['spending limit', 'budget limit', 'limit configuration'],
                    'test_directories' => ['BudgetManagement'],
                ],
                'budget_tracking' => [
                    'description' => 'Budget usage tracking',
                    'test_patterns' => ['budget tracking', 'budget usage', 'spending tracking'],
                    'test_directories' => ['BudgetManagement'],
                ],
            ],
            'Story3_Analytics' => [
                'usage_analytics' => [
                    'description' => 'Usage analytics with background processing',
                    'test_patterns' => ['usage analytics', 'analytics processing', 'usage tracking'],
                    'test_directories' => ['Analytics'],
                ],
                'background_processing' => [
                    'description' => 'Background job processing for analytics',
                    'test_patterns' => ['background processing', 'analytics job', 'queue processing'],
                    'test_directories' => ['Analytics'],
                ],
                'metrics_collection' => [
                    'description' => 'Comprehensive metrics collection',
                    'test_patterns' => ['metrics collection', 'analytics metrics', 'data collection'],
                    'test_directories' => ['Analytics'],
                ],
                'reporting_dashboard' => [
                    'description' => 'Analytics reporting and dashboard',
                    'test_patterns' => ['analytics reporting', 'dashboard', 'analytics dashboard'],
                    'test_directories' => ['Analytics'],
                ],
            ],
            'Story4_MCPFramework' => [
                'mcp_server_framework' => [
                    'description' => 'MCP server framework and configuration',
                    'test_patterns' => ['MCP server', 'MCP framework', 'server configuration'],
                    'test_directories' => ['MCPFramework'],
                ],
                'server_management' => [
                    'description' => 'MCP server lifecycle management',
                    'test_patterns' => ['server management', 'server lifecycle', 'MCP management'],
                    'test_directories' => ['MCPFramework'],
                ],
                'configuration_system' => [
                    'description' => 'MCP configuration system',
                    'test_patterns' => ['MCP configuration', 'server configuration', 'config management'],
                    'test_directories' => ['MCPFramework'],
                ],
                'server_discovery' => [
                    'description' => 'MCP server discovery and registration',
                    'test_patterns' => ['server discovery', 'MCP discovery', 'server registration'],
                    'test_directories' => ['MCPFramework'],
                ],
            ],
            'Story5_MCPSetup' => [
                'easy_setup_system' => [
                    'description' => 'Easy MCP Setup system',
                    'test_patterns' => ['MCP setup', 'easy setup', 'setup system'],
                    'test_directories' => ['MCPSetup'],
                ],
                'interactive_setup' => [
                    'description' => 'Interactive MCP server setup',
                    'test_patterns' => ['interactive setup', 'setup wizard', 'MCP installation'],
                    'test_directories' => ['MCPSetup'],
                ],
                'automated_configuration' => [
                    'description' => 'Automated MCP configuration',
                    'test_patterns' => ['automated configuration', 'auto configuration', 'setup automation'],
                    'test_directories' => ['MCPSetup'],
                ],
                'setup_validation' => [
                    'description' => 'MCP setup validation and testing',
                    'test_patterns' => ['setup validation', 'setup testing', 'configuration validation'],
                    'test_directories' => ['MCPSetup'],
                ],
            ],
            'Story6_MCPIntegration' => [
                'ai_call_integration' => [
                    'description' => 'MCP integration with AI calls',
                    'test_patterns' => ['MCP integration', 'AI call integration', 'withTools', 'allTools'],
                    'test_directories' => ['MCPIntegration'],
                ],
                'tool_specification' => [
                    'description' => 'AI calls specify MCP tools',
                    'test_patterns' => ['tool specification', 'withTools', 'tool selection'],
                    'test_directories' => ['MCPIntegration'],
                ],
                'tool_execution' => [
                    'description' => 'MCP tool execution and routing',
                    'test_patterns' => ['tool execution', 'MCP execution', 'tool routing'],
                    'test_directories' => ['MCPIntegration'],
                ],
                'response_integration' => [
                    'description' => 'Tool results in AI responses',
                    'test_patterns' => ['response integration', 'tool results', 'response metadata'],
                    'test_directories' => ['MCPIntegration'],
                ],
            ],
            'Story7_Performance' => [
                'performance_monitoring' => [
                    'description' => 'Performance monitoring and optimization',
                    'test_patterns' => ['performance monitoring', 'performance optimization', 'monitoring system'],
                    'test_directories' => ['Performance'],
                ],
                'response_time_tracking' => [
                    'description' => 'Response time tracking and benchmarks',
                    'test_patterns' => ['response time', 'performance benchmarks', 'time tracking'],
                    'test_directories' => ['Performance'],
                ],
                'optimization_recommendations' => [
                    'description' => 'Performance optimization recommendations',
                    'test_patterns' => ['optimization recommendations', 'performance recommendations', 'optimization'],
                    'test_directories' => ['Performance'],
                ],
                'performance_dashboard' => [
                    'description' => 'Performance monitoring dashboard',
                    'test_patterns' => ['performance dashboard', 'monitoring dashboard', 'performance metrics'],
                    'test_directories' => ['Performance'],
                ],
            ],
            'Story8_Infrastructure' => [
                'core_infrastructure' => [
                    'description' => 'Supporting infrastructure components',
                    'test_patterns' => ['core infrastructure', 'infrastructure components', 'system infrastructure'],
                    'test_directories' => ['CoreInfrastructure'],
                ],
                'ai_manager_system' => [
                    'description' => 'AI Manager and Driver System',
                    'test_patterns' => ['AI Manager', 'Driver System', 'provider management'],
                    'test_directories' => ['CoreInfrastructure'],
                ],
                'database_layer' => [
                    'description' => 'Database layer and model relationships',
                    'test_patterns' => ['database layer', 'model relationships', 'database infrastructure'],
                    'test_directories' => ['CoreInfrastructure'],
                ],
                'service_layer' => [
                    'description' => 'Facade and Service Layer integration',
                    'test_patterns' => ['service layer', 'facade integration', 'Laravel integration'],
                    'test_directories' => ['CoreInfrastructure'],
                ],
            ],
        ];
    }

    /**
     * Record validation result.
     */
    protected function recordValidationResult(string $storyId, array $results): void
    {
        $this->validationResults[$storyId] = $results;
    }

    /**
     * Log validation results.
     */
    protected function logValidationResults(): void
    {
        if (! empty($this->validationResults)) {
            Log::info('Sprint4b Acceptance Criteria Validation Results', [
                'validation_results' => $this->validationResults,
                'summary' => $this->generateValidationSummary(),
            ]);
        }
    }

    /**
     * Generate validation summary.
     */
    protected function generateValidationSummary(): array
    {
        $summary = [
            'total_stories' => count($this->acceptanceCriteria),
            'stories_validated' => count($this->validationResults) - (isset($this->validationResults['Overall']) ? 1 : 0),
            'total_criteria' => 0,
            'covered_criteria' => 0,
            'stories_meeting_target' => 0,
            'stories_below_target' => 0,
        ];

        foreach ($this->validationResults as $storyId => $storyResults) {
            if ($storyId === 'Overall') {
                continue;
            }

            $storyCovered = 0;
            $storyTotal = count($storyResults);

            foreach ($storyResults as $result) {
                $summary['total_criteria']++;
                if ($result['is_covered']) {
                    $summary['covered_criteria']++;
                    $storyCovered++;
                }
            }

            $storyCoveragePercentage = ($storyCovered / $storyTotal) * 100;
            if ($storyCoveragePercentage >= 90) {
                $summary['stories_meeting_target']++;
            } else {
                $summary['stories_below_target']++;
            }
        }

        $summary['overall_coverage_percentage'] = $summary['total_criteria'] > 0
            ? ($summary['covered_criteria'] / $summary['total_criteria']) * 100
            : 0;

        return $summary;
    }
}
