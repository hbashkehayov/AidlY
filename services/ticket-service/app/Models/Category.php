<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $table = 'categories';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false; // Only has created_at

    protected $fillable = [
        'name',
        'description',
        'parent_category_id',
        'icon',
        'color',
        'is_active',
        'display_order'
    ];

    protected $casts = [
        'id' => 'string',
        'parent_category_id' => 'string',
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (!$category->id) {
                $category->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationships
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_category_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_category_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * Helper methods
     */
    public function isRootCategory(): bool
    {
        return is_null($this->parent_category_id);
    }

    public function hasChildren(): bool
    {
        return $this->children()->count() > 0;
    }

    public function getFullPathAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->full_path . ' > ' . $this->name;
        }

        return $this->name;
    }

    public function getTicketCountAttribute(): int
    {
        return $this->tickets()->active()->count();
    }
}