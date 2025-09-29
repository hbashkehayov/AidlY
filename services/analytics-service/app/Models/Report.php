<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Report extends Model
{
    protected $table = 'reports';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'report_type',
        'query_sql',
        'filters',
        'columns',
        'chart_config',
        'schedule_config',
        'recipients',
        'is_public',
        'created_by',
        'is_active',
        'last_executed_at'
    ];

    protected $casts = [
        'id' => 'string',
        'filters' => 'array',
        'columns' => 'array',
        'chart_config' => 'array',
        'schedule_config' => 'array',
        'recipients' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'last_executed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            if (!$report->id) {
                $report->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationships
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo('App\Models\User', 'created_by', 'id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ReportExecution::class, 'report_id');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(ScheduledReport::class, 'report_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeByCreator($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Helper methods
     */
    public function getLastExecutionAttribute()
    {
        return $this->executions()->latest()->first();
    }

    public function getExecutionCountAttribute()
    {
        return $this->executions()->count();
    }

    public function getSuccessfulExecutionCountAttribute()
    {
        return $this->executions()->where('status', 'completed')->count();
    }

    public function getFailedExecutionCountAttribute()
    {
        return $this->executions()->where('status', 'failed')->count();
    }

    public function getAvgExecutionTimeAttribute()
    {
        return $this->executions()
            ->where('status', 'completed')
            ->avg('execution_time_ms');
    }

    public function isScheduled()
    {
        return $this->schedule()->where('is_active', true)->exists();
    }

    public function canBeAccessedBy($userId)
    {
        return $this->is_public || $this->created_by === $userId;
    }

    /**
     * Static methods
     */
    public static function getReportTypes()
    {
        return [
            'dashboard' => 'Dashboard Report',
            'performance' => 'Performance Report',
            'satisfaction' => 'Customer Satisfaction',
            'sla' => 'SLA Compliance',
            'activity' => 'Activity Report',
            'custom' => 'Custom Report'
        ];
    }

    public static function getChartTypes()
    {
        return [
            'line' => 'Line Chart',
            'bar' => 'Bar Chart',
            'area' => 'Area Chart',
            'pie' => 'Pie Chart',
            'column' => 'Column Chart',
            'table' => 'Data Table'
        ];
    }
}