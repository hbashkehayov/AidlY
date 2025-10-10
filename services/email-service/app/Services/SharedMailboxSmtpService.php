<?php

namespace App\Services;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

/**
 * Shared Mailbox SMTP Service
 * Handles sending emails through shared mailboxes with agent identity formatting
 * Example: "Alice (AidlY Support) <support@company.com>"
 */
class SharedMailboxSmtpService
{
    protected $defaultConfig;

    public function __construct()
    {
        $this->defaultConfig = [
            'connection_timeout' => env('EMAIL_SMTP_TIMEOUT', 30),
            'max_recipients' => env('EMAIL_MAX_RECIPIENTS', 50),
            'rate_limit_per_minute' => env('EMAIL_RATE_LIMIT', 60),
            'retry_attempts' => env('EMAIL_RETRY_ATTEMPTS', 3),
            'retry_delay' => env('EMAIL_RETRY_DELAY', 5), // seconds
        ];
    }

    /**
     * Send reply through shared mailbox with agent formatting
     */
    public function sendTicketReply(array $replyData): array
    {
        $ticketId = $replyData['ticket_id'];
        $agentData = $replyData['agent'] ?? [];
        $recipientData = $replyData['recipient'] ?? [];
        $content = $replyData['content'] ?? '';
        $subject = $replyData['subject'] ?? '';
        $mailboxAddress = $replyData['mailbox_address'] ?? null;

        // Validate required data
        if (empty($ticketId) || empty($content) || empty($recipientData['email'])) {
            throw new \InvalidArgumentException('Missing required reply data: ticket_id, content, or recipient email');
        }

        // Find appropriate shared mailbox
        $mailbox = $this->findMailboxForReply($mailboxAddress, $replyData);
        if (!$mailbox) {
            throw new \Exception('No suitable shared mailbox found for sending reply');
        }

        Log::info('Sending ticket reply through shared mailbox', [
            'ticket_id' => $ticketId,
            'mailbox_address' => $mailbox->email_address,
            'recipient' => $recipientData['email'],
            'agent' => $agentData['name'] ?? 'System'
        ]);

        try {
            // Format the email with agent identity
            $emailData = $this->formatAgentReply($content, $agentData, $mailbox, $replyData);

            // Create and send email
            $result = $this->sendEmail([
                'mailbox' => $mailbox,
                'to' => [
                    'email' => $recipientData['email'],
                    'name' => $recipientData['name'] ?? null
                ],
                'subject' => $this->formatReplySubject($subject, $ticketId),
                'body_html' => $emailData['html_body'],
                'body_plain' => $emailData['plain_body'],
                'headers' => $this->buildReplyHeaders($replyData),
                'agent_data' => $agentData,
                'ticket_id' => $ticketId,
            ]);

            Log::info('Successfully sent ticket reply', [
                'ticket_id' => $ticketId,
                'message_id' => $result['message_id'] ?? null,
                'mailbox_used' => $mailbox->email_address
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to send ticket reply through shared mailbox', [
                'ticket_id' => $ticketId,
                'mailbox_address' => $mailbox->email_address,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send notification email through shared mailbox
     */
    public function sendNotification(array $notificationData): array
    {
        $type = $notificationData['type'] ?? 'general';
        $recipients = $notificationData['recipients'] ?? [];
        $subject = $notificationData['subject'] ?? '';
        $content = $notificationData['content'] ?? '';
        $mailboxAddress = $notificationData['mailbox_address'] ?? null;

        if (empty($recipients) || empty($subject) || empty($content)) {
            throw new \InvalidArgumentException('Missing required notification data');
        }

        // Find appropriate mailbox
        $mailbox = $this->findMailboxForNotification($mailboxAddress, $type);
        if (!$mailbox) {
            throw new \Exception('No suitable shared mailbox found for sending notification');
        }

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($recipients as $recipient) {
            try {
                $result = $this->sendEmail([
                    'mailbox' => $mailbox,
                    'to' => $recipient,
                    'subject' => $subject,
                    'body_html' => $content,
                    'body_plain' => strip_tags($content),
                    'notification_type' => $type,
                ]);

                $results[] = [
                    'recipient' => $recipient['email'],
                    'success' => true,
                    'message_id' => $result['message_id'] ?? null
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'recipient' => $recipient['email'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $failureCount++;

                Log::error('Failed to send notification to recipient', [
                    'recipient' => $recipient['email'],
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Completed notification sending', [
            'type' => $type,
            'total_recipients' => count($recipients),
            'successful' => $successCount,
            'failed' => $failureCount
        ]);

        return [
            'success' => $successCount > 0,
            'total_sent' => $successCount,
            'total_failed' => $failureCount,
            'results' => $results
        ];
    }

    /**
     * Core email sending method
     */
    protected function sendEmail(array $emailData): array
    {
        $mailbox = $emailData['mailbox'];
        $to = $emailData['to'];
        $subject = $emailData['subject'];
        $bodyHtml = $emailData['body_html'] ?? '';
        $bodyPlain = $emailData['body_plain'] ?? '';
        $headers = $emailData['headers'] ?? [];
        $agentData = $emailData['agent_data'] ?? [];

        // Create SMTP transport
        $transport = $this->createSmtpTransport($mailbox);
        $mailer = new Mailer($transport);

        // Create email message
        $email = new Email();

        // Set sender with agent formatting
        $fromAddress = $this->formatSenderAddress($mailbox, $agentData);
        $email->from($fromAddress);

        // Set recipient
        if (is_array($to)) {
            $toAddress = new Address($to['email'], $to['name'] ?? '');
        } else {
            $toAddress = new Address($to);
        }
        $email->to($toAddress);

        // Set subject
        $email->subject($subject);

        // Set body content
        if (!empty($bodyHtml)) {
            $email->html($bodyHtml);
        }
        if (!empty($bodyPlain)) {
            $email->text($bodyPlain);
        }

        // Add custom headers
        $customMessageId = null;
        foreach ($headers as $name => $value) {
            if (!empty($value)) {
                // Check if Message-ID was provided in custom headers
                if (strtolower($name) === 'message-id') {
                    $customMessageId = $value;
                }
                $email->getHeaders()->addTextHeader($name, $value);
            }
        }

        // Add tracking Message-ID (use custom one if provided, otherwise generate)
        if ($customMessageId) {
            $messageId = $customMessageId;
            $email->getHeaders()->addIdHeader('Message-ID', $messageId);
        } else {
            $messageId = $this->generateMessageId($mailbox->email_address);
            $email->getHeaders()->addIdHeader('Message-ID', $messageId);
        }

        // Set reply-to to the shared mailbox
        $email->replyTo(new Address($mailbox->email_address, $mailbox->name));

        // Send the email
        $result = $mailer->send($email);

        return [
            'success' => true,
            'message_id' => $messageId,
            'mailbox_used' => $mailbox->email_address,
            'recipient' => $to['email'] ?? $to,
            'subject' => $subject
        ];
    }

    /**
     * Format agent reply with proper structure and signature
     */
    protected function formatAgentReply(string $content, array $agentData, EmailAccount $mailbox, array $replyData): array
    {
        $agentName = $agentData['name'] ?? 'Support Team';
        $agentDepartment = $agentData['department'] ?? 'Customer Support';

        // Generate agent signature
        $signature = $mailbox->generateAgentSignature([
            'name' => $agentName,
            'email' => $agentData['email'] ?? $mailbox->email_address,
            'department' => $agentDepartment,
        ]);

        // Format HTML body
        $htmlBody = $this->buildHtmlReply($content, $signature, $replyData);

        // Format plain text body
        $plainBody = $this->buildPlainReply($content, $signature, $replyData);

        return [
            'html_body' => $htmlBody,
            'plain_body' => $plainBody
        ];
    }

    /**
     * Build HTML formatted reply
     */
    protected function buildHtmlReply(string $content, string $signature, array $replyData): string
    {
        // Convert plain text content to HTML if needed
        if (strip_tags($content) === $content) {
            $content = nl2br(htmlspecialchars($content));
        }

        $html = "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title>Reply</title>\n</head>\n<body style=\"font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0;\">\n";

        // Container
        $html .= "    <div style=\"max-width: 600px; margin: 20px auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);\">\n";

        // Add reply content
        $html .= "        <div style=\"margin: 20px 0;\">\n";
        $html .= "            {$content}\n";
        $html .= "        </div>\n";

        // Add signature
        $html .= "        <div style=\"margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; color: #555;\">\n";
        $html .= "            <p style=\"margin: 0;\">" . nl2br(htmlspecialchars($signature)) . "</p>\n";
        $html .= "        </div>\n";

        // Close container
        $html .= "    </div>\n";

        $html .= "</body>\n</html>";

        return $html;
    }

    /**
     * Build plain text reply
     */
    protected function buildPlainReply(string $content, string $signature, array $replyData): string
    {
        // Strip HTML if present
        $content = strip_tags($content);

        $plain = $content . "\n\n";
        $plain .= $signature;

        return $plain;
    }

    /**
     * Format sender address with agent identity
     */
    protected function formatSenderAddress(EmailAccount $mailbox, array $agentData): Address
    {
        $agentName = $agentData['name'] ?? 'Support Team';
        $mailboxName = $mailbox->name ?? 'AidlY Support';

        // Format as "Agent Name (Company Support)"
        $displayName = "{$agentName} ({$mailboxName})";

        return new Address($mailbox->email_address, $displayName);
    }

    /**
     * Format reply subject with ticket number
     */
    protected function formatReplySubject(string $originalSubject, string $ticketId): string
    {
        // Remove any existing Re: or Fwd: prefixes
        $subject = preg_replace('/^(Re:|Fwd?:|AW:)\s*/i', '', $originalSubject);

        // Remove existing ticket numbers
        $subject = preg_replace('/\s*\[?TKT-\d{6}\]?\s*/', '', $subject);

        // Add our ticket number and Re: prefix
        return "Re: [TKT-{$ticketId}] {$subject}";
    }

    /**
     * Build reply headers for proper email threading
     */
    protected function buildReplyHeaders(array $replyData): array
    {
        $headers = [];

        // Set Message-ID for email threading (if provided)
        if (!empty($replyData['message_id'])) {
            $headers['Message-ID'] = $replyData['message_id'];
        }

        // Threading headers
        if (!empty($replyData['original_message_id'])) {
            $headers['In-Reply-To'] = $replyData['original_message_id'];
            $headers['References'] = $replyData['original_message_id'];
        }

        // Add thread references if available
        if (!empty($replyData['thread_references'])) {
            $headers['References'] = $replyData['thread_references'] . ' ' . ($replyData['original_message_id'] ?? '');
        }

        // Ticket tracking headers
        $headers['X-AidlY-Ticket-ID'] = $replyData['ticket_id'] ?? '';
        $headers['X-AidlY-Thread-Type'] = 'ticket-reply';

        // Auto-reply detection prevention
        $headers['Auto-Submitted'] = 'auto-generated';

        return array_filter($headers);
    }

    /**
     * Create SMTP transport for shared mailbox
     */
    protected function createSmtpTransport(EmailAccount $mailbox): EsmtpTransport
    {
        $smtpConfig = $mailbox->getSmtpConfig();

        // For Gmail SMTP on port 587, we need STARTTLS (not direct TLS)
        $transport = new EsmtpTransport(
            $smtpConfig['host'],
            $smtpConfig['port'],
            false  // Don't use direct TLS, Gmail uses STARTTLS
        );

        $transport->setUsername($smtpConfig['username']);
        $transport->setPassword($smtpConfig['password']);

        return $transport;
    }

    /**
     * Find appropriate mailbox for sending reply
     */
    protected function findMailboxForReply(?string $preferredMailbox, array $replyData): ?EmailAccount
    {
        // If specific mailbox requested, use it
        if (!empty($preferredMailbox)) {
            $mailbox = EmailAccount::sharedMailboxes()
                ->where('email_address', $preferredMailbox)
                ->first();
            if ($mailbox) {
                return $mailbox;
            }
        }

        // Find mailbox based on original recipient
        if (!empty($replyData['original_recipient'])) {
            $mailbox = EmailAccount::sharedMailboxes()
                ->where('email_address', $replyData['original_recipient'])
                ->first();
            if ($mailbox) {
                return $mailbox;
            }
        }

        // Find mailbox based on department
        if (!empty($replyData['department_id'])) {
            $mailbox = EmailAccount::sharedMailboxes()
                ->where('department_id', $replyData['department_id'])
                ->first();
            if ($mailbox) {
                return $mailbox;
            }
        }

        // Fallback to default shared mailbox
        return EmailAccount::sharedMailboxes()->first();
    }

    /**
     * Find appropriate mailbox for notifications
     */
    protected function findMailboxForNotification(?string $preferredMailbox, string $notificationType): ?EmailAccount
    {
        // If specific mailbox requested, use it
        if (!empty($preferredMailbox)) {
            $mailbox = EmailAccount::sharedMailboxes()
                ->where('email_address', $preferredMailbox)
                ->first();
            if ($mailbox) {
                return $mailbox;
            }
        }

        // Find mailbox based on notification type
        $typeToMailboxMap = [
            'billing' => ['billing@', 'accounts@'],
            'sales' => ['sales@', 'info@'],
            'technical' => ['support@', 'tech@'],
            'general' => ['info@', 'hello@', 'support@'],
        ];

        if (isset($typeToMailboxMap[$notificationType])) {
            foreach ($typeToMailboxMap[$notificationType] as $pattern) {
                $mailbox = EmailAccount::sharedMailboxes()
                    ->where('email_address', 'LIKE', $pattern . '%')
                    ->first();
                if ($mailbox) {
                    return $mailbox;
                }
            }
        }

        // Fallback to default
        return EmailAccount::sharedMailboxes()->first();
    }

    /**
     * Generate unique message ID
     */
    protected function generateMessageId(string $domain): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $hostname = parse_url($domain, PHP_URL_HOST) ?: $domain;

        return "<aidly.{$timestamp}.{$random}@{$hostname}>";
    }

    /**
     * Test SMTP connection for shared mailbox
     */
    public function testSmtpConnection(EmailAccount $mailbox): array
    {
        if (!$mailbox->isSharedMailbox()) {
            return [
                'success' => false,
                'error' => 'invalid_type',
                'message' => 'Account is not configured as a shared mailbox',
            ];
        }

        try {
            $transport = $this->createSmtpTransport($mailbox);

            // Test connection
            $transport->start();
            $transport->stop();

            return [
                'success' => true,
                'message' => 'SMTP connection successful',
                'mailbox_info' => [
                    'address' => $mailbox->email_address,
                    'smtp_host' => $mailbox->smtp_host,
                    'smtp_port' => $mailbox->smtp_port,
                    'encryption' => $mailbox->smtp_use_tls ? 'TLS' : 'None',
                ],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'smtp_test_failed',
                'message' => 'SMTP connection failed: ' . $e->getMessage(),
            ];
        }
    }
}