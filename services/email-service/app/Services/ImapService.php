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
            $messages = $folder->messages()->unseen()->get();

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

        // Check for duplicates
        if (EmailQueue::isDuplicate($messageId, $account->id)) {
            Log::debug("Skipping duplicate email", [
                'message_id' => $messageId,
                'account_id' => $account->id,
            ]);
            return;
        }

        // Extract email data
        $emailData = $this->extractEmailData($message, $account);

        // Save to email queue
        $emailQueue = new EmailQueue($emailData);
        $emailQueue->save();

        // Mark message as seen
        $message->setFlag('Seen');

        Log::debug("Email added to queue", [
            'queue_id' => $emailQueue->id,
            'message_id' => $messageId,
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

        // Extract body content
        $bodyHtml = $message->getHTMLBody();
        $bodyPlain = $message->getTextBody();

        // Process attachments
        $attachments = $this->processAttachments($message);

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

        $headers = [
            'message-id' => $message->getMessageId(),
            'in-reply-to' => $message->getInReplyTo(),
            'references' => $message->getReferences(),
            'date' => $dateString,
            'priority' => $message->getPriority(),
            'thread-topic' => $message->getHeader('thread-topic'),
        ];

        return [
            'email_account_id' => $account->id,
            'message_id' => $message->getMessageId(),
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
     * Process email attachments
     */
    protected function processAttachments(Message $message): array
    {
        $attachments = [];
        $attachmentCollection = $message->getAttachments();

        if ($attachmentCollection->count() > $this->maxAttachments) {
            Log::warning("Email has too many attachments", [
                'message_id' => $message->getMessageId(),
                'attachment_count' => $attachmentCollection->count(),
                'max_allowed' => $this->maxAttachments,
            ]);
            return [];
        }

        foreach ($attachmentCollection as $attachment) {
            try {
                $fileName = $attachment->getName();
                $fileSize = $attachment->getSize();
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

                // Get attachment content
                $content = $attachment->getContent();
                $base64Content = base64_encode($content);

                $attachments[] = [
                    'filename' => $fileName,
                    'size' => $fileSize,
                    'mime_type' => $attachment->getMimeType(),
                    'extension' => $extension,
                    'content_base64' => $base64Content,
                    'is_inline' => $attachment->getContentId() ? true : false,
                ];

            } catch (\Exception $e) {
                Log::error("Failed to process attachment", [
                    'message_id' => $message->getMessageId(),
                    'filename' => $attachment->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
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
}