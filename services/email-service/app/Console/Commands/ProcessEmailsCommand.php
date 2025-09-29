<?php

namespace App\Console\Commands;

use App\Services\EmailToTicketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending emails and convert them to tickets';

    protected $emailToTicketService;

    public function __construct(EmailToTicketService $emailToTicketService)
    {
        parent::__construct();
        $this->emailToTicketService = $emailToTicketService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting email processing...');

        try {
            $results = $this->emailToTicketService->processAllPendingEmails();

            $successful = 0;
            $failed = 0;

            foreach ($results as $result) {
                if ($result['success']) {
                    $successful++;
                    $action = $result['action'] ?? 'processed';
                    $ticketId = $result['ticket_id'] ?? 'N/A';
                    $this->info("✓ Email {$result['email_id']}: {$action} (Ticket: {$ticketId})");
                } else {
                    $failed++;
                    $this->error("✗ Email {$result['email_id']}: {$result['error']}");
                }
            }

            $this->info("\nEmail processing completed:");
            $this->info("- Successfully processed: {$successful}");
            if ($failed > 0) {
                $this->warn("- Failed to process: {$failed}");
            }

            return $failed > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error('Email processing failed: ' . $e->getMessage());
            Log::error('Email processing command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}