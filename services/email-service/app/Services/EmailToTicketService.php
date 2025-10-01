<?php

namespace App\Services;

use App\Models\EmailQueue;
use App\Models\EmailAccount;
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

        // Prepare description - prefer HTML body over plain text, with fallback
        $description = $email->body_html ?: $email->body_plain ?: $email->content;

        // If description is empty, use subject as description (for system emails like mailer-daemon)
        if (empty(trim($description))) {
            $description = $email->subject;
            Log::warning("Email has empty body, using subject as description", [
                'email_id' => $email->id,
                'from' => $email->from_address,
                'subject' => $email->subject,
            ]);
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
        // Prefer HTML body over plain text for comments as well
        $commentData = [
            'content' => $email->body_html ?: $email->body_plain ?: $email->content,
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

                $response = Http::post("{$this->ticketServiceUrl}/api/v1/attachments", $attachmentData);

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
}