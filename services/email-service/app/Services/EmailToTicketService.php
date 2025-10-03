<?php

namespace App\Services;

use App\Models\EmailQueue;
use App\Models\EmailAccount;
use App\Models\BlockedEmailAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailToTicketService
{
    protected $ticketServiceUrl;
    protected $clientServiceUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->ticketServiceUrl = env('TICKET_SERVICE_URL', 'http://localhost:8002');
        $this->clientServiceUrl = env('CLIENT_SERVICE_URL', 'http://localhost:8003');
        $this->apiKey = env('TICKET_SERVICE_API_KEY', '');
    }

    /**
     * Create HTTP client with authentication headers
     */
    protected function httpClient()
    {
        $client = Http::timeout(30);

        if ($this->apiKey) {
            $client = $client->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey]);
        }

        return $client;
    }

    /**
     * Process all pending emails in the queue
     */
    public function processAllPendingEmails(): array
    {
        $pendingEmails = EmailQueue::pending()->orderBy('received_at')->get();
        $results = [];

        foreach ($pendingEmails as $email) {
            try {
                $result = $this->processEmail($email);
                $results[] = [
                    'email_id' => $email->id,
                    'success' => true,
                    'ticket_id' => $result['ticket_id'] ?? null,
                    'action' => $result['action'] ?? 'created',
                ];
            } catch (\Exception $e) {
                Log::error("Failed to process email to ticket", [
                    'email_id' => $email->id,
                    'message_id' => $email->message_id,
                    'subject' => $email->subject,
                    'error' => $e->getMessage(),
                ]);

                $email->markAsFailed($e->getMessage());
                $results[] = [
                    'email_id' => $email->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info("Processed pending emails", [
            'total_processed' => count($results),
            'successful' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
        ]);

        return $results;
    }

    /**
     * Process individual email to create or update ticket
     */
    public function processEmail(EmailQueue $email): array
    {
        $emailAccount = $email->emailAccount;
        if (!$emailAccount || !$emailAccount->auto_create_tickets) {
            throw new \Exception("Email account not configured for auto ticket creation");
        }

        // Skip system emails (mailer-daemon, no-reply, etc.)
        if ($this->isSystemEmail($email->from_address)) {
            $email->markAsProcessed(null); // Mark as processed without creating ticket
            Log::info("Skipped system email", [
                'from' => $email->from_address,
                'subject' => $email->subject,
            ]);
            throw new \Exception("Skipped system email from: " . $email->from_address);
        }

        // Check if this is a reply to existing ticket
        $existingTicket = $this->findExistingTicket($email);

        if ($existingTicket) {
            // Add comment to existing ticket
            $result = $this->addCommentToTicket($existingTicket['id'], $email);
            $email->markAsProcessed($existingTicket['id']);
            return [
                'action' => 'commented',
                'ticket_id' => $existingTicket['id'],
                'comment_id' => $result['comment_id'] ?? null,
            ];
        } else {
            // Create new ticket
            $ticket = $this->createTicketFromEmail($email, $emailAccount);
            $email->markAsProcessed($ticket['id']);
            return [
                'action' => 'created',
                'ticket_id' => $ticket['id'],
                'ticket_number' => $ticket['ticket_number'] ?? null,
            ];
        }
    }

    /**
     * Find existing ticket by email threading or subject
     */
    protected function findExistingTicket(EmailQueue $email): ?array
    {
        // First, try to find by message threading (In-Reply-To, References)
        $inReplyTo = $email->headers['in-reply-to'] ?? null;
        $references = $email->headers['references'] ?? null;

        if ($inReplyTo || $references) {
            $ticket = $this->findTicketByMessageIds([$inReplyTo, $references]);
            if ($ticket) {
                return $ticket;
            }
        }

        // Second, try to find by subject line (ticket number pattern)
        // This handles various formats:
        // - TKT-001234
        // - [Ticket #TKT-001234]
        // - Re: TKT-001234
        // - Ticket: TKT-001234
        $subject = $email->subject;
        if (preg_match('/TKT-(\d{6})/', $subject, $matches)) {
            $ticketNumber = 'TKT-' . $matches[1];
            Log::info("Found ticket number in subject", [
                'subject' => $subject,
                'ticket_number' => $ticketNumber
            ]);
            $ticket = $this->findTicketByNumber($ticketNumber);
            if ($ticket) {
                Log::info("Matched existing ticket by number", [
                    'ticket_id' => $ticket['id'],
                    'ticket_number' => $ticketNumber
                ]);
                return $ticket;
            }
        }

        // Third, try to find recent tickets from same client
        $client = $this->findOrCreateClient($email->from_address);
        if ($client) {
            return $this->findRecentTicketByClient($client['id'], $subject);
        }

        return null;
    }

    /**
     * Find ticket by message IDs
     */
    protected function findTicketByMessageIds(array $messageIds): ?array
    {
        foreach ($messageIds as $messageId) {
            if (!$messageId) continue;

            // Clean up message ID (remove < > brackets if present)
            $cleanMessageId = trim($messageId, '<>');

            try {
                $request = Http::when($this->apiKey, function ($http) {
                    return $http->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey]);
                });

                // First, try to find by original incoming message ID
                $response = $request->get("{$this->ticketServiceUrl}/api/v1/public/tickets/by-message-id", [
                    'message_id' => $cleanMessageId
                ]);

                if ($response->successful() && $response->json('success')) {
                    Log::info("Found ticket by incoming message ID", [
                        'message_id' => $cleanMessageId,
                        'ticket_id' => $response->json('data.id')
                    ]);
                    return $response->json('data');
                }

                // Second, try to find by sent message ID (replies from agents)
                $response = $request->get("{$this->ticketServiceUrl}/api/v1/public/tickets/by-sent-message-id", [
                    'message_id' => $cleanMessageId
                ]);

                if ($response->successful() && $response->json('success')) {
                    Log::info("Found ticket by sent message ID (agent reply)", [
                        'message_id' => $cleanMessageId,
                        'ticket_id' => $response->json('data.id')
                    ]);
                    return $response->json('data');
                }
            } catch (\Exception $e) {
                Log::warning("Failed to find ticket by message ID", [
                    'message_id' => $cleanMessageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Find ticket by ticket number
     */
    protected function findTicketByNumber(string $ticketNumber): ?array
    {
        try {
            $response = Http::get("{$this->ticketServiceUrl}/api/v1/public/tickets/by-number/{$ticketNumber}");

            if ($response->successful() && $response->json('success')) {
                Log::info("Matched existing ticket by number", [
                    'ticket_number' => $ticketNumber,
                    'ticket_id' => $response->json('data.id')
                ]);
                return $response->json('data');
            }
        } catch (\Exception $e) {
            Log::warning("Failed to find ticket by number", [
                'ticket_number' => $ticketNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Find recent ticket by client and similar subject
     */
    protected function findRecentTicketByClient(string $clientId, string $subject): ?array
    {
        try {
            $response = Http::get("{$this->ticketServiceUrl}/api/v1/tickets", [
                'client_id' => $clientId,
                'status' => 'open,pending,on_hold',
                'limit' => 5,
                'sort' => '-created_at'
            ]);

            if (!$response->successful() || !$response->json('success')) {
                return null;
            }

            $tickets = $response->json('data.data', []);

            // Look for similar subjects (simplified similarity check)
            $normalizedSubject = $this->normalizeSubject($subject);

            foreach ($tickets as $ticket) {
                $ticketSubject = $this->normalizeSubject($ticket['subject']);
                if (similar_text($normalizedSubject, $ticketSubject) / strlen($normalizedSubject) > 0.7) {
                    return $ticket;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to find recent tickets by client", [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Normalize subject line for comparison
     */
    protected function normalizeSubject(string $subject): string
    {
        // Remove common reply prefixes and ticket numbers
        $subject = preg_replace('/^(Re:|Fwd?:|AW:|RE:)\s*/i', '', $subject);
        $subject = preg_replace('/\bTKT-\d{6}\b/', '', $subject);
        return trim(strtolower($subject));
    }

    /**
     * Create new ticket from email
     */
    protected function createTicketFromEmail(EmailQueue $email, EmailAccount $emailAccount): array
    {
        // First, try to find existing client (don't create yet)
        $client = $this->findClient($email->from_address);
        $clientId = $client ? $client['id'] : null;
        $isNewClient = !$client;

        // CHECK: If client is blocked, send notification and reject ticket creation
        if ($client && isset($client['is_blocked']) && $client['is_blocked'] === true) {
            // Send auto-reply notification to blocked sender
            $notificationSent = $this->sendBlockedSenderNotification($email->from_address, $email->subject);

            // Log blocked attempt to database
            $this->logBlockedEmailAttempt([
                'client_id' => $client['id'],
                'email_address' => $email->from_address,
                'client_name' => $client['name'] ?? null,
                'subject' => $email->subject,
                'email_queue_id' => $email->id,
                'message_id' => $email->message_id,
                'notification_sent' => $notificationSent,
                'block_reason' => 'Client is blocked in system',
                'metadata' => [
                    'action' => 'new_ticket_blocked',
                    'from_address' => $email->from_address,
                    'to_addresses' => $email->to_addresses,
                    'email_account_id' => $email->email_account_id,
                ]
            ]);

            // Mark email as processed (no ticket created)
            $email->markAsProcessed(null);

            Log::warning("Blocked email from blocked client - Auto-reply sent", [
                'client_id' => $client['id'],
                'client_email' => $email->from_address,
                'client_name' => $client['name'] ?? 'Unknown',
                'subject' => $email->subject,
                'email_id' => $email->id,
                'blocked_reason' => 'Client is blocked in system',
                'notification_sent' => $notificationSent
            ]);

            throw new \Exception("Email sender is blocked: " . $email->from_address);
        }

        // Enhanced description extraction with multiple fallbacks (same as comment logic)
        \Log::info('Email body extraction for new ticket', [
            'email_id' => $email->id,
            'body_html_length' => strlen($email->body_html ?? ''),
            'body_plain_length' => strlen($email->body_plain ?? ''),
            'content_length' => strlen($email->content ?? ''),
            'has_attachments' => $email->hasAttachments(),
        ]);

        $description = null;

        if (!empty($email->body_html)) {
            $description = $this->cleanEmailContent($email->body_html, true);
        } elseif (!empty($email->body_plain)) {
            $description = $this->cleanEmailContent($email->body_plain, false);
        } elseif (!empty($email->content)) {
            $description = $this->cleanEmailContent($email->content, false);
        }

        // Final fallbacks if all sources are empty
        if (empty($description)) {
            if ($email->hasAttachments()) {
                $attachmentCount = is_array($email->attachments) ? count($email->attachments) : 0;
                $description = "[Email received with {$attachmentCount} attachment(s)]";
                Log::warning("Email has no text content, only attachments - creating ticket anyway", [
                    'email_id' => $email->id,
                    'from' => $email->from_address,
                    'attachment_count' => $attachmentCount,
                ]);
            } else {
                // Last resort: use subject
                $description = "[No readable content - possible email parsing error]";
                Log::error("Email has no content and no attachments, using fallback", [
                    'email_id' => $email->id,
                    'from' => $email->from_address,
                    'subject' => $email->subject,
                ]);
            }
        }

        // Prepare ticket data
        $ticketData = [
            'subject' => $email->subject,
            'description' => $description,
            'client_id' => $clientId,
            'client_email' => $email->from_address, // Pass email for ticket service to handle client creation
            'source' => 'email',
            'priority' => $emailAccount->default_ticket_priority ?: 'medium',
            'category_id' => $emailAccount->default_category_id,
            'assigned_department_id' => $emailAccount->department_id,
            'custom_fields' => [
                'email_message_id' => $email->message_id,
                'email_account_id' => $email->email_account_id,
                'original_from' => $email->from_address,
                'original_to' => $email->to_addresses,
                'original_cc' => $email->cc_addresses,
                'has_attachments' => $email->hasAttachments(),
            ],
        ];

        // Create ticket via API
        $headers = [];
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $response = Http::withHeaders($headers)->post("{$this->ticketServiceUrl}/api/v1/public/tickets", $ticketData);

        if (!$response->successful()) {
            throw new \Exception("Failed to create ticket: " . $response->body());
        }

        $ticketResponse = $response->json();
        if (!$ticketResponse['success']) {
            throw new \Exception("Ticket creation failed: " . ($ticketResponse['message'] ?? 'Unknown error'));
        }

        $ticket = $ticketResponse['data'];

        // Only create client if ticket was successfully created and client didn't exist
        if ($isNewClient && !$clientId) {
            try {
                $createdClient = $this->createClient($email->from_address);
                if ($createdClient) {
                    Log::info("Created client after successful ticket creation", [
                        'client_id' => $createdClient['id'],
                        'email' => $email->from_address,
                        'ticket_id' => $ticket['id'],
                    ]);
                }
            } catch (\Exception $e) {
                // Log but don't fail - ticket is already created
                Log::warning("Failed to create client after ticket creation", [
                    'email' => $email->from_address,
                    'ticket_id' => $ticket['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Process attachments if any
        if ($email->hasAttachments()) {
            $this->processEmailAttachments($email, $ticket['id']);
        }

        Log::info("Created ticket from email", [
            'email_id' => $email->id,
            'ticket_id' => $ticket['id'],
            'ticket_number' => $ticket['ticket_number'],
            'client_email' => $email->from_address,
            'subject' => $email->subject,
        ]);

        return $ticket;
    }

    /**
     * Add comment to existing ticket
     */
    protected function addCommentToTicket(string $ticketId, EmailQueue $email): array
    {
        // CHECK: If client is blocked, send notification and reject comment creation
        $client = $this->findClient($email->from_address);
        if ($client && isset($client['is_blocked']) && $client['is_blocked'] === true) {
            // Send auto-reply notification to blocked sender
            $notificationSent = $this->sendBlockedSenderNotification($email->from_address, $email->subject);

            // Log blocked attempt to database
            $this->logBlockedEmailAttempt([
                'client_id' => $client['id'],
                'email_address' => $email->from_address,
                'client_name' => $client['name'] ?? null,
                'subject' => $email->subject,
                'email_queue_id' => $email->id,
                'message_id' => $email->message_id,
                'notification_sent' => $notificationSent,
                'block_reason' => 'Client is blocked - Reply to ticket rejected',
                'metadata' => [
                    'action' => 'ticket_reply_blocked',
                    'ticket_id' => $ticketId,
                    'from_address' => $email->from_address,
                    'to_addresses' => $email->to_addresses,
                    'email_account_id' => $email->email_account_id,
                ]
            ]);

            // Mark email as processed (no comment created)
            $email->markAsProcessed($ticketId);

            Log::warning("Blocked email reply from blocked client - Auto-reply sent", [
                'client_id' => $client['id'],
                'client_email' => $email->from_address,
                'client_name' => $client['name'] ?? 'Unknown',
                'ticket_id' => $ticketId,
                'subject' => $email->subject,
                'email_id' => $email->id,
                'blocked_reason' => 'Client is blocked - Reply rejected',
                'notification_sent' => $notificationSent
            ]);

            throw new \Exception("Email sender is blocked - Reply rejected: " . $email->from_address);
        }

        // Enhanced content extraction with multiple fallbacks
        \Log::info('Email body extraction for ticket comment', [
            'email_id' => $email->id,
            'body_html_length' => strlen($email->body_html ?? ''),
            'body_plain_length' => strlen($email->body_plain ?? ''),
            'content_length' => strlen($email->content ?? ''),
            'has_attachments' => $email->hasAttachments(),
        ]);

        // Try multiple sources in order of preference:
        // 1. HTML body (best for formatting)
        // 2. Plain text body
        // 3. Processed content field
        // 4. Fallback messages
        $content = null;

        if (!empty($email->body_html)) {
            $content = $this->cleanEmailContent($email->body_html, true);
        } elseif (!empty($email->body_plain)) {
            $content = $this->cleanEmailContent($email->body_plain, false);
        } elseif (!empty($email->content)) {
            $content = $this->cleanEmailContent($email->content, false);
        }

        // Final fallbacks if all sources are empty
        if (empty($content)) {
            if ($email->hasAttachments()) {
                $attachmentCount = is_array($email->attachments) ? count($email->attachments) : 0;
                $content = "[Email received with {$attachmentCount} attachment(s)]";
                \Log::warning('Email has no text content, only attachments', [
                    'email_id' => $email->id,
                    'attachment_count' => $attachmentCount,
                ]);
            } else {
                $content = "[Email received with no readable content]";
                \Log::error('Email has no content and no attachments', [
                    'email_id' => $email->id,
                    'from' => $email->from_address,
                    'subject' => $email->subject,
                ]);
            }
        }

        \Log::info('Final comment content prepared', [
            'email_id' => $email->id,
            'content_length' => strlen($content),
            'preview' => substr(strip_tags($content), 0, 100),
        ]);

        $commentData = [
            'content' => $content,
            'is_internal_note' => false,
            // Email metadata for Gmail-style display
            'from_address' => $email->from_address,
            'to_addresses' => $email->to_addresses,
            'cc_addresses' => $email->cc_addresses,
            'subject' => $email->subject,
            'body_html' => $email->body_html,
            'body_plain' => $email->body_plain,
            'headers' => $email->headers,
            'metadata' => [
                'email_message_id' => $email->message_id,
                'email_account_id' => $email->email_account_id,
                'from_email' => $email->from_address,
                'has_attachments' => $email->hasAttachments(),
            ],
        ];

        // Include attachments if present
        if ($email->hasAttachments()) {
            $commentData['attachments'] = $email->attachments;
        }

        $response = Http::post("{$this->ticketServiceUrl}/api/v1/public/tickets/{$ticketId}/comments", $commentData);

        if (!$response->successful()) {
            throw new \Exception("Failed to add comment to ticket: " . $response->body());
        }

        $commentResponse = $response->json();
        if (!$commentResponse['success']) {
            throw new \Exception("Comment creation failed: " . ($commentResponse['message'] ?? 'Unknown error'));
        }

        $comment = $commentResponse['data'];

        // Process attachments if any
        if ($email->hasAttachments()) {
            $this->processEmailAttachments($email, $ticketId, $comment['id']);
        }

        Log::info("Added comment from email to ticket", [
            'email_id' => $email->id,
            'ticket_id' => $ticketId,
            'comment_id' => $comment['id'],
            'from_email' => $email->from_address,
        ]);

        return $comment;
    }

    /**
     * Find existing client by email address
     */
    protected function findClient(string $email): ?array
    {
        try {
            $response = Http::get("{$this->clientServiceUrl}/api/v1/clients", [
                'email' => $email,
                'limit' => 1
            ]);

            if ($response->successful() && $response->json('success')) {
                $clients = $response->json('data', []);
                if (!empty($clients)) {
                    return $clients[0];
                }
            }
        } catch (\Exception $e) {
            Log::error("Error finding client", [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Create new client
     */
    protected function createClient(string $email): ?array
    {
        try {
            $clientData = [
                'email' => $email,
                'name' => $this->extractNameFromEmail($email),
            ];

            $response = Http::post("{$this->clientServiceUrl}/api/v1/clients", $clientData);

            if ($response->successful() && $response->json('success')) {
                return $response->json('data');
            }

            Log::error("Failed to create client", [
                'email' => $email,
                'response' => $response->body(),
            ]);

        } catch (\Exception $e) {
            Log::error("Error creating client", [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Find or create client by email address (legacy method for compatibility)
     */
    protected function findOrCreateClient(string $email): ?array
    {
        $client = $this->findClient($email);
        if ($client) {
            return $client;
        }

        return $this->createClient($email);
    }

    /**
     * Check if email is from a system/automated sender
     */
    protected function isSystemEmail(string $email): bool
    {
        $email = strtolower(trim($email));

        // List of system email patterns to skip
        $systemPatterns = [
            'mailer-daemon@',
            'postmaster@',
            'no-reply@',
            'noreply@',
            'do-not-reply@',
            'donotreply@',
            'bounce@',
            'bounces@',
            'notification@',
            'notifications@',
            'automated@',
            'daemon@',
            'system@',
        ];

        foreach ($systemPatterns as $pattern) {
            if (strpos($email, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract name from email address
     */
    protected function extractNameFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        $localPart = $parts[0];

        // Replace common separators with spaces and capitalize
        $name = ucwords(str_replace(['.', '_', '-', '+'], ' ', $localPart));

        return $name;
    }

    /**
     * Process email attachments and upload them
     */
    protected function processEmailAttachments(EmailQueue $email, string $ticketId, ?string $commentId = null): void
    {
        if (!$email->hasAttachments()) {
            return;
        }

        foreach ($email->attachments as $attachment) {
            try {
                $attachmentData = [
                    'ticket_id' => $ticketId,
                    'comment_id' => $commentId,
                    'file_name' => $attachment['filename'],
                    'file_type' => $attachment['extension'],
                    'file_size' => $attachment['size'],
                    'mime_type' => $attachment['mime_type'],
                    'is_inline' => $attachment['is_inline'],
                    'content_base64' => $attachment['content_base64'],
                ];

                $response = Http::post("{$this->ticketServiceUrl}/api/v1/public/attachments", $attachmentData);

                if (!$response->successful()) {
                    Log::error("Failed to upload attachment", [
                        'ticket_id' => $ticketId,
                        'filename' => $attachment['filename'],
                        'error' => $response->body(),
                    ]);
                    continue;
                }

                Log::debug("Uploaded attachment", [
                    'ticket_id' => $ticketId,
                    'comment_id' => $commentId,
                    'filename' => $attachment['filename'],
                    'size' => $attachment['size'],
                ]);

            } catch (\Exception $e) {
                Log::error("Error processing attachment", [
                    'ticket_id' => $ticketId,
                    'filename' => $attachment['filename'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send notification email to blocked sender
     *
     * @param string $recipientEmail
     * @param string $originalSubject
     * @return bool Success status
     */
    protected function sendBlockedSenderNotification(string $recipientEmail, string $originalSubject): bool
    {
        try {
            // Get company/app name from env
            $companyName = env('APP_NAME', 'AidlY Support');
            $supportEmail = env('MAIL_FROM_ADDRESS', 'support@aidly.com');

            // Prepare email content
            $subject = "Message Delivery Failed - Account Restricted";

            $htmlBody = $this->getBlockedSenderEmailTemplate($companyName, $originalSubject, $supportEmail);

            $plainBody = $this->getBlockedSenderPlainText($companyName, $originalSubject, $supportEmail);

            // Send email using Symfony Mailer
            $mailer = app('mailer');

            $message = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($supportEmail, $companyName))
                ->to($recipientEmail)
                ->subject($subject)
                ->html($htmlBody)
                ->text($plainBody);

            $mailer->send($message);

            Log::info("Sent blocked sender notification", [
                'recipient' => $recipientEmail,
                'subject' => $subject,
                'original_subject' => $originalSubject
            ]);

            return true;

        } catch (\Exception $e) {
            // Log error but don't throw - we don't want to fail the entire process
            Log::error("Failed to send blocked sender notification", [
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Log blocked email attempt to database
     *
     * @param array $data
     * @return void
     */
    protected function logBlockedEmailAttempt(array $data): void
    {
        try {
            BlockedEmailAttempt::create($data);

            Log::debug("Logged blocked email attempt", [
                'client_id' => $data['client_id'] ?? null,
                'email_address' => $data['email_address'],
                'subject' => $data['subject']
            ]);
        } catch (\Exception $e) {
            // Log error but don't throw
            Log::error("Failed to log blocked email attempt", [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }

    /**
     * Get HTML email template for blocked sender notification
     *
     * @param string $companyName
     * @param string $originalSubject
     * @param string $supportEmail
     * @return string
     */
    protected function getBlockedSenderEmailTemplate(string $companyName, string $originalSubject, string $supportEmail): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Delivery Failed</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 40px 40px 30px; border-radius: 8px 8px 0 0;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <div style="background-color: rgba(255,255,255,0.2); border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="display: block; margin: 16px auto;">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                            </svg>
                                        </div>
                                        <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;">
                                            Message Delivery Failed
                                        </h1>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <div style="color: #374151; font-size: 16px; line-height: 24px;">
                                <p style="margin: 0 0 20px;">
                                    Your message could not be delivered to <strong>{$companyName}</strong> support team.
                                </p>

                                <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 16px 20px; margin: 24px 0; border-radius: 4px;">
                                    <p style="margin: 0 0 8px; color: #991b1b; font-weight: 600; font-size: 14px;">
                                        ⚠️ Account Restricted
                                    </p>
                                    <p style="margin: 0; color: #7f1d1d; font-size: 14px; line-height: 20px;">
                                        Your account has been restricted from submitting support requests. This means we cannot process emails sent from your address.
                                    </p>
                                </div>

                                <div style="background-color: #f9fafb; padding: 16px 20px; border-radius: 6px; margin: 24px 0;">
                                    <p style="margin: 0 0 8px; font-weight: 600; font-size: 14px; color: #6b7280;">
                                        Original Subject:
                                    </p>
                                    <p style="margin: 0; color: #1f2937; font-size: 14px;">
                                        {$originalSubject}
                                    </p>
                                </div>

                                <h3 style="color: #111827; font-size: 18px; font-weight: 600; margin: 32px 0 16px;">
                                    Why was my message blocked?
                                </h3>
                                <p style="margin: 0 0 12px; color: #6b7280; font-size: 15px; line-height: 22px;">
                                    Your account may have been restricted for one of the following reasons:
                                </p>
                                <ul style="margin: 12px 0 24px; padding-left: 24px; color: #6b7280; font-size: 15px; line-height: 22px;">
                                    <li style="margin-bottom: 8px;">Violation of our terms of service or acceptable use policy</li>
                                    <li style="margin-bottom: 8px;">Repeated abusive or inappropriate communications</li>
                                    <li style="margin-bottom: 8px;">Outstanding payment or account issues</li>
                                    <li style="margin-bottom: 8px;">Request from account administrator</li>
                                </ul>

                                <h3 style="color: #111827; font-size: 18px; font-weight: 600; margin: 32px 0 16px;">
                                    What can I do?
                                </h3>
                                <p style="margin: 0 0 16px; color: #6b7280; font-size: 15px; line-height: 22px;">
                                    If you believe this restriction was made in error or would like to discuss having it removed, please contact our account management team directly:
                                </p>

                                <div style="background-color: #eff6ff; border: 1px solid #bfdbfe; padding: 20px; border-radius: 6px; margin: 24px 0;">
                                    <p style="margin: 0 0 12px; color: #1e40af; font-size: 14px;">
                                        <strong>📧 Email:</strong> <a href="mailto:{$supportEmail}" style="color: #2563eb; text-decoration: none;">{$supportEmail}</a>
                                    </p>
                                    <p style="margin: 0; color: #1e40af; font-size: 13px;">
                                        Please include your account details and the reason you're contacting us.
                                    </p>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px 40px; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #9ca3af; font-size: 13px; line-height: 18px; text-align: center;">
                                This is an automated message from <strong>{$companyName}</strong>.<br>
                                Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Additional Notice -->
                <table width="600" cellpadding="0" cellspacing="0" style="margin-top: 20px;">
                    <tr>
                        <td style="padding: 0 20px;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 18px; text-align: center;">
                                © {$companyName}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Get plain text version of blocked sender notification
     *
     * @param string $companyName
     * @param string $originalSubject
     * @param string $supportEmail
     * @return string
     */
    protected function getBlockedSenderPlainText(string $companyName, string $originalSubject, string $supportEmail): string
    {
        return <<<TEXT
MESSAGE DELIVERY FAILED
=======================

Your message could not be delivered to {$companyName} support team.

ACCOUNT RESTRICTED
------------------
Your account has been restricted from submitting support requests. This means we cannot process emails sent from your address.

Original Subject: {$originalSubject}

WHY WAS MY MESSAGE BLOCKED?
---------------------------
Your account may have been restricted for one of the following reasons:

• Violation of our terms of service or acceptable use policy
• Repeated abusive or inappropriate communications
• Outstanding payment or account issues
• Request from account administrator

WHAT CAN I DO?
--------------
If you believe this restriction was made in error or would like to discuss having it removed, please contact our account management team directly:

Email: stu2101681026@uni-plovdiv.bg

Please include your account details and the reason you're contacting us.

---
This is an automated message from {$companyName}.
Please do not reply directly to this email.

© {$companyName}. All rights reserved.
TEXT;
    }

    /**
     * Clean email content for customer support display
     * Removes signatures, quoted replies, disclaimers, and email artifacts
     *
     * @param string $content The email content
     * @param bool $isHtml Whether the content is HTML
     * @return string Cleaned content
     */
    protected function cleanEmailContent(string $content, bool $isHtml = false): string
    {
        if (empty($content)) {
            return '';
        }

        // If HTML, convert to plain text first
        if ($isHtml) {
            // Remove style and script tags with their content
            $content = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content);
            $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);

            // Convert <br> and <p> to newlines
            $content = preg_replace('/<br\s*\/?>/', "\n", $content);
            $content = preg_replace('/<\/p>/', "\n\n", $content);

            // Strip all HTML tags
            $content = strip_tags($content);

            // Decode HTML entities
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Split into lines for processing
        $lines = explode("\n", $content);
        $cleanedLines = [];
        $signatureFound = false;
        $quotedReplyFound = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Stop at common email signatures
            if (preg_match('/^(--|__)\s*$/', $trimmedLine) ||
                preg_match('/^(Best regards|Kind regards|Thanks|Regards|Sincerely|Cheers|Best|BR|Thank you),?\s*$/i', $trimmedLine)) {
                $signatureFound = true;
                break;
            }

            // Stop at "On [date] [person] wrote:" patterns
            if (preg_match('/^On .+ wrote:$/i', $trimmedLine) ||
                preg_match('/^[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{2,4}.+wrote:$/i', $trimmedLine) ||
                preg_match('/^\d{4}-\d{2}-\d{2}.+wrote:$/i', $trimmedLine)) {
                $quotedReplyFound = true;
                break;
            }

            // Skip lines starting with > (quoted text)
            if (preg_match('/^>+/', $trimmedLine)) {
                $quotedReplyFound = true;
                continue;
            }

            // Skip common email disclaimers
            if (preg_match('/^(CONFIDENTIAL|DISCLAIMER|This email|The information contained)/i', $trimmedLine)) {
                break;
            }

            // Skip "Sent from my iPhone/Android" etc
            if (preg_match('/^Sent from my (iPhone|iPad|Android|Samsung|Mobile)/i', $trimmedLine)) {
                break;
            }

            // Skip empty lines if we haven't found content yet
            if (empty($cleanedLines) && empty($trimmedLine)) {
                continue;
            }

            $cleanedLines[] = $line;
        }

        // Join lines back together
        $cleaned = implode("\n", $cleanedLines);

        // Remove excessive whitespace
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned);
        $cleaned = preg_replace('/[ \t]{2,}/', ' ', $cleaned);

        // Trim
        $cleaned = trim($cleaned);

        return $cleaned;
    }
}