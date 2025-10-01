<?php

namespace App\Http\Controllers;

use App\Models\EmailQueue;
use App\Models\EmailAccount;
use App\Services\ImapService;
use App\Services\EmailToTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WebhookEmailController extends Controller
{
    protected $imapService;
    protected $emailToTicketService;

    public function __construct(
        ImapService $imapService,
        EmailToTicketService $emailToTicketService
    ) {
        $this->imapService = $imapService;
        $this->emailToTicketService = $emailToTicketService;
    }

    /**
     * Instant webhook endpoint to process incoming emails
     * This is called immediately when an email arrives (e.g., from Gmail push notifications)
     */
    public function processIncoming(Request $request)
    {
        try {
            Log::info('Webhook: Incoming email notification received', [
                'payload' => $request->all()
            ]);

            // Option 1: If the webhook provides email data directly
            if ($request->has('email_data')) {
                return $this->processEmailData($request->input('email_data'));
            }

            // Option 2: If webhook only notifies of new email (need to fetch)
            if ($request->has('account_id')) {
                return $this->fetchAndProcess($request->input('account_id'));
            }

            // Option 3: Fetch from all accounts immediately
            return $this->fetchAndProcessAll();

        } catch (\Exception $e) {
            Log::error('Webhook: Failed to process incoming email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process email data directly from webhook payload
     */
    protected function processEmailData(array $emailData): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($emailData, [
            'account_id' => 'required|uuid',
            'from' => 'required|email',
            'to' => 'required|array',
            'subject' => 'required|string',
            'body_html' => 'nullable|string',
            'body_plain' => 'nullable|string',
            'message_id' => 'required|string',
            'headers' => 'nullable|array',
            'attachments' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find email account
        $account = EmailAccount::find($emailData['account_id']);
        if (!$account) {
            return response()->json([
                'success' => false,
                'error' => 'Email account not found'
            ], 404);
        }

        // Create email queue entry
        $email = EmailQueue::create([
            'email_account_id' => $account->id,
            'message_id' => $emailData['message_id'],
            'from_address' => $emailData['from'],
            'to_addresses' => $emailData['to'],
            'cc_addresses' => $emailData['cc'] ?? [],
            'subject' => $emailData['subject'],
            'body_html' => $emailData['body_html'] ?? null,
            'body_plain' => $emailData['body_plain'] ?? null,
            'headers' => $emailData['headers'] ?? [],
            'attachments' => $emailData['attachments'] ?? [],
            'received_at' => \Carbon\Carbon::now(),
            'is_processed' => false,
        ]);

        Log::info('Webhook: Email queued from direct data', [
            'email_id' => $email->id,
            'message_id' => $email->message_id,
            'from' => $email->from_address,
            'subject' => $email->subject
        ]);

        // Process immediately to ticket
        try {
            $result = $this->emailToTicketService->processEmail($email);

            Log::info('Webhook: Email processed to ticket instantly', [
                'email_id' => $email->id,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email processed instantly',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook: Failed to process email to ticket', [
                'email_id' => $email->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch new emails from specific account and process instantly
     */
    protected function fetchAndProcess(string $accountId): \Illuminate\Http\JsonResponse
    {
        $account = EmailAccount::find($accountId);
        if (!$account) {
            return response()->json([
                'success' => false,
                'error' => 'Email account not found'
            ], 404);
        }

        Log::info('Webhook: Fetching new emails from account', [
            'account_id' => $account->id,
            'email' => $account->email_address
        ]);

        // Fetch new emails
        $fetchResult = $this->imapService->fetchEmailsFromAccount($account);

        if (!$fetchResult['success']) {
            return response()->json([
                'success' => false,
                'error' => $fetchResult['error'] ?? 'Failed to fetch emails'
            ], 500);
        }

        // Process all newly fetched emails immediately
        $processResults = [];
        $newEmails = EmailQueue::where('email_account_id', $account->id)
            ->where('is_processed', false)
            ->orderBy('received_at')
            ->get();

        foreach ($newEmails as $email) {
            try {
                $result = $this->emailToTicketService->processEmail($email);
                $processResults[] = [
                    'email_id' => $email->id,
                    'success' => true,
                    'action' => $result['action'],
                    'ticket_id' => $result['ticket_id'],
                ];
            } catch (\Exception $e) {
                $processResults[] = [
                    'email_id' => $email->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info('Webhook: Processed emails from account', [
            'account_id' => $account->id,
            'total' => count($processResults),
            'successful' => count(array_filter($processResults, fn($r) => $r['success']))
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Emails fetched and processed instantly',
            'data' => [
                'fetched_count' => $fetchResult['fetched_count'] ?? 0,
                'processed' => $processResults
            ]
        ]);
    }

    /**
     * Fetch from all accounts and process instantly
     */
    protected function fetchAndProcessAll(): \Illuminate\Http\JsonResponse
    {
        Log::info('Webhook: Fetching from all active accounts');

        $results = $this->imapService->fetchAllEmails();

        // Process all newly fetched emails immediately
        $processResults = [];
        $newEmails = EmailQueue::where('is_processed', false)
            ->orderBy('received_at')
            ->get();

        foreach ($newEmails as $email) {
            try {
                $result = $this->emailToTicketService->processEmail($email);
                $processResults[] = [
                    'email_id' => $email->id,
                    'success' => true,
                    'action' => $result['action'],
                    'ticket_id' => $result['ticket_id'],
                ];

                Log::info('Webhook: Email processed instantly', [
                    'email_id' => $email->id,
                    'action' => $result['action'],
                    'ticket_id' => $result['ticket_id']
                ]);

            } catch (\Exception $e) {
                $processResults[] = [
                    'email_id' => $email->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                Log::error('Webhook: Failed to process email', [
                    'email_id' => $email->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'All emails fetched and processed instantly',
            'data' => [
                'accounts_checked' => count($results),
                'processed' => $processResults,
                'successful' => count(array_filter($processResults, fn($r) => $r['success'])),
                'failed' => count(array_filter($processResults, fn($r) => !$r['success']))
            ]
        ]);
    }

    /**
     * Manual trigger endpoint - fetch and process immediately
     * Can be called via API or curl for testing
     */
    public function trigger(Request $request)
    {
        $accountId = $request->input('account_id');

        if ($accountId) {
            return $this->fetchAndProcess($accountId);
        }

        return $this->fetchAndProcessAll();
    }

    /**
     * Health check for webhook endpoint
     */
    public function health()
    {
        return response()->json([
            'success' => true,
            'service' => 'Email Webhook Processor',
            'status' => 'healthy',
            'features' => [
                'instant_processing' => true,
                'message_id_threading' => true,
                'auto_ticket_matching' => true
            ],
            'timestamp' => \Carbon\Carbon::now()->toISOString()
        ]);
    }
}
