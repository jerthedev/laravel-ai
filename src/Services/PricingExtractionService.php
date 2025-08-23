<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Enums\BillingModel;
use JTD\LaravelAI\Enums\PricingUnit;

/**
 * Service for extracting pricing information from AI search responses.
 *
 * This service uses NLP patterns and regex to extract pricing data from
 * search results, with confidence scoring and validation.
 */
class PricingExtractionService
{
    /**
     * Extract pricing information from search results.
     *
     * @param  array  $searchResults  Array of search results
     * @param  string  $provider  AI provider name
     * @param  string  $model  Model name
     * @return array Extracted pricing data with confidence scores
     */
    public function extractPricing(array $searchResults, string $provider, string $model): array
    {
        $extractedPricing = [];
        $confidenceScores = [];

        foreach ($searchResults as $result) {
            $pricing = $this->extractFromSingleResult($result, $provider, $model);

            if (! empty($pricing)) {
                $extractedPricing[] = $pricing;
                $confidenceScores[] = $pricing['confidence'] ?? 0.0;
            }
        }

        if (empty($extractedPricing)) {
            return [];
        }

        // Consolidate multiple pricing extractions
        return $this->consolidatePricing($extractedPricing, $confidenceScores);
    }

    /**
     * Extract pricing from a single search result.
     */
    private function extractFromSingleResult(array $result, string $provider, string $model): array
    {
        $text = $this->combineResultText($result);
        $pricing = [];

        // Extract token-based pricing
        $tokenPricing = $this->extractTokenPricing($text, $provider, $model);
        if (! empty($tokenPricing)) {
            $pricing = array_merge($pricing, $tokenPricing);
        }

        // Extract request-based pricing
        $requestPricing = $this->extractRequestPricing($text, $provider, $model);
        if (! empty($requestPricing)) {
            $pricing = array_merge($pricing, $requestPricing);
        }

        // Extract image-based pricing
        $imagePricing = $this->extractImagePricing($text, $provider, $model);
        if (! empty($imagePricing)) {
            $pricing = array_merge($pricing, $imagePricing);
        }

        if (empty($pricing)) {
            return [];
        }

        // Add metadata
        $pricing['source_url'] = $result['url'] ?? '';
        $pricing['source_title'] = $result['title'] ?? '';
        $pricing['confidence'] = $this->calculateExtractionConfidence($pricing, $result);
        $pricing['extracted_at'] = now()->toISOString();

        return $pricing;
    }

    /**
     * Extract token-based pricing patterns.
     */
    private function extractTokenPricing(string $text, string $provider, string $model): array
    {
        $pricing = [];

        // Pattern for input/output token pricing
        $patterns = [
            // $0.0025 per 1K input tokens, $0.01 per 1K output tokens
            '/\$?(\d+\.?\d*)\s*(?:per|\/)\s*(\d+[KM]?)\s*(?:input|prompt)\s*tokens?.*?\$?(\d+\.?\d*)\s*(?:per|\/)\s*(\d+[KM]?)\s*(?:output|completion)\s*tokens?/i',
            // Input: $0.0025/1K tokens, Output: $0.01/1K tokens
            '/input:?\s*\$?(\d+\.?\d*)\s*(?:per|\/)\s*(\d+[KM]?)\s*tokens?.*?output:?\s*\$?(\d+\.?\d*)\s*(?:per|\/)\s*(\d+[KM]?)\s*tokens?/i',
            // $0.0025 / 1K input tokens and $0.01 / 1K output tokens
            '/\$?(\d+\.?\d*)\s*\/\s*(\d+[KM]?)\s*input\s*tokens?.*?\$?(\d+\.?\d*)\s*\/\s*(\d+[KM]?)\s*output\s*tokens?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $inputCost = (float) $matches[1];
                $inputUnit = $this->parseUnit($matches[2]);
                $outputCost = (float) $matches[3];
                $outputUnit = $this->parseUnit($matches[4]);

                // Normalize to per-1K-tokens if needed
                if ($inputUnit === $outputUnit) {
                    $pricing['input'] = $inputCost;
                    $pricing['output'] = $outputCost;
                    $pricing['unit'] = $inputUnit;
                    $pricing['currency'] = 'USD';
                    $pricing['billing_model'] = BillingModel::PAY_PER_USE;
                    break;
                }
            }
        }

        // Pattern for single token pricing
        $singlePatterns = [
            // $0.002 per 1K tokens
            '/\$?(\d+\.?\d*)\s*(?:per|\/)\s*(\d+[KM]?)\s*tokens?/i',
        ];

        foreach ($singlePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $cost = (float) $matches[1];
                $unit = $this->parseUnit($matches[2]);

                $pricing['cost'] = $cost;
                $pricing['unit'] = $unit;
                $pricing['currency'] = 'USD';
                $pricing['billing_model'] = BillingModel::PAY_PER_USE;
                break;
            }
        }

        return $pricing;
    }

    /**
     * Extract request-based pricing patterns.
     */
    private function extractRequestPricing(string $text, string $provider, string $model): array
    {
        $pricing = [];

        $patterns = [
            // $0.01 per request
            '/\$?(\d+\.?\d*)\s*(?:per|\/)\s*request/i',
            // $0.01 per API call
            '/\$?(\d+\.?\d*)\s*(?:per|\/)\s*(?:API\s*)?call/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $cost = (float) $matches[1];

                $pricing['cost'] = $cost;
                $pricing['unit'] = PricingUnit::PER_REQUEST;
                $pricing['currency'] = 'USD';
                $pricing['billing_model'] = BillingModel::PAY_PER_USE;
                break;
            }
        }

        return $pricing;
    }

    /**
     * Extract image-based pricing patterns.
     */
    private function extractImagePricing(string $text, string $provider, string $model): array
    {
        $pricing = [];

        $patterns = [
            // $0.04 per image
            '/\$?(\d+\.?\d*)\s*(?:per|\/)\s*image/i',
            // $0.04 per generation
            '/\$?(\d+\.?\d*)\s*(?:per|\/)\s*generation/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $cost = (float) $matches[1];

                $pricing['cost'] = $cost;
                $pricing['unit'] = PricingUnit::PER_IMAGE;
                $pricing['currency'] = 'USD';
                $pricing['billing_model'] = BillingModel::PAY_PER_USE;
                break;
            }
        }

        return $pricing;
    }

    /**
     * Parse unit string to PricingUnit enum.
     */
    private function parseUnit(string $unitStr): PricingUnit
    {
        $unitStr = strtolower(trim($unitStr));

        return match ($unitStr) {
            '1k', '1000' => PricingUnit::PER_1K_TOKENS,
            '1m', '1000000' => PricingUnit::PER_1M_TOKENS,
            default => PricingUnit::PER_1K_TOKENS,
        };
    }

    /**
     * Combine text from search result for analysis.
     */
    private function combineResultText(array $result): string
    {
        $text = '';

        if (! empty($result['title'])) {
            $text .= $result['title'] . ' ';
        }

        if (! empty($result['description'])) {
            $text .= $result['description'] . ' ';
        }

        if (! empty($result['content']) && is_array($result['content'])) {
            $text .= implode(' ', $result['content']) . ' ';
        }

        return $text;
    }

    /**
     * Calculate confidence score for extracted pricing.
     */
    private function calculateExtractionConfidence(array $pricing, array $result): float
    {
        $confidence = 0.3; // Base confidence

        // Boost for official sources
        $url = strtolower($result['url'] ?? '');
        if (str_contains($url, 'docs.') || str_contains($url, 'documentation') || str_contains($url, 'api.')) {
            $confidence += 0.4;
        }

        // Boost for having both input and output pricing
        if (isset($pricing['input']) && isset($pricing['output'])) {
            $confidence += 0.2;
        }

        // Boost for recent content
        if (! empty($result['published'])) {
            // This would need proper date parsing
            $confidence += 0.1;
        }

        // Boost for pricing-specific content
        $title = strtolower($result['title'] ?? '');
        if (str_contains($title, 'pricing') || str_contains($title, 'cost')) {
            $confidence += 0.1;
        }

        return min(1.0, $confidence);
    }

    /**
     * Consolidate multiple pricing extractions into a single result.
     */
    private function consolidatePricing(array $pricingArray, array $confidenceScores): array
    {
        if (empty($pricingArray)) {
            return [];
        }

        // Find the highest confidence pricing
        $maxConfidenceIndex = array_keys($confidenceScores, max($confidenceScores))[0];
        $bestPricing = $pricingArray[$maxConfidenceIndex];

        // Try to fill in missing data from other extractions
        foreach ($pricingArray as $pricing) {
            if (! isset($bestPricing['input']) && isset($pricing['input'])) {
                $bestPricing['input'] = $pricing['input'];
            }
            if (! isset($bestPricing['output']) && isset($pricing['output'])) {
                $bestPricing['output'] = $pricing['output'];
            }
            if (! isset($bestPricing['cost']) && isset($pricing['cost'])) {
                $bestPricing['cost'] = $pricing['cost'];
            }
        }

        // Calculate overall confidence
        $bestPricing['confidence'] = max($confidenceScores);
        $bestPricing['sources_count'] = count($pricingArray);
        $bestPricing['effective_date'] = now()->toDateString();

        return $bestPricing;
    }

    /**
     * Validate extracted pricing data.
     */
    public function validateExtractedPricing(array $pricing): array
    {
        $errors = [];

        // Check for required fields
        if (! isset($pricing['unit'])) {
            $errors[] = 'Missing pricing unit';
        }

        if (! isset($pricing['currency'])) {
            $errors[] = 'Missing currency';
        }

        // Check for pricing values
        $hasInput = isset($pricing['input']) && $pricing['input'] > 0;
        $hasOutput = isset($pricing['output']) && $pricing['output'] > 0;
        $hasCost = isset($pricing['cost']) && $pricing['cost'] > 0;

        if (! $hasInput && ! $hasOutput && ! $hasCost) {
            $errors[] = 'No valid pricing values found';
        }

        // Check for reasonable pricing ranges
        if ($hasInput && $pricing['input'] > 1.0) {
            $errors[] = 'Input pricing seems unusually high';
        }

        if ($hasOutput && $pricing['output'] > 1.0) {
            $errors[] = 'Output pricing seems unusually high';
        }

        return $errors;
    }
}
