<?php

namespace JTD\LaravelAI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use JTD\LaravelAI\Services\PerformanceAlertManager;

/**
 * Performance Alert Controller
 *
 * Manages performance alerts including acknowledgment, resolution,
 * and statistics for the performance monitoring system.
 */
class PerformanceAlertController extends Controller
{
    /**
     * Performance Alert Manager.
     */
    protected PerformanceAlertManager $alertManager;

    /**
     * Create a new controller instance.
     */
    public function __construct(PerformanceAlertManager $alertManager)
    {
        $this->alertManager = $alertManager;
    }

    /**
     * Get active alerts.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Active alerts
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'severity' => 'nullable|string|in:low,medium,high,critical',
            'component' => 'nullable|string',
            'component_name' => 'nullable|string',
            'limit' => 'integer|min:1|max:100',
            'offset' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $filters = array_filter([
            'severity' => $request->input('severity'),
            'component' => $request->input('component'),
            'component_name' => $request->input('component_name'),
        ]);

        $alerts = $this->alertManager->getActiveAlerts($filters);

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $paginatedAlerts = array_slice($alerts, $offset, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'alerts' => $paginatedAlerts,
                'total' => count($alerts),
                'limit' => $limit,
                'offset' => $offset,
                'filters' => $filters,
            ],
        ]);
    }

    /**
     * Acknowledge an alert.
     *
     * @param  Request  $request  HTTP request
     * @param  int  $alertId  Alert ID
     * @return JsonResponse Acknowledgment result
     */
    public function acknowledge(Request $request, int $alertId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->input('user_id');
        $success = $this->alertManager->acknowledgeAlert($alertId, $userId);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Alert acknowledged successfully',
                'alert_id' => $alertId,
                'acknowledged_by' => $userId,
                'acknowledged_at' => now()->toISOString(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to acknowledge alert',
            'alert_id' => $alertId,
        ], 404);
    }

    /**
     * Resolve an alert.
     *
     * @param  Request  $request  HTTP request
     * @param  int  $alertId  Alert ID
     * @return JsonResponse Resolution result
     */
    public function resolve(Request $request, int $alertId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->input('user_id');
        $resolutionNotes = $request->input('resolution_notes');

        $success = $this->alertManager->resolveAlert($alertId, $userId, $resolutionNotes);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Alert resolved successfully',
                'alert_id' => $alertId,
                'resolved_by' => $userId,
                'resolved_at' => now()->toISOString(),
                'resolution_notes' => $resolutionNotes,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to resolve alert',
            'alert_id' => $alertId,
        ], 404);
    }

    /**
     * Get alert statistics.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Alert statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'string|in:hour,day,week,month',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $timeframe = $request->input('timeframe', 'day');
        $statistics = $this->alertManager->getAlertStatistics($timeframe);

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }

    /**
     * Test alert system.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Test result
     */
    public function test(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'severity' => 'string|in:low,medium,high,critical',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $severity = $request->input('severity', 'medium');
        $testResult = $this->alertManager->testAlertSystem($severity);

        return response()->json([
            'success' => $testResult['success'],
            'data' => $testResult,
        ]);
    }
}
