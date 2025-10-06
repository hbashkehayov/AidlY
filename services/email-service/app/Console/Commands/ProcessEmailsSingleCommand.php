<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Models\EmailQueue;
use App\Services\EmailToTicketService;
use App\Services\ImapService;
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
    protected $imapService;
    private $lockFile = '/tmp/aidly_email_processing.lock';

    public function __construct(EmailToTicketService $emailToTicketService, ImapService $imapService)
    {
        parent::__construct();
        $this->emailToTicketService = $emailToTicketService;
        $this->imapService = $imapService;
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

        try {
            if ($dryRun) {
                $this->info("  → Would fetch emails from account");
                return $stats;
            }

            // Use ImapService which has proper multipart/MIME handling
            $result = $this->imapService->fetchEmailsFromAccount($account);

            $stats['fetched'] = $result['count'];
            $stats['duplicates'] = $result['total_messages'] - $result['count'];
            $stats['errors'] = count($result['errors'] ?? []);

            if ($result['count'] > 0) {
                $this->info("  → Queued {$result['count']} email(s)");
            } else {
                $this->info("  → No new emails");
            }

        } catch (\Exception $e) {
            $stats['errors']++;
            throw $e;
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