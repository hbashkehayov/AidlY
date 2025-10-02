<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BlockedEmailAttempt extends Model
{
    protected $table = 'blocked_email_attempts';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'client_id',
        'email_address',
        'client_name',
        'subject',
        'email_queue_id',
        'message_id',
        'notification_sent',
        'block_reason',
        'metadata',
        'attempted_at',
    ];

    protected $casts = [
        'id' => 'string',
        'client_id' => 'string',
        'email_queue_id' => 'string',
        'notification_sent' => 'boolean',
        'metadata' => 'array',
        'attempted_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($attempt) {
            if (!$attempt->id) {
                $attempt->id = (string) Str::uuid();
            }

            if (!$attempt->attempted_at) {
                $attempt->attempted_at = Carbon::now();
            }
        });
    }

    /**
     * Scopes
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('attempted_at', '>=', now()->subDays($days));
    }

    public function scopeByEmail($query, string $email)
    {
        return $query->where('email_address', 'ILIKE', '%' . $email . '%');
    }

    public function scopeNotificationSent($query)
    {
        return $query->where('notification_sent', true);
    }

    public function scopeNotificationFailed($query)
    {
        return $query->where('notification_sent', false);
    }
}
