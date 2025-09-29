<?php

namespace App\Console\Commands;

use App\Services\ImapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:fetch {--account-id= : Fetch emails for specific account only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch emails from configured IMAP accounts';

    protected $imapService;

    public function __construct(ImapService $imapService)
    {
        parent::__construct();
        $this->imapService = $imapService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting email fetch process...');

        try {
            $accountId = $this->option('account-id');

            if ($accountId) {
                $account = \App\Models\EmailAccount::find($accountId);
                if (!$account) {
                    $this->error("Email account with ID {$accountId} not found");
                    return 1;
                }

                $this->info("Fetching emails from account: {$account->name}");
                $result = $this->imapService->fetchEmailsFromAccount($account);

                $this->info("Fetched {$result['count']} emails from {$account->name}");
                if (!empty($result['errors'])) {
                    $this->warn("Encountered " . count($result['errors']) . " errors");
                }

            } else {
                $this->info('Fetching emails from all active accounts...');
                $results = $this->imapService->fetchAllEmails();

                $totalFetched = 0;
                $totalErrors = 0;

                foreach ($results as $result) {
                    if ($result['success']) {
                        $totalFetched += $result['emails_fetched'];
                        $this->info("✓ {$result['account_name']}: {$result['emails_fetched']} emails");
                    } else {
                        $totalErrors++;
                        $this->error("✗ {$result['account_name']}: {$result['error']}");
                    }
                }

                $this->info("Total emails fetched: {$totalFetched}");
                if ($totalErrors > 0) {
                    $this->warn("Accounts with errors: {$totalErrors}");
                }
            }

            $this->info('Email fetch process completed successfully');
            return 0;

        } catch (\Exception $e) {
            $this->error('Email fetch process failed: ' . $e->getMessage());
            Log::error('Email fetch command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}