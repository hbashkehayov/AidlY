<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TicketReplyEmailService
{
    protected $sharedMailboxSmtpService;

    public function __construct(SharedMailboxSmtpService $sharedMailboxSmtpService)
    {
        $this->sharedMailboxSmtpService = $sharedMailboxSmtpService;
    }

    /**
     * Send ticket reply as email to client
     */
    public function sendReplyEmail(array $ticketData, array $commentData, array $clientData): array
    {
        // Get user info for the person who replied
        $userInfo = $this->getUserInfo($commentData['user_id']);

        // Prepare reply data for SharedMailboxSmtpService
        $replyData = [
            'ticket_id' => $ticketData['id'],
            'ticket_number' => $ticketData['ticket_number'] ?? null,
            'subject' => $ticketData['subject'],
            'content' => $commentData['content'],
            'agent' => [
                'name' => $userInfo['name'] ?? 'Support Agent',
                'email' => $userInfo['email'] ?? null,
                'department' => $userInfo['department'] ?? 'Customer Support',
            ],
            'recipient' => [
                'email' => $clientData['email'],
                'name' => $clientData['name'] ?? null,
            ],
            'mailbox_address' => $this->getPreferredMailboxForTicket($ticketData),
            'original_message_id' => $ticketData['metadata']['email_message_id'] ?? null,
            'original_recipient' => $ticketData['metadata']['original_recipient'] ?? null,
        ];

        // Send email through shared mailbox service
        $result = $this->sharedMailboxSmtpService->sendTicketReply($replyData);

        // Log the sent email
        $this->logSentEmail($ticketData, $commentData, $clientData, $result);

        return $result;
    }

    /**
     * Send ticket status change email
     */
    public function sendStatusChangeEmail(array $ticketData, array $clientData): array
    {
        $subject = $this->buildStatusChangeSubject($ticketData);
        $emailContent = $this->buildStatusChangeEmailContent($ticketData, $clientData);

        // Prepare notification data for SharedMailboxSmtpService
        $notificationData = [
            'type' => 'status_change',
            'subject' => $subject,
            'content' => $emailContent['html'],
            'recipients' => [
                [
                    'email' => $clientData['email'],
                    'name' => $clientData['name'] ?? null,
                ]
            ],
            'mailbox_address' => $this->getPreferredMailboxForTicket($ticketData),
        ];

        // Send through shared mailbox notification system
        $result = $this->sharedMailboxSmtpService->sendNotification($notificationData);

        Log::info('Status change email sent', [
            'ticket_id' => $ticketData['id'],
            'status' => $ticketData['status'],
            'client_email' => $clientData['email'],
        ]);

        return $result;
    }

    /**
     * Get preferred mailbox address for sending replies
     */
    protected function getPreferredMailboxForTicket(array $ticketData): ?string
    {
        // Try to use original recipient address from metadata
        if (!empty($ticketData['metadata']['original_recipient'])) {
            return $ticketData['metadata']['original_recipient'];
        }

        // Try to find by original email account if stored in metadata
        if (!empty($ticketData['metadata']['email_account_id'])) {
            $account = EmailAccount::find($ticketData['metadata']['email_account_id']);
            if ($account && $account->is_active && $account->isSharedMailbox()) {
                return $account->email_address;
            }
        }

        // Try to find by department if ticket has assigned department
        if (!empty($ticketData['assigned_department_id'])) {
            $account = EmailAccount::where('department_id', $ticketData['assigned_department_id'])
                ->where('is_active', true)
                ->where('account_type', 'shared_mailbox')
                ->first();
            if ($account) {
                return $account->email_address;
            }
        }

        // Fallback to support@softart.bg specifically (as configured in your setup)
        $supportAccount = EmailAccount::sharedMailboxes()
            ->where('email_address', 'support@softart.bg')
            ->first();
        if ($supportAccount) {
            return $supportAccount->email_address;
        }

        // Final fallback to any shared mailbox
        $defaultSharedMailbox = EmailAccount::sharedMailboxes()->first();
        return $defaultSharedMailbox ? $defaultSharedMailbox->email_address : null;
    }

    /**
     * Get user information from auth service
     */
    protected function getUserInfo(string $userId): array
    {
        try {
            $response = Http::get(env('AUTH_SERVICE_URL', 'http://localhost:8001') . "/api/v1/users/{$userId}");

            if ($response->successful() && $response->json('success')) {
                return $response->json('data');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch user info', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'name' => 'Support Agent',
            'email' => 'noreply@support.com'
        ];
    }

    /**
     * Build reply subject with ticket number
     */
    protected function buildReplySubject(string $originalSubject, ?string $ticketNumber): string
    {
        // Check if subject already contains ticket number
        if ($ticketNumber && strpos($originalSubject, $ticketNumber) === false) {
            return "Re: [{$ticketNumber}] {$originalSubject}";
        }

        // If no ticket number or already contains it, just add Re:
        if (!str_starts_with(strtolower($originalSubject), 're:')) {
            return "Re: {$originalSubject}";
        }

        return $originalSubject;
    }

    /**
     * Build status change subject
     */
    protected function buildStatusChangeSubject(array $ticketData): string
    {
        $ticketNumber = $ticketData['ticket_number'] ?? '';
        $status = ucfirst($ticketData['status']);
        $subject = $ticketData['subject'];

        return "[{$ticketNumber}] Ticket {$status}: {$subject}";
    }

    /**
     * Build reply email content
     */
    protected function buildReplyEmailContent(array $commentData, array $ticketData, array $userInfo, array $clientData): array
    {
        $agentName = $userInfo['name'] ?? 'Support Agent';
        $replyContent = $commentData['content'];
        $ticketNumber = $ticketData['ticket_number'] ?? 'N/A';
        $clientName = $clientData['name'] ?? 'Customer';

        // Plain text version
        $plainContent = "Hello {$clientName},\n\n";
        $plainContent .= "{$agentName} has replied to your support ticket:\n\n";
        $plainContent .= "Ticket: {$ticketNumber}\n";
        $plainContent .= "Subject: {$ticketData['subject']}\n\n";
        $plainContent .= "Reply:\n" . strip_tags($replyContent) . "\n\n";
        $plainContent .= "You can view and respond to this ticket at: " . env('APP_URL') . "/tickets/{$ticketData['id']}\n\n";
        $plainContent .= "Best regards,\n{$agentName}\nSupport Team";

        // HTML version
        $htmlContent = $this->buildHtmlEmailTemplate([
            'client_name' => $clientName,
            'agent_name' => $agentName,
            'ticket_number' => $ticketNumber,
            'ticket_subject' => $ticketData['subject'],
            'reply_content' => $replyContent,
            'ticket_url' => env('APP_URL') . "/tickets/{$ticketData['id']}",
        ]);

        return [
            'plain' => $plainContent,
            'html' => $htmlContent,
        ];
    }

    /**
     * Build status change email content
     */
    protected function buildStatusChangeEmailContent(array $ticketData, array $clientData): array
    {
        $clientName = $clientData['name'] ?? 'Customer';
        $ticketNumber = $ticketData['ticket_number'] ?? 'N/A';
        $status = ucfirst($ticketData['status']);
        $previousStatus = ucfirst($ticketData['previous_status'] ?? '');

        $plainContent = "Hello {$clientName},\n\n";
        $plainContent .= "Your support ticket status has been updated:\n\n";
        $plainContent .= "Ticket: {$ticketNumber}\n";
        $plainContent .= "Subject: {$ticketData['subject']}\n";
        $plainContent .= "Status: {$previousStatus} → {$status}\n\n";

        if ($ticketData['status'] === 'resolved') {
            $plainContent .= "Your issue has been resolved. If you need further assistance, please reply to this email.\n\n";
        } elseif ($ticketData['status'] === 'closed') {
            $plainContent .= "This ticket has been closed. If you need to reopen it, please reply to this email.\n\n";
        }

        $plainContent .= "You can view this ticket at: " . env('APP_URL') . "/tickets/{$ticketData['id']}\n\n";
        $plainContent .= "Best regards,\nSupport Team";

        $htmlContent = $this->buildStatusChangeHtmlTemplate([
            'client_name' => $clientName,
            'ticket_number' => $ticketNumber,
            'ticket_subject' => $ticketData['subject'],
            'status' => $status,
            'previous_status' => $previousStatus,
            'ticket_url' => env('APP_URL') . "/tickets/{$ticketData['id']}",
        ]);

        return [
            'plain' => $plainContent,
            'html' => $htmlContent,
        ];
    }

    /**
     * Build HTML email template for replies
     */
    protected function buildHtmlEmailTemplate(array $data): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket Reply</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .ticket-info { background: #e9ecef; padding: 10px; border-radius: 3px; margin: 10px 0; }
        .reply-content { background: #fff; border-left: 4px solid #007bff; padding: 15px; margin: 15px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Support Ticket Reply</h2>
        </div>

        <p>Hello ' . htmlspecialchars($data['client_name']) . ',</p>

        <p>' . htmlspecialchars($data['agent_name']) . ' has replied to your support ticket:</p>

        <div class="ticket-info">
            <strong>Ticket:</strong> ' . htmlspecialchars($data['ticket_number']) . '<br>
            <strong>Subject:</strong> ' . htmlspecialchars($data['ticket_subject']) . '
        </div>

        <div class="reply-content">
            <h4>Reply from ' . htmlspecialchars($data['agent_name']) . ':</h4>
            ' . $data['reply_content'] . '
        </div>

        <p>
            <a href="' . htmlspecialchars($data['ticket_url']) . '" class="btn">View Ticket</a>
        </p>

        <div class="footer">
            <p>Best regards,<br>' . htmlspecialchars($data['agent_name']) . '<br>Support Team</p>
            <p>You can reply directly to this email to continue the conversation.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Build HTML email template for status changes
     */
    protected function buildStatusChangeHtmlTemplate(array $data): string
    {
        $statusColor = match($data['status']) {
            'Resolved' => '#28a745',
            'Closed' => '#6c757d',
            'Open' => '#007bff',
            'Pending' => '#ffc107',
            default => '#007bff'
        };

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Status Update</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .status-change { background: ' . $statusColor . '; color: white; padding: 15px; border-radius: 5px; text-align: center; margin: 15px 0; }
        .ticket-info { background: #e9ecef; padding: 10px; border-radius: 3px; margin: 10px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Ticket Status Update</h2>
        </div>

        <p>Hello ' . htmlspecialchars($data['client_name']) . ',</p>

        <p>Your support ticket status has been updated:</p>

        <div class="ticket-info">
            <strong>Ticket:</strong> ' . htmlspecialchars($data['ticket_number']) . '<br>
            <strong>Subject:</strong> ' . htmlspecialchars($data['ticket_subject']) . '
        </div>

        <div class="status-change">
            <h3>' . htmlspecialchars($data['previous_status']) . ' → ' . htmlspecialchars($data['status']) . '</h3>
        </div>

        <p>
            <a href="' . htmlspecialchars($data['ticket_url']) . '" class="btn">View Ticket</a>
        </p>

        <div class="footer">
            <p>Best regards,<br>Support Team</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Build email headers for proper threading
     */
    protected function buildReplyHeaders(array $ticketData): array
    {
        $headers = [];

        // Add Message-ID for tracking
        $headers['Message-ID'] = '<ticket-' . $ticketData['id'] . '-' . time() . '@' . parse_url(env('APP_URL'), PHP_URL_HOST) . '>';

        // Add In-Reply-To and References for threading
        if (!empty($ticketData['metadata']['email_message_id'])) {
            $headers['In-Reply-To'] = $ticketData['metadata']['email_message_id'];
            $headers['References'] = $ticketData['metadata']['email_message_id'];
        }

        return $headers;
    }

    /**
     * Log sent email for tracking
     */
    protected function logSentEmail(array $ticketData, array $commentData, array $clientData, array $result): void
    {
        Log::info('Ticket reply email sent', [
            'ticket_id' => $ticketData['id'],
            'ticket_number' => $ticketData['ticket_number'] ?? null,
            'comment_id' => $commentData['id'] ?? null,
            'client_email' => $clientData['email'],
            'user_id' => $commentData['user_id'],
            'success' => $result['success'] ?? false,
            'message_id' => $result['message_id'] ?? null,
        ]);
    }
}