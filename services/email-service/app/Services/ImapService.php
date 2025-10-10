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
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message as MimeMessage;

class ImapService
{
    protected $clientManager;
    protected $maxAttachments;
    protected $maxAttachmentSize;
    protected $allowedAttachmentTypes;

    public function __construct()
    {
        $this->clientManager = new ClientManager();
        $this->maxAttachments = env('EMAIL_MAX_ATTACHMENTS', 5);
        $this->maxAttachmentSize = env('EMAIL_MAX_ATTACHMENT_SIZE', 10485760); // 10MB
        $this->allowedAttachmentTypes = explode(',', env('EMAIL_ALLOWED_ATTACHMENT_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip'));
    }

    /**
     * Fetch emails from all active email accounts
     */
    public function fetchAllEmails(): array
    {
        $results = [];
        $accounts = EmailAccount::active()->get();

        foreach ($accounts as $account) {
            try {
                $result = $this->fetchEmailsFromAccount($account);
                $results[] = [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'success' => true,
                    'emails_fetched' => $result['count'],
                    'message' => "Fetched {$result['count']} emails",
                ];
            } catch (\Exception $e) {
                Log::error("Failed to fetch emails from account {$account->id}", [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'success' => false,
                    'emails_fetched' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Fetch emails from a specific email account
     */
    public function fetchEmailsFromAccount(EmailAccount $account): array
    {
        // Validate account configuration
        if (empty($account->imap_host) || empty($account->imap_username) || empty($account->imap_password)) {
            throw new \Exception("Email account {$account->name} is not properly configured");
        }

        try {
            $client = $this->createImapClient($account);
            $client->connect();

            $folder = $client->getFolder('INBOX');
            // Ensure body fetching is enabled in the query
            $messages = $folder->query()
                ->unseen()
                ->setFetchBody(true)
                ->get();

            $fetchedCount = 0;
            $errors = [];

            foreach ($messages as $message) {
                try {
                    $this->processMessage($message, $account);
                    $fetchedCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'message_id' => $message->getMessageId(),
                        'subject' => $message->getSubject(),
                        'error' => $e->getMessage(),
                    ];

                    Log::error("Failed to process email message", [
                        'account_id' => $account->id,
                        'message_id' => $message->getMessageId(),
                        'subject' => $message->getSubject(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $client->disconnect();
            $account->updateLastSync();

            Log::info("Fetched emails from account", [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'total_messages' => $messages->count(),
                'processed' => $fetchedCount,
                'errors' => count($errors),
            ]);

            return [
                'count' => $fetchedCount,
                'errors' => $errors,
                'total_messages' => $messages->count(),
            ];

        } catch (\Exception $e) {
            Log::error("Failed to connect to email account", [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create IMAP client for account
     */
    protected function createImapClient(EmailAccount $account): Client
    {
        $config = [
            'host' => $account->imap_host,
            'port' => $account->imap_port,
            'encryption' => $account->imap_use_ssl ? 'ssl' : null,
            'validate_cert' => true,
            'username' => $account->imap_username,
            'password' => $account->imap_password,
            'protocol' => 'imap',
            'options' => [
                'delimiter' => '/',
                'fetch' => IMAP::FT_UID,
                'fetch_body' => true,
                'fetch_flags' => true,
                'soft_expunge' => false,
                'debug' => false,
            ]
        ];

        return $this->clientManager->make($config);
    }

    /**
     * Process individual email message
     */
    protected function processMessage(Message $message, EmailAccount $account): void
    {
        $messageId = $message->getMessageId();

        // Normalize message ID by removing angle brackets for consistent duplicate detection
        $normalizedMessageId = trim($messageId, '<>');

        // Check for duplicates using normalized message ID
        if (EmailQueue::isDuplicate($normalizedMessageId, $account->id)) {
            Log::debug("Skipping duplicate email", [
                'message_id' => $messageId,
                'normalized_message_id' => $normalizedMessageId,
                'account_id' => $account->id,
            ]);
            return;
        }

        // Extract email data
        $emailData = $this->extractEmailData($message, $account);

        // Ensure message_id is normalized (without angle brackets)
        $emailData['message_id'] = $normalizedMessageId;

        // Save to email queue
        $emailQueue = new EmailQueue($emailData);
        $emailQueue->save();

        // Mark message as seen
        $message->setFlag('Seen');

        Log::debug("Email added to queue", [
            'queue_id' => $emailQueue->id,
            'message_id' => $normalizedMessageId,
            'subject' => $message->getSubject(),
            'from' => $message->getFrom()[0]->mail ?? 'unknown',
        ]);
    }

    /**
     * Extract data from email message
     */
    protected function extractEmailData(Message $message, EmailAccount $account): array
    {
        // Extract addresses
        $fromAddresses = $message->getFrom();
        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc();

        $from = $fromAddresses->first();
        $fromAddress = $from ? $from->mail : null;

        $toArray = [];
        foreach ($toAddresses as $to) {
            $toArray[] = $to->mail;
        }

        $ccArray = [];
        foreach ($ccAddresses as $cc) {
            $ccArray[] = $cc->mail;
        }

        // Extract body content and attachments using zbateson/mail-mime-parser in one pass
        // This prevents parsing the same email twice and handles complex MIME structures better
        $bodyHtml = null;
        $bodyPlain = null;
        $attachments = [];
        $parsedMessage = null;

        try {
            // Parse the email ONCE and extract both bodies and attachments
            $result = $this->extractBodiesAndAttachments($message);

            if ($result) {
                $bodyHtml = $result['html'];
                $bodyPlain = $result['plain'];
                $attachments = $result['attachments'] ?? [];
                $parsedMessage = $result['parsed_message'] ?? null;

                \Log::info('Email content extracted successfully', [
                    'message_id' => $message->getMessageId(),
                    'html_length' => strlen($bodyHtml ?? ''),
                    'plain_length' => strlen($bodyPlain ?? ''),
                    'attachment_count' => count($attachments),
                ]);
            } else {
                \Log::warning('No body content extracted from email', [
                    'message_id' => $message->getMessageId(),
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to extract email content', [
                'message_id' => $message->getMessageId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Convert inline images (CID references) to base64 data URIs
        if ($bodyHtml && !empty($attachments)) {
            \Log::info('Before embedding inline images', [
                'message_id' => $message->getMessageId(),
                'html_preview' => substr($bodyHtml, 0, 500),
                'attachment_count' => count($attachments),
                'inline_attachment_count' => count(array_filter($attachments, fn($a) => $a['is_inline'] ?? false)),
            ]);

            $bodyHtml = $this->embedInlineImages($bodyHtml, $attachments, $message);

            \Log::info('After embedding inline images', [
                'message_id' => $message->getMessageId(),
                'html_preview' => substr($bodyHtml, 0, 500),
                'contains_data_uri' => strpos($bodyHtml, 'data:image') !== false,
            ]);
        }

        // Extract headers
        $date = $message->getDate();
        $dateString = null;

        if ($date) {
            if (is_object($date) && method_exists($date, 'format')) {
                $dateString = $date->format('c');
            } elseif (is_object($date) && method_exists($date, 'toString')) {
                $dateString = $date->toString();
            } elseif (is_string($date)) {
                $dateString = $date;
            } else {
                $dateString = (string) $date;
            }
        }

        // Normalize message IDs (remove angle brackets)
        $normalizedMessageId = trim($message->getMessageId(), '<>');
        $normalizedInReplyTo = $message->getInReplyTo() ? trim($message->getInReplyTo(), '<>') : null;
        $normalizedReferences = $message->getReferences() ? trim($message->getReferences(), '<>') : null;

        $headers = [
            'message-id' => $normalizedMessageId,
            'in-reply-to' => $normalizedInReplyTo,
            'references' => $normalizedReferences,
            'date' => $dateString,
            'priority' => $message->getPriority(),
            'thread-topic' => $message->getHeader('thread-topic'),
        ];

        return [
            'email_account_id' => $account->id,
            'message_id' => $normalizedMessageId,
            'from_address' => $fromAddress,
            'to_addresses' => !empty($toArray) ? $toArray : null,
            'cc_addresses' => !empty($ccArray) ? $ccArray : null,
            'subject' => $message->getSubject(),
            'body_plain' => $bodyPlain,
            'body_html' => $bodyHtml,
            'headers' => !empty($headers) ? $headers : null,
            'attachments' => !empty($attachments) ? $attachments : null,
            'received_at' => $dateString ? date('Y-m-d H:i:s', strtotime($dateString)) : date('Y-m-d H:i:s'),
            'is_processed' => false,
            'retry_count' => 0,
        ];
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

                    Log::debug("Embedded inline image", [
                        'content_id' => $contentId,
                        'mime_type' => $attachment['mime_type'],
                        'size' => $attachment['size'],
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to embed inline image", [
                    'message_id' => $message->getMessageId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $html;
    }


    /**
     * Test connection to email account
     */
    public function testConnection(EmailAccount $account): array
    {
        try {
            $client = $this->createImapClient($account);
            $client->connect();

            $folders = $client->getFolders();
            $client->disconnect();

            return [
                'success' => true,
                'message' => 'Connection successful',
                'folders' => $folders->pluck('name')->toArray(),
            ];

        } catch (ConnectionFailedException $e) {
            return [
                'success' => false,
                'error' => 'Connection failed',
                'message' => $e->getMessage(),
            ];
        } catch (AuthFailedException $e) {
            return [
                'success' => false,
                'error' => 'Authentication failed',
                'message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Unknown error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get email statistics for account
     */
    public function getAccountStats(EmailAccount $account): array
    {
        try {
            $client = $this->createImapClient($account);
            $client->connect();

            $folder = $client->getFolder('INBOX');
            $totalMessages = $folder->messages()->count();
            $unreadMessages = $folder->messages()->unseen()->count();

            $client->disconnect();

            return [
                'total_messages' => $totalMessages,
                'unread_messages' => $unreadMessages,
                'last_sync' => $account->last_sync_at?->format('Y-m-d H:i:s'),
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract email bodies AND attachments in a single parse
     * This prevents double-parsing and handles complex nested MIME structures properly
     *
     * @param Message $message Webklex IMAP message object
     * @return array|null Array with 'html', 'plain', 'attachments', and 'parsed_message' keys
     */
    protected function extractBodiesAndAttachments(Message $message): ?array
    {
        try {
            // Get the raw email content - need full message with headers for proper attachment parsing
            $rawEmail = $message->getRawBody();

            // Try to get full message with headers for better MIME parsing
            if (method_exists($message, 'getHeader')) {
                try {
                    $headerRaw = $message->getHeader()->raw;
                    if (!empty($headerRaw)) {
                        $fullRaw = $headerRaw . "\r\n\r\n" . $rawEmail;
                        \Log::debug('Using full raw email with headers', [
                            'raw_body_length' => strlen($rawEmail),
                            'full_raw_length' => strlen($fullRaw),
                        ]);
                        $rawEmail = $fullRaw;
                    }
                } catch (\Exception $e) {
                    \Log::debug('Could not prepend headers, using raw body only', ['error' => $e->getMessage()]);
                }
            }

            if (empty($rawEmail)) {
                \Log::warning('Raw email body is empty');
                return null;
            }

            \Log::debug('Parsing email with zbateson', [
                'message_id' => $message->getMessageId(),
                'raw_length' => strlen($rawEmail),
            ]);

            // Parse with zbateson (second param: true = attached content stream)
            $parser = new MailMimeParser();
            $parsedMessage = $parser->parse($rawEmail, true);

            // Extract text bodies using improved nested traversal
            $textPlain = null;
            $textHtml = null;

            // Method 1: Try standard getTextPart() and getHtmlPart()
            $textPart = $parsedMessage->getTextPart();
            if ($textPart) {
                $textPlain = $textPart->getContent();
                \Log::debug('Found text/plain via getTextPart()', ['length' => strlen($textPlain ?? '')]);
            }

            $htmlPart = $parsedMessage->getHtmlPart();
            if ($htmlPart) {
                $textHtml = $htmlPart->getContent();
                \Log::debug('Found text/html via getHtmlPart()', ['length' => strlen($textHtml ?? '')]);
            }

            // Method 2: If not found, manually traverse ALL parts recursively
            // This handles complex nested multipart structures (e.g., multipart/mixed > multipart/alternative > text/plain)
            if (empty($textPlain) && empty($textHtml)) {
                \Log::debug('Standard extraction failed, trying recursive part traversal');
                $allParts = $parsedMessage->getAllParts();

                foreach ($allParts as $index => $part) {
                    $contentType = strtolower($part->getContentType() ?? '');
                    $disposition = strtolower($part->getContentDisposition() ?? '');
                    $filename = $part->getFilename();

                    \Log::debug("Examining MIME part", [
                        'index' => $index,
                        'content_type' => $contentType,
                        'disposition' => $disposition,
                        'has_filename' => !empty($filename),
                    ]);

                    // Skip parts that are clearly attachments (have filename or explicit attachment disposition)
                    if (!empty($filename) || $disposition === 'attachment') {
                        \Log::debug("Skipping part (is attachment)", ['index' => $index]);
                        continue;
                    }

                    // Extract text content based on content type
                    if (empty($textPlain) && strpos($contentType, 'text/plain') === 0) {
                        $textPlain = $part->getContent();
                        \Log::debug('Found text/plain via recursive traversal', [
                            'index' => $index,
                            'length' => strlen($textPlain ?? '')
                        ]);
                    } elseif (empty($textHtml) && strpos($contentType, 'text/html') === 0) {
                        $textHtml = $part->getContent();
                        \Log::debug('Found text/html via recursive traversal', [
                            'index' => $index,
                            'length' => strlen($textHtml ?? '')
                        ]);
                    }

                    // Stop if we found both
                    if (!empty($textPlain) && !empty($textHtml)) {
                        \Log::debug('Found both text types, stopping traversal');
                        break;
                    }
                }
            }

            // Extract attachments
            $attachments = $this->extractAttachmentsFromParsed($parsedMessage, $message);

            \Log::info('Email parsing complete', [
                'message_id' => $message->getMessageId(),
                'has_plain' => !empty($textPlain),
                'has_html' => !empty($textHtml),
                'plain_length' => strlen($textPlain ?? ''),
                'html_length' => strlen($textHtml ?? ''),
                'attachment_count' => count($attachments),
            ]);

            // Return the results even if bodies are empty (might have attachments only)
            return [
                'plain' => $textPlain,
                'html' => $textHtml,
                'attachments' => $attachments,
                'parsed_message' => $parsedMessage,
            ];

        } catch (\Exception $e) {
            \Log::error('Email parsing failed', [
                'message_id' => $message->getMessageId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Extract attachments from already-parsed message
     * This prevents re-parsing the email
     *
     * @param MimeMessage $parsedMessage Already parsed zbateson message
     * @param Message $originalMessage Original webklex message (for logging)
     * @return array Array of attachments
     */
    protected function extractAttachmentsFromParsed(MimeMessage $parsedMessage, Message $originalMessage): array
    {
        $attachments = [];

        try {
            // Get all attachment parts
            $attachmentParts = $parsedMessage->getAllAttachmentParts();

            // DEBUGGING: Get all parts to see what we're missing
            $allParts = $parsedMessage->getAllParts();

            \Log::info('Extracting attachments from parsed message', [
                'message_id' => $originalMessage->getMessageId(),
                'attachment_count' => count($attachmentParts),
                'total_parts' => count($allParts),
            ]);

            // Log all parts to see structure
            foreach ($allParts as $index => $part) {
                \Log::debug('Part detected in email', [
                    'index' => $index,
                    'content_type' => $part->getContentType(),
                    'disposition' => $part->getContentDisposition(),
                    'filename' => $part->getFilename(),
                    'has_content' => strlen($part->getContent() ?? '') > 0,
                    'content_size' => strlen($part->getContent() ?? ''),
                ]);
            }

            if (count($attachmentParts) > $this->maxAttachments) {
                \Log::warning("Email has too many attachments", [
                    'message_id' => $originalMessage->getMessageId(),
                    'attachment_count' => count($attachmentParts),
                    'max_allowed' => $this->maxAttachments,
                ]);
                return [];
            }

            foreach ($attachmentParts as $index => $attachment) {
                try {
                    $fileName = $attachment->getFilename();
                    $content = $attachment->getContent();
                    $fileSize = strlen($content);
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    \Log::debug("Processing attachment", [
                        'index' => $index,
                        'filename' => $fileName,
                        'size' => $fileSize,
                        'extension' => $extension,
                    ]);

                    // Check file size
                    if ($fileSize > $this->maxAttachmentSize) {
                        \Log::warning("Attachment too large, skipping", [
                            'filename' => $fileName,
                            'size' => $fileSize,
                            'max_size' => $this->maxAttachmentSize,
                        ]);
                        continue;
                    }

                    // Check file type
                    if (!in_array($extension, $this->allowedAttachmentTypes)) {
                        \Log::warning("Attachment type not allowed, skipping", [
                            'filename' => $fileName,
                            'extension' => $extension,
                            'allowed_types' => $this->allowedAttachmentTypes,
                        ]);
                        continue;
                    }

                    // Get content ID for inline images
                    $contentId = $attachment->getContentId();
                    if ($contentId) {
                        $contentId = trim($contentId, '<>');
                    }

                    // Check disposition to determine if inline
                    $disposition = strtolower($attachment->getContentDisposition() ?? '');
                    $isInline = ($disposition === 'inline') || !empty($contentId);

                    $attachments[] = [
                        'filename' => $fileName ?: 'attachment_' . (count($attachments) + 1),
                        'size' => $fileSize,
                        'mime_type' => $attachment->getContentType(),
                        'extension' => $extension,
                        'content_base64' => base64_encode($content),
                        'is_inline' => $isInline,
                        'content_id' => $contentId,
                    ];

                    \Log::debug("Attachment processed successfully", [
                        'filename' => $fileName,
                        'is_inline' => $isInline,
                    ]);

                } catch (\Exception $e) {
                    \Log::error("Failed to process individual attachment", [
                        'message_id' => $originalMessage->getMessageId(),
                        'index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            \Log::error("Failed to extract attachments from parsed message", [
                'message_id' => $originalMessage->getMessageId(),
                'error' => $e->getMessage(),
            ]);
        }

        return $attachments;
    }
}