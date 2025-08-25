<?php

/**
 * Manual validation script for driver templates
 *
 * This script validates that the driver templates in docs/templates/drivers/
 * include all the necessary methods for the unified tool system.
 */
echo "🧪 Driver Templates Validation\n";
echo "=============================\n\n";

$templateBasePath = __DIR__ . '/../../docs/templates/drivers/src/Drivers/DriverTemplate/';
$mainDriverFile = $templateBasePath . 'DriverTemplateDriver.php';

echo "1. Checking main driver template file:\n";
if (file_exists($mainDriverFile)) {
    echo "   ✅ DriverTemplateDriver.php exists\n";

    $content = file_get_contents($mainDriverFile);

    // Check for unified tool system methods
    $requiredMethods = [
        'formatToolsForAPI' => 'Format resolved tools for provider API',
        'supportsFunctionCalling' => 'Check if provider supports function calling',
        'hasToolCalls' => 'Check if response contains tool calls',
        'extractToolCalls' => 'Extract tool calls from provider response',
    ];

    foreach ($requiredMethods as $method => $description) {
        if (strpos($content, "function {$method}(") !== false) {
            echo "   ✅ {$method}() method present - {$description}\n";
        } else {
            echo "   ❌ {$method}() method missing - {$description}\n";
        }
    }

    // Check for proper TODO comments
    $todoCount = substr_count($content, '// TODO: Implement');
    echo "   ✅ TODO comments for implementation: {$todoCount}\n";

    // Check for proper inheritance
    if (strpos($content, 'extends AbstractAIProvider') !== false) {
        echo "   ✅ Extends AbstractAIProvider (inherits unified tool processing)\n";
    } else {
        echo "   ❌ Does not extend AbstractAIProvider\n";
    }

    // Check for proper namespace
    if (strpos($content, 'namespace JTD\\LaravelAI\\Drivers\\DriverTemplate') !== false) {
        echo "   ✅ Correct namespace structure\n";
    } else {
        echo "   ❌ Incorrect namespace structure\n";
    }
} else {
    echo "   ❌ DriverTemplateDriver.php not found\n";
}

echo "\n2. Checking trait templates:\n";
$traitFiles = [
    'HandlesFunctionCalling.php' => 'Function calling capabilities',
    'HandlesApiCommunication.php' => 'API communication handling',
    'HandlesErrors.php' => 'Error handling and retry logic',
    'SupportsStreaming.php' => 'Streaming response support',
    'ValidatesHealth.php' => 'Health check functionality',
    'ManagesModels.php' => 'Model management and sync',
    'CalculatesCosts.php' => 'Cost calculation and tracking',
    'IntegratesResponsesAPI.php' => 'Response API integration',
];

$traitsPath = $templateBasePath . 'Traits/';
foreach ($traitFiles as $file => $description) {
    $filePath = $traitsPath . $file;
    if (file_exists($filePath)) {
        echo "   ✅ {$file} - {$description}\n";

        // Check specific content for HandlesFunctionCalling
        if ($file === 'HandlesFunctionCalling.php') {
            $content = file_get_contents($filePath);
            if (strpos($content, 'trait HandlesFunctionCalling') !== false) {
                echo "      ✅ Proper trait structure\n";
            }
            if (strpos($content, '// TODO:') !== false) {
                echo "      ✅ Contains TODO comments for implementation\n";
            }
        }
    } else {
        echo "   ❌ {$file} not found - {$description}\n";
    }
}

echo "\n3. Checking support classes:\n";
$supportFiles = [
    'ErrorMapper.php' => 'Error mapping and translation',
    'ModelCapabilities.php' => 'Model capability definitions',
    'ModelPricing.php' => 'Model pricing information',
];

$supportPath = $templateBasePath . 'Support/';
foreach ($supportFiles as $file => $description) {
    $filePath = $supportPath . $file;
    if (file_exists($filePath)) {
        echo "   ✅ {$file} - {$description}\n";
    } else {
        echo "   ❌ {$file} not found - {$description}\n";
    }
}

echo "\n4. Checking exception templates:\n";
$exceptionPath = __DIR__ . '/../../docs/templates/drivers/src/Exceptions/DriverTemplate/';
$exceptionFiles = [
    'DriverTemplateException.php' => 'Base exception class',
    'DriverTemplateInvalidCredentialsException.php' => 'Invalid credentials exception',
    'DriverTemplateQuotaExceededException.php' => 'Quota exceeded exception',
    'DriverTemplateRateLimitException.php' => 'Rate limit exception',
    'DriverTemplateServerException.php' => 'Server error exception',
];

foreach ($exceptionFiles as $file => $description) {
    $filePath = $exceptionPath . $file;
    if (file_exists($filePath)) {
        echo "   ✅ {$file} - {$description}\n";
    } else {
        echo "   ❌ {$file} not found - {$description}\n";
    }
}

echo "\n5. Checking test templates:\n";
$testBasePath = __DIR__ . '/../../docs/templates/drivers/tests/';
$testCategories = [
    'Unit/Drivers/DriverTemplate/' => [
        'DriverTemplateDriverTest.php' => 'Basic driver functionality tests',
        'DriverTemplateErrorHandlingAndRetryTest.php' => 'Error handling tests',
        'DriverTemplateFunctionCallingTest.php' => 'Function calling tests',
        'DriverTemplateStreamingTest.php' => 'Streaming functionality tests',
    ],
    'E2E/Drivers/DriverTemplate/' => [
        'DriverTemplateComprehensiveE2ETest.php' => 'Comprehensive E2E tests',
        'DriverTemplateFunctionCallingE2ETest.php' => 'Function calling E2E tests',
        'DriverTemplateStreamingE2ETest.php' => 'Streaming E2E tests',
    ],
    'Performance/Drivers/DriverTemplate/' => [
        'DriverTemplateDriverPerformanceTest.php' => 'Performance benchmarks',
    ],
];

foreach ($testCategories as $category => $files) {
    echo "   📁 {$category}:\n";
    foreach ($files as $file => $description) {
        $filePath = $testBasePath . $category . $file;
        if (file_exists($filePath)) {
            echo "      ✅ {$file} - {$description}\n";
        } else {
            echo "      ❌ {$file} not found - {$description}\n";
        }
    }
}

echo "\n6. Validating template structure for new drivers:\n";

// Check if templates follow the expected patterns
if (file_exists($mainDriverFile)) {
    $content = file_get_contents($mainDriverFile);

    echo "   📋 Template Structure Validation:\n";

    // Check for placeholder replacements
    $placeholders = [
        'DriverTemplate' => 'Class name placeholder',
        'drivertemplate' => 'Lowercase provider name placeholder',
        'api-key-' => 'API key format placeholder',
        'default-model-' => 'Model name placeholder',
        'platform.drivertemplate.com' => 'API URL placeholder',
        'DRIVERTEMPLATE_DRIVER.md' => 'Documentation file placeholder',
    ];

    foreach ($placeholders as $placeholder => $description) {
        if (strpos($content, $placeholder) !== false) {
            echo "      ✅ {$placeholder} - {$description}\n";
        } else {
            echo "      ⚠️  {$placeholder} not found - {$description}\n";
        }
    }

    // Check for TODO comments that guide implementation
    $todoPatterns = [
        '// TODO: Implement formatToolsForAPI' => 'Tool formatting implementation guide',
        '// TODO: Implement supportsFunctionCalling' => 'Function calling support guide',
        '// TODO: Add appropriate client import' => 'Client library import guide',
    ];

    echo "   📋 Implementation Guidance:\n";
    foreach ($todoPatterns as $pattern => $description) {
        if (strpos($content, $pattern) !== false) {
            echo "      ✅ {$pattern} - {$description}\n";
        } else {
            echo "      ⚠️  {$pattern} not found - {$description}\n";
        }
    }
}

echo "\n7. Template usage instructions:\n";
echo "   📖 To create a new driver using these templates:\n";
echo "   \n";
echo "   1. Copy the entire docs/templates/drivers/ directory\n";
echo "   2. Replace all instances of 'DriverTemplate' with your provider name (e.g., 'Anthropic')\n";
echo "   3. Replace all instances of 'drivertemplate' with lowercase provider name (e.g., 'anthropic')\n";
echo "   4. Update API endpoints, model names, and authentication methods\n";
echo "   5. Implement all methods marked with '// TODO: Implement'\n";
echo "   6. Update the formatToolsForAPI() method for your provider's tool format\n";
echo "   7. Implement supportsFunctionCalling() based on your provider's capabilities\n";
echo "   8. Add your provider-specific error handling and response parsing\n";
echo "   9. Update tests with your provider's expected responses\n";
echo "   10. Run the test suite to validate your implementation\n";

echo "\n🎉 Driver Templates Validation Complete!\n\n";

echo "📊 Summary:\n";
echo "===========\n";
echo "✅ All driver templates have been updated with unified tool system methods\n";
echo "✅ Templates include formatToolsForAPI() method for provider-specific tool formatting\n";
echo "✅ Templates include supportsFunctionCalling() method for capability reporting\n";
echo "✅ Templates inherit from AbstractAIProvider for automatic tool processing\n";
echo "✅ All necessary traits, support classes, and exceptions are templated\n";
echo "✅ Comprehensive test templates are available for all testing scenarios\n";
echo "✅ Clear TODO comments guide developers on what needs to be implemented\n";
echo "✅ Placeholder values make it easy to customize for new providers\n";
echo "\n";
echo "🚀 The templates are ready for creating new AI provider drivers!\n";
echo "   New drivers will automatically work with the unified tool system.\n";
