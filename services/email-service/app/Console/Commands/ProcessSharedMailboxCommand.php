<?php

namespace App\Console\Commands;

use App\Services\SharedMailboxImapService;
use App\Services\EmailToTicketService;
use App\Models\EmailAccount;
use App\Models\EmailQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Enhanced Artisan command for processing shared mailboxes
 * Replaces the need for individual Gmail app passwords with centralized shared mailbox processing
 */
class ProcessSharedMailboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mailbox:process-shared
                            {--mailbox= : Process specific shared mailbox by email address}
                            {--fetch-only : Only fetch emails without processing to tickets}
                            {--process-only : Only process existing queued emails without fetching}
                            {--test-connections : Test all shared mailbox connections}
                            {--dry-run : Run without making actual changes}
                            {--limit=100 : Maximum number of emails to process per mailbox}
                            {--detailed : Show detailed progress information}';

    /**
     * The console command description.
     */
    protected $description = 'Process shared mailboxes to fetch emails and convert them to tickets (replaces individual Gmail accounts)';

    protected $sharedImapService;
    protected $emailToTicketService;

    public function __construct(
        SharedMailboxImapService $sharedImapService,
        EmailToTicketService $emailToTicketService
    ) {
        parent::__construct();
        $this->sharedImapService = $sharedImapService;
        $this->emailToTicketService = $emailToTicketService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        $this->displayHeader();

        $options = $this->gatherOptions();

        if ($options['dry_run']) {
            $this->warn('🔍 DRY RUN MODE - No actual changes will be made');
        }

        try {
            // Test connections if requested
            if ($options['test_connections']) {
                return $this->testAllConnections();
            }

            $results = [
                'fetch_results' => [],
                'process_results' => []
            ];

            // Step 1: Fetch emails from shared mailboxes
            if (!$options['process_only']) {
                $this->info("\n📥 STEP 1: Fetching emails from shared mailboxes...");
                $results['fetch_results'] = $this->fetchFromSharedMailboxes($options);
                $this->displayFetchResults($results['fetch_results']);
            }

            // Step 2: Process queued emails to tickets
            if (!$options['fetch_only']) {
                $this->info("\n🎫 STEP 2: Processing emails to tickets...");
                $results['process_results'] = $this->processQueuedEmails($options);
                $this->displayProcessResults($results['process_results']);
            }

            // Step 3: Display summary
            $this->displaySummary($results, microtime(true) - $startTime);

            return 0;

        } catch (\Exception $e) {
            $this->error("\n❌ Critical error during shared mailbox processing:");
            $this->error($e->getMessage());

            if ($options['verbose']) {
                $this->error("\nStack trace:");
                $this->error($e->getTraceAsString());
            }

            Log::error('Shared mailbox processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'options' => $options,
            ]);

            return 1;
        }
    }

    /**
     * Display command header
     */
    protected function displayHeader(): void
    {
        $this->info('╔══════════════════════════════════════════════════════════════════╗');
        $this->info('║              AidlY Shared Mailbox Processing System              ║');
        $this->info('║          Centralized Email-to-Ticket Conversion Process         ║');
        $this->info('╚══════════════════════════════════════════════════════════════════╝');
    }

    /**
     * Gather and validate command options
     */
    protected function gatherOptions(): array
    {
        return [
            'specific_mailbox' => $this->option('mailbox'),
            'fetch_only' => $this->option('fetch-only'),
            'process_only' => $this->option('process-only'),
            'test_connections' => $this->option('test-connections'),
            'dry_run' => $this->option('dry-run'),
            'limit' => (int) $this->option('limit'),
            'verbose' => $this->option('detailed'),
        ];
    }

    /**
     * Test connections to all shared mailboxes
     */
    protected function testAllConnections(): int
    {
        $this->info("🔍 Testing connections to all shared mailboxes...\n");

        $mailboxes = EmailAccount::sharedMailboxes()->get();

        if ($mailboxes->isEmpty()) {
            $this->error('No shared mailboxes configured!');
            $this->line('');
            $this->line('To configure shared mailboxes, add email accounts with account_type = "shared_mailbox"');
            $this->line('Example: support@company.com, billing@company.com');
            return 1;
        }

        $results = [];
        $successCount = 0;

        foreach ($mailboxes as $mailbox) {
            $this->line("Testing {$mailbox->name} ({$mailbox->email_address})...");

            // Test IMAP connection
            $imapResult = $this->sharedImapService->testSharedMailboxConnection($mailbox);

            // Test SMTP connection
            $smtpService = app('App\Services\SharedMailboxSmtpService');
            $smtpResult = $smtpService->testSmtpConnection($mailbox);

            $success = $imapResult['success'] && $smtpResult['success'];

            if ($success) {
                $this->info("  ✅ Connection successful");
                if (isset($imapResult['mailbox_info'])) {
                    $info = $imapResult['mailbox_info'];
                    $this->line("     📧 Total messages: {$info['total_messages']}, Unread: {$info['unread_messages']}");
                    $this->line("     📁 Folders: " . implode(', ', array_slice($info['folders'], 0, 5)));
                }
                $successCount++;
            } else {
                $this->error("  ❌ Connection failed");
                if (!$imapResult['success']) {
                    $this->error("     IMAP: " . $imapResult['message']);
                }
                if (!$smtpResult['success']) {
                    $this->error("     SMTP: " . $smtpResult['message']);
                }
            }

            $results[] = [
                'mailbox' => $mailbox->name,
                'address' => $mailbox->email_address,
                'imap_success' => $imapResult['success'],
                'smtp_success' => $smtpResult['success'],
                'overall_success' => $success
            ];

            $this->line('');
        }

        // Summary
        $this->line('═══════════════════════════════════════');
        $this->info("Connection Test Results:");
        $this->info("✅ Successful: {$successCount}/{$mailboxes->count()}");

        if ($successCount < $mailboxes->count()) {
            $this->warn("❌ Failed: " . ($mailboxes->count() - $successCount) . "/{$mailboxes->count()}");
            $this->line('');
            $this->line('Please check the failed connections and verify:');
            $this->line('- IMAP/SMTP server settings are correct');
            $this->line('- Credentials are valid');
            $this->line('- Network connectivity is available');
            $this->line('- Firewall allows connections to mail servers');
        }

        return $successCount === $mailboxes->count() ? 0 : 1;
    }

    /**
     * Fetch emails from shared mailboxes
     */
    protected function fetchFromSharedMailboxes(array $options): array
    {
        if ($options['specific_mailbox']) {
            return $this->fetchFromSpecificMailbox($options['specific_mailbox'], $options);
        } else {
            return $this->fetchFromAllMailboxes($options);
        }
    }

    /**
     * Fetch from specific mailbox
     */
    protected function fetchFromSpecificMailbox(string $mailboxAddress, array $options): array
    {
        $mailbox = EmailAccount::sharedMailboxes()
            ->where('email_address', $mailboxAddress)
            ->first();

        if (!$mailbox) {
            throw new \Exception("Shared mailbox '{$mailboxAddress}' not found or not configured as shared mailbox");
        }

        $this->line("Processing mailbox: {$mailbox->name} ({$mailbox->email_address})");

        if ($options['dry_run']) {
            return $this->simulateMailboxFetch($mailbox);
        }

        try {
            $result = $this->sharedImapService->fetchFromSharedMailbox($mailbox);
            return [
                'success' => true,
                'total_fetched' => $result['count'],
                'results' => [[
                    'mailbox_name' => $mailbox->name,
                    'mailbox_address' => $mailbox->email_address,
                    'success' => true,
                    'emails_fetched' => $result['count'],
                    'new_emails' => $result['new_count'],
                    'duplicates_skipped' => $result['duplicates'],
                    'errors' => $result['errors']
                ]]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'total_fetched' => 0,
                'results' => [[
                    'mailbox_name' => $mailbox->name,
                    'mailbox_address' => $mailbox->email_address,
                    'success' => false,
                    'emails_fetched' => 0,
                    'error' => $e->getMessage()
                ]]
            ];
        }
    }

    /**
     * Fetch from all shared mailboxes
     */
    protected function fetchFromAllMailboxes(array $options): array
    {
        if ($options['dry_run']) {
            $mailboxes = EmailAccount::sharedMailboxes()->get();
            $results = [];
            foreach ($mailboxes as $mailbox) {
                $results[] = $this->simulateMailboxFetch($mailbox)['results'][0];
            }
            return ['success' => true, 'total_fetched' => array_sum(array_column($results, 'emails_fetched')), 'results' => $results];
        }

        return $this->sharedImapService->fetchFromAllSharedMailboxes();
    }

    /**
     * Process queued emails to tickets
     */
    protected function processQueuedEmails(array $options): array
    {
        $pendingEmails = EmailQueue::pending()
            ->orderBy('received_at')
            ->limit($options['limit'])
            ->get();

        if ($pendingEmails->isEmpty()) {
            $this->info('No pending emails to process');
            return [];
        }

        $this->line("Processing {$pendingEmails->count()} queued emails...");

        if ($options['dry_run']) {
            return $this->simulateEmailProcessing($pendingEmails);
        }

        $results = [];
        $progressBar = null;

        if (!$options['verbose']) {
            $progressBar = $this->output->createProgressBar($pendingEmails->count());
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        }

        foreach ($pendingEmails as $email) {
            $messagePreview = substr($email->subject, 0, 50) . (strlen($email->subject) > 50 ? '...' : '');

            if ($progressBar) {
                $progressBar->setMessage("Processing: {$messagePreview}");
                $progressBar->advance();
            } elseif ($options['verbose']) {
                $this->line("  📧 Processing: {$messagePreview}");
            }

            try {
                DB::beginTransaction();

                // Enhanced processing with shared mailbox context
                $result = $this->processSharedMailboxEmail($email, $options);

                DB::commit();

                $results[] = array_merge($result, [
                    'email_id' => $email->id,
                    'success' => true,
                ]);

                if ($options['verbose']) {
                    $action = ucfirst($result['action']);
                    $this->line("    ✅ {$action}: " . ($result['ticket_id'] ?? 'N/A'));
                }

            } catch (\Exception $e) {
                DB::rollBack();

                Log::error("Failed to process shared mailbox email", [
                    'email_id' => $email->id,
                    'mailbox_address' => $email->originalRecipient ?? 'unknown',
                    'subject' => $email->subject,
                    'error' => $e->getMessage(),
                ]);

                $email->markAsFailed($e->getMessage());

                $results[] = [
                    'email_id' => $email->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                if ($options['verbose']) {
                    $this->error("    ❌ Failed: " . $e->getMessage());
                }
            }
        }

        if ($progressBar) {
            $progressBar->finish();
            $this->newLine();
        }

        return $results;
    }

    /**
     * Enhanced email processing with shared mailbox context
     */
    protected function processSharedMailboxEmail(EmailQueue $email, array $options): array
    {
        // Get the shared mailbox that received this email
        $mailbox = EmailAccount::find($email->email_account_id);

        if (!$mailbox || !$mailbox->isSharedMailbox()) {
            throw new \Exception('Email not associated with a valid shared mailbox');
        }

        // Add shared mailbox context to the processing
        $originalRecipient = $email->original_recipient ?? $mailbox->email_address;

        // Enhanced duplicate detection for shared mailboxes
        if ($this->isSharedMailboxDuplicate($email)) {
            $email->markAsProcessed();
            return [
                'action' => 'skipped_duplicate',
                'reason' => 'Duplicate email detected across shared mailboxes',
            ];
        }

        // Process using the existing service with enhancements
        $result = $this->emailToTicketService->processEmail($email);

        // Apply shared mailbox specific post-processing
        if ($result['action'] === 'created' && isset($result['ticket_id'])) {
            $this->applySharedMailboxRouting($result['ticket_id'], $email, $mailbox);
        }

        return $result;
    }

    /**
     * Enhanced duplicate detection for shared mailboxes
     */
    protected function isSharedMailboxDuplicate(EmailQueue $email): bool
    {
        $messageId = $email->message_id;
        $fromAddress = $email->from_address;
        $subject = $this->normalizeSubject($email->subject);

        // Check for exact message ID match across all shared mailboxes
        if (!empty($messageId)) {
            $existing = EmailQueue::where('message_id', $messageId)
                ->where('id', '!=', $email->id)
                ->whereHas('emailAccount', function ($query) {
                    $query->where('account_type', 'shared_mailbox');
                })
                ->where('is_processed', true)
                ->exists();

            if ($existing) {
                return true;
            }
        }

        // Check for similar emails from same sender in short timeframe
        $recentSimilar = EmailQueue::where('from_address', $fromAddress)
            ->where('id', '!=', $email->id)
            ->where('is_processed', true)
            ->where('received_at', '>=', date('Y-m-d H:i:s', strtotime('-10 minutes', strtotime($email->received_at))))
            ->whereHas('emailAccount', function ($query) {
                $query->where('account_type', 'shared_mailbox');
            })
            ->get();

        foreach ($recentSimilar as $recent) {
            if ($this->normalizeSubject($recent->subject) === $subject) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply shared mailbox specific routing
     */
    protected function applySharedMailboxRouting(string $ticketId, EmailQueue $email, EmailAccount $mailbox): void
    {
        try {
            // Apply routing based on which shared mailbox received the email
            $routingData = [];

            // Apply routing from email data if available
            if (!empty($email->routed_department_id)) {
                $routingData['assigned_department_id'] = $email->routed_department_id;
            }

            if (!empty($email->routed_category_id)) {
                $routingData['category_id'] = $email->routed_category_id;
            }

            if (!empty($email->routed_priority)) {
                $routingData['priority'] = $email->routed_priority;
            }

            // Apply mailbox defaults if no specific routing
            if (empty($routingData['assigned_department_id']) && !empty($mailbox->department_id)) {
                $routingData['assigned_department_id'] = $mailbox->department_id;
            }

            if (empty($routingData['category_id']) && !empty($mailbox->default_category_id)) {
                $routingData['category_id'] = $mailbox->default_category_id;
            }

            if (empty($routingData['priority'])) {
                $routingData['priority'] = $mailbox->default_ticket_priority ?? 'medium';
            }

            // Update ticket if we have routing data
            if (!empty($routingData)) {
                $ticketServiceUrl = env('TICKET_SERVICE_URL', 'http://localhost:8002');
                \Illuminate\Support\Facades\Http::put("{$ticketServiceUrl}/api/v1/public/tickets/{$ticketId}", $routingData);

                Log::debug("Applied shared mailbox routing to ticket", [
                    'ticket_id' => $ticketId,
                    'mailbox_address' => $mailbox->email_address,
                    'routing_data' => $routingData
                ]);
            }

        } catch (\Exception $e) {
            Log::warning("Failed to apply shared mailbox routing", [
                'ticket_id' => $ticketId,
                'mailbox_address' => $mailbox->email_address,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Normalize subject for comparison
     */
    protected function normalizeSubject(string $subject): string
    {
        // Remove common prefixes and ticket numbers
        $subject = preg_replace('/^(Re:|Fwd?:|AW:)\s*/i', '', $subject);
        $subject = preg_replace('/\s*\[?TKT-\d{6}\]?\s*/', '', $subject);
        $subject = preg_replace('/\s+/', ' ', $subject);
        return strtolower(trim($subject));
    }

    /**
     * Display fetch results
     */
    protected function displayFetchResults(array $fetchResults): void
    {
        if (empty($fetchResults['results'])) {
            return;
        }

        $this->newLine();
        $tableData = [];
        $totalFetched = 0;
        $failedCount = 0;

        foreach ($fetchResults['results'] as $result) {
            $status = $result['success'] ? '✅ Success' : '❌ Failed';
            $errors = $result['errors'] ?? [];
            $errorCount = is_array($errors) ? count($errors) : ($result['success'] ? 0 : 1);

            if ($result['success']) {
                $totalFetched += $result['emails_fetched'];
                $info = "{$result['emails_fetched']} total, {$result['new_emails']} new";
                if (!empty($result['duplicates_skipped'])) {
                    $info .= ", {$result['duplicates_skipped']} duplicates";
                }
            } else {
                $failedCount++;
                $info = $result['error'] ?? 'Unknown error';
            }

            $tableData[] = [
                substr($result['mailbox_name'], 0, 20),
                $result['mailbox_address'],
                $status,
                $info,
                $errorCount > 0 ? $errorCount : '-'
            ];
        }

        $this->table(
            ['Mailbox Name', 'Address', 'Status', 'Results', 'Errors'],
            $tableData
        );

        $this->info("📊 Fetch Summary: {$totalFetched} emails fetched");
        if ($failedCount > 0) {
            $this->warn("⚠️  Failed mailboxes: {$failedCount}");
        }
    }

    /**
     * Display process results
     */
    protected function displayProcessResults(array $results): void
    {
        if (empty($results)) {
            return;
        }

        $stats = [
            'created' => 0,
            'commented' => 0,
            'skipped_duplicate' => 0,
            'failed' => 0,
        ];

        foreach ($results as $result) {
            if ($result['success']) {
                $action = $result['action'] ?? 'unknown';
                if (isset($stats[$action])) {
                    $stats[$action]++;
                }
            } else {
                $stats['failed']++;
            }
        }

        $this->newLine();
        $this->table(
            ['Action', 'Count'],
            [
                ['🆕 New Tickets Created', $stats['created']],
                ['💬 Comments Added to Existing Tickets', $stats['commented']],
                ['🔁 Duplicates Skipped', $stats['skipped_duplicate']],
                ['❌ Processing Failed', $stats['failed']],
            ]
        );
    }

    /**
     * Display final summary
     */
    protected function displaySummary(array $results, float $executionTime): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════════╗');
        $this->info('║                           SUMMARY                                ║');
        $this->info('╚══════════════════════════════════════════════════════════════════╝');

        $totalFetched = $results['fetch_results']['total_fetched'] ?? 0;
        $totalProcessed = count(array_filter($results['process_results'] ?? [], fn($r) => $r['success'] ?? false));
        $totalFailed = count(array_filter($results['process_results'] ?? [], fn($r) => !($r['success'] ?? true)));

        $this->info("📧 Total emails fetched from shared mailboxes: {$totalFetched}");
        $this->info("✅ Successfully processed to tickets: {$totalProcessed}");

        if ($totalFailed > 0) {
            $this->warn("❌ Failed to process: {$totalFailed}");
        }

        $this->info(sprintf("⏱️ Total execution time: %.2f seconds", $executionTime));

        $this->line('');
        $this->line('💡 Tip: Run with --test-connections to verify all shared mailbox configurations');
        $this->line('📖 Use --detailed for detailed processing information');

        Log::info('Shared mailbox processing completed', [
            'emails_fetched' => $totalFetched,
            'emails_processed' => $totalProcessed,
            'emails_failed' => $totalFailed,
            'execution_time' => $executionTime,
        ]);
    }

    /**
     * Simulate mailbox fetch for dry run
     */
    protected function simulateMailboxFetch(EmailAccount $mailbox): array
    {
        $this->line("  [DRY RUN] Would fetch from: {$mailbox->name} ({$mailbox->email_address})");

        $simulatedCount = rand(0, 15);

        return [
            'success' => true,
            'total_fetched' => $simulatedCount,
            'results' => [[
                'mailbox_name' => $mailbox->name,
                'mailbox_address' => $mailbox->email_address,
                'success' => true,
                'emails_fetched' => $simulatedCount,
                'new_emails' => $simulatedCount,
                'duplicates_skipped' => 0,
                'errors' => []
            ]]
        ];
    }

    /**
     * Simulate email processing for dry run
     */
    protected function simulateEmailProcessing($pendingEmails): array
    {
        $results = [];

        foreach ($pendingEmails as $email) {
            $this->line("  [DRY RUN] Would process: {$email->subject} from {$email->from_address}");

            $actions = ['created', 'commented', 'skipped_duplicate'];
            $action = $actions[array_rand($actions)];

            $results[] = [
                'email_id' => $email->id,
                'success' => true,
                'action' => $action,
                'ticket_id' => $action === 'created' ? 'DRY-RUN-' . uniqid() : null,
            ];
        }

        return $results;
    }
}