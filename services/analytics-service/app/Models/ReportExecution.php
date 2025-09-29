<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportExecution extends Model
{
    protected $table = 'report_executions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'report_id',
        'executed_by',
        'execution_type',
        'status',
        'record_count',
        'execution_time_ms',
        'file_path',
        'error_message'
    ];

    protected $casts = [
        'id' => 'string',
        'report_id' => 'string',
        'executed_by' => 'string',
        'record_count' => 'integer',
        'execution_time_ms' => 'integer',
        'created_at' => 'datetime'
    ];

    // Execution types
    const TYPE_MANUAL = 'manual';
    const TYPE_SCHEDULED = 'scheduled';
    const TYPE_EXPORT = 'export';

    // Status types
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($execution) {
            if (!$execution->id) {
                $execution->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationships
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo('App\Models\User', 'executed_by', 'id');
    }

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeByExecutor($query, $userId)
    {
        return $query->where('executed_by', $userId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('execution_type', $type);
    }

    public function scopeRecent($query, $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Accessors
     */
    public function getExecutionTimeSecondsAttribute()
    {
        return $this->execution_time_ms ? round($this->execution_time_ms / 1000, 2) : null;
    }

    public function getIsSuccessfulAttribute()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getHasFileAttribute()
    {
        return !empty($this->file_path);
    }

    public function getFileExistsAttribute()
    {
        return $this->has_file && \Storage::disk('local')->exists($this->file_path);
    }

    public function getFileSizeAttribute()
    {
        return $this->file_exists ? \Storage::disk('local')->size($this->file_path) : null;
    }

    public function getFormattedFileSizeAttribute()
    {
        $size = $this->file_size;
        if (!$size) return null;

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($size) / log(1024));
        $power = min($power, count($units) - 1);

        return round($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Helper methods
     */
    public function markAsCompleted($recordCount = null, $filePath = null)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'record_count' => $recordCount,
            'file_path' => $filePath,
            'execution_time_ms' => $this->getExecutionTimeMs()
        ]);
    }

    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'execution_time_ms' => $this->getExecutionTimeMs()
        ]);
    }

    private function getExecutionTimeMs()
    {
        return round((microtime(true) - $this->created_at->timestamp) * 1000);
    }

    /**
     * Static methods
     */
    public static function getExecutionTypes()
    {
        return [
            self::TYPE_MANUAL => 'Manual',
            self::TYPE_SCHEDULED => 'Scheduled',
            self::TYPE_EXPORT => 'Export'
        ];
    }

    public static function getStatusOptions()
    {
        return [
            self::STATUS_RUNNING => 'Running',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed'
        ];
    }
}