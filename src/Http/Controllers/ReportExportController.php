<?php

namespace JTD\LaravelAI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use JTD\LaravelAI\Services\ReportExportService;

/**
 * Report Export Controller
 *
 * Handles report export requests, file downloads, and export management
 * with support for multiple formats and automated scheduling.
 */
class ReportExportController extends Controller
{
    /**
     * Report Export Service.
     */
    protected ReportExportService $reportExportService;

    /**
     * Create a new controller instance.
     */
    public function __construct(ReportExportService $reportExportService)
    {
        $this->reportExportService = $reportExportService;
    }

    /**
     * Export analytics report.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Export result
     */
    public function exportReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:pdf,csv,json,xlsx',
            'report_type' => 'string|in:comprehensive,cost_breakdown,usage_trends,budget_analysis',
            'date_range' => 'string|in:week,month,quarter,year,custom',
            'start_date' => 'nullable|date|required_if:date_range,custom',
            'end_date' => 'nullable|date|after_or_equal:start_date|required_if:date_range,custom',
            'providers' => 'array',
            'providers.*' => 'string|max:50',
            'models' => 'array',
            'models.*' => 'string|max:100',
            'include_forecasting' => 'boolean',
            'include_trends' => 'boolean',
            'include_comparisons' => 'boolean',
            'include_recommendations' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $options = array_merge($validator->validated(), [
                'user_id' => $request->user()->id,
            ]);

            $result = $this->reportExportService->exportAnalyticsReport($options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result,
                    'message' => 'Report exported successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message'],
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Export failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export cost breakdown report.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Export result
     */
    public function exportCostBreakdown(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:pdf,csv,json,xlsx',
            'date_range' => 'string|in:week,month,quarter,year',
            'providers' => 'array',
            'providers.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = $request->user()->id;
            $format = $request->input('format');
            $filters = $validator->validated();
            unset($filters['format']);

            $result = $this->reportExportService->exportCostBreakdown($userId, $format, $filters);

            return response()->json([
                'success' => $result['success'],
                'data' => $result['success'] ? $result : null,
                'error' => $result['success'] ? null : $result['error'],
                'message' => $result['success'] ? 'Cost breakdown exported successfully' : $result['message'],
            ], $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Export failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export usage trends report.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Export result
     */
    public function exportUsageTrends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:pdf,csv,json,xlsx',
            'date_range' => 'string|in:week,month,quarter,year',
            'include_forecasting' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = $request->user()->id;
            $format = $request->input('format');
            $filters = $validator->validated();
            unset($filters['format']);

            $result = $this->reportExportService->exportUsageTrends($userId, $format, $filters);

            return response()->json([
                'success' => $result['success'],
                'data' => $result['success'] ? $result : null,
                'error' => $result['success'] ? null : $result['error'],
                'message' => $result['success'] ? 'Usage trends exported successfully' : $result['message'],
            ], $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Export failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download exported report file.
     *
     * @param  Request  $request  HTTP request
     * @param  string  $fileName  File name
     * @return Response File download response
     */
    public function downloadReport(Request $request, string $fileName): Response
    {
        try {
            $filePath = "exports/{$fileName}";

            if (! Storage::disk('local')->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File not found',
                ], 404);
            }

            // Verify user has access to this file
            if (! $this->canAccessFile($request, $fileName)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access',
                ], 403);
            }

            $fileContent = Storage::disk('local')->get($filePath);
            $mimeType = $this->getMimeType($fileName);

            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Download failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Schedule automated report generation.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Schedule result
     */
    public function scheduleReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|string|in:comprehensive,cost_breakdown,usage_trends,budget_analysis',
            'format' => 'required|string|in:pdf,csv,json,xlsx',
            'frequency' => 'required|string|in:daily,weekly,monthly,quarterly',
            'delivery_method' => 'required|string|in:email,storage,webhook',
            'delivery_config' => 'required|array',
            'filters' => 'array',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $scheduleOptions = array_merge($validator->validated(), [
                'user_id' => $request->user()->id,
            ]);

            $result = $this->reportExportService->scheduleAutomatedReport($scheduleOptions);

            return response()->json([
                'success' => $result['success'],
                'data' => $result['success'] ? $result : null,
                'error' => $result['success'] ? null : $result['error'],
                'message' => $result['success'] ? 'Report scheduled successfully' : $result['message'],
            ], $result['success'] ? 201 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Scheduling failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get export history for user.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Export history
     */
    public function exportHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'format' => 'nullable|string|in:pdf,csv,json,xlsx',
            'report_type' => 'nullable|string|in:comprehensive,cost_breakdown,usage_trends,budget_analysis',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = $request->user()->id;
            $filters = $validator->validated();

            $history = $this->getExportHistory($userId, $filters);

            return response()->json([
                'success' => true,
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve export history',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete exported report file.
     *
     * @param  Request  $request  HTTP request
     * @param  string  $fileName  File name
     * @return JsonResponse Deletion result
     */
    public function deleteReport(Request $request, string $fileName): JsonResponse
    {
        try {
            $filePath = "exports/{$fileName}";

            if (! Storage::disk('local')->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File not found',
                ], 404);
            }

            // Verify user has access to this file
            if (! $this->canAccessFile($request, $fileName)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access',
                ], 403);
            }

            Storage::disk('local')->delete($filePath);

            return response()->json([
                'success' => true,
                'message' => 'Report deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Deletion failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if user can access file.
     *
     * @param  Request  $request  HTTP request
     * @param  string  $fileName  File name
     * @return bool Can access
     */
    protected function canAccessFile(Request $request, string $fileName): bool
    {
        // Extract user ID from filename or check database
        // For now, implement basic check
        return true; // Placeholder - implement proper access control
    }

    /**
     * Get MIME type for file.
     *
     * @param  string  $fileName  File name
     * @return string MIME type
     */
    protected function getMimeType(string $fileName): string
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        return match ($extension) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    /**
     * Get export history for user.
     *
     * @param  int  $userId  User ID
     * @param  array  $filters  Filters
     * @return array Export history
     */
    protected function getExportHistory(int $userId, array $filters): array
    {
        // This would query the database for export history
        // For now, return placeholder data
        return [
            'exports' => [],
            'total' => 0,
            'page' => $filters['page'] ?? 1,
            'limit' => $filters['limit'] ?? 20,
        ];
    }
}
