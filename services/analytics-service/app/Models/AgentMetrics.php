<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AgentMetrics extends Model
{
    protected $table = 'agent_metrics';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'agent_id',
        'date',
        'tickets_created',
        'tickets_resolved',
        'tickets_escalated',
        'tickets_assigned',
        'avg_first_response_time',
        'avg_resolution_time',
        'total_working_time',
        'customer_satisfaction_score',
        'internal_quality_score',
        'comments_sent',
        'internal_notes'
    ];

    protected $casts = [
        'id' => 'string',
        'agent_id' => 'string',
        'date' => 'date',
        'tickets_created' => 'integer',
        'tickets_resolved' => 'integer',
        'tickets_escalated' => 'integer',
        'tickets_assigned' => 'integer',
        'avg_first_response_time' => 'integer',
        'avg_resolution_time' => 'integer',
        'total_working_time' => 'integer',
        'customer_satisfaction_score' => 'decimal:2',
        'internal_quality_score' => 'decimal:2',
        'comments_sent' => 'integer',
        'internal_notes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($metrics) {
            if (!$metrics->id) {
                $metrics->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Scopes
     */
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeTopPerformers($query, $metric = 'tickets_resolved', $limit = 10)
    {
        return $query->orderBy($metric, 'desc')->limit($limit);
    }

    /**
     * Helper methods
     */
    public function getEfficiencyScoreAttribute()
    {
        if ($this->tickets_assigned === 0) return 0;
        return round(($this->tickets_resolved / $this->tickets_assigned) * 100, 2);
    }

    public function getAvgResponseTimeHoursAttribute()
    {
        return $this->avg_first_response_time ? round($this->avg_first_response_time / 3600, 2) : null;
    }

    public function getAvgResolutionTimeHoursAttribute()
    {
        return $this->avg_resolution_time ? round($this->avg_resolution_time / 3600, 2) : null;
    }

    /**
     * Static methods for metrics aggregation
     */
    public static function aggregateForAgent($agentId, $date)
    {
        // This would typically be called by a scheduled job
        $metrics = static::updateOrCreate(
            ['agent_id' => $agentId, 'date' => $date],
            [
                'tickets_created' => static::getTicketsCreatedByAgent($agentId, $date),
                'tickets_resolved' => static::getTicketsResolvedByAgent($agentId, $date),
                'tickets_escalated' => static::getTicketsEscalatedByAgent($agentId, $date),
                'tickets_assigned' => static::getTicketsAssignedToAgent($agentId, $date),
                'avg_first_response_time' => static::getAvgFirstResponseTime($agentId, $date),
                'avg_resolution_time' => static::getAvgResolutionTime($agentId, $date),
                'comments_sent' => static::getCommentsSentByAgent($agentId, $date),
                'internal_notes' => static::getInternalNotesByAgent($agentId, $date)
            ]
        );

        return $metrics;
    }

    private static function getTicketsCreatedByAgent($agentId, $date)
    {
        return \DB::connection('pgsql')->table('tickets')
            ->where('assigned_agent_id', $agentId)
            ->whereDate('created_at', $date)
            ->count();
    }

    private static function getTicketsResolvedByAgent($agentId, $date)
    {
        return \DB::connection('pgsql')->table('tickets')
            ->where('assigned_agent_id', $agentId)
            ->whereDate('resolved_at', $date)
            ->count();
    }

    private static function getTicketsEscalatedByAgent($agentId, $date)
    {
        // Count tickets where agent was changed on given date
        return \DB::connection('pgsql')->table('ticket_history')
            ->where('user_id', $agentId)
            ->where('action', 'escalated')
            ->whereDate('created_at', $date)
            ->count();
    }

    private static function getTicketsAssignedToAgent($agentId, $date)
    {
        return \DB::connection('pgsql')->table('ticket_history')
            ->where('new_value', $agentId)
            ->where('action', 'assigned')
            ->whereDate('created_at', $date)
            ->count();
    }

    private static function getAvgFirstResponseTime($agentId, $date)
    {
        return \DB::connection('pgsql')->table('tickets')
            ->where('assigned_agent_id', $agentId)
            ->whereDate('first_response_at', $date)
            ->whereNotNull('first_response_at')
            ->avg(\DB::raw('EXTRACT(EPOCH FROM (first_response_at - created_at))'));
    }

    private static function getAvgResolutionTime($agentId, $date)
    {
        return \DB::connection('pgsql')->table('tickets')
            ->where('assigned_agent_id', $agentId)
            ->whereDate('resolved_at', $date)
            ->whereNotNull('resolved_at')
            ->avg(\DB::raw('EXTRACT(EPOCH FROM (resolved_at - created_at))'));
    }

    private static function getCommentsSentByAgent($agentId, $date)
    {
        return \DB::connection('pgsql')->table('ticket_comments')
            ->where('user_id', $agentId)
            ->where('is_internal_note', false)
            ->whereDate('created_at', $date)
            ->count();
    }

    private static function getInternalNotesByAgent($agentId, $date)
    {
        return \DB::connection('pgsql')->table('ticket_comments')
            ->where('user_id', $agentId)
            ->where('is_internal_note', true)
            ->whereDate('created_at', $date)
            ->count();
    }
}