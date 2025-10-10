<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Attachment extends Model
{
    use HasUuids;

    protected $table = 'attachments';

    protected $fillable = [
        'ticket_id',
        'comment_id',
        'uploaded_by_user_id',
        'uploaded_by_client_id',
        'file_name',
        'file_type',
        'file_size',
        'storage_path',
        'mime_type',
        'is_inline',
    ];

    protected $casts = [
        'is_inline' => 'boolean',
        'file_size' => 'integer',
        'created_at' => 'datetime',
    ];

    protected $appends = ['url', 'filename', 'path', 'size'];

    protected $visible = [
        'id',
        'ticket_id',
        'comment_id',
        'uploaded_by_user_id',
        'uploaded_by_client_id',
        'file_name',
        'file_type',
        'file_size',
        'storage_path',
        'mime_type',
        'is_inline',
        'created_at',
        'url',
        'filename',
        'path',
        'size',
    ];

    public $timestamps = false;

    /**
     * Get the ticket that owns the attachment
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the comment that owns the attachment
     */
    public function comment()
    {
        return $this->belongsTo(TicketComment::class);
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get download URL for frontend
     */
    public function getUrlAttribute(): string
    {
        return "/api/v1/attachments/{$this->id}/download";
    }

    /**
     * Get filename attribute (alias for file_name)
     */
    public function getFilenameAttribute(): ?string
    {
        return $this->attributes['file_name'] ?? null;
    }

    /**
     * Get path attribute (alias for storage_path)
     */
    public function getPathAttribute(): ?string
    {
        return $this->attributes['storage_path'] ?? null;
    }

    /**
     * Get size attribute (alias for file_size)
     */
    public function getSizeAttribute(): ?int
    {
        return $this->attributes['file_size'] ?? null;
    }
}
