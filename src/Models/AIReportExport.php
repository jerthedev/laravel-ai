<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AIReportExport extends Model
{
    protected $table = 'ai_report_exports';

    protected $fillable = [
        'export_id',
        'user_id',
        'report_type',
        'format',
        'date_range',
        'start_date',
        'end_date',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'status',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
        'filters',
        'options',
        'metadata',
        'download_count',
        'last_downloaded_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'file_size' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'filters' => 'array',
        'options' => 'array',
        'metadata' => 'array',
        'download_count' => 'integer',
        'last_downloaded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $reportType)
    {
        return $query->where('report_type', $reportType);
    }

    public function scopeByFormat($query, string $format)
    {
        return $query->where('format', $format);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'processing', 'completed'])
            ->where('expires_at', '>', now());
    }

    public function scopeReadyForDownload($query)
    {
        return $query->where('status', 'completed')
            ->where('expires_at', '>', now());
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function getProcessingTimeAttribute(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        if ($this->file_size === null) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function getTimeToExpiryAttribute(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }

        return now()->diffInHours($this->expires_at, false);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (! $this->isReadyForDownload()) {
            return null;
        }

        // This would be a route to the download endpoint
        return route('ai.reports.download', ['export' => $this->export_id]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
               ($this->expires_at && $this->expires_at->isPast());
    }

    public function isReadyForDownload(): bool
    {
        return $this->isCompleted() && ! $this->isExpired();
    }

    public function hasBeenDownloaded(): bool
    {
        return $this->download_count > 0;
    }

    public function isNearExpiry(int $hours = 24): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->diffInHours(now()) <= $hours;
    }

    public function start(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        return $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function complete(string $filePath, string $fileName, int $fileSize, string $mimeType): bool
    {
        if ($this->status !== 'processing') {
            return false;
        }

        return $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7), // Default 7 days expiry
        ]);
    }

    public function fail(string $errorMessage): bool
    {
        if (! in_array($this->status, ['pending', 'processing'])) {
            return false;
        }

        return $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    public function expire(): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        return $this->update([
            'status' => 'expired',
        ]);
    }

    public function recordDownload(): bool
    {
        if (! $this->isReadyForDownload()) {
            return false;
        }

        return $this->increment('download_count') &&
               $this->update(['last_downloaded_at' => now()]);
    }

    public function extendExpiry(int $days = 7): bool
    {
        if (! $this->isCompleted()) {
            return false;
        }

        return $this->update([
            'expires_at' => now()->addDays($days),
        ]);
    }

    public static function createExport(int $userId, string $reportType, string $format, array $options = []): self
    {
        $exportId = Str::uuid()->toString();

        return self::create([
            'export_id' => $exportId,
            'user_id' => $userId,
            'report_type' => $reportType,
            'format' => $format,
            'date_range' => $options['date_range'] ?? null,
            'start_date' => $options['start_date'] ?? null,
            'end_date' => $options['end_date'] ?? null,
            'filters' => $options['filters'] ?? [],
            'options' => $options['export_options'] ?? [],
            'metadata' => $options['metadata'] ?? [],
            'status' => 'pending',
        ]);
    }

    public static function cleanupExpired(): int
    {
        $expiredExports = self::where('expires_at', '<', now())
            ->where('status', 'completed')
            ->get();

        $cleanedCount = 0;

        foreach ($expiredExports as $export) {
            // Delete the actual file if it exists
            if ($export->file_path && file_exists($export->file_path)) {
                unlink($export->file_path);
            }

            // Mark as expired
            $export->expire();
            $cleanedCount++;
        }

        return $cleanedCount;
    }

    public static function getUserExportStats(int $userId, int $days = 30): array
    {
        $exports = self::forUser($userId)->recent($days)->get();

        return [
            'total_exports' => $exports->count(),
            'completed_exports' => $exports->where('status', 'completed')->count(),
            'failed_exports' => $exports->where('status', 'failed')->count(),
            'total_downloads' => $exports->sum('download_count'),
            'total_file_size' => $exports->sum('file_size'),
            'by_type' => $exports->groupBy('report_type')->map->count()->toArray(),
            'by_format' => $exports->groupBy('format')->map->count()->toArray(),
            'avg_processing_time' => $exports->filter(fn ($e) => $e->processing_time)->avg('processing_time'),
        ];
    }

    public static function getSystemExportStats(int $days = 30): array
    {
        $exports = self::recent($days)->get();

        return [
            'total_exports' => $exports->count(),
            'completed_exports' => $exports->where('status', 'completed')->count(),
            'failed_exports' => $exports->where('status', 'failed')->count(),
            'processing_exports' => $exports->where('status', 'processing')->count(),
            'pending_exports' => $exports->where('status', 'pending')->count(),
            'total_downloads' => $exports->sum('download_count'),
            'total_file_size' => $exports->sum('file_size'),
            'unique_users' => $exports->pluck('user_id')->unique()->count(),
            'popular_types' => $exports->groupBy('report_type')->map->count()->sortDesc()->toArray(),
            'popular_formats' => $exports->groupBy('format')->map->count()->sortDesc()->toArray(),
            'avg_processing_time' => $exports->filter(fn ($e) => $e->processing_time)->avg('processing_time'),
            'success_rate' => $exports->count() > 0 ?
                ($exports->where('status', 'completed')->count() / $exports->count()) * 100 : 0,
        ];
    }

    public static function getProcessingQueue(): array
    {
        return self::whereIn('status', ['pending', 'processing'])
            ->orderBy('created_at')
            ->get()
            ->map(function ($export) {
                return [
                    'export_id' => $export->export_id,
                    'user_id' => $export->user_id,
                    'report_type' => $export->report_type,
                    'format' => $export->format,
                    'status' => $export->status,
                    'created_at' => $export->created_at,
                    'started_at' => $export->started_at,
                    'queue_time' => $export->created_at->diffInMinutes(now()),
                ];
            })
            ->toArray();
    }
}
