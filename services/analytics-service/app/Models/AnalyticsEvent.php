<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AnalyticsEvent extends Model
{
    protected $table = 'analytics_events';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'event_type',
        'event_category',
        'ticket_id',
        'client_id',
        'user_id',
        'properties',
        'session_id',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'id' => 'string',
        'ticket_id' => 'string',
        'client_id' => 'string',
        'user_id' => 'string',
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (!$event->id) {
                $event->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Scopes
     */
    public function scopeByType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('event_category', $category);
    }

    public function scopeForTicket($query, $ticketId)
    {
        return $query->where('ticket_id', $ticketId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Static methods for common events
     */
    public static function logTicketCreated($ticketId, $userId, $properties = [])
    {
        return static::create([
            'event_type' => 'ticket_created',
            'event_category' => 'ticket',
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'properties' => $properties
        ]);
    }

    public static function logTicketAssigned($ticketId, $agentId, $assignedBy, $properties = [])
    {
        return static::create([
            'event_type' => 'ticket_assigned',
            'event_category' => 'ticket',
            'ticket_id' => $ticketId,
            'user_id' => $assignedBy,
            'properties' => array_merge(['assigned_to' => $agentId], $properties)
        ]);
    }

    public static function logTicketResolved($ticketId, $resolvedBy, $resolutionTime, $properties = [])
    {
        return static::create([
            'event_type' => 'ticket_resolved',
            'event_category' => 'ticket',
            'ticket_id' => $ticketId,
            'user_id' => $resolvedBy,
            'properties' => array_merge(['resolution_time_seconds' => $resolutionTime], $properties)
        ]);
    }

    public static function logUserLogin($userId, $ipAddress, $userAgent, $properties = [])
    {
        return static::create([
            'event_type' => 'user_login',
            'event_category' => 'authentication',
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'properties' => $properties
        ]);
    }
}