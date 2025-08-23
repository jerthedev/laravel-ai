<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Exceptions\UnauthorizedAccessException;
use JTD\LaravelAI\Models\ConversationTemplate;

/**
 * Template Sharing Service
 *
 * Manages template sharing capabilities including permissions, access control,
 * team sharing, public sharing, and collaborative template management.
 */
class TemplateSharingService
{
    protected ConversationTemplateService $templateService;

    public function __construct(ConversationTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Share template with specific users.
     */
    public function shareWithUsers(ConversationTemplate $template, array $userIds, array $permissions = []): array
    {
        $results = ['success' => [], 'failed' => []];
        $defaultPermissions = ['view' => true, 'use' => true, 'edit' => false, 'delete' => false];
        $permissions = array_merge($defaultPermissions, $permissions);

        DB::transaction(function () use ($template, $userIds, $permissions, &$results) {
            foreach ($userIds as $userId) {
                try {
                    // Create or update sharing record
                    $sharing = DB::table('ai_template_shares')->updateOrInsert(
                        [
                            'template_id' => $template->id,
                            'shared_with_id' => $userId,
                            'shared_with_type' => 'user',
                        ],
                        [
                            'permissions' => json_encode($permissions),
                            'shared_by_id' => auth()->id(),
                            'shared_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $results['success'][] = $userId;

                    Log::info('Template shared with user', [
                        'template_id' => $template->id,
                        'template_uuid' => $template->uuid,
                        'user_id' => $userId,
                        'permissions' => $permissions,
                    ]);
                } catch (\Exception $e) {
                    $results['failed'][] = ['user_id' => $userId, 'error' => $e->getMessage()];
                }
            }
        });

        return $results;
    }

    /**
     * Share template with teams/groups.
     */
    public function shareWithTeams(ConversationTemplate $template, array $teamIds, array $permissions = []): array
    {
        $results = ['success' => [], 'failed' => []];
        $defaultPermissions = ['view' => true, 'use' => true, 'edit' => false, 'delete' => false];
        $permissions = array_merge($defaultPermissions, $permissions);

        DB::transaction(function () use ($template, $teamIds, $permissions, &$results) {
            foreach ($teamIds as $teamId) {
                try {
                    DB::table('ai_template_shares')->updateOrInsert(
                        [
                            'template_id' => $template->id,
                            'shared_with_id' => $teamId,
                            'shared_with_type' => 'team',
                        ],
                        [
                            'permissions' => json_encode($permissions),
                            'shared_by_id' => auth()->id(),
                            'shared_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $results['success'][] = $teamId;

                    Log::info('Template shared with team', [
                        'template_id' => $template->id,
                        'template_uuid' => $template->uuid,
                        'team_id' => $teamId,
                        'permissions' => $permissions,
                    ]);
                } catch (\Exception $e) {
                    $results['failed'][] = ['team_id' => $teamId, 'error' => $e->getMessage()];
                }
            }
        });

        return $results;
    }

    /**
     * Make template public.
     */
    public function makePublic(ConversationTemplate $template, array $options = []): bool
    {
        $updateData = [
            'is_public' => true,
            'published_at' => now(),
        ];

        // Add public sharing metadata
        $metadata = $template->metadata ?? [];
        $metadata['public_sharing'] = array_merge([
            'shared_by' => auth()->id(),
            'shared_at' => now()->toISOString(),
            'allow_derivatives' => true,
            'allow_commercial' => false,
        ], $options);

        $updateData['metadata'] = $metadata;

        $success = $template->update($updateData);

        if ($success) {
            Log::info('Template made public', [
                'template_id' => $template->id,
                'template_uuid' => $template->uuid,
                'shared_by' => auth()->id(),
                'options' => $options,
            ]);
        }

        return $success;
    }

    /**
     * Make template private.
     */
    public function makePrivate(ConversationTemplate $template): bool
    {
        $success = $template->update([
            'is_public' => false,
            'published_at' => null,
        ]);

        if ($success) {
            // Remove all sharing records
            DB::table('ai_template_shares')
                ->where('template_id', $template->id)
                ->delete();

            Log::info('Template made private', [
                'template_id' => $template->id,
                'template_uuid' => $template->uuid,
            ]);
        }

        return $success;
    }

    /**
     * Revoke sharing access.
     */
    public function revokeAccess(ConversationTemplate $template, int $sharedWithId, string $sharedWithType): bool
    {
        $deleted = DB::table('ai_template_shares')
            ->where('template_id', $template->id)
            ->where('shared_with_id', $sharedWithId)
            ->where('shared_with_type', $sharedWithType)
            ->delete();

        if ($deleted) {
            Log::info('Template sharing access revoked', [
                'template_id' => $template->id,
                'template_uuid' => $template->uuid,
                'shared_with_id' => $sharedWithId,
                'shared_with_type' => $sharedWithType,
            ]);
        }

        return $deleted > 0;
    }

    /**
     * Get templates shared with user.
     */
    public function getSharedWithUser(int $userId, array $filters = []): Collection
    {
        $query = ConversationTemplate::active()
            ->whereExists(function ($query) use ($userId) {
                $query->select(DB::raw(1))
                    ->from('ai_template_shares')
                    ->whereColumn('ai_template_shares.template_id', 'ai_conversation_templates.id')
                    ->where(function ($q) use ($userId) {
                        $q->where(function ($subQ) use ($userId) {
                            $subQ->where('shared_with_id', $userId)
                                ->where('shared_with_type', 'user');
                        })->orWhere(function ($subQ) use ($userId) {
                            // Also include team shares if user is member of team
                            $subQ->where('shared_with_type', 'team')
                                ->whereExists(function ($teamQuery) use ($userId) {
                                    $teamQuery->select(DB::raw(1))
                                        ->from('team_members') // Assuming team membership table
                                        ->whereColumn('team_members.team_id', 'ai_template_shares.shared_with_id')
                                        ->where('team_members.user_id', $userId);
                                });
                        });
                    });
            });

        // Apply filters
        if (! empty($filters['category'])) {
            $query->inCategory($filters['category']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Get templates shared by user.
     */
    public function getSharedByUser(int $userId): Collection
    {
        return ConversationTemplate::active()
            ->whereExists(function ($query) use ($userId) {
                $query->select(DB::raw(1))
                    ->from('ai_template_shares')
                    ->whereColumn('ai_template_shares.template_id', 'ai_conversation_templates.id')
                    ->where('shared_by_id', $userId);
            })
            ->orWhere(function ($query) use ($userId) {
                $query->where('created_by_id', $userId)
                    ->where('is_public', true);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get sharing details for a template.
     */
    public function getSharingDetails(ConversationTemplate $template): array
    {
        $shares = DB::table('ai_template_shares')
            ->where('template_id', $template->id)
            ->get();

        $userShares = [];
        $teamShares = [];

        foreach ($shares as $share) {
            $shareData = [
                'id' => $share->shared_with_id,
                'permissions' => json_decode($share->permissions, true),
                'shared_by' => $share->shared_by_id,
                'shared_at' => $share->shared_at,
            ];

            if ($share->shared_with_type === 'user') {
                $userShares[] = $shareData;
            } elseif ($share->shared_with_type === 'team') {
                $teamShares[] = $shareData;
            }
        }

        return [
            'is_public' => $template->is_public,
            'published_at' => $template->published_at,
            'user_shares' => $userShares,
            'team_shares' => $teamShares,
            'total_shares' => count($userShares) + count($teamShares),
            'public_metadata' => $template->metadata['public_sharing'] ?? null,
        ];
    }

    /**
     * Check if user has permission to access template.
     */
    public function hasPermission(ConversationTemplate $template, int $userId, string $permission): bool
    {
        // Owner always has all permissions
        if ($template->created_by_id === $userId) {
            return true;
        }

        // Public templates allow view and use
        if ($template->is_public && in_array($permission, ['view', 'use'])) {
            return true;
        }

        // Check direct user sharing
        $userShare = DB::table('ai_template_shares')
            ->where('template_id', $template->id)
            ->where('shared_with_id', $userId)
            ->where('shared_with_type', 'user')
            ->first();

        if ($userShare) {
            $permissions = json_decode($userShare->permissions, true);

            return $permissions[$permission] ?? false;
        }

        // Check team sharing
        $teamShares = DB::table('ai_template_shares')
            ->where('template_id', $template->id)
            ->where('shared_with_type', 'team')
            ->get();

        foreach ($teamShares as $teamShare) {
            // Check if user is member of this team
            $isMember = DB::table('team_members')
                ->where('team_id', $teamShare->shared_with_id)
                ->where('user_id', $userId)
                ->exists();

            if ($isMember) {
                $permissions = json_decode($teamShare->permissions, true);
                if ($permissions[$permission] ?? false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get sharing statistics.
     */
    public function getSharingStatistics(): array
    {
        return [
            'total_public_templates' => ConversationTemplate::public()->count(),
            'total_shared_templates' => ConversationTemplate::whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('ai_template_shares')
                    ->whereColumn('ai_template_shares.template_id', 'ai_conversation_templates.id');
            })->count(),
            'sharing_breakdown' => [
                'user_shares' => DB::table('ai_template_shares')->where('shared_with_type', 'user')->count(),
                'team_shares' => DB::table('ai_template_shares')->where('shared_with_type', 'team')->count(),
            ],
            'most_shared_templates' => $this->getMostSharedTemplates(5),
            'recent_shares' => $this->getRecentShares(10),
        ];
    }

    /**
     * Get most shared templates.
     */
    protected function getMostSharedTemplates(int $limit): Collection
    {
        $templateIds = DB::table('ai_template_shares')
            ->selectRaw('template_id, COUNT(*) as share_count')
            ->groupBy('template_id')
            ->orderByDesc('share_count')
            ->limit($limit)
            ->pluck('template_id');

        return ConversationTemplate::whereIn('id', $templateIds)
            ->orderByDesc('usage_count')
            ->get();
    }

    /**
     * Get recent sharing activity.
     */
    protected function getRecentShares(int $limit): array
    {
        return DB::table('ai_template_shares')
            ->join('ai_conversation_templates', 'ai_template_shares.template_id', '=', 'ai_conversation_templates.id')
            ->select([
                'ai_conversation_templates.uuid',
                'ai_conversation_templates.name',
                'ai_template_shares.shared_with_type',
                'ai_template_shares.shared_at',
                'ai_template_shares.shared_by_id',
            ])
            ->orderByDesc('ai_template_shares.shared_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Create template fork/derivative.
     */
    public function forkTemplate(ConversationTemplate $originalTemplate, array $modifications = []): ConversationTemplate
    {
        // Check if forking is allowed
        if (! $this->hasPermission($originalTemplate, auth()->id(), 'view')) {
            throw new UnauthorizedAccessException('No permission to fork this template');
        }

        $publicMetadata = $originalTemplate->metadata['public_sharing'] ?? [];
        if ($originalTemplate->is_public && ! ($publicMetadata['allow_derivatives'] ?? true)) {
            throw new UnauthorizedAccessException('Template does not allow derivatives');
        } elseif (! $originalTemplate->is_public) {
            throw new UnauthorizedAccessException('Template is not public and cannot be forked');
        }

        // Create fork
        $forkData = array_merge(
            $originalTemplate->only([
                'description', 'category', 'template_data', 'parameters',
                'default_configuration', 'tags', 'language',
            ]),
            $modifications,
            [
                'name' => $modifications['name'] ?? $originalTemplate->name . ' (Fork)',
                'is_public' => false,
                'published_at' => null,
                'usage_count' => 0,
                'avg_rating' => null,
                'created_by_id' => auth()->id(),
                'created_by_type' => auth()->user()::class,
                'metadata' => array_merge(
                    $originalTemplate->metadata ?? [],
                    [
                        'forked_from' => [
                            'template_uuid' => $originalTemplate->uuid,
                            'template_name' => $originalTemplate->name,
                            'forked_at' => now()->toISOString(),
                            'forked_by' => auth()->id(),
                        ],
                    ],
                    $modifications['metadata'] ?? []
                ),
            ]
        );

        $fork = $this->templateService->createTemplate($forkData);

        Log::info('Template forked', [
            'original_template_id' => $originalTemplate->id,
            'original_template_uuid' => $originalTemplate->uuid,
            'fork_template_id' => $fork->id,
            'fork_template_uuid' => $fork->uuid,
            'forked_by' => auth()->id(),
        ]);

        return $fork;
    }

    /**
     * Update sharing permissions.
     */
    public function updatePermissions(ConversationTemplate $template, int $sharedWithId, string $sharedWithType, array $permissions): bool
    {
        $updated = DB::table('ai_template_shares')
            ->where('template_id', $template->id)
            ->where('shared_with_id', $sharedWithId)
            ->where('shared_with_type', $sharedWithType)
            ->update([
                'permissions' => json_encode($permissions),
                'updated_at' => now(),
            ]);

        if ($updated) {
            Log::info('Template sharing permissions updated', [
                'template_id' => $template->id,
                'template_uuid' => $template->uuid,
                'shared_with_id' => $sharedWithId,
                'shared_with_type' => $sharedWithType,
                'permissions' => $permissions,
            ]);
        }

        return $updated > 0;
    }
}
