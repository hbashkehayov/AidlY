<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TicketHistory extends Model
{
    protected $table = 'ticket_history';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false; // Only has created_at

    protected $fillable = [
        'ticket_id',
        'user_id',
        'action',
        'field_name',
        'old_value',
        'new_value',
        'metadata'
    ];

    protected $casts = [
        'id' => 'string',
        'ticket_id' => 'string',
        'user_id' => 'string',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($history) {
            if (!$history->id) {
                $history->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationships
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Helper methods
     */
    public function getActionDescription(): string
    {
        return match($this->action) {
            'created' => 'Ticket created',
            'status_changed' => 'Status changed',
            'priority_changed' => 'Priority changed',
            'assigned' => 'Assigned to agent',
            'unassigned' => 'Unassigned from agent',
            'comment_added' => 'Comment added',
            'internal_note_added' => 'Internal note added',
            'field_updated' => $this->field_name ? ucfirst(str_replace('_', ' ', $this->field_name)) . ' updated' : 'Field updated',
            'resolved' => 'Ticket resolved',
            'closed' => 'Ticket closed',
            'reopened' => 'Ticket reopened',
            default => ucfirst(str_replace('_', ' ', $this->action))
        };
    }

    public function getChangeDescription(): ?string
    {
        if ($this->old_value && $this->new_value) {
            return "Changed from '{$this->old_value}' to '{$this->new_value}'";
        } elseif ($this->new_value && !$this->old_value) {
            return "Set to '{$this->new_value}'";
        } elseif ($this->old_value && !$this->new_value) {
            return "Removed '{$this->old_value}'";
        }

        return null;
    }
}