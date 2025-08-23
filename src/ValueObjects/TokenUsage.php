<?php

namespace JTD\LaravelAI\ValueObjects;

/**
 * Alias for the TokenUsage model to maintain backward compatibility
 * with tests that expect it in the ValueObjects namespace.
 */
class_alias(\JTD\LaravelAI\Models\TokenUsage::class, __NAMESPACE__ . '\TokenUsage');
