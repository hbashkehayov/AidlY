<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailQueue;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Illuminate\Support\Facades\Log;

/**
 * Enhanced IMAP service for shared mailboxes
 * Centralizes email fetching from shared accounts like support@company.com
 */
class SharedMailboxImapService
{
    protected $clientManager;
    protected $config;

    public function __construct()
    {
        $this->clientManager = new ClientManager();
        $this->config = [
            'max_attachments' => env('EMAIL_MAX_ATTACHMENTS', 5),
            'max_attachment_size' => env('EMAIL_MAX_ATTACHMENT_SIZE', 10485760), // 10MB
            'allowed_attachment_types' => explode(',', env('EMAIL_ALLOWED_ATTACHMENT_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip')),
            'fetch_limit' => env('EMAIL_FETCH_LIMIT_PER_ACCOUNT', 50), // Prevent memory issues
            'connection_timeout' => env('EMAIL_CONNECTION_TIMEOUT', 30),
        ];
    }

    /**
     * Fetch emails from all active shared mailboxes
     */
    public function fetchFromAllSharedMailboxes(): array
    {
        $results = [];
        $sharedMailboxes = EmailAccount::sharedMailboxes()->get();

        if ($sharedMailboxes->isEmpty()) {
            Log::warning('No active shared mailboxes configured');
            return [
                'success' => false,
                'message' => 'No shared mailboxes found',
                'results' => []
            ];
        }

        Log::info('Starting email fetch from shared mailboxes', [
            'mailbox_count' => $sharedMailboxes->count()
        ]);

        foreach ($sharedMailboxes as $mailbox) {
            try {
                $result = $this->fetchFromSharedMailbox($mailbox);
                $results[] = [
                    'mailbox_id' => $mailbox->id,
                    'mailbox_name' => $mailbox->name,
                    'mailbox_address' => $mailbox->email_address,
                    'success' => true,
                    'emails_fetched' => $result['count'],
                    'new_emails' => $result['new_count'],
                    'duplicates_skipped' => $result['duplicates'],
                    'errors' => $result['errors'],
                ];
            } catch (\Exception $e) {
                Log::error("Failed to fetch emails from shared mailbox", [
                    'mailbox_id' => $mailbox->id,
                    'mailbox_name' => $mailbox->name,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'mailbox_id' => $mailbox->id,
                    'mailbox_name' => $mailbox->name,
                    'mailbox_address' => $mailbox->email_address,
                    'success' => false,
                    'emails_fetched' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalFetched = array_sum(array_column($results, 'emails_fetched'));
        $successfulMailboxes = count(array_filter($results, fn($r) => $r['success']));

        Log::info('Completed email fetch from shared mailboxes', [
            'total_emails_fetched' => $totalFetched,
            'successful_mailboxes' => $successfulMailboxes,
            'total_mailboxes' => count($results),
        ]);

        return [
            'success' => true,
            'total_fetched' => $totalFetched,
            'results' => $results
        ];
    }

    /**
     * Fetch emails from a specific shared mailbox
     */
    public function fetchFromSharedMailbox(EmailAccount $mailbox): array
    {
        if (!$mailbox->isSharedMailbox()) {
            throw new \InvalidArgumentException("Email account '{$mailbox->name}' is not configured as a shared mailbox");
        }

        Log::info("Fetching emails from shared mailbox", [
            'mailbox_name' => $mailbox->name,
            'mailbox_address' => $mailbox->email_address
        ]);

        $client = $this->createImapClient($mailbox);

        try {
            $client->connect();
            $this->validateConnection($client, $mailbox);

            // Get INBOX folder
            $folder = $client->getFolder('INBOX');

            // Fetch only unseen emails to avoid reprocessing
            $messages = $folder->messages()
                ->unseen()
                ->limit($this->config['fetch_limit'])
                ->get();

            $processedCount = 0;
            $newCount = 0;
            $duplicateCount = 0;
            $errors = [];

            Log::debug("Found unseen messages in shared mailbox", [
                'mailbox_name' => $mailbox->name,
                'message_count' => $messages->count()
            ]);

            foreach ($messages as $message) {
                try {
                    $result = $this->processSharedMailboxMessage($message, $mailbox);

                    if ($result['action'] === 'created') {
                        $newCount++;
                    } elseif ($result['action'] === 'duplicate_skipped') {
                        $duplicateCount++;
                    }

                    $processedCount++;

                    // Mark as seen to prevent reprocessing
                    $message->setFlag('Seen');

                } catch (\Exception $e) {
                    $errors[] = [
                        'message_id' => $message->getMessageId() ?: 'unknown',
                        'subject' => $message->getSubject() ?: 'No subject',
                        'from' => $this->extractFromAddress($message),
                        'error' => $e->getMessage(),
                    ];

                    Log::error("Failed to process message from shared mailbox", [
                        'mailbox_name' => $mailbox->name,
                        'message_id' => $message->getMessageId(),
                        'subject' => $message->getSubject(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $client->disconnect();
            $mailbox->updateLastSync();

            Log::info("Successfully processed shared mailbox emails", [
                'mailbox_name' => $mailbox->name,
                'total_messages' => $messages->count(),
                'processed' => $processedCount,
                'new_emails' => $newCount,
                'duplicates' => $duplicateCount,
                'errors' => count($errors),
            ]);

            return [
                'count' => $processedCount,
                'new_count' => $newCount,
                'duplicates' => $duplicateCount,
                'errors' => $errors,
                'total_messages' => $messages->count(),
            ];

        } catch (\Exception $e) {
            if (isset($client)) {
                try {
                    $client->disconnect();
                } catch (\Exception $disconnectError) {
                    Log::warning("Failed to disconnect IMAP client", [
                        'mailbox_name' => $mailbox->name,
                        'error' => $disconnectError->getMessage()
                    ]);
                }
            }
            throw $e;
        }
    }

    /**
     * Create and configure IMAP client for shared mailbox
     */
    protected function createImapClient(EmailAccount $mailbox): Client
    {
        $imapConfig = $mailbox->getImapConfig();

        $config = [
            'host' => $imapConfig['host'],
            'port' => $imapConfig['port'],
            'encryption' => $imapConfig['encryption'],
            'validate_cert' => false, // Changed to false for Gmail compatibility
            'username' => $imapConfig['username'],
            'password' => $imapConfig['password'],
            'protocol' => 'imap',
            'authentication' => 'login', // Added explicit authentication method
            'options' => [
                'delimiter' => '/',
                'fetch' => \Webklex\PHPIMAP\IMAP::FT_PEEK, // Changed to FT_PEEK to not mark as read
                'fetch_body' => true,
                'fetch_flags' => true,
                'soft_expunge' => false,
                'debug' => env('EMAIL_DEBUG', false),
                'decoder' => [
                    'message' => 'utf-8',
                    'attachment' => 'utf-8'
                ],
                'events' => [],
                'masks' => []
            ]
        ];

        return $this->clientManager->make($config);
    }

    /**
     * Validate IMAP connection
     */
    protected function validateConnection(Client $client, EmailAccount $mailbox): void
    {
        try {
            $folders = $client->getFolders();
            if ($folders->isEmpty()) {
                throw new \Exception("No folders found in mailbox");
            }
        } catch (\Exception $e) {
            throw new \Exception("Connection validation failed for {$mailbox->name}: " . $e->getMessage());
        }
    }

    /**
     * Process individual message from shared mailbox
     */
    protected function processSharedMailboxMessage(Message $message, EmailAccount $mailbox): array
    {
        $messageId = $message->getMessageId();

        // Enhanced duplicate detection
        if ($this->isDuplicateMessage($messageId, $mailbox->id)) {
            Log::debug("Skipping duplicate message from shared mailbox", [
                'mailbox_name' => $mailbox->name,
                'message_id' => $messageId,
                'subject' => $message->getSubject(),
            ]);

            return ['action' => 'duplicate_skipped'];
        }

        // Extract comprehensive email data
        $emailData = $this->extractSharedMailboxEmailData($message, $mailbox);

        // Apply routing rules if configured
        $emailData = $this->applyRoutingRules($emailData, $mailbox);

        // Create email queue entry
        $emailQueue = new EmailQueue($emailData);
        $emailQueue->save();

        Log::debug("Email queued from shared mailbox", [
            'queue_id' => $emailQueue->id,
            'mailbox_name' => $mailbox->name,
            'message_id' => $messageId,
            'subject' => $message->getSubject(),
            'from' => $emailData['from_address'],
            'routed_department' => $emailData['routed_department_id'] ?? null,
        ]);

        return [
            'action' => 'created',
            'queue_id' => $emailQueue->id
        ];
    }

    /**
     * Enhanced duplicate detection
     */
    protected function isDuplicateMessage(string $messageId, string $mailboxId): bool
    {
        if (empty($messageId)) {
            return false;
        }

        return EmailQueue::where('message_id', $messageId)
            ->where('email_account_id', $mailboxId)
            ->exists();
    }

    /**
     * Extract email data with shared mailbox context
     */
    protected function extractSharedMailboxEmailData(Message $message, EmailAccount $mailbox): array
    {
        // Extract addresses
        $fromAddresses = $message->getFrom();
        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc();

        $from = $fromAddresses->first();
        $fromAddress = $from ? $from->mail : null;
        $fromName = $from ? $from->personal : null;

        // Process recipient addresses
        $toArray = [];
        foreach ($toAddresses as $to) {
            $toArray[] = $to->mail;
        }

        $ccArray = [];
        foreach ($ccAddresses as $cc) {
            $ccArray[] = $cc->mail;
        }

        // Extract and clean email content using improved methods
        $bodyHtml = $message->getHTMLBody();
        $bodyPlain = $message->getTextBody();

        Log::debug('Initial shared mailbox body extraction', [
            'message_id' => $message->getMessageId(),
            'html_length' => strlen($bodyHtml ?? ''),
            'plain_length' => strlen($bodyPlain ?? ''),
        ]);

        // Check if we need improved extraction (same logic as ImapService)
        if ($this->needsImprovedExtraction($bodyHtml, $bodyPlain, $message)) {
            Log::info('Using improved extraction for shared mailbox email', [
                'message_id' => $message->getMessageId(),
                'mailbox' => $mailbox->name,
            ]);

            $extractedHtml = null;
            $extractedPlain = null;

            // Use the same improved extraction method
            $this->extractBodiesFromParts($message, $extractedHtml, $extractedPlain);

            if (!empty($extractedPlain) || !empty($extractedHtml)) {
                $bodyPlain = $extractedPlain ?: $bodyPlain;
                $bodyHtml = $extractedHtml ?: $bodyHtml;

                Log::info('Improved extraction results for shared mailbox', [
                    'html_length' => strlen($bodyHtml ?? ''),
                    'plain_length' => strlen($bodyPlain ?? ''),
                ]);
            }
        }

        // Clean the body content to remove attachment IDs
        $bodyHtml = $this->cleanBodyContent($bodyHtml);
        $bodyPlain = $this->cleanBodyContent($bodyPlain);

        // Process attachments with shared mailbox context
        $attachments = $this->processSharedMailboxAttachments($message, $mailbox);

        // Convert inline images (CID references) to base64 data URIs
        if ($bodyHtml && !empty($attachments)) {
            $bodyHtml = $this->embedInlineImages($bodyHtml, $attachments, $message);
        }

        // Prefer plain text, fallback to HTML converted to text
        $content = $bodyPlain;
        if (empty($content) && !empty($bodyHtml)) {
            $content = strip_tags($bodyHtml);
        }

        // Extract email headers for threading
        $headers = $this->extractMessageHeaders($message);

        // Parse date
        $receivedAt = $this->parseEmailDate($message->getDate());

        return [
            'email_account_id' => $mailbox->id,
            'message_id' => $message->getMessageId(),
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'to_addresses' => $toArray,
            'cc_addresses' => $ccArray,
            'subject' => $message->getSubject(),
            'body_plain' => $bodyPlain,
            'body_html' => $bodyHtml,
            'content' => $content, // Processed content for tickets
            'headers' => $headers,
            'attachments' => $attachments,
            'received_at' => $receivedAt,
            'is_processed' => false,
            'retry_count' => 0,
            'mailbox_type' => 'shared',
            'original_recipient' => $mailbox->email_address, // Track which shared mailbox received this
        ];
    }

    /**
     * Apply routing rules based on shared mailbox configuration
     */
    protected function applyRoutingRules(array $emailData, EmailAccount $mailbox): array
    {
        $routingRules = $mailbox->getRoutingRules();

        if (empty($routingRules)) {
            return $emailData;
        }

        foreach ($routingRules as $rule) {
            if ($this->matchesRoutingRule($emailData, $rule)) {
                $emailData['routed_department_id'] = $rule['department_id'] ?? null;
                $emailData['routed_category_id'] = $rule['category_id'] ?? null;
                $emailData['routed_priority'] = $rule['priority'] ?? null;
                $emailData['routing_reason'] = $rule['name'] ?? 'Auto-routed';

                Log::debug("Applied routing rule to email", [
                    'rule_name' => $rule['name'] ?? 'unnamed',
                    'department_id' => $rule['department_id'] ?? null,
                    'subject' => $emailData['subject']
                ]);
                break;
            }
        }

        return $emailData;
    }

    /**
     * Check if email matches routing rule
     */
    protected function matchesRoutingRule(array $emailData, array $rule): bool
    {
        // Subject-based routing
        if (!empty($rule['subject_contains'])) {
            foreach ((array)$rule['subject_contains'] as $keyword) {
                if (stripos($emailData['subject'], $keyword) !== false) {
                    return true;
                }
            }
        }

        // Sender-based routing
        if (!empty($rule['from_domain'])) {
            $fromDomain = substr(strrchr($emailData['from_address'], "@"), 1);
            if (in_array($fromDomain, (array)$rule['from_domain'])) {
                return true;
            }
        }

        // Recipient-based routing (which shared mailbox alias was used)
        if (!empty($rule['to_addresses'])) {
            foreach ((array)$rule['to_addresses'] as $address) {
                if (in_array($address, $emailData['to_addresses'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Process attachments for shared mailbox
     */
    protected function processSharedMailboxAttachments(Message $message, EmailAccount $mailbox): array
    {
        $attachments = [];
        $attachmentCollection = $message->getAttachments();

        if ($attachmentCollection->count() > $this->config['max_attachments']) {
            Log::warning("Email has too many attachments, processing first {$this->config['max_attachments']}", [
                'mailbox_name' => $mailbox->name,
                'message_id' => $message->getMessageId(),
                'attachment_count' => $attachmentCollection->count(),
            ]);
        }

        $processedCount = 0;
        foreach ($attachmentCollection as $attachment) {
            if ($processedCount >= $this->config['max_attachments']) {
                break;
            }

            try {
                $fileName = $attachment->getName() ?: 'unknown_file';
                $fileSize = $attachment->getSize();
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Validate attachment
                if (!$this->isValidAttachment($fileName, $fileSize, $extension, $mailbox)) {
                    continue;
                }

                // Get attachment content
                $content = $attachment->getContent();
                $base64Content = base64_encode($content);

                // Get content ID for inline images
                $contentId = $attachment->getContentId();
                if ($contentId) {
                    $contentId = trim($contentId, '<>');
                }

                $attachments[] = [
                    'filename' => $fileName,
                    'size' => $fileSize,
                    'mime_type' => $attachment->getMimeType(),
                    'extension' => $extension,
                    'content_base64' => $base64Content,
                    'is_inline' => !empty($contentId),
                    'content_id' => $contentId,
                    'processed_by_mailbox' => $mailbox->email_address,
                ];

                $processedCount++;

            } catch (\Exception $e) {
                Log::error("Failed to process attachment from shared mailbox", [
                    'mailbox_name' => $mailbox->name,
                    'message_id' => $message->getMessageId(),
                    'filename' => $attachment->getName() ?: 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $attachments;
    }

    /**
     * Validate attachment against shared mailbox policies
     */
    protected function isValidAttachment(string $fileName, int $fileSize, string $extension, EmailAccount $mailbox): bool
    {
        // Check file size
        if ($fileSize > $this->config['max_attachment_size']) {
            Log::warning("Attachment too large, skipping", [
                'mailbox_name' => $mailbox->name,
                'filename' => $fileName,
                'size' => $fileSize,
                'max_size' => $this->config['max_attachment_size'],
            ]);
            return false;
        }

        // Check file type
        if (!in_array($extension, $this->config['allowed_attachment_types'])) {
            Log::warning("Attachment type not allowed, skipping", [
                'mailbox_name' => $mailbox->name,
                'filename' => $fileName,
                'extension' => $extension,
                'allowed_types' => $this->config['allowed_attachment_types'],
            ]);
            return false;
        }

        return true;
    }

    /**
     * Extract message headers for threading
     */
    protected function extractMessageHeaders(Message $message): array
    {
        $date = $this->parseEmailDate($message->getDate());

        return [
            'message-id' => $message->getMessageId(),
            'in-reply-to' => $message->getInReplyTo(),
            'references' => $message->getReferences(),
            'date' => $date,
            'priority' => $message->getPriority(),
            'thread-topic' => $message->getHeader('thread-topic'),
            'auto-submitted' => $message->getHeader('auto-submitted'), // Detect auto-replies
        ];
    }

    /**
     * Parse email date to standardized format
     */
    protected function parseEmailDate($date): string
    {
        if (empty($date)) {
            return date('Y-m-d H:i:s');
        }

        try {
            if (is_object($date) && method_exists($date, 'format')) {
                return $date->format('Y-m-d H:i:s');
            } elseif (is_string($date)) {
                return date('Y-m-d H:i:s', strtotime($date));
            } else {
                return date('Y-m-d H:i:s', strtotime((string)$date));
            }
        } catch (\Exception $e) {
            Log::warning("Failed to parse email date", [
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            return date('Y-m-d H:i:s');
        }
    }

    /**
     * Extract sender address safely
     */
    protected function extractFromAddress(Message $message): string
    {
        $fromAddresses = $message->getFrom();
        $from = $fromAddresses->first();
        return $from ? $from->mail : 'unknown@unknown.com';
    }

    /**
     * Test connection to shared mailbox
     */
    public function testSharedMailboxConnection(EmailAccount $mailbox): array
    {
        if (!$mailbox->isSharedMailbox()) {
            return [
                'success' => false,
                'error' => 'invalid_type',
                'message' => 'Account is not configured as a shared mailbox',
            ];
        }

        try {
            $client = $this->createImapClient($mailbox);
            $client->connect();

            // Validate connection and get folder info
            $folders = $client->getFolders();
            $inbox = $client->getFolder('INBOX');
            $messageCount = $inbox->messages()->count();
            $unreadCount = $inbox->messages()->unseen()->count();

            $client->disconnect();

            return [
                'success' => true,
                'message' => 'Connection successful',
                'mailbox_info' => [
                    'address' => $mailbox->email_address,
                    'folders' => $folders->pluck('name')->toArray(),
                    'total_messages' => $messageCount,
                    'unread_messages' => $unreadCount,
                    'last_sync' => $mailbox->last_sync_at?->format('Y-m-d H:i:s'),
                ],
            ];

        } catch (ConnectionFailedException $e) {
            return [
                'success' => false,
                'error' => 'connection_failed',
                'message' => 'Cannot connect to IMAP server: ' . $e->getMessage(),
            ];
        } catch (AuthFailedException $e) {
            return [
                'success' => false,
                'error' => 'auth_failed',
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'unknown_error',
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if body content needs improved extraction (same as ImapService)
     */
    protected function needsImprovedExtraction(?string $bodyHtml, ?string $bodyPlain, Message $message): bool
    {
        // If both are empty or null - use improved extraction
        if (empty($bodyHtml) && empty($bodyPlain)) {
            return true;
        }

        // If either body is very short (< 10 chars), likely failed extraction
        $htmlLength = strlen($bodyHtml ?? '');
        $plainLength = strlen($bodyPlain ?? '');
        if (($htmlLength > 0 && $htmlLength < 10) || ($plainLength > 0 && $plainLength < 10)) {
            return true;
        }

        // Check for suspicious patterns
        $combined = ($bodyHtml ?? '') . ($bodyPlain ?? '');

        // Pattern 1: Attachment content IDs
        if (preg_match('/\b[a-f0-9]{32}\b/i', $combined)) {
            return true;
        }

        // Pattern 2: MIME boundary markers
        if (preg_match('/--[=_-]{10,}/i', $combined)) {
            return true;
        }

        return false;
    }

    /**
     * Extract bodies from message parts (same as ImapService)
     */
    protected function extractBodiesFromParts(Message $message, &$bodyHtml, &$bodyPlain)
    {
        try {
            $structure = $message->getStructure();
            if (!$structure) {
                return;
            }

            $parts = $this->getAllMessageParts($message);

            foreach ($parts as $partInfo) {
                $mimeType = strtolower($partInfo['type']);
                $disposition = strtolower($partInfo['disposition'] ?? '');
                $isInline = $partInfo['is_inline'] ?? false;

                // Skip attachments but NOT inline parts
                if ($disposition === 'attachment' && !$isInline) {
                    continue;
                }

                try {
                    $content = $message->getBodyText($partInfo['section']);
                    if (empty($content)) {
                        continue;
                    }

                    // Clean the content immediately
                    $content = $this->cleanBodyContent($content);
                    if (empty($content)) {
                        continue;
                    }

                    // Assign content based on MIME type
                    if ($mimeType === 'text/plain' && empty($bodyPlain)) {
                        $bodyPlain = $content;
                    } elseif ($mimeType === 'text/html' && empty($bodyHtml)) {
                        $bodyHtml = $content;
                    }

                    if (!empty($bodyHtml) && !empty($bodyPlain)) {
                        break;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to extract part content in shared mailbox', [
                        'section' => $partInfo['section'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract bodies from parts in shared mailbox', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all message parts with metadata (same as ImapService)
     */
    protected function getAllMessageParts(Message $message, $structure = null, $section = '1', &$parts = []): array
    {
        if ($structure === null) {
            $structure = $message->getStructure();
            $parts = [];
        }

        if (!$structure) {
            return $parts;
        }

        // Get MIME type
        $type = 'text/plain';
        if (isset($structure->subtype)) {
            $type = strtolower($structure->type ?? 'text') . '/' . strtolower($structure->subtype);
        }

        // Get disposition
        $disposition = null;
        $isInline = false;

        if (isset($structure->disposition)) {
            $disposition = strtolower($structure->disposition);
            if ($disposition === 'inline') {
                $isInline = true;
                if (strpos($type, 'text/') === 0) {
                    $disposition = null;
                }
            }
        } elseif (isset($structure->ifdisposition) && $structure->ifdisposition) {
            $disposition = 'attachment';
        }

        if (strpos($type, 'text/') === 0 && empty($disposition)) {
            $isInline = true;
        }

        $parts[] = [
            'section' => $section,
            'type' => $type,
            'disposition' => $disposition,
            'is_inline' => $isInline,
        ];

        // Recursively process sub-parts
        if (isset($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $index => $subPart) {
                $subSection = $section . '.' . ($index + 1);
                $this->getAllMessageParts($message, $subPart, $subSection, $parts);
            }
        }

        return $parts;
    }

    /**
     * Clean body content from attachment IDs (same as ImapService)
     */
    protected function cleanBodyContent(?string $content): ?string
    {
        if (empty($content)) {
            return $content;
        }

        $originalLength = strlen($content);

        // Remove attachment ID patterns
        $content = preg_replace('/\b[a-f0-9]{32,}\b/i', '', $content);
        $content = preg_replace('/^[a-f0-9]{24,}\s*$/m', '', $content);

        // Remove MIME boundary markers
        $content = preg_replace('/--[=_-]{10,}.*/m', '', $content);

        // Remove Content headers that leaked through
        $content = preg_replace('/^Content-Type:.*$/mi', '', $content);
        $content = preg_replace('/^Content-Transfer-Encoding:.*$/mi', '', $content);
        $content = preg_replace('/^Content-Disposition:.*$/mi', '', $content);

        // Remove excessive whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = preg_replace('/[ \t]{2,}/', ' ', $content);
        $content = trim($content);

        // If content is now too short, return null
        $cleanedTextOnly = strip_tags($content);
        if (strlen($cleanedTextOnly) < 3) {
            return null;
        }

        return $content;
    }

    /**
     * Embed inline images in HTML by converting CID references to base64 data URIs
     */
    protected function embedInlineImages(string $html, array $attachments, Message $message): string
    {
        // Use the attachments array we already extracted
        foreach ($attachments as $attachment) {
            try {
                $contentId = $attachment['content_id'] ?? null;

                if ($contentId && $attachment['is_inline']) {
                    // Create data URI from the base64 content we already have
                    $dataUri = "data:{$attachment['mime_type']};base64,{$attachment['content_base64']}";

                    // Replace CID references with data URI
                    // Handle various CID formats: cid:xxx, cid:xxx@domain, etc.
                    $html = preg_replace(
                        [
                            '/src=["\']cid:' . preg_quote($contentId, '/') . '["\']/',
                            '/src=["\']cid:' . preg_quote($contentId, '/') . '@[^"\']*["\']/',
                        ],
                        'src="' . $dataUri . '"',
                        $html
                    );

                    Log::debug("Embedded inline image in shared mailbox email", [
                        'content_id' => $contentId,
                        'mime_type' => $attachment['mime_type'],
                        'size' => $attachment['size'],
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to embed inline image in shared mailbox email", [
                    'message_id' => $message->getMessageId(),
                    'filename' => $attachment['filename'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $html;
    }
}