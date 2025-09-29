<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ScheduledReport extends Model
{
    protected $table = 'scheduled_reports';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'report_id',
        'cron_expression',
        'timezone',
        'recipients',
        'is_active',
        'last_run_at',
        'next_run_at',
        'failure_count'
    ];

    protected $casts = [
        'id' => 'string',
        'report_id' => 'string',
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'failure_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($schedule) {
            if (!$schedule->id) {
                $schedule->id = (string) Str::uuid();
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

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->where('next_run_at', '<=', now())
            ->where('is_active', true);
    }

    public function scopeByReport($query, $reportId)
    {
        return $query->where('report_id', $reportId);
    }

    /**
     * Accessors
     */
    public function getIsOverdueAttribute()
    {
        return $this->next_run_at && $this->next_run_at->isPast();
    }

    public function getHasFailuresAttribute()
    {
        return $this->failure_count > 0;
    }

    public function getCronDescriptionAttribute()
    {
        return $this->describeCronExpression($this->cron_expression);
    }

    /**
     * Methods
     */
    public function markAsRun()
    {
        $this->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRun(),
            'failure_count' => 0
        ]);
    }

    public function markAsFailed()
    {
        $this->increment('failure_count');

        // Disable after 5 consecutive failures
        if ($this->failure_count >= 5) {
            $this->update(['is_active' => false]);
        } else {
            // Retry in 1 hour
            $this->update(['next_run_at' => now()->addHour()]);
        }
    }

    public function calculateNextRun()
    {
        // This is a simplified implementation
        // In production, you'd use a proper cron parser like mtdowling/cron-expression
        $expression = $this->cron_expression;
        $timezone = $this->timezone ?? 'UTC';

        try {
            // Parse common cron expressions
            if ($expression === '0 0 * * *') {
                // Daily at midnight
                return now($timezone)->addDay()->startOfDay();
            } elseif ($expression === '0 0 * * 0') {
                // Weekly on Sunday at midnight
                return now($timezone)->next(Carbon::SUNDAY)->startOfDay();
            } elseif ($expression === '0 0 1 * *') {
                // Monthly on 1st at midnight
                return now($timezone)->addMonth()->startOfMonth()->startOfDay();
            } elseif (preg_match('/^0 (\d+) \* \* \*$/', $expression, $matches)) {
                // Daily at specific hour
                $hour = (int) $matches[1];
                $next = now($timezone)->setHour($hour)->setMinute(0)->setSecond(0);
                if ($next->isPast()) {
                    $next->addDay();
                }
                return $next;
            }

            // Default: run in 1 hour
            return now($timezone)->addHour();
        } catch (\Exception $e) {
            // If parsing fails, default to 1 hour
            return now($timezone)->addHour();
        }
    }

    private function describeCronExpression($expression)
    {
        // Simple cron expression descriptions
        $descriptions = [
            '0 0 * * *' => 'Daily at midnight',
            '0 0 * * 0' => 'Weekly on Sundays at midnight',
            '0 0 1 * *' => 'Monthly on the 1st at midnight',
            '0 6 * * *' => 'Daily at 6:00 AM',
            '0 12 * * *' => 'Daily at 12:00 PM',
            '0 18 * * *' => 'Daily at 6:00 PM',
            '0 0 * * 1' => 'Weekly on Mondays at midnight',
            '0 9 * * 1-5' => 'Weekdays at 9:00 AM',
        ];

        return $descriptions[$expression] ?? "Custom: {$expression}";
    }

    /**
     * Static methods
     */
    public static function getCommonSchedules()
    {
        return [
            '0 0 * * *' => 'Daily at midnight',
            '0 6 * * *' => 'Daily at 6:00 AM',
            '0 9 * * *' => 'Daily at 9:00 AM',
            '0 12 * * *' => 'Daily at 12:00 PM',
            '0 18 * * *' => 'Daily at 6:00 PM',
            '0 0 * * 1' => 'Weekly on Mondays',
            '0 0 * * 0' => 'Weekly on Sundays',
            '0 9 * * 1-5' => 'Weekdays at 9:00 AM',
            '0 0 1 * *' => 'Monthly on the 1st',
            '0 0 15 * *' => 'Monthly on the 15th'
        ];
    }

    public static function getDueReports()
    {
        return static::due()->with('report')->get();
    }
}