<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use JTD\LaravelAI\Enums\BillingModel;
use JTD\LaravelAI\Enums\PricingUnit;
use JTD\LaravelAI\Services\BraveSearchMCPService;
use JTD\LaravelAI\Services\IntelligentPricingDiscovery;
use JTD\LaravelAI\Services\PricingExtractionService;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Services\PricingValidator;
use Tests\TestCase;

class IntelligentPricingDiscoveryTest extends TestCase
{
    protected IntelligentPricingDiscovery $discoveryService;

    protected PricingService $pricingService;

    protected PricingValidator $pricingValidator;

    protected BraveSearchMCPService $braveSearchService;

    protected PricingExtractionService $extractionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingService = $this->createMock(PricingService::class);
        $this->pricingValidator = $this->createMock(PricingValidator::class);
        $this->braveSearchService = $this->createMock(BraveSearchMCPService::class);
        $this->extractionService = $this->createMock(PricingExtractionService::class);

        $this->discoveryService = new IntelligentPricingDiscovery(
            $this->pricingService,
            $this->pricingValidator,
            $this->braveSearchService,
            $this->extractionService
        );
    }

    public function test_discovery_disabled()
    {
        Config::set('ai.model_sync.ai_discovery.enabled', false);

        $result = $this->discoveryService->discoverPricing('openai', 'gpt-4o');

        $this->assertEquals('disabled', $result['status']);
        $this->assertStringContains('disabled', $result['message']);
    }

    public function test_discovery_cost_exceeded()
    {
        Config::set('ai.model_sync.ai_discovery.enabled', true);
        Config::set('ai.model_sync.ai_discovery.max_cost_per_discovery', 0.001);

        $result = $this->discoveryService->discoverPricing('openai', 'gpt-4o');

        $this->assertEquals('cost_exceeded', $result['status']);
        $this->assertArrayHasKey('estimated_cost', $result);
        $this->assertArrayHasKey('max_cost', $result);
    }

    public function test_discovery_requires_confirmation()
    {
        Config::set('ai.model_sync.ai_discovery.enabled', true);
        Config::set('ai.model_sync.ai_discovery.require_confirmation', true);
        Config::set('ai.model_sync.ai_discovery.max_cost_per_discovery', 0.1);

        $result = $this->discoveryService->discoverPricing('openai', 'gpt-4o');

        $this->assertEquals('confirmation_required', $result['status']);
        $this->assertArrayHasKey('confirmation_prompt', $result);
        $this->assertArrayHasKey('estimated_cost', $result);
    }

    public function test_successful_discovery()
    {
        $this->setupSuccessfulDiscovery();

        $searchResults = [
            [
                'title' => 'OpenAI GPT-4o Pricing',
                'url' => 'https://openai.com/pricing',
                'description' => 'GPT-4o costs $0.0025 per 1K input tokens and $0.01 per 1K output tokens',
                'content' => [],
            ],
        ];

        $extractedPricing = [
            'input' => 0.0025,
            'output' => 0.01,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'confidence' => 0.9,
        ];

        $this->braveSearchService
            ->expects($this->atLeastOnce())
            ->method('search')
            ->willReturn([
                'status' => 'success',
                'results' => $searchResults,
                'metadata' => ['api_cost' => 0.001],
            ]);

        $this->extractionService
            ->expects($this->once())
            ->method('extractPricing')
            ->with($searchResults, 'openai', 'gpt-4o')
            ->willReturn($extractedPricing);

        $this->pricingValidator
            ->expects($this->once())
            ->method('validateModelPricing')
            ->willReturn([]);

        $result = $this->discoveryService->discoverPricing('openai', 'gpt-4o', ['confirmed' => true]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('pricing', $result);
        $this->assertArrayHasKey('confidence_score', $result);
        $this->assertEquals('ai_discovery', $result['pricing']['source']);
    }

    public function test_discovery_no_results()
    {
        $this->setupSuccessfulDiscovery();

        $this->braveSearchService
            ->expects($this->atLeastOnce())
            ->method('search')
            ->willReturn([
                'status' => 'error',
                'message' => 'No results found',
            ]);

        $result = $this->discoveryService->discoverPricing('openai', 'gpt-4o', ['confirmed' => true]);

        $this->assertEquals('no_results', $result['status']);
        $this->assertArrayHasKey('queries_tried', $result);
    }

    public function test_discovery_no_pricing_extracted()
    {
        $this->setupSuccessfulDiscovery();

        $this->braveSearchService
            ->expects($this->atLeastOnce())
            ->method('search')
            ->willReturn([
                'status' => 'success',
                'results' => [['title' => 'Some result']],
                'metadata' => ['api_cost' => 0.001],
            ]);

        $this->extractionService
            ->expects($this->once())
            ->method('extractPricing')
            ->willReturn([]);

        $result = $this->discoveryService->discoverPricing('openai', 'gpt-4o', ['confirmed' => true]);

        $this->assertEquals('no_pricing_extracted', $result['status']);
        $this->assertArrayHasKey('results_found', $result);
    }

    public function test_discovery_low_confidence()
    {
        $this->setupSuccessfulDiscovery();
        Config::set('ai.model_sync.ai_discovery.confidence_threshold', 0.9);

        $extractedPricing = [
            'input' => 0.0025,
            'output' => 0.01,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'confidence' => 0.5, // Low confidence
        ];

        $this->braveSearchService
            ->expects($this->atLeastOnce())
            ->method('search')
            ->willReturn([
                'status' => 'success',
                'results' => [['title' => 'Some result']],
                'metadata' => ['api_cost' => 0.001],
            ]);

        $this->extractionService
            ->expects($this->once())
            ->method('extractPricing')
            ->willReturn($extractedPricing);

        $result = $this->discoveryService->discoverPricing('openai', 'gpt-4o', ['confirmed' => true]);

        $this->assertEquals('low_confidence', $result['status']);
        $this->assertArrayHasKey('confidence_score', $result);
        $this->assertArrayHasKey('confidence_threshold', $result);
    }

    public function test_discovery_with_cache()
    {
        $this->setupSuccessfulDiscovery();
        Config::set('ai.model_sync.ai_discovery.cache_discoveries', true);

        $cachedResult = [
            'status' => 'success',
            'pricing' => ['cached' => 'pricing'],
            'confidence_score' => 0.9,
        ];

        Cache::shouldReceive('get')
            ->once()
            ->with('ai_discovery:openai:gpt-4o')
            ->andReturn($cachedResult);

        $result = $this->discoveryService->discoverPricing('openai', 'gpt-4o');

        $this->assertEquals($cachedResult, $result);
    }

    public function test_discovery_caches_successful_result()
    {
        $this->setupSuccessfulDiscovery();
        Config::set('ai.model_sync.ai_discovery.cache_discoveries', true);

        $extractedPricing = [
            'input' => 0.0025,
            'output' => 0.01,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'confidence' => 0.9,
        ];

        $this->braveSearchService
            ->expects($this->atLeastOnce())
            ->method('search')
            ->willReturn([
                'status' => 'success',
                'results' => [['title' => 'Some result']],
                'metadata' => ['api_cost' => 0.001],
            ]);

        $this->extractionService
            ->expects($this->once())
            ->method('extractPricing')
            ->willReturn($extractedPricing);

        $this->pricingValidator
            ->expects($this->once())
            ->method('validateModelPricing')
            ->willReturn([]);

        Cache::shouldReceive('get')
            ->once()
            ->andReturn(null);

        Cache::shouldReceive('put')
            ->once()
            ->with(
                'ai_discovery:openai:gpt-4o',
                $this->callback(function ($result) {
                    return $result['status'] === 'success';
                }),
                86400
            );

        $result = $this->discoveryService->discoverPricing('openai', 'gpt-4o', ['confirmed' => true]);

        $this->assertEquals('success', $result['status']);
    }

    public function test_discovery_fallback_on_failure()
    {
        Config::set('ai.model_sync.ai_discovery.enabled', true);
        Config::set('ai.model_sync.ai_discovery.require_confirmation', false);
        Config::set('ai.model_sync.ai_discovery.fallback_on_failure', true);
        Config::set('ai.model_sync.ai_discovery.max_cost_per_discovery', 0.1);

        $fallbackPricing = [
            'input' => 0.01,
            'output' => 0.02,
            'source' => 'universal_fallback',
        ];

        $this->pricingService
            ->expects($this->once())
            ->method('getModelPricing')
            ->with('openai', 'gpt-4o')
            ->willReturn($fallbackPricing);

        $this->braveSearchService
            ->expects($this->atLeastOnce())
            ->method('search')
            ->willThrowException(new \Exception('Search failed'));

        $result = $this->discoveryService->discoverPricing('openai', 'gpt-4o');

        $this->assertEquals('fallback', $result['status']);
        $this->assertArrayHasKey('pricing', $result);
        $this->assertEquals($fallbackPricing, $result['pricing']);
    }

    protected function setupSuccessfulDiscovery(): void
    {
        Config::set('ai.model_sync.ai_discovery.enabled', true);
        Config::set('ai.model_sync.ai_discovery.require_confirmation', false);
        Config::set('ai.model_sync.ai_discovery.max_cost_per_discovery', 0.1);
        Config::set('ai.model_sync.ai_discovery.confidence_threshold', 0.8);
        Config::set('ai.model_sync.ai_discovery.cache_discoveries', false);
    }
}
