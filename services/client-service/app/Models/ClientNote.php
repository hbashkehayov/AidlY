<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClientNote extends Model
{
    protected $table = 'client_notes';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'client_id',
        'created_by',
        'note',
        'is_pinned'
    ];

    protected $casts = [
        'id' => 'string',
        'client_id' => 'string',
        'created_by' => 'string',
        'is_pinned' => 'boolean',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID for new notes
        static::creating(function ($note) {
            if (!$note->id) {
                $note->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationships
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Scopes
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeByCreator($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Helper methods
     */
    public function pin()
    {
        $this->is_pinned = true;
        return $this->save();
    }

    public function unpin()
    {
        $this->is_pinned = false;
        return $this->save();
    }

    public function isPinned(): bool
    {
        return $this->is_pinned;
    }
}