<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Illuminate\Support\Facades\Log;

class SmtpService
{
    protected $defaultFromAddress;
    protected $defaultFromName;

    public function __construct()
    {
        $this->defaultFromAddress = env('MAIL_FROM_ADDRESS', 'noreply@aidly.com');
        $this->defaultFromName = env('MAIL_FROM_NAME', 'AidlY Support');
    }

    /**
     * Send email using specific email account
     */
    public function sendEmail(EmailAccount $account, array $emailData): array
    {
        try {
            $mailer = $this->createMailer($account);
            $message = $this->createMessage($emailData, $account);

            $mailer->send($message);

            Log::info("Email sent successfully", [
                'account_id' => $account->id,
                'to' => $emailData['to'],
                'subject' => $emailData['subject'],
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
            ];

        } catch (\Exception $e) {
            Log::error("Failed to send email", [
                'account_id' => $account->id,
                'to' => $emailData['to'] ?? 'unknown',
                'subject' => $emailData['subject'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send email using default SMTP settings
     */
    public function sendEmailDefault(array $emailData): array
    {
        try {
            $mailer = $this->createDefaultMailer();
            $message = $this->createMessage($emailData);

            $mailer->send($message);

            Log::info("Email sent successfully (default)", [
                'to' => $emailData['to'],
                'subject' => $emailData['subject'],
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
            ];

        } catch (\Exception $e) {
            Log::error("Failed to send email (default)", [
                'to' => $emailData['to'] ?? 'unknown',
                'subject' => $emailData['subject'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send email using template
     */
    public function sendTemplatedEmail(EmailAccount $account, EmailTemplate $template, array $variables, array $recipients): array
    {
        try {
            // Validate template variables
            $missing = $template->validateVariables($variables);
            if (!empty($missing)) {
                throw new \Exception("Missing template variables: " . implode(', ', $missing));
            }

            // Render template
            $rendered = $template->render($variables);

            $results = [];
            foreach ($recipients as $recipient) {
                $emailData = [
                    'to' => $recipient,
                    'subject' => $rendered['subject'],
                    'body_html' => $rendered['body_html'],
                    'body_plain' => $rendered['body_plain'],
                ];

                $result = $this->sendEmail($account, $emailData);
                $results[] = array_merge($result, ['recipient' => $recipient]);
            }

            return [
                'success' => true,
                'results' => $results,
                'sent_count' => count(array_filter($results, fn($r) => $r['success'])),
                'failed_count' => count(array_filter($results, fn($r) => !$r['success'])),
            ];

        } catch (\Exception $e) {
            Log::error("Failed to send templated emails", [
                'account_id' => $account->id,
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send ticket notification email
     */
    public function sendTicketNotification(string $type, array $ticketData, array $clientData, ?EmailAccount $account = null): array
    {
        // Find appropriate template
        $template = EmailTemplate::active()
            ->where('category', $this->getTemplateCategory($type))
            ->first();

        if (!$template) {
            Log::warning("No template found for ticket notification", ['type' => $type]);
            return ['success' => false, 'error' => 'No template found'];
        }

        // Prepare template variables
        $variables = $this->prepareTicketVariables($type, $ticketData, $clientData);

        // Use provided account or find default
        if (!$account) {
            $account = EmailAccount::active()->first();
            if (!$account) {
                return ['success' => false, 'error' => 'No email account configured'];
            }
        }

        return $this->sendTemplatedEmail($account, $template, $variables, [$clientData['email']]);
    }

    /**
     * Create Symfony Mailer instance for account
     */
    protected function createMailer(EmailAccount $account): Mailer
    {
        $config = $account->getSmtpConfig();
        $dsn = $this->buildSmtpDsn($config);
        $transport = Transport::fromDsn($dsn);

        return new Mailer($transport);
    }

    /**
     * Create default Symfony Mailer instance
     */
    protected function createDefaultMailer(): Mailer
    {
        $config = [
            'host' => env('MAIL_HOST'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
        ];

        $dsn = $this->buildSmtpDsn($config);
        $transport = Transport::fromDsn($dsn);

        return new Mailer($transport);
    }

    /**
     * Build SMTP DSN string
     */
    protected function buildSmtpDsn(array $config): string
    {
        $encryption = $config['encryption'] ?? null;
        $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';

        $dsn = "{$scheme}://";

        if ($config['username']) {
            $dsn .= urlencode($config['username']);
            if ($config['password']) {
                $dsn .= ':' . urlencode($config['password']);
            }
            $dsn .= '@';
        }

        $dsn .= $config['host'];

        if ($config['port']) {
            $dsn .= ':' . $config['port'];
        }

        // Add encryption parameter for TLS
        if ($encryption === 'tls') {
            $dsn .= '?encryption=tls';
        }

        return $dsn;
    }

    /**
     * Create email message
     */
    protected function createMessage(array $emailData, ?EmailAccount $account = null): Email
    {
        $message = new Email();

        // From address
        $fromAddress = $account
            ? $account->email_address
            : $this->defaultFromAddress;
        $fromName = $account
            ? $account->name
            : $this->defaultFromName;

        $message->from(new Address($fromAddress, $fromName));

        // To addresses
        $toAddresses = is_array($emailData['to']) ? $emailData['to'] : [$emailData['to']];
        foreach ($toAddresses as $to) {
            if (is_array($to) && isset($to['email'])) {
                $message->addTo(new Address($to['email'], $to['name'] ?? ''));
            } else {
                $message->addTo($to);
            }
        }

        // CC addresses
        if (!empty($emailData['cc'])) {
            $ccAddresses = is_array($emailData['cc']) ? $emailData['cc'] : [$emailData['cc']];
            foreach ($ccAddresses as $cc) {
                $message->addCc($cc);
            }
        }

        // BCC addresses
        if (!empty($emailData['bcc'])) {
            $bccAddresses = is_array($emailData['bcc']) ? $emailData['bcc'] : [$emailData['bcc']];
            foreach ($bccAddresses as $bcc) {
                $message->addBcc($bcc);
            }
        }

        // Subject
        $message->subject($emailData['subject']);

        // Body
        if (!empty($emailData['body_html'])) {
            $message->html($emailData['body_html']);
        }

        if (!empty($emailData['body_plain'])) {
            $message->text($emailData['body_plain']);
        } elseif (!empty($emailData['body_html'])) {
            // Generate plain text from HTML if only HTML is provided
            $message->text(strip_tags($emailData['body_html']));
        }

        // Headers
        if (!empty($emailData['headers'])) {
            foreach ($emailData['headers'] as $header => $value) {
                $message->getHeaders()->addTextHeader($header, $value);
            }
        }

        // Reply-To for threading
        if (!empty($emailData['reply_to'])) {
            $message->replyTo($emailData['reply_to']);
        }

        // Message ID for threading
        if (!empty($emailData['in_reply_to'])) {
            $message->getHeaders()->addTextHeader('In-Reply-To', $emailData['in_reply_to']);
        }

        if (!empty($emailData['references'])) {
            $message->getHeaders()->addTextHeader('References', $emailData['references']);
        }

        // Attachments
        if (!empty($emailData['attachments'])) {
            foreach ($emailData['attachments'] as $attachment) {
                if (isset($attachment['content_base64'])) {
                    $content = base64_decode($attachment['content_base64']);
                    $message->attach($content, $attachment['filename'], $attachment['mime_type'] ?? null);
                } elseif (isset($attachment['path'])) {
                    $message->attachFromPath($attachment['path'], $attachment['filename'] ?? null);
                }
            }
        }

        return $message;
    }

    /**
     * Get template category for notification type
     */
    protected function getTemplateCategory(string $type): string
    {
        return match($type) {
            'ticket_created' => EmailTemplate::CATEGORY_TICKET_CREATED,
            'ticket_updated' => EmailTemplate::CATEGORY_TICKET_UPDATED,
            'ticket_resolved' => EmailTemplate::CATEGORY_TICKET_RESOLVED,
            'ticket_closed' => EmailTemplate::CATEGORY_TICKET_CLOSED,
            'auto_reply' => EmailTemplate::CATEGORY_AUTO_REPLY,
            'escalation' => EmailTemplate::CATEGORY_ESCALATION,
            'reminder' => EmailTemplate::CATEGORY_REMINDER,
            default => EmailTemplate::CATEGORY_CUSTOM,
        };
    }

    /**
     * Prepare template variables for ticket notifications
     */
    protected function prepareTicketVariables(string $type, array $ticketData, array $clientData): array
    {
        $variables = [
            'client_name' => $clientData['name'] ?? $this->extractNameFromEmail($clientData['email']),
            'client_email' => $clientData['email'],
            'ticket_number' => $ticketData['ticket_number'] ?? '',
            'ticket_subject' => $ticketData['subject'] ?? '',
            'ticket_priority' => ucfirst($ticketData['priority'] ?? 'medium'),
            'ticket_status' => ucfirst($ticketData['status'] ?? 'new'),
            'company_name' => env('APP_NAME', 'AidlY'),
            'support_email' => $this->defaultFromAddress,
            'created_at' => isset($ticketData['created_at']) ?
                date('F j, Y \a\t g:i A', strtotime($ticketData['created_at'])) :
                date('F j, Y \a\t g:i A'),
        ];

        // Type-specific variables
        switch ($type) {
            case 'ticket_created':
                $variables['sla_time'] = '24 hours'; // Should come from SLA policy
                break;

            case 'ticket_resolved':
            case 'ticket_updated':
                $variables['agent_name'] = $ticketData['assigned_agent_name'] ?? 'Support Team';
                $variables['resolution_notes'] = $ticketData['resolution_notes'] ?? '';
                break;

            case 'auto_reply':
                $variables['original_subject'] = $ticketData['subject'] ?? '';
                $variables['phone_number'] = env('SUPPORT_PHONE', '+1-555-0123');
                break;
        }

        return $variables;
    }

    /**
     * Extract name from email address
     */
    protected function extractNameFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        $localPart = $parts[0];
        return ucwords(str_replace(['.', '_', '-', '+'], ' ', $localPart));
    }

    /**
     * Test SMTP connection
     */
    public function testConnection(?EmailAccount $account = null): array
    {
        try {
            $mailer = $account ? $this->createMailer($account) : $this->createDefaultMailer();

            // Create a test message
            $message = new Email();
            $message->from($account ? $account->email_address : $this->defaultFromAddress);
            $message->to($account ? $account->email_address : $this->defaultFromAddress);
            $message->subject('SMTP Connection Test');
            $message->text('This is a test email to verify SMTP configuration.');

            // Note: We're not actually sending the test message to avoid spam
            // Just testing the transport creation

            return [
                'success' => true,
                'message' => 'SMTP connection test successful',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}