<?php

namespace JTD\LaravelAI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use JTD\LaravelAI\Http\Requests\CreateBudgetRequest;
use JTD\LaravelAI\Http\Requests\UpdateBudgetRequest;
use JTD\LaravelAI\Services\BudgetService;

/**
 * Budget Controller
 *
 * RESTful API endpoints for budget CRUD operations, status checking,
 * and threshold management with proper validation and authorization.
 */
class BudgetController extends Controller
{
    /**
     * Budget Service.
     */
    protected BudgetService $budgetService;

    /**
     * Create a new controller instance.
     */
    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    /**
     * Get all budgets for the authenticated user.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Budget list
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id ?? $request->input('user_id');
            $projectId = $request->input('project_id');
            $organizationId = $request->input('organization_id');
            $budgetType = $request->input('type');
            $isActive = $request->input('active');

            $budgets = $this->budgetService->getBudgets([
                'user_id' => $userId,
                'project_id' => $projectId,
                'organization_id' => $organizationId,
                'type' => $budgetType,
                'is_active' => $isActive,
            ]);

            return response()->json([
                'success' => true,
                'data' => $budgets,
                'meta' => [
                    'total' => count($budgets),
                    'filters_applied' => array_filter([
                        'user_id' => $userId,
                        'project_id' => $projectId,
                        'organization_id' => $organizationId,
                        'type' => $budgetType,
                        'active' => $isActive,
                    ]),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve budgets',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific budget by ID.
     *
     * @param  Request  $request  HTTP request
     * @param  int  $id  Budget ID
     * @return JsonResponse Budget details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $budget = $this->budgetService->getBudget($id);

            if (! $budget) {
                return response()->json([
                    'success' => false,
                    'error' => 'Budget not found',
                ], 404);
            }

            // Check authorization
            if (! $this->canAccessBudget($request, $budget)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to budget',
                ], 403);
            }

            // Include budget status
            $status = $this->budgetService->getBudgetStatus(
                $budget['user_id'],
                $budget['type'],
                [
                    'project_id' => $budget['project_id'],
                    'organization_id' => $budget['organization_id'],
                ]
            );

            return response()->json([
                'success' => true,
                'data' => array_merge($budget, ['status' => $status]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve budget',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new budget.
     *
     * @param  CreateBudgetRequest  $request  Validated request
     * @return JsonResponse Created budget
     */
    public function store(CreateBudgetRequest $request): JsonResponse
    {
        try {
            $budgetData = $request->validated();

            // Set user ID from authenticated user if not provided
            if (! isset($budgetData['user_id'])) {
                $budgetData['user_id'] = $request->user()->id;
            }

            $budget = $this->budgetService->createBudget($budgetData);

            return response()->json([
                'success' => true,
                'data' => $budget,
                'message' => 'Budget created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create budget',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing budget.
     *
     * @param  UpdateBudgetRequest  $request  Validated request
     * @param  int  $id  Budget ID
     * @return JsonResponse Updated budget
     */
    public function update(UpdateBudgetRequest $request, int $id): JsonResponse
    {
        try {
            $budget = $this->budgetService->getBudget($id);

            if (! $budget) {
                return response()->json([
                    'success' => false,
                    'error' => 'Budget not found',
                ], 404);
            }

            // Check authorization
            if (! $this->canModifyBudget($request, $budget)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized to modify budget',
                ], 403);
            }

            $updateData = $request->validated();
            $updatedBudget = $this->budgetService->updateBudget($id, $updateData);

            return response()->json([
                'success' => true,
                'data' => $updatedBudget,
                'message' => 'Budget updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update budget',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a budget.
     *
     * @param  Request  $request  HTTP request
     * @param  int  $id  Budget ID
     * @return JsonResponse Deletion result
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $budget = $this->budgetService->getBudget($id);

            if (! $budget) {
                return response()->json([
                    'success' => false,
                    'error' => 'Budget not found',
                ], 404);
            }

            // Check authorization
            if (! $this->canModifyBudget($request, $budget)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized to delete budget',
                ], 403);
            }

            $deleted = $this->budgetService->deleteBudget($id);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Budget deleted successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete budget',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete budget',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get budget status for multiple budget types.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Budget status
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id ?? $request->input('user_id');
            $projectId = $request->input('project_id');
            $organizationId = $request->input('organization_id');
            $budgetTypes = $request->input('types', ['daily', 'monthly', 'per_request']);

            if (is_string($budgetTypes)) {
                $budgetTypes = explode(',', $budgetTypes);
            }

            $status = [];
            foreach ($budgetTypes as $type) {
                $status[$type] = $this->budgetService->getBudgetStatus($userId, $type, [
                    'project_id' => $projectId,
                    'organization_id' => $organizationId,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $status,
                'meta' => [
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'organization_id' => $organizationId,
                    'types_checked' => $budgetTypes,
                    'checked_at' => now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get budget status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update budget thresholds.
     *
     * @param  Request  $request  HTTP request
     * @param  int  $id  Budget ID
     * @return JsonResponse Updated thresholds
     */
    public function updateThresholds(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'warning_threshold' => 'nullable|numeric|min:0|max:100',
            'critical_threshold' => 'nullable|numeric|min:0|max:100',
            'alert_enabled' => 'boolean',
            'alert_channels' => 'array',
            'alert_channels.*' => 'string|in:email,slack,sms,database',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $budget = $this->budgetService->getBudget($id);

            if (! $budget) {
                return response()->json([
                    'success' => false,
                    'error' => 'Budget not found',
                ], 404);
            }

            // Check authorization
            if (! $this->canModifyBudget($request, $budget)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized to modify budget thresholds',
                ], 403);
            }

            $thresholdData = $validator->validated();
            $updatedBudget = $this->budgetService->updateBudgetThresholds($id, $thresholdData);

            return response()->json([
                'success' => true,
                'data' => $updatedBudget,
                'message' => 'Budget thresholds updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update budget thresholds',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset budget spending (for testing or administrative purposes).
     *
     * @param  Request  $request  HTTP request
     * @param  int  $id  Budget ID
     * @return JsonResponse Reset result
     */
    public function reset(Request $request, int $id): JsonResponse
    {
        try {
            $budget = $this->budgetService->getBudget($id);

            if (! $budget) {
                return response()->json([
                    'success' => false,
                    'error' => 'Budget not found',
                ], 404);
            }

            // Check authorization (only admins or budget owners)
            if (! $this->canResetBudget($request, $budget)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized to reset budget',
                ], 403);
            }

            $resetResult = $this->budgetService->resetBudget($id);

            return response()->json([
                'success' => true,
                'data' => $resetResult,
                'message' => 'Budget reset successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to reset budget',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get budget usage history.
     *
     * @param  Request  $request  HTTP request
     * @param  int  $id  Budget ID
     * @return JsonResponse Usage history
     */
    public function usage(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'integer|min:1|max:1000',
            'page' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $budget = $this->budgetService->getBudget($id);

            if (! $budget) {
                return response()->json([
                    'success' => false,
                    'error' => 'Budget not found',
                ], 404);
            }

            // Check authorization
            if (! $this->canAccessBudget($request, $budget)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to budget usage',
                ], 403);
            }

            $filters = $validator->validated();
            $usage = $this->budgetService->getBudgetUsage($id, $filters);

            return response()->json([
                'success' => true,
                'data' => $usage,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get budget usage',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if user can access budget.
     *
     * @param  Request  $request  HTTP request
     * @param  array  $budget  Budget data
     * @return bool Can access
     */
    protected function canAccessBudget(Request $request, array $budget): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // User can access their own budgets
        if ($budget['user_id'] === $user->id) {
            return true;
        }

        // Check project access
        if ($budget['project_id'] && $this->hasProjectAccess($user, $budget['project_id'])) {
            return true;
        }

        // Check organization access
        if ($budget['organization_id'] && $this->hasOrganizationAccess($user, $budget['organization_id'])) {
            return true;
        }

        // Check admin access
        return $this->isAdmin($user);
    }

    /**
     * Check if user can modify budget.
     *
     * @param  Request  $request  HTTP request
     * @param  array  $budget  Budget data
     * @return bool Can modify
     */
    protected function canModifyBudget(Request $request, array $budget): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // User can modify their own budgets
        if ($budget['user_id'] === $user->id) {
            return true;
        }

        // Check admin access
        return $this->isAdmin($user);
    }

    /**
     * Check if user can reset budget.
     *
     * @param  Request  $request  HTTP request
     * @param  array  $budget  Budget data
     * @return bool Can reset
     */
    protected function canResetBudget(Request $request, array $budget): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // Only admins can reset budgets
        return $this->isAdmin($user);
    }

    /**
     * Check if user has project access.
     *
     * @param  mixed  $user  User model
     * @param  string  $projectId  Project ID
     * @return bool Has access
     */
    protected function hasProjectAccess($user, string $projectId): bool
    {
        // This would typically check project membership
        // Implementation depends on your project access control system
        return false;
    }

    /**
     * Check if user has organization access.
     *
     * @param  mixed  $user  User model
     * @param  string  $organizationId  Organization ID
     * @return bool Has access
     */
    protected function hasOrganizationAccess($user, string $organizationId): bool
    {
        // This would typically check organization membership
        // Implementation depends on your organization access control system
        return false;
    }

    /**
     * Check if user is admin.
     *
     * @param  mixed  $user  User model
     * @return bool Is admin
     */
    protected function isAdmin($user): bool
    {
        // This would typically check user roles/permissions
        // Implementation depends on your authorization system
        return $user->is_admin ?? false;
    }
}
