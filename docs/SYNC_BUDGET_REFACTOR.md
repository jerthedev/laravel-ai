# Sync & Budget System Refactor

## Overview

This document outlines the complete migration from static pricing files to a centralized, database-first pricing system with intelligent discovery capabilities. The refactor maintains driver ownership of pricing defaults while providing centralized management, normalization, and optional AI-powered pricing discovery.

## Current State Analysis

### Existing Components
- ✅ Database schema: `ai_providers`, `ai_provider_models`, `ai_provider_model_costs` tables
- ✅ Static pricing files: `src/Drivers/{Provider}/Support/ModelPricing.php`
- ✅ Sync commands: `ai:sync-models` (models only, no pricing)
- ✅ Budget system: Uses static pricing for cost estimation
- ✅ Cost tracking: Basic implementation with hardcoded rates

### Current Problems
- ❌ Database tables exist but aren't used
- ❌ Static pricing files have inconsistent units (1K vs 1M tokens)
- ❌ No centralized pricing management
- ❌ Budget calculations use outdated/estimated rates
- ❌ No pricing sync or discovery system
- ❌ Manual updates required for pricing changes

## Target Architecture

### Core Principles
1. **Database-First**: `ai_provider_model_costs` as primary source
2. **Driver Ownership**: Each driver maintains static defaults
3. **Smart Fallbacks**: Database → Driver → Universal fallback chain
4. **Unit Standardization**: Consistent pricing units across all providers
5. **Optional AI Discovery**: Intelligent pricing updates (opt-in)
6. **Non-Blocking**: Graceful degradation when components unavailable

### Data Flow
```
Provider APIs → AI Discovery (optional) → Database Tables → PricingService → Budget/Cost Systems
                     ↓
              Driver Static Defaults (fallback)
```

## Implementation Plan

### Phase 1: Foundation & Standardization

#### 1.1 Create Standardization Enums

**File: `src/Enums/PricingUnit.php`**
```php
<?php

namespace JTD\LaravelAI\Enums;

enum PricingUnit: string
{
    // Token-based pricing
    case PER_TOKEN = 'per_token';
    case PER_1K_TOKENS = '1k_tokens';
    case PER_1M_TOKENS = '1m_tokens';
    
    // Character-based pricing  
    case PER_CHARACTER = 'per_character';
    case PER_1K_CHARACTERS = '1k_characters';
    
    // Time-based pricing
    case PER_SECOND = 'per_second';
    case PER_MINUTE = 'per_minute';
    case PER_HOUR = 'per_hour';
    
    // Request-based pricing
    case PER_REQUEST = 'per_request';
    case PER_IMAGE = 'per_image';
    case PER_AUDIO_FILE = 'per_audio_file';
    
    // Data-based pricing
    case PER_MB = 'per_mb';
    case PER_GB = 'per_gb';

    public function label(): string
    {
        return match($this) {
            self::PER_TOKEN => 'Per Token',
            self::PER_1K_TOKENS => 'Per 1,000 Tokens',
            self::PER_1M_TOKENS => 'Per 1,000,000 Tokens',
            // ... other cases
        };
    }

    public function getBaseUnit(): self
    {
        return match($this) {
            self::PER_1K_TOKENS, self::PER_1M_TOKENS => self::PER_TOKEN,
            self::PER_1K_CHARACTERS => self::PER_CHARACTER,
            default => $this,
        };
    }

    public function getMultiplier(): float
    {
        return match($this) {
            self::PER_1K_TOKENS => 1000,
            self::PER_1M_TOKENS => 1000000,
            self::PER_1K_CHARACTERS => 1000,
            default => 1,
        };
    }
}
```

**File: `src/Enums/BillingModel.php`**
```php
<?php

namespace JTD\LaravelAI\Enums;

enum BillingModel: string
{
    case PAY_PER_USE = 'pay_per_use';
    case TIERED = 'tiered';
    case SUBSCRIPTION = 'subscription';
    case CREDITS = 'credits';
    case FREE_TIER = 'free_tier';
    case ENTERPRISE = 'enterprise';

    public function label(): string
    {
        return match($this) {
            self::PAY_PER_USE => 'Pay Per Use',
            self::TIERED => 'Tiered Pricing',
            self::SUBSCRIPTION => 'Subscription',
            self::CREDITS => 'Credits',
            self::FREE_TIER => 'Free Tier',
            self::ENTERPRISE => 'Enterprise',
        };
    }

    public function supportsAutomaticCalculation(): bool
    {
        return match($this) {
            self::PAY_PER_USE, self::TIERED, self::CREDITS => true,
            default => false,
        };
    }
}
```

#### 1.2 Create Common Pricing Interface

**File: `src/Contracts/PricingInterface.php`**
```php
<?php

namespace JTD\LaravelAI\Contracts;

interface PricingInterface
{
    /**
     * Get pricing for a specific model.
     */
    public function getModelPricing(string $model): array;

    /**
     * Calculate cost based on usage.
     */
    public function calculateCost(string $model, array $usage): float;

    /**
     * Get all model pricing for this provider.
     */
    public function getAllModelPricing(): array;

    /**
     * Get supported pricing units.
     */
    public function getSupportedUnits(): array;

    /**
     * Validate pricing configuration.
     */
    public function validatePricing(): array;
}
```

#### 1.3 Update Existing Driver Pricing Files

**Example: Update `src/Drivers/OpenAI/Support/ModelPricing.php`**
```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Support;

use JTD\LaravelAI\Contracts\PricingInterface;
use JTD\LaravelAI\Enums\PricingUnit;
use JTD\LaravelAI\Enums\BillingModel;

class ModelPricing implements PricingInterface
{
    /**
     * OpenAI model pricing with standardized format.
     */
    public static array $pricing = [
        'gpt-4o' => [
            'input' => 0.0025,
            'output' => 0.01,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-4o-mini' => [
            'input' => 0.00015,
            'output' => 0.0006,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'dall-e-3' => [
            'cost' => 0.04,
            'unit' => PricingUnit::PER_IMAGE,
            'size' => '1024x1024',
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        // ... other models
    ];

    public function getModelPricing(string $model): array
    {
        return self::$pricing[$model] ?? [];
    }

    public function calculateCost(string $model, array $usage): float
    {
        $pricing = $this->getModelPricing($model);
        if (empty($pricing)) return 0.0;

        $unit = $pricing['unit'];
        
        return match($unit) {
            PricingUnit::PER_1K_TOKENS => $this->calculateTokenCost($pricing, $usage),
            PricingUnit::PER_IMAGE => $pricing['cost'] * ($usage['images'] ?? 1),
            PricingUnit::PER_MINUTE => $pricing['cost'] * ($usage['minutes'] ?? 0),
            default => 0.0,
        };
    }

    private function calculateTokenCost(array $pricing, array $usage): float
    {
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        
        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];
        
        return $inputCost + $outputCost;
    }

    public function getAllModelPricing(): array
    {
        return self::$pricing;
    }

    public function getSupportedUnits(): array
    {
        return [
            PricingUnit::PER_1K_TOKENS,
            PricingUnit::PER_IMAGE,
            PricingUnit::PER_MINUTE,
        ];
    }

    public function validatePricing(): array
    {
        $errors = [];
        
        foreach (self::$pricing as $model => $data) {
            if (!isset($data['unit']) || !$data['unit'] instanceof PricingUnit) {
                $errors[] = "Model '{$model}' missing or invalid unit";
            }
            
            if (!isset($data['billing_model']) || !$data['billing_model'] instanceof BillingModel) {
                $errors[] = "Model '{$model}' missing or invalid billing_model";
            }
        }
        
        return $errors;
    }
}
```

#### 1.4 Create Pricing Validation Service

**File: `src/Services/PricingValidator.php`**
```php
<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Enums\PricingUnit;
use JTD\LaravelAI\Enums\BillingModel;

class PricingValidator
{
    public function validatePricingArray(array $pricing): array
    {
        $errors = [];
        
        foreach ($pricing as $model => $data) {
            $errors = array_merge($errors, $this->validateModelPricing($model, $data));
        }
        
        return $errors;
    }

    private function validateModelPricing(string $model, array $data): array
    {
        $errors = [];
        
        // Required fields
        $required = ['unit', 'billing_model', 'currency'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $errors[] = "Model '{$model}' missing required field: {$field}";
            }
        }
        
        // Enum validation
        if (isset($data['unit']) && !$data['unit'] instanceof PricingUnit) {
            $errors[] = "Model '{$model}' unit must be PricingUnit enum";
        }
        
        if (isset($data['billing_model']) && !$data['billing_model'] instanceof BillingModel) {
            $errors[] = "Model '{$model}' billing_model must be BillingModel enum";
        }
        
        // Unit-specific validation
        if (isset($data['unit'])) {
            $errors = array_merge($errors, $this->validateUnitSpecificFields($model, $data));
        }
        
        return $errors;
    }

    private function validateUnitSpecificFields(string $model, array $data): array
    {
        $errors = [];
        $unit = $data['unit'];
        
        if (in_array($unit, [PricingUnit::PER_1K_TOKENS, PricingUnit::PER_1M_TOKENS])) {
            if (!isset($data['input']) || !isset($data['output'])) {
                $errors[] = "Model '{$model}' with token pricing must have 'input' and 'output' fields";
            }
        } elseif (in_array($unit, [PricingUnit::PER_IMAGE, PricingUnit::PER_REQUEST])) {
            if (!isset($data['cost'])) {
                $errors[] = "Model '{$model}' with request pricing must have 'cost' field";
            }
        }
        
        return $errors;
    }
}
```

### Phase 2: Database-First Architecture

#### 2.1 Enhanced PricingService with Database Integration

**File: `src/Services/PricingService.php`**
```php
<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Services\PricingValidator;

class PricingService
{
    public function __construct(
        protected DriverManager $driverManager,
        protected PricingValidator $validator
    ) {}

    /**
     * Get pricing with database-first fallback chain.
     */
    public function getModelPricing(string $provider, string $model): array
    {
        // 1. Try database first
        if ($dbPricing = $this->getFromDatabase($provider, $model)) {
            return $dbPricing;
        }
        
        // 2. Fallback to driver static defaults
        if ($driverPricing = $this->getFromDriver($provider, $model)) {
            return $driverPricing;
        }
        
        // 3. Universal fallback
        return $this->getUniversalFallback();
    }

    /**
     * Calculate cost using database-first pricing.
     */
    public function calculateCost(string $provider, string $model, int $inputTokens, int $outputTokens): array
    {
        $pricing = $this->getModelPricing($provider, $model);
        
        if (empty($pricing)) {
            return $this->getDefaultCostCalculation($inputTokens, $outputTokens);
        }

        $usage = [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];

        // Use driver's calculation method if available
        if ($driver = $this->getDriverPricingClass($provider)) {
            $totalCost = $driver->calculateCost($model, $usage);
        } else {
            $totalCost = $this->calculateGenericCost($pricing, $usage);
        }

        return [
            'model' => $model,
            'provider' => $provider,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'input_cost' => $this->calculateInputCost($pricing, $inputTokens),
            'output_cost' => $this->calculateOutputCost($pricing, $outputTokens),
            'total_cost' => $totalCost,
            'currency' => $pricing['currency'] ?? 'USD',
            'unit' => $pricing['unit']?->value ?? 'unknown',
            'source' => $this->getPricingSource($provider, $model),
        ];
    }

    private function getFromDatabase(string $provider, string $model): ?array
    {
        $cacheKey = "pricing:db:{$provider}:{$model}";
        
        return Cache::remember($cacheKey, 3600, function () use ($provider, $model) {
            $result = DB::table('ai_provider_model_costs as costs')
                ->join('ai_provider_models as models', 'costs.ai_provider_model_id', '=', 'models.id')
                ->join('ai_providers as providers', 'models.ai_provider_id', '=', 'providers.id')
                ->where('providers.name', $provider)
                ->where('models.name', $model)
                ->where('costs.is_current', true)
                ->select([
                    'costs.cost_per_unit',
                    'costs.unit',
                    'costs.cost_type',
                    'costs.currency',
                    'costs.billing_model',
                    'costs.effective_date',
                ])
                ->get();

            if ($result->isEmpty()) {
                return null;
            }

            // Transform database results to pricing array
            return $this->transformDatabasePricing($result);
        });
    }

    private function getFromDriver(string $provider, string $model): ?array
    {
        try {
            $driver = $this->driverManager->driver($provider);
            $pricingClass = $this->getDriverPricingClass($provider);
            
            if ($pricingClass) {
                return $pricingClass->getModelPricing($model);
            }
        } catch (\Exception $e) {
            // Driver not available or no pricing class
        }
        
        return null;
    }

    private function getDriverPricingClass(string $provider): ?object
    {
        $pricingClass = "\\JTD\\LaravelAI\\Drivers\\" . ucfirst($provider) . "\\Support\\ModelPricing";
        
        if (class_exists($pricingClass)) {
            return new $pricingClass();
        }
        
        return null;
    }

    private function getUniversalFallback(): array
    {
        return [
            'input' => 0.00001,  // $0.01 per 1K tokens
            'output' => 0.00002, // $0.02 per 1K tokens
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'source' => 'universal_fallback',
        ];
    }

    private function transformDatabasePricing($results): array
    {
        $pricing = [
            'currency' => $results->first()->currency ?? 'USD',
            'billing_model' => BillingModel::from($results->first()->billing_model ?? 'pay_per_use'),
            'unit' => PricingUnit::from($results->first()->unit ?? '1k_tokens'),
            'effective_date' => $results->first()->effective_date,
            'source' => 'database',
        ];

        foreach ($results as $cost) {
            if ($cost->cost_type === 'input') {
                $pricing['input'] = $cost->cost_per_unit;
            } elseif ($cost->cost_type === 'output') {
                $pricing['output'] = $cost->cost_per_unit;
            } else {
                $pricing['cost'] = $cost->cost_per_unit;
            }
        }

        return $pricing;
    }

    private function calculateGenericCost(array $pricing, array $usage): float
    {
        $unit = $pricing['unit'];

        return match($unit) {
            PricingUnit::PER_1K_TOKENS, PricingUnit::PER_1M_TOKENS =>
                $this->calculateTokenBasedCost($pricing, $usage),
            PricingUnit::PER_IMAGE, PricingUnit::PER_REQUEST =>
                $pricing['cost'] * ($usage['requests'] ?? 1),
            PricingUnit::PER_MINUTE =>
                $pricing['cost'] * ($usage['minutes'] ?? 0),
            default => 0.0,
        };
    }

    private function calculateTokenBasedCost(array $pricing, array $usage): float
    {
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        $unit = $pricing['unit'];

        $divisor = match($unit) {
            PricingUnit::PER_1K_TOKENS => 1000,
            PricingUnit::PER_1M_TOKENS => 1000000,
            default => 1000,
        };

        $inputCost = ($inputTokens / $divisor) * ($pricing['input'] ?? 0);
        $outputCost = ($outputTokens / $divisor) * ($pricing['output'] ?? 0);

        return $inputCost + $outputCost;
    }

    private function getPricingSource(string $provider, string $model): string
    {
        if ($this->getFromDatabase($provider, $model)) {
            return 'database';
        } elseif ($this->getFromDriver($provider, $model)) {
            return 'driver_static';
        } else {
            return 'universal_fallback';
        }
    }

    /**
     * Clear pricing cache.
     */
    public function clearCache(?string $provider = null, ?string $model = null): void
    {
        if ($provider && $model) {
            Cache::forget("pricing:db:{$provider}:{$model}");
        } elseif ($provider) {
            // Clear all models for provider
            $pattern = "pricing:db:{$provider}:*";
            // Implementation depends on cache driver
        } else {
            Cache::flush();
        }
    }
}
```

#### 2.2 Update Budget System Integration

**Update: `src/Middleware/BudgetEnforcementMiddleware.php`**
```php
<?php

namespace JTD\LaravelAI\Middleware;

use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Services\BudgetService;

class BudgetEnforcementMiddleware implements AIMiddlewareInterface
{
    public function __construct(
        protected BudgetService $budgetService,
        protected PricingService $pricingService
    ) {}

    protected function estimateRequestCost(AIMessage $message): float
    {
        $estimatedTokens = $this->estimateTokens($message->content);
        $provider = $message->provider ?? 'openai';
        $model = $message->model ?? $this->getDefaultModel($provider);

        // Use centralized pricing service
        $costData = $this->pricingService->calculateCost(
            $provider,
            $model,
            (int)($estimatedTokens * 0.75), // Estimated input
            (int)($estimatedTokens * 0.25)  // Estimated output
        );

        return $costData['total_cost'] ?? $estimatedTokens * 0.00001;
    }
}
```

**Update: `src/Listeners/CostTrackingListener.php`**
```php
<?php

namespace JTD\LaravelAI\Listeners;

use JTD\LaravelAI\Services\PricingService;

class CostTrackingListener implements ShouldQueue
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    protected function calculateMessageCost($message, $response): float
    {
        $inputTokens = $this->getInputTokens($response);
        $outputTokens = $this->getOutputTokens($response);
        $provider = $message->provider ?? 'openai';
        $model = $response->model ?? $message->model ?? 'gpt-4o-mini';

        try {
            $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);
            return $costData['total_cost'] ?? 0.0;
        } catch (\Exception $e) {
            // Fallback calculation
            return ($inputTokens * 0.00001) + ($outputTokens * 0.00002);
        }
    }
}
```

### Phase 3: Enhanced Model Sync with Pricing

#### 3.1 Configuration Updates

**Update: `config/ai.php`**
```php
'model_sync' => [
    'include_pricing' => env('AI_SYNC_INCLUDE_PRICING', true),
    'validate_pricing' => env('AI_SYNC_VALIDATE_PRICING', true),

    'pricing_discovery' => [
        'ai_powered' => env('AI_PRICING_DISCOVERY_ENABLED', false),
        'use_brave_search' => env('AI_USE_BRAVE_SEARCH_PRICING', false),
        'fallback_to_static' => env('AI_FALLBACK_TO_STATIC_PRICING', true),
        'warn_on_missing_mcp' => env('AI_WARN_MISSING_MCP', true),
        'cost_threshold_warning' => env('AI_PRICING_COST_WARNING', 0.05), // $0.05
    ],

    'pricing_validation' => [
        'validate_against_static' => true,
        'alert_on_major_changes' => true,
        'change_threshold_percent' => 20,
        'require_confirmation' => true,
    ],

    'database' => [
        'batch_size' => env('AI_SYNC_BATCH_SIZE', 100),
        'transaction_timeout' => env('AI_SYNC_TRANSACTION_TIMEOUT', 300),
        'backup_before_sync' => env('AI_SYNC_BACKUP_BEFORE', true),
    ],
],
```

#### 3.2 Enhanced Sync Models Command

**Update: `src/Console/Commands/SyncModelsCommand.php`**
```php
<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Services\PricingValidator;

class SyncModelsCommand extends Command
{
    protected $signature = 'ai:sync-models
                            {--provider= : Sync specific provider only}
                            {--skip-pricing : Skip pricing synchronization}
                            {--enable-ai-discovery : Enable AI-powered pricing discovery}
                            {--force-ai-discovery : Force AI discovery even without MCP}
                            {--dry-run : Preview changes without applying}
                            {--show-pricing : Show detailed pricing information}
                            {--validate-only : Only validate existing data}';

    protected $description = 'Synchronize AI models and pricing from all configured providers';

    public function __construct(
        protected DriverManager $driverManager,
        protected PricingService $pricingService,
        protected PricingValidator $validator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->displayHeader();

        if ($this->option('validate-only')) {
            return $this->validateExistingData();
        }

        $providers = $this->getProvidersToSync();

        if (empty($providers)) {
            $this->error('No providers available for synchronization.');
            return self::FAILURE;
        }

        $this->displayWarnings();

        $results = [];
        foreach ($providers as $provider) {
            $results[$provider] = $this->syncProvider($provider);
        }

        $this->displaySummary($results);

        return $this->determineExitCode($results);
    }

    private function displayHeader(): void
    {
        $this->info('🔄 AI Models & Pricing Synchronization');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
    }

    private function displayWarnings(): void
    {
        $aiDiscoveryEnabled = config('ai.model_sync.pricing_discovery.ai_powered', false) ||
                             $this->option('enable-ai-discovery');

        if (!$aiDiscoveryEnabled) {
            $this->warn('⚠️  AI-Powered Pricing Discovery is DISABLED');
            $this->line('   Enable in config/ai.php or use --enable-ai-discovery');
            $this->line('   See docs/PRICING_DISCOVERY.md for benefits and costs');
            $this->newLine();
        }

        if ($aiDiscoveryEnabled && !$this->hasBraveSearchMCP() && !$this->option('force-ai-discovery')) {
            $this->warn('⚠️  Brave Search MCP not detected');
            $this->line('   Install for enhanced pricing discovery: composer require mcp/brave-search');
            $this->line('   Falling back to static defaults and provider APIs');
            $this->newLine();
        }

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }
    }

    private function syncProvider(string $provider): array
    {
        $this->line("<info>{$provider}:</info>");

        try {
            $driver = $this->driverManager->driver($provider);

            // Sync models
            $modelResults = $this->syncProviderModels($provider, $driver);

            // Sync pricing (unless skipped)
            $pricingResults = [];
            if (!$this->option('skip-pricing')) {
                $pricingResults = $this->syncProviderPricing($provider, $driver);
            }

            $this->displayProviderResults($provider, $modelResults, $pricingResults);

            return [
                'status' => 'success',
                'models' => $modelResults,
                'pricing' => $pricingResults,
            ];

        } catch (\Exception $e) {
            $this->error("  ❌ Sync failed: " . $e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function syncProviderModels(string $provider, $driver): array
    {
        // Implementation for model sync
        // This would populate ai_providers and ai_provider_models tables

        return [
            'synced' => 15,
            'updated' => 3,
            'new' => 2,
        ];
    }

    private function syncProviderPricing(string $provider, $driver): array
    {
        // Get pricing from driver
        $pricingClass = $this->getPricingClass($provider);
        if (!$pricingClass) {
            return ['status' => 'no_pricing_class'];
        }

        $allPricing = $pricingClass->getAllModelPricing();

        // Validate pricing
        $errors = $this->validator->validatePricingArray($allPricing);
        if (!empty($errors)) {
            $this->warn('  ⚠️  Pricing validation errors:');
            foreach ($errors as $error) {
                $this->line("    • {$error}");
            }
        }

        // AI-powered discovery (if enabled)
        if ($this->shouldUseAIDiscovery()) {
            $allPricing = $this->enhancePricingWithAI($provider, $allPricing);
        }

        // Store in database (if not dry run)
        if (!$this->option('dry-run')) {
            $this->storePricingInDatabase($provider, $allPricing);
        }

        return [
            'models_processed' => count($allPricing),
            'validation_errors' => count($errors),
            'ai_enhanced' => $this->shouldUseAIDiscovery(),
        ];
    }

    private function shouldUseAIDiscovery(): bool
    {
        return (config('ai.model_sync.pricing_discovery.ai_powered', false) ||
                $this->option('enable-ai-discovery')) &&
               ($this->hasBraveSearchMCP() || $this->option('force-ai-discovery'));
    }

    private function hasBraveSearchMCP(): bool
    {
        return app()->bound('mcp.brave-search') || class_exists('\\MCP\\BraveSearch\\BraveSearchMCP');
    }

    // ... additional helper methods
}
```

### Phase 4: AI-Powered Pricing Discovery

#### 4.1 Intelligent Pricing Discovery Service

**File: `src/Services/IntelligentPricingDiscovery.php`**
```php
<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Services\AIManager;

class IntelligentPricingDiscovery
{
    public function __construct(
        protected AIManager $aiManager
    ) {}

    /**
     * Discover latest pricing using AI search capabilities.
     */
    public function discoverLatestPricing(string $provider, array $models): array
    {
        $results = [];
        $costEstimate = 0;

        foreach ($models as $model => $currentPricing) {
            try {
                // 1. Try provider API first (if available)
                if ($apiPricing = $this->getFromProviderAPI($provider, $model)) {
                    $results[$model] = array_merge($currentPricing, $apiPricing, ['source' => 'provider_api']);
                    continue;
                }

                // 2. Try AI-powered search
                if ($aiPricing = $this->searchWithAI($provider, $model)) {
                    $results[$model] = array_merge($currentPricing, $aiPricing, ['source' => 'ai_search']);
                    $costEstimate += 0.01; // Estimate cost per search
                    continue;
                }

                // 3. Keep existing pricing
                $results[$model] = array_merge($currentPricing, ['source' => 'static_default']);

            } catch (\Exception $e) {
                Log::warning("Pricing discovery failed for {$provider}:{$model}", [
                    'error' => $e->getMessage()
                ]);

                $results[$model] = array_merge($currentPricing, ['source' => 'error_fallback']);
            }
        }

        return [
            'pricing' => $results,
            'estimated_cost' => $costEstimate,
            'models_enhanced' => count(array_filter($results, fn($r) => $r['source'] === 'ai_search')),
        ];
    }

    private function searchWithAI(string $provider, string $model): ?array
    {
        $query = "Search for latest {$provider} {$model} API pricing per token January 2025 official documentation";

        try {
            $response = $this->aiManager
                ->conversation()
                ->message($query)
                ->tools(['brave_search'])
                ->send();

            return $this->parsePricingFromAIResponse($response, $provider, $model);

        } catch (\Exception $e) {
            Log::warning("AI search failed for {$provider}:{$model}", [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    private function parsePricingFromAIResponse($response, string $provider, string $model): ?array
    {
        // Parse AI response and extract pricing information
        // This would use NLP to extract pricing from search results

        $content = $response->content ?? '';

        // Simple regex patterns for common pricing formats
        $patterns = [
            '/\$?(\d+\.?\d*)\s*per\s*1[,\s]*000\s*tokens/i',
            '/\$?(\d+\.?\d*)\s*\/\s*1[,\s]*000\s*tokens/i',
            '/input.*?\$?(\d+\.?\d*).*?output.*?\$?(\d+\.?\d*)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return $this->buildPricingFromMatches($matches, $provider, $model);
            }
        }

        return null;
    }

    private function buildPricingFromMatches(array $matches, string $provider, string $model): array
    {
        // Build pricing array from regex matches
        // This is a simplified implementation

        if (count($matches) >= 3) {
            // Input/output pricing found
            return [
                'input' => (float) $matches[1],
                'output' => (float) $matches[2],
                'unit' => PricingUnit::PER_1K_TOKENS,
                'currency' => 'USD',
                'billing_model' => BillingModel::PAY_PER_USE,
                'effective_date' => now()->toDateString(),
                'confidence' => 0.8,
            ];
        } elseif (count($matches) >= 2) {
            // Single pricing found
            return [
                'cost' => (float) $matches[1],
                'unit' => PricingUnit::PER_1K_TOKENS,
                'currency' => 'USD',
                'billing_model' => BillingModel::PAY_PER_USE,
                'effective_date' => now()->toDateString(),
                'confidence' => 0.6,
            ];
        }

        return null;
    }

    private function getFromProviderAPI(string $provider, string $model): ?array
    {
        // Try to get pricing directly from provider API
        // This would be provider-specific implementation

        return match($provider) {
            'openai' => $this->getOpenAIPricing($model),
            'gemini' => $this->getGeminiPricing($model),
            'xai' => $this->getXAIPricing($model),
            default => null,
        };
    }

    private function getOpenAIPricing(string $model): ?array
    {
        // OpenAI doesn't provide pricing API, but we could check their pricing page
        return null;
    }

    private function getGeminiPricing(string $model): ?array
    {
        // Google Cloud Pricing API could be used here
        return null;
    }

    private function getXAIPricing(string $model): ?array
    {
        // xAI pricing API (if available)
        return null;
    }
}
```

### Phase 5: Migration & Testing

#### 5.1 Migration Script

**File: `src/Console/Commands/MigratePricingSystemCommand.php`**
```php
<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;

class MigratePricingSystemCommand extends Command
{
    protected $signature = 'ai:migrate-pricing-system
                            {--backup : Create backup before migration}
                            {--validate : Validate migration results}
                            {--rollback : Rollback to previous system}';

    protected $description = 'Migrate from static pricing to database-first pricing system';

    public function handle(): int
    {
        $this->info('🔄 Migrating to Database-First Pricing System');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($this->option('rollback')) {
            return $this->rollbackMigration();
        }

        // Step 1: Backup existing data
        if ($this->option('backup')) {
            $this->backupExistingData();
        }

        // Step 2: Validate all driver pricing
        $this->validateAllDriverPricing();

        // Step 3: Populate database from static pricing
        $this->populateDatabaseFromStatic();

        // Step 4: Update configuration
        $this->updateConfiguration();

        // Step 5: Validate migration
        if ($this->option('validate')) {
            $this->validateMigration();
        }

        $this->info('✅ Migration completed successfully!');
        $this->displayNextSteps();

        return self::SUCCESS;
    }

    private function validateAllDriverPricing(): void
    {
        $this->info('📋 Validating driver pricing configurations...');

        // Implementation to validate all drivers use proper enums
    }

    private function populateDatabaseFromStatic(): void
    {
        $this->info('💾 Populating database from static pricing...');

        // Implementation to sync all static pricing to database
    }

    private function displayNextSteps(): void
    {
        $this->newLine();
        $this->info('🎯 Next Steps:');
        $this->line('1. Run: php artisan ai:sync-models --show-pricing');
        $this->line('2. Test budget enforcement with new pricing');
        $this->line('3. Monitor cost tracking accuracy');
        $this->line('4. Consider enabling AI-powered discovery');
        $this->newLine();
        $this->line('📖 Documentation: docs/PRICING_DISCOVERY.md');
    }
}
```

#### 5.2 Testing Strategy

**File: `tests/Feature/PricingSystemTest.php`**
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Enums\PricingUnit;
use JTD\LaravelAI\Enums\BillingModel;

class PricingSystemTest extends TestCase
{
    public function test_database_first_pricing_fallback_chain()
    {
        $pricingService = app(PricingService::class);

        // Test fallback chain: Database → Driver → Universal
        $pricing = $pricingService->getModelPricing('openai', 'gpt-4o');

        $this->assertIsArray($pricing);
        $this->assertArrayHasKey('unit', $pricing);
        $this->assertInstanceOf(PricingUnit::class, $pricing['unit']);
    }

    public function test_cost_calculation_accuracy()
    {
        $pricingService = app(PricingService::class);

        $cost = $pricingService->calculateCost('openai', 'gpt-4o', 1000, 500);

        $this->assertIsArray($cost);
        $this->assertArrayHasKey('total_cost', $cost);
        $this->assertIsFloat($cost['total_cost']);
        $this->assertGreaterThan(0, $cost['total_cost']);
    }

    public function test_enum_validation()
    {
        $validator = app(PricingValidator::class);

        $invalidPricing = [
            'test-model' => [
                'input' => 0.001,
                'output' => 0.002,
                'unit' => 'invalid_unit', // Should be enum
                'billing_model' => 'invalid_model', // Should be enum
            ]
        ];

        $errors = $validator->validatePricingArray($invalidPricing);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('unit must be PricingUnit enum', implode(' ', $errors));
    }
}
```

## Implementation Checklist

### Phase 1: Foundation (Week 1)
- [ ] Create PricingUnit enum with all supported units
- [ ] Create BillingModel enum with all billing types
- [ ] Create PricingInterface for driver consistency
- [ ] Update all existing driver pricing files to use enums
- [ ] Create PricingValidator service
- [ ] Update database migration to use enum values
- [ ] Write driver development documentation

### Phase 2: Database Integration (Week 2)
- [ ] Create enhanced PricingService with database-first logic
- [ ] Implement fallback chain (Database → Driver → Universal)
- [ ] Update BudgetEnforcementMiddleware to use PricingService
- [ ] Update CostTrackingListener to use PricingService
- [ ] Add pricing cache management
- [ ] Create unit normalization logic

### Phase 3: Enhanced Sync (Week 3)
- [ ] Update config/ai.php with pricing discovery settings
- [ ] Enhance SyncModelsCommand with pricing capabilities
- [ ] Add command flags for pricing control
- [ ] Implement pricing validation in sync process
- [ ] Add database population from static pricing
- [ ] Create comprehensive command output and warnings

### Phase 4: AI Discovery (Week 4)
- [ ] Create IntelligentPricingDiscovery service
- [ ] Implement Brave Search MCP integration
- [ ] Add AI response parsing for pricing extraction
- [ ] Create provider API pricing fetchers
- [ ] Add cost estimation and confirmation prompts
- [ ] Implement pricing confidence scoring

### Phase 5: Migration & Testing (Week 5)
- [ ] Create migration command for existing systems
- [ ] Write comprehensive test suite
- [ ] Create rollback procedures
- [ ] Update all documentation
- [ ] Performance testing and optimization
- [ ] Production deployment guide

## Success Metrics

- ✅ All pricing queries use database-first approach
- ✅ Budget enforcement accuracy improved by >90%
- ✅ Cost tracking matches actual provider billing
- ✅ Sync commands populate database correctly
- ✅ AI discovery reduces manual pricing updates by >80%
- ✅ System gracefully handles missing components
- ✅ New drivers can be added without central changes

## Documentation Updates Required

1. **docs/PRICING_DISCOVERY.md** - AI-powered pricing discovery guide
2. **docs/DRIVER_DEVELOPMENT.md** - Updated with enum requirements
3. **docs/BUDGET_SYSTEM.md** - Database-first budget calculations
4. **docs/SYNC_COMMANDS.md** - Enhanced sync command documentation
5. **README.md** - Updated setup and configuration instructions

This comprehensive refactor transforms the pricing system from static files to an intelligent, database-driven system while maintaining backward compatibility and driver autonomy.
```
