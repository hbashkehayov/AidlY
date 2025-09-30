<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailAccount extends Model
{
    protected $table = 'email_accounts';

    protected $keyType = 'string';
    public $incrementing = false;

    // Disable timestamps since table only has created_at
    public $timestamps = false;

    protected $fillable = [
        'name',
        'email_address',
        'account_type', // 'shared_mailbox' or 'personal' (legacy)
        'imap_host',
        'imap_port',
        'imap_username',
        'imap_password', // This will trigger the mutator to store as imap_password_encrypted
        'imap_use_ssl',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password', // This will trigger the mutator to store as smtp_password_encrypted
        'smtp_use_tls',
        'department_id',
        'auto_create_tickets',
        'default_ticket_priority',
        'default_category_id',
        'routing_rules',
        'signature_template',
        'is_active'
    ];

    protected $casts = [
        'id' => 'string',
        'department_id' => 'string',
        'default_category_id' => 'string',
        'imap_port' => 'integer',
        'smtp_port' => 'integer',
        'imap_use_ssl' => 'boolean',
        'smtp_use_tls' => 'boolean',
        'auto_create_tickets' => 'boolean',
        'is_active' => 'boolean',
        'routing_rules' => 'array',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'imap_password_encrypted',
        'smtp_password_encrypted'
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($account) {
            if (!$account->id) {
                $account->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Encrypt and set IMAP password
     */
    public function setImapPasswordAttribute($value)
    {
        $this->attributes['imap_password_encrypted'] = encrypt($value);
    }

    /**
     * Decrypt and get IMAP password
     */
    public function getImapPasswordAttribute()
    {
        try {
            $encrypted = $this->attributes['imap_password_encrypted'] ?? '';
            return $encrypted ? decrypt($encrypted) : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Encrypt and set SMTP password
     */
    public function setSmtpPasswordAttribute($value)
    {
        $this->attributes['smtp_password_encrypted'] = encrypt($value);
    }

    /**
     * Decrypt and get SMTP password
     */
    public function getSmtpPasswordAttribute()
    {
        try {
            $encrypted = $this->attributes['smtp_password_encrypted'] ?? '';
            return $encrypted ? decrypt($encrypted) : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get IMAP configuration array
     */
    public function getImapConfig(): array
    {
        return [
            'host' => $this->imap_host,
            'port' => $this->imap_port,
            'encryption' => $this->imap_use_ssl ? 'ssl' : null,
            'username' => $this->imap_username,
            'password' => $this->imap_password,
            'validate_cert' => true,
        ];
    }

    /**
     * Get SMTP configuration array
     */
    public function getSmtpConfig(): array
    {
        return [
            'host' => $this->smtp_host,
            'port' => $this->smtp_port,
            'encryption' => $this->smtp_use_tls ? 'tls' : null,
            'username' => $this->smtp_username,
            'password' => $this->smtp_password,
        ];
    }

    /**
     * Update last sync time
     */
    public function updateLastSync()
    {
        $this->last_sync_at = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Check if this is a shared mailbox
     */
    public function isSharedMailbox(): bool
    {
        return $this->account_type === 'shared_mailbox';
    }

    /**
     * Get routing rules for this mailbox
     */
    public function getRoutingRules(): array
    {
        return $this->routing_rules ?: [];
    }

    /**
     * Get signature template with placeholders
     */
    public function getSignatureTemplate(): string
    {
        return $this->signature_template ?: "\n\n---\nBest regards,\n{agent_name}\n{department_name}\n{company_name}";
    }

    /**
     * Generate agent signature
     */
    public function generateAgentSignature(array $agentData): string
    {
        $template = $this->getSignatureTemplate();
        $replacements = [
            '{agent_name}' => $agentData['name'] ?? 'Support Team',
            '{agent_email}' => $agentData['email'] ?? $this->email_address,
            '{department_name}' => $agentData['department'] ?? 'Customer Support',
            '{company_name}' => env('APP_NAME', 'AidlY Support'),
            '{mailbox_address}' => $this->email_address,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSharedMailboxes($query)
    {
        return $query->where('account_type', 'shared_mailbox')->where('is_active', true);
    }

    public function scopeAgentAccounts($query)
    {
        return $query->where('account_type', 'shared_mailbox')
                     ->where('user_id', '!=', null)
                     ->where('is_active', true);
    }
}