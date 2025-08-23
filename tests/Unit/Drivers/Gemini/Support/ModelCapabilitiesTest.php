<?php

namespace JTD\LaravelAI\Tests\Unit\Drivers\Gemini\Support;

use JTD\LaravelAI\Drivers\Gemini\Support\ModelCapabilities;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * ModelCapabilities Unit Tests
 *
 * Tests the Gemini model capabilities functionality.
 */
#[Group('unit')]
#[Group('gemini')]
#[Group('support')]
class ModelCapabilitiesTest extends TestCase
{
    #[Test]
    public function it_returns_correct_context_lengths(): void
    {
        $this->assertEquals(30720, ModelCapabilities::getContextLength('gemini-pro'));
        $this->assertEquals(12288, ModelCapabilities::getContextLength('gemini-pro-vision'));
        $this->assertEquals(1048576, ModelCapabilities::getContextLength('gemini-1.5-pro'));
        $this->assertEquals(1048576, ModelCapabilities::getContextLength('gemini-1.5-flash'));
    }

    #[Test]
    public function it_returns_default_context_length_for_unknown_model(): void
    {
        $contextLength = ModelCapabilities::getContextLength('unknown-model');
        $this->assertEquals(30720, $contextLength);
    }

    #[Test]
    public function it_returns_correct_display_names(): void
    {
        $this->assertEquals('Gemini Pro', ModelCapabilities::getDisplayName('gemini-pro'));
        $this->assertEquals('Gemini Pro Vision', ModelCapabilities::getDisplayName('gemini-pro-vision'));
        $this->assertEquals('Gemini 1.5 Pro', ModelCapabilities::getDisplayName('gemini-1.5-pro'));
        $this->assertEquals('Gemini 1.5 Flash', ModelCapabilities::getDisplayName('gemini-1.5-flash'));
    }

    #[Test]
    public function it_returns_formatted_name_for_unknown_model(): void
    {
        $displayName = ModelCapabilities::getDisplayName('custom-model-name');
        $this->assertEquals('Custom model name', $displayName);
    }

    #[Test]
    public function it_returns_correct_descriptions(): void
    {
        $description = ModelCapabilities::getDescription('gemini-pro');
        $this->assertStringContainsString('scaling across a wide range of tasks', $description);

        $description = ModelCapabilities::getDescription('gemini-pro-vision');
        $this->assertStringContainsString('multimodal tasks including images', $description);
    }

    #[Test]
    public function it_returns_default_description_for_unknown_model(): void
    {
        $description = ModelCapabilities::getDescription('unknown-model');
        $this->assertEquals('Google Gemini model', $description);
    }

    #[Test]
    #[DataProvider('visionModelProvider')]
    public function it_correctly_identifies_vision_models(string $modelId, bool $expected): void
    {
        $this->assertEquals($expected, ModelCapabilities::supportsVision($modelId));
    }

    public static function visionModelProvider(): array
    {
        return [
            ['gemini-pro', false],
            ['gemini-pro-vision', true],
            ['gemini-1.5-pro', true],
            ['gemini-1.5-flash', true],
            ['gemini-1.0-pro', false],
            ['gemini-1.0-pro-vision', true],
            ['custom-vision-model', true], // Contains 'vision'
        ];
    }

    #[Test]
    public function it_returns_correct_model_capabilities(): void
    {
        $capabilities = ModelCapabilities::getModelCapabilities('gemini-pro');
        $this->assertContains('chat', $capabilities);
        $this->assertContains('text_generation', $capabilities);
        $this->assertContains('safety_settings', $capabilities);
        $this->assertNotContains('vision', $capabilities);

        $visionCapabilities = ModelCapabilities::getModelCapabilities('gemini-pro-vision');
        $this->assertContains('vision', $visionCapabilities);
        $this->assertContains('multimodal', $visionCapabilities);
    }

    #[Test]
    #[DataProvider('jsonModeModelProvider')]
    public function it_correctly_identifies_json_mode_support(string $modelId, bool $expected): void
    {
        $this->assertEquals($expected, ModelCapabilities::supportsJsonMode($modelId));
    }

    public static function jsonModeModelProvider(): array
    {
        return [
            ['gemini-pro', false],
            ['gemini-1.5-pro', true],
            ['gemini-1.5-flash', true],
            ['gemini-1.5-pro-exp-0801', true],
            ['gemini-pro-vision', false],
        ];
    }

    #[Test]
    public function it_identifies_all_models_support_code_generation(): void
    {
        $models = ['gemini-pro', 'gemini-pro-vision', 'gemini-1.5-pro', 'gemini-1.5-flash'];

        foreach ($models as $model) {
            $this->assertTrue(ModelCapabilities::supportsCodeGeneration($model));
        }
    }

    #[Test]
    public function it_identifies_all_models_support_safety_settings(): void
    {
        $models = ['gemini-pro', 'gemini-pro-vision', 'gemini-1.5-pro', 'gemini-1.5-flash'];

        foreach ($models as $model) {
            $this->assertTrue(ModelCapabilities::supportsSafetySettings($model));
        }
    }

    #[Test]
    public function it_identifies_no_models_support_streaming_yet(): void
    {
        $models = ['gemini-pro', 'gemini-pro-vision', 'gemini-1.5-pro', 'gemini-1.5-flash'];

        foreach ($models as $model) {
            $this->assertFalse(ModelCapabilities::supportsStreaming($model));
        }
    }

    #[Test]
    public function it_identifies_no_models_support_function_calling_yet(): void
    {
        $models = ['gemini-pro', 'gemini-pro-vision', 'gemini-1.5-pro', 'gemini-1.5-flash'];

        foreach ($models as $model) {
            $this->assertFalse(ModelCapabilities::supportsFunctionCalling($model));
        }
    }

    #[Test]
    public function it_returns_all_supported_models(): void
    {
        $models = ModelCapabilities::getAllModels();

        $this->assertIsArray($models);
        $this->assertContains('gemini-pro', $models);
        $this->assertContains('gemini-pro-vision', $models);
        $this->assertContains('gemini-1.5-pro', $models);
        $this->assertContains('gemini-1.5-flash', $models);
    }

    #[Test]
    public function it_returns_vision_models(): void
    {
        $visionModels = ModelCapabilities::getVisionModels();

        $this->assertIsArray($visionModels);
        $this->assertContains('gemini-pro-vision', $visionModels);
        $this->assertContains('gemini-1.5-pro', $visionModels);
        $this->assertNotContains('gemini-pro', $visionModels);
    }

    #[Test]
    public function it_returns_large_context_models(): void
    {
        $largeContextModels = ModelCapabilities::getLargeContextModels();

        $this->assertIsArray($largeContextModels);
        $this->assertContains('gemini-1.5-pro', $largeContextModels);
        $this->assertContains('gemini-1.5-flash', $largeContextModels);
        $this->assertNotContains('gemini-pro', $largeContextModels);
    }

    #[Test]
    #[DataProvider('taskModelProvider')]
    public function it_returns_best_model_for_task(string $task, string $expectedModel): void
    {
        $bestModel = ModelCapabilities::getBestModelForTask($task);
        $this->assertEquals($expectedModel, $bestModel);
    }

    public static function taskModelProvider(): array
    {
        return [
            ['vision', 'gemini-1.5-pro'],
            ['multimodal', 'gemini-1.5-pro'],
            ['speed', 'gemini-1.5-flash'],
            ['fast', 'gemini-1.5-flash'],
            ['large_context', 'gemini-1.5-pro'],
            ['code', 'gemini-1.5-pro'],
            ['general', 'gemini-pro'],
            ['unknown_task', 'gemini-pro'],
        ];
    }

    #[Test]
    public function it_returns_model_family_information(): void
    {
        $family15 = ModelCapabilities::getModelFamily('gemini-1.5-pro');
        $this->assertEquals('gemini-1.5', $family15['family']);
        $this->assertEquals('1.5', $family15['generation']);
        $this->assertContains('large_context', $family15['features']);

        $family10 = ModelCapabilities::getModelFamily('gemini-pro');
        $this->assertEquals('gemini-1.0', $family10['family']);
        $this->assertEquals('1.0', $family10['generation']);
    }

    #[Test]
    public function it_identifies_experimental_models(): void
    {
        $this->assertTrue(ModelCapabilities::isExperimental('gemini-1.5-pro-exp-0801'));
        $this->assertTrue(ModelCapabilities::isExperimental('gemini-1.5-flash-exp-0827'));
        $this->assertFalse(ModelCapabilities::isExperimental('gemini-pro'));
        $this->assertFalse(ModelCapabilities::isExperimental('gemini-1.5-pro'));
    }

    #[Test]
    public function it_identifies_deprecated_models(): void
    {
        // Currently no deprecated models, but test the functionality
        $this->assertFalse(ModelCapabilities::isDeprecated('gemini-pro'));
        $this->assertFalse(ModelCapabilities::isDeprecated('gemini-1.5-pro'));
    }

    #[Test]
    public function it_returns_performance_profiles(): void
    {
        $profile = ModelCapabilities::getPerformanceProfile('gemini-1.5-flash');
        $this->assertEquals('very_high', $profile['speed']);
        $this->assertEquals('high', $profile['quality']);

        $profile = ModelCapabilities::getPerformanceProfile('gemini-1.5-pro');
        $this->assertEquals('very_high', $profile['quality']);
        $this->assertEquals('very_high', $profile['context_efficiency']);
    }

    #[Test]
    public function it_returns_safety_categories(): void
    {
        $categories = ModelCapabilities::getSafetyCategories('gemini-pro');

        $this->assertIsArray($categories);
        $this->assertContains('HARM_CATEGORY_HARASSMENT', $categories);
        $this->assertContains('HARM_CATEGORY_HATE_SPEECH', $categories);
        $this->assertContains('HARM_CATEGORY_SEXUALLY_EXPLICIT', $categories);
        $this->assertContains('HARM_CATEGORY_DANGEROUS_CONTENT', $categories);
    }

    #[Test]
    public function it_returns_supported_image_types_for_vision_models(): void
    {
        $imageTypes = ModelCapabilities::getSupportedImageTypes('gemini-pro-vision');

        $this->assertIsArray($imageTypes);
        $this->assertContains('image/png', $imageTypes);
        $this->assertContains('image/jpeg', $imageTypes);
        $this->assertContains('image/webp', $imageTypes);

        // Non-vision model should return empty array
        $nonVisionTypes = ModelCapabilities::getSupportedImageTypes('gemini-pro');
        $this->assertEmpty($nonVisionTypes);
    }
}
