<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TicketComment extends Model
{
    protected $table = 'ticket_comments';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'client_id',
        'content',
        'is_internal_note',
        'is_ai_generated',
        'ai_suggestion_used',
        'attachments',
        'is_read',
        'read_at',
        'read_by',
        // Email metadata for Gmail-style rendering
        'from_address',
        'to_addresses',
        'cc_addresses',
        'subject',
        'body_html',
        'body_plain',
        'headers',
    ];

    protected $casts = [
        'id' => 'string',
        'ticket_id' => 'string',
        'user_id' => 'string',
        'client_id' => 'string',
        'read_by' => 'string',
        'is_internal_note' => 'boolean',
        'is_ai_generated' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'attachments' => 'array',
        // Email metadata casts
        'to_addresses' => 'array',
        'cc_addresses' => 'array',
        'headers' => 'array',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($comment) {
            if (!$comment->id) {
                $comment->id = (string) Str::uuid();
            }
        });

        static::created(function ($comment) {
            // Update ticket's first response time if this is the first agent response
            // INCLUDES internal notes - agents working on tickets should count as response
            if ($comment->user_id) {
                $ticket = $comment->ticket;
                if (!$ticket->first_response_at) {
                    $ticket->first_response_at = $comment->created_at;
                    $ticket->save();
                }
            }

            // Log comment creation in ticket history
            TicketHistory::create([
                'ticket_id' => $comment->ticket_id,
                'user_id' => $comment->user_id ?? null,
                'action' => $comment->is_internal_note ? 'internal_note_added' : 'comment_added',
                'metadata' => [
                    'comment_id' => $comment->id,
                    'content_preview' => Str::limit($comment->content, 100),
                    'is_ai_generated' => $comment->is_ai_generated,
                ]
            ]);
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
     * Scopes
     */
    public function scopePublic($query)
    {
        return $query->where('is_internal_note', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal_note', true);
    }

    public function scopeByAgent($query)
    {
        return $query->whereNotNull('user_id');
    }

    public function scopeByClient($query)
    {
        return $query->whereNotNull('client_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Helper methods
     */
    public function isFromAgent(): bool
    {
        return !is_null($this->user_id);
    }

    public function isFromClient(): bool
    {
        return !is_null($this->client_id);
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    public function getAttachmentCount(): int
    {
        return $this->attachments ? count($this->attachments) : 0;
    }
}