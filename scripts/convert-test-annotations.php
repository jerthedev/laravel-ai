<?php

/**
 * Convert PHPUnit @test annotations to #[Test] attributes
 *
 * This script converts all @test annotations to #[Test] attributes
 * and adds the necessary use statement for PHPUnit 12 compatibility.
 */
$testDir = __DIR__ . '/../tests';

function convertTestFile($filePath)
{
    $content = file_get_contents($filePath);
    $originalContent = $content;

    // Check if file contains @test annotations
    if (! preg_match('/\/\*\*\s*@test\s*\*\//', $content)) {
        return false; // No changes needed
    }

    // Add use statement if not present
    if (! preg_match('/use PHPUnit\\Framework\\Attributes\\Test;/', $content)) {
        // Find the last use statement or namespace declaration
        if (preg_match('/^(namespace [^;]+;)\s*$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPos = $matches[0][1] + strlen($matches[0][0]);
            $content = substr_replace($content, "\n\nuse PHPUnit\\Framework\\Attributes\\Test;", $insertPos, 0);
        } elseif (preg_match('/^use [^;]+;$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            // Find the last use statement
            preg_match_all('/^use [^;]+;$/m', $content, $allMatches, PREG_OFFSET_CAPTURE);
            $lastMatch = end($allMatches[0]);
            $insertPos = $lastMatch[1] + strlen($lastMatch[0]);
            $content = substr_replace($content, "\nuse PHPUnit\\Framework\\Attributes\\Test;", $insertPos, 0);
        } else {
            // Insert after opening PHP tag
            $content = preg_replace('/^<\?php\s*$/m', "<?php\n\nuse PHPUnit\\Framework\\Attributes\\Test;", $content);
        }
    }

    // Convert /** @test */ to #[Test]
    $content = preg_replace('/\s*\/\*\*\s*@test\s*\*\/\s*\n/', "\n    #[Test]\n", $content);

    // Clean up any double newlines
    $content = preg_replace('/\n\n\n+/', "\n\n", $content);

    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);

        return true;
    }

    return false;
}

function scanDirectory($dir)
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

echo "🔄 Converting PHPUnit @test annotations to #[Test] attributes...\n\n";

$testFiles = scanDirectory($testDir);
$convertedFiles = 0;
$totalAnnotations = 0;

foreach ($testFiles as $file) {
    $relativePath = str_replace(__DIR__ . '/../', '', $file);

    // Count @test annotations in file
    $content = file_get_contents($file);
    $annotationCount = preg_match_all('/\/\*\*\s*@test\s*\*\//', $content);

    if ($annotationCount > 0) {
        echo "📝 Processing: {$relativePath} ({$annotationCount} annotations)\n";

        if (convertTestFile($file)) {
            $convertedFiles++;
            $totalAnnotations += $annotationCount;
            echo "   ✅ Converted {$annotationCount} annotations\n";
        } else {
            echo "   ⚠️  No changes made\n";
        }
    }
}

echo "\n🎉 Conversion Complete!\n";
echo "📊 Summary:\n";
echo "   - Files processed: {$convertedFiles}\n";
echo "   - Total annotations converted: {$totalAnnotations}\n";
echo "   - All tests now use #[Test] attributes\n\n";

echo "✅ Next steps:\n";
echo "   1. Run tests to verify conversion: vendor/bin/phpunit\n";
echo "   2. Upgrade to PHPUnit 12: composer require phpunit/phpunit:^12.0 --dev\n";
echo "   3. Update phpunit.xml schema to version 12.0\n\n";
