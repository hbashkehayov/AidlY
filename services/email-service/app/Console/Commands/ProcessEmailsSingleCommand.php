<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Models\EmailQueue;
use App\Services\EmailToTicketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessEmailsSingleCommand extends Command
{
    protected $signature = 'email:process
                            {--limit=50 : Maximum number of emails to fetch per account}
                            {--dry-run : Run without making changes}';

    protected $description = 'Single command to fetch and process emails to prevent race conditions';

    protected $emailToTicketService;
    private $lockFile = '/tmp/aidly_email_processing.lock';

    public function __construct(EmailToTicketService $emailToTicketService)
    {
        parent::__construct();
        $this->emailToTicketService = $emailToTicketService;
    }

    public function handle()
    {
        // Prevent concurrent execution using file lock
        if (!$this->acquireLock()) {
            $this->warn('Email processing is already running. Skipping to prevent race conditions.');
            return 0;
        }

        try {
            $startTime = microtime(true);
            $dryRun = $this->option('dry-run');
            $limit = (int) $this->option('limit');

            if ($dryRun) {
                $this->info('🔍 DRY RUN MODE - No changes will be made');
            }

            $this->info('Starting unified email processing...');

            // Get all active email accounts
            $emailAccounts = EmailAccount::where('is_active', true)->get();

            if ($emailAccounts->isEmpty()) {
                $this->warn('No active email accounts found');
                return 0;
            }

            $totalStats = [
                'fetched' => 0,
                'tickets_created' => 0,
                'errors' => 0,
                'duplicates' => 0
            ];

            foreach ($emailAccounts as $account) {
                $this->info("\n📧 Processing: {$account->name} ({$account->email_address})");

                try {
                    $stats = $this->processAccount($account, $limit, $dryRun);

                    $totalStats['fetched'] += $stats['fetched'];
                    $totalStats['tickets_created'] += $stats['tickets_created'];
                    $totalStats['errors'] += $stats['errors'];
                    $totalStats['duplicates'] += $stats['duplicates'];

                    // Update last sync time
                    if (!$dryRun && $stats['fetched'] > 0) {
                        $account->update(['last_sync_at' => date('Y-m-d H:i:s')]);
                    }

                } catch (\Exception $e) {
                    $this->error("  ❌ Failed: " . $e->getMessage());
                    Log::error('Email account processing failed', [
                        'account_id' => $account->id,
                        'error' => $e->getMessage()
                    ]);
                    $totalStats['errors']++;
                }
            }

            // Process any unprocessed emails in queue
            if (!$dryRun) {
                $this->info("\n🎫 Processing queued emails to tickets...");
                $ticketStats = $this->processQueueToTickets();
                $totalStats['tickets_created'] += $ticketStats['created'];
            }

            $duration = round(microtime(true) - $startTime, 2);

            $this->info("\n" . str_repeat('=', 50));
            $this->info("✅ Email processing completed in {$duration}s");
            $this->info("📊 Summary:");
            $this->info("  • Emails fetched: {$totalStats['fetched']}");
            $this->info("  • Duplicates skipped: {$totalStats['duplicates']}");
            $this->info("  • Tickets created: {$totalStats['tickets_created']}");
            $this->info("  • Errors: {$totalStats['errors']}");

            return 0;

        } finally {
            $this->releaseLock();
        }
    }

    private function processAccount(EmailAccount $account, int $limit, bool $dryRun): array
    {
        $stats = [
            'fetched' => 0,
            'tickets_created' => 0,
            'errors' => 0,
            'duplicates' => 0
        ];

        // Use native PHP IMAP for Gmail compatibility
        $mailbox = "{{$account->imap_host}:{$account->imap_port}/imap/ssl}INBOX";
        $imap = @imap_open($mailbox, $account->imap_username, $account->imap_password);

        if (!$imap) {
            throw new \Exception('IMAP connection failed: ' . imap_last_error());
        }

        try {
            // Search for UNSEEN emails first, then check recent emails that might have been marked as seen
            $emails = imap_search($imap, 'UNSEEN');

            // Also check emails from the last hour that we might have missed
            $oneHourAgo = date('j-M-Y H:i', strtotime('-1 hour'));
            $recentEmails = imap_search($imap, "SINCE \"{$oneHourAgo}\"");

            if ($recentEmails) {
                $emails = $emails ? array_unique(array_merge($emails, $recentEmails)) : $recentEmails;
            }

            if (!$emails) {
                $this->info("  → No new emails");
                return $stats;
            }

            $this->info("  → Found " . count($emails) . " unread emails");

            foreach (array_slice($emails, 0, $limit) as $emailNumber) {
                try {
                    $header = imap_headerinfo($imap, $emailNumber);
                    $messageId = $header->message_id ?? uniqid('email_');

                    // Check for duplicates
                    if (EmailQueue::where('message_id', $messageId)->exists()) {
                        $stats['duplicates']++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->info("  → Would queue: {$header->subject}");
                        $stats['fetched']++;
                        continue;
                    }

                    // Get email body
                    $body = $this->getEmailBody($imap, $emailNumber);

                    // Create queue entry with transaction to prevent duplicates
                    DB::transaction(function () use ($account, $header, $body, $messageId, &$stats) {
                        // Double-check for race condition
                        if (EmailQueue::where('message_id', $messageId)->exists()) {
                            $stats['duplicates']++;
                            return;
                        }

                        $toAddresses = $this->extractAddresses($header->to ?? []);
                        $ccAddresses = $this->extractAddresses($header->cc ?? []);

                        EmailQueue::create([
                            'email_account_id' => $account->id,
                            'message_id' => $messageId,
                            'from_address' => $header->from[0]->mailbox . '@' . $header->from[0]->host,
                            'from_name' => $header->from[0]->personal ?? '',
                            'to_addresses' => DB::raw("ARRAY['" . implode("','", $toAddresses) . "']::text[]"),
                            'cc_addresses' => DB::raw("ARRAY['" . implode("','", $ccAddresses) . "']::text[]"),
                            'subject' => imap_utf8($header->subject ?? '(no subject)'),
                            'body_plain' => $body['plain'],
                            'body_html' => $body['html'],
                            'content' => $body['plain'] ?: strip_tags($body['html']),
                            'received_at' => date('Y-m-d H:i:s', $header->udate),
                            'is_processed' => false,
                            'mailbox_type' => $account->account_type,
                            'original_recipient' => $account->email_address,
                        ]);

                        $stats['fetched']++;
                    });

                    // Mark as read
                    imap_setflag_full($imap, $emailNumber, "\\Seen");

                    $this->info("  → Queued: " . imap_utf8($header->subject ?? ''));

                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error('Failed to process email', [
                        'error' => $e->getMessage(),
                        'account' => $account->email_address
                    ]);
                }
            }

        } finally {
            imap_close($imap);
        }

        return $stats;
    }

    private function processQueueToTickets(): array
    {
        $stats = ['created' => 0, 'failed' => 0];

        $unprocessedEmails = EmailQueue::where('is_processed', false)
            ->where('retry_count', '<', 3)
            ->orderBy('received_at')
            ->limit(100)
            ->lockForUpdate()
            ->get();

        foreach ($unprocessedEmails as $email) {
            try {
                $result = $this->emailToTicketService->processEmail($email);

                if ($result['success']) {
                    $stats['created']++;
                    $this->info("  → Ticket #{$result['ticket_id']} created from: {$email->subject}");
                } else {
                    $stats['failed']++;
                    $email->increment('retry_count');
                }

            } catch (\Exception $e) {
                $stats['failed']++;
                $email->increment('retry_count');

                Log::error('Failed to create ticket from email', [
                    'email_id' => $email->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $stats;
    }

    private function getEmailBody($imap, $emailNumber): array
    {
        $structure = imap_fetchstructure($imap, $emailNumber);
        $body = ['plain' => '', 'html' => ''];

        if (!$structure->parts) {
            // Simple message
            $content = imap_fetchbody($imap, $emailNumber, 1);
            $body['plain'] = $this->decodeContent($content, $structure->encoding);
        } else {
            // Multipart message
            foreach ($structure->parts as $partNum => $part) {
                $partNumber = $partNum + 1;

                if ($part->type == 0) { // Text
                    $content = imap_fetchbody($imap, $emailNumber, $partNumber);
                    $decoded = $this->decodeContent($content, $part->encoding);

                    if (strtoupper($part->subtype) == 'PLAIN') {
                        $body['plain'] = $decoded;
                    } elseif (strtoupper($part->subtype) == 'HTML') {
                        $body['html'] = $decoded;
                    }
                }
            }
        }

        return $body;
    }

    private function decodeContent($content, $encoding): string
    {
        switch ($encoding) {
            case 3: // BASE64
                return base64_decode($content);
            case 4: // QUOTED-PRINTABLE
                return quoted_printable_decode($content);
            default:
                return $content;
        }
    }

    private function extractAddresses($addresses): array
    {
        $result = [];
        foreach ($addresses as $address) {
            $result[] = $address->mailbox . '@' . $address->host;
        }
        return $result;
    }

    private function acquireLock(): bool
    {
        if (file_exists($this->lockFile)) {
            // Check if lock is stale (older than 10 minutes)
            if (time() - filemtime($this->lockFile) > 600) {
                unlink($this->lockFile);
            } else {
                return false;
            }
        }

        return touch($this->lockFile);
    }

    private function releaseLock(): void
    {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }
}