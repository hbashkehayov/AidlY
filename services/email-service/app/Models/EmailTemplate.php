<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'subject',
        'body_html',
        'body_plain',
        'category',
        'variables',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'id' => 'string',
        'created_by' => 'string',
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    // Template categories
    const CATEGORY_WELCOME = 'welcome';
    const CATEGORY_TICKET_CREATED = 'ticket_created';
    const CATEGORY_TICKET_UPDATED = 'ticket_updated';
    const CATEGORY_TICKET_RESOLVED = 'ticket_resolved';
    const CATEGORY_TICKET_CLOSED = 'ticket_closed';
    const CATEGORY_AUTO_REPLY = 'auto_reply';
    const CATEGORY_ESCALATION = 'escalation';
    const CATEGORY_REMINDER = 'reminder';
    const CATEGORY_CUSTOM = 'custom';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (!$template->id) {
                $template->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Render template with variables
     */
    public function render(array $variables = []): array
    {
        $subject = $this->renderText($this->subject, $variables);
        $bodyHtml = $this->renderText($this->body_html, $variables);
        $bodyPlain = $this->renderText($this->body_plain, $variables);

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_plain' => $bodyPlain,
        ];
    }

    /**
     * Render text with variable substitution
     */
    protected function renderText(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace("{{$key}}", $value, $text);
        }

        return $text;
    }

    /**
     * Extract variables from template text
     */
    public function extractVariables(): array
    {
        $variables = [];
        $texts = [$this->subject, $this->body_html, $this->body_plain];

        foreach ($texts as $text) {
            if (preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches)) {
                $variables = array_merge($variables, $matches[1]);
            }
        }

        return array_unique($variables);
    }

    /**
     * Validate template variables
     */
    public function validateVariables(array $variables): array
    {
        $required = $this->extractVariables();
        $missing = [];

        foreach ($required as $var) {
            if (!array_key_exists($var, $variables)) {
                $missing[] = $var;
            }
        }

        return $missing;
    }

    /**
     * Get default templates
     */
    public static function getDefaultTemplates(): array
    {
        return [
            [
                'name' => 'New Ticket Confirmation',
                'category' => self::CATEGORY_TICKET_CREATED,
                'subject' => 'Your support request has been received - {{ticket_number}}',
                'body_html' => '<p>Hello {{client_name}},</p><p>Thank you for contacting us. We have received your support request and assigned it ticket number <strong>{{ticket_number}}</strong>.</p><p><strong>Subject:</strong> {{ticket_subject}}</p><p>Our support team will review your request and respond within {{sla_time}}.</p><p>Best regards,<br>{{company_name}} Support Team</p>',
                'body_plain' => "Hello {{client_name}},\n\nThank you for contacting us. We have received your support request and assigned it ticket number {{ticket_number}}.\n\nSubject: {{ticket_subject}}\n\nOur support team will review your request and respond within {{sla_time}}.\n\nBest regards,\n{{company_name}} Support Team",
                'variables' => ['client_name', 'ticket_number', 'ticket_subject', 'sla_time', 'company_name'],
                'is_active' => true,
            ],
            [
                'name' => 'Ticket Resolved',
                'category' => self::CATEGORY_TICKET_RESOLVED,
                'subject' => 'Your support request has been resolved - {{ticket_number}}',
                'body_html' => '<p>Hello {{client_name}},</p><p>We are pleased to inform you that your support request ({{ticket_number}}) has been resolved.</p><p><strong>Subject:</strong> {{ticket_subject}}</p><p><strong>Resolution:</strong></p><p>{{resolution_notes}}</p><p>If you have any questions or need further assistance, please reply to this email.</p><p>Best regards,<br>{{agent_name}}<br>{{company_name}} Support Team</p>',
                'body_plain' => "Hello {{client_name}},\n\nWe are pleased to inform you that your support request ({{ticket_number}}) has been resolved.\n\nSubject: {{ticket_subject}}\n\nResolution:\n{{resolution_notes}}\n\nIf you have any questions or need further assistance, please reply to this email.\n\nBest regards,\n{{agent_name}}\n{{company_name}} Support Team",
                'variables' => ['client_name', 'ticket_number', 'ticket_subject', 'resolution_notes', 'agent_name', 'company_name'],
                'is_active' => true,
            ],
            [
                'name' => 'Auto Reply',
                'category' => self::CATEGORY_AUTO_REPLY,
                'subject' => 'Re: {{original_subject}}',
                'body_html' => '<p>Thank you for your email. We have received your message and will respond as soon as possible.</p><p>If this is urgent, please call us at {{phone_number}}.</p><p>Best regards,<br>{{company_name}} Support Team</p>',
                'body_plain' => "Thank you for your email. We have received your message and will respond as soon as possible.\n\nIf this is urgent, please call us at {{phone_number}}.\n\nBest regards,\n{{company_name}} Support Team",
                'variables' => ['original_subject', 'phone_number', 'company_name'],
                'is_active' => true,
            ],
        ];
    }
}