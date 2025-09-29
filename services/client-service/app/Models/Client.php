<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Client extends Model
{
    protected $table = 'clients';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'email',
        'name',
        'company',
        'phone',
        'mobile',
        'avatar_url',
        'timezone',
        'language',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'postal_code',
        'crm_id',
        'crm_type',
        'lead_score',
        'lifetime_value',
        'notification_preferences',
        'custom_fields',
        'tags',
        'is_vip',
        'is_blocked',
        'first_contact_at',
        'last_contact_at'
    ];

    protected $casts = [
        'id' => 'string',
        'notification_preferences' => 'array',
        'custom_fields' => 'array',
        'tags' => 'array',
        'is_vip' => 'boolean',
        'is_blocked' => 'boolean',
        'is_deleted' => 'boolean',
        'lead_score' => 'integer',
        'lifetime_value' => 'decimal:2',
        'first_contact_at' => 'datetime',
        'last_contact_at' => 'datetime',
    ];

    protected $hidden = [
        'is_deleted',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID for new clients
        static::creating(function ($client) {
            if (!$client->id) {
                $client->id = (string) Str::uuid();
            }

            // Set first contact if not provided
            if (!$client->first_contact_at) {
                $client->first_contact_at = date('Y-m-d H:i:s');
            }
        });
    }

    /**
     * Relationships
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->orderBy('created_at', 'desc');
    }

    public function merges(): HasMany
    {
        return $this->hasMany(ClientMerge::class, 'primary_client_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeBlocked($query)
    {
        return $query->where('is_blocked', true);
    }

    public function scopeVip($query)
    {
        return $query->where('is_vip', true);
    }

    public function scopeByCompany($query, $company)
    {
        return $query->where('company', 'ILIKE', '%' . $company . '%');
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('email', 'ILIKE', '%' . $email . '%');
    }

    public function scopeByName($query, $name)
    {
        return $query->where('name', 'ILIKE', '%' . $name . '%');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ILIKE', '%' . $search . '%')
              ->orWhere('email', 'ILIKE', '%' . $search . '%')
              ->orWhere('company', 'ILIKE', '%' . $search . '%')
              ->orWhere('phone', 'ILIKE', '%' . $search . '%');
        });
    }

    /**
     * Helper methods
     */
    public function getFullNameAttribute(): string
    {
        return $this->name ?: $this->email;
    }

    public function getFullAddressAttribute(): string
    {
        $address = collect([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country
        ])->filter()->implode(', ');

        return $address;
    }

    public function updateLastContact()
    {
        $this->last_contact_at = now();
        $this->save();
    }

    public function block($reason = null)
    {
        $this->is_blocked = true;

        // Add block reason to custom fields
        if ($reason) {
            $customFields = $this->custom_fields ?: [];
            $customFields['block_reason'] = $reason;
            $customFields['blocked_at'] = now()->toISOString();
            $this->custom_fields = $customFields;
        }

        return $this->save();
    }

    public function unblock()
    {
        $this->is_blocked = false;

        // Remove block reason from custom fields
        $customFields = $this->custom_fields ?: [];
        unset($customFields['block_reason'], $customFields['blocked_at']);
        $this->custom_fields = $customFields;

        return $this->save();
    }

    public function setAsVip($reason = null)
    {
        $this->is_vip = true;

        if ($reason) {
            $customFields = $this->custom_fields ?: [];
            $customFields['vip_reason'] = $reason;
            $customFields['vip_since'] = now()->toISOString();
            $this->custom_fields = $customFields;
        }

        return $this->save();
    }

    public function removeVip()
    {
        $this->is_vip = false;

        $customFields = $this->custom_fields ?: [];
        unset($customFields['vip_reason'], $customFields['vip_since']);
        $this->custom_fields = $customFields;

        return $this->save();
    }

    public function addTag($tag)
    {
        $tags = $this->tags ?: [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
            return $this->save();
        }
        return true;
    }

    public function removeTag($tag)
    {
        $tags = $this->tags ?: [];
        $tags = array_values(array_diff($tags, [$tag]));
        $this->tags = $tags;
        return $this->save();
    }

    public function hasTag($tag): bool
    {
        $tags = $this->tags ?: [];
        return in_array($tag, $tags);
    }

    /**
     * Soft delete functionality
     */
    public function softDelete()
    {
        $this->is_deleted = true;
        return $this->save();
    }

    public function restore()
    {
        $this->is_deleted = false;
        return $this->save();
    }

    public function isDeleted(): bool
    {
        return $this->is_deleted;
    }
}