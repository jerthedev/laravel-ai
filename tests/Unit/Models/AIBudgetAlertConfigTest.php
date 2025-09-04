<?php

namespace JTD\LaravelAI\Tests\Unit\Models;

use JTD\LaravelAI\Models\AIBudgetAlertConfig;
use JTD\LaravelAI\Models\User;
use JTD\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AIBudgetAlertConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_can_create_alert_config_for_user()
    {
        $config = AIBudgetAlertConfig::createForUser($this->user->id, 'monthly');

        $this->assertInstanceOf(AIBudgetAlertConfig::class, $config);
        $this->assertEquals($this->user->id, $config->user_id);
        $this->assertEquals('monthly', $config->budget_type);
        $this->assertTrue($config->enabled);
        $this->assertTrue($config->is_active);
    }

    public function test_can_create_alert_config_for_project()
    {
        $config = AIBudgetAlertConfig::createForProject('project-123', 'daily');

        $this->assertEquals('project-123', $config->project_id);
        $this->assertEquals('daily', $config->budget_type);
    }

    public function test_can_create_alert_config_for_organization()
    {
        $config = AIBudgetAlertConfig::createForOrganization('org-456', 'yearly');

        $this->assertEquals('org-456', $config->organization_id);
        $this->assertEquals('yearly', $config->budget_type);
    }

    public function test_get_defaults_for_different_budget_types()
    {
        $dailyDefaults = AIBudgetAlertConfig::getDefaults('daily');
        $this->assertEquals(75.0, $dailyDefaults['min_threshold_percentage']);

        $monthlyDefaults = AIBudgetAlertConfig::getDefaults('monthly');
        $this->assertEquals(80.0, $monthlyDefaults['min_threshold_percentage']);

        $orgDefaults = AIBudgetAlertConfig::getDefaults('organization');
        $this->assertEquals(90.0, $orgDefaults['min_threshold_percentage']);
        $this->assertTrue($orgDefaults['slack_enabled']);
        $this->assertTrue($orgDefaults['sms_enabled']);
    }

    public function test_should_send_email_by_severity()
    {
        $config = AIBudgetAlertConfig::factory()->create([
            'email_enabled' => true,
            'email_severities' => ['medium', 'high', 'critical'],
        ]);

        $this->assertTrue($config->shouldSendEmail('high'));
        $this->assertTrue($config->shouldSendEmail('critical'));
        $this->assertFalse($config->shouldSendEmail('low'));
    }

    public function test_should_send_slack_by_severity()
    {
        $config = AIBudgetAlertConfig::factory()->create([
            'slack_enabled' => true,
            'slack_webhook' => 'https://hooks.slack.com/test',
            'slack_severities' => ['high', 'critical'],
        ]);

        $this->assertTrue($config->shouldSendSlack('critical'));
        $this->assertFalse($config->shouldSendSlack('medium'));
    }

    public function test_should_send_sms_by_severity()
    {
        $config = AIBudgetAlertConfig::factory()->create([
            'sms_enabled' => true,
            'sms_phone' => '+1234567890',
            'sms_severities' => ['critical'],
        ]);

        $this->assertTrue($config->shouldSendSms('critical'));
        $this->assertFalse($config->shouldSendSms('high'));
    }

    public function test_get_channels_for_severity()
    {
        $config = AIBudgetAlertConfig::factory()->create([
            'email_enabled' => true,
            'email_severities' => ['medium', 'high', 'critical'],
            'slack_enabled' => true,
            'slack_webhook' => 'https://hooks.slack.com/test',
            'slack_severities' => ['high', 'critical'],
            'sms_enabled' => true,
            'sms_phone' => '+1234567890',
            'sms_severities' => ['critical'],
        ]);

        $criticalChannels = $config->getChannelsForSeverity('critical');
        $this->assertContains('email', $criticalChannels);
        $this->assertContains('slack', $criticalChannels);
        $this->assertContains('sms', $criticalChannels);

        $mediumChannels = $config->getChannelsForSeverity('medium');
        $this->assertContains('email', $mediumChannels);
        $this->assertNotContains('slack', $mediumChannels);
        $this->assertNotContains('sms', $mediumChannels);
    }

    public function test_should_alert_based_on_threshold()
    {
        $config = AIBudgetAlertConfig::factory()->create([
            'enabled' => true,
            'is_active' => true,
            'min_threshold_percentage' => 75.0,
        ]);

        $this->assertTrue($config->shouldAlert(80.0));
        $this->assertTrue($config->shouldAlert(75.0));
        $this->assertFalse($config->shouldAlert(70.0));
    }

    public function test_should_not_alert_when_disabled()
    {
        $config = AIBudgetAlertConfig::factory()->create([
            'enabled' => false,
            'is_active' => true,
            'min_threshold_percentage' => 75.0,
        ]);

        $this->assertFalse($config->shouldAlert(80.0));
    }

    public function test_get_severity_for_threshold()
    {
        $config = AIBudgetAlertConfig::factory()->create();

        $this->assertEquals('critical', $config->getSeverityForThreshold(98.0));
        $this->assertEquals('high', $config->getSeverityForThreshold(88.0));
        $this->assertEquals('medium', $config->getSeverityForThreshold(78.0));
        $this->assertEquals('low', $config->getSeverityForThreshold(60.0));
    }

    public function test_scope_for_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        AIBudgetAlertConfig::factory()->create(['user_id' => $user1->id]);
        AIBudgetAlertConfig::factory()->create(['user_id' => $user2->id]);

        $configs = AIBudgetAlertConfig::forUser($user1->id)->get();

        $this->assertCount(1, $configs);
        $this->assertEquals($user1->id, $configs->first()->user_id);
    }

    public function test_scope_for_project()
    {
        AIBudgetAlertConfig::factory()->create(['project_id' => 'project-1']);
        AIBudgetAlertConfig::factory()->create(['project_id' => 'project-2']);

        $configs = AIBudgetAlertConfig::forProject('project-1')->get();

        $this->assertCount(1, $configs);
        $this->assertEquals('project-1', $configs->first()->project_id);
    }

    public function test_scope_by_budget_type()
    {
        AIBudgetAlertConfig::factory()->create(['budget_type' => 'daily']);
        AIBudgetAlertConfig::factory()->create(['budget_type' => 'monthly']);

        $configs = AIBudgetAlertConfig::byBudgetType('monthly')->get();

        $this->assertCount(1, $configs);
        $this->assertEquals('monthly', $configs->first()->budget_type);
    }

    public function test_scope_active()
    {
        AIBudgetAlertConfig::factory()->create(['is_active' => true]);
        AIBudgetAlertConfig::factory()->create(['is_active' => false]);

        $configs = AIBudgetAlertConfig::active()->get();

        $this->assertCount(1, $configs);
        $this->assertTrue($configs->first()->is_active);
    }

    public function test_scope_enabled()
    {
        AIBudgetAlertConfig::factory()->create(['enabled' => true]);
        AIBudgetAlertConfig::factory()->create(['enabled' => false]);

        $configs = AIBudgetAlertConfig::enabled()->get();

        $this->assertCount(1, $configs);
        $this->assertTrue($configs->first()->enabled);
    }

    public function test_user_relationship()
    {
        $config = AIBudgetAlertConfig::factory()->create(['user_id' => $this->user->id]);

        $this->assertInstanceOf(User::class, $config->user);
        $this->assertEquals($this->user->id, $config->user->id);
    }

    public function test_casts_arrays_properly()
    {
        $config = AIBudgetAlertConfig::factory()->create([
            'email_severities' => ['high', 'critical'],
            'slack_severities' => ['critical'],
            'additional_emails' => ['admin@example.com', 'alerts@example.com'],
        ]);

        $this->assertIsArray($config->email_severities);
        $this->assertIsArray($config->slack_severities);
        $this->assertIsArray($config->additional_emails);
        
        $this->assertContains('high', $config->email_severities);
        $this->assertContains('admin@example.com', $config->additional_emails);
    }
}