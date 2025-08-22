<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use JTD\LaravelAI\Tests\Performance\PerformanceBenchmark;
use JTD\LaravelAI\Drivers\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;

/**
 * Run Performance Tests Command
 *
 * Artisan command to run performance benchmarks for AI drivers
 * and generate detailed performance reports.
 */
class RunPerformanceTestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:performance 
                            {--driver=openai : The AI driver to test}
                            {--iterations=5 : Number of iterations for each test}
                            {--output= : Output file for performance report}
                            {--format=json : Report format (json, csv, html)}';

    /**
     * The console command description.
     */
    protected $description = 'Run performance benchmarks for AI drivers';

    /**
     * Performance benchmark instance.
     */
    private PerformanceBenchmark $benchmark;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->benchmark = new PerformanceBenchmark();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $driver = $this->option('driver');
        $iterations = (int) $this->option('iterations');
        $outputFile = $this->option('output');
        $format = $this->option('format');

        $this->info("🚀 Running performance benchmarks for {$driver} driver");
        $this->info("📊 Iterations: {$iterations}");
        $this->newLine();

        try {
            // Initialize driver
            $driverInstance = $this->initializeDriver($driver);
            
            // Run benchmarks
            $results = $this->runBenchmarks($driverInstance, $iterations);
            
            // Generate report
            $report = $this->benchmark->generateReport();
            
            // Display results
            $this->displayResults($report);
            
            // Save report if requested
            if ($outputFile) {
                $this->saveReport($report, $outputFile, $format);
            }

            $this->newLine();
            $this->info('✅ Performance benchmarks completed successfully');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Performance benchmarks failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Initialize the specified driver.
     */
    private function initializeDriver(string $driver)
    {
        switch ($driver) {
            case 'openai':
                if (empty(config('ai.providers.openai.api_key'))) {
                    throw new \RuntimeException('OpenAI API key not configured');
                }
                
                return new OpenAIDriver([
                    'api_key' => config('ai.providers.openai.api_key'),
                    'timeout' => 60,
                ]);

            default:
                throw new \InvalidArgumentException("Unsupported driver: {$driver}");
        }
    }

    /**
     * Run performance benchmarks.
     */
    private function runBenchmarks($driver, int $iterations): array
    {
        $results = [];

        // Basic message benchmark
        $this->info('📝 Testing basic message performance...');
        $results['basic_message'] = $this->benchmark->measureIterations(
            'basic_message',
            function () use ($driver) {
                return $driver->sendMessage(AIMessage::user('Hello, this is a performance test.'), [
                    'model' => 'gpt-3.5-turbo',
                    'max_tokens' => 50,
                    'temperature' => 0,
                ]);
            },
            $iterations
        );

        // Model listing benchmark
        $this->info('📋 Testing model listing performance...');
        $results['model_listing'] = $this->benchmark->measureIterations(
            'model_listing',
            function () use ($driver) {
                return $driver->getAvailableModels();
            },
            min($iterations, 3) // Fewer iterations for model listing
        );

        // Cost calculation benchmark
        $this->info('💰 Testing cost calculation performance...');
        $results['cost_calculation'] = $this->benchmark->measureIterations(
            'cost_calculation',
            function () use ($driver) {
                return $driver->calculateCost(
                    AIMessage::user('This is a test message for cost calculation.'),
                    'gpt-3.5-turbo'
                );
            },
            $iterations * 2 // More iterations for fast operations
        );

        // Health check benchmark
        $this->info('🏥 Testing health check performance...');
        $results['health_check'] = $this->benchmark->measureIterations(
            'health_check',
            function () use ($driver) {
                return $driver->getHealthStatus();
            },
            min($iterations, 3) // Fewer iterations for health checks
        );

        // Token estimation benchmark
        $this->info('🔢 Testing token estimation performance...');
        $results['token_estimation'] = $this->benchmark->measureIterations(
            'token_estimation',
            function () use ($driver) {
                return $driver->estimateTokens(AIMessage::user('This is a test message for token estimation performance testing.'));
            },
            $iterations * 3 // More iterations for very fast operations
        );

        return $results;
    }

    /**
     * Display benchmark results.
     */
    private function displayResults(array $report): void
    {
        $this->newLine();
        $this->info('📊 PERFORMANCE BENCHMARK RESULTS');
        $this->info('================================');

        foreach ($report['benchmarks'] as $benchmark) {
            if (isset($benchmark['operation']) && isset($benchmark['response_time'])) {
                // Single operation result
                $this->displaySingleResult($benchmark);
            } elseif (isset($benchmark['iterations'])) {
                // Multiple iterations result
                $this->displayIterationsResult($benchmark);
            }
        }

        // Display analysis
        if (!empty($report['analysis']['performance_issues'])) {
            $this->newLine();
            $this->warn('⚠️  PERFORMANCE ISSUES DETECTED');
            foreach ($report['analysis']['performance_issues'] as $issue) {
                $this->warn("  • {$issue['operation']}: " . count($issue['issues']) . " issues");
            }
        }

        if (!empty($report['analysis']['recommendations'])) {
            $this->newLine();
            $this->info('💡 RECOMMENDATIONS');
            foreach ($report['analysis']['recommendations'] as $recommendation) {
                $this->info("  • {$recommendation}");
            }
        }
    }

    /**
     * Display single operation result.
     */
    private function displaySingleResult(array $result): void
    {
        $status = $result['success'] ? '✅' : '❌';
        $this->line("{$status} {$result['operation']}: {$result['response_time']}ms, {$result['memory_usage']}MB");
    }

    /**
     * Display iterations result.
     */
    private function displayIterationsResult(array $result): void
    {
        $this->info("🔹 {$result['operation']} ({$result['iterations']} iterations):");
        $this->line("  Average: {$result['response_time']['avg']}ms, {$result['memory_usage']['avg']}MB");
        $this->line("  Range: {$result['response_time']['min']}-{$result['response_time']['max']}ms");
        $this->line("  Success Rate: {$result['success_rate']}%");
        $this->line("  Throughput: {$result['throughput']} req/sec");
    }

    /**
     * Save performance report to file.
     */
    private function saveReport(array $report, string $filename, string $format): void
    {
        $content = match ($format) {
            'json' => json_encode($report, JSON_PRETTY_PRINT),
            'csv' => $this->convertToCsv($report),
            'html' => $this->convertToHtml($report),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };

        file_put_contents($filename, $content);
        $this->info("📄 Report saved to: {$filename}");
    }

    /**
     * Convert report to CSV format.
     */
    private function convertToCsv(array $report): string
    {
        $csv = "Operation,Response Time (ms),Memory Usage (MB),Success,Timestamp\n";
        
        foreach ($report['benchmarks'] as $benchmark) {
            if (isset($benchmark['operation']) && isset($benchmark['response_time'])) {
                $csv .= sprintf(
                    "%s,%s,%s,%s,%s\n",
                    $benchmark['operation'],
                    $benchmark['response_time'],
                    $benchmark['memory_usage'],
                    $benchmark['success'] ? 'true' : 'false',
                    $benchmark['timestamp']
                );
            }
        }
        
        return $csv;
    }

    /**
     * Convert report to HTML format.
     */
    private function convertToHtml(array $report): string
    {
        $html = '<html><head><title>Performance Report</title></head><body>';
        $html .= '<h1>Performance Benchmark Report</h1>';
        $html .= '<p>Generated: ' . $report['summary']['timestamp'] . '</p>';
        $html .= '<table border="1"><tr><th>Operation</th><th>Response Time (ms)</th><th>Memory Usage (MB)</th><th>Success</th></tr>';
        
        foreach ($report['benchmarks'] as $benchmark) {
            if (isset($benchmark['operation']) && isset($benchmark['response_time'])) {
                $html .= sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars($benchmark['operation']),
                    $benchmark['response_time'],
                    $benchmark['memory_usage'],
                    $benchmark['success'] ? 'Yes' : 'No'
                );
            }
        }
        
        $html .= '</table></body></html>';
        
        return $html;
    }
}
