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

        // Extract body content using zbateson/mail-mime-parser (handles attachments properly)
        $bodyHtml = null;
        $bodyPlain = null;

        try {
            $result = $this->extractBodiesWithZbateson($message);

            if ($result) {
                $bodyHtml = $result['html'];
                $bodyPlain = $result['plain'];

                \Log::info('Email body extracted successfully', [
                    'message_id' => $message->getMessageId(),
                    'html_length' => strlen($bodyHtml ?? ''),
                    'plain_length' => strlen($bodyPlain ?? ''),
                ]);
            } else {
                \Log::warning('No body content extracted from email', [
                    'message_id' => $message->getMessageId(),
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to extract email body', [
                'message_id' => $message->getMessageId(),
                'error' => $e->getMessage(),
            ]);
        }

        // Process attachments (also extract from the same parsed message if available)
        $attachments = $this->processAttachments($message, $result);

        // Convert inline images (CID references) to base64 data URIs
        if ($bodyHtml && !empty($attachments)) {
            $bodyHtml = $this->embedInlineImages($bodyHtml, $attachments, $message);
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
     * Process email attachments using zbateson parser
     */
    protected function processAttachments(Message $message, ?array $bodyResult = null): array
    {
        $attachments = [];

        try {
            // Parse email with zbateson - try to get the complete raw message including headers
            $rawEmail = $message->getRawBody();

            // Alternative: Try getting the full message with headers
            if (method_exists($message, 'getHeader')) {
                try {
                    $fullRaw = $message->getHeader()->raw . "\r\n\r\n" . $rawEmail;
                    \Log::debug('Using full raw email with headers', [
                        'raw_body_length' => strlen($rawEmail),
                        'full_raw_length' => strlen($fullRaw),
                    ]);
                    $rawEmail = $fullRaw;
                } catch (\Exception $e) {
                    \Log::debug('Could not prepend headers, using raw body only', ['error' => $e->getMessage()]);
                }
            }

            if (empty($rawEmail)) {
                return [];
            }

            // Check first 500 chars of raw email to see structure
            \Log::debug('Raw email preview', [
                'preview' => substr($rawEmail, 0, 500),
                'total_length' => strlen($rawEmail),
            ]);

            $parser = new MailMimeParser();
            $parsedMessage = $parser->parse($rawEmail, true);

            // Get all attachment parts
            $attachmentParts = $parsedMessage->getAllAttachmentParts();
            $allParts = $parsedMessage->getAllParts();

            \Log::info('Attachment extraction attempt', [
                'message_id' => $message->getMessageId(),
                'attachment_count' => count($attachmentParts),
                'all_parts_count' => count($allParts),
                'raw_email_length' => strlen($rawEmail),
            ]);

            // Debug: Check message structure
            \Log::debug('Message structure', [
                'is_multipart' => $parsedMessage->isMultiPart(),
                'is_mime' => $parsedMessage->isMime(),
                'content_type' => $parsedMessage->getContentType(),
                'part_count' => $parsedMessage->getPartCount(),
            ]);

            // Debug: Log all parts to see what we have
            foreach ($allParts as $index => $part) {
                \Log::debug('MIME part detected', [
                    'index' => $index,
                    'content_type' => $part->getContentType(),
                    'disposition' => $part->getContentDisposition(),
                    'filename' => $part->getFilename(),
                    'size' => strlen($part->getContent() ?? ''),
                    'is_attachment' => ($part->getContentDisposition() === 'attachment'),
                ]);
            }

            // Try alternative: Look for parts with filenames regardless of disposition
            \Log::info('Checking for parts with filenames...');
            foreach ($allParts as $part) {
                $filename = $part->getFilename();
                if (!empty($filename)) {
                    \Log::info('Found part with filename!', [
                        'filename' => $filename,
                        'content_type' => $part->getContentType(),
                        'disposition' => $part->getContentDisposition(),
                        'size' => strlen($part->getContent() ?? ''),
                    ]);
                }
            }

            if (count($attachmentParts) > $this->maxAttachments) {
                Log::warning("Email has too many attachments", [
                    'message_id' => $message->getMessageId(),
                    'attachment_count' => count($attachmentParts),
                    'max_allowed' => $this->maxAttachments,
                ]);
                return [];
            }

            foreach ($attachmentParts as $attachment) {
                try {
                    $fileName = $attachment->getFilename();
                    $content = $attachment->getContent();
                    $fileSize = strlen($content);
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    // Check file size
                    if ($fileSize > $this->maxAttachmentSize) {
                        Log::warning("Attachment too large, skipping", [
                            'filename' => $fileName,
                            'size' => $fileSize,
                            'max_size' => $this->maxAttachmentSize,
                        ]);
                        continue;
                    }

                    // Check file type
                    if (!in_array($extension, $this->allowedAttachmentTypes)) {
                        Log::warning("Attachment type not allowed, skipping", [
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
                        'filename' => $fileName ?: 'attachment_' . count($attachments),
                        'size' => $fileSize,
                        'mime_type' => $attachment->getContentType(),
                        'extension' => $extension,
                        'content_base64' => base64_encode($content),
                        'is_inline' => $isInline,
                        'content_id' => $contentId,
                    ];

                } catch (\Exception $e) {
                    Log::error("Failed to process attachment", [
                        'message_id' => $message->getMessageId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to extract attachments", [
                'message_id' => $message->getMessageId(),
                'error' => $e->getMessage(),
            ]);
        }

        return $attachments;
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
     * Extract email bodies using zbateson/mail-mime-parser
     * This library properly handles multipart MIME messages with attachments
     *
     * @param Message $message Webklex IMAP message object
     * @return array|null Array with 'html' and 'plain' keys, or null on failure
     */
    protected function extractBodiesWithZbateson(Message $message): ?array
    {
        try {
            // Get the raw email content
            $rawEmail = $message->getRawBody();

            if (empty($rawEmail)) {
                \Log::warning('Raw email body is empty');
                return null;
            }

            // Parse with zbateson (second param: true = attached content stream)
            $parser = new MailMimeParser();
            $parsedMessage = $parser->parse($rawEmail, true);

            // Extract text bodies (zbateson handles multipart properly)
            $textPlain = null;
            $textHtml = null;

            // Get text content
            $textPart = $parsedMessage->getTextPart();
            if ($textPart) {
                $textPlain = $textPart->getContent();
            }

            // Get HTML content
            $htmlPart = $parsedMessage->getHtmlPart();
            if ($htmlPart) {
                $textHtml = $htmlPart->getContent();
            }

            // If neither found, try to get any text content from parts
            if (empty($textPlain) && empty($textHtml)) {
                $allParts = $parsedMessage->getAllParts();
                foreach ($allParts as $part) {
                    $contentType = strtolower($part->getContentType() ?? '');

                    // Skip attachments
                    $disposition = strtolower($part->getContentDisposition() ?? '');
                    if ($disposition === 'attachment') {
                        continue;
                    }

                    if (strpos($contentType, 'text/plain') === 0 && empty($textPlain)) {
                        $textPlain = $part->getContent();
                    } elseif (strpos($contentType, 'text/html') === 0 && empty($textHtml)) {
                        $textHtml = $part->getContent();
                    }

                    // Stop if we found both
                    if (!empty($textPlain) && !empty($textHtml)) {
                        break;
                    }
                }
            }

            // Return the results
            if (!empty($textPlain) || !empty($textHtml)) {
                return [
                    'plain' => $textPlain,
                    'html' => $textHtml,
                ];
            }

            \Log::warning('Parsed message but found no text content');
            return null;

        } catch (\Exception $e) {
            \Log::error('Email body extraction failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}