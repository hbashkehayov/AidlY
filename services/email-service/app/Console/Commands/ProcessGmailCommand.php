<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Models\EmailQueue;
use App\Services\EmailToTicketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessGmailCommand extends Command
{
    protected $signature = 'gmail:fetch {--limit=50 : Maximum number of emails to process}';
    protected $description = 'Fetch emails from Gmail using PHP IMAP (working alternative)';

    protected $emailToTicketService;

    public function __construct(EmailToTicketService $emailToTicketService)
    {
        parent::__construct();
        $this->emailToTicketService = $emailToTicketService;
    }

    public function handle()
    {
        $limit = $this->option('limit');

        $emailAccount = EmailAccount::where('email_address', 'hristiyan.bashkehayov@gmail.com')->first();

        if (!$emailAccount) {
            $this->error('Email account not found');
            return 1;
        }

        $this->info('Fetching emails from Gmail...');

        // Connect to Gmail IMAP
        $mailbox = "{imap.gmail.com:993/imap/ssl}INBOX";
        $username = $emailAccount->imap_username;
        $password = $emailAccount->imap_password; // This will decrypt automatically

        $imap = @imap_open($mailbox, $username, $password);

        if (!$imap) {
            $this->error('Failed to connect: ' . imap_last_error());
            return 1;
        }

        // Search for unread emails
        $emails = imap_search($imap, 'UNSEEN');

        if (!$emails) {
            $this->info('No new emails found');
            imap_close($imap);
            return 0;
        }

        $this->info('Found ' . count($emails) . ' unread emails');

        $processed = 0;
        $created = 0;

        foreach (array_slice($emails, 0, $limit) as $emailNumber) {
            try {
                $header = imap_headerinfo($imap, $emailNumber);
                $structure = imap_fetchstructure($imap, $emailNumber);

                // Check if already processed
                $messageId = $header->message_id ?? uniqid();

                if (EmailQueue::where('message_id', $messageId)->exists()) {
                    $this->info("Skipping duplicate: {$header->subject}");
                    continue;
                }

                // Get email body
                $body = $this->getBody($imap, $emailNumber, $structure);

                // Prepare email data
                $emailData = [
                    'email_account_id' => $emailAccount->id,
                    'message_id' => $messageId,
                    'from_address' => $header->from[0]->mailbox . '@' . $header->from[0]->host,
                    'from_name' => $header->from[0]->personal ?? '',
                    'to_addresses' => $this->extractAddresses($header->to ?? []),
                    'cc_addresses' => $this->extractAddresses($header->cc ?? []),
                    'subject' => $header->subject ?? '(no subject)',
                    'body_plain' => $body['plain'] ?? '',
                    'body_html' => $body['html'] ?? '',
                    'content' => $body['plain'] ?? strip_tags($body['html'] ?? ''),
                    'received_at' => date('Y-m-d H:i:s', $header->udate),
                    'is_processed' => false,
                    'mailbox_type' => 'shared',
                    'original_recipient' => $emailAccount->email_address,
                ];

                // Save to queue
                $queuedEmail = EmailQueue::create($emailData);
                $created++;

                $this->info("Queued: {$header->subject}");

                // Mark as read
                imap_setflag_full($imap, $emailNumber, "\\Seen");

                // Process to ticket if auto-create is enabled
                if ($emailAccount->auto_create_tickets) {
                    try {
                        $result = $this->emailToTicketService->processQueuedEmail($queuedEmail);
                        if ($result['success']) {
                            $this->info("  → Ticket created: #{$result['ticket_id']}");
                        }
                    } catch (\Exception $e) {
                        $this->error("  → Failed to create ticket: " . $e->getMessage());
                    }
                }

                $processed++;

            } catch (\Exception $e) {
                $this->error("Error processing email: " . $e->getMessage());
            }
        }

        imap_close($imap);

        // Update last sync time
        $emailAccount->update(['last_sync_at' => now()]);

        $this->info("\nCompleted:");
        $this->info("- Processed: $processed emails");
        $this->info("- Created: $created new queue entries");

        return 0;
    }

    private function getBody($imap, $emailNumber, $structure)
    {
        $body = ['plain' => '', 'html' => ''];

        if ($structure->type == 0) { // Text
            $body['plain'] = imap_fetchbody($imap, $emailNumber, 1);
        } elseif ($structure->type == 1) { // Multipart
            foreach ($structure->parts as $partNum => $part) {
                $partNumber = $partNum + 1;

                if ($part->type == 0) { // Text
                    $data = imap_fetchbody($imap, $emailNumber, $partNumber);

                    if ($part->encoding == 3) { // BASE64
                        $data = base64_decode($data);
                    } elseif ($part->encoding == 4) { // QUOTED-PRINTABLE
                        $data = quoted_printable_decode($data);
                    }

                    if ($part->subtype == 'PLAIN') {
                        $body['plain'] = $data;
                    } elseif ($part->subtype == 'HTML') {
                        $body['html'] = $data;
                    }
                }
            }
        }

        return $body;
    }

    private function extractAddresses($addresses)
    {
        $result = [];
        foreach ($addresses as $address) {
            $result[] = $address->mailbox . '@' . $address->host;
        }
        return $result;
    }
}