<?php

/**
 * Test Performance Analysis Script
 *
 * Analyzes PHPUnit test performance and identifies slow tests.
 * Usage: php scripts/test-performance.php [--threshold=1.0] [--format=table|json]
 */

$options = getopt('', ['threshold::', 'format::', 'help']);

if (isset($options['help'])) {
    echo "Test Performance Analysis Script\n";
    echo "Usage: php scripts/test-performance.php [options]\n\n";
    echo "Options:\n";
    echo "  --threshold=N    Show tests slower than N seconds (default: 0.5)\n";
    echo "  --format=FORMAT  Output format: table, json, or summary (default: table)\n";
    echo "  --help           Show this help message\n\n";
    echo "Examples:\n";
    echo "  php scripts/test-performance.php\n";
    echo "  php scripts/test-performance.php --threshold=1.0 --format=json\n";
    exit(0);
}

$threshold = (float)($options['threshold'] ?? 0.5);
$format = $options['format'] ?? 'table';

// Run tests with JUnit output in batches to identify hanging tests
echo "Running tests to collect performance data...\n";

// Define test directories to run separately
$testDirs = [
    'tests/Unit' => 'Unit Tests',
    'tests/Feature' => 'Feature Tests',
    'tests/Integration' => 'Integration Tests',
    'tests/Performance' => 'Performance Tests',
    'tests/E2E' => 'E2E Tests'
];

$allTests = [];
$failedDirs = [];

foreach ($testDirs as $dir => $name) {
    if (!is_dir($dir)) {
        echo "Skipping $name - directory not found\n";
        continue;
    }

    echo "Running $name...\n";
    $junitFile = "junit-" . basename($dir) . ".xml";

    // Set a reasonable timeout for each test suite
    $command = "vendor/bin/phpunit --log-junit $junitFile $dir 2>/dev/null";

    // Use proc_open to have better control over the process
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes);

    if (is_resource($process)) {
        // Close stdin
        fclose($pipes[0]);

        // Set a timeout of 5 minutes per test suite
        $timeout = 300;
        $start = time();

        while (proc_get_status($process)['running'] && (time() - $start) < $timeout) {
            usleep(100000); // 0.1 second
        }

        $status = proc_get_status($process);
        if ($status['running']) {
            echo "  ⚠️  $name timed out after {$timeout}s - terminating\n";
            proc_terminate($process);
            $failedDirs[] = $name;
        } else {
            echo "  ✅ $name completed\n";
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        // Parse results if junit file was created
        if (file_exists($junitFile)) {
            $xml = simplexml_load_file($junitFile);
            if ($xml) {
                extractTests($xml, $allTests);
            }
            unlink($junitFile);
        }
    } else {
        echo "  ❌ Failed to start $name\n";
        $failedDirs[] = $name;
    }
}

if (!empty($failedDirs)) {
    echo "\n⚠️  The following test suites had issues:\n";
    foreach ($failedDirs as $dir) {
        echo "  - $dir\n";
    }
    echo "\n";
}

if (empty($allTests)) {
    echo "Error: No test data collected\n";
    exit(1);
}

$tests = $allTests;

function extractTests($element, &$tests) {
    if ($element->getName() === 'testcase') {
        $time = (float)$element['time'];
        $name = (string)$element['name'];
        $class = (string)$element['class'];
        $tests[] = [
            'class' => $class,
            'method' => $name,
            'time' => $time,
            'full_name' => $class . '::' . $name
        ];
    }

    foreach ($element->children() as $child) {
        extractTests($child, $tests);
    }
}

if (empty($tests)) {
    echo "Error: No test data found\n";
    exit(1);
}

// Sort by time (slowest first)
usort($tests, function($a, $b) { return $b['time'] <=> $a['time']; });

// Filter slow tests
$slowTests = array_filter($tests, function($t) use ($threshold) {
    return $t['time'] > $threshold;
});

// Calculate statistics
$totalTime = array_sum(array_column($tests, 'time'));
$averageTime = $totalTime / count($tests);
$medianTime = $tests[intval(count($tests)/2)]['time'];

$verySlowTests = array_filter($tests, function($t) { return $t['time'] > 2.0; });
$mediumTests = array_filter($tests, function($t) { return $t['time'] > 0.5 && $t['time'] <= 2.0; });
$fastTests = array_filter($tests, function($t) { return $t['time'] <= 0.1; });

// Group by test suite
$suites = [];
foreach ($tests as $test) {
    $suite = basename(str_replace('\\', '/', $test['class']));
    if (!isset($suites[$suite])) {
        $suites[$suite] = ['count' => 0, 'time' => 0, 'tests' => []];
    }
    $suites[$suite]['count']++;
    $suites[$suite]['time'] += $test['time'];
    $suites[$suite]['tests'][] = $test;
}

uasort($suites, function($a, $b) { return $b['time'] <=> $a['time']; });

// Output results
switch ($format) {
    case 'json':
        echo json_encode([
            'summary' => [
                'total_tests' => count($tests),
                'total_time' => round($totalTime, 3),
                'average_time' => round($averageTime, 3),
                'median_time' => round($medianTime, 3),
                'slow_tests_count' => count($slowTests),
                'very_slow_tests_count' => count($verySlowTests),
            ],
            'slow_tests' => array_map(function($test) {
                return [
                    'class' => basename(str_replace('\\', '/', $test['class'])),
                    'method' => $test['method'],
                    'time' => round($test['time'], 3)
                ];
            }, $slowTests),
            'suites' => array_map(function($suite, $name) {
                return [
                    'name' => $name,
                    'count' => $suite['count'],
                    'total_time' => round($suite['time'], 3),
                    'average_time' => round($suite['time'] / $suite['count'], 3)
                ];
            }, $suites, array_keys($suites))
        ], JSON_PRETTY_PRINT);
        break;

    case 'summary':
        echo "\n📊 TEST PERFORMANCE SUMMARY\n";
        echo "===========================\n\n";
        echo "Total tests: " . count($tests) . "\n";
        echo "Total time: " . number_format($totalTime, 3) . "s\n";
        echo "Average time: " . number_format($averageTime, 3) . "s\n";
        echo "Median time: " . number_format($medianTime, 3) . "s\n\n";
        echo "🚨 Very slow tests (>2s): " . count($verySlowTests) . "\n";
        echo "⚠️  Slow tests (>0.5s): " . count($mediumTests) . "\n";
        echo "✅ Fast tests (<0.1s): " . count($fastTests) . "\n\n";

        if (!empty($slowTests)) {
            echo "🐌 TESTS REQUIRING ATTENTION (>" . $threshold . "s):\n";
            foreach (array_slice($slowTests, 0, 10) as $test) {
                $className = basename(str_replace('\\', '/', $test['class']));
                echo "  " . number_format($test['time'], 3) . "s - " . $className . "::" . $test['method'] . "\n";
            }
        }
        break;

    default: // table
        echo "\n📊 TEST PERFORMANCE ANALYSIS\n";
        echo "============================\n\n";

        if (!empty($slowTests)) {
            echo "🐌 SLOW TESTS (>" . $threshold . "s):\n";
            echo str_pad('Time (s)', 10) . str_pad('Class', 40) . 'Method' . "\n";
            echo str_repeat('-', 100) . "\n";

            foreach (array_slice($slowTests, 0, 20) as $test) {
                $className = basename(str_replace('\\', '/', $test['class']));
                echo str_pad(number_format($test['time'], 3), 10) .
                     str_pad($className, 40) .
                     $test['method'] . "\n";
            }
            echo "\n";
        }

        echo "📈 PERFORMANCE STATISTICS:\n";
        echo "Total tests: " . count($tests) . "\n";
        echo "Total time: " . number_format($totalTime, 3) . "s\n";
        echo "Average time: " . number_format($averageTime, 3) . "s\n";
        echo "Median time: " . number_format($medianTime, 3) . "s\n\n";

        echo "🚨 Very slow tests (>2s): " . count($verySlowTests) . "\n";
        echo "⚠️  Medium tests (0.5-2s): " . count($mediumTests) . "\n";
        echo "✅ Fast tests (<0.1s): " . count($fastTests) . "\n\n";

        echo "📊 TEST SUITE BREAKDOWN:\n";
        foreach (array_slice($suites, 0, 10, true) as $suite => $data) {
            echo str_pad($suite, 40) .
                 str_pad($data['count'] . ' tests', 15) .
                 number_format($data['time'], 3) . "s (avg: " .
                 number_format($data['time'] / $data['count'], 3) . "s)\n";
        }

        if (!empty($verySlowTests)) {
            echo "\n🚨 RECOMMENDATIONS:\n";
            echo "- Consider optimizing tests that take >2s\n";
            echo "- Check for unnecessary delays, retries, or heavy operations\n";
            echo "- Use mocks and stubs to avoid real I/O operations\n";
            echo "- Consider splitting complex tests into smaller units\n";
        }
        break;
}

// Cleanup handled in the loop above

echo "\n";
