<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'visible_to_agents',
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
        'is_internal_note' => 'boolean',
        'is_ai_generated' => 'boolean',
        // Keep in casts for proper JSON encoding when saving
        // Accessor will override this when reading if relationship is loaded
        'attachments' => 'array',
        'visible_to_agents' => 'array',
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

    public function commentAttachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'comment_id')->orderBy('created_at', 'desc');
    }

    /**
     * Accessor to make attachments available as 'attachments' in JSON responses
     * Prioritizes relational data (Attachment records) over JSONB field
     * Falls back to JSONB if relationship not loaded (backward compatibility)
     *
     * Note: The $value parameter is the casted value (array decoded from JSON by Laravel)
     */
    public function getAttachmentsAttribute($value)
    {
        // If commentAttachments relationship is loaded, use it (new approach)
        // This returns proper Attachment model instances with all fields
        if ($this->relationLoaded('commentAttachments')) {
            // Convert Collection to array to ensure proper JSON serialization with all fields
            return $this->commentAttachments->map(function($attachment) {
                return $attachment->toArray();
            })->toArray();
        }

        // Otherwise return the casted value from database (Laravel already decoded JSON → array)
        return $value;
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

    /**
     * Check if a user can view this internal note
     */
    public function canBeViewedBy(?string $userId): bool
    {
        // If not an internal note, everyone can see it
        if (!$this->is_internal_note) {
            return true;
        }

        // If no user ID provided, cannot view internal notes
        if (!$userId) {
            return false;
        }

        // Creator can always see their own notes
        if ($this->user_id === $userId) {
            return true;
        }

        // If no visibility restrictions, only creator can see
        if (!$this->visible_to_agents || empty($this->visible_to_agents)) {
            return false;
        }

        // Check if visible to all agents
        if (in_array('all', $this->visible_to_agents)) {
            return true;
        }

        // Check if user is in the visible_to_agents list
        return in_array($userId, $this->visible_to_agents);
    }
}