<?php

namespace App\Console\Commands;

use App\Services\ImapService;
use App\Services\EmailToTicketService;
use App\Models\EmailAccount;
use App\Models\EmailQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmailToTicketCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:to-tickets
                            {--account-id= : Process specific email account only}
                            {--fetch-only : Only fetch emails without processing}
                            {--process-only : Only process existing emails without fetching}
                            {--dry-run : Run without making actual changes}
                            {--limit=100 : Maximum number of emails to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch emails and convert them to tickets with duplicate detection and attachment handling';

    protected $imapService;
    protected $emailToTicketService;

    public function __construct(ImapService $imapService, EmailToTicketService $emailToTicketService)
    {
        parent::__construct();
        $this->imapService = $imapService;
        $this->emailToTicketService = $emailToTicketService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║           Email to Ticket Conversion Process Started          ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');

        $fetchOnly = $this->option('fetch-only');
        $processOnly = $this->option('process-only');
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual changes will be made');
        }

        $fetchResults = [];
        $processResults = [];

        try {
            // Step 1: Fetch emails unless process-only
            if (!$processOnly) {
                $this->info("\n📥 STEP 1: Fetching emails from configured accounts...");
                $fetchResults = $this->fetchEmails($dryRun);
                $this->displayFetchResults($fetchResults);
            }

            // Step 2: Process emails unless fetch-only
            if (!$fetchOnly) {
                $this->info("\n🎫 STEP 2: Processing emails to create tickets...");
                $processResults = $this->processEmails($limit, $dryRun);
                $this->displayProcessResults($processResults);
            }

            // Step 3: Generate summary
            $this->displaySummary($fetchResults, $processResults, microtime(true) - $startTime);

            return 0;

        } catch (\Exception $e) {
            $this->error("\n❌ Critical error during email-to-ticket conversion:");
            $this->error($e->getMessage());

            Log::error('Email-to-ticket command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'options' => [
                    'account-id' => $this->option('account-id'),
                    'fetch-only' => $fetchOnly,
                    'process-only' => $processOnly,
                    'dry-run' => $dryRun,
                    'limit' => $limit,
                ],
            ]);

            return 1;
        }
    }

    /**
     * Fetch emails from IMAP accounts
     */
    protected function fetchEmails(bool $dryRun = false): array
    {
        $results = [];
        $accountId = $this->option('account-id');

        if ($accountId) {
            $account = EmailAccount::find($accountId);
            if (!$account) {
                throw new \Exception("Email account with ID {$accountId} not found");
            }
            $accounts = collect([$account]);
        } else {
            $accounts = EmailAccount::active()->get();
        }

        if ($accounts->isEmpty()) {
            $this->warn('No active email accounts configured');
            return [];
        }

        $progressBar = $this->output->createProgressBar($accounts->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        foreach ($accounts as $account) {
            $progressBar->setMessage("Fetching from {$account->name}");
            $progressBar->advance();

            try {
                if ($dryRun) {
                    $result = $this->simulateFetch($account);
                } else {
                    $result = $this->imapService->fetchEmailsFromAccount($account);
                }

                $results[] = [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'success' => true,
                    'emails_fetched' => $result['count'],
                    'errors' => $result['errors'] ?? [],
                ];

            } catch (\Exception $e) {
                Log::error("Failed to fetch emails from account", [
                    'account_id' => $account->id,
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

        $progressBar->finish();
        $this->newLine();

        return $results;
    }

    /**
     * Process emails to create tickets
     */
    protected function processEmails(int $limit = 100, bool $dryRun = false): array
    {
        $results = [];

        // Get pending emails with retry logic
        $pendingEmails = EmailQueue::pending()
            ->orderBy('received_at')
            ->limit($limit)
            ->get();

        if ($pendingEmails->isEmpty()) {
            $this->info('No pending emails to process');
            return [];
        }

        $progressBar = $this->output->createProgressBar($pendingEmails->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        foreach ($pendingEmails as $email) {
            $progressBar->setMessage("Processing: " . substr($email->subject, 0, 50));
            $progressBar->advance();

            try {
                if ($dryRun) {
                    $result = $this->simulateProcessing($email);
                } else {
                    $result = $this->processEmailWithEnhancements($email);
                }

                $results[] = array_merge($result, [
                    'email_id' => $email->id,
                    'success' => true,
                ]);

            } catch (\Exception $e) {
                Log::error("Failed to process email", [
                    'email_id' => $email->id,
                    'error' => $e->getMessage(),
                ]);

                if (!$dryRun) {
                    $email->markAsFailed($e->getMessage());
                }

                $results[] = [
                    'email_id' => $email->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $progressBar->finish();
        $this->newLine();

        return $results;
    }

    /**
     * Enhanced email processing with better duplicate detection and assignment
     */
    protected function processEmailWithEnhancements(EmailQueue $email): array
    {
        DB::beginTransaction();

        try {
            // Check for duplicates first
            if ($this->isDuplicateEmail($email)) {
                $email->markAsProcessed();
                DB::commit();
                return [
                    'action' => 'skipped_duplicate',
                    'reason' => 'Duplicate email detected',
                ];
            }

            // Process the email through the service
            $result = $this->emailToTicketService->processEmail($email);

            // Apply automatic assignment if ticket was created
            if ($result['action'] === 'created' && isset($result['ticket_id'])) {
                $this->applyAutomaticAssignment($result['ticket_id'], $email);
            }

            DB::commit();
            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Enhanced duplicate detection
     */
    protected function isDuplicateEmail(EmailQueue $email): bool
    {
        // Check by message ID
        if (EmailQueue::where('message_id', $email->message_id)
            ->where('id', '!=', $email->id)
            ->where('is_processed', true)
            ->exists()) {
            return true;
        }

        // Check for very similar emails sent recently (within 5 minutes)
        $recentSimilar = EmailQueue::where('from_address', $email->from_address)
            ->where('id', '!=', $email->id)
            ->where('is_processed', true)
            ->where('received_at', '>=', date('Y-m-d H:i:s', strtotime('-5 minutes', strtotime($email->received_at))))
            ->get();

        foreach ($recentSimilar as $recent) {
            // Compare normalized subjects
            $subject1 = $this->normalizeText($email->subject);
            $subject2 = $this->normalizeText($recent->subject);

            if ($subject1 === $subject2) {
                // Check content similarity
                $content1 = $this->normalizeText($email->content);
                $content2 = $this->normalizeText($recent->content);

                $similarity = similar_text($content1, $content2, $percent);
                if ($percent > 90) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Apply automatic ticket assignment based on rules
     */
    protected function applyAutomaticAssignment(string $ticketId, EmailQueue $email): void
    {
        try {
            $assignmentRules = [
                // Priority-based assignment
                'urgent' => $this->getAvailableAgentByPriority('urgent'),
                'high' => $this->getAvailableAgentByPriority('high'),

                // Department-based assignment
                'sales@' => 'sales_department_id',
                'support@' => 'support_department_id',
                'billing@' => 'billing_department_id',
            ];

            $assignedAgentId = null;
            $assignedDepartmentId = null;

            // Check email patterns for department assignment
            foreach ($assignmentRules as $pattern => $department) {
                if (strpos($email->to_addresses[0] ?? '', $pattern) !== false) {
                    $assignedDepartmentId = env(strtoupper($department));
                    break;
                }
            }

            // Get least loaded agent in department
            if ($assignedDepartmentId) {
                $assignedAgentId = $this->getLeastLoadedAgent($assignedDepartmentId);
            }

            // If we have an assignment, update the ticket
            if ($assignedAgentId || $assignedDepartmentId) {
                $this->updateTicketAssignment($ticketId, $assignedAgentId, $assignedDepartmentId);
            }

        } catch (\Exception $e) {
            Log::warning("Failed to apply automatic assignment", [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get least loaded agent in department
     */
    protected function getLeastLoadedAgent(string $departmentId): ?string
    {
        // This would query the ticket service API to get agent workload
        // For now, returning null - implement based on your business logic
        return null;
    }

    /**
     * Get available agent by priority
     */
    protected function getAvailableAgentByPriority(string $priority): ?string
    {
        // Implement priority-based agent selection logic
        return null;
    }

    /**
     * Update ticket assignment via API
     */
    protected function updateTicketAssignment(string $ticketId, ?string $agentId, ?string $departmentId): void
    {
        $ticketServiceUrl = env('TICKET_SERVICE_URL', 'http://localhost:8002');

        $updateData = [];
        if ($agentId) {
            $updateData['assigned_agent_id'] = $agentId;
        }
        if ($departmentId) {
            $updateData['assigned_department_id'] = $departmentId;
        }

        if (!empty($updateData)) {
            \Illuminate\Support\Facades\Http::put("{$ticketServiceUrl}/api/v1/tickets/{$ticketId}", $updateData);
        }
    }

    /**
     * Normalize text for comparison
     */
    protected function normalizeText(string $text): string
    {
        // Remove whitespace, special characters, and make lowercase
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        return strtolower(trim($text));
    }

    /**
     * Display fetch results
     */
    protected function displayFetchResults(array $results): void
    {
        if (empty($results)) {
            return;
        }

        $totalFetched = 0;
        $failedAccounts = 0;

        $this->newLine();
        $this->table(
            ['Account', 'Status', 'Emails Fetched', 'Errors'],
            collect($results)->map(function ($result) use (&$totalFetched, &$failedAccounts) {
                if ($result['success']) {
                    $totalFetched += $result['emails_fetched'];
                    $status = '✅ Success';
                    $errors = count($result['errors'] ?? []);
                } else {
                    $failedAccounts++;
                    $status = '❌ Failed';
                    $errors = $result['error'] ?? 'Unknown error';
                }

                return [
                    $result['account_name'],
                    $status,
                    $result['emails_fetched'],
                    $errors,
                ];
            })->toArray()
        );

        $this->info("Total emails fetched: {$totalFetched}");
        if ($failedAccounts > 0) {
            $this->warn("Failed accounts: {$failedAccounts}");
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
                ['🆕 Tickets Created', $stats['created']],
                ['💬 Comments Added', $stats['commented']],
                ['🔁 Duplicates Skipped', $stats['skipped_duplicate']],
                ['❌ Failed', $stats['failed']],
            ]
        );
    }

    /**
     * Display final summary
     */
    protected function displaySummary(array $fetchResults, array $processResults, float $executionTime): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                         SUMMARY                               ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');

        $totalFetched = array_sum(array_column($fetchResults, 'emails_fetched'));
        $totalProcessed = count(array_filter($processResults, fn($r) => $r['success']));
        $totalFailed = count(array_filter($processResults, fn($r) => !$r['success']));

        $this->info("📧 Total emails fetched: {$totalFetched}");
        $this->info("✅ Successfully processed: {$totalProcessed}");
        if ($totalFailed > 0) {
            $this->warn("❌ Failed to process: {$totalFailed}");
        }
        $this->info(sprintf("⏱️ Execution time: %.2f seconds", $executionTime));

        Log::info('Email-to-ticket conversion completed', [
            'emails_fetched' => $totalFetched,
            'emails_processed' => $totalProcessed,
            'emails_failed' => $totalFailed,
            'execution_time' => $executionTime,
        ]);
    }

    /**
     * Simulate fetch for dry run
     */
    protected function simulateFetch(EmailAccount $account): array
    {
        $this->info("  [DRY RUN] Would fetch emails from: {$account->name}");
        return ['count' => rand(0, 10), 'errors' => []];
    }

    /**
     * Simulate processing for dry run
     */
    protected function simulateProcessing(EmailQueue $email): array
    {
        $this->info("  [DRY RUN] Would process email: {$email->subject}");
        return [
            'action' => 'created',
            'ticket_id' => 'DRY-RUN-' . uniqid(),
        ];
    }
}