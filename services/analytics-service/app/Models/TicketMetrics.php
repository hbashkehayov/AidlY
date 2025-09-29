<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TicketMetrics extends Model
{
    protected $table = 'ticket_metrics';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'date',
        'tickets_created',
        'tickets_resolved',
        'tickets_closed',
        'tickets_reopened',
        'status_new',
        'status_open',
        'status_pending',
        'status_on_hold',
        'status_resolved',
        'status_closed',
        'status_cancelled',
        'priority_low',
        'priority_medium',
        'priority_high',
        'priority_urgent',
        'source_email',
        'source_web_form',
        'source_chat',
        'source_phone',
        'source_social_media',
        'source_api',
        'source_internal',
        'avg_first_response_time',
        'avg_resolution_time',
        'avg_customer_satisfaction',
        'ai_categorizations',
        'ai_suggestions_used',
        'ai_sentiment_analyzed'
    ];

    protected $casts = [
        'id' => 'string',
        'date' => 'date',
        'tickets_created' => 'integer',
        'tickets_resolved' => 'integer',
        'tickets_closed' => 'integer',
        'tickets_reopened' => 'integer',
        'status_new' => 'integer',
        'status_open' => 'integer',
        'status_pending' => 'integer',
        'status_on_hold' => 'integer',
        'status_resolved' => 'integer',
        'status_closed' => 'integer',
        'status_cancelled' => 'integer',
        'priority_low' => 'integer',
        'priority_medium' => 'integer',
        'priority_high' => 'integer',
        'priority_urgent' => 'integer',
        'source_email' => 'integer',
        'source_web_form' => 'integer',
        'source_chat' => 'integer',
        'source_phone' => 'integer',
        'source_social_media' => 'integer',
        'source_api' => 'integer',
        'source_internal' => 'integer',
        'avg_first_response_time' => 'integer',
        'avg_resolution_time' => 'integer',
        'avg_customer_satisfaction' => 'decimal:2',
        'ai_categorizations' => 'integer',
        'ai_suggestions_used' => 'integer',
        'ai_sentiment_analyzed' => 'integer',
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
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('date', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('date', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth()
        ]);
    }

    /**
     * Accessors for formatted data
     */
    public function getStatusDistributionAttribute()
    {
        return [
            'new' => $this->status_new,
            'open' => $this->status_open,
            'pending' => $this->status_pending,
            'on_hold' => $this->status_on_hold,
            'resolved' => $this->status_resolved,
            'closed' => $this->status_closed,
            'cancelled' => $this->status_cancelled
        ];
    }

    public function getPriorityDistributionAttribute()
    {
        return [
            'low' => $this->priority_low,
            'medium' => $this->priority_medium,
            'high' => $this->priority_high,
            'urgent' => $this->priority_urgent
        ];
    }

    public function getSourceDistributionAttribute()
    {
        return [
            'email' => $this->source_email,
            'web_form' => $this->source_web_form,
            'chat' => $this->source_chat,
            'phone' => $this->source_phone,
            'social_media' => $this->source_social_media,
            'api' => $this->source_api,
            'internal' => $this->source_internal
        ];
    }

    public function getAvgFirstResponseTimeHoursAttribute()
    {
        return $this->avg_first_response_time ? round($this->avg_first_response_time / 3600, 2) : null;
    }

    public function getAvgResolutionTimeHoursAttribute()
    {
        return $this->avg_resolution_time ? round($this->avg_resolution_time / 3600, 2) : null;
    }

    public function getResolutionRateAttribute()
    {
        if ($this->tickets_created === 0) return 0;
        return round(($this->tickets_resolved / $this->tickets_created) * 100, 2);
    }

    /**
     * Static method to aggregate daily metrics
     */
    public static function aggregateForDate($date)
    {
        $carbonDate = Carbon::parse($date);

        $metrics = static::updateOrCreate(
            ['date' => $carbonDate->format('Y-m-d')],
            [
                'tickets_created' => static::getTicketsCreatedOnDate($carbonDate),
                'tickets_resolved' => static::getTicketsResolvedOnDate($carbonDate),
                'tickets_closed' => static::getTicketsClosedOnDate($carbonDate),
                'tickets_reopened' => static::getTicketsReopenedOnDate($carbonDate),

                // Status distribution
                'status_new' => static::getTicketsByStatusOnDate($carbonDate, 'new'),
                'status_open' => static::getTicketsByStatusOnDate($carbonDate, 'open'),
                'status_pending' => static::getTicketsByStatusOnDate($carbonDate, 'pending'),
                'status_on_hold' => static::getTicketsByStatusOnDate($carbonDate, 'on_hold'),
                'status_resolved' => static::getTicketsByStatusOnDate($carbonDate, 'resolved'),
                'status_closed' => static::getTicketsByStatusOnDate($carbonDate, 'closed'),
                'status_cancelled' => static::getTicketsByStatusOnDate($carbonDate, 'cancelled'),

                // Priority distribution
                'priority_low' => static::getTicketsByPriorityOnDate($carbonDate, 'low'),
                'priority_medium' => static::getTicketsByPriorityOnDate($carbonDate, 'medium'),
                'priority_high' => static::getTicketsByPriorityOnDate($carbonDate, 'high'),
                'priority_urgent' => static::getTicketsByPriorityOnDate($carbonDate, 'urgent'),

                // Source distribution
                'source_email' => static::getTicketsBySourceOnDate($carbonDate, 'email'),
                'source_web_form' => static::getTicketsBySourceOnDate($carbonDate, 'web_form'),
                'source_chat' => static::getTicketsBySourceOnDate($carbonDate, 'chat'),
                'source_phone' => static::getTicketsBySourceOnDate($carbonDate, 'phone'),
                'source_social_media' => static::getTicketsBySourceOnDate($carbonDate, 'social_media'),
                'source_api' => static::getTicketsBySourceOnDate($carbonDate, 'api'),
                'source_internal' => static::getTicketsBySourceOnDate($carbonDate, 'internal'),

                // Performance metrics
                'avg_first_response_time' => static::getAvgFirstResponseTimeOnDate($carbonDate),
                'avg_resolution_time' => static::getAvgResolutionTimeOnDate($carbonDate),
                'avg_customer_satisfaction' => static::getAvgCustomerSatisfactionOnDate($carbonDate),

                // AI metrics
                'ai_categorizations' => static::getAICategorizations($carbonDate),
                'ai_suggestions_used' => static::getAISuggestionsUsed($carbonDate),
                'ai_sentiment_analyzed' => static::getAISentimentAnalyzed($carbonDate)
            ]
        );

        return $metrics;
    }

    // Helper methods for aggregation
    private static function getTicketsCreatedOnDate($date)
    {
        return \DB::table('tickets')
            ->whereDate('created_at', $date)
            ->where('is_deleted', false)
            ->count();
    }

    private static function getTicketsResolvedOnDate($date)
    {
        return \DB::table('tickets')
            ->whereDate('resolved_at', $date)
            ->where('is_deleted', false)
            ->count();
    }

    private static function getTicketsClosedOnDate($date)
    {
        return \DB::table('tickets')
            ->whereDate('closed_at', $date)
            ->where('is_deleted', false)
            ->count();
    }

    private static function getTicketsReopenedOnDate($date)
    {
        return \DB::table('ticket_history')
            ->where('action', 'reopened')
            ->whereDate('created_at', $date)
            ->count();
    }

    private static function getTicketsByStatusOnDate($date, $status)
    {
        return \DB::table('tickets')
            ->whereDate('created_at', $date)
            ->where('status', $status)
            ->where('is_deleted', false)
            ->count();
    }

    private static function getTicketsByPriorityOnDate($date, $priority)
    {
        return \DB::table('tickets')
            ->whereDate('created_at', $date)
            ->where('priority', $priority)
            ->where('is_deleted', false)
            ->count();
    }

    private static function getTicketsBySourceOnDate($date, $source)
    {
        return \DB::table('tickets')
            ->whereDate('created_at', $date)
            ->where('source', $source)
            ->where('is_deleted', false)
            ->count();
    }

    private static function getAvgFirstResponseTimeOnDate($date)
    {
        return \DB::table('tickets')
            ->whereDate('first_response_at', $date)
            ->whereNotNull('first_response_at')
            ->avg(\DB::raw('EXTRACT(EPOCH FROM (first_response_at - created_at))'));
    }

    private static function getAvgResolutionTimeOnDate($date)
    {
        return \DB::table('tickets')
            ->whereDate('resolved_at', $date)
            ->whereNotNull('resolved_at')
            ->avg(\DB::raw('EXTRACT(EPOCH FROM (resolved_at - created_at))'));
    }

    private static function getAvgCustomerSatisfactionOnDate($date)
    {
        // This would require a customer satisfaction system
        // For now, return a placeholder
        return null;
    }

    private static function getAICategorizations($date)
    {
        return \DB::table('tickets')
            ->whereDate('ai_last_processed_at', $date)
            ->where('ai_categorization_enabled', true)
            ->count();
    }

    private static function getAISuggestionsUsed($date)
    {
        return \DB::table('tickets')
            ->whereDate('ai_last_processed_at', $date)
            ->where('ai_suggestions_enabled', true)
            ->whereNotNull('ai_response_suggestions')
            ->count();
    }

    private static function getAISentimentAnalyzed($date)
    {
        return \DB::table('tickets')
            ->whereDate('ai_last_processed_at', $date)
            ->where('ai_sentiment_analysis_enabled', true)
            ->whereNotNull('sentiment_score')
            ->count();
    }
}