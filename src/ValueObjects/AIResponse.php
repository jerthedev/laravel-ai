<?php

namespace JTD\LaravelAI\ValueObjects;

/**
 * Alias for the AIResponse model to maintain backward compatibility
 * with tests that expect it in the ValueObjects namespace.
 */
class_alias(\JTD\LaravelAI\Models\AIResponse::class, __NAMESPACE__ . '\AIResponse');
