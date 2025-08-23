<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Models\ConversationTemplate;
use JTD\LaravelAI\Models\User;
use JTD\LaravelAI\Services\ConversationTemplateService;
use JTD\LaravelAI\Services\TemplateSharingService;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class TemplateSharingServiceTest extends TestCase
{
    protected TemplateSharingService $service;

    protected $mockTemplateService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockTemplateService = Mockery::mock(ConversationTemplateService::class);
        $this->service = new TemplateSharingService($this->mockTemplateService);
    }

    #[Test]
    public function it_shares_template_with_users(): void
    {
        $template = ConversationTemplate::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs(User::factory()->create());

        $permissions = ['view' => true, 'use' => true, 'edit' => false];
        $result = $this->service->shareWithUsers($template, [$user1->id, $user2->id], $permissions);

        $this->assertEquals(2, count($result['success']));
        $this->assertEmpty($result['failed']);
        $this->assertContains($user1->id, $result['success']);
        $this->assertContains($user2->id, $result['success']);

        // Verify database records
        $this->assertDatabaseHas('ai_template_shares', [
            'template_id' => $template->id,
            'shared_with_id' => $user1->id,
            'shared_with_type' => 'user',
        ]);

        $this->assertDatabaseHas('ai_template_shares', [
            'template_id' => $template->id,
            'shared_with_id' => $user2->id,
            'shared_with_type' => 'user',
        ]);
    }

    #[Test]
    public function it_shares_template_with_teams(): void
    {
        $template = ConversationTemplate::factory()->create();
        $teamIds = [1, 2];

        $this->actingAs(User::factory()->create());

        $permissions = ['view' => true, 'use' => true];
        $result = $this->service->shareWithTeams($template, $teamIds, $permissions);

        $this->assertEquals(2, count($result['success']));
        $this->assertEmpty($result['failed']);

        // Verify database records
        $this->assertDatabaseHas('ai_template_shares', [
            'template_id' => $template->id,
            'shared_with_id' => 1,
            'shared_with_type' => 'team',
        ]);
    }

    #[Test]
    public function it_makes_template_public(): void
    {
        $template = ConversationTemplate::factory()->create(['is_public' => false]);
        $this->actingAs(User::factory()->create());

        $options = ['allow_derivatives' => true, 'allow_commercial' => false];
        $result = $this->service->makePublic($template, $options);

        $this->assertTrue($result);

        $template->refresh();
        $this->assertTrue($template->is_public);
        $this->assertNotNull($template->published_at);
        $this->assertArrayHasKey('public_sharing', $template->metadata);
        $this->assertEquals(true, $template->metadata['public_sharing']['allow_derivatives']);
        $this->assertEquals(false, $template->metadata['public_sharing']['allow_commercial']);
    }

    #[Test]
    public function it_makes_template_private(): void
    {
        $template = ConversationTemplate::factory()->create(['is_public' => true]);
        $user = User::factory()->create();

        // Create some sharing records
        DB::table('ai_template_shares')->insert([
            'template_id' => $template->id,
            'shared_with_id' => $user->id,
            'shared_with_type' => 'user',
            'permissions' => json_encode(['view' => true]),
            'shared_by_id' => 1,
            'shared_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->makePrivate($template);

        $this->assertTrue($result);

        $template->refresh();
        $this->assertFalse($template->is_public);
        $this->assertNull($template->published_at);

        // Verify sharing records are removed
        $this->assertDatabaseMissing('ai_template_shares', [
            'template_id' => $template->id,
        ]);
    }

    #[Test]
    public function it_revokes_access(): void
    {
        $template = ConversationTemplate::factory()->create();
        $user = User::factory()->create();

        // Create sharing record
        DB::table('ai_template_shares')->insert([
            'template_id' => $template->id,
            'shared_with_id' => $user->id,
            'shared_with_type' => 'user',
            'permissions' => json_encode(['view' => true]),
            'shared_by_id' => 1,
            'shared_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->revokeAccess($template, $user->id, 'user');

        $this->assertTrue($result);
        $this->assertDatabaseMissing('ai_template_shares', [
            'template_id' => $template->id,
            'shared_with_id' => $user->id,
            'shared_with_type' => 'user',
        ]);
    }

    #[Test]
    public function it_gets_sharing_details(): void
    {
        $template = ConversationTemplate::factory()->create(['is_public' => true]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create sharing records
        DB::table('ai_template_shares')->insert([
            [
                'template_id' => $template->id,
                'shared_with_id' => $user1->id,
                'shared_with_type' => 'user',
                'permissions' => json_encode(['view' => true, 'use' => true]),
                'shared_by_id' => 1,
                'shared_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'template_id' => $template->id,
                'shared_with_id' => 1,
                'shared_with_type' => 'team',
                'permissions' => json_encode(['view' => true]),
                'shared_by_id' => 1,
                'shared_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $details = $this->service->getSharingDetails($template);

        $this->assertTrue($details['is_public']);
        $this->assertEquals(1, count($details['user_shares']));
        $this->assertEquals(1, count($details['team_shares']));
        $this->assertEquals(2, $details['total_shares']);
        $this->assertEquals($user1->id, $details['user_shares'][0]['id']);
        $this->assertEquals(['view' => true, 'use' => true], $details['user_shares'][0]['permissions']);
    }

    #[Test]
    public function it_checks_owner_permissions(): void
    {
        $owner = User::factory()->create();
        $template = ConversationTemplate::factory()->create(['created_by_id' => $owner->id]);

        // Owner should have all permissions
        $this->assertTrue($this->service->hasPermission($template, $owner->id, 'view'));
        $this->assertTrue($this->service->hasPermission($template, $owner->id, 'use'));
        $this->assertTrue($this->service->hasPermission($template, $owner->id, 'edit'));
        $this->assertTrue($this->service->hasPermission($template, $owner->id, 'delete'));
    }

    #[Test]
    public function it_checks_public_template_permissions(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $template = ConversationTemplate::factory()->create([
            'is_public' => true,
            'created_by_id' => $owner->id, // Ensure user is not the owner
        ]);

        // Public templates allow view and use
        $this->assertTrue($this->service->hasPermission($template, $user->id, 'view'));
        $this->assertTrue($this->service->hasPermission($template, $user->id, 'use'));
        $this->assertFalse($this->service->hasPermission($template, $user->id, 'edit'));
        $this->assertFalse($this->service->hasPermission($template, $user->id, 'delete'));
    }

    #[Test]
    public function it_checks_shared_user_permissions(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $template = ConversationTemplate::factory()->create([
            'is_public' => false,
            'created_by_id' => $owner->id, // Ensure user is not the owner
        ]);

        // Create sharing record with specific permissions
        DB::table('ai_template_shares')->insert([
            'template_id' => $template->id,
            'shared_with_id' => $user->id,
            'shared_with_type' => 'user',
            'permissions' => json_encode(['view' => true, 'use' => true, 'edit' => false]),
            'shared_by_id' => $owner->id,
            'shared_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->service->hasPermission($template, $user->id, 'view'));
        $this->assertTrue($this->service->hasPermission($template, $user->id, 'use'));
        $this->assertFalse($this->service->hasPermission($template, $user->id, 'edit'));
    }

    #[Test]
    public function it_forks_template_successfully(): void
    {
        $originalTemplate = ConversationTemplate::factory()->create([
            'name' => 'Original Template',
            'is_public' => true,
            'metadata' => ['public_sharing' => ['allow_derivatives' => true]],
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->mockTemplateService
            ->shouldReceive('createTemplate')
            ->once()
            ->andReturn(ConversationTemplate::factory()->create([
                'name' => 'Original Template (Fork)',
                'created_by_id' => $user->id,
            ]));

        $modifications = ['name' => 'My Custom Fork'];
        $fork = $this->service->forkTemplate($originalTemplate, $modifications);

        $this->assertInstanceOf(ConversationTemplate::class, $fork);
        $this->assertEquals($user->id, $fork->created_by_id);
    }

    #[Test]
    public function it_prevents_forking_when_not_allowed(): void
    {
        $originalTemplate = ConversationTemplate::factory()->create([
            'is_public' => true,
            'metadata' => ['public_sharing' => ['allow_derivatives' => false]],
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->expectException(\JTD\LaravelAI\Exceptions\UnauthorizedAccessException::class);
        $this->expectExceptionMessage('Template does not allow derivatives');

        $this->service->forkTemplate($originalTemplate);
    }

    #[Test]
    public function it_updates_sharing_permissions(): void
    {
        $template = ConversationTemplate::factory()->create();
        $user = User::factory()->create();

        // Create initial sharing record
        DB::table('ai_template_shares')->insert([
            'template_id' => $template->id,
            'shared_with_id' => $user->id,
            'shared_with_type' => 'user',
            'permissions' => json_encode(['view' => true, 'use' => false]),
            'shared_by_id' => 1,
            'shared_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newPermissions = ['view' => true, 'use' => true, 'edit' => true];
        $result = $this->service->updatePermissions($template, $user->id, 'user', $newPermissions);

        $this->assertTrue($result);

        // Verify permissions were updated
        $share = DB::table('ai_template_shares')
            ->where('template_id', $template->id)
            ->where('shared_with_id', $user->id)
            ->first();

        $this->assertEquals($newPermissions, json_decode($share->permissions, true));
    }

    #[Test]
    public function it_gets_sharing_statistics(): void
    {
        // Create test data
        $template1 = ConversationTemplate::factory()->create(['is_public' => true]);
        $template2 = ConversationTemplate::factory()->create(['is_public' => false]);
        $user = User::factory()->create();

        DB::table('ai_template_shares')->insert([
            [
                'template_id' => $template2->id,
                'shared_with_id' => $user->id,
                'shared_with_type' => 'user',
                'permissions' => json_encode(['view' => true]),
                'shared_by_id' => 1,
                'shared_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'template_id' => $template2->id,
                'shared_with_id' => 1,
                'shared_with_type' => 'team',
                'permissions' => json_encode(['view' => true]),
                'shared_by_id' => 1,
                'shared_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $stats = $this->service->getSharingStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_public_templates', $stats);
        $this->assertArrayHasKey('total_shared_templates', $stats);
        $this->assertArrayHasKey('sharing_breakdown', $stats);
        $this->assertEquals(1, $stats['total_public_templates']);
        $this->assertEquals(1, $stats['total_shared_templates']);
        $this->assertEquals(1, $stats['sharing_breakdown']['user_shares']);
        $this->assertEquals(1, $stats['sharing_breakdown']['team_shares']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
