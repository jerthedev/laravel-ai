# Test Organization

This document describes the organization structure of the test suite for the Laravel AI package.

## Directory Structure

The test suite is organized into the following main categories:

### Test Categories

- **`Unit/`** - Unit tests that test individual classes and methods in isolation
- **`Integration/`** - Integration tests that test how components work together
- **`Feature/`** - Feature tests that test complete features from a user perspective
- **`Performance/`** - Performance and benchmark tests
- **`E2E/`** - End-to-end tests that test against real APIs

### Driver-Specific Organization

Within each test category, driver-specific tests are organized under `Drivers/{DriverName}/`:

```
tests/
├── Unit/
│   ├── Drivers/
│   │   └── OpenAI/
│   │       ├── OpenAIDriverTest.php
│   │       ├── OpenAIErrorHandlingAndRetryTest.php
│   │       ├── OpenAIFunctionCallingTest.php
│   │       ├── OpenAIFunctionCallingErrorTest.php
│   │       ├── OpenAIStreamingTest.php
│   │       └── OpenAIStreamingErrorTest.php
│   ├── ConfigurationTest.php
│   ├── DriverSystemTest.php
│   └── ...
├── E2E/
│   ├── Drivers/
│   │   └── OpenAI/
│   │       ├── OpenAIAdvancedIntegrationTest.php
│   │       ├── OpenAIComprehensiveE2ETest.php
│   │       ├── OpenAIFunctionCallingE2ETest.php
│   │       ├── OpenAIQuotaErrorTest.php
│   │       ├── OpenAIResponsesAPITest.php
│   │       ├── OpenAIStreamingE2ETest.php
│   │       └── ...
│   └── E2ETestCase.php
├── Performance/
│   ├── Drivers/
│   │   └── OpenAI/
│   │       └── OpenAIDriverPerformanceTest.php
│   └── PerformanceBenchmark.php
├── Integration/
│   ├── Drivers/
│   │   └── OpenAI/
│   └── ...
└── Feature/
    ├── Drivers/
    │   └── OpenAI/
    └── ...
```

## Adding New Driver Tests

When adding tests for a new AI provider driver, follow this organization pattern:

1. Create the driver directory under each relevant test category:
   ```bash
   mkdir -p tests/Unit/Drivers/NewDriver
   mkdir -p tests/E2E/Drivers/NewDriver
   mkdir -p tests/Performance/Drivers/NewDriver
   # etc.
   ```

2. Place driver-specific tests in the appropriate directories:
   - Unit tests → `tests/Unit/Drivers/NewDriver/`
   - E2E tests → `tests/E2E/Drivers/NewDriver/`
   - Performance tests → `tests/Performance/Drivers/NewDriver/`

## Test Naming Conventions

- **Unit Tests**: `{ClassName}Test.php` (e.g., `OpenAIDriverTest.php`)
- **Feature-specific Tests**: `{DriverName}{Feature}Test.php` (e.g., `OpenAIStreamingTest.php`)
- **Error Handling Tests**: `{DriverName}{Feature}ErrorTest.php` (e.g., `OpenAIFunctionCallingErrorTest.php`)
- **E2E Tests**: `{DriverName}{Feature}E2ETest.php` (e.g., `OpenAIStreamingE2ETest.php`)
- **Performance Tests**: `{DriverName}DriverPerformanceTest.php`

## Running Tests

### Run all tests:
```bash
vendor/bin/phpunit
```

### Run tests by category:
```bash
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/E2E
vendor/bin/phpunit tests/Performance
```

### Run driver-specific tests:
```bash
vendor/bin/phpunit tests/Unit/Drivers/OpenAI
vendor/bin/phpunit tests/E2E/Drivers/OpenAI
```

### Run specific test files:
```bash
vendor/bin/phpunit tests/Unit/Drivers/OpenAI/OpenAIDriverTest.php
```

## Test Performance Analysis

Use the test performance script to analyze test execution times:

```bash
php scripts/test-performance.php --format=summary
```

This will show:
- Total test count and execution time
- Slow tests that may need optimization
- Performance breakdown by test suite

## Benefits of This Organization

1. **Scalability**: Easy to add new drivers without cluttering the main test directories
2. **Maintainability**: Driver-specific tests are grouped together for easier maintenance
3. **Clarity**: Clear separation between different types of tests and different drivers
4. **Performance**: Easier to run subsets of tests for faster development cycles
