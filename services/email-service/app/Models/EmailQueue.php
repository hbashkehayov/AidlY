<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmailQueue extends Model
{
    protected $table = 'email_queue';

    protected $keyType = 'string';
    public $incrementing = false;

    // Disable timestamps since table only has created_at
    public $timestamps = false;

    protected $fillable = [
        'email_account_id',
        'message_id',
        'from_address',
        'to_addresses',
        'cc_addresses',
        'subject',
        'body_plain',
        'body_html',
        'headers',
        'attachments',
        'ticket_id',
        'is_processed',
        'processed_at',
        'error_message',
        'retry_count',
        'received_at'
    ];

    protected $casts = [
        'id' => 'string',
        'email_account_id' => 'string',
        'ticket_id' => 'string',
        'to_addresses' => 'array',
        'cc_addresses' => 'array',
        'headers' => 'array',
        'attachments' => 'array',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
        'received_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    // Processing status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PROCESSED = 'processed';
    const STATUS_FAILED = 'failed';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($email) {
            if (!$email->id) {
                $email->id = (string) Str::uuid();
            }

            if (!$email->received_at) {
                $email->received_at = date('Y-m-d H:i:s');
            }
        });
    }

    /**
     * Relationships
     */
    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('is_processed', false)
                     ->where('retry_count', '<', 5);
    }

    public function scopeProcessed($query)
    {
        return $query->where('is_processed', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('error_message', '!=', null);
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('email_account_id', $accountId);
    }

    /**
     * Helper methods
     */
    public function markAsProcessed($ticketId = null)
    {
        $this->is_processed = true;
        $this->processed_at = date('Y-m-d H:i:s');
        if ($ticketId) {
            $this->ticket_id = $ticketId;
        }
        $this->save();
    }

    public function markAsFailed($errorMessage)
    {
        $this->error_message = $errorMessage;
        $this->retry_count += 1;
        $this->save();
    }

    public function incrementRetryCount()
    {
        $this->retry_count += 1;
        $this->save();
    }

    /**
     * Check if email is duplicate based on message ID
     */
    public static function isDuplicate($messageId, $emailAccountId = null): bool
    {
        $query = static::where('message_id', $messageId);

        if ($emailAccountId) {
            $query->where('email_account_id', $emailAccountId);
        }

        return $query->exists();
    }

    /**
     * Get plain text content with fallback to HTML
     */
    public function getContentAttribute(): string
    {
        return $this->body_plain ?: strip_tags($this->body_html ?: '');
    }

    /**
     * Check if this email has attachments
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * Get attachment count
     */
    public function getAttachmentCount(): int
    {
        return count($this->attachments ?: []);
    }

    /**
     * Check if email should be retried
     */
    public function shouldRetry(int $maxRetries = 5): bool
    {
        return $this->retry_count < $maxRetries && !empty($this->error_message);
    }
}