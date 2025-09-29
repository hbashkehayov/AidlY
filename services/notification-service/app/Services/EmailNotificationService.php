<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class EmailNotificationService
{
    /**
     * Send email notification
     */
    public function sendEmailNotification(Notification $notification): bool
    {
        try {
            // Get template for this notification type
            $template = $this->getTemplate($notification->type, $notification->data['locale'] ?? 'en');

            if (!$template) {
                Log::warning('No email template found for notification type', [
                    'type' => $notification->type
                ]);
                return $this->sendPlainEmail($notification);
            }

            // Get recipient email
            $recipientEmail = $this->getRecipientEmail($notification);

            if (!$recipientEmail) {
                throw new \Exception('No recipient email found');
            }

            // Prepare template variables
            $variables = $this->prepareVariables($notification);

            // Render email content
            $subject = $this->renderTemplate($template->subject ?? $template->title_template, $variables);
            $htmlBody = $this->renderTemplate($template->html_template ?? $template->message_template, $variables);
            $textBody = $this->renderTemplate($template->message_template, $variables);

            // Send email
            Mail::raw($textBody, function ($message) use ($recipientEmail, $subject, $htmlBody) {
                $message->to($recipientEmail)
                       ->subject($subject);

                if ($htmlBody) {
                    $message->html($htmlBody);
                }

                $message->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));

                // Add priority header for urgent notifications
                if (in_array($notification->priority, ['high', 'urgent'])) {
                    $message->priority(1);
                    $message->getHeaders()->addTextHeader('X-Priority', '1');
                    $message->getHeaders()->addTextHeader('Importance', 'High');
                }
            });

            Log::info('Email notification sent', [
                'notification_id' => $notification->id,
                'recipient' => $recipientEmail
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send digest email with multiple notifications
     */
    public function sendDigestEmail(array $notifications, string $recipientEmail): bool
    {
        try {
            if (empty($notifications)) {
                return false;
            }

            // Group notifications by type
            $groupedNotifications = collect($notifications)->groupBy('type');

            // Prepare digest content
            $digestData = [
                'recipient_email' => $recipientEmail,
                'notification_count' => count($notifications),
                'grouped_notifications' => $groupedNotifications,
                'date' => now()->format('F j, Y')
            ];

            // Send digest email
            Mail::send('emails.digest', $digestData, function ($message) use ($recipientEmail, $digestData) {
                $message->to($recipientEmail)
                       ->subject("Your Daily Digest - {$digestData['notification_count']} Updates")
                       ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            });

            // Mark notifications as sent
            foreach ($notifications as $notification) {
                $notification->markAsSent();
            }

            Log::info('Digest email sent', [
                'recipient' => $recipientEmail,
                'notification_count' => count($notifications)
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send digest email', [
                'recipient' => $recipientEmail,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send plain email without template
     */
    private function sendPlainEmail(Notification $notification): bool
    {
        try {
            $recipientEmail = $this->getRecipientEmail($notification);

            if (!$recipientEmail) {
                throw new \Exception('No recipient email found');
            }

            Mail::raw($notification->message, function ($message) use ($recipientEmail, $notification) {
                $message->to($recipientEmail)
                       ->subject($notification->title)
                       ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send plain email', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get email template for notification type
     */
    private function getTemplate(string $type, string $locale = 'en'): ?NotificationTemplate
    {
        // Try to find template for specific locale
        $template = NotificationTemplate::where('event_type', $type)
            ->where('channel', 'email')
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->first();

        // Fallback to English if locale-specific template not found
        if (!$template && $locale !== 'en') {
            $template = NotificationTemplate::where('event_type', $type)
                ->where('channel', 'email')
                ->where('locale', 'en')
                ->where('is_active', true)
                ->orderBy('priority', 'desc')
                ->first();
        }

        return $template;
    }

    /**
     * Get recipient email address
     */
    private function getRecipientEmail(Notification $notification): ?string
    {
        // This would need to be implemented based on your user/client model structure
        // For now, returning from notification data
        return $notification->data['recipient_email'] ?? null;
    }

    /**
     * Prepare variables for template rendering
     */
    private function prepareVariables(Notification $notification): array
    {
        $variables = $notification->data ?? [];

        // Add common variables
        $variables['notification_id'] = $notification->id;
        $variables['notification_type'] = $notification->type;
        $variables['notification_title'] = $notification->title;
        $variables['notification_message'] = $notification->message;
        $variables['action_url'] = $notification->action_url;
        $variables['action_text'] = $notification->action_text;
        $variables['app_name'] = env('APP_NAME', 'AidlY');
        $variables['app_url'] = env('APP_URL');
        $variables['current_year'] = date('Y');

        return $variables;
    }

    /**
     * Render template with variables
     */
    private function renderTemplate(string $template, array $variables): string
    {
        $content = $template;

        // Replace variables in template
        foreach ($variables as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
                $content = str_replace('{{ ' . $key . ' }}', $value, $content);
            }
        }

        return $content;
    }

    /**
     * Create default email templates
     */
    public static function createDefaultTemplates(): void
    {
        $templates = [
            [
                'name' => 'ticket_created_email',
                'event_type' => 'ticket_created',
                'channel' => 'email',
                'locale' => 'en',
                'subject' => '[Ticket #{{ticket_number}}] {{ticket_subject}}',
                'title_template' => 'New Ticket Created',
                'message_template' => "A new ticket has been created:\n\nTicket #{{ticket_number}}\nSubject: {{ticket_subject}}\nPriority: {{ticket_priority}}\n\nDescription:\n{{ticket_description}}\n\nView ticket: {{action_url}}",
                'html_template' => '<h2>New Ticket Created</h2><p>A new ticket has been created:</p><ul><li><strong>Ticket:</strong> #{{ticket_number}}</li><li><strong>Subject:</strong> {{ticket_subject}}</li><li><strong>Priority:</strong> {{ticket_priority}}</li></ul><p><strong>Description:</strong></p><p>{{ticket_description}}</p><p><a href="{{action_url}}">View Ticket</a></p>',
                'variables' => ['ticket_number', 'ticket_subject', 'ticket_priority', 'ticket_description', 'action_url'],
                'is_system' => true
            ],
            [
                'name' => 'ticket_assigned_email',
                'event_type' => 'ticket_assigned',
                'channel' => 'email',
                'locale' => 'en',
                'subject' => '[Assigned] Ticket #{{ticket_number}} - {{ticket_subject}}',
                'title_template' => 'Ticket Assigned to You',
                'message_template' => "You have been assigned to ticket #{{ticket_number}}:\n\nSubject: {{ticket_subject}}\nPriority: {{ticket_priority}}\nClient: {{client_name}}\n\nView ticket: {{action_url}}",
                'html_template' => '<h2>Ticket Assigned to You</h2><p>You have been assigned to ticket #{{ticket_number}}:</p><ul><li><strong>Subject:</strong> {{ticket_subject}}</li><li><strong>Priority:</strong> {{ticket_priority}}</li><li><strong>Client:</strong> {{client_name}}</li></ul><p><a href="{{action_url}}">View Ticket</a></p>',
                'variables' => ['ticket_number', 'ticket_subject', 'ticket_priority', 'client_name', 'action_url'],
                'is_system' => true
            ],
            [
                'name' => 'comment_added_email',
                'event_type' => 'comment_added',
                'channel' => 'email',
                'locale' => 'en',
                'subject' => 'Re: [Ticket #{{ticket_number}}] {{ticket_subject}}',
                'title_template' => 'New Comment on Ticket',
                'message_template' => "{{commenter_name}} added a comment on ticket #{{ticket_number}}:\n\n{{comment_content}}\n\nView ticket: {{action_url}}",
                'html_template' => '<h2>New Comment on Ticket</h2><p><strong>{{commenter_name}}</strong> added a comment on ticket #{{ticket_number}}:</p><blockquote>{{comment_content}}</blockquote><p><a href="{{action_url}}">View Ticket</a></p>',
                'variables' => ['ticket_number', 'ticket_subject', 'commenter_name', 'comment_content', 'action_url'],
                'is_system' => true
            ],
            [
                'name' => 'ticket_resolved_email',
                'event_type' => 'ticket_resolved',
                'channel' => 'email',
                'locale' => 'en',
                'subject' => '[Resolved] Ticket #{{ticket_number}} - {{ticket_subject}}',
                'title_template' => 'Ticket Resolved',
                'message_template' => "Your ticket #{{ticket_number}} has been resolved.\n\nSubject: {{ticket_subject}}\nResolution: {{resolution_message}}\n\nIf you have any questions, please reply to this email.",
                'html_template' => '<h2>Ticket Resolved</h2><p>Your ticket #{{ticket_number}} has been resolved.</p><ul><li><strong>Subject:</strong> {{ticket_subject}}</li></ul><p><strong>Resolution:</strong></p><p>{{resolution_message}}</p><p>If you have any questions, please reply to this email.</p>',
                'variables' => ['ticket_number', 'ticket_subject', 'resolution_message'],
                'is_system' => true
            ],
            [
                'name' => 'sla_breach_email',
                'event_type' => 'sla_breach',
                'channel' => 'email',
                'locale' => 'en',
                'subject' => '[SLA Breach] Ticket #{{ticket_number}}',
                'title_template' => 'SLA Breach Alert',
                'message_template' => "SLA breach detected for ticket #{{ticket_number}}:\n\nSubject: {{ticket_subject}}\nPriority: {{ticket_priority}}\nBreach Type: {{breach_type}}\nTime Exceeded: {{time_exceeded}}\n\nImmediate action required: {{action_url}}",
                'html_template' => '<h2 style="color: #d32f2f;">SLA Breach Alert</h2><p>SLA breach detected for ticket #{{ticket_number}}:</p><ul><li><strong>Subject:</strong> {{ticket_subject}}</li><li><strong>Priority:</strong> {{ticket_priority}}</li><li><strong>Breach Type:</strong> {{breach_type}}</li><li><strong>Time Exceeded:</strong> {{time_exceeded}}</li></ul><p><a href="{{action_url}}" style="background-color: #d32f2f; color: white; padding: 10px 20px; text-decoration: none;">Take Action</a></p>',
                'variables' => ['ticket_number', 'ticket_subject', 'ticket_priority', 'breach_type', 'time_exceeded', 'action_url'],
                'is_system' => true,
                'priority' => 10
            ]
        ];

        foreach ($templates as $templateData) {
            NotificationTemplate::updateOrCreate(
                ['name' => $templateData['name']],
                $templateData
            );
        }
    }
}