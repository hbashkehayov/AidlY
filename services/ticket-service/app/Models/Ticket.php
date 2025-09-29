<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'subject',
        'description',
        'status',
        'priority',
        'source',
        'client_id',
        'assigned_agent_id',
        'assigned_department_id',
        'category_id',
        'sla_policy_id',
        'first_response_due_at',
        'resolution_due_at',
        'tags',
        'custom_fields',
        'is_spam',
        // AI Enhancement Fields
        'detected_language',
        'language_confidence_score',
        'sentiment_score',
        'sentiment_confidence',
        'ai_category_suggestions',
        'ai_tag_suggestions',
        'ai_response_suggestions',
        'ai_estimated_resolution_time',
        'ai_processing_metadata',
        'ai_processing_status',
        'ai_categorization_enabled',
        'ai_suggestions_enabled',
        'ai_sentiment_analysis_enabled'
    ];

    protected $casts = [
        'id' => 'string',
        'client_id' => 'string',
        'assigned_agent_id' => 'string',
        'assigned_department_id' => 'string',
        'category_id' => 'string',
        'sla_policy_id' => 'string',
        'ai_suggested_category_id' => 'string',
        'first_response_at' => 'datetime',
        'first_response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'ai_processed_at' => 'datetime',
        'ai_last_processed_at' => 'datetime',
        'ai_confidence_score' => 'decimal:2',
        'language_confidence_score' => 'decimal:2',
        'sentiment_confidence' => 'decimal:2',
        'tags' => 'array',
        'custom_fields' => 'array',
        'ai_category_suggestions' => 'array',
        'ai_tag_suggestions' => 'array',
        'ai_response_suggestions' => 'array',
        'ai_processing_metadata' => 'array',
        'is_spam' => 'boolean',
        'is_deleted' => 'boolean',
        'ai_categorization_enabled' => 'boolean',
        'ai_suggestions_enabled' => 'boolean',
        'ai_sentiment_analysis_enabled' => 'boolean',
    ];

    protected $hidden = [
        'is_deleted',
        'ai_suggestion',
        'ai_webhook_url'
    ];

    // Status constants
    const STATUS_NEW = 'new';
    const STATUS_OPEN = 'open';
    const STATUS_PENDING = 'pending';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';
    const STATUS_CANCELLED = 'cancelled';

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Source constants
    const SOURCE_EMAIL = 'email';
    const SOURCE_WEB_FORM = 'web_form';
    const SOURCE_CHAT = 'chat';
    const SOURCE_PHONE = 'phone';
    const SOURCE_SOCIAL_MEDIA = 'social_media';
    const SOURCE_API = 'api';
    const SOURCE_INTERNAL = 'internal';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID for new tickets
        static::creating(function ($ticket) {
            if (!$ticket->id) {
                $ticket->id = (string) Str::uuid();
            }

            // Generate ticket number if not provided
            if (!$ticket->ticket_number) {
                $ticket->ticket_number = static::generateTicketNumber();
            }
        });

        // Log ticket history on updates
        static::updated(function ($ticket) {
            $ticket->logChanges();
        });
    }

    /**
     * Generate a unique ticket number
     */
    public static function generateTicketNumber(): string
    {
        $sequence = \DB::select("SELECT nextval('ticket_number_seq') as number")[0]->number;
        return 'TKT-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Log changes to ticket history
     */
    public function logChanges()
    {
        $changes = $this->getChanges();
        $original = $this->getOriginal();

        foreach ($changes as $field => $newValue) {
            if (in_array($field, ['updated_at'])) {
                continue; // Skip timestamp fields
            }

            TicketHistory::create([
                'ticket_id' => $this->id,
                'user_id' => $this->getCurrentUserId(),
                'action' => 'field_updated',
                'field_name' => $field,
                'old_value' => $original[$field] ?? null,
                'new_value' => $newValue,
                'metadata' => [
                    'user_agent' => request()->header('User-Agent'),
                    'ip_address' => request()->ip(),
                ]
            ]);
        }
    }

    /**
     * Helper method to get current user ID from request
     */
    protected function getCurrentUserId(): ?string
    {
        try {
            // Try to get user from request (set by JWT middleware)
            $user = request()->attributes->get('auth_user');
            if ($user && isset($user['id'])) {
                return $user['id'];
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Relationships
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at', 'asc');
    }

    public function history(): HasMany
    {
        return $this->hasMany(TicketHistory::class)->orderBy('created_at', 'desc');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeAssignedTo($query, $agentId)
    {
        return $query->where('assigned_agent_id', $agentId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_agent_id');
    }

    public function scopeOverdue($query)
    {
        return $query->where(function ($q) {
            $q->where('first_response_due_at', '<', now())
              ->orWhere('resolution_due_at', '<', now());
        });
    }

    /**
     * Helper methods
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_OPEN,
            self::STATUS_PENDING,
            self::STATUS_ON_HOLD
        ]);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED
        ]);
    }

    public function isOverdue(): bool
    {
        $now = now();
        return ($this->first_response_due_at && $this->first_response_due_at < $now) ||
               ($this->resolution_due_at && $this->resolution_due_at < $now);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_NEW => '#6B7280',
            self::STATUS_OPEN => '#3B82F6',
            self::STATUS_PENDING => '#F59E0B',
            self::STATUS_ON_HOLD => '#8B5CF6',
            self::STATUS_RESOLVED => '#10B981',
            self::STATUS_CLOSED => '#374151',
            self::STATUS_CANCELLED => '#EF4444',
            default => '#6B7280'
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => '#10B981',
            self::PRIORITY_MEDIUM => '#F59E0B',
            self::PRIORITY_HIGH => '#F97316',
            self::PRIORITY_URGENT => '#EF4444',
            default => '#6B7280'
        };
    }

    /**
     * Status transition methods
     */
    public function open($agentId = null): bool
    {
        if ($this->status === self::STATUS_NEW) {
            $this->status = self::STATUS_OPEN;
            if ($agentId) {
                $this->assigned_agent_id = $agentId;
            }
            return $this->save();
        }
        return false;
    }

    public function resolve(): bool
    {
        if ($this->isOpen()) {
            $this->status = self::STATUS_RESOLVED;
            $this->resolved_at = now();
            return $this->save();
        }
        return false;
    }

    public function close(): bool
    {
        if ($this->status === self::STATUS_RESOLVED) {
            $this->status = self::STATUS_CLOSED;
            $this->closed_at = now();
            return $this->save();
        }
        return false;
    }

    public function assign($agentId): bool
    {
        $this->assigned_agent_id = $agentId;
        if ($this->status === self::STATUS_NEW) {
            $this->status = self::STATUS_OPEN;
        }
        return $this->save();
    }

    public function setPriority($priority): bool
    {
        if (in_array($priority, [
            self::PRIORITY_LOW,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT
        ])) {
            $this->priority = $priority;
            return $this->save();
        }
        return false;
    }

    /**
     * AI Integration Helper Methods
     */

    /**
     * Check if AI features are enabled for this ticket
     */
    public function hasAIEnabled(): bool
    {
        return $this->ai_categorization_enabled ||
               $this->ai_suggestions_enabled ||
               $this->ai_sentiment_analysis_enabled;
    }

    /**
     * Check if ticket needs AI processing
     */
    public function needsAIProcessing(): bool
    {
        return $this->hasAIEnabled() &&
               ($this->ai_processing_status === null ||
                $this->ai_processing_status === 'failed' ||
                ($this->updated_at > $this->ai_last_processed_at));
    }

    /**
     * Mark ticket for AI processing
     */
    public function markForAIProcessing(string $status = 'pending'): bool
    {
        $this->ai_processing_status = $status;
        return $this->save();
    }

    /**
     * Update AI processing results
     */
    public function updateAIResults(array $aiData): bool
    {
        $this->fill($aiData);
        $this->ai_last_processed_at = now();
        $this->ai_processing_status = 'completed';
        return $this->save();
    }

    /**
     * Get AI suggestions for display
     */
    public function getAISuggestions(): array
    {
        return [
            'categories' => $this->ai_category_suggestions ?? [],
            'tags' => $this->ai_tag_suggestions ?? [],
            'responses' => $this->ai_response_suggestions ?? [],
            'priority' => $this->ai_suggested_priority,
            'estimated_resolution_time' => $this->ai_estimated_resolution_time,
            'sentiment' => [
                'score' => $this->sentiment_score,
                'confidence' => $this->sentiment_confidence
            ],
            'language' => [
                'detected' => $this->detected_language,
                'confidence' => $this->language_confidence_score
            ]
        ];
    }

    /**
     * Apply AI suggestions to ticket
     */
    public function applyAISuggestion(string $type, $value): bool
    {
        switch ($type) {
            case 'category':
                if (is_string($value)) {
                    $this->category_id = $value;
                }
                break;
            case 'priority':
                if (in_array($value, [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT])) {
                    $this->priority = $value;
                }
                break;
            case 'tags':
                if (is_array($value)) {
                    $this->tags = array_unique(array_merge($this->tags ?? [], $value));
                }
                break;
        }

        return $this->save();
    }

    /**
     * Get AI confidence level as text
     */
    public function getAIConfidenceLevel(): string
    {
        if (!$this->ai_confidence_score) {
            return 'none';
        }

        if ($this->ai_confidence_score >= 0.8) {
            return 'high';
        } elseif ($this->ai_confidence_score >= 0.6) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Scope for tickets that need AI processing
     */
    public function scopeNeedsAIProcessing($query)
    {
        return $query->where(function ($q) {
            $q->where('ai_categorization_enabled', true)
              ->orWhere('ai_suggestions_enabled', true)
              ->orWhere('ai_sentiment_analysis_enabled', true);
        })->where(function ($q) {
            $q->whereNull('ai_processing_status')
              ->orWhere('ai_processing_status', 'failed')
              ->orWhereColumn('updated_at', '>', 'ai_last_processed_at');
        });
    }
}