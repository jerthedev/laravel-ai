#!/bin/bash

# Test Coverage by Feature Script
# Runs coverage analysis for each Sprint4b feature area

echo "🧪 Running Feature-Based Test Coverage Analysis"
echo "=============================================="

# Create coverage reports directory
mkdir -p coverage/features

# Sprint4b Feature Test Suites
features=("CostTracking" "BudgetManagement" "Analytics" "MCPFramework" "MCPSetup" "MCPIntegration" "Performance")

echo ""
echo "📊 Running coverage for individual features:"

for feature in "${features[@]}"; do
    echo "  → Testing $feature feature..."
    vendor/bin/phpunit --testsuite="$feature" \
        --coverage-html="coverage/features/$feature" \
        --coverage-clover="coverage/features/$feature.xml" \
        --coverage-text \
        --coverage-filter=src/ \
        > "coverage/features/$feature-report.txt" 2>&1
    
    if [ $? -eq 0 ]; then
        echo "    ✅ $feature tests completed"
    else
        echo "    ❌ $feature tests failed"
    fi
done

echo ""
echo "📈 Running combined Sprint4b coverage:"
vendor/bin/phpunit --testsuite=Sprint4b \
    --coverage-html=coverage/sprint4b \
    --coverage-clover=coverage/sprint4b.xml \
    --coverage-text \
    --coverage-filter=src/

echo ""
echo "📋 Coverage reports generated in:"
echo "  - coverage/features/ (individual features)"
echo "  - coverage/sprint4b/ (combined Sprint4b)"
echo ""
echo "🎯 To view a specific feature coverage:"
echo "  open coverage/features/[FeatureName]/index.html"
echo ""
echo "🎯 To view Sprint4b combined coverage:"
echo "  open coverage/sprint4b/index.html"
