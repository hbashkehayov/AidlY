<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClientMerge extends Model
{
    protected $table = 'client_merges';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'primary_client_id',
        'merged_client_id',
        'merged_by',
        'merge_data'
    ];

    protected $casts = [
        'id' => 'string',
        'primary_client_id' => 'string',
        'merged_client_id' => 'string',
        'merged_by' => 'string',
        'merge_data' => 'array',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID for new merges
        static::creating(function ($merge) {
            if (!$merge->id) {
                $merge->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationships
     */
    public function primaryClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'primary_client_id');
    }

    /**
     * Scopes
     */
    public function scopeByPrimaryClient($query, $clientId)
    {
        return $query->where('primary_client_id', $clientId);
    }

    public function scopeByMergedClient($query, $clientId)
    {
        return $query->where('merged_client_id', $clientId);
    }

    public function scopeByMerger($query, $userId)
    {
        return $query->where('merged_by', $userId);
    }
}