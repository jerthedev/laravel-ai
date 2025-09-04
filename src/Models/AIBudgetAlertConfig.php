<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIBudgetAlertConfig extends Model
{
    protected $table = 'ai_budget_alert_configs';

    protected $fillable = [
        'user_id',
        'project_id',
        'organization_id',
        'budget_type',
        'enabled',
        'min_threshold_percentage',
        'email_enabled',
        'email_severities',
        'additional_emails',
        'slack_enabled',
        'slack_severities',
        'slack_webhook',
        'sms_enabled',
        'sms_severities',
        'sms_phone',
        'is_active',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'enabled' => 'boolean',
        'min_threshold_percentage' => 'decimal:2',
        'email_enabled' => 'boolean',
        'email_severities' => 'array',
        'additional_emails' => 'array',
        'slack_enabled' => 'boolean',
        'slack_severities' => 'array',
        'sms_enabled' => 'boolean',
        'sms_severities' => 'array',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AIBudgetAlert::class, 'user_id', 'user_id')
            ->where('budget_type', $this->budget_type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeForOrganization($query, string $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeByBudgetType($query, string $budgetType)
    {
        return $query->where('budget_type', $budgetType);
    }

    public function shouldSendEmail(string $severity): bool
    {
        return $this->email_enabled && 
               is_array($this->email_severities) && 
               in_array($severity, $this->email_severities);
    }

    public function shouldSendSlack(string $severity): bool
    {
        return $this->slack_enabled && 
               !empty($this->slack_webhook) &&
               is_array($this->slack_severities) && 
               in_array($severity, $this->slack_severities);
    }

    public function shouldSendSms(string $severity): bool
    {
        return $this->sms_enabled && 
               !empty($this->sms_phone) &&
               is_array($this->sms_severities) && 
               in_array($severity, $this->sms_severities);
    }

    public function getChannelsForSeverity(string $severity): array
    {
        $channels = [];

        if ($this->shouldSendEmail($severity)) {
            $channels[] = 'email';
        }

        if ($this->shouldSendSlack($severity)) {
            $channels[] = 'slack';
        }

        if ($this->shouldSendSms($severity)) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    public function shouldAlert(float $thresholdPercentage): bool
    {
        return $this->enabled && 
               $this->is_active && 
               $thresholdPercentage >= $this->min_threshold_percentage;
    }

    public function getSeverityForThreshold(float $thresholdPercentage): string
    {
        if ($thresholdPercentage >= 95) {
            return 'critical';
        } elseif ($thresholdPercentage >= 85) {
            return 'high';
        } elseif ($thresholdPercentage >= 75) {
            return 'medium';
        }
        
        return 'low';
    }

    public static function getDefaults(string $budgetType): array
    {
        $defaults = [
            'enabled' => true,
            'email_enabled' => true,
            'email_severities' => ['medium', 'high', 'critical'],
            'slack_enabled' => false,
            'slack_severities' => ['high', 'critical'],
            'sms_enabled' => false,
            'sms_severities' => ['critical'],
            'additional_emails' => [],
            'is_active' => true,
        ];

        $typeDefaults = match ($budgetType) {
            'per_request' => [
                'min_threshold_percentage' => 100.0,
                'email_severities' => ['critical'],
            ],
            'daily' => [
                'min_threshold_percentage' => 75.0,
                'email_severities' => ['medium', 'high', 'critical'],
            ],
            'monthly' => [
                'min_threshold_percentage' => 80.0,
                'email_severities' => ['high', 'critical'],
            ],
            'project' => [
                'min_threshold_percentage' => 85.0,
                'slack_enabled' => true,
            ],
            'organization' => [
                'min_threshold_percentage' => 90.0,
                'slack_enabled' => true,
                'sms_enabled' => true,
            ],
            default => [
                'min_threshold_percentage' => 75.0,
            ],
        };

        return array_merge($defaults, $typeDefaults);
    }

    public static function createForUser(int $userId, string $budgetType, array $overrides = []): self
    {
        return self::create(array_merge(
            self::getDefaults($budgetType),
            [
                'user_id' => $userId,
                'budget_type' => $budgetType,
            ],
            $overrides
        ));
    }

    public static function createForProject(string $projectId, string $budgetType, array $overrides = []): self
    {
        return self::create(array_merge(
            self::getDefaults($budgetType),
            [
                'project_id' => $projectId,
                'budget_type' => $budgetType,
            ],
            $overrides
        ));
    }

    public static function createForOrganization(string $organizationId, string $budgetType, array $overrides = []): self
    {
        return self::create(array_merge(
            self::getDefaults($budgetType),
            [
                'organization_id' => $organizationId,
                'budget_type' => $budgetType,
            ],
            $overrides
        ));
    }
}