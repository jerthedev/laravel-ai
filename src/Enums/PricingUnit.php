<?php

namespace JTD\LaravelAI\Enums;

/**
 * Pricing unit enumeration for standardizing cost calculations across AI providers.
 * 
 * This enum provides consistent pricing units and conversion methods to normalize
 * different provider pricing models into a unified system.
 */
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

    /**
     * Get human-readable label for the pricing unit.
     */
    public function label(): string
    {
        return match($this) {
            self::PER_TOKEN => 'Per Token',
            self::PER_1K_TOKENS => 'Per 1,000 Tokens',
            self::PER_1M_TOKENS => 'Per 1,000,000 Tokens',
            self::PER_CHARACTER => 'Per Character',
            self::PER_1K_CHARACTERS => 'Per 1,000 Characters',
            self::PER_SECOND => 'Per Second',
            self::PER_MINUTE => 'Per Minute',
            self::PER_HOUR => 'Per Hour',
            self::PER_REQUEST => 'Per Request',
            self::PER_IMAGE => 'Per Image',
            self::PER_AUDIO_FILE => 'Per Audio File',
            self::PER_MB => 'Per Megabyte',
            self::PER_GB => 'Per Gigabyte',
        };
    }

    /**
     * Get the base unit for normalization purposes.
     * 
     * For example, PER_1K_TOKENS and PER_1M_TOKENS both normalize to PER_TOKEN.
     */
    public function getBaseUnit(): self
    {
        return match($this) {
            self::PER_1K_TOKENS, self::PER_1M_TOKENS => self::PER_TOKEN,
            self::PER_1K_CHARACTERS => self::PER_CHARACTER,
            default => $this,
        };
    }

    /**
     * Get the multiplier to convert from this unit to the base unit.
     * 
     * For example, PER_1K_TOKENS has a multiplier of 1000 to convert to PER_TOKEN.
     */
    public function getMultiplier(): float
    {
        return match($this) {
            self::PER_1K_TOKENS => 1000.0,
            self::PER_1M_TOKENS => 1000000.0,
            self::PER_1K_CHARACTERS => 1000.0,
            self::PER_MINUTE => 60.0, // Convert to seconds
            self::PER_HOUR => 3600.0, // Convert to seconds
            self::PER_GB => 1024.0, // Convert to MB
            default => 1.0,
        };
    }

    /**
     * Convert a cost from this unit to the base unit.
     * 
     * @param float $cost The cost in this unit
     * @return float The cost converted to the base unit
     */
    public function convertToBaseUnit(float $cost): float
    {
        return $cost / $this->getMultiplier();
    }

    /**
     * Convert a cost from the base unit to this unit.
     * 
     * @param float $cost The cost in the base unit
     * @return float The cost converted to this unit
     */
    public function convertFromBaseUnit(float $cost): float
    {
        return $cost * $this->getMultiplier();
    }

    /**
     * Check if this unit is token-based.
     */
    public function isTokenBased(): bool
    {
        return in_array($this, [
            self::PER_TOKEN,
            self::PER_1K_TOKENS,
            self::PER_1M_TOKENS,
        ]);
    }

    /**
     * Check if this unit is character-based.
     */
    public function isCharacterBased(): bool
    {
        return in_array($this, [
            self::PER_CHARACTER,
            self::PER_1K_CHARACTERS,
        ]);
    }

    /**
     * Check if this unit is time-based.
     */
    public function isTimeBased(): bool
    {
        return in_array($this, [
            self::PER_SECOND,
            self::PER_MINUTE,
            self::PER_HOUR,
        ]);
    }

    /**
     * Check if this unit is request-based.
     */
    public function isRequestBased(): bool
    {
        return in_array($this, [
            self::PER_REQUEST,
            self::PER_IMAGE,
            self::PER_AUDIO_FILE,
        ]);
    }

    /**
     * Check if this unit is data-based.
     */
    public function isDataBased(): bool
    {
        return in_array($this, [
            self::PER_MB,
            self::PER_GB,
        ]);
    }

    /**
     * Get all token-based pricing units.
     * 
     * @return array<self>
     */
    public static function getTokenBasedUnits(): array
    {
        return [
            self::PER_TOKEN,
            self::PER_1K_TOKENS,
            self::PER_1M_TOKENS,
        ];
    }

    /**
     * Get all character-based pricing units.
     * 
     * @return array<self>
     */
    public static function getCharacterBasedUnits(): array
    {
        return [
            self::PER_CHARACTER,
            self::PER_1K_CHARACTERS,
        ];
    }

    /**
     * Get all time-based pricing units.
     * 
     * @return array<self>
     */
    public static function getTimeBasedUnits(): array
    {
        return [
            self::PER_SECOND,
            self::PER_MINUTE,
            self::PER_HOUR,
        ];
    }

    /**
     * Get all request-based pricing units.
     * 
     * @return array<self>
     */
    public static function getRequestBasedUnits(): array
    {
        return [
            self::PER_REQUEST,
            self::PER_IMAGE,
            self::PER_AUDIO_FILE,
        ];
    }

    /**
     * Get all data-based pricing units.
     * 
     * @return array<self>
     */
    public static function getDataBasedUnits(): array
    {
        return [
            self::PER_MB,
            self::PER_GB,
        ];
    }

    /**
     * Get all available pricing units grouped by category.
     * 
     * @return array<string, array<self>>
     */
    public static function getAllGrouped(): array
    {
        return [
            'token' => self::getTokenBasedUnits(),
            'character' => self::getCharacterBasedUnits(),
            'time' => self::getTimeBasedUnits(),
            'request' => self::getRequestBasedUnits(),
            'data' => self::getDataBasedUnits(),
        ];
    }
}
